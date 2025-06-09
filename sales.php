<?php
// sales.php - this handles all the sales stuff
// need to include database and check if user can see this
require_once 'config/db.php';
require_once 'config/auth.php';

// check if user has access to sales page
// only admins and cashiers can see sales data
requireRole(['admin', 'cashier'], $conn);

$current_user_role = getCurrentUserRole($conn); // figure out what type of user this is

// function to get all the sales from database
// this shows recent sales in a table
function getAllSales($conn, $limit = 10) {
    // get sales data with some joins - learned this in database class
    $sql = "SELECT 
        o.id, 
        o.created_at as order_date,
        o.total_amount,
        o.status,
        COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.status = 'completed'
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false; // something went wrong
    }
    
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    return $stmt->get_result();
}

// function to get sales summary - total sales, revenue, etc
function getSalesSummary($conn) {
    $sql = "SELECT 
        COUNT(id) as total_sales,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(AVG(total_amount), 0) as average_sale
    FROM orders
    WHERE status = 'completed'";
    
    $result = $conn->query($sql);
    if ($result) {
        $data = $result->fetch_assoc();
        // make the numbers look nice with decimals
        $data['total_revenue'] = number_format($data['total_revenue'], 2);
        $data['average_sale'] = number_format($data['average_sale'], 2);
        return $data;
    } else {
        // if something went wrong, return zeros
        return [
            'total_sales' => 0,
            'total_revenue' => '0.00',
            'average_sale' => '0.00'
        ];
    }
}

// Function to get top selling products
function getTopSellingProducts($conn, $limit = 5) {
    $sql = "SELECT 
        p.name,
        p.id,
        SUM(oi.quantity) as sold_quantity,
        p.quantity as remaining_quantity,
        p.price
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
    GROUP BY p.id, p.name, p.quantity, p.price
    ORDER BY sold_quantity DESC
    LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    return $stmt->get_result();
}

// Function to get low quantity products
function getLowQuantityProducts($conn, $limit = 5) {
    $sql = "SELECT 
        p.name,
        p.id,
        p.quantity as remaining_quantity,
        p.alert_quantity,
        p.price,
        CASE 
            WHEN p.quantity <= p.alert_quantity/2 THEN 'Critical'
            WHEN p.quantity <= p.alert_quantity THEN 'Low'
            ELSE 'Normal'
        END as stock_status
    FROM products p
    WHERE p.quantity <= p.alert_quantity
    ORDER BY (p.quantity/p.alert_quantity) ASC, p.quantity ASC
    LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    return $stmt->get_result();
}

// Get sales data
$sales_list = getAllSales($conn);
$sales_summary = getSalesSummary($conn);

// Get the data for display
$top_selling = getTopSellingProducts($conn);
$low_stock = getLowQuantityProducts($conn);

// Add a new sale if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sale'])) {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    
    // Get product details
    $product_sql = "SELECT name, price, quantity as stock FROM products WHERE id = ?";
    $stmt = $conn->prepare($product_sql);
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    
    if ($product) {
        // Check if we have enough stock
        if ($product['stock'] >= $quantity) {
            $total_amount = $quantity * $product['price'];
            
            // Create order
            $order_sql = "INSERT INTO orders (total_amount, status) VALUES (?, 'completed')";
            $stmt = $conn->prepare($order_sql);
            $stmt->bind_param('d', $total_amount);
            
            if ($stmt->execute()) {
                $order_id = $stmt->insert_id;                  // Add order item
                $item_sql = "INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($item_sql);
                $total_price = $quantity * $product['price'];
                $stmt->bind_param('iisidd', $order_id, $product_id, $product['name'], $quantity, $product['price'], $total_price);
                if ($stmt->execute()) {
                    // Update product quantity
                    $update_sql = "UPDATE products SET quantity = quantity - ? WHERE id = ?";
                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param('ii', $quantity, $product_id);
                    $stmt->execute();
                    
                    // Ensure inventory_transactions table exists
                    $check_table = $conn->query("SHOW TABLES LIKE 'inventory_transactions'");
                    if ($check_table->num_rows == 0) {
                        // Create the table if it doesn't exist
                        $create_table_sql = "CREATE TABLE IF NOT EXISTS inventory_transactions (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            product_id INT NOT NULL,
                            product_name VARCHAR(255) NOT NULL,
                            transaction_type ENUM('delivery', 'sale', 'adjustment', 'return') NOT NULL DEFAULT 'delivery',
                            quantity INT NOT NULL,
                            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                            total_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                            supplier_order_id INT NULL,
                            supplier_name VARCHAR(255) NULL,
                            batch_number VARCHAR(100) NULL,
                            expiry_date DATE NULL,
                            transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            notes TEXT NULL,
                            created_by VARCHAR(100) NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            
                            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                            INDEX idx_product_id (product_id),
                            INDEX idx_transaction_type (transaction_type),
                            INDEX idx_transaction_date (transaction_date),
                            INDEX idx_supplier_order_id (supplier_order_id)
                        )";
                        $conn->query($create_table_sql);
                    }
                    
                    // Create inventory transaction record for the sale
                    $total_value = $quantity * $product['price'];
                    $current_user = isset($_SESSION['username']) ? $_SESSION['username'] : 'system';
                    
                    $insert_transaction = $conn->prepare("INSERT INTO inventory_transactions (product_id, product_name, transaction_type, quantity, unit_price, total_value, notes, created_by) VALUES (?, ?, 'sale', ?, ?, ?, 'Sale transaction', ?)");
                    $insert_transaction->bind_param("isidds", 
                        $product_id, 
                        $product['name'], 
                        $quantity, 
                        $product['price'], 
                        $total_value, 
                        $current_user
                    );
                    $insert_transaction->execute();
                    
                    $success_message = "Sale added successfully!";
                    
                    // Refresh data
                    $sales_list = getAllSales($conn);
                    $sales_summary = getSalesSummary($conn);
                } else {
                    $error_message = "Error adding order item: " . $conn->error;
                }
            } else {
                $error_message = "Error creating order: " . $conn->error;
            }
        } else {
            $error_message = "Not enough stock available. Available: " . $product['stock'];
        }
    } else {
        $error_message = "Product not found!";
    }
}

