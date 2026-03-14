<?php
session_start();

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'core_inventory';

// Create connection
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Site configuration
define('SITE_NAME', 'CoreInventory');
define('SITE_URL', 'http://localhost/CoreInventory/');

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] == $role;
}

// Function to redirect
function redirect($url) {
    header("Location: $url");
    exit();
}

// Function to generate OTP
function generateOTP() {
    return rand(100000, 999999);
}

// Function to send OTP email (simplified - in production use PHPMailer)
function sendOTPEmail($email, $otp) {
    // In production, implement actual email sending
    // For demo, we'll just return true
    return true;
}
?>
