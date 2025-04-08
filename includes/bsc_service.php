<?php
require_once 'config.php';
require_once 'db.php';

class BSCService {
    private $db;
    private $apiEndpoint;
    private $contractAddress;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->apiEndpoint = BSC_NODE_URL;
        $this->contractAddress = BSC_CONTRACT_ADDRESS;
    }

    /**
     * Verify USDT deposit on BSC
     * @param string $txHash Transaction hash
     * @return array
     */
    public function verifyDeposit($txHash) {
        try {
            // Make API call to BSC node to get transaction details
            $response = $this->callBscApi([
                'jsonrpc' => '2.0',
                'method' => 'eth_getTransactionReceipt',
                'params' => [$txHash],
                'id' => 1
            ]);

            if (!$response || !isset($response['result'])) {
                throw new Exception('Failed to get transaction receipt');
            }

            $receipt = $response['result'];

            // Verify transaction status
            if ($receipt['status'] !== '0x1') {
                throw new Exception('Transaction failed on blockchain');
            }

            // Verify it's a transfer to our contract address
            if (strtolower($receipt['to']) !== strtolower($this->contractAddress)) {
                throw new Exception('Invalid recipient address');
            }

            // Get transaction amount from logs
            $amount = $this->parseTransferAmount($receipt['logs']);

            return [
                'success' => true,
                'amount' => $amount,
                'from' => $receipt['from']
            ];

        } catch (Exception $e) {
            error_log("Deposit verification error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Process pending deposits
     * This should be run by a cron job
     */
    public function processPendingDeposits() {
        try {
            // Get all pending deposit transactions
            $pendingDeposits = $this->db->getRows(
                "SELECT t.*, i.id as investment_id 
                FROM transactions t 
                LEFT JOIN investments i ON i.user_id = t.user_id 
                WHERE t.type = 'deposit' AND t.status = 'pending' AND t.tx_hash IS NOT NULL"
            );

            foreach ($pendingDeposits as $deposit) {
                $this->db->beginTransaction();

                try {
                    // Verify deposit on blockchain
                    $verification = $this->verifyDeposit($deposit['tx_hash']);

                    if ($verification['success']) {
                        // Update transaction status
                        $this->db->query(
                            "UPDATE transactions SET status = 'completed' WHERE id = ?",
                            [$deposit['id']]
                        );

                        // Update investment status if exists
                        if ($deposit['investment_id']) {
                            $this->db->query(
                                "UPDATE investments SET status = 'active' WHERE id = ?",
                                [$deposit['investment_id']]
                            );
                        }

                        $this->db->commit();
                    }

                } catch (Exception $e) {
                    $this->db->rollback();
                    error_log("Error processing deposit {$deposit['id']}: " . $e->getMessage());
                }
            }

            return ['success' => true, 'message' => 'Pending deposits processed'];

        } catch (Exception $e) {
            error_log("Process pending deposits error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Process withdrawal request
     * @param array $withdrawal Transaction record
     * @return array
     */
    public function processWithdrawal($withdrawal) {
        try {
            // In a real implementation, this would:
            // 1. Create and sign a transaction using your platform's wallet
            // 2. Broadcast the transaction to the BSC network
            // 3. Wait for confirmation
            // 4. Update the transaction status

            // For demo purposes, we'll simulate the process
            $success = true; // In reality, this would depend on the actual transaction
            
            if ($success) {
                // Update transaction status
                $this->db->query(
                    "UPDATE transactions SET status = 'completed' WHERE id = ?",
                    [$withdrawal['id']]
                );

                return [
                    'success' => true,
                    'message' => 'Withdrawal processed successfully'
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to process withdrawal'
            ];

        } catch (Exception $e) {
            error_log("Process withdrawal error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Make API call to BSC node
     * @param array $data
     * @return array
     */
    private function callBscApi($data) {
        try {
            $ch = curl_init($this->apiEndpoint);
            
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data)
            ]);

            $response = curl_exec($ch);
            $error = curl_error($ch);
            
            curl_close($ch);

            if ($error) {
                throw new Exception("cURL Error: $error");
            }

            return json_decode($response, true);

        } catch (Exception $e) {
            error_log("BSC API call error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Parse transfer amount from transaction logs
     * @param array $logs
     * @return float
     */
    private function parseTransferAmount($logs) {
        // In a real implementation, this would:
        // 1. Find the Transfer event log
        // 2. Decode the amount from the data field
        // 3. Convert from wei to USDT (considering decimals)
        
        // For demo purposes, we'll return a simulated amount
        return 100.00;
    }

    /**
     * Get BSC transaction status
     * @param string $txHash
     * @return array
     */
    public function getTransactionStatus($txHash) {
        try {
            $response = $this->callBscApi([
                'jsonrpc' => '2.0',
                'method' => 'eth_getTransactionReceipt',
                'params' => [$txHash],
                'id' => 1
            ]);

            if (!$response || !isset($response['result'])) {
                return ['success' => false, 'message' => 'Transaction not found'];
            }

            $receipt = $response['result'];
            
            return [
                'success' => true,
                'status' => $receipt['status'] === '0x1' ? 'confirmed' : 'failed',
                'blockNumber' => hexdec($receipt['blockNumber']),
                'gasUsed' => hexdec($receipt['gasUsed'])
            ];

        } catch (Exception $e) {
            error_log("Get transaction status error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

// Example usage:
// $bsc = new BSCService();
// $result = $bsc->verifyDeposit('0x...');
// $status = $bsc->getTransactionStatus('0x...');