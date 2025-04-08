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

// Get user details and balances
$userId = $_SESSION['user_id'];
$user = $auth->getUserDetails($userId);
$balances = $wallet->getBalances($userId);

$error = '';
$success = '';

// Process withdrawal form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    
    if (!in_array($type, ['profit', 'bonus'])) {
        $error = 'Invalid withdrawal type';
    } else {
        $balance = $type === 'profit' ? $balances['profit_balance'] : $balances['bonus_balance'];
        
        if ($amount <= 0) {
            $error = 'Please enter a valid amount';
        } elseif ($amount > $balance) {
            $error = 'Insufficient balance';
        } else {
            $result = $type === 'profit' ? 
                $wallet->withdrawProfit([
                    'user_id' => $userId,
                    'amount' => $amount,
                    'wallet_address' => $user['wallet_address']
                ]) :
                $wallet->withdrawBonus([
                    'user_id' => $userId,
                    'amount' => $amount,
                    'wallet_address' => $user['wallet_address']
                ]);

            if ($result['success']) {
                $success = $result['message'];
                $balances = $wallet->getBalances($userId);
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw - <?php echo SITE_NAME; ?></title>
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
                    <a href="transactions.php" class="text-gray-700 hover:text-gray-900">History</a>
                    <a href="logout.php" class="text-red-600 hover:text-red-800">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Profit Withdrawal -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Profit Balance: $<?php echo number_format($balances['profit_balance'], 2); ?></h2>
                <form method="POST" onsubmit="return confirm('Confirm withdrawal?');">
                    <input type="hidden" name="type" value="profit">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Amount (USDT)</label>
                        <input type="number" name="amount" required step="0.01" min="10" 
                               max="<?php echo $balances['profit_balance']; ?>"
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                    </div>
                    <button type="submit" class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700">
                        Withdraw Profit
                    </button>
                </form>
            </div>

            <!-- Bonus Withdrawal -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Bonus Balance: $<?php echo number_format($balances['bonus_balance'], 2); ?></h2>
                <form method="POST" onsubmit="return confirm('Confirm withdrawal?');">
                    <input type="hidden" name="type" value="bonus">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Amount (USDT)</label>
                        <input type="number" name="amount" required step="0.01" min="10" 
                               max="<?php echo $balances['bonus_balance']; ?>"
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700">
                        Withdraw Bonus
                    </button>
                </form>
            </div>
        </div>

        <div class="mt-6 bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Withdrawal Information</h3>
            <ul class="space-y-2 text-sm text-gray-600">
                <li>• Minimum withdrawal: $10 USDT</li>
                <li>• Processing time: 5-30 minutes</li>
                <li>• Withdrawals are sent to: <?php echo substr($user['wallet_address'], 0, 6) . '...' . substr($user['wallet_address'], -4); ?></li>
            </ul>
        </div>
    </main>
</body>
</html>