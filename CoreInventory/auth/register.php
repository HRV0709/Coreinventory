<?php
require_once '../includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('../index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    
    // Validation
    if ($password != $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Check if username or email exists
        $check = "SELECT * FROM users WHERE username = '$username' OR email = '$email'";
        $result = mysqli_query($conn, $check);
        
        if (mysqli_num_rows($result) > 0) {
            $error = 'Username or email already exists';
        } else {
            // Generate OTP
            $otp = generateOTP();
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Save OTP
            $insert_otp = "INSERT INTO otp_verification (email, otp_code, purpose, expires_at) 
                           VALUES ('$email', '$otp', 'registration', '$expires')";
            mysqli_query($conn, $insert_otp);
            
            // Store registration data in session
            $_SESSION['reg_data'] = [
                'username' => $username,
                'email' => $email,
                'password' => md5($password),
                'full_name' => $full_name,
                'phone' => $phone,
                'role' => $role
            ];
            
            // Send OTP email
            sendOTPEmail($email, $otp);
            
            // Redirect to OTP verification
            $_SESSION['otp_email'] = $email;
            $_SESSION['otp_purpose'] = 'registration';
            redirect('verify-otp.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/auth.css">
   
</head>
<body>
    <div class="container">
        <div class="auth-box">
            <div class="auth-header">
                <h1>Create Account</h1>
                <p>Register for <?php echo SITE_NAME; ?></p>
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
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Register as</label>
                        <select id="role" name="role" required>
                            <option value="staff">Staff</option>
                            <option value="manager">Manager</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Register</button>
                </form>
                
                <div class="auth-footer">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>