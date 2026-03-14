<?php
require_once '../includes/config.php';

// Check if user is logged in and is staff
if (!isLoggedIn() || !hasRole('staff')) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

// Handle Replacement Request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'request') {
        $damage_id = intval($_POST['damage_id']);
        $notes = mysqli_real_escape_string($conn, $_POST['notes']);
        $requested_by = $_SESSION['user_id'];
        
        // Check if damage record exists and belongs to this user
        $check = mysqli_query($conn, "SELECT * FROM damage_products WHERE id = $damage_id AND reported_by = $requested_by");
        
        if (mysqli_num_rows($check) == 0) {
            $error = "Invalid damage report";
        } else {
            // In a real system, you'd have a replacement_requests table
            // For now, we'll just update the damage record
            $update = "UPDATE damage_products SET status = 'replacement_requested', notes = CONCAT(notes, ' | Replacement requested: ', '$notes') WHERE id = $damage_id";
            
            if (mysqli_query($conn, $update)) {
                $message = "Replacement request submitted successfully";
            } else {
                $error = "Error: " . mysqli_error($conn);
            }
        }
    }
}

// Get user's damage reports that are eligible for replacement
$damage_reports = mysqli_query($conn, "
    SELECT d.*, 
           p.name as product_name,
           p.sku,
           w.name as warehouse_name,
           l.name as location_name
    FROM damage_products d
    JOIN products p ON d.product_id = p.id
    LEFT JOIN warehouses w ON d.warehouse_id = w.id
    LEFT JOIN locations l ON d.location_id = l.id
    WHERE d.reported_by = " . $_SESSION['user_id'] . " 
    AND d.status IN ('reported', 'inspected')
    ORDER BY d.created_at DESC
");

// Get replacement history (simplified - would come from replacement table)
$replacements = mysqli_query($conn, "
    SELECT d.*, p.name as product_name
    FROM damage_products d
    JOIN products p ON d.product_id = p.id
    WHERE d.reported_by = " . $_SESSION['user_id'] . " 
    AND d.status = 'replaced'
    ORDER BY d.created_at DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Replacement - Staff Panel - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/staff.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .replacement-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #17a2b8;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .product-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        .product-name {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        .product-sku {
            color: #6c757d;
            font-size: 13px;
        }
        .damage-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        .detail-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        .detail-label {
            font-size: 11px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
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
            <a href="replace-product.php" class="menu-item active">
                <i class="fas fa-undo-alt"></i> Replace Products
            </a>
            <a href="stock-view.php" class="menu-item">
                <i class="fas fa-boxes"></i> Stock View
            </a>
            <a href="move-history.php" class="menu-item">
                <i class="fas fa-history"></i> Move History
            </a>
            <a href="profile.php" class="menu-item">
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
            <h1 class="page-title">Request Product Replacement</h1>
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
        
        <!-- Eligible Damages for Replacement -->
        <h2 style="margin-bottom: 15px;">Products Eligible for Replacement</h2>
        
        <?php if (mysqli_num_rows($damage_reports) > 0): ?>
            <?php while($damage = mysqli_fetch_assoc($damage_reports)): ?>
            <div class="replacement-card">
                <div class="product-info">
                    <div>
                        <span class="product-name"><?php echo $damage['product_name']; ?></span>
                        <span class="product-sku">(<?php echo $damage['sku']; ?>)</span>
                    </div>
                    <span class="damage-badge badge-<?php echo $damage['status']; ?>">
                        <?php echo ucfirst($damage['status']); ?>
                    </span>
                </div>
                
                <div class="damage-details">
                    <div class="detail-item">
                        <div class="detail-label">Quantity Damaged</div>
                        <div class="detail-value"><?php echo $damage['quantity']; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Damage Date</div>
                        <div class="detail-value"><?php echo $damage['damage_date']; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Location</div>
                        <div class="detail-value"><?php echo $damage['warehouse_name']; ?></div>
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <strong>Reason:</strong> <?php echo $damage['reason']; ?>
                </div>
                
                <form method="POST" action="" onsubmit="return confirm('Submit replacement request for this item?');">
                    <input type="hidden" name="action" value="request">
                    <input type="hidden" name="damage_id" value="<?php echo $damage['id']; ?>">
                    
                    <div style="display: flex; gap: 10px;">
                        <input type="text" name="notes" placeholder="Additional notes (optional)" style="flex: 1; padding: 8px; border: 2px solid #e0e0e0; border-radius: 4px;">
                        <button type="submit" class="btn btn-primary">Request Replacement</button>
                    </div>
                </form>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="alert alert-info" style="margin-bottom: 30px;">
                No products eligible for replacement at this time.
            </div>
        <?php endif; ?>
        
        <!-- Replacement History -->
        <h2 style="margin: 30px 0 15px;">Replacement History</h2>
        
        <div style="background: white; border-radius: 10px; padding: 20px;">
            <?php if (mysqli_num_rows($replacements) > 0): ?>
                <table class="simple-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($rep = mysqli_fetch_assoc($replacements)): ?>
                        <tr>
                            <td><?php echo $rep['damage_date']; ?></td>
                            <td><?php echo $rep['product_name']; ?></td>
                            <td><?php echo $rep['quantity']; ?></td>
                            <td>
                                <span class="damage-badge badge-replaced">Replaced</span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #6c757d; padding: 20px;">No replacement history</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>