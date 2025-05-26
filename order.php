<?php
require_once 'config/db.php';
require_once 'config/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Function to get all orders with items
function getAllOrders($conn) {
    $sql = "SELECT o.*
            FROM orders o
            ORDER BY o.id ASC";
    $result = $conn->query($sql);
    
    $orders = [];
    if ($result && $result->num_rows > 0) {
        while ($order = $result->fetch_assoc()) {
            // Get items for this order
            $sql = "SELECT oi.*, p.name as product_name
                    FROM order_items oi
                    JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = " . $order['id'];
            $items_result = $conn->query($sql);
            
            $order['items'] = [];
            if ($items_result && $items_result->num_rows > 0) {
                while ($item = $items_result->fetch_assoc()) {
                    $order['items'][] = $item;
                }
            }
            
            $orders[] = $order;
        }
    }
    return $orders;
}

// Handle form submission for updating order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {        case 'update_status':
            try {
                $order_id = $_POST['order_id'];
                $status = $_POST['status'];
                
                // Validate status
                $allowed_statuses = ['pending', 'processing', 'completed', 'cancelled'];
                if (!in_array($status, $allowed_statuses)) {
                    throw new Exception("Invalid status");
                }
                
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $status, $order_id);
                
                if ($stmt->execute()) {
                    $success = "Order status updated successfully";
                } else {
                    throw new Exception("Error updating order status");
                }
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
            break;

        case 'create_order':
            try {
                $conn->begin_transaction();
                
                $total_amount = 0;
                
                // Insert the order first
                $stmt = $conn->prepare("INSERT INTO orders (total_amount, status) VALUES (?, 'pending')");
                $stmt->bind_param("d", $total_amount);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error creating order");
                }
                
                $order_id = $conn->insert_id;
                $items = json_decode($_POST['items'], true);
                $total = 0;

                // Insert order items and update inventory
                foreach ($items as $item) {
                    // Get current product price and stock
                    $stmt = $conn->prepare("SELECT price, quantity FROM products WHERE id = ?");
                    $stmt->bind_param("i", $item['product_id']);
                    $stmt->execute();
                    $product = $stmt->get_result()->fetch_assoc();
                    
                    if ($product['quantity'] < $item['quantity']) {
                        throw new Exception("Insufficient stock for product ID: " . $item['product_id']);
                    }
                    
                    // Insert order item
                    $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $product['price']);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Error creating order item");
                    }
                    
                    // Update product inventory
                    $new_quantity = $product['quantity'] - $item['quantity'];
                    $stmt = $conn->prepare("UPDATE products SET quantity = ? WHERE id = ?");
                    $stmt->bind_param("ii", $new_quantity, $item['product_id']);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Error updating product inventory");
                    }
                    
                    $total += $product['price'] * $item['quantity'];
                }
                
                // Update order total
                $stmt = $conn->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
                $stmt->bind_param("di", $total, $order_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error updating order total");
                }
                
                $conn->commit();
                $success = "Order created successfully";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error: " . $e->getMessage();
            }
            break;
    }
}

// Get all orders
$orders = getAllOrders($conn);

