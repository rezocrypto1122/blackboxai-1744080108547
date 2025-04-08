<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Initialize Auth class
$auth = new Auth();

// Logout the admin
$auth->logout();

// Redirect to login page
header('Location: login.php');
exit();