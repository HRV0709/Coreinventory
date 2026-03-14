<?php
require_once '../includes/config.php';

// Check if user is logged in and is staff
if (!isLoggedIn() || !hasRole('staff')) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

// Get user details
$user_id = $_SESSION['user_id'];
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
$user = mysqli_fetch_assoc($user_query);

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'update_profile') {
            $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $address = mysqli_real_escape_string($conn, $_POST['address']);
            
            $update = "UPDATE users SET full_name = '$full_name', phone = '$phone', address = '$address' WHERE id = $user_id";
            
            if (mysqli_query($conn, $update)) {
                $_SESSION['full_name'] = $full_name;
                $message = "Profile updated successfully";
                // Refresh user data
                $user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
                $user = mysqli_fetch_assoc($user_query);
            } else {
                $error = "Error updating profile";
            }
        }
        elseif ($_POST['action'] == 'change_password') {
            $current = md5($_POST['current_password']);
            $new = $_POST['new_password'];
            $confirm = $_POST['confirm_password'];
            
            // Verify current password
            $check = mysqli_query($conn, "SELECT id FROM users WHERE id = $user_id AND password = '$current'");
            
            if (mysqli_num_rows($check) == 0) {
                $error = "Current password is incorrect";
            } elseif ($new != $confirm) {
                $error = "New passwords do not match";
            } elseif (strlen($new) < 6) {
                $error = "Password must be at least 6 characters";
            } else {
                $new_password = md5($new);
                $update = "UPDATE users SET password = '$new_password' WHERE id = $user_id";
                
                if (mysqli_query($conn, $update)) {
                    $message = "Password changed successfully";
                } else {
                    $error = "Error changing password";
                }
            }
        }
    }
}

// Get user statistics
$stats = mysqli_query($conn, "
    SELECT 
        (SELECT COUNT(*) FROM receipts WHERE created_by = $user_id) as receipts_count,
        (SELECT COUNT(*) FROM deliveries WHERE created_by = $user_id) as deliveries_count,
        (SELECT COUNT(*) FROM transfers WHERE created_by = $user_id) as transfers_count,
        (SELECT COUNT(*) FROM damage_products WHERE reported_by = $user_id) as damage_count
");
$user_stats = mysqli_fetch_assoc($stats);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Staff Panel - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/staff.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: 600;
            color: #667eea;
            border: 4px solid rgba(255,255,255,0.3);
        }
        .profile-name {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .profile-role {
            background: rgba(255,255,255,0.2);
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .stat-number {
            font-size: 28px;
            font-weight: 600;
            color: #3498db;
        }
        .stat-label {
            font-size: 13px;
            color: #7f8c8d;
        }
        .profile-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tab-btn {
            padding: 10px 20px;
            background: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            color: #2c3e50;
        }
        .tab-btn.active {
            background: #3498db;
            color: white;
        }
        .tab-content {
            background: white;
            border-radius: 10px;
            padding: 30px;
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .info-row {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        .info-label {
            width: 150px;
            font-weight: 600;
            color: #2c3e50;
        }
        .info-value {
            flex: 1;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><?php echo SITE_NAME; ?></h2>
            <p>Staff Panel</p>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="receipts.php" class="menu-item">
                <i class="fas fa-truck-loading"></i> Receipts
            </a>
            <a href="deliveries.php" class="menu-item">
                <i class="fas fa-truck"></i> Delivery Orders
            </a>
            <a href="transfers.php" class="menu-item">
                <i class="fas fa-exchange-alt"></i> Internal Transfers
            </a>
            <a href="damage.php" class="menu-item">
                <i class="fas fa-exclamation-triangle"></i> Damage Products
            </a>
            <a href="replace-product.php" class="menu-item">
                <i class="fas fa-undo-alt"></i> Replace Products
            </a>
            <a href="stock-view.php" class="menu-item">
                <i class="fas fa-boxes"></i> Stock View
            </a>
            <a href="move-history.php" class="menu-item">
                <i class="fas fa-history"></i> Move History
            </a>
            <a href="profile.php" class="menu-item active">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="../auth/logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <h1 class="page-title">My Profile</h1>
            <div class="user-info">
                <span>Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <div class="user-avatar">
                    <?php echo substr($_SESSION['full_name'], 0, 1); ?>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
            </div>
            <div class="profile-name"><?php echo $user['full_name']; ?></div>
            <div class="profile-role"><?php echo ucfirst($user['role']); ?></div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-number"><?php echo $user_stats['receipts_count']; ?></div>
                <div class="stat-label">Receipts</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo $user_stats['deliveries_count']; ?></div>
                <div class="stat-label">Deliveries</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo $user_stats['transfers_count']; ?></div>
                <div class="stat-label">Transfers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo $user_stats['damage_count']; ?></div>
                <div class="stat-label">Damage Reports</div>
            </div>
        </div>
        
        <!-- Profile Tabs -->
        <div class="profile-tabs">
            <button class="tab-btn active" onclick="showTab('info')">Personal Info</button>
            <button class="tab-btn" onclick="showTab('edit')">Edit Profile</button>
            <button class="tab-btn" onclick="showTab('password')">Change Password</button>
        </div>
        
        <!-- Personal Info Tab -->
        <div id="tab-info" class="tab-content active">
            <h2 style="margin-bottom: 20px;">Personal Information</h2>
            
            <div class="info-row">
                <div class="info-label">Username</div>
                <div class="info-value"><?php echo $user['username']; ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Email</div>
                <div class="info-value"><?php echo $user['email']; ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Full Name</div>
                <div class="info-value"><?php echo $user['full_name']; ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Phone</div>
                <div class="info-value"><?php echo $user['phone'] ?: 'Not provided'; ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Address</div>
                <div class="info-value"><?php echo nl2br($user['address'] ?: 'Not provided'); ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Member Since</div>
                <div class="info-value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></div>
            </div>
        </div>
        
        <!-- Edit Profile Tab -->
        <div id="tab-edit" class="tab-content">
            <h2 style="margin-bottom: 20px;">Edit Profile</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo $user['full_name']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" value="<?php echo $user['phone']; ?>">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="4"><?php echo $user['address']; ?></textarea>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </div>
            </form>
        </div>
        
        <!-- Change Password Tab -->
        <div id="tab-password" class="tab-content">
            <h2 style="margin-bottom: 20px;">Change Password</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label for="current_password">Current Password *</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password *</label>
                    <input type="password" id="new_password" name="new_password" required>
                    <small style="color: #6c757d;">Minimum 6 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    function showTab(tab) {
        // Hide all tabs
        document.getElementById('tab-info').classList.remove('active');
        document.getElementById('tab-edit').classList.remove('active');
        document.getElementById('tab-password').classList.remove('active');
        
        // Remove active class from all buttons
        var buttons = document.querySelectorAll('.tab-btn');
        buttons.forEach(btn => btn.classList.remove('active'));
        
        // Show selected tab
        if (tab === 'info') {
            document.getElementById('tab-info').classList.add('active');
            buttons[0].classList.add('active');
        } else if (tab === 'edit') {
            document.getElementById('tab-edit').classList.add('active');
            buttons[1].classList.add('active');
        } else if (tab === 'password') {
            document.getElementById('tab-password').classList.add('active');
            buttons[2].classList.add('active');
        }
    }
    </script>
</body>
</html>