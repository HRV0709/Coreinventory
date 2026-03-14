<?php
require_once '../includes/config.php';

// Check if user is logged in and is staff
if (!isLoggedIn() || !hasRole('staff')) {
    redirect('../auth/login.php');
}

// Get operational data
$pending_receipts = mysqli_query($conn, "SELECT COUNT(*) as count FROM receipts WHERE status = 'waiting'");
$pending_receipts = mysqli_fetch_assoc($pending_receipts)['count'];

$pending_deliveries = mysqli_query($conn, "SELECT COUNT(*) as count FROM deliveries WHERE status = 'waiting'");
$pending_deliveries = mysqli_fetch_assoc($pending_deliveries)['count'];

$pending_transfers = mysqli_query($conn, "SELECT COUNT(*) as count FROM transfers WHERE status = 'waiting'");
$pending_transfers = mysqli_fetch_assoc($pending_transfers)['count'];

$low_stock = mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM stock s 
    JOIN products p ON s.product_id = p.id 
    WHERE s.quantity <= p.reorder_level
");
$low_stock = mysqli_fetch_assoc($low_stock)['count'];

// Get recent stock movements
$recent_moves = mysqli_query($conn, "
    SELECT m.*, p.name as product_name 
    FROM move_history m 
    JOIN products p ON m.product_id = p.id 
    ORDER BY m.moved_at DESC 
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/staff.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><?php echo SITE_NAME; ?></h2>
            <p>Staff Panel</p>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item active">
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
            <h1 class="page-title">Staff Dashboard</h1>
            <div class="user-info">
                <span>Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <div class="user-avatar">
                    <?php echo substr($_SESSION['full_name'], 0, 1); ?>
                </div>
            </div>
        </div>
        
        <!-- Simple Stats -->
        <div class="stats-simple">
            <div class="stat-box">
                <div class="stat-label">Pending Receipts</div>
                <div class="stat-number"><?php echo $pending_receipts; ?></div>
            </div>
            
            <div class="stat-box">
                <div class="stat-label">Pending Deliveries</div>
                <div class="stat-number"><?php echo $pending_deliveries; ?></div>
            </div>
            
            <div class="stat-box">
                <div class="stat-label">Pending Transfers</div>
                <div class="stat-number"><?php echo $pending_transfers; ?></div>
            </div>
            
            <div class="stat-box warning">
                <div class="stat-label">Low Stock Items</div>
                <div class="stat-number"><?php echo $low_stock; ?></div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="receipts.php?action=add" class="action-btn">
                <i class="fas fa-plus"></i> New Receipt
            </a>
            <a href="deliveries.php?action=add" class="action-btn">
                <i class="fas fa-plus"></i> New Delivery
            </a>
            <a href="transfers.php?action=add" class="action-btn">
                <i class="fas fa-plus"></i> New Transfer
            </a>
            <a href="damage.php?action=add" class="action-btn">
                <i class="fas fa-plus"></i> Report Damage
            </a>
            <a href="stock-view.php" class="action-btn">
                <i class="fas fa-search"></i> View Stock
            </a>
        </div>
        
        <!-- Recent Movements -->
        <div style="background: white; border-radius: 8px; padding: 20px; margin-top: 20px;">
            <h2 style="margin-bottom: 15px; font-size: 18px;">Recent Stock Movements</h2>
            
            <table class="simple-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Reference</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($move = mysqli_fetch_assoc($recent_moves)): ?>
                    <tr>
                        <td><?php echo $move['product_name']; ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $move['move_type']; ?>">
                                <?php echo ucfirst($move['move_type']); ?>
                            </span>
                        </td>
                        <td><?php echo $move['quantity']; ?></td>
                        <td><?php echo $move['reference_number']; ?></td>
                        <td><?php echo date('H:i', strtotime($move['moved_at'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>