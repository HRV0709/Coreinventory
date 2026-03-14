<?php
require_once '../includes/config.php';

// Check if user is logged in and is manager
if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

// Handle Add Receipt
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $receipt_number = 'RCP-' . date('Ymd') . '-' . rand(1000, 9999);
        $supplier = mysqli_real_escape_string($conn, $_POST['supplier']);
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        $received_date = mysqli_real_escape_string($conn, $_POST['received_date']);
        $received_time = mysqli_real_escape_string($conn, $_POST['received_time']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $created_by = $_SESSION['user_id'];
        
        $query = "INSERT INTO receipts (receipt_number, supplier, product_id, quantity, received_date, received_time, status, created_by) 
                  VALUES ('$receipt_number', '$supplier', $product_id, $quantity, '$received_date', '$received_time', '$status', $created_by)";
        
        if (mysqli_query($conn, $query)) {
            $receipt_id = mysqli_insert_id($conn);
            
            // If status is 'done', update stock
            if ($status == 'done') {
                // Get warehouse and location (simplified - in real system, you'd select these)
                $warehouse_id = 1; // Default warehouse
                $location_id = 1; // Default location
                
                // Check if stock exists
                $check = "SELECT id, quantity FROM stock WHERE product_id = $product_id AND warehouse_id = $warehouse_id AND location_id = $location_id";
                $result = mysqli_query($conn, $check);
                
                if (mysqli_num_rows($result) > 0) {
                    $stock = mysqli_fetch_assoc($result);
                    $new_quantity = $stock['quantity'] + $quantity;
                    $update = "UPDATE stock SET quantity = $new_quantity WHERE id = " . $stock['id'];
                    mysqli_query($conn, $update);
                } else {
                    $insert = "INSERT INTO stock (product_id, warehouse_id, location_id, quantity) 
                               VALUES ($product_id, $warehouse_id, $location_id, $quantity)";
                    mysqli_query($conn, $insert);
                }
                
                // Log movement
                $log = "INSERT INTO move_history (product_id, to_warehouse, to_location, quantity, move_type, reference_number, moved_by) 
                        VALUES ($product_id, $warehouse_id, $location_id, $quantity, 'receipt', '$receipt_number', $created_by)";
                mysqli_query($conn, $log);
            }
            
            $message = "Receipt created successfully. Number: $receipt_number";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
    elseif ($_POST['action'] == 'update_status') {
        $id = intval($_POST['id']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        $query = "UPDATE receipts SET status = '$status' WHERE id = $id";
        
        if (mysqli_query($conn, $query)) {
            // If status changed to done, update stock
            if ($status == 'done') {
                $receipt = mysqli_query($conn, "SELECT * FROM receipts WHERE id = $id");
                $receipt_data = mysqli_fetch_assoc($receipt);
                
                // Update stock logic here
                $message = "Receipt completed and stock updated";
            } else {
                $message = "Receipt status updated";
            }
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Get all receipts
$receipts = mysqli_query($conn, "
    SELECT r.*, p.name as product_name, u.full_name as created_by_name 
    FROM receipts r
    JOIN products p ON r.product_id = p.id
    JOIN users u ON r.created_by = u.id
    ORDER BY r.created_at DESC
");

// Get products for dropdown
$products = mysqli_query($conn, "SELECT id, name, sku FROM products ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipts - Manager Panel - <?php echo SITE_NAME; ?></title>
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
            <a href="receipts.php" class="menu-item active">
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
            <h1 class="page-title">Receipts Management</h1>
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
        
        <!-- Add Receipt Button -->
        <button class="btn btn-primary" onclick="toggleForm()" style="margin-bottom: 20px;">
            <i class="fas fa-plus"></i> Create New Receipt
        </button>
        
        <!-- Add Receipt Form -->
        <div id="addForm" style="display: none; background: white; border-radius: 12px; padding: 20px; margin-bottom: 30px;">
            <h2 style="margin-bottom: 20px;">Create New Receipt</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                    <div class="form-group">
                        <label for="supplier">Supplier *</label>
                        <input type="text" id="supplier" name="supplier" required>
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
                        <label for="received_date">Received Date *</label>
                        <input type="date" id="received_date" name="received_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="received_time">Received Time</label>
                        <input type="time" id="received_time" name="received_time" value="<?php echo date('H:i'); ?>">
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
                    <button type="submit" class="btn btn-success">Create Receipt</button>
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
        
        <!-- Receipts Table -->
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="receiptTable">
                    <?php 
                    mysqli_data_seek($products, 0); // Reset products pointer
                    while($receipt = mysqli_fetch_assoc($receipts)): 
                    ?>
                    <tr data-status="<?php echo $receipt['status']; ?>">
                        <td><strong><?php echo $receipt['receipt_number']; ?></strong></td>
                        <td><?php echo $receipt['received_date']; ?> <?php echo $receipt['received_time']; ?></td>
                        <td><?php echo $receipt['supplier']; ?></td>
                        <td><?php echo $receipt['product_name']; ?></td>
                        <td><?php echo $receipt['quantity']; ?></td>
                        <td>
                            <span class="status-badge" style="background: 
                                <?php 
                                switch($receipt['status']) {
                                    case 'draft': echo '#6c757d'; break;
                                    case 'waiting': echo '#ffc107'; break;
                                    case 'ready': echo '#17a2b8'; break;
                                    case 'done': echo '#28a745'; break;
                                    case 'canceled': echo '#dc3545'; break;
                                }
                                ?>; color: white;">
                                <?php echo ucfirst($receipt['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $receipt['created_by_name']; ?></td>
                       <!-- Replace the Actions column in the receipts table with this: -->
<td>
    <a href="print_receipt.php?id=<?php echo $receipt['id']; ?>" class="btn btn-sm btn-info" style="background: #3498db; color: white; text-decoration: none; padding: 5px 10px; border-radius: 4px; margin-right: 5px;" target="_blank">
        <i class="fas fa-print"></i> Print
    </a>
    <button class="btn btn-sm btn-primary" onclick="viewReceipt(<?php echo $receipt['id']; ?>)">View</button>
    <?php if ($receipt['status'] != 'done' && $receipt['status'] != 'canceled'): ?>
    <form method="POST" style="display: inline;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" value="<?php echo $receipt['id']; ?>">
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
        if (form.style.display === 'none') {
            form.style.display = 'block';
        } else {
            form.style.display = 'none';
        }
    }
    
    function filterTable(status) {
        var rows = document.querySelectorAll('#receiptTable tr');
        var searchText = document.getElementById('searchInput').value.toLowerCase();
        
        rows.forEach(function(row) {
            var rowStatus = row.getAttribute('data-status');
            var rowText = row.textContent.toLowerCase();
            var statusMatch = (status === 'all' || rowStatus === status);
            var searchMatch = (searchText === '' || rowText.includes(searchText));
            
            if (statusMatch && searchMatch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    document.getElementById('searchInput').addEventListener('keyup', function() {
        filterTable(document.querySelector('.filter-select').value);
    });
    
    function viewReceipt(id) {
        alert('View receipt details for ID: ' + id);
        // In real implementation, this would open a modal or redirect
    }
    </script>
</body>
</html>