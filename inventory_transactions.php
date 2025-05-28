<?php
require_once 'config/db.php';
require_once 'config/auth.php';

// Check if user has access to inventory management
requireRole(['admin', 'store_clerk', 'supplier'], $conn);

$current_user_role = getCurrentUserRole($conn);

// Function to get all inventory transactions
function getInventoryTransactions($conn) {
    $sql = "SELECT 
                it.*,
                p.name as product_name_current,
                p.quantity as current_stock,
                so.order_date as order_date
            FROM inventory_transactions it
            LEFT JOIN products p ON it.product_id = p.id
            LEFT JOIN supplier_orders so ON it.supplier_order_id = so.id
            ORDER BY it.transaction_date DESC";
    
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Function to get inventory summary by product
function getInventorySummary($conn) {
    $sql = "SELECT 
                p.id,
                p.name,
                p.quantity as current_quantity,
                p.alert_quantity,
                COUNT(it.id) as total_transactions,
                SUM(CASE WHEN it.transaction_type = 'delivery' THEN it.quantity ELSE 0 END) as total_delivered,
                SUM(CASE WHEN it.transaction_type = 'sale' THEN it.quantity ELSE 0 END) as total_sold,
                MAX(it.transaction_date) as last_transaction
            FROM products p
            LEFT JOIN inventory_transactions it ON p.id = it.product_id
            GROUP BY p.id, p.name, p.quantity, p.alert_quantity
            ORDER BY p.name";
    
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

$transactions = getInventoryTransactions($conn);
$inventory_summary = getInventorySummary($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Transactions - Inventory Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <style> 
        .dashboard-container {
            display: flex;
            min-height: 100vh;
            background: #f4f6f9;
        }        .main-content {
            flex: 1;
            padding: 15px;
            background: #fff;
            min-height: 100vh;
            max-width: 70vw;
            margin: 0 auto;
        }
        
        .dashboard-header {
            text-align: center;
            margin-bottom: 20px;
        }
          .dashboard-header h1 {
            font-size: 1.6rem;
            margin: 0 0 10px 0;
            color: #333;
        }
        .wide-table {
            width: 100%;
        }
        .wide-page {
            max-width: 95vw;
            margin: 0 auto;
            padding: 5px 10px;
        }
        .page-header h1 {
            font-size: 1.8rem;
            margin: 5px 0 3px 0;
        }
        .page-header p {
            font-size: 1rem;
            margin: 0 0 10px 0;
            color: #666;
        }
          .transaction-type {
            padding: 4px 8px;
            border-radius: 4px;
            color: white;
            font-size: 11px;
            font-weight: bold;
        }
        .transaction-delivery { background-color: #28a745; }
        .transaction-sale { background-color: #dc3545; }
        .transaction-adjustment { background-color: #ffc107; color: #000; }
        .transaction-return { background-color: #17a2b8; }
          .tabs {
            margin-bottom: 20px;
            text-align: center;
        }
        .tab-button {
            padding: 8px 16px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            cursor: pointer;
            display: inline-block;
            margin-right: 3px;
            font-size: 1rem;
            border-radius: 4px;
        }
        .tab-button.active {
            background: #007bff;
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }        .card {
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 8px;
            max-width: 90%;
            margin-left: auto;
            margin-right: auto;
        }
        .card-header {
            text-align: center;
            background: #f8f9fa;
            border-radius: 8px 8px 0 0;
        }        .card-header h3 {
            font-size: 1.1rem;
            margin: 0;
            padding: 12px;
            color: #333;
        }.card-body {
            padding: 15px;
        }
        
        .table-responsive {
            text-align: center;
        }        .wide-table {
            font-size: 0.8rem;
            width: 100%;
            margin: 0 auto;
            text-align: center;
        }
        .wide-table th,
        .wide-table td {
            padding: 6px 8px;
            vertical-align: middle;
            text-align: center;
        }        .wide-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            font-size: 0.85rem;
        }.wide-table td {
            border-bottom: 1px solid #eee;
        }
        .wide-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .no-data {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
            font-size: 1rem;
        }
        
        .status-normal { 
            color: #28a745; 
            font-weight: 500; 
            padding: 3px 8px;
            background: #d4edda;
            border-radius: 3px;
        }
        .status-low { 
            color: #dc3545; 
            font-weight: 600; 
            padding: 3px 8px;
            background: #f8d7da;
            border-radius: 3px;
        }
        
        @media (max-width: 1200px) {
            .wide-page {
                padding: 5px 8px;
            }
        }
        
        @media (max-width: 768px) {
            .wide-table {
                font-size: 0.8rem;
            }
            .wide-table th,
            .wide-table td {
                padding: 8px 10px;
            }
            .page-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Include Sidebar -->
        <?php require_once 'templates/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="dashboard-header">
                <h1>Inventory Transactions</h1>
                <div class="header-actions">
                    <div class="search-bar">
                        <input type="text" id="searchInput" placeholder="Search transactions..." class="search-input">
                        <button type="button" class="search-btn"><i class="fas fa-search"></i></button>
                    </div>
                </div>
            </header>

            <div class="tabs">
                <div class="tab-button active" onclick="showTab('transactions')">Recent Transactions</div>
                <div class="tab-button" onclick="showTab('summary')">Inventory Summary</div>
            </div>

            <!-- Transactions Tab -->
            <div id="transactions" class="tab-content active">
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Transactions</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($transactions)): ?>
                            <div class="no-data">
                                No transactions found. Transactions will appear when orders are marked as delivered.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table wide-table">
                                    <thead>
                                        <tr>
                                            <th>Transaction Date</th>
                                            <th>Product Name</th>
                                            <th>Type</th>
                                            <th>Quantity</th>
                                            <th>Unit Price</th>
                                            <th>Total Value</th>
                                            <th>Supplier</th>
                                            <th>Order ID</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td><?= date('M j, Y g:i A', strtotime($transaction['transaction_date'])) ?></td>
                                                <td><strong><?= htmlspecialchars($transaction['product_name']) ?></strong></td>
                                                <td>
                                                    <span class="transaction-type transaction-<?= $transaction['transaction_type'] ?>">
                                                        <?= ucfirst($transaction['transaction_type']) ?>
                                                    </span>
                                                </td>
                                                <td><?= number_format($transaction['quantity']) ?></td>
                                                <td>$<?= number_format($transaction['unit_price'], 2) ?></td>
                                                <td><strong>$<?= number_format($transaction['total_value'], 2) ?></strong></td>
                                                <td><?= htmlspecialchars($transaction['supplier_name'] ?? '-') ?></td>
                                                <td>#<?= $transaction['supplier_order_id'] ?? '-' ?></td>
                                                <td><em><?= htmlspecialchars($transaction['notes'] ?? '-') ?></em></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div> <!-- End of card-body -->
                </div> <!-- End of card -->
            </div> <!-- End of transactions tab -->

            <!-- Inventory Summary Tab -->
            <div id="summary" class="tab-content" style="display:none;">
                <div class="card">
                    <div class="card-header">
                        <h3>Inventory Summary by Product</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table wide-table">
                                <thead>
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Current Stock</th>
                                        <th>Alert Level</th>
                                        <th>Total Delivered</th>
                                        <th>Total Sold</th>
                                        <th>Total Transactions</th>
                                        <th>Last Activity</th>
                                        <th>Stock Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventory_summary as $summary): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($summary['name']) ?></strong></td>
                                            <td><?= number_format($summary['current_quantity']) ?> units</td>
                                            <td><?= number_format($summary['alert_quantity']) ?> units</td>
                                            <td><?= number_format($summary['total_delivered']) ?> units</td>
                                            <td><?= number_format($summary['total_sold']) ?> units</td>
                                            <td><?= number_format($summary['total_transactions']) ?></td>
                                            <td>
                                                <?= $summary['last_transaction'] ? date('M j, Y', strtotime($summary['last_transaction'])) : 'Never' ?>
                                            </td>
                                            <td>                                                <?php if ($summary['current_quantity'] <= $summary['alert_quantity']): ?>
                                                    <span class="status-low">Low Stock</span>
                                                <?php else: ?>
                                                    <span class="status-normal">Normal</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div> <!-- End of card-body -->
                </div> <!-- End of card -->
            </div> <!-- End of summary tab -->        </main>
    </div> <!-- End of dashboard-container -->

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.style.display = 'none';
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            const selectedTab = document.getElementById(tabName);
            if (selectedTab) {
                selectedTab.style.display = 'block';
                selectedTab.classList.add('active');
            }
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const tables = document.querySelectorAll('.wide-table tbody');
            
            tables.forEach(table => {
                const rows = table.querySelectorAll('tr');
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(filter) ? '' : 'none';
                });
            });
        });
    </script>
</body>
</html>
