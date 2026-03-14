<?php
require_once '../includes/config.php';

// Check if user is logged in and is manager
if (!isLoggedIn() || !hasRole('manager')) {
    redirect('../auth/login.php');
}

// Get receipt ID from URL
$receipt_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($receipt_id == 0) {
    die('Invalid receipt ID');
}

// Fetch receipt details with all related information
$query = "
    SELECT r.*, 
           p.name as product_name, 
           p.sku,
           p.category,
           p.unit_of_measure,
           u.full_name as created_by_name,
           u.email as created_by_email,
           u.username as created_by_username
    FROM receipts r
    JOIN products p ON r.product_id = p.id
    JOIN users u ON r.created_by = u.id
    WHERE r.id = $receipt_id
";

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    die('Receipt not found');
}

$receipt = mysqli_fetch_assoc($result);

// Get warehouse info (you may need to adjust this based on your actual warehouse data)
$warehouse_query = "SELECT name FROM warehouses WHERE id = 1 LIMIT 1";
$warehouse_result = mysqli_query($conn, $warehouse_query);
$warehouse = mysqli_fetch_assoc($warehouse_result);
$warehouse_name = $warehouse ? $warehouse['name'] : 'Main Warehouse';

// Format dates
$received_date_formatted = date('F j, Y', strtotime($receipt['received_date']));
$created_date_formatted = date('F j, Y H:i:s', strtotime($receipt['created_at']));
$received_time_formatted = date('g:i A', strtotime($receipt['received_time']));

