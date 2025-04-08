<?php
require_once 'config.php';
require_once 'db.php';

class Wallet {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get user's wallet balances
     * @param int $userId
     * @return array|false
     */
    public function getBalances($userId) {
        try {
            return $this->db->getRow(
                "SELECT profit_balance, bonus_balance FROM users WHERE id = ?",
                [$userId]
            );
        } catch (Exception $e) {
            error_log("Get balances error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Request profit withdrawal
     * @param array $data
     * @return array
     */
    public function withdrawProfit($data) {
        try {
            if (!isset($data['user_id'], $data['amount'], $data['wallet_address'])) {
                return ['success' => false, 'message' => 'Missing required data'];
            }

            // Validate wallet address
            if (!isValidWalletAddress($data['wallet_address'])) {
                return ['success' => false, 'message' => 'Invalid wallet address'];
            }

            // Begin transaction
            $this->db->beginTransaction();

            try {
                // Get current profit balance
                $user = $this->db->getRow(
                    "SELECT profit_balance FROM users WHERE id = ? FOR UPDATE",
                    [$data['user_id']]
                );

                if (!$user) {
                    throw new Exception("User not found");
                }

                if ($user['profit_balance'] < $data['amount']) {
                    throw new Exception("Insufficient profit balance");
                }

                // Update user's profit balance
                $success = $this->db->query(
                    "UPDATE users SET profit_balance = profit_balance - ? WHERE id = ?",
                    [$data['amount'], $data['user_id']]
                );

                if (!$success) {
                    throw new Exception("Failed to update profit balance");
                }

                // Create withdrawal transaction
                $success = $this->db->query(
                    "INSERT INTO transactions (user_id, type, amount, wallet_address) 
                    VALUES (?, 'withdrawal', ?, ?)",
                    [$data['user_id'], $data['amount'], $data['wallet_address']]
                );

                if (!$success) {
                    throw new Exception("Failed to create withdrawal transaction");
                }

                $this->db->commit();
                return [
                    'success' => true,
                    'message' => 'Profit withdrawal request submitted successfully'
                ];

            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Profit withdrawal error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Request bonus withdrawal
     * @param array $data
     * @return array
     */
    public function withdrawBonus($data) {
        try {
            if (!isset($data['user_id'], $data['amount'], $data['wallet_address'])) {
                return ['success' => false, 'message' => 'Missing required data'];
            }

            // Validate wallet address
            if (!isValidWalletAddress($data['wallet_address'])) {
                return ['success' => false, 'message' => 'Invalid wallet address'];
            }

            // Begin transaction
            $this->db->beginTransaction();

            try {
                // Get current bonus balance
                $user = $this->db->getRow(
                    "SELECT bonus_balance FROM users WHERE id = ? FOR UPDATE",
                    [$data['user_id']]
                );

                if (!$user) {
                    throw new Exception("User not found");
                }

                if ($user['bonus_balance'] < $data['amount']) {
                    throw new Exception("Insufficient bonus balance");
                }

                // Update user's bonus balance
                $success = $this->db->query(
                    "UPDATE users SET bonus_balance = bonus_balance - ? WHERE id = ?",
                    [$data['amount'], $data['user_id']]
                );

                if (!$success) {
                    throw new Exception("Failed to update bonus balance");
                }

                // Create withdrawal transaction
                $success = $this->db->query(
                    "INSERT INTO transactions (user_id, type, amount, wallet_address) 
                    VALUES (?, 'withdrawal', ?, ?)",
                    [$data['user_id'], $data['amount'], $data['wallet_address']]
                );

                if (!$success) {
                    throw new Exception("Failed to create withdrawal transaction");
                }

                $this->db->commit();
                return [
                    'success' => true,
                    'message' => 'Bonus withdrawal request submitted successfully'
                ];

            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Bonus withdrawal error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get user's transaction history
     * @param int $userId
     * @param array $filters
     * @return array|false
     */
    public function getTransactionHistory($userId, $filters = []) {
        try {
            $query = "SELECT * FROM transactions WHERE user_id = ?";
            $params = [$userId];

            // Add type filter if specified
            if (!empty($filters['type'])) {
                $query .= " AND type = ?";
                $params[] = $filters['type'];
            }

            // Add status filter if specified
            if (!empty($filters['status'])) {
                $query .= " AND status = ?";
                $params[] = $filters['status'];
            }

            // Add date range filter if specified
            if (!empty($filters['start_date'])) {
                $query .= " AND created_at >= ?";
                $params[] = $filters['start_date'];
            }
            if (!empty($filters['end_date'])) {
                $query .= " AND created_at <= ?";
                $params[] = $filters['end_date'];
            }

            $query .= " ORDER BY created_at DESC";

            // Add pagination if specified
            if (!empty($filters['limit'])) {
                $query .= " LIMIT ?";
                $params[] = (int)$filters['limit'];

                if (!empty($filters['offset'])) {
                    $query .= " OFFSET ?";
                    $params[] = (int)$filters['offset'];
                }
            }

            return $this->db->getRows($query, $params);

        } catch (Exception $e) {
            error_log("Get transaction history error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get transaction details
     * @param int $transactionId
     * @param int $userId
     * @return array|false
     */
    public function getTransactionDetails($transactionId, $userId) {
        try {
            return $this->db->getRow(
                "SELECT * FROM transactions WHERE id = ? AND user_id = ?",
                [$transactionId, $userId]
            );
        } catch (Exception $e) {
            error_log("Get transaction details error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get total statistics for user
     * @param int $userId
     * @return array|false
     */
    public function getUserStats($userId) {
        try {
            $stats = [
                'total_invested' => 0,
                'total_profit' => 0,
                'total_bonus' => 0,
                'active_investments' => 0
            ];

            // Get total invested amount
            $result = $this->db->getRow(
                "SELECT COALESCE(SUM(amount), 0) as total FROM investments WHERE user_id = ?",
                [$userId]
            );
            $stats['total_invested'] = $result['total'];

            // Get total profit
            $result = $this->db->getRow(
                "SELECT COALESCE(SUM(amount), 0) as total FROM transactions 
                WHERE user_id = ? AND type = 'profit'",
                [$userId]
            );
            $stats['total_profit'] = $result['total'];

            // Get total bonus
            $result = $this->db->getRow(
                "SELECT COALESCE(SUM(amount), 0) as total FROM transactions 
                WHERE user_id = ? AND type = 'referral_bonus'",
                [$userId]
            );
            $stats['total_bonus'] = $result['total'];

            // Get count of active investments
            $result = $this->db->getRow(
                "SELECT COUNT(*) as count FROM investments 
                WHERE user_id = ? AND status = 'active'",
                [$userId]
            );
            $stats['active_investments'] = $result['count'];

            return $stats;

        } catch (Exception $e) {
            error_log("Get user stats error: " . $e->getMessage());
            return false;
        }
    }
}

// Example usage:
// $wallet = new Wallet();
// $balances = $wallet->getBalances(1);
// $result = $wallet->withdrawProfit([
//     'user_id' => 1,
//     'amount' => 100,
//     'wallet_address' => '0x...'
// ]);