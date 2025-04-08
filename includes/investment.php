<?php
require_once 'config.php';
require_once 'db.php';

class Investment {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new investment
     * @param array $data
     * @return array
     */
    public function createInvestment($data) {
        try {
            // Validate package
            if (!isset(PACKAGES[$data['package_id']])) {
                return ['success' => false, 'message' => 'Invalid investment package'];
            }

            $package = PACKAGES[$data['package_id']];

            // Validate amount
            if ($data['amount'] < $package['min'] || $data['amount'] > $package['max']) {
                return [
                    'success' => false, 
                    'message' => "Amount must be between $" . $package['min'] . " and $" . $package['max']
                ];
            }

            // Calculate end date
            $endDate = date('Y-m-d H:i:s', strtotime('+' . $package['duration'] . ' days'));

            // Begin transaction
            $this->db->beginTransaction();

            try {
                // Create investment record
                $success = $this->db->query(
                    "INSERT INTO investments (user_id, package_id, amount, daily_profit, contract_duration, end_date) 
                    VALUES (?, ?, ?, ?, ?, ?)",
                    [
                        $data['user_id'],
                        $data['package_id'],
                        $data['amount'],
                        $package['profit'],
                        $package['duration'],
                        $endDate
                    ]
                );

                if (!$success) {
                    throw new Exception("Failed to create investment record");
                }

                $investmentId = $this->db->lastInsertId();

                // Create transaction record for deposit
                $success = $this->db->query(
                    "INSERT INTO transactions (user_id, type, amount, wallet_address) 
                    VALUES (?, 'deposit', ?, ?)",
                    [
                        $data['user_id'],
                        $data['amount'],
                        $data['wallet_address']
                    ]
                );

                if (!$success) {
                    throw new Exception("Failed to create transaction record");
                }

                // Process referral bonuses
                $this->processReferralBonuses($data['user_id'], $data['amount']);

                $this->db->commit();
                return [
                    'success' => true,
                    'message' => 'Investment created successfully',
                    'investment_id' => $investmentId
                ];

            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Investment creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create investment'];
        }
    }

    /**
     * Process referral bonuses for an investment
     * @param int $userId
     * @param float $amount
     */
    private function processReferralBonuses($userId, $amount) {
        try {
            // Get user's referral chain
            $user = $this->db->getRow(
                "SELECT referred_by, referral_level FROM users WHERE id = ?",
                [$userId]
            );

            if (!$user || !$user['referred_by']) {
                return;
            }

            $currentReferrerId = $user['referred_by'];
            $currentLevel = 1;

            // Process up to 5 levels of referrals
            while ($currentReferrerId && $currentLevel <= 5) {
                // Get bonus percentage for current level
                $bonusPercentage = REFERRAL_BONUSES[$currentLevel];
                $bonusAmount = $amount * $bonusPercentage;

                // Update referrer's bonus balance
                $this->db->query(
                    "UPDATE users SET bonus_balance = bonus_balance + ? WHERE id = ?",
                    [$bonusAmount, $currentReferrerId]
                );

                // Create transaction record for referral bonus
                $this->db->query(
                    "INSERT INTO transactions (user_id, type, amount) 
                    VALUES (?, 'referral_bonus', ?)",
                    [$currentReferrerId, $bonusAmount]
                );

                // Get next referrer in chain
                $referrer = $this->db->getRow(
                    "SELECT referred_by FROM users WHERE id = ?",
                    [$currentReferrerId]
                );

                $currentReferrerId = $referrer ? $referrer['referred_by'] : null;
                $currentLevel++;
            }

        } catch (Exception $e) {
            error_log("Referral bonus processing error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process daily profits for all active investments
     * This should be run by a cron job
     */
    public function processDailyProfits() {
        try {
            // Get all active investments
            $investments = $this->db->getRows(
                "SELECT * FROM investments WHERE status = 'active' AND end_date > NOW()"
            );

            foreach ($investments as $investment) {
                $this->db->beginTransaction();

                try {
                    // Calculate daily profit
                    $dailyProfit = $investment['amount'] * $investment['daily_profit'];

                    // Update investment total profit
                    $this->db->query(
                        "UPDATE investments SET 
                        total_profit = total_profit + ?,
                        last_profit_update = NOW()
                        WHERE id = ?",
                        [$dailyProfit, $investment['id']]
                    );

                    // Update user's profit balance
                    $this->db->query(
                        "UPDATE users SET profit_balance = profit_balance + ? 
                        WHERE id = ?",
                        [$dailyProfit, $investment['user_id']]
                    );

                    // Create transaction record for profit
                    $this->db->query(
                        "INSERT INTO transactions (user_id, type, amount) 
                        VALUES (?, 'profit', ?)",
                        [$investment['user_id'], $dailyProfit]
                    );

                    $this->db->commit();

                } catch (Exception $e) {
                    $this->db->rollback();
                    throw $e;
                }
            }

            // Check for completed investments
            $this->db->query(
                "UPDATE investments SET status = 'completed' 
                WHERE status = 'active' AND end_date <= NOW()"
            );

            return ['success' => true, 'message' => 'Daily profits processed successfully'];

        } catch (Exception $e) {
            error_log("Daily profit processing error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to process daily profits'];
        }
    }

    /**
     * Get user's investments
     * @param int $userId
     * @return array
     */
    public function getUserInvestments($userId) {
        try {
            return $this->db->getRows(
                "SELECT * FROM investments WHERE user_id = ? ORDER BY created_at DESC",
                [$userId]
            );
        } catch (Exception $e) {
            error_log("Get user investments error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get investment details
     * @param int $investmentId
     * @param int $userId
     * @return array|false
     */
    public function getInvestmentDetails($investmentId, $userId) {
        try {
            return $this->db->getRow(
                "SELECT * FROM investments WHERE id = ? AND user_id = ?",
                [$investmentId, $userId]
            );
        } catch (Exception $e) {
            error_log("Get investment details error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all investment packages
     * @return array
     */
    public function getPackages() {
        return PACKAGES;
    }
}

// Example usage:
// $investment = new Investment();
// $result = $investment->createInvestment([
//     'user_id' => 1,
//     'package_id' => 1,
//     'amount' => 100,
//     'wallet_address' => '0x...'
// ]);