// Calculate total (if you have price, otherwise just show quantity)
$total_quantity = $receipt['quantity'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo $receipt['receipt_number']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            padding: 30px;
        }
        
        .print-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .receipt-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .receipt-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }
        
        .receipt-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .receipt-number {
            background: rgba(255,255,255,0.2);
            display: inline-block;
            padding: 8px 20px;
            border-radius: 30px;
            margin-top: 15px;
            font-size: 18px;
            font-weight: 600;
        }
        
        .receipt-body {
            padding: 40px;
        }
        
        .company-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px dashed #e0e0e0;
        }
        
        .company-details h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 20px;
        }
        
        .company-details p {
            color: #7f8c8d;
            margin: 5px 0;
            font-size: 14px;
        }
        
        .receipt-details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        .detail-item {
            border-right: 1px solid #dee2e6;
            padding-right: 20px;
        }
        
        .detail-item:last-child {
            border-right: none;
        }
        
        .detail-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        
        .detail-value {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .detail-value.small {
            font-size: 14px;
            font-weight: normal;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-draft { background: #6c757d; color: white; }
        .status-waiting { background: #ffc107; color: #333; }
        .status-ready { background: #17a2b8; color: white; }
        .status-done { background: #28a745; color: white; }
        .status-canceled { background: #dc3545; color: white; }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .items-table th {
            background: #3498db;
            color: white;
            padding: 15px;
            text-align: left;
            font-size: 14px;
            font-weight: 500;
        }
        
        .items-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            color: #2c3e50;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        .items-table tr:hover {
            background: #f8f9fa;
        }
        
        .summary-section {
            display: flex;
            justify-content: flex-end;
            margin: 30px 0;
        }
        
        .summary-box {
            width: 300px;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #e9ecef;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .summary-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .summary-label {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .summary-value {
            font-weight: 600;
            color: #2c3e50;
            font-size: 16px;
        }
        
        .summary-value.total {
            font-size: 20px;
            color: #27ae60;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px dashed #e0e0e0;
            text-align: center;
            color: #95a5a6;
            font-size: 12px;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin: 40px 0 20px;
        }
        
        .signature-box {
            text-align: center;
            width: 200px;
        }
        
        .signature-line {
            border-top: 2px solid #2c3e50;
            margin-top: 50px;
            padding-top: 10px;
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .no-print {
            text-align: right;
            margin-bottom: 20px;
        }
        
        .print-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            margin-right: 10px;
            transition: all 0.3s ease;
        }
        
        .print-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .back-btn {
            background: #95a5a6;
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }
        
        @media print {
            .no-print {
                display: none;
            }
            body {
                background: white;
                padding: 0;
            }
            .print-container {
                box-shadow: none;
                border-radius: 0;
            }
        }
        
        .watermark {
            position: fixed;
            bottom: 20px;
            right: 20px;
            opacity: 0.1;
            font-size: 50px;
            color: #3498db;
            z-index: -1;
            transform: rotate(-15deg);
        }
        
        .barcode {
            text-align: center;
            margin: 20px 0;
            font-family: 'Libre Barcode 39', cursive;
            font-size: 32px;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39&display=swap" rel="stylesheet">
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="print-btn">
            <i class="fas fa-print"></i> Print / Save as PDF
        </button>
        <a href="receipts.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Receipts
        </a>
    </div>
    
    <div class="print-container">
        <div class="watermark"><?php echo SITE_NAME; ?></div>
        
        <div class="receipt-header">
            <h1><?php echo SITE_NAME; ?></h1>
            <p>Inventory Management System</p>
            <div class="receipt-number">RECEIPT #: <?php echo $receipt['receipt_number']; ?></div>
        </div>
        
        <div class="receipt-body">
            <div class="company-info">
                <div class="company-details">
                    <h3>From:</h3>
                    <p><strong><?php echo SITE_NAME; ?></strong></p>
                    <p>123 Business Street</p>
                    <p>City, State 12345</p>
                    <p>Phone: +1 234 567 890</p>
                    <p>Email: info@coreinventory.com</p>
                </div>
                <div class="company-details" style="text-align: right;">
                    <h3>Supplier:</h3>
                    <p><strong><?php echo $receipt['supplier']; ?></strong></p>
                    <p>Received Date: <?php echo $received_date_formatted; ?></p>
                    <p>Received Time: <?php echo $received_time_formatted; ?></p>
                    <p>Warehouse: <?php echo $warehouse_name; ?></p>
                </div>
            </div>
            
            <div class="receipt-details">
                <div class="details-grid">
                    <div class="detail-item">
                        <div class="detail-label">Receipt Status</div>
                        <div class="detail-value">
                            <span class="status-badge status-<?php echo $receipt['status']; ?>">
                                <?php echo strtoupper($receipt['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Created By</div>
                        <div class="detail-value"><?php echo $receipt['created_by_name']; ?></div>
                        <div class="detail-value small"><?php echo $receipt['created_by_email']; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Created Date</div>
                        <div class="detail-value"><?php echo $created_date_formatted; ?></div>
                    </div>
                </div>
            </div>
            
            <h3 style="color: #2c3e50; margin-bottom: 15px;">Items Received</h3>
            
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><?php echo $receipt['product_name']; ?></strong></td>
                        <td><?php echo $receipt['sku']; ?></td>
                        <td><?php echo $receipt['category'] ?: 'N/A'; ?></td>
                        <td><?php echo number_format($receipt['quantity']); ?></td>
                        <td><?php echo $receipt['unit_of_measure']; ?></td>
                    </tr>
                </tbody>
            </table>
            
            <div class="summary-section">
                <div class="summary-box">
                    <div class="summary-row">
                        <span class="summary-label">Subtotal Quantity:</span>
                        <span class="summary-value"><?php echo number_format($receipt['quantity']); ?> <?php echo $receipt['unit_of_measure']; ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Total Items:</span>
                        <span class="summary-value">1</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Total Quantity:</span>
                        <span class="summary-value total"><?php echo number_format($receipt['quantity']); ?> <?php echo $receipt['unit_of_measure']; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="barcode">
                *<?php echo $receipt['receipt_number']; ?>*
            </div>
            
            <div class="signature-section">
                <div class="signature-box">
                    <div class="signature-line">Received By</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line">Authorized Signature</div>
                </div>
            </div>
            
            <div class="footer">
                <p>This is a computer generated receipt and does not require a physical signature.</p>
                <p>Receipt #: <?php echo $receipt['receipt_number']; ?> | Generated on: <?php echo date('F j, Y H:i:s'); ?></p>
                <p>Thank you for your business!</p>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script>
        // Auto-trigger print dialog when page loads (optional)
        // window.onload = function() {
        //     window.print();
        // }
    </script>
</body>
</html>