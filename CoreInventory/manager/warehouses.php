<?php
require_once '../includes/config.php';

// Check if user is logged in and is manager
if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../auth/login.php');
}

// Get all warehouses with statistics
$warehouses = mysqli_query($conn, "
    SELECT w.*, 
           COUNT(DISTINCT l.id) as location_count,
           COUNT(DISTINCT s.product_id) as product_count,
           COALESCE(SUM(s.quantity), 0) as total_stock
    FROM warehouses w
    LEFT JOIN locations l ON w.id = l.warehouse_id
    LEFT JOIN stock s ON w.id = s.warehouse_id
    GROUP BY w.id
    ORDER BY w.name
");

// Get locations for each warehouse
$locations = mysqli_query($conn, "
    SELECT l.*, w.name as warehouse_name 
    FROM locations l 
    JOIN warehouses w ON l.warehouse_id = w.id 
    ORDER BY w.name, l.name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouses - Manager Panel - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/manager.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><?php echo SITE_NAME; ?></h2>
            <p>Manager Panel</p>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-category">Dashboard</div>
            <a href="dashboard.php" class="menu-item">
                <i class="fas fa-home"></i> Dashboard
            </a>
            
            <div class="menu-category">Products</div>
            <a href="products.php" class="menu-item">
                <i class="fas fa-box"></i> Products
            </a>
            
            <div class="menu-category">Operations</div>
            <a href="receipts.php" class="menu-item">
                <i class="fas fa-truck-loading"></i> Receipts
            </a>
            <a href="deliveries.php" class="menu-item">
                <i class="fas fa-truck"></i> Delivery Orders
            </a>
            <a href="transfers.php" class="menu-item">
                <i class="fas fa-exchange-alt"></i> Internal Transfers
            </a>
            <a href="adjustments.php" class="menu-item">
                <i class="fas fa-balance-scale"></i> Inventory Adjustment
            </a>
            
            <div class="menu-category">Damage Management</div>
            <a href="damage.php" class="menu-item">
                <i class="fas fa-exclamation-triangle"></i> Damaged Products
            </a>
            <a href="damage-reports.php" class="menu-item">
                <i class="fas fa-file-alt"></i> Damage Reports
            </a>
            <a href="replace-product.php" class="menu-item">
                <i class="fas fa-undo-alt"></i> Replace Products
            </a>
            
            <div class="menu-category">Inventory</div>
            <a href="move-history.php" class="menu-item">
                <i class="fas fa-history"></i> Move History
            </a>
            <a href="warehouses.php" class="menu-item active">
                <i class="fas fa-warehouse"></i> Warehouses
            </a>
            
            <div class="menu-category">Account</div>
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
            <h1 class="page-title">Warehouse Management</h1>
            <div class="user-info">
                <span>Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <div class="user-avatar">
                    <?php echo substr($_SESSION['full_name'], 0, 1); ?>
                </div>
            </div>
        </div>
        
        <!-- Warehouse Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <?php while($warehouse = mysqli_fetch_assoc($warehouses)): ?>
            <div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <div style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0;"><?php echo $warehouse['name']; ?></h3>
                        <span style="background: rgba(255,255,255,0.2); padding: 5px 10px; border-radius: 20px; font-size: 12px;">
                            <?php echo ucfirst($warehouse['status']); ?>
                        </span>
                    </div>
                    <p style="margin: 5px 0 0; opacity: 0.9; font-size: 14px;">
                        Code: <?php echo $warehouse['short_code'] ?: 'N/A'; ?> | Type: <?php echo ucfirst($warehouse['type']); ?>
                    </p>
                </div>
                
                <div style="padding: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px;">
                        <div style="text-align: center;">
                            <div style="font-size: 20px; font-weight: 600; color: #2c3e50;"><?php echo $warehouse['location_count']; ?></div>
                            <div style="font-size: 12px; color: #7f8c8d;">Locations</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 20px; font-weight: 600; color: #2c3e50;"><?php echo $warehouse['product_count']; ?></div>
                            <div style="font-size: 12px; color: #7f8c8d;">Products</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 20px; font-weight: 600; color: #2c3e50;"><?php echo $warehouse['total_stock']; ?></div>
                            <div style="font-size: 12px; color: #7f8c8d;">Total Stock</div>
                        </div>
                    </div>
                    
                    <?php if ($warehouse['address']): ?>
                    <p style="color: #7f8c8d; font-size: 13px; margin-bottom: 15px;">
                        <i class="fas fa-map-marker-alt"></i> <?php echo $warehouse['address']; ?>
                    </p>
                    <?php endif; ?>
                    
                    <button class="btn btn-sm btn-primary" onclick="viewWarehouse(<?php echo $warehouse['id']; ?>)">
                        View Details
                    </button>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        
        <!-- Locations List -->
        <div class="table-responsive">
            <h2 style="margin-bottom: 20px;">All Locations</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Warehouse</th>
                        <th>Location Name</th>
                        <th>Code</th>
                        <th>Type</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    mysqli_data_seek($locations, 0);
                    while($location = mysqli_fetch_assoc($locations)): 
                    ?>
                    <tr>
                        <td><?php echo $location['warehouse_name']; ?></td>
                        <td><?php echo $location['name']; ?></td>
                        <td><?php echo $location['short_code']; ?></td>
                        <td><?php echo ucfirst($location['type']); ?></td>
                        <td><span class="status-badge status-active">Active</span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
    function viewWarehouse(id) {
        alert('View warehouse details for ID: ' + id);
        // Would redirect to warehouse detail page
    }
    </script>
</body>
</html>