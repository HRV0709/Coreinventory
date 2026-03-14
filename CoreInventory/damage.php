<?php
require_once '../includes/config.php';

// Check if user is logged in and is staff
if (!isLoggedIn() || !hasRole('staff')) {
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
        $stock_check = mysqli_query($conn, "SELECT id, quantity FROM stock WHERE product_id = $product_id AND warehouse_id = $warehouse_id AND location_id = $location_id");
        
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
                    $update = "UPDATE stock SET quantity = $new_quantity WHERE id = " . $stock['id'];
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
}

// Get products with stock for dropdown
$products = mysqli_query($conn, "SELECT id, name, sku FROM products ORDER BY name");

// Get active warehouses
$warehouses = mysqli_query($conn, "SELECT id, name FROM warehouses WHERE status = 'active' ORDER BY name");

// Get user's damage reports
$damage_reports = mysqli_query($conn, "
    SELECT d.*, 
           p.name as product_name,
           p.sku,
           w.name as warehouse_name,
           l.name as location_name
    FROM damage_products d
    JOIN products p ON d.product_id = p.id
    LEFT JOIN warehouses w ON d.warehouse_id = w.id
    LEFT JOIN locations l ON d.location_id = l.id
    WHERE d.reported_by = " . $_SESSION['user_id'] . "
    ORDER BY d.created_at DESC
    LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Damage - Staff Panel - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/staff.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .damage-form {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .damage-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }
        .badge-reported { background: #fff3cd; color: #856404; }
        .badge-inspected { background: #d4edda; color: #155724; }
        .badge-replaced { background: #cce5ff; color: #004085; }
        .badge-disposed { background: #f8d7da; color: #721c24; }
    </style>
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
            <a href="damage.php" class="menu-item active">
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
            <h1 class="page-title">Report Damaged Products</h1>
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
        
        <!-- Damage Report Form -->
        <div class="damage-form">
            <h2 style="margin-bottom: 20px; color: #dc3545;">Report New Damage</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="report">
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                    <div class="form-group">
                        <label for="product_id">Product *</label>
                        <select id="product_id" name="product_id" required>
                            <option value="">Select Product</option>
                            <?php while($product = mysqli_fetch_assoc($products)): ?>
                            <option value="<?php echo $product['id']; ?>"><?php echo $product['name']; ?> (<?php echo $product['sku']; ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="warehouse_id">Warehouse *</label>
                        <select id="warehouse_id" name="warehouse_id" required>
                            <option value="">Select Warehouse</option>
                            <?php while($wh = mysqli_fetch_assoc($warehouses)): ?>
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
                        <textarea id="reason" name="reason" rows="3" required placeholder="Describe how the damage occurred..."></textarea>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-danger" style="padding: 10px 25px;">
                        <i class="fas fa-exclamation-triangle"></i> Report Damage
                    </button>
                    <button type="reset" class="btn btn-secondary" style="padding: 10px 25px;">Clear Form</button>
                </div>
            </form>
        </div>
        
        <!-- Recent Damage Reports -->
        <div style="background: white; border-radius: 10px; padding: 20px;">
            <h2 style="margin-bottom: 20px;">Your Recent Reports</h2>
            
            <?php if (mysqli_num_rows($damage_reports) > 0): ?>
                <table class="simple-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($damage = mysqli_fetch_assoc($damage_reports)): ?>
                        <tr>
                            <td><?php echo $damage['damage_date']; ?></td>
                            <td><?php echo $damage['product_name']; ?><br><small><?php echo $damage['sku']; ?></small></td>
                            <td><strong><?php echo $damage['quantity']; ?></strong></td>
                            <td><?php echo $damage['warehouse_name']; ?> - <?php echo $damage['location_name']; ?></td>
                            <td>
                                <span class="damage-badge badge-<?php echo $damage['status']; ?>">
                                    <?php echo ucfirst($damage['status']); ?>
                                </span>
                            </td>
                            <td><?php echo substr($damage['reason'], 0, 50) . (strlen($damage['reason']) > 50 ? '...' : ''); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #6c757d; padding: 30px;">No damage reports yet</p>
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
    
    document.getElementById('warehouse_id').addEventListener('change', function() {
        var whId = this.value;
        var select = document.getElementById('location_id');
        select.innerHTML = '<option value="">Select Location</option>';
        
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