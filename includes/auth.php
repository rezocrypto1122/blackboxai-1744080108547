<?php
require_once 'config.php';
require_once 'db.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Register a new user
     * @param array $userData
     * @return array
     */
    public function register($userData) {
        try {
            // Validate input
            if (empty($userData['username']) || empty($userData['email']) || 
                empty($userData['password']) || empty($userData['wallet_address'])) {
                return ['success' => false, 'message' => 'All fields are required'];
            }

            // Validate email
            if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }

            // Validate wallet address
            if (!isValidWalletAddress($userData['wallet_address'])) {
                return ['success' => false, 'message' => 'Invalid wallet address'];
            }

            // Check if email exists
            $existingUser = $this->db->getRow(
                "SELECT id FROM users WHERE email = ?", 
                [$userData['email']]
            );
            if ($existingUser) {
                return ['success' => false, 'message' => 'Email already exists'];
            }

            // Check if username exists
            $existingUsername = $this->db->getRow(
                "SELECT id FROM users WHERE username = ?", 
                [$userData['username']]
            );
            if ($existingUsername) {
                return ['success' => false, 'message' => 'Username already exists'];
            }

            // Generate referral code
            $referralCode = $this->generateUniqueReferralCode();

            // Process referral if provided
            $referredBy = null;
            $referralLevel = 0;
            if (!empty($userData['referral_code'])) {
                $referrer = $this->db->getRow(
                    "SELECT id, referral_level FROM users WHERE referral_code = ?",
                    [$userData['referral_code']]
                );
                if ($referrer) {
                    $referredBy = $referrer['id'];
                    $referralLevel = $referrer['referral_level'] + 1;
                }
            }

            // Hash password
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);

            // Begin transaction
            $this->db->beginTransaction();

            // Insert user
            $success = $this->db->query(
                "INSERT INTO users (username, email, password, wallet_address, referral_code, referred_by, referral_level) 
                VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $userData['username'],
                    $userData['email'],
                    $hashedPassword,
                    $userData['wallet_address'],
                    $referralCode,
                    $referredBy,
                    $referralLevel
                ]
            );

            if (!$success) {
                $this->db->rollback();
                return ['success' => false, 'message' => 'Registration failed'];
            }

            $this->db->commit();
            return ['success' => true, 'message' => 'Registration successful'];

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }

    /**
     * Login user
     * @param string $email
     * @param string $password
     * @return array
     */
    public function login($email, $password) {
        try {
            // Get user by email
            $user = $this->db->getRow(
                "SELECT id, username, email, password FROM users WHERE email = ?",
                [$email]
            );

            if (!$user || !password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Invalid email or password'];
            }

            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];

            // Regenerate session ID for security
            session_regenerate_id(true);

            return ['success' => true, 'message' => 'Login successful'];

        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed'];
        }
    }

    /**
     * Admin login
     * @param string $email
     * @param string $password
     * @return array
     */
    public function adminLogin($email, $password) {
        try {
            if ($email !== ADMIN_EMAIL) {
                return ['success' => false, 'message' => 'Invalid admin credentials'];
            }

            // In production, use a proper admin table or configuration
            $adminPassword = 'admin123'; // Change this in production!
            
            if ($password !== $adminPassword) {
                return ['success' => false, 'message' => 'Invalid admin credentials'];
            }

            $_SESSION['admin'] = true;
            $_SESSION['admin_email'] = $email;

            // Regenerate session ID for security
            session_regenerate_id(true);

            return ['success' => true, 'message' => 'Admin login successful'];

        } catch (Exception $e) {
            error_log("Admin login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Admin login failed'];
        }
    }

    /**
     * Check if user is logged in
     * @return bool
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    /**
     * Check if admin is logged in
     * @return bool
     */
    public function isAdminLoggedIn() {
        return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
    }

    /**
     * Logout user or admin
     */
    public function logout() {
        session_unset();
        session_destroy();
        return ['success' => true, 'message' => 'Logout successful'];
    }

    /**
     * Generate unique referral code
     * @return string
     */
    private function generateUniqueReferralCode() {
        do {
            $code = generateRandomString(8);
            $exists = $this->db->getRow(
                "SELECT id FROM users WHERE referral_code = ?",
                [$code]
            );
        } while ($exists);

        return $code;
    }

    /**
     * Get user details
     * @param int $userId
     * @return array|false
     */
    public function getUserDetails($userId) {
        return $this->db->getRow(
            "SELECT id, username, email, wallet_address, profit_balance, 
            bonus_balance, referral_code, referred_by, referral_level, created_at 
            FROM users WHERE id = ?",
            [$userId]
        );
    }

    /**
     * Update user profile
     * @param int $userId
     * @param array $data
     * @return array
     */
    public function updateProfile($userId, $data) {
        try {
            $updates = [];
            $params = [];

            // Only allow updating certain fields
            $allowedFields = ['username', 'email', 'wallet_address'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updates)) {
                return ['success' => false, 'message' => 'No fields to update'];
            }

            $params[] = $userId;
            $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            
            $success = $this->db->query($query, $params);

            return $success ? 
                ['success' => true, 'message' => 'Profile updated successfully'] :
                ['success' => false, 'message' => 'Profile update failed'];

        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Profile update failed'];
        }
    }
}

// Example usage:
// $auth = new Auth();
// $result = $auth->register([
//     'username' => 'john_doe',
//     'email' => 'john@example.com',
//     'password' => 'secure123',
//     'wallet_address' => '0x...',
//     'referral_code' => 'ABC123' // optional
// ]);