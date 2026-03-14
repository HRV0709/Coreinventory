<?php
require_once '../includes/config.php';

// Check if user is logged in and is manager
if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../auth/login.php');
}

// Get filter parameters
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Build query
$query = "
    SELECT m.*, 
           p.name as product_name,
           p.sku,
           u.full_name as moved_by_name,
           w1.name as from_warehouse_name,
           w2.name as to_warehouse_name,
           l1.name as from_location_name,
           l2.name as to_location_name
    FROM move_history m
    JOIN products p ON m.product_id = p.id
    JOIN users u ON m.moved_by = u.id
    LEFT JOIN warehouses w1 ON m.from_warehouse = w1.id
    LEFT JOIN warehouses w2 ON m.to_warehouse = w2.id
    LEFT JOIN locations l1 ON m.from_location = l1.id
    LEFT JOIN locations l2 ON m.to_location = l2.id
    WHERE 1=1
";

if ($type_filter != 'all') {
    $query .= " AND m.move_type = '$type_filter'";
}

if (!empty($date_filter)) {
    $query .= " AND DATE(m.moved_at) = '$date_filter'";
}

$query .= " ORDER BY m.moved_at DESC LIMIT 1000";

$movements = mysqli_query($conn, $query);

// Get statistics
$stats = mysqli_query($conn, "
    SELECT 
        move_type,
        COUNT(*) as count,
        SUM(quantity) as total_quantity
    FROM move_history
    GROUP BY move_type
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Move History - Manager Panel - <?php echo SITE_NAME; ?></title>
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
            <a href="move-history.php" class="menu-item active">
                <i class="fas fa-history"></i> Move History
            </a>
            <a href="warehouses.php" class="menu-item">
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
            <h1 class="page-title">Stock Movement History</h1>
            <div class="user-info">
                <span>Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <div class="user-avatar">
                    <?php echo substr($_SESSION['full_name'], 0, 1); ?>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 30px;">
            <?php 
            $total_moves = 0;
            $total_qty = 0;
            while($stat = mysqli_fetch_assoc($stats)): 
                $total_moves += $stat['count'];
                $total_qty += $stat['total_quantity'];
            ?>
            <div style="background: white; padding: 15px; border-radius: 8px; text-align: center;">
                <div style="font-size: 20px; font-weight: 600; color: #3498db;"><?php echo $stat['total_quantity']; ?></div>
                <div style="font-size: 12px; color: #7f8c8d;"><?php echo ucfirst($stat['move_type']); ?></div>
                <div style="font-size: 11px;">(<?php echo $stat['count']; ?> moves)</div>
            </div>
            <?php endwhile; ?>
            
            <div style="background: white; padding: 15px; border-radius: 8px; text-align: center; background: #f8f9fa;">
                <div style="font-size: 20px; font-weight: 600; color: #2c3e50;"><?php echo $total_moves; ?></div>
                <div style="font-size: 12px; color: #7f8c8d;">Total Moves</div>
                <div style="font-size: 11px;"><?php echo $total_qty; ?> units</div>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" style="display: flex; gap: 10px; width: 100%;">
                <select name="type" class="filter-select">
                    <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="receipt" <?php echo $type_filter == 'receipt' ? 'selected' : ''; ?>>Receipts</option>
                    <option value="delivery" <?php echo $type_filter == 'delivery' ? 'selected' : ''; ?>>Deliveries</option>
                    <option value="transfer" <?php echo $type_filter == 'transfer' ? 'selected' : ''; ?>>Transfers</option>
                    <option value="adjustment" <?php echo $type_filter == 'adjustment' ? 'selected' : ''; ?>>Adjustments</option>
                    <option value="damage" <?php echo $type_filter == 'damage' ? 'selected' : ''; ?>>Damage</option>
                </select>
                
                <input type="date" name="date" class="filter-select" value="<?php echo $date_filter; ?>">
                
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="move-history.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>
        
        <!-- Movements Table -->
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Type</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Quantity</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Reference</th>
                        <th>Moved By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($movements) > 0): ?>
                        <?php while($move = mysqli_fetch_assoc($movements)): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i', strtotime($move['moved_at'])); ?></td>
                            <td>
                                <span class="status-badge" style="background: 
                                    <?php 
                                    switch($move['move_type']) {
                                        case 'receipt': echo '#28a745'; break;
                                        case 'delivery': echo '#dc3545'; break;
                                        case 'transfer': echo '#17a2b8'; break;
                                        case 'adjustment': echo '#ffc107'; break;
                                        case 'damage': echo '#6c757d'; break;
                                        default: echo '#6c757d';
                                    }
                                    ?>; color: white;">
                                    <?php echo ucfirst($move['move_type']); ?>
                                </span>
                            </td>
                            <td><?php echo $move['product_name']; ?></td>
                            <td><?php echo $move['sku']; ?></td>
                            <td><strong><?php echo $move['quantity']; ?></strong></td>
                            <td>
                                <?php 
                                if ($move['from_warehouse_name']) {
                                    echo $move['from_warehouse_name'];
                                    if ($move['from_location_name']) {
                                        echo ' - ' . $move['from_location_name'];
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($move['to_warehouse_name']) {
                                    echo $move['to_warehouse_name'];
                                    if ($move['to_location_name']) {
                                        echo ' - ' . $move['to_location_name'];
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td><?php echo $move['reference_number']; ?></td>
                            <td><?php echo $move['moved_by_name']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px;">No movement records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>