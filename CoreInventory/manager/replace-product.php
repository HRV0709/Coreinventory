<?php
require_once '../includes/config.php';

// Check if user is logged in and is manager
if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

// Handle Replace Product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'replace') {
        $damage_id = intval($_POST['damage_id']);
        $replacement_product_id = intval($_POST['replacement_product_id']);
        $quantity = intval($_POST['quantity']);
        $replacement_date = mysqli_real_escape_string($conn, $_POST['replacement_date']);
        $notes = mysqli_real_escape_string($conn, $_POST['notes']);
        $replaced_by = $_SESSION['user_id'];
        
        // Check if replacement product has enough stock
        $stock_check = mysqli_query($conn, "SELECT SUM(quantity) as total FROM stock WHERE product_id = $replacement_product_id");
        $stock = mysqli_fetch_assoc($stock_check);
        
        if ($stock['total'] < $quantity) {
            $error = "Insufficient stock for replacement. Available: " . $stock['total'];
        } else {
            // Update damage record
            $update = "UPDATE damage_products SET status = 'replaced' WHERE id = $damage_id";
            mysqli_query($conn, $update);
            
            // Create replacement record (simplified - in real system would have replacement table)
            $replacement_number = 'REP-' . date('Ymd') . '-' . rand(1000, 9999);
            
            // Log replacement movement
            $log = "INSERT INTO move_history (product_id, quantity, move_type, reference_number, moved_by) 
                    VALUES ($replacement_product_id, $quantity, 'replacement', '$replacement_number', $replaced_by)";
            mysqli_query($conn, $log);
            
            $message = "Product replacement processed successfully";
        }
    }
}

// Get reported damages that need replacement
$damages = mysqli_query($conn, "
    SELECT d.*, p.name as product_name, p.sku
    FROM damage_products d
    JOIN products p ON d.product_id = p.id
    WHERE d.status IN ('reported', 'inspected')
    ORDER BY d.created_at DESC
");

// Get products for replacement
$products = mysqli_query($conn, "SELECT id, name, sku FROM products ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Replace Products - Manager Panel - <?php echo SITE_NAME; ?></title>
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
            <a href="damage.php" class="menu-item">
                <i class="fas fa-exclamation-triangle"></i> Damaged Products
            </a>
            <a href="damage-reports.php" class="menu-item">
                <i class="fas fa-file-alt"></i> Damage Reports
            </a>
            <a href="replace-product.php" class="menu-item active">
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
            <h1 class="page-title">Replace Damaged Products</h1>
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
        
        <!-- Damaged Products List -->
        <div class="table-responsive">
            <h2 style="margin-bottom: 20px;">Pending Replacements</h2>
            
            <?php if (mysqli_num_rows($damages) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Damage ID</th>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($damage = mysqli_fetch_assoc($damages)): ?>
                        <tr>
                            <td>#<?php echo $damage['id']; ?></td>
                            <td><?php echo $damage['damage_date']; ?></td>
                            <td><?php echo $damage['product_name']; ?><br><small><?php echo $damage['sku']; ?></small></td>
                            <td><?php echo $damage['quantity']; ?></td>
                            <td>
                                <span class="damage-badge badge-<?php echo $damage['status']; ?>">
                                    <?php echo ucfirst($damage['status']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-success" onclick="showReplaceForm(<?php echo $damage['id']; ?>, <?php echo $damage['quantity']; ?>)">
                                    Process Replacement
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">No pending replacements at this time.</div>
            <?php endif; ?>
        </div>
        
        <!-- Replacement Form Modal (hidden by default) -->
        <div id="replaceModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
            <div style="background: white; width: 500px; margin: 100px auto; padding: 30px; border-radius: 12px;">
                <h2 style="margin-bottom: 20px;">Process Replacement</h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="replace">
                    <input type="hidden" name="damage_id" id="modal_damage_id">
                    
                    <div class="form-group">
                        <label for="modal_product">Damaged Product</label>
                        <input type="text" id="modal_product" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_quantity">Damaged Quantity</label>
                        <input type="text" id="modal_quantity" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="replacement_product_id">Replacement Product *</label>
                        <select id="replacement_product_id" name="replacement_product_id" required class="form-control">
                            <option value="">Select Replacement Product</option>
                            <?php while($product = mysqli_fetch_assoc($products)): ?>
                            <option value="<?php echo $product['id']; ?>"><?php echo $product['name']; ?> (<?php echo $product['sku']; ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">Replacement Quantity *</label>
                        <input type="number" id="quantity" name="quantity" min="1" required class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="replacement_date">Replacement Date *</label>
                        <input type="date" id="replacement_date" name="replacement_date" value="<?php echo date('Y-m-d'); ?>" required class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="3" class="form-control"></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-success">Process Replacement</button>
                        <button type="button" class="btn btn-secondary" onclick="closeReplaceForm()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function showReplaceForm(damageId, quantity) {
        // In real implementation, would load product details
        document.getElementById('modal_damage_id').value = damageId;
        document.getElementById('modal_quantity').value = quantity;
        document.getElementById('modal_product').value = 'Product #' + damageId;
        document.getElementById('replaceModal').style.display = 'block';
    }
    
    function closeReplaceForm() {
        document.getElementById('replaceModal').style.display = 'none';
    }
    </script>
</body>
</html>