// Get products for dropdown
$products_sql = "SELECT id, name, price, quantity FROM products WHERE quantity > 0 ORDER BY name";
$products = $conn->query($products_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>Sales - Inventory System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <style>        .dashboard-content {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .sales-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin: 2rem auto;
            max-width: 1200px;
        }
        
        .sales-card {
            background-color: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .sales-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin: 2rem auto;
            max-width: 1000px;
            padding: 0 1rem;
        }
          .summary-item {
            background-color: white;
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            min-height: 160px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .summary-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .summary-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #1a56db;
            line-height: 1;
        }
        
        .summary-label {
            font-size: 1rem;
            color: #4b5563;
            font-weight: 500;
        }
          .recent-sales table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 1rem 0;
        }
        
        .recent-sales th,
        .recent-sales td {
            padding: 1rem;
            text-align: center;
        }
        
        .recent-sales th {
            background-color: #f8fafc;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }
        
        .recent-sales tr:not(:last-child) {
            border-bottom: 1px solid #e5e7eb;
        }
        
        .add-sale-form {
            margin-top: 1.5rem;
        }
          .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.95rem;
        }
        
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #1a56db;
            box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.1);
        }
          .form-actions {
            margin-top: 1.5rem;
            text-align: center;
        }
          .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background-color: #1d4ed8;
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
            max-width: 200px;
            margin: 0 auto;
        }
        
        .btn-primary:hover {
            background-color: #1e40af;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .bg-success {
            background-color: #10b981;
            color: white;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            padding: 1rem;
            border-radius: 0.25rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 0.25rem;
            margin-bottom: 1.5rem;
        }

        .stock-status-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            margin: 2rem auto;
            max-width: 1200px;
            padding: 0 1rem;
        }

        .status-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
        }

        .see-all {
            color: #2563eb;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .see-all:hover {
            text-decoration: underline;
        }

        .product-list table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .product-list th,
        .product-list td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .product-list th {
            font-weight: 500;
            color: #6b7280;
            font-size: 0.875rem;
            text-transform: uppercase;
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .product-name {
            font-weight: 500;
            color: #1f2937;
        }

        .stock-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .stock-badge.critical {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .stock-badge.low {
            background-color: #fff7ed;
            color: #ea580c;
        }

        .stock-badge.normal {
            background-color: #f0fdf4;
            color: #16a34a;
        }

        @media (max-width: 768px) {
            .stock-status-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="dashboard-content">            <div class="dashboard-header" style="text-align: center;">
                <h1>Sales Management</h1>
            </div>

            <!-- Original sales summary and content below -->
            <div class="sales-summary">
                <div class="summary-item">
                    <div class="summary-value"><?php echo isset($sales_summary['total_sales']) ? $sales_summary['total_sales'] : 0; ?></div>
                    <div class="summary-label">Total Sales</div>
                </div>
                
                <div class="summary-item">
                    <div class="summary-value">$<?php echo isset($sales_summary['total_revenue']) ? $sales_summary['total_revenue'] : '0.00'; ?></div>
                    <div class="summary-label">Total Revenue</div>
                </div>
                
                <div class="summary-item">
                    <div class="summary-value">$<?php echo isset($sales_summary['average_sale']) ? $sales_summary['average_sale'] : '0.00'; ?></div>
                    <div class="summary-label">Average Sale</div>
                </div>
            </div>
            
            <div class="sales-grid">
                <!-- Recent Sales Table -->
                <div class="sales-card">
                    <h2 style="text-align: center; margin-bottom: 1.5rem;">Recent Sales</h2>
                    <table class="recent-sales">                        <thead>
                            <tr>
                                <th>Sales ID</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($sales_list && $sales_list->num_rows > 0): ?>
                                <?php while ($sale = $sales_list->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $sale['id']; ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($sale['order_date'])); ?></td>
                                        <td><?php echo $sale['item_count']; ?> items</td>
                                        <td>$<?php echo number_format($sale['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-success"><?php echo ucfirst($sale['status']); ?></span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No sales found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Add Sale Form -->
                <div class="sales-card">
                    <h2 style="text-align: center; margin-bottom: 1.5rem;">Add New Sale</h2>
                    <form class="add-sale-form" method="POST">
                        <div class="form-group">
                            <label for="product_id">Select Product</label>
                            <select id="product_id" name="product_id" required>
                                <option value="">-- Select Product --</option>
                                <?php if ($products && $products->num_rows > 0): ?>
                                    <?php while ($product = $products->fetch_assoc()): ?>
                                        <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['price']; ?>" data-stock="<?php echo $product['quantity']; ?>">
                                            <?php echo $product['name']; ?> - $<?php echo number_format($product['price'], 2); ?> (<?php echo $product['quantity']; ?> in stock)
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="number" id="quantity" name="quantity" min="1" value="1" required>
                            <small id="stock-info"></small>
                        </div>
                        
                        <div class="form-group">
                            <label for="total">Total Amount</label>
                            <input type="text" id="total" readonly>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="add_sale" class="btn-primary">
                                <i class="fas fa-plus-circle"></i> Add Sale
                            </button>
                        </div>
                    </form>                </div>
            </div>

            <!-- Stock Status Cards -->
            <div class="stock-status-container">
                <!-- Top Selling Products -->
                <div class="status-card">
                    <div class="card-header">
                        <h2>Top Selling Stock</h2>
                        <a href="inventory.php" class="see-all">See All</a>
                    </div>
                    <div class="product-list">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Sold Qty</th>
                                    <th>Remaining</th>
                                    <th>Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($top_selling && $top_selling->num_rows > 0): ?>
                                    <?php while ($product = $top_selling->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td><?php echo $product['sold_quantity'] ?: 0; ?></td>
                                            <td><?php echo $product['remaining_quantity']; ?></td>
                                            <td>$<?php echo number_format($product['price'], 2); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4">No sales data available</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Low Quantity Products -->
                <div class="status-card">
                    <div class="card-header">
                        <h2>Low Quantity Stock</h2>
                        <a href="inventory.php" class="see-all">See All</a>
                    </div>
                    <div class="product-list">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Remaining</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($low_stock && $low_stock->num_rows > 0): ?>
                                    <?php while ($product = $low_stock->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="product-info">
                                                    <span class="product-name"><?php echo htmlspecialchars($product['name']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo $product['remaining_quantity']; ?> units</td>
                                            <td>
                                                <span class="stock-badge <?php echo strtolower($product['stock_status']); ?>">
                                                    <?php echo $product['stock_status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="3">All stock levels are normal</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const productSelect = document.getElementById('product_id');
            const quantityInput = document.getElementById('quantity');
            const totalInput = document.getElementById('total');
            const stockInfo = document.getElementById('stock-info');
            
            function updateTotal() {
                const selectedOption = productSelect.options[productSelect.selectedIndex];
                
                if (selectedOption && selectedOption.value) {
                    const price = parseFloat(selectedOption.dataset.price);
                    const stock = parseInt(selectedOption.dataset.stock);
                    const quantity = parseInt(quantityInput.value) || 0;
                    
                    if (quantity > stock) {
                        quantityInput.value = stock;
                        stockInfo.textContent = 'Quantity adjusted to available stock.';
                        stockInfo.style.color = '#b91c1c';
                    } else {
                        stockInfo.textContent = '';
                    }
                    
                    const total = price * parseInt(quantityInput.value);
                    totalInput.value = '$' + total.toFixed(2);
                } else {
                    totalInput.value = '';
                }
            }
            
            productSelect.addEventListener('change', updateTotal);
            quantityInput.addEventListener('input', updateTotal);
              // Initialize
            updateTotal();
        });
    </script>
    
    <!-- Auto-logout system -->
    <script src="css/auto-logout.js"></script>
    <script>
        // Mark body as logged in for auto-logout detection
        document.body.classList.add('logged-in');
        document.body.setAttribute('data-user-id', '<?php echo $_SESSION['user_id']; ?>');
    </script>
</body>
</html>
