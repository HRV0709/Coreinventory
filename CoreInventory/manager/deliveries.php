<?php
require_once '../includes/config.php';

// Check if user is logged in and is manager
if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

// Handle Add Delivery
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $delivery_number = 'DEL-' . date('Ymd') . '-' . rand(1000, 9999);
        $customer = mysqli_real_escape_string($conn, $_POST['customer']);
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        $delivery_date = mysqli_real_escape_string($conn, $_POST['delivery_date']);
        $delivery_time = mysqli_real_escape_string($conn, $_POST['delivery_time']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $created_by = $_SESSION['user_id'];
        
        // Check stock availability
        $stock_check = mysqli_query($conn, "SELECT SUM(quantity) as total FROM stock WHERE product_id = $product_id");
        $stock = mysqli_fetch_assoc($stock_check);
        
        if ($stock['total'] < $quantity) {
            $error = "Insufficient stock. Available: " . $stock['total'];
        } else {
            $query = "INSERT INTO deliveries (delivery_number, customer, product_id, quantity, delivery_date, delivery_time, status, created_by) 
                      VALUES ('$delivery_number', '$customer', $product_id, $quantity, '$delivery_date', '$delivery_time', '$status', $created_by)";
            
            if (mysqli_query($conn, $query)) {
                $delivery_id = mysqli_insert_id($conn);
                
                // If status is 'done', update stock (reduce)
                if ($status == 'done') {
                    // Get warehouse and location with stock (simplified - would need selection)
                    $stock_item = mysqli_query($conn, "SELECT * FROM stock WHERE product_id = $product_id AND quantity >= $quantity LIMIT 1");
                    
                    if (mysqli_num_rows($stock_item) > 0) {
                        $item = mysqli_fetch_assoc($stock_item);
                        $new_quantity = $item['quantity'] - $quantity;
                        
                        $update = "UPDATE stock SET quantity = $new_quantity WHERE id = " . $item['id'];
                        mysqli_query($conn, $update);
                        
                        // Log movement
                        $log = "INSERT INTO move_history (product_id, from_warehouse, from_location, quantity, move_type, reference_number, moved_by) 
                                VALUES ($product_id, {$item['warehouse_id']}, {$item['location_id']}, $quantity, 'delivery', '$delivery_number', $created_by)";
                        mysqli_query($conn, $log);
                    }
                }
                
                $message = "Delivery order created successfully. Number: $delivery_number";
            } else {
                $error = "Error: " . mysqli_error($conn);
            }
        }
    }
    elseif ($_POST['action'] == 'update_status') {
        $id = intval($_POST['id']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        $query = "UPDATE deliveries SET status = '$status' WHERE id = $id";
        
        if (mysqli_query($conn, $query)) {
            $message = "Delivery status updated";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Get all deliveries
$deliveries = mysqli_query($conn, "
    SELECT d.*, p.name as product_name, u.full_name as created_by_name 
    FROM deliveries d
    JOIN products p ON d.product_id = p.id
    JOIN users u ON d.created_by = u.id
    ORDER BY d.created_at DESC
");

// Get products for dropdown
$products = mysqli_query($conn, "SELECT id, name, sku FROM products ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Orders - Manager Panel - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/manager.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar (same as receipts.php) -->
    <div class="sidebar">
        <!-- ... same sidebar structure as receipts.php ... -->
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
            <a href="deliveries.php" class="menu-item active">
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
            <h1 class="page-title">Delivery Orders</h1>
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
        
        <!-- Add Delivery Button -->
        <button class="btn btn-primary" onclick="toggleForm()" style="margin-bottom: 20px;">
            <i class="fas fa-plus"></i> Create New Delivery
        </button>
        
        <!-- Add Delivery Form -->
        <div id="addForm" style="display: none; background: white; border-radius: 12px; padding: 20px; margin-bottom: 30px;">
            <h2 style="margin-bottom: 20px;">Create New Delivery Order</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                    <div class="form-group">
                        <label for="customer">Customer *</label>
                        <input type="text" id="customer" name="customer" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="product_id">Product *</label>
                        <select id="product_id" name="product_id" required>
                            <option value="">Select Product</option>
                            <?php while($product = mysqli_fetch_assoc($products)): ?>
                            <option value="<?php echo $product['id']; ?>">
                                <?php echo $product['name']; ?> (<?php echo $product['sku']; ?>)
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">Quantity *</label>
                        <input type="number" id="quantity" name="quantity" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="delivery_date">Delivery Date *</label>
                        <input type="date" id="delivery_date" name="delivery_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="delivery_time">Delivery Time</label>
                        <input type="time" id="delivery_time" name="delivery_time" value="<?php echo date('H:i'); ?>">
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
                    <button type="submit" class="btn btn-success">Create Delivery</button>
                    <button type="button" class="btn btn-secondary" onclick="toggleForm()">Cancel</button>
                </div>
            </form>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <select class="filter-select" onchange="filterTable(this.value)">
                <option value="all">All Status</option>
                <option value="draft">Draft</option>
                <option value="waiting">Waiting</option>
                <option value="ready">Ready</option>
                <option value="done">Done</option>
                <option value="canceled">Canceled</option>
            </select>
            
            <input type="text" class="filter-select" placeholder="Search..." style="flex: 1;" id="searchInput">
        </div>
        
        <!-- Deliveries Table -->
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Delivery #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="deliveryTable">
                    <?php while($delivery = mysqli_fetch_assoc($deliveries)): ?>
                    <tr data-status="<?php echo $delivery['status']; ?>">
                        <td><strong><?php echo $delivery['delivery_number']; ?></strong></td>
                        <td><?php echo $delivery['delivery_date']; ?> <?php echo $delivery['delivery_time']; ?></td>
                        <td><?php echo $delivery['customer']; ?></td>
                        <td><?php echo $delivery['product_name']; ?></td>
                        <td><?php echo $delivery['quantity']; ?></td>
                        <td>
                            <span class="status-badge" style="background: 
                                <?php 
                                switch($delivery['status']) {
                                    case 'draft': echo '#6c757d'; break;
                                    case 'waiting': echo '#ffc107'; break;
                                    case 'ready': echo '#17a2b8'; break;
                                    case 'done': echo '#28a745'; break;
                                    case 'canceled': echo '#dc3545'; break;
                                }
                                ?>; color: white;">
                                <?php echo ucfirst($delivery['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $delivery['created_by_name']; ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="viewDelivery(<?php echo $delivery['id']; ?>)">View</button>
                            <?php if ($delivery['status'] != 'done' && $delivery['status'] != 'canceled'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="id" value="<?php echo $delivery['id']; ?>">
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
    function toggleForm() {
        var form = document.getElementById('addForm');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
    
    function filterTable(status) {
        var rows = document.querySelectorAll('#deliveryTable tr');
        var searchText = document.getElementById('searchInput').value.toLowerCase();
        
        rows.forEach(function(row) {
            var rowStatus = row.getAttribute('data-status');
            var rowText = row.textContent.toLowerCase();
            var statusMatch = (status === 'all' || rowStatus === status);
            var searchMatch = (searchText === '' || rowText.includes(searchText));
            
            row.style.display = (statusMatch && searchMatch) ? '' : 'none';
        });
    }
    
    document.getElementById('searchInput').addEventListener('keyup', function() {
        filterTable(document.querySelector('.filter-select').value);
    });
    
    function viewDelivery(id) {
        alert('View delivery details for ID: ' + id);
    }
    </script>
</body>
</html>