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

// Fetch receipt details
$query = "
    SELECT r.*, 
           p.name as product_name, 
           p.sku,
           p.unit_of_measure,
           u.full_name as created_by_name,
           u.email as created_by_email
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

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="receipt_' . $receipt['receipt_number'] . '.pdf"');

// Create PDF content
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt ' . $receipt['receipt_number'] . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #3498db;
        }
        .header h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 28px;
        }
        .header h2 {
            color: #7f8c8d;
            margin: 5px 0 0;
            font-size: 18px;
            font-weight: normal;
        }
        .receipt-info {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .info-row {
            display: flex;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #dee2e6;
        }
        .info-label {
            width: 150px;
            font-weight: bold;
            color: #2c3e50;
        }
        .info-value {
            flex: 1;
            color: #34495e;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .details-table th {
            background: #3498db;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .details-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        .details-table tr:last-child td {
            border-bottom: none;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #7f8c8d;
            font-size: 12px;
            border-top: 1px solid #dee2e6;
            padding-top: 20px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-draft { background: #6c757d; color: white; }
        .status-waiting { background: #ffc107; color: #333; }
        .status-ready { background: #17a2b8; color: white; }
        .status-done { background: #28a745; color: white; }
        .status-canceled { background: #dc3545; color: white; }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 60px;
            color: rgba(52, 152, 219, 0.1);
            z-index: -1;
            white-space: nowrap;
        }
        .company-details {
            text-align: right;
            margin-bottom: 20px;
        }
        .signature {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .signature-line {
            width: 200px;
            border-top: 1px solid #333;
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="watermark"><?php echo SITE_NAME; ?></div>
    
    <div class="company-details">
        <h3><?php echo SITE_NAME; ?></h3>
        <p>123 Business Street<br>
        City, State 12345<br>
        Phone: +1 234 567 890<br>
        Email: info@coreinventory.com</p>
    </div>
    
    <div class="header">
        <h1>RECEIPT VOUCHER</h1>
        <h2>Receipt Number: ' . $receipt['receipt_number'] . '</h2>
    </div>
    
    <div class="receipt-info">
        <div class="info-row">
            <div class="info-label">Receipt Date:</div>
            <div class="info-value">' . date('F j, Y', strtotime($receipt['received_date'])) . ' at ' . $receipt['received_time'] . '</div>
        </div>
        <div class="info-row">
            <div class="info-label">Supplier:</div>
            <div class="info-value">' . $receipt['supplier'] . '</div>
        </div>
        <div class="info-row">
            <div class="info-label">Status:</div>
            <div class="info-value">
                <span class="status-badge status-' . $receipt['status'] . '">' . ucfirst($receipt['status']) . '</span>
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Created By:</div>
            <div class="info-value">' . $receipt['created_by_name'] . ' (' . $receipt['created_by_email'] . ')</div>
        </div>
        <div class="info-row">
            <div class="info-label">Created At:</div>
            <div class="info-value">' . date('F j, Y H:i:s', strtotime($receipt['created_at'])) . '</div>
        </div>
    </div>
    
    <h3>Receipt Details</h3>
    <table class="details-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th>Quantity</th>
                <th>Unit</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>' . $receipt['product_name'] . '</td>
                <td>' . $receipt['sku'] . '</td>
                <td>' . $receipt['quantity'] . '</td>
                <td>' . $receipt['unit_of_measure'] . '</td>
            </tr>
        </tbody>
    </table>
    
    <div class="signature">
        <div>
            <div class="signature-line">Received By</div>
        </div>
        <div>
            <div class="signature-line">Authorized Signature</div>
        </div>
    </div>
    
    <div class="footer">
        <p>This is a system generated receipt. No signature required.</p>
        <p>Generated on: ' . date('F j, Y H:i:s') . '</p>
    </div>
</body>
</html>
';

// Since we can't use external libraries like DOMPDF without JavaScript, 
// we'll create an HTML file and let the browser handle it
echo $html;
?>