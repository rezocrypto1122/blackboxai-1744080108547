<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Initialize Auth class
$auth = new Auth();

// Check if admin is logged in
if (!$auth->isAdminLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Initialize Database connection
$db = Database::getInstance();

// Get dashboard statistics
try {
    // Total users
    $totalUsers = $db->getRow("SELECT COUNT(*) as count FROM users")['count'];

    // Total active investments
    $activeInvestments = $db->getRow(
        "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
        FROM investments WHERE status = 'active'"
    );

    // Pending withdrawals
    $pendingWithdrawals = $db->getRow(
        "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
        FROM transactions 
        WHERE type = 'withdrawal' AND status = 'pending'"
    );

    // Recent transactions
    $recentTransactions = $db->getRows(
        "SELECT t.*, u.username 
        FROM transactions t 
        JOIN users u ON t.user_id = u.id 
        ORDER BY t.created_at DESC 
        LIMIT 10"
    );

    // Investment package statistics
    $packageStats = [];
    foreach (PACKAGES as $id => $package) {
        $stats = $db->getRow(
            "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
            FROM investments 
            WHERE package_id = ? AND status = 'active'",
            [$id]
        );
        $packageStats[$id] = $stats;
    }

} catch (Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $error = "Error loading dashboard data";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
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
                    <a href="users.php" class="hover:text-gray-300">Users</a>
                    <a href="investments.php" class="hover:text-gray-300">Investments</a>
                    <a href="transactions.php" class="hover:text-gray-300">Transactions</a>
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

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Users -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-users text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500 uppercase">Total Users</p>
                        <p class="text-2xl font-semibold"><?php echo number_format($totalUsers); ?></p>
                    </div>
                </div>
            </div>

            <!-- Active Investments -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-chart-line text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500 uppercase">Active Investments</p>
                        <p class="text-2xl font-semibold"><?php echo number_format($activeInvestments['count']); ?></p>
                        <p class="text-sm text-gray-500">$<?php echo number_format($activeInvestments['total'], 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Pending Withdrawals -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="fas fa-clock text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500 uppercase">Pending Withdrawals</p>
                        <p class="text-2xl font-semibold"><?php echo number_format($pendingWithdrawals['count']); ?></p>
                        <p class="text-sm text-gray-500">$<?php echo number_format($pendingWithdrawals['total'], 2); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Investment Packages Statistics -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold">Investment Package Statistics</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach (PACKAGES as $id => $package): ?>
                        <div class="border rounded-lg p-4">
                            <h3 class="font-semibold mb-2"><?php echo htmlspecialchars($package['name']); ?></h3>
                            <p class="text-sm text-gray-600">
                                Active Investments: <?php echo number_format($packageStats[$id]['count']); ?>
                            </p>
                            <p class="text-sm text-gray-600">
                                Total Amount: $<?php echo number_format($packageStats[$id]['total'], 2); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold">Recent Transactions</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recentTransactions as $transaction): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo htmlspecialchars($transaction['username']); ?>
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
                                    $<?php echo number_format($transaction['amount'], 2); ?>
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-gray-200">
                <a href="transactions.php" class="text-blue-600 hover:text-blue-800">View all transactions →</a>
            </div>
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