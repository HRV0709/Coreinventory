<?php
require_once '../includes/config.php';

// Check if reset email exists
if (!isset($_SESSION['reset_email'])) {
    redirect('forgot-password.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password != $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        $email = $_SESSION['reset_email'];
        $hashed_password = md5($password);
        
        $update = "UPDATE users SET password = '$hashed_password' WHERE email = '$email'";
        
        if (mysqli_query($conn, $update)) {
            $success = 'Password reset successful! You can now login.';
            unset($_SESSION['reset_email']);
            
            // Redirect to login after 3 seconds
            header("refresh:3;url=login.php");
        } else {
            $error = 'Password reset failed';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="auth-box">
            <div class="auth-header">
                <h1>Reset Password</h1>
                <p>Enter your new password</p>
            </div>
            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php else: ?>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Reset Password</button>
                    </form>
                <?php endif; ?>
                
                <div class="auth-footer">
                    <p><a href="login.php">Back to Login</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>