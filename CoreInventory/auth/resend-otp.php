<?php
require_once '../includes/config.php';

// Check if there's an OTP session
if (!isset($_SESSION['otp_email']) || !isset($_SESSION['otp_purpose'])) {
    $_SESSION['error'] = 'No active OTP session. Please try again.';
    redirect('login.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_SESSION['otp_email'];
    $purpose = $_SESSION['otp_purpose'];
    
    // Check if too many resend attempts
    $check_attempts = mysqli_query($conn, "SELECT COUNT(*) as attempts FROM otp_verification 
                                           WHERE email = '$email' AND purpose = '$purpose' 
                                           AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $attempts = mysqli_fetch_assoc($check_attempts)['attempts'];
    
    if ($attempts >= 5) {
        $error = "Too many OTP requests. Please try again after 1 hour.";
    } else {
        // Generate new OTP
        $otp = sprintf("%06d", mt_rand(1, 999999));
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // Mark old OTPs as expired
        $expire_old = "UPDATE otp_verification SET verified = 1, expires_at = NOW() 
                       WHERE email = '$email' AND purpose = '$purpose' AND verified = 0";
        mysqli_query($conn, $expire_old);
        
        // Insert new OTP
        $insert = "INSERT INTO otp_verification (email, otp_code, purpose, expires_at, verified) 
                   VALUES ('$email', '$otp', '$purpose', '$expires', 0)";
        
        if (mysqli_query($conn, $insert)) {
            // For development/testing - show OTP on screen (remove in production)
            $message = "A new OTP has been sent. For testing: Your OTP is <strong>$otp</strong>";
            
            // In production, you would send email here
            // mail($email, "Your OTP Code", "Your OTP is: $otp");
            
            $_SESSION['otp_sent'] = time();
        } else {
            $error = "Failed to generate OTP. Please try again.";
        }
    }
}

// Get the time when OTP was last sent
$last_otp = mysqli_query($conn, "SELECT created_at, otp_code FROM otp_verification 
                                  WHERE email = '{$_SESSION['otp_email']}' 
                                  AND purpose = '{$_SESSION['otp_purpose']}' 
                                  ORDER BY created_at DESC LIMIT 1");
$last_sent = mysqli_fetch_assoc($last_otp);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend OTP - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .test-otp {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            text-align: center;
            font-size: 18px;
        }
        .test-otp strong {
            font-size: 24px;
            letter-spacing: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-box">
            <div class="auth-header">
                <h1>Resend OTP</h1>
                <p>Get a new verification code</p>
            </div>
            
            <div class="auth-body">
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> 
                        <?php echo $message; ?>
                    </div>
                    
                    <!-- For testing - remove in production -->
                    <?php if (strpos($message, 'For testing') !== false): ?>
                    <div class="test-otp">
                        <i class="fas fa-flask"></i> Test Mode<br>
                        Use this OTP: <strong><?php echo $last_sent['otp_code']; ?></strong>
                    </div>
                    <?php endif; ?>
                    
                    <div class="resend-info">
                        <p>Redirecting to verification page in <span id="counter">5</span> seconds...</p>
                    </div>
                    <script>
                        let counter = 5;
                        const countdown = setInterval(function() {
                            counter--;
                            document.getElementById('counter').textContent = counter;
                            if (counter === 0) {
                                clearInterval(countdown);
                                window.location.href = 'verify-otp.php';
                            }
                        }, 1000);
                    </script>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> 
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!$message): ?>
                    <div class="email-display">
                        <i class="fas fa-envelope"></i> 
                        OTP will be sent to: <strong><?php echo $_SESSION['otp_email']; ?></strong>
                    </div>
                    
                    <?php if ($last_sent): ?>
                        <div class="resend-info">
                            <p>Last OTP was sent at: <?php echo date('h:i:s A', strtotime($last_sent['created_at'])); ?></p>
                            <?php if (isset($_SESSION['otp_sent'])): ?>
                            <p>You can request a new OTP every 60 seconds.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="resendForm">
                        <button type="submit" class="btn btn-primary" id="resendBtn">
                            <i class="fas fa-paper-plane"></i> Resend OTP
                        </button>
                    </form>
                    
                    <div class="auth-footer">
                        <p><a href="verify-otp.php"><i class="fas fa-arrow-left"></i> Back to Verification</a></p>
                        <p><a href="login.php">Return to Login</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    // Cooldown timer
    const resendBtn = document.getElementById('resendBtn');
    const lastSent = <?php echo isset($_SESSION['otp_sent']) ? $_SESSION['otp_sent'] : 0; ?>;
    const now = Math.floor(Date.now() / 1000);
    const timeSinceLast = now - lastSent;
    
    if (resendBtn && timeSinceLast < 60 && timeSinceLast > 0) {
        let cooldown = 60 - timeSinceLast;
        resendBtn.disabled = true;
        
        const timer = setInterval(function() {
            cooldown--;
            if (cooldown <= 0) {
                clearInterval(timer);
                resendBtn.disabled = false;
                resendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Resend OTP';
            } else {
                resendBtn.innerHTML = '<i class="fas fa-hourglass-half"></i> Wait ' + cooldown + 's';
            }
        }, 1000);
    }
    </script>
</body>
</html>