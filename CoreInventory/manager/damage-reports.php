<?php
require_once '../includes/config.php';

// Check if user is logged in and is manager
if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../auth/login.php');
}

// Get damage reports with statistics
$damage_stats = mysqli_query($conn, "
    SELECT 
        status,
        COUNT(*) as count,
        SUM(quantity) as total_quantity
    FROM damage_products
    GROUP BY status
");

$total_damaged = mysqli_query($conn, "SELECT SUM(quantity) as total FROM damage_products");
$total_damaged = mysqli_fetch_assoc($total_damaged)['total'];

// Get detailed damage reports
$damage_details = mysqli_query($conn, "
    SELECT d.*, 
           p.name as product_name,
           p.sku,
           p.category,
           w.name as warehouse_name,
           l.name as location_name,
           u.full_name as reported_by_name
    FROM damage_products d
    JOIN products p ON d.product_id = p.id
    LEFT JOIN warehouses w ON d.warehouse_id = w.id
    LEFT JOIN locations l ON d.location_id = l.id
    JOIN users u ON d.reported_by = u.id
    ORDER BY d.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Damage Reports - Manager Panel - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/manager.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar (similar to previous) -->
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
            <a href="damage-reports.php" class="menu-item active">
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
            <h1 class="page-title">Damage Reports</h1>
            <div class="user-info">
                <span>Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <div class="user-avatar">
                    <?php echo substr($_SESSION['full_name'], 0, 1); ?>
                </div>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
            <div style="background: white; padding: 20px; border-radius: 12px; border-left: 4px solid #dc3545;">
                <div style="color: #6c757d; font-size: 14px;">Total Damaged</div>
                <div style="font-size: 28px; font-weight: 600;"><?php echo $total_damaged ?: 0; ?></div>
                <div style="color: #28a745; font-size: 12px;">units</div>
            </div>
            
            <?php 
            mysqli_data_seek($damage_stats, 0);
            while($stat = mysqli_fetch_assoc($damage_stats)): 
            ?>
            <div style="background: white; padding: 20px; border-radius: 12px; border-left: 4px solid #ffc107;">
                <div style="color: #6c757d; font-size: 14px;"><?php echo ucfirst($stat['status']); ?></div>
                <div style="font-size: 28px; font-weight: 600;"><?php echo $stat['count']; ?></div>
                <div style="color: #6c757d; font-size: 12px;"><?php echo $stat['total_quantity']; ?> units</div>
            </div>
            <?php endwhile; ?>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <select class="filter-select" onchange="filterByStatus(this.value)">
                <option value="all">All Status</option>
                <option value="reported">Reported</option>
                <option value="inspected">Inspected</option>
                <option value="replaced">Replaced</option>
                <option value="disposed">Disposed</option>
            </select>
            
            <select class="filter-select" onchange="filterByMonth(this.value)">
                <option value="all">All Time</option>
                <option value="1">Last 30 Days</option>
                <option value="3">Last 3 Months</option>
                <option value="6">Last 6 Months</option>
                <option value="12">Last Year</option>
            </select>
            
            <input type="text" class="filter-select" placeholder="Search products..." style="flex: 1;" id="searchInput">
        </div>
        
        <!-- Damage Reports Table -->
        <div class="table-responsive">
            <table class="data-table" id="damageTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Reported By</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($damage = mysqli_fetch_assoc($damage_details)): ?>
                    <tr data-status="<?php echo $damage['status']; ?>" data-date="<?php echo $damage['damage_date']; ?>">
                        <td><?php echo date('Y-m-d', strtotime($damage['damage_date'])); ?></td>
                        <td><strong><?php echo $damage['product_name']; ?></strong></td>
                        <td><?php echo $damage['sku']; ?></td>
                        <td><?php echo $damage['category']; ?></td>
                        <td><?php echo $damage['quantity']; ?></td>
                        <td><?php echo $damage['warehouse_name']; ?> - <?php echo $damage['location_name']; ?></td>
                        <td>
                            <span class="damage-badge badge-<?php echo $damage['status']; ?>">
                                <?php echo ucfirst($damage['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $damage['reported_by_name']; ?></td>
                        <td><?php echo $damage['reason']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Export Button -->
        <div style="margin-top: 20px; text-align: right;">
            <button class="btn btn-primary" onclick="exportReport()">
                <i class="fas fa-download"></i> Export Report
            </button>
        </div>
    </div>
    
    <script>
    function filterByStatus(status) {
        var rows = document.querySelectorAll('#damageTable tbody tr');
        var searchText = document.getElementById('searchInput').value.toLowerCase();
        
        rows.forEach(function(row) {
            var rowStatus = row.getAttribute('data-status');
            var rowText = row.textContent.toLowerCase();
            var statusMatch = (status === 'all' || rowStatus === status);
            var searchMatch = (searchText === '' || rowText.includes(searchText));
            
            row.style.display = (statusMatch && searchMatch) ? '' : 'none';
        });
    }
    
    function filterByMonth(months) {
        // Implementation would filter by date
        // Simplified version
        filterByStatus(document.querySelector('.filter-select').value);
    }
    
    document.getElementById('searchInput').addEventListener('keyup', function() {
        filterByStatus(document.querySelector('.filter-select').value);
    });
    
    function exportReport() {
        alert('Export functionality would generate CSV/PDF report');
    }
    </script>
</body>
</html>