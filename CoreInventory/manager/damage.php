<?php
require_once '../includes/config.php';

// Check if user is logged in and is manager
if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

// Handle Report Damage
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'report') {
        $product_id = intval($_POST['product_id']);
        $warehouse_id = intval($_POST['warehouse_id']);
        $location_id = intval($_POST['location_id']);
        $quantity = intval($_POST['quantity']);
        $damage_date = mysqli_real_escape_string($conn, $_POST['damage_date']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        $reported_by = $_SESSION['user_id'];
        
        // Check stock availability
        $stock_check = mysqli_query($conn, "SELECT quantity FROM stock WHERE product_id = $product_id AND warehouse_id = $warehouse_id AND location_id = $location_id");
        
        if (mysqli_num_rows($stock_check) == 0) {
            $error = "No stock found at selected location";
        } else {
            $stock = mysqli_fetch_assoc($stock_check);
            if ($stock['quantity'] < $quantity) {
                $error = "Insufficient stock. Available: " . $stock['quantity'];
            } else {
                // Insert damage record
                $query = "INSERT INTO damage_products (product_id, warehouse_id, location_id, quantity, damage_date, reason, reported_by, status) 
                          VALUES ($product_id, $warehouse_id, $location_id, $quantity, '$damage_date', '$reason', $reported_by, 'reported')";
                
                if (mysqli_query($conn, $query)) {
                    $damage_id = mysqli_insert_id($conn);
                    
                    // Reduce stock
                    $new_quantity = $stock['quantity'] - $quantity;
                    $update = "UPDATE stock SET quantity = $new_quantity WHERE product_id = $product_id AND warehouse_id = $warehouse_id AND location_id = $location_id";
                    mysqli_query($conn, $update);
                    
                    // Log movement
                    $damage_number = 'DAM-' . date('Ymd') . '-' . $damage_id;
                    $log = "INSERT INTO move_history (product_id, from_warehouse, from_location, quantity, move_type, reference_number, moved_by) 
                            VALUES ($product_id, $warehouse_id, $location_id, $quantity, 'damage', '$damage_number', $reported_by)";
                    mysqli_query($conn, $log);
                    
                    $message = "Damage reported successfully. Stock reduced by $quantity units.";
                } else {
                    $error = "Error: " . mysqli_error($conn);
                }
            }
        }
    }
    elseif ($_POST['action'] == 'update_status') {
        $id = intval($_POST['id']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        $query = "UPDATE damage_products SET status = '$status' WHERE id = $id";
        
        if (mysqli_query($conn, $query)) {
            $message = "Damage report status updated";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Get all damage reports
$damage_reports = mysqli_query($conn, "
    SELECT d.*, 
           p.name as product_name,
           p.sku,
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

// Get products, warehouses, locations for dropdowns
$products = mysqli_query($conn, "SELECT id, name, sku FROM products ORDER BY name");
$warehouses = mysqli_query($conn, "SELECT id, name FROM warehouses WHERE status = 'active' ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Damaged Products - Manager Panel - <?php echo SITE_NAME; ?></title>
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
            <a href="damage.php" class="menu-item active">
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
            <h1 class="page-title">Damaged Products</h1>
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
        
        <!-- Report Damage Button -->
        <button class="btn btn-danger" onclick="toggleForm()" style="margin-bottom: 20px;">
            <i class="fas fa-exclamation-triangle"></i> Report Damaged Product
        </button>
        
        <!-- Report Damage Form -->
        <div id="addForm" style="display: none; background: white; border-radius: 12px; padding: 20px; margin-bottom: 30px;">
            <h2 style="margin-bottom: 20px;">Report Damaged Product</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="report">
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                    <div class="form-group">
                        <label for="product_id">Product *</label>
                        <select id="product_id" name="product_id" required onchange="loadStockLocations()">
                            <option value="">Select Product</option>
                            <?php while($product = mysqli_fetch_assoc($products)): ?>
                            <option value="<?php echo $product['id']; ?>"><?php echo $product['name']; ?> (<?php echo $product['sku']; ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="warehouse_id">Warehouse *</label>
                        <select id="warehouse_id" name="warehouse_id" required onchange="loadLocations()">
                            <option value="">Select Warehouse</option>
                            <?php 
                            mysqli_data_seek($warehouses, 0);
                            while($wh = mysqli_fetch_assoc($warehouses)): 
                            ?>
                            <option value="<?php echo $wh['id']; ?>"><?php echo $wh['name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="location_id">Location *</label>
                        <select id="location_id" name="location_id" required>
                            <option value="">Select Location</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">Damaged Quantity *</label>
                        <input type="number" id="quantity" name="quantity" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="damage_date">Damage Date *</label>
                        <input type="date" id="damage_date" name="damage_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group" style="grid-column: span 2;">
                        <label for="reason">Reason for Damage *</label>
                        <textarea id="reason" name="reason" rows="3" required></textarea>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-danger">Report Damage</button>
                    <button type="button" class="btn btn-secondary" onclick="toggleForm()">Cancel</button>
                </div>
            </form>
        </div>
        
        <!-- Damage Reports Table -->
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Reported By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($damage = mysqli_fetch_assoc($damage_reports)): ?>
                    <tr>
                        <td>#<?php echo $damage['id']; ?></td>
                        <td><?php echo $damage['damage_date']; ?></td>
                        <td><?php echo $damage['product_name']; ?><br><small><?php echo $damage['sku']; ?></small></td>
                        <td><?php echo $damage['quantity']; ?></td>
                        <td><?php echo $damage['warehouse_name']; ?> - <?php echo $damage['location_name']; ?></td>
                        <td>
                            <span class="damage-badge badge-<?php echo $damage['status']; ?>">
                                <?php echo ucfirst($damage['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $damage['reported_by_name']; ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="viewDetails(<?php echo $damage['id']; ?>)">View</button>
                            <?php if ($damage['status'] == 'reported'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="id" value="<?php echo $damage['id']; ?>">
                                <select name="status" onchange="this.form.submit()" style="padding: 5px; border-radius: 4px;">
                                    <option value="">Update</option>
                                    <option value="inspected">Inspected</option>
                                    <option value="replaced">Replaced</option>
                                    <option value="disposed">Disposed</option>
                                </select>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
    // Store locations data
    var locations = <?php 
        $loc_result = mysqli_query($conn, "SELECT l.*, w.name as warehouse_name FROM locations l JOIN warehouses w ON l.warehouse_id = w.id");
        $locs = [];
        while($loc = mysqli_fetch_assoc($loc_result)) {
            $locs[] = $loc;
        }
        echo json_encode($locs);
    ?>;
    
    function toggleForm() {
        var form = document.getElementById('addForm');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
    
    function loadLocations() {
        var warehouseId = document.getElementById('warehouse_id').value;
        var select = document.getElementById('location_id');
        select.innerHTML = '<option value="">Select Location</option>';
        
        locations.forEach(function(loc) {
            if (loc.warehouse_id == warehouseId) {
                var option = document.createElement('option');
                option.value = loc.id;
                option.textContent = loc.name;
                select.appendChild(option);
            }
        });
    }
    
    function loadStockLocations() {
        // This would filter locations that have stock of selected product
        // Simplified version
        loadLocations();
    }
    
    function viewDetails(id) {
        alert('View damage details for ID: ' + id);
    }
    </script>
</body>
</html>