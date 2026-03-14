<?php
require_once '../includes/config.php';

// Check if user is logged in and is manager
if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_product') {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $sku = mysqli_real_escape_string($conn, $_POST['sku']);
        $category = mysqli_real_escape_string($conn, $_POST['category']);
        $unit = mysqli_real_escape_string($conn, $_POST['unit']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $reorder_level = intval($_POST['reorder_level']);
        
        // Check if SKU already exists
        $check = mysqli_query($conn, "SELECT id FROM products WHERE sku = '$sku'");
        if (mysqli_num_rows($check) > 0) {
            $error = "Product with SKU '$sku' already exists!";
        } else {
            $query = "INSERT INTO products (name, sku, category, unit_of_measure, description, reorder_level) 
                      VALUES ('$name', '$sku', '$category', '$unit', '$description', $reorder_level)";
            
            if (mysqli_query($conn, $query)) {
                $message = "Product added successfully!";
            } else {
                $error = "Error adding product: " . mysqli_error($conn);
            }
        }
    }
    
    // Handle Edit Product
    elseif ($_POST['action'] == 'edit_product') {
        $id = intval($_POST['id']);
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $sku = mysqli_real_escape_string($conn, $_POST['sku']);
        $category = mysqli_real_escape_string($conn, $_POST['category']);
        $unit = mysqli_real_escape_string($conn, $_POST['unit']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $reorder_level = intval($_POST['reorder_level']);
        
        // Check if SKU exists for other products
        $check = mysqli_query($conn, "SELECT id FROM products WHERE sku = '$sku' AND id != $id");
        if (mysqli_num_rows($check) > 0) {
            $error = "Another product with SKU '$sku' already exists!";
        } else {
            $query = "UPDATE products SET 
                      name = '$name',
                      sku = '$sku',
                      category = '$category',
                      unit_of_measure = '$unit',
                      description = '$description',
                      reorder_level = $reorder_level
                      WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                $message = "Product updated successfully!";
            } else {
                $error = "Error updating product: " . mysqli_error($conn);
            }
        }
    }
    
    // Handle Delete Product - WITH STOCK DELETION
    elseif ($_POST['action'] == 'delete_product') {
        $id = intval($_POST['id']);
        $confirm = isset($_POST['confirm_delete']) ? $_POST['confirm_delete'] : '';
        
        // Check if product has stock
        $check_stock = mysqli_query($conn, "SELECT COUNT(*) as count, SUM(quantity) as total FROM stock WHERE product_id = $id");
        $stock_data = mysqli_fetch_assoc($check_stock);
        $has_stock = $stock_data['count'] > 0;
        
        if ($has_stock && $confirm != 'yes') {
            // Show confirmation form
            $error = "This product has existing stock (" . $stock_data['total'] . " units). Please confirm deletion.";
            // We'll handle this with JavaScript confirmation
        } else {
            // Start transaction (if your MySQL engine supports it)
            // For simplicity, we'll just delete in order
            
            // First delete from move_history (if any)
            mysqli_query($conn, "DELETE FROM move_history WHERE product_id = $id");
            
            // Then delete from stock
            mysqli_query($conn, "DELETE FROM stock WHERE product_id = $id");
            
            // Then delete from receipts, deliveries, transfers, damage_products
            mysqli_query($conn, "DELETE FROM receipts WHERE product_id = $id");
            mysqli_query($conn, "DELETE FROM deliveries WHERE product_id = $id");
            mysqli_query($conn, "DELETE FROM transfers WHERE product_id = $id");
            mysqli_query($conn, "DELETE FROM damage_products WHERE product_id = $id");
            
            // Finally delete the product
            $query = "DELETE FROM products WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                $message = "Product and all associated records deleted successfully!";
            } else {
                $error = "Error deleting product: " . mysqli_error($conn);
            }
        }
    }
}

// Get edit product data if requested
$edit_product = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $result = mysqli_query($conn, "SELECT * FROM products WHERE id = $id");
    if (mysqli_num_rows($result) > 0) {
        $edit_product = mysqli_fetch_assoc($result);
    }
}

