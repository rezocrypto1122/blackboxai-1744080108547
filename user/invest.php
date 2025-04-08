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

$error = '';
$success = '';

// Process investment form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $packageId = $_POST['package_id'] ?? 0;
    $amount = floatval($_POST['amount'] ?? 0);
    
    if (!isset(PACKAGES[$packageId])) {
        $error = 'Invalid investment package';
    } else {
        $package = PACKAGES[$packageId];
        if ($amount < $package['min'] || $amount > $package['max']) {
            $error = "Amount must be between $" . number_format($package['min']) . 
                    " and $" . number_format($package['max']);
        } else {
            $result = $investment->createInvestment([
                'user_id' => $userId,
                'package_id' => $packageId,
                'amount' => $amount,
                'wallet_address' => $user['wallet_address']
            ]);

            if ($result['success']) {
                $success = $result['message'];
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
    <title>Invest - <?php echo SITE_NAME; ?></title>
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
                    <a href="withdraw.php" class="text-gray-700 hover:text-gray-900">Withdraw</a>
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

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach (PACKAGES as $id => $package): ?>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4"><?php echo htmlspecialchars($package['name']); ?></h3>
                    <div class="space-y-2 mb-4">
                        <p>Investment: $<?php echo number_format($package['min']); ?> - $<?php echo number_format($package['max']); ?></p>
                        <p>Daily Profit: <?php echo ($package['profit'] * 100); ?>%</p>
                        <p>Duration: <?php echo $package['duration']; ?> days</p>
                    </div>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="package_id" value="<?php echo $id; ?>">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Amount (USDT)</label>
                            <input type="number" name="amount" required min="<?php echo $package['min']; ?>" 
                                   max="<?php echo $package['max']; ?>" step="0.01"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700">
                            Invest Now
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>