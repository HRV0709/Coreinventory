<?php
require_once '../includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (hasRole('admin')) {
        redirect('../admin/dashboard.php');
    } elseif (hasRole('manager')) {
        redirect('../manager/dashboard.php');
    } elseif (hasRole('staff')) {
        redirect('../staff/dashboard.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = md5($_POST['password']);
    
    $query = "SELECT * FROM users WHERE username = '$username' AND password = '$password' AND status = 1";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        
        // Redirect based on role
        if ($user['role'] == 'admin') {
            redirect('../admin/dashboard.php');
        } elseif ($user['role'] == 'manager') {
            redirect('../manager/dashboard.php');
        } else {
            redirect('../staff/dashboard.php');
        }
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
    <div class="container">
        <div class="auth-box">
            <div class="auth-header">
                <h1><?php echo SITE_NAME; ?></h1>
                <p>Login to your account</p>
            </div>
            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Login</button>
                </form>
                
                <div class="auth-footer">
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                    <p><a href="forgot-password.php">Forgot Password?</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>