<?php
require_once '../includes/config.php';

// Check if user is logged in and is staff
if (!isLoggedIn() || !hasRole('staff')) {
    redirect('../auth/login.php');
}

// Get all stock with details
$stock_query = "
    SELECT 
        p.name as product_name,
        p.sku,
        p.category,
        p.unit_of_measure,
        s.quantity,
        s.min_quantity,
        s.max_quantity,
        w.name as warehouse_name,
        l.name as location_name
    FROM stock s
    JOIN products p ON s.product_id = p.id
    JOIN warehouses w ON s.warehouse_id = w.id
    JOIN locations l ON s.location_id = l.id
    ORDER BY w.name, l.name, p.name
";
$stock = mysqli_query($conn, $stock_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock View - <?php echo SITE_NAME; ?></title>
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
            <a href="stock-view.php" class="menu-item active">
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
            <h1 class="page-title">Stock View</h1>
            <div class="user-info">
                <span>Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <div class="user-avatar">
                    <?php echo substr($_SESSION['full_name'], 0, 1); ?>
                </div>
            </div>
        </div>
        
        <!-- Search Bar -->
        <div style="margin-bottom: 20px;">
            <input type="text" id="stockSearch" placeholder="Search products..." 
                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px;">
        </div>
        
        <!-- Stock Grid -->
        <div class="stock-grid" id="stockGrid">
            <?php while($item = mysqli_fetch_assoc($stock)): ?>
            <div class="stock-card" data-name="<?php echo strtolower($item['product_name']); ?>" 
                 data-sku="<?php echo strtolower($item['sku']); ?>">
                <div class="stock-name"><?php echo $item['product_name']; ?></div>
                <div class="stock-detail">
                    <span>SKU:</span>
                    <span><?php echo $item['sku']; ?></span>
                </div>
                <div class="stock-detail">
                    <span>Category:</span>
                    <span><?php echo $item['category']; ?></span>
                </div>
                <div class="stock-quantity"><?php echo $item['quantity']; ?> <?php echo $item['unit_of_measure']; ?></div>
                <div class="stock-location">
                    <i class="fas fa-map-marker-alt"></i> 
                    <?php echo $item['warehouse_name']; ?> - <?php echo $item['location_name']; ?>
                </div>
                <?php if ($item['quantity'] <= $item['min_quantity']): ?>
                <div style="margin-top: 10px;">
                    <span class="status-badge" style="background: #f8d7da; color: #721c24;">
                        Low Stock
                    </span>
                </div>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    
    <script>
    // Simple search functionality (no JavaScript? This is minimal JS for UX)
    document.getElementById('stockSearch').addEventListener('keyup', function() {
        var searchText = this.value.toLowerCase();
        var cards = document.querySelectorAll('.stock-card');
        
        cards.forEach(function(card) {
            var name = card.getAttribute('data-name');
            var sku = card.getAttribute('data-sku');
            
            if (name.includes(searchText) || sku.includes(searchText)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>