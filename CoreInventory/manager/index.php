<?php
require_once '../includes/config.php';

// Check if user is logged in and is manager
if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../auth/login.php');
}

// Redirect to dashboard
redirect('dashboard.php');
?>