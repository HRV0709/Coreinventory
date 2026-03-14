<?php
require_once '../includes/config.php';

// Check if user is logged in and is manager
if (!isLoggedIn() || !hasRole('manager')) {
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
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $created_by = $_SESSION['user_id'];
        
        // Check if source has enough stock
        $stock_check = mysqli_query($conn, "SELECT quantity FROM stock WHERE product_id = $product_id AND warehouse_id = $from_warehouse AND location_id = $from_location");
        
        if (mysqli_num_rows($stock_check) == 0) {
            $error = "No stock found at source location";
        } else {
            $stock = mysqli_fetch_assoc($stock_check);
            if ($stock['quantity'] < $quantity) {
                $error = "Insufficient stock at source. Available: " . $stock['quantity'];
            } else {
                $query = "INSERT INTO transfers (transfer_number, from_warehouse, to_warehouse, from_location, to_location, product_id, quantity, transfer_date, status, created_by) 
                          VALUES ('$transfer_number', $from_warehouse, $to_warehouse, $from_location, $to_location, $product_id, $quantity, '$transfer_date', '$status', $created_by)";
                
                if (mysqli_query($conn, $query)) {
                    $transfer_id = mysqli_insert_id($conn);
                    
                    // If status is 'done', update stock
                    if ($status == 'done') {
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
                    }
                    
                    $message = "Transfer created successfully. Number: $transfer_number";
                } else {
                    $error = "Error: " . mysqli_error($conn);
                }
            }
        }
    }
    elseif ($_POST['action'] == 'update_status') {
        $id = intval($_POST['id']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        $query = "UPDATE transfers SET status = '$status' WHERE id = $id";
        
        if (mysqli_query($conn, $query)) {
            $message = "Transfer status updated";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Get all transfers
$transfers = mysqli_query($conn, "
    SELECT t.*, 
           p.name as product_name,
           u.full_name as created_by_name,
           w1.name as from_warehouse_name,
           w2.name as to_warehouse_name,
           l1.name as from_location_name,
           l2.name as to_location_name
    FROM transfers t
    JOIN products p ON t.product_id = p.id
    JOIN users u ON t.created_by = u.id
    JOIN warehouses w1 ON t.from_warehouse = w1.id
    JOIN warehouses w2 ON t.to_warehouse = w2.id
    JOIN locations l1 ON t.from_location = l1.id
    JOIN locations l2 ON t.to_location = l2.id
    ORDER BY t.created_at DESC
");

// Get products, warehouses, locations for dropdowns
$products = mysqli_query($conn, "SELECT id, name FROM products ORDER BY name");
$warehouses = mysqli_query($conn, "SELECT id, name FROM warehouses WHERE status = 'active' ORDER BY name");
$locations = mysqli_query($conn, "SELECT l.*, w.name as warehouse_name FROM locations l JOIN warehouses w ON l.warehouse_id = w.id ORDER BY w.name, l.name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal Transfers - Manager Panel - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/manager.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar (similar to previous) -->
    <div class="sidebar">
        <!-- ... sidebar structure ... -->
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
            <a href="transfers.php" class="menu-item active">
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
            <h1 class="page-title">Internal Transfers</h1>
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
        
        <!-- Add Transfer Button -->
        <button class="btn btn-primary" onclick="toggleForm()" style="margin-bottom: 20px;">
            <i class="fas fa-plus"></i> Create New Transfer
        </button>
        
        <!-- Add Transfer Form -->
        <div id="addForm" style="display: none; background: white; border-radius: 12px; padding: 20px; margin-bottom: 30px;">
            <h2 style="margin-bottom: 20px;">Create New Transfer</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
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
                        <select id="from_warehouse" name="from_warehouse" required onchange="updateFromLocations()">
                            <option value="">Select Source Warehouse</option>
                            <?php 
                            mysqli_data_seek($warehouses, 0);
                            while($wh = mysqli_fetch_assoc($warehouses)): 
                            ?>
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
                        <select id="to_warehouse" name="to_warehouse" required onchange="updateToLocations()">
                            <option value="">Select Destination Warehouse</option>
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
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="draft">Draft</option>
                            <option value="waiting">Waiting</option>
                            <option value="ready">Ready</option>
                            <option value="done">Done</option>
                        </select>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-success">Create Transfer</button>
                    <button type="button" class="btn btn-secondary" onclick="toggleForm()">Cancel</button>
                </div>
            </form>
        </div>
        
        <!-- Transfers Table -->
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Transfer #</th>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($transfer = mysqli_fetch_assoc($transfers)): ?>
                    <tr>
                        <td><strong><?php echo $transfer['transfer_number']; ?></strong></td>
                        <td><?php echo $transfer['transfer_date']; ?></td>
                        <td><?php echo $transfer['product_name']; ?></td>
                        <td><?php echo $transfer['quantity']; ?></td>
                        <td><?php echo $transfer['from_warehouse_name']; ?> - <?php echo $transfer['from_location_name']; ?></td>
                        <td><?php echo $transfer['to_warehouse_name']; ?> - <?php echo $transfer['to_location_name']; ?></td>
                        <td>
                            <span class="status-badge" style="background: 
                                <?php 
                                switch($transfer['status']) {
                                    case 'draft': echo '#6c757d'; break;
                                    case 'waiting': echo '#ffc107'; break;
                                    case 'ready': echo '#17a2b8'; break;
                                    case 'done': echo '#28a745'; break;
                                    case 'canceled': echo '#dc3545'; break;
                                }
                                ?>; color: white;">
                                <?php echo ucfirst($transfer['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $transfer['created_by_name']; ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="viewTransfer(<?php echo $transfer['id']; ?>)">View</button>
                            <?php if ($transfer['status'] != 'done' && $transfer['status'] != 'canceled'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="id" value="<?php echo $transfer['id']; ?>">
                                <select name="status" onchange="this.form.submit()" style="padding: 5px; border-radius: 4px;">
                                    <option value="">Change</option>
                                    <option value="draft">Draft</option>
                                    <option value="waiting">Waiting</option>
                                    <option value="ready">Ready</option>
                                    <option value="done">Done</option>
                                    <option value="canceled">Cancel</option>
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
    // Store locations data for JavaScript
    var locations = <?php 
        $locations_array = [];
        mysqli_data_seek($locations, 0);
        while($loc = mysqli_fetch_assoc($locations)) {
            $locations_array[] = $loc;
        }
        echo json_encode($locations_array);
    ?>;
    
    function toggleForm() {
        var form = document.getElementById('addForm');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
    
    function updateFromLocations() {
        var warehouseId = document.getElementById('from_warehouse').value;
        var select = document.getElementById('from_location');
        select.innerHTML = '<option value="">Select Source Location</option>';
        
        locations.forEach(function(loc) {
            if (loc.warehouse_id == warehouseId) {
                var option = document.createElement('option');
                option.value = loc.id;
                option.textContent = loc.name;
                select.appendChild(option);
            }
        });
    }
    
    function updateToLocations() {
        var warehouseId = document.getElementById('to_warehouse').value;
        var select = document.getElementById('to_location');
        select.innerHTML = '<option value="">Select Destination Location</option>';
        
        locations.forEach(function(loc) {
            if (loc.warehouse_id == warehouseId) {
                var option = document.createElement('option');
                option.value = loc.id;
                option.textContent = loc.name;
                select.appendChild(option);
            }
        });
    }
    
    function viewTransfer(id) {
        alert('View transfer details for ID: ' + id);
    }
    </script>
</body>
</html>