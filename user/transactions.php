<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/wallet.php';

// Initialize classes
$auth = new Auth();
$wallet = new Wallet();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get user details
$userId = $_SESSION['user_id'];
$user = $auth->getUserDetails($userId);

// Get transactions
$transactions = $wallet->getTransactionHistory($userId, ['limit' => 20]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg mb-6">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center">
                        <i class="fas fa-coins text-2xl text-blue-600"></i>
                        <span class="ml-2 text-xl font-bold"><?php echo SITE_NAME; ?></span>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-gray-700 hover:text-gray-900">Dashboard</a>
                    <a href="invest.php" class="text-gray-700 hover:text-gray-900">Invest</a>
                    <a href="withdraw.php" class="text-gray-700 hover:text-gray-900">Withdraw</a>
                    <a href="logout.php" class="text-red-600 hover:text-red-800">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4">
        <!-- Transactions Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold">Transaction History</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($transactions): ?>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('Y-m-d H:i', strtotime($tx['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $tx['type'] === 'deposit' ? 'bg-green-100 text-green-800' : 
                                                ($tx['type'] === 'withdrawal' ? 'bg-red-100 text-red-800' : 
                                                'bg-blue-100 text-blue-800'); ?>">
                                            <?php echo ucfirst($tx['type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="<?php echo $tx['type'] === 'withdrawal' ? 'text-red-600' : 'text-green-600'; ?>">
                                            $<?php echo number_format($tx['amount'], 2); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $tx['status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                                ($tx['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                'bg-red-100 text-red-800'); ?>">
                                            <?php echo ucfirst($tx['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if ($tx['tx_hash']): ?>
                                            <a href="https://bscscan.com/tx/<?php echo $tx['tx_hash']; ?>" 
                                               target="_blank" 
                                               class="text-blue-600 hover:text-blue-800">
                                                View on BSCScan
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    No transactions found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>