<?php
require_once '../includes/config.php';

// Check if user is logged in and is manager
if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../auth/login.php');
}

// Get current date for filters
$today = date('Y-m-d');
$first_day_month = date('Y-m-01');
$last_day_month = date('Y-m-t');

// ==================== KPI CARDS DATA ====================

// Total Products
$total_products = mysqli_query($conn, "SELECT COUNT(*) as count FROM products");
$total_products = mysqli_fetch_assoc($total_products)['count'];

// Total Stock Value (assuming average price - you may need to adjust)
$total_stock = mysqli_query($conn, "SELECT SUM(quantity) as total FROM stock");
$total_stock = mysqli_fetch_assoc($total_stock)['total'] ?? 0;

// Low Stock Items
$low_stock = mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM stock s 
    JOIN products p ON s.product_id = p.id 
    WHERE s.quantity <= p.reorder_level
");
$low_stock = mysqli_fetch_assoc($low_stock)['count'];

// Out of Stock Items
$out_of_stock = mysqli_query($conn, "
    SELECT COUNT(*) as count 
    FROM stock 
    WHERE quantity = 0
");
$out_of_stock = mysqli_fetch_assoc($out_of_stock)['count'];

// Pending Receipts
$pending_receipts = mysqli_query($conn, "
    SELECT COUNT(*) as count, SUM(quantity) as total_qty 
    FROM receipts 
    WHERE status = 'waiting' OR status = 'ready'
");
$pending_receipts_data = mysqli_fetch_assoc($pending_receipts);
$pending_receipts_count = $pending_receipts_data['count'];
$pending_receipts_qty = $pending_receipts_data['total_qty'] ?? 0;

// Pending Deliveries
$pending_deliveries = mysqli_query($conn, "
    SELECT COUNT(*) as count, SUM(quantity) as total_qty 
    FROM deliveries 
    WHERE status = 'waiting' OR status = 'ready'
");
$pending_deliveries_data = mysqli_fetch_assoc($pending_deliveries);
$pending_deliveries_count = $pending_deliveries_data['count'];
$pending_deliveries_qty = $pending_deliveries_data['total_qty'] ?? 0;

// Pending Transfers
$pending_transfers = mysqli_query($conn, "
    SELECT COUNT(*) as count, SUM(quantity) as total_qty 
    FROM transfers 
    WHERE status = 'waiting' OR status = 'ready'
");
$pending_transfers_data = mysqli_fetch_assoc($pending_transfers);
$pending_transfers_count = $pending_transfers_data['count'];
$pending_transfers_qty = $pending_transfers_data['total_qty'] ?? 0;

// Damaged Products Count
$damaged_count = mysqli_query($conn, "
    SELECT COUNT(*) as count, SUM(quantity) as total_qty 
    FROM damage_products 
    WHERE status = 'reported'
");
$damaged_data = mysqli_fetch_assoc($damaged_count);
$damaged_count = $damaged_data['count'];
$damaged_qty = $damaged_data['total_qty'] ?? 0;

// ==================== TODAY'S ACTIVITY ====================

// Today's Receipts
$today_receipts = mysqli_query($conn, "
    SELECT COUNT(*) as count, SUM(quantity) as total_qty 
    FROM receipts 
    WHERE DATE(created_at) = '$today'
");
$today_receipts_data = mysqli_fetch_assoc($today_receipts);

// Today's Deliveries
$today_deliveries = mysqli_query($conn, "
    SELECT COUNT(*) as count, SUM(quantity) as total_qty 
    FROM deliveries 
    WHERE DATE(created_at) = '$today'
");
$today_deliveries_data = mysqli_fetch_assoc($today_deliveries);

// Today's Transfers
$today_transfers = mysqli_query($conn, "
    SELECT COUNT(*) as count, SUM(quantity) as total_qty 
    FROM transfers 
    WHERE DATE(created_at) = '$today'
");
$today_transfers_data = mysqli_fetch_assoc($today_transfers);

// ==================== MONTHLY STATISTICS ====================

// Monthly Receipts
$monthly_receipts = mysqli_query($conn, "
    SELECT COUNT(*) as count, SUM(quantity) as total_qty 
    FROM receipts 
    WHERE DATE(created_at) BETWEEN '$first_day_month' AND '$last_day_month'
");
$monthly_receipts_data = mysqli_fetch_assoc($monthly_receipts);

// Monthly Deliveries
$monthly_deliveries = mysqli_query($conn, "
    SELECT COUNT(*) as count, SUM(quantity) as total_qty 
    FROM deliveries 
    WHERE DATE(created_at) BETWEEN '$first_day_month' AND '$last_day_month'
");
$monthly_deliveries_data = mysqli_fetch_assoc($monthly_deliveries);

// Monthly Transfers
$monthly_transfers = mysqli_query($conn, "
    SELECT COUNT(*) as count, SUM(quantity) as total_qty 
    FROM transfers 
    WHERE DATE(created_at) BETWEEN '$first_day_month' AND '$last_day_month'
");
$monthly_transfers_data = mysqli_fetch_assoc($monthly_transfers);

// ==================== TOP PRODUCTS ====================

// Most Active Products (by movement)
$top_products = mysqli_query($conn, "
    SELECT 
        p.id,
        p.name,
        p.sku,
        COUNT(m.id) as movement_count,
        SUM(m.quantity) as total_moved
    FROM products p
    LEFT JOIN move_history m ON p.id = m.product_id
    GROUP BY p.id
    ORDER BY movement_count DESC
    LIMIT 5
");

// Low Stock Products
$low_stock_products = mysqli_query($conn, "
    SELECT 
        p.id,
        p.name,
        p.sku,
        s.quantity,
        p.reorder_level,
        w.name as warehouse_name,
        l.name as location_name
    FROM stock s
    JOIN products p ON s.product_id = p.id
    JOIN warehouses w ON s.warehouse_id = w.id
    JOIN locations l ON s.location_id = l.id
    WHERE s.quantity <= p.reorder_level
    ORDER BY s.quantity ASC
    LIMIT 5
");

// ==================== RECENT ACTIVITIES ====================

// Recent Damage Reports
$damage_reports = mysqli_query($conn, "
    SELECT d.*, 
           p.name as product_name,
           u.full_name as reported_by_name
    FROM damage_products d 
    JOIN products p ON d.product_id = p.id 
    JOIN users u ON d.reported_by = u.id
    WHERE d.status = 'reported' 
    ORDER BY d.created_at DESC 
    LIMIT 5
");

// Recent Movements
$recent_movements = mysqli_query($conn, "
    SELECT m.*, 
           p.name as product_name,
           u.full_name as moved_by_name
    FROM move_history m
    JOIN products p ON m.product_id = p.id
    JOIN users u ON m.moved_by = u.id
    ORDER BY m.moved_at DESC
    LIMIT 5
");

// ==================== WAREHOUSE STATS ====================

$warehouse_stats = mysqli_query($conn, "
    SELECT 
        w.name,
        COUNT(DISTINCT s.product_id) as product_count,
        COALESCE(SUM(s.quantity), 0) as total_stock
    FROM warehouses w
    LEFT JOIN stock s ON w.id = s.warehouse_id
    GROUP BY w.id
    ORDER BY total_stock DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/manager.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Additional Dashboard Styles */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .kpi-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid #3498db;
            transition: transform 0.3s ease;
        }
        
        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .kpi-card.warning {
            border-left-color: #e74c3c;
        }
        
        .kpi-card.success {
            border-left-color: #27ae60;
        }
        
        .kpi-card.info {
            border-left-color: #f39c12;
        }
        
        .kpi-title {
            font-size: 13px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .kpi-value {
            font-size: 32px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .kpi-subtitle {
            font-size: 12px;
            color: #95a5a6;
        }
        
        .kpi-icon {
            float: right;
            font-size: 40px;
            color: rgba(52, 152, 219, 0.2);
        }
        
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f2f5;
        }
        
        .card-header h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 18px;
        }
        
        .card-header i {
            color: #3498db;
            font-size: 24px;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #f0f2f5;
            transition: background 0.3s ease;
        }
        
        .activity-item:hover {
            background: #f8f9fa;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #e8f0fe;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #3498db;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 3px;
        }
        
        .activity-time {
            font-size: 11px;
            color: #95a5a6;
        }
        
        .activity-badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .badge-receipt { background: #d4edda; color: #155724; }
        .badge-delivery { background: #f8d7da; color: #721c24; }
        .badge-transfer { background: #cce5ff; color: #004085; }
        .badge-damage { background: #fff3cd; color: #856404; }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #ecf0f1;
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: #3498db;
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .progress-fill.warning {
            background: #e74c3c;
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #ecf0f1;
        }
        
        .stat-row:last-child {
            border-bottom: none;
        }
        
        .view-all {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #ecf0f1;
        }
        
        .view-all a {
            color: #3498db;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
        }
        
        .view-all a:hover {
            text-decoration: underline;
        }
        
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 20px;
        }
        
        .quick-action-btn {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #2c3e50;
        }
        
        .quick-action-btn:hover {
            border-color: #3498db;
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.2);
        }
        
        .quick-action-btn i {
            font-size: 32px;
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .quick-action-btn span {
            display: block;
            font-weight: 500;
        }
        
        .warehouse-stats {
            margin-top: 15px;
        }
        
        .warehouse-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .warehouse-name {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .warehouse-stock {
            background: #e8f0fe;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            color: #3498db;
        }
        
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .row {
                grid-template-columns: 1fr;
            }
            .quick-actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
            <a href="dashboard.php" class="menu-item active">
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
            <h1 class="page-title">Dashboard Overview</h1>
            <div class="user-info">
                <span>Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <div class="user-avatar">
                    <?php echo substr($_SESSION['full_name'], 0, 1); ?>
                </div>
            </div>
        </div>
        
        <!-- Main KPI Cards -->
        <div class="dashboard-grid">
            <div class="kpi-card">
                <i class="fas fa-box kpi-icon"></i>
                <div class="kpi-title">Total Products</div>
                <div class="kpi-value"><?php echo $total_products; ?></div>
                <div class="kpi-subtitle">Active in inventory</div>
            </div>
            
            <div class="kpi-card">
                <i class="fas fa-cubes kpi-icon"></i>
                <div class="kpi-title">Total Stock</div>
                <div class="kpi-value"><?php echo number_format($total_stock); ?></div>
                <div class="kpi-subtitle">Units in warehouse</div>
            </div>
            
            <div class="kpi-card warning">
                <i class="fas fa-exclamation-triangle kpi-icon"></i>
                <div class="kpi-title">Low Stock</div>
                <div class="kpi-value"><?php echo $low_stock; ?></div>
                <div class="kpi-subtitle">Items below reorder level</div>
            </div>
            
            <div class="kpi-card warning">
                <i class="fas fa-times-circle kpi-icon"></i>
                <div class="kpi-title">Out of Stock</div>
                <div class="kpi-value"><?php echo $out_of_stock; ?></div>
                <div class="kpi-subtitle">Items with zero stock</div>
            </div>
            
            <div class="kpi-card info">
                <i class="fas fa-truck-loading kpi-icon"></i>
                <div class="kpi-title">Pending Receipts</div>
                <div class="kpi-value"><?php echo $pending_receipts_count; ?></div>
                <div class="kpi-subtitle"><?php echo $pending_receipts_qty; ?> units to receive</div>
            </div>
            
            <div class="kpi-card info">
                <i class="fas fa-truck kpi-icon"></i>
                <div class="kpi-title">Pending Deliveries</div>
                <div class="kpi-value"><?php echo $pending_deliveries_count; ?></div>
                <div class="kpi-subtitle"><?php echo $pending_deliveries_qty; ?> units to deliver</div>
            </div>
            
            <div class="kpi-card info">
                <i class="fas fa-exchange-alt kpi-icon"></i>
                <div class="kpi-title">Pending Transfers</div>
                <div class="kpi-value"><?php echo $pending_transfers_count; ?></div>
                <div class="kpi-subtitle"><?php echo $pending_transfers_qty; ?> units to transfer</div>
            </div>
            
            <div class="kpi-card warning">
                <i class="fas fa-broken kpi-icon"></i>
                <div class="kpi-title">Damaged Items</div>
                <div class="kpi-value"><?php echo $damaged_count; ?></div>
                <div class="kpi-subtitle"><?php echo $damaged_qty; ?> units damaged</div>
            </div>
        </div>
        
        <!-- Today's Activity Summary -->
        <div class="row">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-day"></i> Today's Activity</h3>
                    <span><?php echo date('F j, Y'); ?></span>
                </div>
                
                <div class="stat-row">
                    <span><i class="fas fa-truck-loading" style="color: #27ae60;"></i> Receipts Today</span>
                    <span><strong><?php echo $today_receipts_data['count'] ?? 0; ?></strong> (<?php echo $today_receipts_data['total_qty'] ?? 0; ?> units)</span>
                </div>
                
                <div class="stat-row">
                    <span><i class="fas fa-truck" style="color: #e74c3c;"></i> Deliveries Today</span>
                    <span><strong><?php echo $today_deliveries_data['count'] ?? 0; ?></strong> (<?php echo $today_deliveries_data['total_qty'] ?? 0; ?> units)</span>
                </div>
                
                <div class="stat-row">
                    <span><i class="fas fa-exchange-alt" style="color: #3498db;"></i> Transfers Today</span>
                    <span><strong><?php echo $today_transfers_data['count'] ?? 0; ?></strong> (<?php echo $today_transfers_data['total_qty'] ?? 0; ?> units)</span>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Monthly Summary</h3>
                    <span><?php echo date('F Y'); ?></span>
                </div>
                
                <div class="stat-row">
                    <span>Total Receipts</span>
                    <span><strong><?php echo $monthly_receipts_data['count'] ?? 0; ?></strong> (<?php echo $monthly_receipts_data['total_qty'] ?? 0; ?> units)</span>
                </div>
                
                <div class="stat-row">
                    <span>Total Deliveries</span>
                    <span><strong><?php echo $monthly_deliveries_data['count'] ?? 0; ?></strong> (<?php echo $monthly_deliveries_data['total_qty'] ?? 0; ?> units)</span>
                </div>
                
                <div class="stat-row">
                    <span>Total Transfers</span>
                    <span><strong><?php echo $monthly_transfers_data['count'] ?? 0; ?></strong> (<?php echo $monthly_transfers_data['total_qty'] ?? 0; ?> units)</span>
                </div>
            </div>
        </div>
        
        <!-- Low Stock and Top Products -->
        <div class="row">
            <!-- Low Stock Products -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-exclamation-circle" style="color: #e74c3c;"></i> Low Stock Alert</h3>
                    <a href="products.php?filter=low_stock" style="color: #3498db; font-size: 13px;">View All</a>
                </div>
                
                <?php if (mysqli_num_rows($low_stock_products) > 0): ?>
                    <?php while($product = mysqli_fetch_assoc($low_stock_products)): ?>
                    <div class="activity-item">
                        <div class="activity-icon" style="background: #fef2f2; color: #e74c3c;">
                            <i class="fas fa-exclamation"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title"><?php echo $product['name']; ?> (<?php echo $product['sku']; ?>)</div>
                            <div style="font-size: 12px; color: #7f8c8d; margin-bottom: 5px;">
                                <?php echo $product['warehouse_name']; ?> - <?php echo $product['location_name']; ?>
                            </div>
                            <div class="progress-bar">
                                <?php 
                                $percentage = min(100, ($product['quantity'] / $product['reorder_level']) * 100);
                                ?>
                                <div class="progress-fill warning" style="width: <?php echo $percentage; ?>%;"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 11px;">
                                <span>Current: <?php echo $product['quantity']; ?></span>
                                <span>Reorder at: <?php echo $product['reorder_level']; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #27ae60; padding: 20px;">
                        <i class="fas fa-check-circle"></i> All products are well stocked!
                    </p>
                <?php endif; ?>
            </div>
            
            <!-- Top Products -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line" style="color: #27ae60;"></i> Most Active Products</h3>
                    <a href="move-history.php" style="color: #3498db; font-size: 13px;">View History</a>
                </div>
                
                <?php if (mysqli_num_rows($top_products) > 0): ?>
                    <?php while($product = mysqli_fetch_assoc($top_products)): ?>
                    <div class="activity-item">
                        <div class="activity-icon" style="background: #e8f5e9; color: #27ae60;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title"><?php echo $product['name']; ?></div>
                            <div style="font-size: 12px; color: #7f8c8d;">
                                SKU: <?php echo $product['sku']; ?>
                            </div>
                            <div style="display: flex; gap: 15px; margin-top: 5px; font-size: 11px;">
                                <span><i class="fas fa-move"></i> <?php echo $product['movement_count']; ?> movements</span>
                                <span><i class="fas fa-cubes"></i> <?php echo $product['total_moved']; ?> units moved</span>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #7f8c8d; padding: 20px;">No movement data available</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="row">
            <!-- Recent Movements -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Stock Movements</h3>
                    <a href="move-history.php" style="color: #3498db; font-size: 13px;">View All</a>
                </div>
                
                <?php if (mysqli_num_rows($recent_movements) > 0): ?>
                    <?php while($move = mysqli_fetch_assoc($recent_movements)): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <?php 
                            $icon = '';
                            $bgColor = '';
                            switch($move['move_type']) {
                                case 'receipt':
                                    $icon = 'fa-truck-loading';
                                    $bgColor = '#d4edda';
                                    $color = '#155724';
                                    break;
                                case 'delivery':
                                    $icon = 'fa-truck';
                                    $bgColor = '#f8d7da';
                                    $color = '#721c24';
                                    break;
                                case 'transfer':
                                    $icon = 'fa-exchange-alt';
                                    $bgColor = '#cce5ff';
                                    $color = '#004085';
                                    break;
                                default:
                                    $icon = 'fa-move';
                                    $bgColor = '#e2e3e5';
                                    $color = '#383d41';
                            }
                            ?>
                            <i class="fas <?php echo $icon; ?>" style="color: <?php echo $color; ?>;"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">
                                <?php echo ucfirst($move['move_type']); ?>: <?php echo $move['product_name']; ?>
                            </div>
                            <div style="font-size: 12px; color: #7f8c8d;">
                                <?php echo $move['quantity']; ?> units • Ref: <?php echo $move['reference_number']; ?>
                            </div>
                            <div class="activity-time">
                                <?php echo date('M d, Y H:i', strtotime($move['moved_at'])); ?> by <?php echo $move['moved_by_name']; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #7f8c8d; padding: 20px;">No recent movements</p>
                <?php endif; ?>
            </div>
            
            <!-- Recent Damage Reports -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-exclamation-triangle" style="color: #e74c3c;"></i> Recent Damage Reports</h3>
                    <a href="damage-reports.php" style="color: #3498db; font-size: 13px;">View All</a>
                </div>
                
                <?php if (mysqli_num_rows($damage_reports) > 0): ?>
                    <?php while($damage = mysqli_fetch_assoc($damage_reports)): ?>
                    <div class="activity-item">
                        <div class="activity-icon" style="background: #fef2f2; color: #e74c3c;">
                            <i class="fas fa-broken"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title"><?php echo $damage['product_name']; ?></div>
                            <div style="font-size: 12px; color: #7f8c8d;">
                                Quantity: <?php echo $damage['quantity']; ?> • Reason: <?php echo $damage['reason']; ?>
                            </div>
                            <div class="activity-time">
                                <?php echo date('M d, Y', strtotime($damage['damage_date'])); ?> reported by <?php echo $damage['reported_by_name']; ?>
                            </div>
                        </div>
                        <div>
                            <span class="damage-badge badge-<?php echo $damage['status']; ?>">
                                <?php echo ucfirst($damage['status']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #27ae60; padding: 20px;">
                        <i class="fas fa-check-circle"></i> No damage reports
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Warehouse Statistics -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <h3><i class="fas fa-warehouse"></i> Warehouse Stock Distribution</h3>
                <a href="warehouses.php" style="color: #3498db; font-size: 13px;">Manage Warehouses</a>
            </div>
            
            <div class="warehouse-stats">
                <?php while($wh = mysqli_fetch_assoc($warehouse_stats)): ?>
                <div class="warehouse-item">
                    <span class="warehouse-name"><?php echo $wh['name']; ?></span>
                    <div>
                        <span class="warehouse-stock"><?php echo $wh['product_count']; ?> products</span>
                        <span style="margin-left: 10px; font-weight: 600;"><?php echo number_format($wh['total_stock']); ?> units</span>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions-grid">
            <a href="receipts.php?action=add" class="quick-action-btn">
                <i class="fas fa-truck-loading"></i>
                <span>New Receipt</span>
            </a>
            <a href="deliveries.php?action=add" class="quick-action-btn">
                <i class="fas fa-truck"></i>
                <span>New Delivery</span>
            </a>
            <a href="transfers.php?action=add" class="quick-action-btn">
                <i class="fas fa-exchange-alt"></i>
                <span>New Transfer</span>
            </a>
            <a href="damage.php?action=add" class="quick-action-btn">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Report Damage</span>
            </a>
        </div>
    </div>
</body>
</html>