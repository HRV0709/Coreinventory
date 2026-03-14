<?php
require_once 'includes/config.php';

// Redirect based on role if logged in
if (isLoggedIn()) {
    if (hasRole('admin')) {
        redirect('admin/dashboard.php');
    } elseif (hasRole('manager')) {
        redirect('manager/dashboard.php');
    } elseif (hasRole('staff')) {
        redirect('staff/dashboard.php');
    }
} else {
    redirect('auth/login.php');
}
?>