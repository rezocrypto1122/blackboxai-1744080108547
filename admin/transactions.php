<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/bsc_service.php';

// Initialize Auth class
$auth = new Auth();

// Check if admin is logged in
if (!$auth->isAdminLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Initialize Database connection
$db = Database::getInstance();

// Handle transaction approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['transaction_id'])) {
    try {
        $transactionId = $_POST['transaction_id'];
        $action = $_POST['action'];

        $transaction = $db->getRow(
            "SELECT * FROM transactions WHERE id = ?",
            [$transactionId]
        );

        if ($transaction) {
            if ($action === 'approve') {
                // For withdrawals, process through BSC service
                if ($transaction['type'] === 'withdrawal') {
                    $bsc = new BSCService();
                    $result = $bsc->processWithdrawal($transaction);
                    
                    if ($result['success']) {
                        $db->query(
                            "UPDATE transactions SET status = 'completed' WHERE id = ?",
                            [$transactionId]
                        );
                        $success = "Transaction approved and processed successfully";
                    } else {
                        $error = "Failed to process withdrawal: " . $result['message'];
                    }
                } else {
                    $db->query(
                        "UPDATE transactions SET status = 'completed' WHERE id = ?",
                        [$transactionId]
                    );
                    $success = "Transaction approved successfully";
                }
            } elseif ($action === 'reject') {
                $db->query(
                    "UPDATE transactions SET status = 'failed' WHERE id = ?",
                    [$transactionId]
                );
                
                // If rejecting a withdrawal, return the amount to user's balance
                if ($transaction['type'] === 'withdrawal') {
                    $balanceField = $transaction['type'] === 'bonus' ? 'bonus_balance' : 'profit_balance';
                    $db->query(
                        "UPDATE users SET $balanceField = $balanceField + ? WHERE id = ?",
                        [$transaction['amount'], $transaction['user_id']]
                    );
                }
                
                $success = "Transaction rejected successfully";
            }
        }
    } catch (Exception $e) {
        error_log("Transaction action error: " . $e->getMessage());
        $error = "Failed to process transaction action";
    }
}

// Handle filters and pagination
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    // Build query conditions
    $conditions = [];
    $params = [];

    if ($type) {
        $conditions[] = "t.type = ?";
        $params[] = $type;
    }

    if ($status) {
        $conditions[] = "t.status = ?";
        $params[] = $status;
    }

    if ($search) {
        $conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR t.wallet_address LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }

    $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // Get total transactions count
    $totalQuery = "SELECT COUNT(*) as count 
                   FROM transactions t 
                   JOIN users u ON t.user_id = u.id 
                   $whereClause";
    $total = $db->getRow($totalQuery, $params)['count'];
    $totalPages = ceil($total / $perPage);

    // Get transactions for current page
    $transactions = $db->getRows(
        "SELECT t.*, u.username, u.email 
         FROM transactions t 
         JOIN users u ON t.user_id = u.id 
         $whereClause 
         ORDER BY t.created_at DESC 
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );

    // Get transaction statistics
    $stats = $db->getRow(
        "SELECT 
         COUNT(*) as total_transactions,
         SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END) as total_deposits,
         SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END) as total_withdrawals,
         COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_transactions
         FROM transactions"
    );

} catch (Exception $e) {
    error_log("Admin transactions page error: " . $e->getMessage());
    $error = "Error loading transactions data";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Transactions - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Admin Navigation -->
    <nav class="bg-gray-900 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-shield-alt text-2xl"></i>
                    </div>
                    <div class="ml-4 text-xl font-semibold">Admin Dashboard</div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="hover:text-gray-300">Dashboard</a>
                    <a href="users.php" class="hover:text-gray-300">Users</a>
                    <a href="investments.php" class="hover:text-gray-300">Investments</a>
                    <a href="logout.php" class="hover:text-gray-300">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-500">Total Transactions</div>
                <div class="text-2xl font-semibold">
                    <?php echo number_format($stats['total_transactions']); ?>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-500">Total Deposits</div>
                <div class="text-2xl font-semibold text-green-600">
                    $<?php echo number_format($stats['total_deposits'], 2); ?>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-500">Total Withdrawals</div>
                <div class="text-2xl font-semibold text-red-600">
                    $<?php echo number_format($stats['total_withdrawals'], 2); ?>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-500">Pending Transactions</div>
                <div class="text-2xl font-semibold text-yellow-600">
                    <?php echo number_format($stats['pending_transactions']); ?>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-6">
                <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="type" class="w-full border rounded-lg px-3 py-2">
                            <option value="">All Types</option>
                            <option value="deposit" <?php echo $type === 'deposit' ? 'selected' : ''; ?>>Deposit</option>
                            <option value="withdrawal" <?php echo $type === 'withdrawal' ? 'selected' : ''; ?>>Withdrawal</option>
                            <option value="profit" <?php echo $type === 'profit' ? 'selected' : ''; ?>>Profit</option>
                            <option value="referral_bonus" <?php echo $type === 'referral_bonus' ? 'selected' : ''; ?>>Referral Bonus</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="w-full border rounded-lg px-3 py-2">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by username or wallet" 
                               class="w-full border rounded-lg px-3 py-2">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-600 text-white rounded-lg px-4 py-2 hover:bg-blue-700">
                            Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold">Transactions</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Wallet</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($transaction['username']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($transaction['email']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $transaction['type'] === 'deposit' ? 'bg-green-100 text-green-800' : 
                                            ($transaction['type'] === 'withdrawal' ? 'bg-red-100 text-red-800' : 
                                            'bg-blue-100 text-blue-800'); ?>">
                                        <?php echo ucfirst($transaction['type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium 
                                        <?php echo $transaction['type'] === 'withdrawal' ? 'text-red-600' : 'text-green-600'; ?>">
                                        $<?php echo number_format($transaction['amount'], 2); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">
                                        <?php echo substr($transaction['wallet_address'], 0, 6) . '...' . substr($transaction['wallet_address'], -4); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $transaction['status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                            ($transaction['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                            'bg-red-100 text-red-800'); ?>">
                                        <?php echo ucfirst($transaction['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('Y-m-d H:i', strtotime($transaction['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if ($transaction['status'] === 'pending'): ?>
                                        <form method="POST" class="inline-flex space-x-2">
                                            <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                                            <button type="submit" name="action" value="approve" 
                                                    class="text-green-600 hover:text-green-900">
                                                Approve
                                            </button>
                                            <button type="submit" name="action" value="reject" 
                                                    class="text-red-600 hover:text-red-900">
                                                Reject
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-gray-500">No actions available</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-500">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $total); ?> 
                            of <?php echo $total; ?> transactions
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" 
                                   class="px-3 py-1 border rounded hover:bg-gray-100">
                                    Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" 
                                   class="px-3 py-1 border rounded hover:bg-gray-100">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-8">
        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
            <p class="text-center text-sm text-gray-500">
                &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.
            </p>
        </div>
    </footer>
</body>
</html>