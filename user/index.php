<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/investment.php';
require_once '../includes/wallet.php';

// Initialize classes
$auth = new Auth();
$investment = new Investment();
$wallet = new Wallet();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get user details
$userId = $_SESSION['user_id'];
$user = $auth->getUserDetails($userId);

// Get user's investments
$investments = $investment->getUserInvestments($userId);

// Get user's balances
$balances = $wallet->getBalances($userId);

// Get user's statistics
$stats = $wallet->getUserStats($userId);

// Get recent transactions
$transactions = $wallet->getTransactionHistory($userId, ['limit' => 5]);

// Get referral earnings
$referralEarnings = $wallet->getTransactionHistory($userId, [
    'type' => 'referral_bonus',
    'status' => 'completed'
]);
$totalReferralEarnings = array_sum(array_column($referralEarnings, 'amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <i class="fas fa-coins text-2xl text-blue-600"></i>
                        <span class="ml-2 text-xl font-bold text-gray-900"><?php echo SITE_NAME; ?></span>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="hidden md:ml-6 md:flex md:items-center md:space-x-4">
                        <a href="invest.php" class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-chart-line mr-1"></i> Invest
                        </a>
                        <a href="withdraw.php" class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-wallet mr-1"></i> Withdraw
                        </a>
                        <a href="transactions.php" class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-history mr-1"></i> History
                        </a>
                        <a href="referrals.php" class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-users mr-1"></i> Referrals
                        </a>
                        <a href="logout.php" class="text-red-600 hover:text-red-800 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Welcome Banner -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        Welcome back, <?php echo htmlspecialchars($user['username']); ?>!
                    </h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Here's your investment overview
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600">Wallet Address</p>
                    <p class="text-sm font-mono text-gray-900">
                        <?php echo substr($user['wallet_address'], 0, 6) . '...' . substr($user['wallet_address'], -4); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Balance Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <!-- Profit Balance -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-dollar-sign text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Profit Balance</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            $<?php echo number_format($balances['profit_balance'], 2); ?>
                        </p>
                    </div>
                </div>
                <a href="withdraw.php?type=profit" 
                   class="mt-4 block text-center text-sm text-blue-600 hover:text-blue-800">
                    Withdraw Profit →
                </a>
            </div>

            <!-- Bonus Balance -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-gift text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Bonus Balance</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            $<?php echo number_format($balances['bonus_balance'], 2); ?>
                        </p>
                    </div>
                </div>
                <a href="withdraw.php?type=bonus" 
                   class="mt-4 block text-center text-sm text-blue-600 hover:text-blue-800">
                    Withdraw Bonus →
                </a>
            </div>

            <!-- Total Invested -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-chart-pie text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Total Invested</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            $<?php echo number_format($stats['total_invested'], 2); ?>
                        </p>
                    </div>
                </div>
                <a href="invest.php" 
                   class="mt-4 block text-center text-sm text-blue-600 hover:text-blue-800">
                    Make New Investment →
                </a>
            </div>
        </div>

        <!-- Active Investments -->
        <div class="bg-white rounded-lg shadow-sm mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Active Investments</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Package</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Daily Profit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Profit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">End Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($investments): ?>
                            <?php foreach ($investments as $inv): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars(PACKAGES[$inv['package_id']]['name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        $<?php echo number_format($inv['amount'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-green-600">
                                        <?php echo ($inv['daily_profit'] * 100); ?>%
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-green-600">
                                        $<?php echo number_format($inv['total_profit'], 2); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo date('Y-m-d', strtotime($inv['end_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $inv['status'] === 'active' ? 'bg-green-100 text-green-800' : 
                                                ($inv['status'] === 'completed' ? 'bg-blue-100 text-blue-800' : 
                                                'bg-gray-100 text-gray-800'); ?>">
                                            <?php echo ucfirst($inv['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    No active investments found. 
                                    <a href="invest.php" class="text-blue-600 hover:text-blue-800">Make your first investment!</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Referral Section -->
        <div class="bg-white rounded-lg shadow-sm mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Your Referral Program</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">Your Referral Link</h3>
                        <div class="mt-2 flex rounded-md shadow-sm">
                            <input type="text" readonly value="<?php echo SITE_URL . '/register.php?ref=' . $user['referral_code']; ?>" 
                                   class="flex-1 min-w-0 block w-full px-3 py-2 rounded-l-md border border-gray-300 bg-gray-50 text-sm">
                            <button onclick="copyReferralLink(this)" 
                                    class="inline-flex items-center px-4 py-2 border border-l-0 border-gray-300 rounded-r-md bg-gray-50 text-sm font-medium text-gray-700 hover:bg-gray-100">
                                Copy
                            </button>
                        </div>
                        <p class="mt-2 text-sm text-gray-600">
                            Share this link to earn referral bonuses when your referrals invest
                        </p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">Referral Earnings</h3>
                        <p class="mt-2 text-2xl font-semibold text-green-600">
                            $<?php echo number_format($totalReferralEarnings, 2); ?>
                        </p>
                        <div class="mt-2 text-sm text-gray-600">
                            <p>Level 1 (10%): <?php echo $stats['level1_referrals'] ?? 0; ?> referrals</p>
                            <p>Level 2 (7%): <?php echo $stats['level2_referrals'] ?? 0; ?> referrals</p>
                            <p>Level 3 (4%): <?php echo $stats['level3_referrals'] ?? 0; ?> referrals</p>
                            <p>Level 4 (2%): <?php echo $stats['level4_referrals'] ?? 0; ?> referrals</p>
                            <p>Level 5 (1%): <?php echo $stats['level5_referrals'] ?? 0; ?> referrals</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900">Recent Transactions</h2>
                <a href="transactions.php" class="text-sm text-blue-600 hover:text-blue-800">View All →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($transactions): ?>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
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
                                        <?php echo date('Y-m-d H:i', strtotime($tx['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                    No transactions found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function copyReferralLink(button) {
            const input = button.parentElement.querySelector('input');
            input.select();
            document.execCommand('copy');
            
            const originalText = button.textContent;
            button.textContent = 'Copied!';
            setTimeout(() => {
                button.textContent = originalText;
            }, 2000);
        }
    </script>
</body>
</html>