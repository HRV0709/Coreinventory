<?php
require_once '../includes/config.php';

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if OTP email exists in session
if (!isset($_SESSION['otp_email']) || !isset($_SESSION['otp_purpose'])) {
    $_SESSION['error'] = 'Session expired. Please try again.';
    redirect('login.php');
}

$error = '';
$success = '';

// For debugging - show session data (remove in production)
/*
echo '<pre>';
print_r($_SESSION);
echo '</pre>';
*/

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $otp = mysqli_real_escape_string($conn, $_POST['otp']);
    $email = $_SESSION['otp_email'];
    $purpose = $_SESSION['otp_purpose'];
    
    // Debug: Check what OTPs exist for this email
    $debug_query = "SELECT * FROM otp_verification 
                    WHERE email = '$email' 
                    AND purpose = '$purpose' 
                    ORDER BY id DESC LIMIT 5";
    $debug_result = mysqli_query($conn, $debug_query);
    
    /*
    echo "<h3>Recent OTPs for $email:</h3>";
    while($row = mysqli_fetch_assoc($debug_result)) {
        echo "ID: " . $row['id'] . " - OTP: " . $row['otp_code'] . " - Expires: " . $row['expires_at'] . " - Verified: " . $row['verified'] . "<br>";
        echo "Current time: " . date('Y-m-d H:i:s') . "<br>";
        echo "Is expired? " . (strtotime($row['expires_at']) < time() ? 'Yes' : 'No') . "<br><br>";
    }
    */
    
    // First, check if any OTP exists for this email/purpose
    $check_query = "SELECT * FROM otp_verification 
                    WHERE email = '$email' 
                    AND purpose = '$purpose' 
                    ORDER BY id DESC LIMIT 1";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) == 0) {
        $error = 'No OTP found for this email. Please request a new one.';
    } else {
        $latest_otp = mysqli_fetch_assoc($check_result);
        
        // Check if OTP is already verified
        if ($latest_otp['verified'] == 1) {
            $error = 'This OTP has already been verified. Please login.';
        }
        // Check if OTP is expired
        else if (strtotime($latest_otp['expires_at']) < time()) {
            $error = 'OTP has expired. Please request a new one.';
        }
        // Verify the OTP
        else if ($latest_otp['otp_code'] == $otp) {
            // Mark OTP as verified
            $update = "UPDATE otp_verification SET verified = 1 WHERE id = " . $latest_otp['id'];
            if (mysqli_query($conn, $update)) {
                
                if ($purpose == 'registration') {
                    // Complete registration
                    if (!isset($_SESSION['reg_data'])) {
                        $error = 'Registration data not found. Please register again.';
                    } else {
                        $data = $_SESSION['reg_data'];
                        
                        // Check if user already exists
                        $check_user = "SELECT id FROM users WHERE username = '{$data['username']}' OR email = '{$data['email']}'";
                        $user_result = mysqli_query($conn, $check_user);
                        
                        if (mysqli_num_rows($user_result) > 0) {
                            $error = 'Username or email already exists. Please try again.';
                        } else {
                            $insert = "INSERT INTO users (username, email, password, full_name, phone, role, status) 
                                      VALUES (
                                          '{$data['username']}',
                                          '{$data['email']}',
                                          '{$data['password']}',
                                          '{$data['full_name']}',
                                          '{$data['phone']}',
                                          '{$data['role']}',
                                          1
                                      )";
                            
                            if (mysqli_query($conn, $insert)) {
                                $success = 'Registration successful! You can now login.';
                                
                                // Clear session data
                                unset($_SESSION['reg_data']);
                                unset($_SESSION['otp_email']);
                                unset($_SESSION['otp_purpose']);
                                
                                // Redirect to login after 3 seconds
                                header("refresh:3;url=login.php");
                            } else {
                                $error = 'Registration failed: ' . mysqli_error($conn);
                            }
                        }
                    }
                } elseif ($purpose == 'password_reset') {
                    // Redirect to reset password
                    $_SESSION['reset_email'] = $email;
                    unset($_SESSION['otp_email']);
                    unset($_SESSION['otp_purpose']);
                    $success = 'OTP verified successfully. Redirecting to password reset...';
                    header("refresh:2;url=reset-password.php");
                }
            } else {
                $error = 'Failed to verify OTP: ' . mysqli_error($conn);
            }
        } else {
            $error = 'Invalid OTP code. Please check and try again.';
            
            // Debug info (remove in production)
            // $error .= " (Entered: $otp, Expected: " . $latest_otp['otp_code'] . ")";
        }
    }
}

