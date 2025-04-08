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

// Handle filters and pagination
$status = $_GET['status'] ?? '';
$package = $_GET['package'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    // Build query conditions
    $conditions = [];
    $params = [];

    if ($status) {
        $conditions[] = "i.status = ?";
        $params[] = $status;
    }

    if ($package) {
        $conditions[] = "i.package_id = ?";
        $params[] = $package;
    }

    if ($search) {
        $conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR u.wallet_address LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }

    $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    // Get total investments count
    $totalQuery = "SELECT COUNT(*) as count 
                   FROM investments i 
                   JOIN users u ON i.user_id = u.id 
                   $whereClause";
    $total = $db->getRow($totalQuery, $params)['count'];
    $totalPages = ceil($total / $perPage);

    // Get investments for current page
    $investments = $db->getRows(
        "SELECT i.*, u.username, u.email, u.wallet_address 
         FROM investments i 
         JOIN users u ON i.user_id = u.id 
         $whereClause 
         ORDER BY i.created_at DESC 
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );

    // Get investment statistics
    $stats = $db->getRow(
        "SELECT 
         COUNT(*) as total_investments,
         SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_investments,
         SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_investments,
         SUM(amount) as total_invested,
         SUM(total_profit) as total_profit
         FROM investments"
    );

} catch (Exception $e) {
    error_log("Admin investments page error: " . $e->getMessage());
    $error = "Error loading investments data";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Investments - Admin Dashboard</title>
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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-500">Total Investments</div>
                <div class="text-2xl font-semibold"><?php echo number_format($stats['total_investments']); ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-500">Active Investments</div>
                <div class="text-2xl font-semibold text-green-600">
                    <?php echo number_format($stats['active_investments']); ?>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-500">Completed Investments</div>
                <div class="text-2xl font-semibold text-blue-600">
                    <?php echo number_format($stats['completed_investments']); ?>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-500">Total Invested</div>
                <div class="text-2xl font-semibold">
                    $<?php echo number_format($stats['total_invested'], 2); ?>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm text-gray-500">Total Profit Generated</div>
                <div class="text-2xl font-semibold text-green-600">
                    $<?php echo number_format($stats['total_profit'], 2); ?>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-6">
                <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="w-full border rounded-lg px-3 py-2">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="terminated" <?php echo $status === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Package</label>
                        <select name="package" class="w-full border rounded-lg px-3 py-2">
                            <option value="">All Packages</option>
                            <?php foreach (PACKAGES as $id => $pkg): ?>
                                <option value="<?php echo $id; ?>" <?php echo $package == $id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pkg['name']); ?>
                                </option>
                            <?php endforeach; ?>
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

        <!-- Investments Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold">Investments</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Package</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Profit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Start Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">End Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($investments as $investment): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($investment['username']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo substr($investment['wallet_address'], 0, 6) . '...' . substr($investment['wallet_address'], -4); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo htmlspecialchars(PACKAGES[$investment['package_id']]['name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    $<?php echo number_format($investment['amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        Daily: <?php echo ($investment['daily_profit'] * 100); ?>%
                                    </div>
                                    <div class="text-sm text-green-600">
                                        Total: $<?php echo number_format($investment['total_profit'], 2); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $investment['status'] === 'active' ? 'bg-green-100 text-green-800' : 
                                            ($investment['status'] === 'completed' ? 'bg-blue-100 text-blue-800' : 
                                            'bg-red-100 text-red-800'); ?>">
                                        <?php echo ucfirst($investment['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('Y-m-d', strtotime($investment['start_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('Y-m-d', strtotime($investment['end_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <a href="investment_details.php?id=<?php echo $investment['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-900">
                                        View Details
                                    </a>
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
                            of <?php echo $total; ?> investments
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&package=<?php echo urlencode($package); ?>&search=<?php echo urlencode($search); ?>" 
                                   class="px-3 py-1 border rounded hover:bg-gray-100">
                                    Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&package=<?php echo urlencode($package); ?>&search=<?php echo urlencode($search); ?>" 
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