// Get all products with stock information
$products = mysqli_query($conn, "
    SELECT p.*, 
           COALESCE(SUM(s.quantity), 0) as total_stock,
           COUNT(DISTINCT s.warehouse_id) as warehouse_count
    FROM products p
    LEFT JOIN stock s ON p.id = s.product_id
    GROUP BY p.id
    ORDER BY p.name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Manager Panel - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/manager.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .add-product-form {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 2px dashed #3498db;
        }
        .form-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f2f5;
        }
        .form-title h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 20px;
        }
        .form-title i {
            color: #3498db;
            font-size: 24px;
        }
        .form-row {
            margin-bottom: 15px;
        }
        .form-row label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
            font-size: 14px;
        }
        .form-row input,
        .form-row select,
        .form-row textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .form-row input:focus,
        .form-row select:focus,
        .form-row textarea:focus {
            border-color: #3498db;
            outline: none;
        }
        .form-row textarea {
            resize: vertical;
            min-height: 80px;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-add {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        .btn-cancel {
            background: #95a5a6;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-cancel:hover {
            background: #7f8c8d;
        }
        .product-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            position: relative;
            border: 1px solid #e0e0e0;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .product-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 5px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .product-card:hover .product-actions {
            opacity: 1;
        }
        .action-icon {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            border: none;
            font-size: 14px;
        }
        .action-icon.edit {
            background: #f39c12;
        }
        .action-icon.delete {
            background: #e74c3c;
        }
        .action-icon:hover {
            opacity: 0.9;
        }
        .toggle-form-btn {
            background: #27ae60;
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
        .sku-badge {
            background: #3498db;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .warning-text {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
        }
        .stock-warning {
            background: #fff3cd;
            color: #856404;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            margin-top: 5px;
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
            <a href="products.php" class="menu-item active">
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
            <h1 class="page-title">Product Management</h1>
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
        
        <!-- Toggle Form Button -->
        <button class="toggle-form-btn" onclick="toggleForm()" id="toggleBtn">
            <i class="fas fa-plus-circle"></i> 
            <span id="toggleBtnText">Add New Product</span>
        </button>
        
        <!-- Add/Edit Product Form -->
        <div id="productForm" class="add-product-form" style="display: <?php echo $edit_product ? 'block' : 'none'; ?>;">
            <div class="form-title">
                <h2>
                    <i class="fas <?php echo $edit_product ? 'fa-edit' : 'fa-plus-circle'; ?>"></i>
                    <?php echo $edit_product ? 'Edit Product' : 'Add New Product'; ?>
                </h2>
                <i class="fas fa-box"></i>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?php echo $edit_product ? 'edit_product' : 'add_product'; ?>">
                <?php if ($edit_product): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_product['id']; ?>">
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                    <div class="form-row">
                        <label for="name">Product Name *</label>
                        <input type="text" id="name" name="name" required 
                               value="<?php echo $edit_product ? $edit_product['name'] : ''; ?>"
                               placeholder="Enter product name">
                    </div>
                    
                    <div class="form-row">
                        <label for="sku">SKU/Code *</label>
                        <input type="text" id="sku" name="sku" required 
                               value="<?php echo $edit_product ? $edit_product['sku'] : ''; ?>"
                               placeholder="Enter unique SKU">
                    </div>
                    
                    <div class="form-row">
                        <label for="category">Category</label>
                        <input type="text" id="category" name="category" 
                               value="<?php echo $edit_product ? $edit_product['category'] : ''; ?>"
                               placeholder="e.g., Electronics, Furniture, etc.">
                    </div>
                    
                    <div class="form-row">
                        <label for="unit">Unit of Measure *</label>
                        <select id="unit" name="unit" required>
                            <option value="">Select Unit</option>
                            <option value="pieces" <?php echo ($edit_product && $edit_product['unit_of_measure'] == 'pieces') ? 'selected' : ''; ?>>Pieces</option>
                            <option value="kg" <?php echo ($edit_product && $edit_product['unit_of_measure'] == 'kg') ? 'selected' : ''; ?>>Kilograms (kg)</option>
                            <option value="box" <?php echo ($edit_product && $edit_product['unit_of_measure'] == 'box') ? 'selected' : ''; ?>>Box</option>
                            <option value="liter" <?php echo ($edit_product && $edit_product['unit_of_measure'] == 'liter') ? 'selected' : ''; ?>>Liters</option>
                            <option value="meter" <?php echo ($edit_product && $edit_product['unit_of_measure'] == 'meter') ? 'selected' : ''; ?>>Meters</option>
                            <option value="dozen" <?php echo ($edit_product && $edit_product['unit_of_measure'] == 'dozen') ? 'selected' : ''; ?>>Dozen</option>
                            <option value="pack" <?php echo ($edit_product && $edit_product['unit_of_measure'] == 'pack') ? 'selected' : ''; ?>>Pack</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <label for="reorder_level">Reorder Level</label>
                        <input type="number" id="reorder_level" name="reorder_level" min="0" 
                               value="<?php echo $edit_product ? $edit_product['reorder_level'] : '10'; ?>"
                               placeholder="Minimum stock alert level">
                    </div>
                </div>
                
                <div class="form-row">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" 
                              placeholder="Enter product description..."><?php echo $edit_product ? $edit_product['description'] : ''; ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-add">
                        <i class="fas <?php echo $edit_product ? 'fa-save' : 'fa-plus-circle'; ?>"></i>
                        <?php echo $edit_product ? 'Update Product' : 'Add Product'; ?>
                    </button>
                    
                    <?php if ($edit_product): ?>
                        <a href="products.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn-cancel" onclick="toggleForm()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Products Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; margin-top: 20px;">
            <?php while($product = mysqli_fetch_assoc($products)): ?>
            <div class="product-card">
                <div class="product-actions">
                    <a href="?edit=<?php echo $product['id']; ?>" class="action-icon edit" title="Edit Product">
                        <i class="fas fa-edit"></i>
                    </a>
                    <button onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['total_stock']; ?>)" class="action-icon delete" title="Delete Product">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                    <h3 style="color: #2c3e50; margin: 0; font-size: 18px;"><?php echo $product['name']; ?></h3>
                    <span class="sku-badge"><?php echo $product['sku']; ?></span>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <p style="color: #7f8c8d; margin: 5px 0;">
                        <i class="fas fa-tag"></i> Category: <?php echo $product['category'] ?: 'Uncategorized'; ?>
                    </p>
                    <p style="color: #7f8c8d; margin: 5px 0;">
                        <i class="fas fa-balance-scale"></i> Unit: <?php echo $product['unit_of_measure']; ?>
                    </p>
                    <?php if ($product['description']): ?>
                    <p style="color: #7f8c8d; margin: 5px 0; font-size: 13px;">
                        <i class="fas fa-align-left"></i> <?php echo substr($product['description'], 0, 50) . (strlen($product['description']) > 50 ? '...' : ''); ?>
                    </p>
                    <?php endif; ?>
                    
                    <?php if ($product['total_stock'] > 0): ?>
                    <div class="stock-warning">
                        <i class="fas fa-exclamation-triangle"></i> Has <?php echo $product['total_stock']; ?> units in stock
                    </div>
                    <?php endif; ?>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                    <div style="text-align: center; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                        <div style="font-size: 24px; font-weight: 600; color: #27ae60;"><?php echo $product['total_stock']; ?></div>
                        <div style="font-size: 12px; color: #7f8c8d;">Total Stock</div>
                    </div>
                    <div style="text-align: center; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                        <div style="font-size: 24px; font-weight: 600; color: #3498db;"><?php echo $product['warehouse_count']; ?></div>
                        <div style="font-size: 12px; color: #7f8c8d;">Warehouses</div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-primary btn-sm" onclick="location.href='stock-view.php?product=<?php echo $product['id']; ?>'">
                        <i class="fas fa-eye"></i> View Stock
                    </button>
                    <button class="btn btn-success btn-sm" onclick="location.href='adjustments.php?product=<?php echo $product['id']; ?>'">
                        <i class="fas fa-edit"></i> Adjust
                    </button>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; width: 400px; padding: 30px; border-radius: 12px; text-align: center;">
            <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #e74c3c; margin-bottom: 20px;"></i>
            <h3 style="margin-bottom: 15px; color: #2c3e50;">Confirm Delete</h3>
            <p id="deleteMessage" style="margin-bottom: 20px; color: #7f8c8d;"></p>
            
            <form method="POST" action="" id="deleteForm">
                <input type="hidden" name="action" value="delete_product">
                <input type="hidden" name="id" id="deleteId" value="">
                <input type="hidden" name="confirm_delete" id="confirmDelete" value="yes">
                
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="submit" class="btn btn-danger" style="background: #e74c3c; color: white; padding: 10px 25px; border: none; border-radius: 6px; cursor: pointer;">
                        <i class="fas fa-trash"></i> Yes, Delete
                    </button>
                    <button type="button" onclick="closeDeleteModal()" class="btn btn-secondary" style="background: #95a5a6; color: white; padding: 10px 25px; border: none; border-radius: 6px; cursor: pointer;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    function toggleForm() {
        var form = document.getElementById('productForm');
        var btn = document.getElementById('toggleBtn');
        var btnText = document.getElementById('toggleBtnText');
        
        if (form.style.display === 'none') {
            form.style.display = 'block';
            btnText.innerHTML = 'Hide Form';
            btn.style.background = '#e74c3c';
        } else {
            form.style.display = 'none';
            btnText.innerHTML = 'Add New Product';
            btn.style.background = '#27ae60';
        }
    }
    
    function confirmDelete(productId, productName, stockCount) {
        var modal = document.getElementById('deleteModal');
        var message = document.getElementById('deleteMessage');
        var deleteId = document.getElementById('deleteId');
        
        if (stockCount > 0) {
            message.innerHTML = `Product "${productName}" has <strong>${stockCount} units</strong> in stock.<br><br>Deleting this product will also delete all associated stock records and history. Are you sure?`;
        } else {
            message.innerHTML = `Are you sure you want to delete product "${productName}"?`;
        }
        
        deleteId.value = productId;
        modal.style.display = 'flex';
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
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
    
    // Close modal if clicked outside
    window.onclick = function(event) {
        var modal = document.getElementById('deleteModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
    </script>
</body>
</html>