// Get the time remaining for current OTP (for display)
$time_remaining = '';
if (isset($_SESSION['otp_email']) && !$success) {
    $time_query = "SELECT expires_at FROM otp_verification 
                   WHERE email = '{$_SESSION['otp_email']}' 
                   AND purpose = '{$_SESSION['otp_purpose']}' 
                   AND verified = 0 
                   ORDER BY id DESC LIMIT 1";
    $time_result = mysqli_query($conn, $time_query);
    if (mysqli_num_rows($time_result) > 0) {
        $expires = mysqli_fetch_assoc($time_result)['expires_at'];
        $remaining = strtotime($expires) - time();
        if ($remaining > 0) {
            $minutes = floor($remaining / 60);
            $seconds = $remaining % 60;
            $time_remaining = "$minutes minutes $seconds seconds";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .otp-info {
            background: #e3f2fd;
            color: #0c5460;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
            text-align: center;
        }
        .timer {
            font-weight: bold;
            color: #dc3545;
        }
        .email-display {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-box">
            <div class="auth-header">
                <h1>Verify OTP</h1>
                <p>Enter the 6-digit code sent to your email</p>
            </div>
            <div class="auth-body">
                <?php if (isset($_SESSION['otp_email'])): ?>
                    <div class="email-display">
                        <i class="fas fa-envelope"></i> 
                        OTP sent to: <strong><?php echo $_SESSION['otp_email']; ?></strong>
                    </div>
                <?php endif; ?>
                
                <?php if ($time_remaining): ?>
                    <div class="otp-info">
                        <i class="fas fa-clock"></i> 
                        OTP expires in: <span class="timer"><?php echo $time_remaining; ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> 
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> 
                        <?php echo $success; ?>
                    </div>
                <?php else: ?>
                    <form method="POST" action="" id="otpForm">
                        <div class="form-group">
                            <label for="otp">OTP Code</label>
                            <input type="text" id="otp" name="otp" class="otp-input" 
                                   maxlength="6" pattern="\d{6}" 
                                   placeholder="Enter 6-digit code"
                                   autocomplete="off" required>
                            <small style="display: block; text-align: center; margin-top: 5px; color: #6c757d;">
                                Enter the 6-digit code from your email
                            </small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="verifyBtn">
                            <i class="fas fa-check"></i> Verify OTP
                        </button>
                    </form>
                <?php endif; ?>
                
                <div class="auth-footer">
                    <p>Didn't receive code? <a href="resend-otp.php">Resend OTP</a></p>
                    <p><a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a></p>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Auto-submit when 6 digits are entered
    document.getElementById('otp')?.addEventListener('input', function(e) {
        if (this.value.length === 6) {
            document.getElementById('otpForm').submit();
        }
    });

    // Disable button after submit to prevent double submission
    document.getElementById('otpForm')?.addEventListener('submit', function() {
        document.getElementById('verifyBtn').disabled = true;
        document.getElementById('verifyBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
    });

    // Countdown timer for OTP expiry
    <?php if ($time_remaining): ?>
    let timeLeft = <?php echo $remaining; ?>;
    const timerElement = document.querySelector('.timer');
    
    if (timerElement) {
        const countdown = setInterval(function() {
            timeLeft--;
            if (timeLeft <= 0) {
                clearInterval(countdown);
                timerElement.innerHTML = 'Expired';
                timerElement.style.color = '#dc3545';
                // Show message that OTP expired
                const infoDiv = document.querySelector('.otp-info');
                if (infoDiv) {
                    infoDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> OTP has expired. Please request a new one.';
                    infoDiv.style.background = '#f8d7da';
                    infoDiv.style.color = '#721c24';
                }
            } else {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timerElement.innerHTML = minutes + 'm ' + seconds + 's';
            }
        }, 1000);
    }
    <?php endif; ?>
    </script>
</body>
</html>