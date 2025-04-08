<?php
// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'usdt_investment');
define('DB_USER', 'root');
define('DB_PASS', '');

// Investment packages configuration
define('PACKAGES', [
    1 => [
        'name' => 'Starter Package',
        'min' => 20,
        'max' => 300,
        'profit' => 0.01, // 1% daily
        'duration' => 100 // days
    ],
    2 => [
        'name' => 'Growth Package',
        'min' => 400,
        'max' => 1000,
        'profit' => 0.02, // 2% daily
        'duration' => 100
    ],
    3 => [
        'name' => 'Premium Package',
        'min' => 1200,
        'max' => 3000,
        'profit' => 0.03, // 3% daily
        'duration' => 100
    ],
    4 => [
        'name' => 'Elite Package',
        'min' => 3200,
        'max' => 5000,
        'profit' => 0.01, // 1% daily
        'duration' => 100
    ],
    5 => [
        'name' => 'VIP Package',
        'min' => 5200,
        'max' => 10000,
        'profit' => 0.01, // 1% daily
        'duration' => 100
    ]
]);

// Referral bonus configuration
define('REFERRAL_BONUSES', [
    1 => 0.10, // Level 1: 10%
    2 => 0.07, // Level 2: 7%
    3 => 0.04, // Level 3: 4%
    4 => 0.02, // Level 4: 2%
    5 => 0.01  // Level 5: 1%
]);

// BSC Network Configuration
define('BSC_NODE_URL', 'https://bsc-dataseed.binance.org/');
define('BSC_CONTRACT_ADDRESS', 'YOUR_CONTRACT_ADDRESS'); // Replace with actual USDT contract address on BSC

// Session configuration
session_start();
date_default_timezone_set('UTC');

// Site configuration
define('SITE_NAME', 'USDT Investment Platform');
define('SITE_URL', 'http://localhost'); // Change in production

// Security configuration
define('HASH_SALT', 'your_random_salt_here'); // Change this to a random string
define('JWT_SECRET', 'your_jwt_secret_here'); // Change this in production

// Admin configuration
define('ADMIN_EMAIL', 'admin@example.com');

// Function to generate random string for referral codes
function generateRandomString($length = 8) {
    return substr(str_shuffle(str_repeat($x='0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)))), 1, $length);
}

// Function to format currency
function formatCurrency($amount) {
    return number_format($amount, 2, '.', ',');
}

// Function to validate wallet address
function isValidWalletAddress($address) {
    return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
}