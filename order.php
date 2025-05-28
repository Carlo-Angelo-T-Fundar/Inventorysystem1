<?php
require_once 'config/db.php';
require_once 'config/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user has access to supplier orders
// Most users should have access to view supplier orders for inventory management

// Function to get all supplier orders
function getAllSupplierOrders($conn) {
    $sql = "SELECT so.*, p.quantity as current_stock 
            FROM supplier_orders so
            LEFT JOIN products p ON so.product_id = p.id
            ORDER BY so.order_date DESC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Function to get all products for order creation
function getProducts($conn) {
    $sql = "SELECT id, name, quantity, price FROM products ORDER BY name ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Function to get all suppliers
function getSuppliers($conn) {
    // Ensure suppliers table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'suppliers'");
    if ($check_table->num_rows == 0) {
        $create_table_sql = "CREATE TABLE IF NOT EXISTS suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            address TEXT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $conn->query($create_table_sql);
        
        // Insert some sample suppliers if table is empty
        $sample_suppliers = "INSERT INTO suppliers (name, email, phone, is_active) VALUES 
            ('ABC Supply Co.', 'contact@abcsupply.com', '+1-555-0101', 1),
            ('XYZ Trading', 'sales@xyztrading.com', '+1-555-0102', 1),
            ('Global Suppliers Inc.', 'info@globalsuppliers.com', '+1-555-0103', 1)";
        $conn->query($sample_suppliers);
    }
    
    $sql = "SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'create_supplier':
                $name = $_POST['supplier_name'];
                $email = $_POST['supplier_email'];
                $phone = $_POST['supplier_phone'];
                
                $stmt = $conn->prepare("INSERT INTO suppliers (name, email, phone, is_active) VALUES (?, ?, ?, 1)");
                $stmt->bind_param("sss", $name, $email, $phone);
                  if ($stmt->execute()) {
                    $success = "Supplier created successfully";
                    // Refresh the page to show the new supplier in the dropdown
                    echo "<script>window.location.reload();</script>";
                } else {
                    throw new Exception("Failed to create supplier");
                }
                break;

            case 'create_product':
                $name = $_POST['product_name'];
                $price = (float)$_POST['product_price'];
                $alert_quantity = (int)$_POST['alert_quantity'];
                
                $stmt = $conn->prepare("INSERT INTO products (name, quantity, price, alert_quantity) VALUES (?, 0, ?, ?)");
                $stmt->bind_param("sdi", $name, $price, $alert_quantity);
                  if ($stmt->execute()) {
                    $success = "Product created successfully";
                    // Refresh the page to show the new product in the dropdown
                    echo "<script>window.location.reload();</script>";
                } else {
                    throw new Exception("Failed to create product");
                }
                break;

            case 'create_order':
                $supplier_id = $_POST['supplier_id'];
                $product_id = $_POST['product_id'];
                $quantity_ordered = (int)$_POST['quantity_ordered'];
                $unit_price = (float)$_POST['unit_price'];
                $expected_delivery_date = $_POST['expected_delivery_date'];
                $notes = $_POST['notes'] ?? '';
                
                // Get supplier and product information
                $supplier_result = $conn->query("SELECT * FROM suppliers WHERE id = $supplier_id");
                $supplier = $supplier_result->fetch_assoc();
                
                $product_result = $conn->query("SELECT * FROM products WHERE id = $product_id");
                $product = $product_result->fetch_assoc();
                
                if (!$supplier || !$product) {
                    throw new Exception("Invalid supplier or product selected");
                }
                
                $total_amount = $quantity_ordered * $unit_price;
                
                $stmt = $conn->prepare("INSERT INTO supplier_orders (supplier_name, supplier_email, supplier_phone, product_id, product_name, quantity_ordered, unit_price, total_amount, expected_delivery_date, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ordered')");
                $stmt->bind_param("sssissddss", 
                    $supplier['name'], 
                    $supplier['email'], 
                    $supplier['phone'], 
                    $product_id, 
                    $product['name'], 
                    $quantity_ordered, 
                    $unit_price, 
                    $total_amount, 
                    $expected_delivery_date, 
                    $notes
                );
                
                if ($stmt->execute()) {
                    $success = "Supplier order created successfully";
                } else {
                    throw new Exception("Failed to create order");
                }
                break;

            case 'update_status':
                $order_id = $_POST['order_id'];
                $status = $_POST['status'];
                $quantity_received = isset($_POST['quantity_received']) ? (int)$_POST['quantity_received'] : 0;
                  $stmt = $conn->prepare("UPDATE supplier_orders SET status = ?, quantity_received = ?, actual_delivery_date = CASE WHEN ? = 'delivered' THEN CURDATE() ELSE actual_delivery_date END WHERE id = ?");
                $stmt->bind_param("sisi", $status, $quantity_received, $status, $order_id);
                  if ($stmt->execute()) {
                    // If order is delivered, create new inventory transaction record
                    if ($status === 'delivered' && $quantity_received > 0) {
                        // Get order details for the inventory transaction
                        $order_result = $conn->query("SELECT product_id, product_name, supplier_name, unit_price FROM supplier_orders WHERE id = $order_id");
                        $order = $order_result->fetch_assoc();
                        
                        // Create inventory transaction record instead of updating existing quantity
                        $total_value = $quantity_received * $order['unit_price'];
                        $current_user = isset($_SESSION['username']) ? $_SESSION['username'] : 'system';
                        
                        // First, ensure inventory_transactions table exists
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
                        
                        // Insert new inventory transaction record
                        $insert_transaction = $conn->prepare("INSERT INTO inventory_transactions (product_id, product_name, transaction_type, quantity, unit_price, total_value, supplier_order_id, supplier_name, notes, created_by) VALUES (?, ?, 'delivery', ?, ?, ?, ?, ?, 'Delivered from supplier order', ?)");
                        $insert_transaction->bind_param("isiddsss", 
                            $order['product_id'], 
                            $order['product_name'], 
                            $quantity_received, 
                            $order['unit_price'], 
                            $total_value, 
                            $order_id, 
                            $order['supplier_name'], 
                            $current_user
                        );
                        
                        if ($insert_transaction->execute()) {
                            // Also update the products table quantity for inventory display
                            $update_inventory = $conn->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");
                            $update_inventory->bind_param("ii", $quantity_received, $order['product_id']);
                            $update_inventory->execute();
                            
                            $success = "Order delivered and new inventory record created successfully";
                        } else {
                            throw new Exception("Failed to create inventory transaction record");
                        }
                    } else {
                        $success = "Order status updated successfully";
                    }
                } else {
                    throw new Exception("Failed to update order status");
                }
                break;

            case 'delete_order':
                $order_id = $_POST['order_id'];
                
                $stmt = $conn->prepare("DELETE FROM supplier_orders WHERE id = ?");
                $stmt->bind_param("i", $order_id);
                
                if ($stmt->execute()) {
                    $success = "Order deleted successfully";
                } else {
                    throw new Exception("Failed to delete order");
                }
                break;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all data for display
$supplier_orders = getAllSupplierOrders($conn);
$products = getProducts($conn);
$suppliers = getSuppliers($conn);?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Orders - Inventory System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/sidebar.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Include Sidebar -->
        <?php 
        $current_page = 'order';
        require_once 'templates/sidebar.php'; 
        ?>

        <!-- Main Content -->
        <main class="main-content">            <div class="content-header">
                <h1>Supplier Orders (Restocking)</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openCreateOrderModal()">
                        <i class="fas fa-plus"></i> Resupply Products
                    </button>
                </div>
            </div>

            <div class="order-filters">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchOrder" placeholder="Search by supplier or product...">
                </div>
                  <div class="filter-group">
                    <select id="statusFilter" onchange="filterOrders()">
                        <option value="">All Status</option>
                        <option value="ordered">Ordered</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Supplier Orders Table -->
            <div class="card">
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Supplier Info</th>
                                <th>Product</th>
                                <th>Qty Ordered</th>
                                <th>Qty Received</th>
                                <th>Unit Price</th>
                                <th>Total Amount</th>
                                <th>Order Date</th>
                                <th>Expected Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($supplier_orders as $order): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['supplier_name']); ?></strong><br>
                                    <small>
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($order['supplier_email']); ?><br>
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['supplier_phone']); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($order['product_name']); ?><br>
                                    <small>Current Stock: <?php echo $order['current_stock'] ?? 'N/A'; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($order['quantity_ordered']); ?></td>
                                <td><?php echo htmlspecialchars($order['quantity_received']); ?></td>
                                <td>$<?php echo number_format($order['unit_price'], 2); ?></td>
                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td><?php echo date('M j, Y', strtotime($order['order_date'])); ?></td>
                                <td><?php echo $order['expected_delivery_date'] ? date('M j, Y', strtotime($order['expected_delivery_date'])) : 'N/A'; ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($order['status']); ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="updateOrderStatus(<?php echo $order['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteOrder(<?php echo $order['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($supplier_orders)): ?>
                            <tr>
                                <td colspan="11" class="text-center">No supplier orders found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>    <!-- Create Supplier Order Modal -->
    <div id="createOrderModal" class="modal">
        <div class="modal-content">
            <h2>Resupply Products</h2>
            <form method="POST" class="order-form">
                <input type="hidden" name="action" value="create_order">
                
                <div class="form-group">
                    <label for="supplier_id">Supplier</label>
                    <div class="supplier-selection">
                        <select id="supplier_id" name="supplier_id" required class="form-control">
                            <option value="">Select a supplier...</option>
                            <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['id']; ?>">
                                <?php echo htmlspecialchars($supplier['name']); ?> - <?php echo htmlspecialchars($supplier['email']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="openCreateSupplierModal()">
                            <i class="fas fa-plus"></i> New Supplier
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="product_id">Product to Restock</label>
                    <div class="product-selection">
                        <select id="product_id" name="product_id" required class="form-control">
                            <option value="">Select a product...</option>
                            <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>">
                                <?php echo htmlspecialchars($product['name']); ?> (Current: <?php echo $product['quantity']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="openCreateProductModal()">
                            <i class="fas fa-plus"></i> New Product
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="quantity_ordered">Quantity to Order</label>
                    <input type="number" id="quantity_ordered" name="quantity_ordered" required min="1" class="form-control">
                </div>

                <div class="form-group">
                    <label for="unit_price">Unit Price ($)</label>
                    <input type="number" id="unit_price" name="unit_price" required min="0" step="0.01" class="form-control">
                </div>

                <div class="form-group">
                    <label for="expected_delivery_date">Expected Delivery Date</label>
                    <input type="date" id="expected_delivery_date" name="expected_delivery_date" class="form-control">
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Additional notes or instructions..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create Order</button>
                    <button type="button" class="btn btn-secondary" onclick="closeCreateOrderModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="updateStatusModal" class="modal">
        <div class="modal-content">
            <h2>Update Order Status</h2>
            <form method="POST" class="status-form">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" id="update_order_id">
                  <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" required class="form-control">
                        <option value="ordered">Ordered</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="form-group" id="quantityReceivedGroup" style="display: none;">
                    <label for="quantity_received">Quantity Received</label>
                    <input type="number" id="quantity_received" name="quantity_received" min="0" class="form-control">
                    <small class="form-text">Enter the actual quantity received (this will update inventory)</small>
                </div>                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Status</button>
                    <button type="button" class="btn btn-secondary" onclick="closeUpdateStatusModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create New Supplier Modal -->
    <div id="createSupplierModal" class="modal">
        <div class="modal-content">
            <h2>Add New Supplier</h2>
            <form method="POST" class="supplier-form">
                <input type="hidden" name="action" value="create_supplier">
                
                <div class="form-group">
                    <label for="supplier_name">Supplier Name</label>
                    <input type="text" id="supplier_name" name="supplier_name" required class="form-control">
                </div>

                <div class="form-group">
                    <label for="supplier_email">Email</label>
                    <input type="email" id="supplier_email" name="supplier_email" required class="form-control">
                </div>

                <div class="form-group">
                    <label for="supplier_phone">Phone Number</label>
                    <input type="tel" id="supplier_phone" name="supplier_phone" required class="form-control">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Supplier</button>
                    <button type="button" class="btn btn-secondary" onclick="closeCreateSupplierModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create New Product Modal -->
    <div id="createProductModal" class="modal">
        <div class="modal-content">
            <h2>Add New Product</h2>
            <form method="POST" class="product-form">
                <input type="hidden" name="action" value="create_product">
                
                <div class="form-group">
                    <label for="product_name">Product Name</label>
                    <input type="text" id="product_name" name="product_name" required class="form-control">
                </div>

                <div class="form-group">
                    <label for="product_price">Unit Price ($)</label>
                    <input type="number" id="product_price" name="product_price" required min="0" step="0.01" class="form-control">
                </div>

                <div class="form-group">
                    <label for="alert_quantity">Alert Quantity (Low Stock Warning)</label>
                    <input type="number" id="alert_quantity" name="alert_quantity" required min="0" class="form-control" value="10">
                    <small class="form-text">System will alert when stock falls below this level</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Product</button>
                    <button type="button" class="btn btn-secondary" onclick="closeCreateProductModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>        // Modal functions
        function openCreateOrderModal() {
            document.getElementById('createOrderModal').style.display = 'block';
        }

        function closeCreateOrderModal() {
            document.getElementById('createOrderModal').style.display = 'none';
        }

        function openCreateSupplierModal() {
            document.getElementById('createSupplierModal').style.display = 'block';
        }

        function closeCreateSupplierModal() {
            document.getElementById('createSupplierModal').style.display = 'none';
        }

        function openCreateProductModal() {
            document.getElementById('createProductModal').style.display = 'block';
        }

        function closeCreateProductModal() {
            document.getElementById('createProductModal').style.display = 'none';
        }

        function openUpdateStatusModal() {
            document.getElementById('updateStatusModal').style.display = 'block';
        }

        function closeUpdateStatusModal() {
            document.getElementById('updateStatusModal').style.display = 'none';
        }

        function updateOrderStatus(orderId) {
            document.getElementById('update_order_id').value = orderId;
            
            // Find the current status of the order
            const orderRow = document.querySelector(`td:first-child`).parentElement;
            const currentStatus = orderRow.querySelector('.status-badge').textContent.trim().toLowerCase();
            
            // Set the current status in the modal
            document.getElementById('status').value = currentStatus;
            
            // Show quantity received field if status is delivered
            toggleQuantityReceivedField();
            
            openUpdateStatusModal();
        }

        function deleteOrder(orderId) {
            if (confirm('Are you sure you want to delete this supplier order?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_order">
                    <input type="hidden" name="order_id" value="${orderId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Show/hide quantity received field based on status
        function toggleQuantityReceivedField() {
            const status = document.getElementById('status').value;
            const quantityGroup = document.getElementById('quantityReceivedGroup');
            
            if (status === 'delivered') {
                quantityGroup.style.display = 'block';
                document.getElementById('quantity_received').required = true;
            } else {
                quantityGroup.style.display = 'none';
                document.getElementById('quantity_received').required = false;
            }
        }

        // Add event listener for status change
        document.getElementById('status').addEventListener('change', toggleQuantityReceivedField);

        // Search functionality
        document.getElementById('searchOrder').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');

            rows.forEach(row => {
                if (row.children.length > 1) {
                    const supplierName = row.children[1].textContent.toLowerCase();
                    const productName = row.children[2].textContent.toLowerCase();
                    const orderId = row.children[0].textContent.toLowerCase();
                    
                    const shouldShow = supplierName.includes(searchValue) || 
                                     productName.includes(searchValue) || 
                                     orderId.includes(searchValue);
                    row.style.display = shouldShow ? '' : 'none';
                }
            });
        });

        // Filter by status
        function filterOrders() {
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');

            rows.forEach(row => {
                if (row.children.length > 1) {
                    const status = row.children[9].textContent.toLowerCase();
                    const shouldShow = !statusFilter || status.includes(statusFilter);
                    row.style.display = shouldShow ? '' : 'none';
                }
            });
        }        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('createOrderModal')) {
                closeCreateOrderModal();
            }
            if (event.target == document.getElementById('updateStatusModal')) {
                closeUpdateStatusModal();
            }
            if (event.target == document.getElementById('createSupplierModal')) {
                closeCreateSupplierModal();
            }
            if (event.target == document.getElementById('createProductModal')) {
                closeCreateProductModal();
            }
        }
    </script>

    <style>
        .alert {
            padding: 1rem;
            margin: 1rem auto;
            max-width: 1200px;
            border-radius: 0.375rem;
        }
        
        .alert-success {
            background-color: #d1fae5;
            border: 1px solid #6ee7b7;
            color: #065f46;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }
        
        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 1rem 0;
        }

        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid #e5e7eb;
        }

        .table th {
            background-color: #f8fafc;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .table tr:hover {
            background-color: #f9fafb;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-badge.pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-badge.ordered {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-badge.shipped {
            background-color: #e0e7ff;
            color: #3730a3;
        }

        .status-badge.delivered {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-badge.cancelled {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin: 1rem auto;
            max-width: 1200px;
            overflow-x: auto;
        }

        .card-body {
            padding: 1.5rem;
        }

        .order-filters {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1rem auto;
            max-width: 1200px;
            padding: 1rem;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
        }

        .search-box i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
        }

        .filter-group {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1rem auto;
            max-width: 1200px;
            padding: 1rem;
        }

        .header-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            background: white;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .btn:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }

        .btn-primary {
            background: #1d4ed8;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: #1e40af;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
            border: none;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.5rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #1d4ed8;
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .form-text {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }        .text-center {
            text-align: center;
        }

        .supplier-selection, .product-selection {
            display: flex;
            gap: 0.5rem;
            align-items: flex-end;
        }

        .supplier-selection select, .product-selection select {
            flex: 1;
        }

        .supplier-selection .btn, .product-selection .btn {
            white-space: nowrap;
            margin-bottom: 0;
        }</style>
</body>
</html>
