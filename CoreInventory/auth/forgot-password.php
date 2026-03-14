<?php
require_once '../includes/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Check if email exists
    $check = "SELECT * FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $check);
    
    if (mysqli_num_rows($result) == 1) {
        // Generate OTP
        $otp = generateOTP();
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // Save OTP
        $insert_otp = "INSERT INTO otp_verification (email, otp_code, purpose, expires_at) 
                       VALUES ('$email', '$otp', 'password_reset', '$expires')";
        mysqli_query($conn, $insert_otp);
        
        // Send OTP email
        sendOTPEmail($email, $otp);
        
        $_SESSION['otp_email'] = $email;
        $_SESSION['otp_purpose'] = 'password_reset';
        
        $success = 'OTP has been sent to your email';
        
        // Redirect to OTP verification after 2 seconds
        header("refresh:2;url=verify-otp.php");
    } else {
        $error = 'Email not found in our records';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="auth-box">
            <div class="auth-header">
                <h1>Forgot Password</h1>
                <p>Enter your email to reset password</p>
            </div>
            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Send OTP</button>
                </form>
                
                <div class="auth-footer">
                    <p><a href="login.php">Back to Login</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>