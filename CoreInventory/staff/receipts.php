<?php
require_once '../includes/config.php';

// Check if user is logged in and is staff
if (!isLoggedIn() || !hasRole('staff')) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

// Handle Add Receipt (simplified for staff)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $receipt_number = 'RCP-' . date('Ymd') . '-' . rand(1000, 9999);
        $supplier = mysqli_real_escape_string($conn, $_POST['supplier']);
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        $received_date = mysqli_real_escape_string($conn, $_POST['received_date']);
        $received_time = mysqli_real_escape_string($conn, $_POST['received_time']);
        $created_by = $_SESSION['user_id'];
        
        $query = "INSERT INTO receipts (receipt_number, supplier, product_id, quantity, received_date, received_time, status, created_by) 
                  VALUES ('$receipt_number', '$supplier', $product_id, $quantity, '$received_date', '$received_time', 'done', $created_by)";
        
        if (mysqli_query($conn, $query)) {
            // Update stock
            $warehouse_id = 1; // Default
            $location_id = 1; // Default
            
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
            
            $message = "Receipt created successfully. Stock updated.";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Get recent receipts
$receipts = mysqli_query($conn, "
    SELECT r.*, p.name as product_name 
    FROM receipts r
    JOIN products p ON r.product_id = p.id
    WHERE r.created_by = " . $_SESSION['user_id'] . "
    ORDER BY r.created_at DESC
    LIMIT 50
");

// Get products for dropdown
$products = mysqli_query($conn, "SELECT id, name, sku FROM products ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipts - Staff Panel - <?php echo SITE_NAME; ?></title>
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
            <a href="receipts.php" class="menu-item active">
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
            <h1 class="page-title">Create Receipt</h1>
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
        
        <!-- Receipt Form -->
        <div class="form-simple" style="margin-bottom: 30px;">
            <h2 style="margin-bottom: 20px;">New Receipt</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
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
                
                <button type="submit" class="btn btn-primary">Create Receipt</button>
            </form>
        </div>
        
        <!-- Recent Receipts -->
        <div class="table-responsive">
            <h2 style="margin-bottom: 20px;">Your Recent Receipts</h2>
            <table class="simple-table">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Product</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($receipt = mysqli_fetch_assoc($receipts)): ?>
                    <tr>
                        <td><?php echo $receipt['receipt_number']; ?></td>
                        <td><?php echo $receipt['received_date']; ?></td>
                        <td><?php echo $receipt['supplier']; ?></td>
                        <td><?php echo $receipt['product_name']; ?></td>
                        <td><?php echo $receipt['quantity']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>