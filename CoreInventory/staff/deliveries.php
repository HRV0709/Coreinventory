<?php
require_once '../includes/config.php';

// Check if user is logged in and is staff
if (!isLoggedIn() || !hasRole('staff')) {
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
        $created_by = $_SESSION['user_id'];
        
        // Check stock
        $stock_check = mysqli_query($conn, "SELECT SUM(quantity) as total FROM stock WHERE product_id = $product_id");
        $stock = mysqli_fetch_assoc($stock_check);
        
        if ($stock['total'] < $quantity) {
            $error = "Insufficient stock. Available: " . $stock['total'];
        } else {
            $query = "INSERT INTO deliveries (delivery_number, customer, product_id, quantity, delivery_date, delivery_time, status, created_by) 
                      VALUES ('$delivery_number', '$customer', $product_id, $quantity, '$delivery_date', '$delivery_time', 'done', $created_by)";
            
            if (mysqli_query($conn, $query)) {
                // Reduce stock (simplified - take from first available)
                $stock_item = mysqli_query($conn, "SELECT * FROM stock WHERE product_id = $product_id AND quantity >= $quantity LIMIT 1");
                $item = mysqli_fetch_assoc($stock_item);
                
                $new_quantity = $item['quantity'] - $quantity;
                $update = "UPDATE stock SET quantity = $new_quantity WHERE id = " . $item['id'];
                mysqli_query($conn, $update);
                
                // Log movement
                $log = "INSERT INTO move_history (product_id, from_warehouse, from_location, quantity, move_type, reference_number, moved_by) 
                        VALUES ($product_id, {$item['warehouse_id']}, {$item['location_id']}, $quantity, 'delivery', '$delivery_number', $created_by)";
                mysqli_query($conn, $log);
                
                $message = "Delivery created successfully. Stock updated.";
            } else {
                $error = "Error: " . mysqli_error($conn);
            }
        }
    }
}

// Get recent deliveries
$deliveries = mysqli_query($conn, "
    SELECT d.*, p.name as product_name 
    FROM deliveries d
    JOIN products p ON d.product_id = p.id
    WHERE d.created_by = " . $_SESSION['user_id'] . "
    ORDER BY d.created_at DESC
    LIMIT 50
");

// Get products
$products = mysqli_query($conn, "SELECT id, name, sku FROM products ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deliveries - Staff Panel - <?php echo SITE_NAME; ?></title>
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
            <a href="deliveries.php" class="menu-item active">
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
            <h1 class="page-title">Create Delivery</h1>
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
        
        <!-- Delivery Form -->
        <div class="form-simple" style="margin-bottom: 30px;">
            <h2 style="margin-bottom: 20px;">New Delivery Order</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
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
                
                <button type="submit" class="btn btn-primary">Create Delivery</button>
            </form>
        </div>
        
        <!-- Recent Deliveries -->
        <div class="table-responsive">
            <h2 style="margin-bottom: 20px;">Your Recent Deliveries</h2>
            <table class="simple-table">
                <thead>
                    <tr>
                        <th>Delivery #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($delivery = mysqli_fetch_assoc($deliveries)): ?>
                    <tr>
                        <td><?php echo $delivery['delivery_number']; ?></td>
                        <td><?php echo $delivery['delivery_date']; ?></td>
                        <td><?php echo $delivery['customer']; ?></td>
                        <td><?php echo $delivery['product_name']; ?></td>
                        <td><?php echo $delivery['quantity']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>