<?php
/**
 * Inventory Transactions Management Page
 * 
 * Displays inventory transaction history with role-based filtering.
 * Provides detailed transaction logs and inventory summaries based on user permissions.
 * Supports different views for admin, store_clerk, supplier, and cashier roles.
 */
require_once 'config/db.php';
require_once 'config/auth.php';

// Verify user has required permissions to access transaction data
requireRole(['admin', 'store_clerk', 'supplier', 'cashier'], $conn);

$current_user_role = getCurrentUserRole($conn);

/**
 * Retrieve inventory transactions based on user role permissions
 * 
 * Implements role-based access control for transaction visibility:
 * - Cashiers: Only sales transactions
 * - Store Clerks: Only delivery transactions
 * - Admins/Suppliers: All transaction types
 * 
 * @param mysqli $conn Database connection
 * @param string $user_role Current user's role
 * @return array Array of transaction records with product and order details
 */
function getInventoryTransactions($conn, $user_role) {
    // Configure transaction filtering based on user role permissions
    $where_clause = "";
    if ($user_role === 'cashier') {
        // Restrict cashiers to sales transactions only
        $where_clause = "WHERE it.transaction_type = 'sale'";
    } elseif ($user_role === 'store_clerk') {
        // Restrict store clerks to delivery transactions only
        $where_clause = "WHERE it.transaction_type = 'delivery'";
    }
    // Admins and suppliers have unrestricted access to all transactions
    
    // Comprehensive query joining transaction, product, and order data
    $sql = "SELECT 
                it.*,
                p.name as product_name_current,
                p.quantity as current_stock,
                so.order_date as order_date
            FROM inventory_transactions it
            LEFT JOIN products p ON it.product_id = p.id
            LEFT JOIN supplier_orders so ON it.supplier_order_id = so.id
            $where_clause
            ORDER BY it.transaction_date DESC";
    
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Generate inventory summary statistics grouped by product
 * 
 * Provides aggregated transaction data with role-based filtering:
 * - Calculates total transactions, sales, and deliveries per product
 * - Shows current stock levels and alert quantities
 * - Filters data based on user role permissions
 * 
 * @param mysqli $conn Database connection
 * @param string $user_role Current user's role
 * @return array Array of product summary statistics
 */
function getInventorySummary($conn, $user_role) {
    // Generate role-specific queries for transaction summaries
    if ($user_role === 'cashier') {
        // Cashiers view sales data only
        $sql = "SELECT 
                    p.name as base_name,
                    p.quantity as current_quantity,
                    p.alert_quantity as alert_quantity,
                    COUNT(it.id) as total_transactions,
                    0 as total_delivered,
                    SUM(CASE WHEN it.transaction_type = 'sale' THEN it.quantity ELSE 0 END) as total_sold,
                    MAX(it.transaction_date) as last_transaction
                FROM products p
                LEFT JOIN inventory_transactions it ON p.id = it.product_id AND it.transaction_type = 'sale'
                GROUP BY p.id, p.name
                ORDER BY p.name";
    } elseif ($user_role === 'store_clerk') {
        // Store clerks view both sales and delivery data
        $sql = "SELECT 
                    p.name as base_name,
                    p.quantity as current_quantity,
                    p.alert_quantity as alert_quantity,
                    COUNT(it.id) as total_transactions,
                    SUM(CASE WHEN it.transaction_type = 'delivery' THEN it.quantity ELSE 0 END) as total_delivered,
                    SUM(CASE WHEN it.transaction_type = 'sale' THEN it.quantity ELSE 0 END) as total_sold,
                    MAX(it.transaction_date) as last_transaction
                FROM products p
                LEFT JOIN inventory_transactions it ON p.id = it.product_id AND it.transaction_type IN ('sale', 'delivery')
                GROUP BY p.id, p.name
                ORDER BY p.name";
    } else {
        // Admins and suppliers view comprehensive transaction data
        $sql = "SELECT 
                    p.name as base_name,
                    p.quantity as current_quantity,
                    p.alert_quantity as alert_quantity,
                    COUNT(it.id) as total_transactions,
                    SUM(CASE WHEN it.transaction_type = 'delivery' THEN it.quantity ELSE 0 END) as total_delivered,
                    SUM(CASE WHEN it.transaction_type = 'sale' THEN it.quantity ELSE 0 END) as total_sold,
                    MAX(it.transaction_date) as last_transaction
                FROM products p
                LEFT JOIN inventory_transactions it ON p.id = it.product_id
                GROUP BY p.id, p.name
                ORDER BY p.name";
    }
    
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Execute data retrieval functions
$transactions = getInventoryTransactions($conn, $current_user_role);
$inventory_summary = getInventorySummary($conn, $current_user_role);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    
    <title>Inventory Transactions - Inventory Management System</title>
    <!-- using basic styles instead of fancy external fonts -->
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
        <?php require_once 'templates/sidebar.php'; ?>        <!-- Main Content -->
        <main class="main-content">
            <header class="dashboard-header">
                <h1>Inventory Transactions</h1>                
                <?php
                // show different messages based on user type - learned this from a tutorial
                $access_info = "";
                if ($current_user_role === 'cashier') {
                    $access_info = "<p style='color: #17a2b8; font-size: 0.9rem; margin: 5px 0;'>📊 You can view <strong>Sale Transactions</strong> only</p>";
                } elseif ($current_user_role === 'store_clerk') {
                    $access_info = "<p style='color: #28a745; font-size: 0.9rem; margin: 5px 0;'>🚚 You can view <strong>Delivery Transactions</strong> only</p>";
                } else {
                    $access_info = "<p style='color: #6c757d; font-size: 0.9rem; margin: 5px 0;'>👁️ Viewing <strong>All Transaction Types</strong></p>";
                }
                echo $access_info; // display the message
                ?><div class="header-actions">
                    <div class="search-bar">                        <input type="text" id="searchInput" placeholder="Search transactions..." class="search-input">
                        <button type="button" class="search-btn">🔍</button>
                    </div>
                    <?php if ($current_user_role !== 'cashier'): ?>                    <div class="filter-dropdown" style="margin-left: 15px;">
                        <select id="typeFilter" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                            <option value="">All Types</option>
                            <?php if ($current_user_role === 'cashier'): ?>
                                <option value="sale">Sales Only</option>
                            <?php elseif ($current_user_role === 'store_clerk'): ?>
                                <option value="delivery">Deliveries Only</option>
                            <?php else: ?>
                                <option value="sale">Sales Only</option>
                                <option value="delivery">Deliveries Only</option>
                                <option value="adjustment">Adjustments Only</option>
                                <option value="return">Returns Only</option>
                            <?php endif; ?>
                        </select>
                    </div>                    <?php endif; ?>
                </div>
            </header>            <div class="tabs">
                <div class="tab-button active" onclick="showTab('transactions')">Recent Transactions</div>
                <?php if ($current_user_role === 'admin' || $current_user_role === 'supplier'): ?>
                <div class="tab-button" onclick="showTab('summary')">Inventory Summary</div>
                <?php endif; ?>
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
                    <div class="card-body">                        <div class="table-responsive">
                            <table class="table wide-table">
                                <thead>
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Current Stock</th>
                                        <th>Alert Level</th>
                                        <?php if ($current_user_role !== 'cashier'): ?>
                                            <th>Total Delivered</th>
                                        <?php endif; ?>
                                        <th>Total Sold</th>
                                        <th>Total Transactions</th>
                                        <th>Last Activity</th>
                                        <th>Stock Status</th>
                                    </tr>
                                </thead>
                                <tbody>                                    <?php foreach ($inventory_summary as $summary): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($summary['base_name']) ?></strong></td>
                                            <td><?= number_format($summary['current_quantity']) ?> units</td>
                                            <td><?= number_format($summary['alert_quantity']) ?> units</td>
                                            <?php if ($current_user_role !== 'cashier'): ?>
                                                <td><?= number_format($summary['total_delivered']) ?> units</td>
                                            <?php endif; ?>
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
        }        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const activeTab = document.querySelector('.tab-content.active');
            
            if (activeTab.id === 'transactions') {
                // Search in transactions table
                const rows = activeTab.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    if (row.children.length > 1) {
                        const productName = row.children[1].textContent.toLowerCase();
                        const transactionType = row.children[2].textContent.toLowerCase();
                        const date = row.children[0].textContent.toLowerCase();
                        const supplier = row.children[6].textContent.toLowerCase();
                        const notes = row.children[8].textContent.toLowerCase();
                        
                        const matchesSearch = productName.includes(filter) || 
                                             transactionType.includes(filter) || 
                                             date.includes(filter) || 
                                             supplier.includes(filter) || 
                                             notes.includes(filter);
                                             
                        row.style.display = matchesSearch ? '' : 'none';
                    }
                });
            } else if (activeTab.id === 'summary') {
                // Search in summary table
                const rows = activeTab.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    if (row.children.length > 1) {
                        const productName = row.children[0].textContent.toLowerCase();
                        const status = row.querySelector('.status-low, .status-normal').textContent.toLowerCase();
                        
                        const matchesSearch = productName.includes(filter) || status.includes(filter);
                        row.style.display = matchesSearch ? '' : 'none';
                    }
                });
            }
        });
    </script>    <!-- Auto-logout system -->
    <script src="css/auto-logout.js"></script>
    <script>
        // Mark body as logged in for auto-logout detection
        document.body.classList.add('logged-in');
        document.body.setAttribute('data-user-id', '<?php echo $_SESSION['user_id']; ?>');
    </script>    <!-- Add filter functionality for transaction types -->
    <script>
        // Add event listener for type filter if it exists
        const typeFilter = document.getElementById('typeFilter');
        if (typeFilter) {
            typeFilter.addEventListener('change', function() {
                const filterValue = this.value.toLowerCase();
                const rows = document.querySelectorAll('#transactions tbody tr');
                
                rows.forEach(row => {
                    if (row.children.length > 1) {
                        const transactionType = row.children[2].textContent.toLowerCase();
                        row.style.display = (filterValue === '' || transactionType.includes(filterValue)) ? '' : 'none';
                    }
                });
            });
        }
    </script>
</body>
</html>
