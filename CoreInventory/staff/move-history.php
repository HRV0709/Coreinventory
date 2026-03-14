<?php
require_once '../includes/config.php';

// Check if user is logged in and is staff
if (!isLoggedIn() || !hasRole('staff')) {
    redirect('../auth/login.php');
}

// Get filter
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';

// Get user's move history
$query = "
    SELECT m.*, 
           p.name as product_name,
           p.sku,
           w1.name as from_warehouse_name,
           w2.name as to_warehouse_name,
           l1.name as from_location_name,
           l2.name as to_location_name
    FROM move_history m
    JOIN products p ON m.product_id = p.id
    LEFT JOIN warehouses w1 ON m.from_warehouse = w1.id
    LEFT JOIN warehouses w2 ON m.to_warehouse = w2.id
    LEFT JOIN locations l1 ON m.from_location = l1.id
    LEFT JOIN locations l2 ON m.to_location = l2.id
    WHERE m.moved_by = " . $_SESSION['user_id'];

if ($type_filter != 'all') {
    $query .= " AND m.move_type = '$type_filter'";
}

$query .= " ORDER BY m.moved_at DESC LIMIT 100";

$movements = mysqli_query($conn, $query);

// Get statistics for user
$stats = mysqli_query($conn, "
    SELECT 
        move_type,
        COUNT(*) as count,
        SUM(quantity) as total_quantity
    FROM move_history
    WHERE moved_by = " . $_SESSION['user_id'] . "
    GROUP BY move_type
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Move History - Staff Panel - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/staff.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .stat-mini-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .stat-mini-value {
            font-size: 20px;
            font-weight: 600;
            color: #3498db;
        }
        .stat-mini-label {
            font-size: 11px;
            color: #7f8c8d;
        }
        .move-type-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            color: white;
        }
        .type-receipt { background: #28a745; }
        .type-delivery { background: #dc3545; }
        .type-transfer { background: #17a2b8; }
        .type-adjustment { background: #ffc107; color: #2c3e50; }
        .type-damage { background: #6c757d; }
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
            <a href="move-history.php" class="menu-item active">
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
            <h1 class="page-title">My Activity History</h1>
            <div class="user-info">
                <span>Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <div class="user-avatar">
                    <?php echo substr($_SESSION['full_name'], 0, 1); ?>
                </div>
            </div>
        </div>
        
        <!-- Mini Statistics -->
        <div class="stats-mini">
            <?php 
            $total_moves = 0;
            $total_qty = 0;
            while($stat = mysqli_fetch_assoc($stats)): 
                $total_moves += $stat['count'];
                $total_qty += $stat['total_quantity'];
            ?>
            <div class="stat-mini-card">
                <div class="stat-mini-value"><?php echo $stat['total_quantity']; ?></div>
                <div class="stat-mini-label"><?php echo ucfirst($stat['move_type']); ?></div>
                <div style="font-size: 10px;">(<?php echo $stat['count']; ?> moves)</div>
            </div>
            <?php endwhile; ?>
            
            <div class="stat-mini-card" style="background: #f8f9fa;">
                <div class="stat-mini-value"><?php echo $total_moves; ?></div>
                <div class="stat-mini-label">Total Moves</div>
                <div style="font-size: 10px;"><?php echo $total_qty; ?> units</div>
            </div>
        </div>
        
        <!-- Filter -->
        <div style="margin-bottom: 20px;">
            <form method="GET" style="display: flex; gap: 10px;">
                <select name="type" style="padding: 8px; border: 2px solid #e0e0e0; border-radius: 4px;">
                    <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="receipt" <?php echo $type_filter == 'receipt' ? 'selected' : ''; ?>>Receipts</option>
                    <option value="delivery" <?php echo $type_filter == 'delivery' ? 'selected' : ''; ?>>Deliveries</option>
                    <option value="transfer" <?php echo $type_filter == 'transfer' ? 'selected' : ''; ?>>Transfers</option>
                    <option value="damage" <?php echo $type_filter == 'damage' ? 'selected' : ''; ?>>Damage</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="move-history.php" class="btn btn-secondary btn-sm">Clear</a>
            </form>
        </div>
        
        <!-- Movements Table -->
        <div style="background: white; border-radius: 10px; padding: 20px; overflow-x: auto;">
            <table class="simple-table">
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
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($movements) > 0): ?>
                        <?php while($move = mysqli_fetch_assoc($movements)): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i', strtotime($move['moved_at'])); ?></td>
                            <td>
                                <span class="move-type-badge type-<?php echo $move['move_type']; ?>">
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
                                        echo '<br><small>' . $move['from_location_name'] . '</small>';
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
                                        echo '<br><small>' . $move['to_location_name'] . '</small>';
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td><small><?php echo $move['reference_number']; ?></small></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px;">No movement records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>