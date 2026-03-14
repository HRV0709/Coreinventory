<?php
require_once '../includes/config.php';

// Check if user is logged in and is staff
if (!isLoggedIn() || !hasRole('staff')) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

// Handle Add Transfer
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $transfer_number = 'TRF-' . date('Ymd') . '-' . rand(1000, 9999);
        $from_warehouse = intval($_POST['from_warehouse']);
        $to_warehouse = intval($_POST['to_warehouse']);
        $from_location = intval($_POST['from_location']);
        $to_location = intval($_POST['to_location']);
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        $transfer_date = mysqli_real_escape_string($conn, $_POST['transfer_date']);
        $created_by = $_SESSION['user_id'];
        
        // Check source stock
        $stock_check = mysqli_query($conn, "SELECT id, quantity FROM stock WHERE product_id = $product_id AND warehouse_id = $from_warehouse AND location_id = $from_location");
        
        if (mysqli_num_rows($stock_check) == 0) {
            $error = "No stock found at source";
        } else {
            $stock = mysqli_fetch_assoc($stock_check);
            if ($stock['quantity'] < $quantity) {
                $error = "Insufficient stock at source";
            } else {
                $query = "INSERT INTO transfers (transfer_number, from_warehouse, to_warehouse, from_location, to_location, product_id, quantity, transfer_date, status, created_by) 
                          VALUES ('$transfer_number', $from_warehouse, $to_warehouse, $from_location, $to_location, $product_id, $quantity, '$transfer_date', 'done', $created_by)";
                
                if (mysqli_query($conn, $query)) {
                    // Reduce from source
                    $new_source_qty = $stock['quantity'] - $quantity;
                    $update_source = "UPDATE stock SET quantity = $new_source_qty WHERE id = " . $stock['id'];
                    mysqli_query($conn, $update_source);
                    
                    // Add to destination
                    $dest_check = mysqli_query($conn, "SELECT id, quantity FROM stock WHERE product_id = $product_id AND warehouse_id = $to_warehouse AND location_id = $to_location");
                    
                    if (mysqli_num_rows($dest_check) > 0) {
                        $dest = mysqli_fetch_assoc($dest_check);
                        $new_dest_qty = $dest['quantity'] + $quantity;
                        $update_dest = "UPDATE stock SET quantity = $new_dest_qty WHERE id = " . $dest['id'];
                        mysqli_query($conn, $update_dest);
                    } else {
                        $insert_dest = "INSERT INTO stock (product_id, warehouse_id, location_id, quantity) 
                                       VALUES ($product_id, $to_warehouse, $to_location, $quantity)";
                        mysqli_query($conn, $insert_dest);
                    }
                    
                    // Log movement
                    $log = "INSERT INTO move_history (product_id, from_warehouse, to_warehouse, from_location, to_location, quantity, move_type, reference_number, moved_by) 
                            VALUES ($product_id, $from_warehouse, $to_warehouse, $from_location, $to_location, $quantity, 'transfer', '$transfer_number', $created_by)";
                    mysqli_query($conn, $log);
                    
                    $message = "Transfer completed successfully";
                } else {
                    $error = "Error: " . mysqli_error($conn);
                }
            }
        }
    }
}

// Get products, warehouses, locations
$products = mysqli_query($conn, "SELECT id, name FROM products ORDER BY name");
$warehouses = mysqli_query($conn, "SELECT id, name FROM warehouses WHERE status = 'active' ORDER BY name");
$locations = mysqli_query($conn, "SELECT l.*, w.name as warehouse_name FROM locations l JOIN warehouses w ON l.warehouse_id = w.id");

// Get recent transfers
$transfers = mysqli_query($conn, "
    SELECT t.*, p.name as product_name 
    FROM transfers t
    JOIN products p ON t.product_id = p.id
    WHERE t.created_by = " . $_SESSION['user_id'] . "
    ORDER BY t.created_at DESC
    LIMIT 50
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfers - Staff Panel - <?php echo SITE_NAME; ?></title>
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
            <a href="transfers.php" class="menu-item active">
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
            <h1 class="page-title">Internal Transfer</h1>
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
        
        <!-- Transfer Form -->
        <div class="form-simple" style="margin-bottom: 30px;">
            <h2 style="margin-bottom: 20px;">Create Transfer</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label for="product_id">Product *</label>
                    <select id="product_id" name="product_id" required>
                        <option value="">Select Product</option>
                        <?php while($product = mysqli_fetch_assoc($products)): ?>
                        <option value="<?php echo $product['id']; ?>"><?php echo $product['name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Quantity *</label>
                    <input type="number" id="quantity" name="quantity" min="1" required>
                </div>
                
                <div class="form-group">
                    <label for="from_warehouse">From Warehouse *</label>
                    <select id="from_warehouse" name="from_warehouse" required>
                        <option value="">Select Source</option>
                        <?php while($wh = mysqli_fetch_assoc($warehouses)): ?>
                        <option value="<?php echo $wh['id']; ?>"><?php echo $wh['name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="from_location">From Location *</label>
                    <select id="from_location" name="from_location" required>
                        <option value="">Select Source Location</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="to_warehouse">To Warehouse *</label>
                    <select id="to_warehouse" name="to_warehouse" required>
                        <option value="">Select Destination</option>
                        <?php 
                        mysqli_data_seek($warehouses, 0);
                        while($wh = mysqli_fetch_assoc($warehouses)): 
                        ?>
                        <option value="<?php echo $wh['id']; ?>"><?php echo $wh['name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="to_location">To Location *</label>
                    <select id="to_location" name="to_location" required>
                        <option value="">Select Destination Location</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="transfer_date">Transfer Date *</label>
                    <input type="date" id="transfer_date" name="transfer_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Complete Transfer</button>
            </form>
        </div>
    </div>
    
    <script>
    // Store locations
    var locations = <?php 
        $loc_result = mysqli_query($conn, "SELECT l.*, w.name as warehouse_name FROM locations l JOIN warehouses w ON l.warehouse_id = w.id");
        $locs = [];
        while($loc = mysqli_fetch_assoc($loc_result)) {
            $locs[] = $loc;
        }
        echo json_encode($locs);
    ?>;
    
    document.getElementById('from_warehouse').addEventListener('change', function() {
        var whId = this.value;
        var select = document.getElementById('from_location');
        select.innerHTML = '<option value="">Select Source Location</option>';
        
        locations.forEach(function(loc) {
            if (loc.warehouse_id == whId) {
                var option = document.createElement('option');
                option.value = loc.id;
                option.textContent = loc.name;
                select.appendChild(option);
            }
        });
    });
    
    document.getElementById('to_warehouse').addEventListener('change', function() {
        var whId = this.value;
        var select = document.getElementById('to_location');
        select.innerHTML = '<option value="">Select Destination Location</option>';
        
        locations.forEach(function(loc) {
            if (loc.warehouse_id == whId) {
                var option = document.createElement('option');
                option.value = loc.id;
                option.textContent = loc.name;
                select.appendChild(option);
            }
        });
    });
    </script>
</body>
</html>