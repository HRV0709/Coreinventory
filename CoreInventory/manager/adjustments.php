<?php
require_once '../includes/config.php';

// Check if user is logged in and is manager
if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

// Handle Adjustment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'adjust') {
        $product_id = intval($_POST['product_id']);
        $warehouse_id = intval($_POST['warehouse_id']);
        $location_id = intval($_POST['location_id']);
        $current_quantity = intval($_POST['current_quantity']);
        $new_quantity = intval($_POST['new_quantity']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        $created_by = $_SESSION['user_id'];
        
        $difference = $new_quantity - $current_quantity;
        
        // Check if stock record exists
        $check_stock = mysqli_query($conn, "SELECT id FROM stock WHERE product_id = $product_id AND warehouse_id = $warehouse_id AND location_id = $location_id");
        
        if (mysqli_num_rows($check_stock) > 0) {
            // Update existing stock
            $update = "UPDATE stock SET quantity = $new_quantity WHERE product_id = $product_id AND warehouse_id = $warehouse_id AND location_id = $location_id";
        } else {
            // Insert new stock record
            $update = "INSERT INTO stock (product_id, warehouse_id, location_id, quantity, min_quantity, max_quantity) 
                       VALUES ($product_id, $warehouse_id, $location_id, $new_quantity, 5, 1000)";
        }
        
        if (mysqli_query($conn, $update)) {
            // Log the adjustment
            $adjustment_number = 'ADJ-' . date('Ymd') . '-' . rand(1000, 9999);
            $log = "INSERT INTO move_history (product_id, from_warehouse, from_location, quantity, move_type, reference_number, moved_by) 
                    VALUES ($product_id, $warehouse_id, $location_id, $difference, 'adjustment', '$adjustment_number', $created_by)";
            mysqli_query($conn, $log);
            
            $message = "Stock adjusted successfully. " . ($difference > 0 ? 'Added ' : 'Removed ') . abs($difference) . " units.";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
    
    // Handle Add New Stock Entry
    elseif ($_POST['action'] == 'add_stock') {
        $product_id = intval($_POST['product_id']);
        $warehouse_id = intval($_POST['warehouse_id']);
        $location_id = intval($_POST['location_id']);
        $quantity = intval($_POST['quantity']);
        $min_quantity = intval($_POST['min_quantity']);
        $max_quantity = intval($_POST['max_quantity']);
        $created_by = $_SESSION['user_id'];
        
        // Check if stock already exists at this location
        $check = mysqli_query($conn, "SELECT id FROM stock WHERE product_id = $product_id AND warehouse_id = $warehouse_id AND location_id = $location_id");
        
        if (mysqli_num_rows($check) > 0) {
            $error = "Stock entry already exists for this product at this location!";
        } else {
            $insert = "INSERT INTO stock (product_id, warehouse_id, location_id, quantity, min_quantity, max_quantity) 
                       VALUES ($product_id, $warehouse_id, $location_id, $quantity, $min_quantity, $max_quantity)";
            
            if (mysqli_query($conn, $insert)) {
                $stock_id = mysqli_insert_id($conn);
                
                // Log the initial stock addition
                $reference = 'INIT-' . date('Ymd') . '-' . $stock_id;
                $log = "INSERT INTO move_history (product_id, to_warehouse, to_location, quantity, move_type, reference_number, moved_by) 
                        VALUES ($product_id, $warehouse_id, $location_id, $quantity, 'receipt', '$reference', $created_by)";
                mysqli_query($conn, $log);
                
                $message = "New stock entry added successfully!";
            } else {
                $error = "Error adding stock: " . mysqli_error($conn);
            }
        }
    }
}

// Get all products for dropdown
$products = mysqli_query($conn, "SELECT id, name, sku FROM products ORDER BY name");

// Get warehouses
$warehouses = mysqli_query($conn, "SELECT id, name FROM warehouses WHERE status = 'active' ORDER BY name");

// Get all locations
$locations_all = mysqli_query($conn, "SELECT l.*, w.name as warehouse_name FROM locations l JOIN warehouses w ON l.warehouse_id = w.id ORDER BY w.name, l.name");

// Get stock items for display - only products that have stock entries
$stock_items = mysqli_query($conn, "
    SELECT 
        p.id as product_id,
        p.name as product_name,
        p.sku,
        s.quantity,
        s.min_quantity,
        w.name as warehouse_name,
        l.name as location_name,
        s.warehouse_id,
        s.location_id
    FROM stock s
    JOIN products p ON s.product_id = p.id
    JOIN warehouses w ON s.warehouse_id = w.id
    JOIN locations l ON s.location_id = l.id
    ORDER BY p.name, w.name, l.name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Adjustments - Manager Panel - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/manager.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .adjustment-form {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 2px solid #f39c12;
        }
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .section-title i {
            font-size: 24px;
            color: #f39c12;
        }
        .add-stock-form {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            border: 2px dashed #28a745;
        }
        .toggle-form-btn {
            background: #28a745;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        .toggle-form-btn:hover {
            background: #2ecc71;
            transform: translateY(-2px);
        }
        .form-row {
            margin-bottom: 15px;
        }
        .form-row label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
        }
        .form-row input,
        .form-row select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
        }
        .form-row input:focus,
        .form-row select:focus {
            border-color: #f39c12;
            outline: none;
        }
        .btn-adjust {
            background: #f39c12;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-adjust:hover {
            background: #e67e22;
            transform: translateY(-2px);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-low {
            background: #dc3545;
            color: white;
        }
        .status-normal {
            background: #28a745;
            color: white;
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
            <a href="adjustments.php" class="menu-item active">
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
            <h1 class="page-title">Inventory Adjustments</h1>
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
        
        <!-- Adjustment Form -->
        <div class="adjustment-form">
            <div class="section-title">
                <i class="fas fa-balance-scale"></i>
                <h2>Make Stock Adjustment</h2>
            </div>
            
            <form method="POST" action="" onsubmit="return validateAdjustment()">
                <input type="hidden" name="action" value="adjust">
                <input type="hidden" name="current_quantity" id="current_quantity">
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <div class="form-group">
                        <label for="product_id">Product *</label>
                        <select id="product_id" name="product_id" required onchange="loadStockInfo()">
                            <option value="">Select Product</option>
                            <?php 
                            mysqli_data_seek($products, 0);
                            while($product = mysqli_fetch_assoc($products)): 
                            ?>
                            <option value="<?php echo $product['id']; ?>"><?php echo $product['name']; ?> (<?php echo $product['sku']; ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="warehouse_id">Warehouse *</label>
                        <select id="warehouse_id" name="warehouse_id" required onchange="updateLocations()">
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
                        <select id="location_id" name="location_id" required onchange="loadStockInfo()">
                            <option value="">Select Location</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Current Stock</label>
                        <div id="current_stock_display" style="padding: 12px; background: #f8f9fa; border-radius: 6px; font-weight: bold; font-size: 18px;">-</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_quantity">New Quantity *</label>
                        <input type="number" id="new_quantity" name="new_quantity" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reason">Reason for Adjustment *</label>
                        <select id="reason" name="reason" required>
                            <option value="Physical Count">Physical Count</option>
                            <option value="Damage">Damage</option>
                            <option value="Loss">Loss</option>
                            <option value="Found">Found</option>
                            <option value="Correction">Correction</option>
                        </select>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn-adjust">
                        <i class="fas fa-check"></i> Apply Adjustment
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Current Stock Table -->
        <div class="table-responsive">
            <h2 style="margin-bottom: 20px;">Current Stock Levels</h2>
            
            <?php if (mysqli_num_rows($stock_items) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Warehouse</th>
                            <th>Location</th>
                            <th>Current Quantity</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($stock = mysqli_fetch_assoc($stock_items)): ?>
                        <tr>
                            <td><?php echo $stock['product_name']; ?></td>
                            <td><?php echo $stock['sku']; ?></td>
                            <td><?php echo $stock['warehouse_name']; ?></td>
                            <td><?php echo $stock['location_name']; ?></td>
                            <td><strong><?php echo $stock['quantity']; ?></strong></td>
                            <td>
                                <?php if ($stock['quantity'] <= $stock['min_quantity']): ?>
                                    <span class="status-badge status-low">Low Stock</span>
                                <?php else: ?>
                                    <span class="status-badge status-normal">Normal</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="quickAdjust(
                                    <?php echo $stock['product_id']; ?>, 
                                    <?php echo $stock['warehouse_id']; ?>, 
                                    <?php echo $stock['location_id']; ?>, 
                                    <?php echo $stock['quantity']; ?>
                                )">
                                    <i class="fas fa-edit"></i> Adjust
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 50px; background: white; border-radius: 12px;">
                    <i class="fas fa-box-open" style="font-size: 48px; color: #6c757d; margin-bottom: 15px;"></i>
                    <h3 style="color: #6c757d; margin-bottom: 10px;">No Stock Entries Found</h3>
                    <p style="color: #95a5a6;">Please add stock entries first to start making adjustments.</p>
                </div>
            <?php endif; ?>
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
    
    // Store stock data
    var stockData = <?php 
        $stock_result = mysqli_query($conn, "SELECT product_id, warehouse_id, location_id, quantity FROM stock");
        $stock_array = [];
        while($s = mysqli_fetch_assoc($stock_result)) {
            $stock_array[] = $s;
        }
        echo json_encode($stock_array);
    ?>;
    
    function updateLocations() {
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
    
    document.getElementById('warehouse_id').addEventListener('change', updateLocations);
    
    function loadStockInfo() {
        var productId = document.getElementById('product_id').value;
        var warehouseId = document.getElementById('warehouse_id').value;
        var locationId = document.getElementById('location_id').value;
        
        if (productId && warehouseId && locationId) {
            var currentQty = 0;
            stockData.forEach(function(item) {
                if (item.product_id == productId && item.warehouse_id == warehouseId && item.location_id == locationId) {
                    currentQty = item.quantity;
                }
            });
            
            document.getElementById('current_stock_display').innerHTML = currentQty;
            document.getElementById('current_quantity').value = currentQty;
        }
    }
    
    function quickAdjust(productId, warehouseId, locationId, currentQty) {
        document.getElementById('product_id').value = productId;
        document.getElementById('warehouse_id').value = warehouseId;
        updateLocations();
        
        setTimeout(function() {
            document.getElementById('location_id').value = locationId;
            document.getElementById('current_stock_display').innerHTML = currentQty;
            document.getElementById('current_quantity').value = currentQty;
            document.getElementById('new_quantity').value = currentQty;
            document.getElementById('new_quantity').focus();
            
            // Scroll to adjustment form
            document.querySelector('.adjustment-form').scrollIntoView({ behavior: 'smooth' });
        }, 500);
    }
    
    function validateAdjustment() {
        var current = parseInt(document.getElementById('current_quantity').value);
        var newQty = parseInt(document.getElementById('new_quantity').value);
        
        if (isNaN(current) || current < 0) {
            alert('Please select a product and location first');
            return false;
        }
        
        var diff = newQty - current;
        if (diff == 0) {
            alert('New quantity is same as current quantity. No adjustment needed.');
            return false;
        }
        
        return confirm('Are you sure you want to adjust stock by ' + (diff > 0 ? '+' : '') + diff + ' units?');
    }
    
    // Auto-hide messages after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            alert.style.transition = 'opacity 1s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 1000);
        });
    }, 5000);
    </script>
</body>
</html>