// Get all products for order creation
$products_result = $conn->query("SELECT id, name, quantity, price FROM products WHERE quantity > 0");
$products = $products_result->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>Order Management - Inventory System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">    <link rel="stylesheet" href="css/style.css">
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
        <main class="main-content">
            <div class="content-header">
                <h1>Orders</h1>
                <div class="header-actions">
                    <button class="btn" onclick="exportToExcel()">
                        <i class="fas fa-print"></i> Print Records
                    </button>
                    <button class="btn">
                        <i class="fas fa-filter"></i>
                    </button>
                    <button class="btn" onclick="exportOrders()">Exporting</button>
                    <button class="btn" onclick="importOrders()">Import Orders</button>
                    <button class="btn btn-primary" onclick="openCreateOrderModal()">
                        <i class="fas fa-plus"></i> New Orders
                    </button>
                </div>
            </div>

            <div class="order-filters">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchOrder" placeholder="Search order ID">
                </div>
                
                <div class="filter-group">
                    <button class="btn" onclick="showDatePicker()">
                        <i class="fas fa-calendar"></i>
                    </button>
                    <select id="salesFilter" onchange="filterOrders()">
                        <option value="">Sales</option>
                        <option value="online">Online</option>
                        <option value="store">Store</option>
                    </select>
                    <select id="statusFilter" onchange="filterOrders()">
                        <option value="">Status</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <button class="btn" onclick="showMoreFilters()">
                        <i class="fas fa-sliders-h"></i> Filter
                    </button>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Orders Table -->
            <div class="card">
                <div class="card-body">                    <table class="table">
                <thead>
                            <tr>
                                <th style="text-align: center;"><input type="checkbox" id="selectAll"></th>
                                <th style="text-align: center;">Order ID</th>
                                <th style="text-align: center;">Date</th>
                                <th style="text-align: center;">Customer</th>
                                <th style="text-align: center;">Sales Channel</th>
                                <th style="text-align: center;">Destination</th>
                                <th style="text-align: center;">Items</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><input type="checkbox" class="order-select" value="<?php echo $order['id']; ?>"></td>                                <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                <td><?php echo date('m/d/Y', strtotime($order['created_at'])); ?></td>
                                <td>Customer Order</td>
                                <td><?php echo htmlspecialchars($order['sales_channel'] ?? 'Store name'); ?></td>
                                <td><?php echo htmlspecialchars($order['destination'] ?? 'Lalitpur'); ?></td>
                                <td><?php echo count($order['items']); ?></td>
                                <td><span class="status-badge <?php echo strtolower($order['status'] ?? 'pending'); ?>">
                                        <?php echo ucfirst($order['status'] ?? 'pending'); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="updateOrderStatus(<?php echo $order['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit Status
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No orders found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Order Modal -->
    <div id="createOrderModal" class="modal">
        <div class="modal-content">
            <h2>Create New Order</h2>            <form method="POST" class="order-form" id="createOrderForm">
                <input type="hidden" name="action" value="create_order">
                <input type="hidden" name="items" id="orderItems">
                <div class="form-group">
                    <label for="sales_channel">Sales Channel</label>
                    <select id="sales_channel" name="sales_channel" required class="form-control">
                        <option value="store">Store</option>
                        <option value="online">Online</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="destination">Destination</label>
                    <input type="text" id="destination" name="destination" required class="form-control" value="Lalitpur">
                </div>

                <div class="form-group">
                    <label>Add Products</label>
                    <div class="product-selection">
                        <select id="productSelect" class="form-control">
                            <option value="">Select a product...</option>
                            <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" 
                                    data-price="<?php echo $product['price']; ?>"
                                    data-max="<?php echo $product['quantity']; ?>">
                                <?php echo htmlspecialchars($product['name']); ?> 
                                ($<?php echo number_format($product['price'], 2); ?>) - 
                                <?php echo $product['quantity']; ?> in stock
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" id="quantityInput" min="1" value="1" class="form-control">
                        <button type="button" class="btn btn-primary" onclick="addProductToOrder()">Add</button>
                    </div>
                </div>

                <div class="selected-products">
                    <h3>Selected Products</h3>
                    <div id="selectedProductsList"></div>
                    <div class="order-total">
                        Total: $<span id="orderTotal">0.00</span>
                    </div>
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
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Status</button>
                    <button type="button" class="btn btn-secondary" onclick="closeUpdateStatusModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>    <script>
        let selectedProducts = [];
        let orderTotal = 0;

        // Export to Excel function
        function exportToExcel() {
            const filters = getActiveFilters();
            const queryString = new URLSearchParams(filters).toString();
            window.location.href = 'export_orders.php?' + queryString;
        }

        // Get active filters
        function getActiveFilters() {
            const filters = {};
            const status = document.getElementById('statusFilter').value;
            const sales = document.getElementById('salesFilter').value;
            
            if (status) filters.status = status;
            if (sales) filters.sales = sales;
            
            return filters;
        }

        // Filter orders function
        function filterOrders() {
            const searchValue = document.getElementById('searchOrder').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const salesFilter = document.getElementById('salesFilter').value.toLowerCase();
            
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const orderIdCell = row.cells[1].textContent.toLowerCase();
                const statusCell = row.cells[7].textContent.toLowerCase();
                const salesCell = row.cells[4].textContent.toLowerCase();
                
                const matchesSearch = orderIdCell.includes(searchValue);
                const matchesStatus = !statusFilter || statusCell.includes(statusFilter);
                const matchesSales = !salesFilter || salesCell.includes(salesFilter);
                
                row.style.display = matchesSearch && matchesStatus && matchesSales ? '' : 'none';
            });
        }

        // Select all checkbox functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.order-select');
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        });

        // Search functionality
        document.getElementById('searchOrder').addEventListener('keyup', function() {
            filterOrders();
        });

        function openCreateOrderModal() {
            document.getElementById('createOrderModal').style.display = 'block';
            resetOrderForm();
        }

        function closeCreateOrderModal() {
            document.getElementById('createOrderModal').style.display = 'none';
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
            const orderRow = document.querySelector(`tr input[value="${orderId}"]`).closest('tr');
            const currentStatus = orderRow.querySelector('.status-badge').textContent.trim().toLowerCase();
            
            // Set the current status in the modal
            document.getElementById('status').value = currentStatus;
            
            openUpdateStatusModal();
        }

        function addProductToOrder() {
            const select = document.getElementById('productSelect');
            const quantity = parseInt(document.getElementById('quantityInput').value);
            
            if (select.value && quantity > 0) {
                const option = select.options[select.selectedIndex];
                const productId = parseInt(select.value);
                const maxQuantity = parseInt(option.dataset.max);
                const price = parseFloat(option.dataset.price);
                
                if (quantity > maxQuantity) {
                    alert(`Only ${maxQuantity} items available in stock`);
                    return;
                }
                
                // Check if product is already in order
                const existingProduct = selectedProducts.find(p => p.product_id === productId);
                if (existingProduct) {
                    if (existingProduct.quantity + quantity > maxQuantity) {
                        alert(`Cannot add more items. Only ${maxQuantity} items available in stock`);
                        return;
                    }
                    existingProduct.quantity += quantity;
                } else {
                    selectedProducts.push({
                        product_id: productId,
                        name: option.text.split(' (')[0],
                        quantity: quantity,
                        price: price
                    });
                }
                
                updateSelectedProductsList();
                select.value = '';
                document.getElementById('quantityInput').value = 1;
            }
        }

        function removeProduct(index) {
            selectedProducts.splice(index, 1);
            updateSelectedProductsList();
        }

        function updateSelectedProductsList() {
            const list = document.getElementById('selectedProductsList');
            orderTotal = 0;
            
            let html = '<ul class="selected-products-list">';
            selectedProducts.forEach((product, index) => {
                const subtotal = product.price * product.quantity;
                orderTotal += subtotal;
                html += `
                    <li>
                        ${product.quantity}x ${product.name} - $${product.price} each
                        <span class="subtotal">$${subtotal.toFixed(2)}</span>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeProduct(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    </li>
                `;
            });
            html += '</ul>';
            
            list.innerHTML = html;
            document.getElementById('orderTotal').textContent = orderTotal.toFixed(2);
            document.getElementById('orderItems').value = JSON.stringify(selectedProducts);
        }

        function resetOrderForm() {
            selectedProducts = [];
            orderTotal = 0;
            updateSelectedProductsList();
            document.getElementById('productSelect').value = '';
            document.getElementById('quantityInput').value = 1;
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('createOrderModal')) {
                closeCreateOrderModal();
            }
            if (event.target == document.getElementById('updateStatusModal')) {
                closeUpdateStatusModal();
            }
        }

        // Add these styles to your existing CSS
        document.head.insertAdjacentHTML('beforeend', `
            <style>
                .table {
                    width: 100%;
                    border-collapse: separate;
                    border-spacing: 0;
                    margin: 1rem 0;
                }

                .table th,
                .table td {
                    padding: 1rem;
                    text-align: center;
                    vertical-align: middle;
                }

                .table th {
                    background-color: #f8fafc;
                    font-weight: 600;
                    text-transform: uppercase;
                    font-size: 0.875rem;
                    letter-spacing: 0.05em;
                }

                .table tr {
                    background-color: #ffffff;
                }

                .table tr:hover {
                    background-color: #f9fafb;
                }

                .status-badge {
                    display: inline-block;
                    padding: 0.5rem 1rem;
                    border-radius: 9999px;
                    font-size: 0.875rem;
                    font-weight: 500;
                }

                .status-badge.pending {
                    background-color: #fff3e0;
                    color: #f57c00;
                }

                .status-badge.processing {
                    background-color: #e3f2fd;
                    color: #1976d2;
                }

                .status-badge.completed {
                    background-color: #e8f5e9;
                    color: #2e7d32;
                }

                .status-badge.cancelled {
                    background-color: #ffebee;
                    color: #c62828;
                }

                .card {
                    background: white;
                    border-radius: 0.5rem;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                    margin: 1rem auto;
                    max-width: 1200px;
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
                    max-width: 300px;
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
            </style>
        `);

        function exportOrders() {
            const checkboxes = document.querySelectorAll('.order-select:checked');
            const selectedIds = Array.from(checkboxes).map(cb => cb.value);
            
            if (selectedIds.length === 0) {
                // If no orders are selected, export all filtered orders
                exportToExcel();
            } else {
                // Export only selected orders
                const queryString = new URLSearchParams({
                    ids: selectedIds.join(','),
                    ...getActiveFilters()
                }).toString();
                window.location.href = 'export_orders.php?' + queryString;
            }
        }

        function importOrders() {
            window.location.href = 'import_orders.php';
        }

        // Add filter functionality
        function showDatePicker() {
            const dateFrom = document.createElement('input');
            dateFrom.type = 'date';
            dateFrom.id = 'dateFrom';
            dateFrom.onchange = filterOrders;
            
            const dateTo = document.createElement('input');
            dateTo.type = 'date';
            dateTo.id = 'dateTo';
            dateTo.onchange = filterOrders;
            
            const container = document.querySelector('.filter-group');
            container.insertBefore(dateTo, container.firstChild);
            container.insertBefore(dateFrom, container.firstChild);
        }

        function showMoreFilters() {
            // Implement additional filters here
            const filterModal = document.createElement('div');
            filterModal.className = 'modal';
            filterModal.innerHTML = `
                <div class="modal-content">
                    <h2>Advanced Filters</h2>
                    <div class="form-group">
                        <label>Price Range</label>
                        <div class="range-inputs">
                            <input type="number" placeholder="Min" id="priceMin">
                            <input type="number" placeholder="Max" id="priceMax">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Items Count</label>
                        <div class="range-inputs">
                            <input type="number" placeholder="Min" id="itemsMin">
                            <input type="number" placeholder="Max" id="itemsMax">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" onclick="applyAdvancedFilters()">Apply Filters</button>
                        <button class="btn" onclick="closeAdvancedFilters()">Cancel</button>
                    </div>
                </div>
            `;
            document.body.appendChild(filterModal);
            filterModal.style.display = 'block';
        }

        function applyAdvancedFilters() {
            // Implement advanced filter logic here
            const priceMin = document.getElementById('priceMin').value;
            const priceMax = document.getElementById('priceMax').value;
            const itemsMin = document.getElementById('itemsMin').value;
            const itemsMax = document.getElementById('itemsMax').value;
            
            filterOrders();
            closeAdvancedFilters();
        }

        function closeAdvancedFilters() {
            const modal = document.querySelector('.modal');
            if (modal) {
                modal.remove();
            }
        }

        // Update filter function to include all filters
        function filterOrders() {
            const rows = document.querySelectorAll('tbody tr');
            const filters = getActiveFilters();
            
            rows.forEach(row => {
                const matches = Object.entries(filters).every(([key, value]) => {
                    if (!value) return true;
                    
                    switch(key) {
                        case 'search':
                            return row.cells[1].textContent.toLowerCase().includes(value.toLowerCase());
                        case 'status':
                            return row.cells[7].textContent.toLowerCase() === value.toLowerCase();
                        case 'sales':
                            return row.cells[4].textContent.toLowerCase().includes(value.toLowerCase());
                        case 'dateFrom':
                            const orderDate = new Date(row.cells[2].textContent);
                            return orderDate >= new Date(value);
                        case 'dateTo':
                            const orderDateTo = new Date(row.cells[2].textContent);
                            return orderDateTo <= new Date(value);
                        default:
                            return true;
                    }
                });
                
                row.style.display = matches ? '' : 'none';
            });
        }
    </script>
</body>
</html>
