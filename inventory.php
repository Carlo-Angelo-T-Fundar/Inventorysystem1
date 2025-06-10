<?php
/**
 * Inventory Management System
 * 
 * Handles product management including create, read, update, and delete operations.
 * Provides role-based access control with different permissions for different user types.
 * Cashiers have read-only access while admins and store clerks can edit.
 */

require_once 'config/db.php';
require_once 'config/auth.php';

// Check role-based access permissions
requireRole(['admin', 'store_clerk', 'supplier', 'cashier'], $conn);

$current_user_role = getCurrentUserRole($conn);
$is_read_only = ($current_user_role === 'cashier'); // Cashiers have read-only access

/**
 * Retrieve all products from database
 * Ensures quantity_arrived column exists for inventory tracking
 * 
 * @param mysqli $conn Database connection
 * @return array Array of product data
 */
function getAllProducts($conn) {
    // Check for quantity_arrived column existence for backward compatibility
    $check_quantity_arrived = $conn->query("SHOW COLUMNS FROM products LIKE 'quantity_arrived'");
    $has_quantity_arrived = $check_quantity_arrived && $check_quantity_arrived->num_rows > 0;
    
    if (!$has_quantity_arrived) {
        // Add missing column with ALTER TABLE statement
        $conn->query("ALTER TABLE products ADD COLUMN quantity_arrived INT DEFAULT 0 AFTER quantity");
        // Initialize existing products with current quantity values
        $conn->query("UPDATE products SET quantity_arrived = quantity WHERE quantity_arrived = 0 OR quantity_arrived IS NULL");
    }
    
    $sql = "SELECT * FROM products ORDER BY id ASC";
    $result = $conn->query($sql);
    
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Add new product to database
 * 
 * Inserts a new product record with all required fields.
 * Uses prepared statements for SQL injection protection.
 * 
 * @param mysqli $conn Database connection
 * @param string $name Product name
 * @param int $quantity Current stock quantity
 * @param int $alert_quantity Low stock alert threshold
 * @param float $price Product unit price
 * @param int $quantity_arrived Initial quantity received
 * @return bool True if product was added successfully
 * @throws Exception If insertion fails
 */
function addProduct($conn, $name, $quantity, $alert_quantity, $price, $quantity_arrived) {
    try {
        // Use prepared statement for secure database insertion
        $stmt = $conn->prepare("INSERT INTO products (name, quantity, quantity_arrived, alert_quantity, price) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("siiid", $name, $quantity, $quantity_arrived, $alert_quantity, $price);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Add product error: " . $e->getMessage());
        throw $e;
    }
}

// function to delete product from database
// function to reorder product IDs to maintain sequential numbering after deletion
function reorderProductIds($conn) {
    try {
        // Start transaction for reordering
        $conn->autocommit(false);
        
        // Get all products ordered by current ID
        $result = $conn->query("SELECT id FROM products ORDER BY id ASC");
        if (!$result) {
            throw new Exception("Failed to fetch products for reordering");
        }
        
        $products = $result->fetch_all(MYSQLI_ASSOC);
        $new_id = 1;
        
        // Temporarily disable foreign key checks to allow ID updates
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        
        foreach ($products as $product) {
            $old_id = $product['id'];
            if ($old_id != $new_id) {
                // Update product ID
                $stmt = $conn->prepare("UPDATE products SET id = ? WHERE id = ?");
                $stmt->bind_param("ii", $new_id, $old_id);
                $stmt->execute();
                  // Update related tables with new product ID
                // Update product_audit_log if it exists
                $audit_check = $conn->query("SHOW TABLES LIKE 'product_audit_log'");
                if ($audit_check && $audit_check->num_rows > 0) {
                    $audit_stmt = $conn->prepare("UPDATE product_audit_log SET product_id = ? WHERE product_id = ?");
                    if ($audit_stmt) {
                        $audit_stmt->bind_param("ii", $new_id, $old_id);
                        $audit_stmt->execute();
                    }
                }
                
                // Update inventory_transactions if it exists
                $trans_check = $conn->query("SHOW TABLES LIKE 'inventory_transactions'");
                if ($trans_check && $trans_check->num_rows > 0) {
                    $trans_stmt = $conn->prepare("UPDATE inventory_transactions SET product_id = ? WHERE product_id = ?");
                    if ($trans_stmt) {
                        $trans_stmt->bind_param("ii", $new_id, $old_id);
                        $trans_stmt->execute();
                    }
                }
                
                // Update supplier_orders if it exists
                $order_check = $conn->query("SHOW TABLES LIKE 'supplier_orders'");
                if ($order_check && $order_check->num_rows > 0) {
                    $order_stmt = $conn->prepare("UPDATE supplier_orders SET product_id = ? WHERE product_id = ?");
                    if ($order_stmt) {
                        $order_stmt->bind_param("ii", $new_id, $old_id);
                        $order_stmt->execute();
                    }
                }
            }
            $new_id++;
        }
        
        // Reset auto increment to next available number
        $conn->query("ALTER TABLE products AUTO_INCREMENT = $new_id");
        
        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        
        // Commit the transaction
        $conn->commit();
        $conn->autocommit(true);
        
        return true;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $conn->autocommit(true);
        throw new Exception("Failed to reorder product IDs: " . $e->getMessage());
    }
}

/**
 * Delete product from database with related data cleanup
 * 
 * Removes product and all associated records from related tables.
 * Performs cascading deletion within a transaction for data integrity.
 * Automatically reorders remaining product IDs after successful deletion.
 * 
 * @param mysqli $conn Database connection
 * @param int $id Product ID to delete
 * @return bool True if deletion was successful
 * @throws Exception If deletion fails or product not found
 */
function deleteProduct($conn, $id) {
    try {
        // Start transaction to ensure all deletions happen together
        $conn->autocommit(false);
        
        // First, check if product_audit_log table exists and delete any records that reference this product
        $audit_check = $conn->query("SHOW TABLES LIKE 'product_audit_log'");
        if ($audit_check && $audit_check->num_rows > 0) {
            $audit_stmt = $conn->prepare("DELETE FROM product_audit_log WHERE product_id = ?");
            if ($audit_stmt) {
                $audit_stmt->bind_param("i", $id);
                $audit_stmt->execute();
            }
        }
        
        // Also delete from inventory_transactions if it exists
        $trans_check = $conn->query("SHOW TABLES LIKE 'inventory_transactions'");
        if ($trans_check && $trans_check->num_rows > 0) {
            $trans_stmt = $conn->prepare("DELETE FROM inventory_transactions WHERE product_id = ?");
            if ($trans_stmt) {
                $trans_stmt->bind_param("i", $id);
                $trans_stmt->execute();
            }
        }
        
        // Finally, delete the product itself
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
          $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $affected_rows = $stmt->affected_rows;
        
        // Commit the transaction
        $conn->commit();
        $conn->autocommit(true);
        
        // If deletion was successful, reorder the remaining product IDs
        if ($affected_rows > 0) {
            reorderProductIds($conn);
        }
        
        return $affected_rows > 0; // return true if something was deleted
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $conn->autocommit(true);
        error_log("Delete product error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Update existing product information
 * 
 * Modifies product details while preserving original arrival data.
 * Updates name, current quantity, alert threshold, and price.
 * 
 * @param mysqli $conn Database connection
 * @param int $id Product ID to update
 * @param string $name Updated product name
 * @param int $quantity Updated current quantity
 * @param int $alert_quantity Updated alert threshold
 * @param float $price Updated unit price
 * @return bool True if update was successful
 * @throws Exception If update fails
 */
function updateProduct($conn, $id, $name, $quantity, $alert_quantity, $price) {
    try {
        // Update editable fields while preserving arrival data
        $stmt = $conn->prepare("UPDATE products SET name = ?, quantity = ?, alert_quantity = ?, price = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $bindResult = $stmt->bind_param("siidi", $name, $quantity, $alert_quantity, $price, $id);
        if (!$bindResult) {
            throw new Exception("Binding parameters failed: " . $stmt->error);
        }
        
        $executeResult = $stmt->execute();
        if (!$executeResult) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        return true; // success!
    } catch (Exception $e) {
        error_log("Update product error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Retrieve single product by ID
 * 
 * Fetches complete product information for editing or display.
 * Converts numeric fields to appropriate data types.
 * 
 * @param mysqli $conn Database connection
 * @param int $id Product ID to retrieve
 * @return array|null Product data array or null if not found
 * @throws Exception If query fails
 */
function getProduct($conn, $id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Query failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        
        if (!$product) {
            return null; // Product not found
        }
        
        // Convert fields to appropriate data types
        $product['id'] = (int)$product['id'];
        $product['quantity'] = (int)$product['quantity'];
        $product['alert_quantity'] = (int)$product['alert_quantity'];
        $product['price'] = (float)$product['price'];
        
        return $product;
    } catch (Exception $e) {
        error_log("Error in getProduct: " . $e->getMessage());
        throw $e;
    }
}

// Handle AJAX requests for product operations
// Responds with JSON data for asynchronous operations
if (isset($_GET['action'])) {
    header('Content-Type: application/json'); // Set JSON response header
    
    try {
        switch ($_GET['action']) {            case 'get_product':
                // Retrieve single product data for editing
                if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
                    throw new Exception('Invalid product ID');
                }
                $product = getProduct($conn, $_GET['id']);
                if ($product) {
                    echo json_encode($product); // Return product data as JSON
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Product not found']);
                }
                break;
                
            case 'delete':
                // Check user permissions for deletion
                if ($is_read_only) {
                    throw new Exception('You don\'t have permission to delete products.');
                }
                
                if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
                    throw new Exception('Invalid product ID');
                }
                if (deleteProduct($conn, $_GET['id'])) {
                    echo json_encode(['success' => true]);
                } else {
                    throw new Exception('Failed to delete product');
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit; // stop here for AJAX requests
}

// handle form submissions when user adds or edits products
// this processes POST requests from the forms
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // check if user can edit products - only some users can
    if ($is_read_only) {
        $_SESSION['error'] = "You don't have permission to edit inventory.";
        header("Location: inventory.php");
        exit();
    }
    
    try {
        switch ($_POST['action']) {
            case 'add':
                // add new product to database
                $quantity_arrived = isset($_POST['quantity_arrived']) ? (int)$_POST['quantity_arrived'] : (int)$_POST['quantity'];
                if (!addProduct($conn, $_POST['name'], $_POST['quantity'], $_POST['alert_quantity'], $_POST['price'], $quantity_arrived)) {
                    throw new Exception("Failed to add product");
                }
                $success = "Product added successfully"; // show success message
                break;            case 'edit':
                // update existing product
                if (!updateProduct($conn, $_POST['product_id'], $_POST['name'], $_POST['quantity'], $_POST['alert_quantity'], $_POST['price'])) {
                    throw new Exception("Failed to update product");
                }
                $success = "Product updated successfully"; // show success message
                break;
                
            case 'reorder_ids':
                // manually reorder product IDs
                if (reorderProductIds($conn)) {
                    $success = "Product IDs reordered successfully";
                } else {
                    throw new Exception("Failed to reorder product IDs");
                }
                break;
                
            default:
                throw new Exception("Invalid action");
        }
    } catch (Exception $e) {
        $error = $e->getMessage(); // show error message if something goes wrong
    }
}

// get all products for display on the page
$products = getAllProducts($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Inventory System</title>
    <!-- using basic fonts instead of fancy Google fonts -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/sidebar.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- sidebar navigation -->
        <?php require_once 'templates/sidebar.php'; ?>

        <!-- main page content -->
        <main class="main-content">
            <header class="dashboard-header">
                <h1>Inventory Management</h1>
                <div class="header-actions">
                    <!-- search box for finding products -->
                    <div class="search-bar">
                        <input type="text" id="searchInput" placeholder="Search products..." class="search-input">
                        <button type="button" class="search-btn">🔍</button>
                    </div>                    <?php if (!$is_read_only): ?>
                    <!-- add product button - only show if user can edit -->
                    <button class="btn btn-primary" onclick="openAddProductModal()">
                        ➕ Add New Product
                    </button>
                    <!-- reorder IDs button - only show if user can edit -->
                    <button class="btn btn-secondary" onclick="reorderProductIds()" title="Reorder Product IDs">
                        🔄 Reorder IDs
                    </button>
                    <?php endif; ?>
                </div>
            </header>

            <!-- show success or error messages -->
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>            <!-- products table showing all inventory -->
            <div class="card">
                <div class="card-body">
                    <table class="table">
                        <!-- table headers -->
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>Quantity</th>
                                <th>Qty Arrived</th>
                                <th>Alert Quantity</th>
                                <th>Price</th>
                                <th>Date Added</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- loop through all products and show them -->
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['id']); ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['quantity']); ?></td>
                                <td><?php echo htmlspecialchars($product['quantity_arrived'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($product['alert_quantity']); ?></td>
                                <td>$<?php echo number_format($product['price'], 2); ?></td>
                                <td><?php echo date('M j, Y', strtotime($product['created_at'])); ?></td>
                                <td>
                                    <!-- show stock status with color coding -->
                                    <span class="status-badge <?php echo $product['quantity'] <= $product['alert_quantity'] ? 'low-stock' : 'in-stock'; ?>">
                                        <?php echo $product['quantity'] <= $product['alert_quantity'] ? 'Low Stock' : 'In Stock'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!$is_read_only): ?>
                                    <!-- edit and delete buttons - only show if user can edit -->
                                    <button class="btn btn-sm btn-primary" onclick="editProduct(<?php echo $product['id']; ?>)">
                                        ✏️
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                        🗑️
                                    </button>
                                    <?php else: ?>
                                    <span class="text-muted">View Only</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <!-- show message if no products found -->
                            <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="9" class="text-center">No products found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add Product Modal -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <h2>Add New Product</h2>
            <form method="POST" class="product-form">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label for="name">Product Name</label>
                    <input type="text" id="name" name="name" required class="form-control">
                </div>

                <div class="form-group">
                    <label for="quantity">Current Quantity</label>
                    <input type="number" id="quantity" name="quantity" required min="0" class="form-control">
                </div>

                <div class="form-group">
                    <label for="quantity_arrived">Quantity Arrived</label>
                    <input type="number" id="quantity_arrived" name="quantity_arrived" required min="0" class="form-control">
                    <small class="form-text">Initial quantity when product first arrived</small>
                </div>

                <div class="form-group">
                    <label for="alert_quantity">Alert Quantity</label>
                    <input type="number" id="alert_quantity" name="alert_quantity" required min="0" class="form-control">
                </div>

                <div class="form-group">
                    <label for="price">Price</label>
                    <input type="number" id="price" name="price" required min="0" step="0.01" class="form-control">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Product</button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddProductModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editProductModal" class="modal">
        <div class="modal-content">
            <h2>Edit Product</h2>
            <form method="POST" class="product-form" id="editProductForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="product_id" id="edit_product_id">
                
                <div class="form-group">
                    <label for="edit_name">Product Name</label>
                    <input type="text" id="edit_name" name="name" required class="form-control">
                </div>

                <div class="form-group">
                    <label for="edit_quantity">Quantity</label>
                    <input type="number" id="edit_quantity" name="quantity" required min="0" class="form-control">
                </div>

                <div class="form-group">
                    <label for="edit_alert_quantity">Alert Quantity</label>
                    <input type="number" id="edit_alert_quantity" name="alert_quantity" required min="0" class="form-control">
                </div>

                <div class="form-group">
                    <label for="edit_price">Price</label>
                    <input type="number" id="edit_price" name="price" required min="0" step="0.01" class="form-control">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Product</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditProductModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddProductModal() {
            document.getElementById('addProductModal').style.display = 'block';
        }

        function closeAddProductModal() {
            document.getElementById('addProductModal').style.display = 'none';
        }

        function openEditProductModal() {
            document.getElementById('editProductModal').style.display = 'block';
        }

        function closeEditProductModal() {
            document.getElementById('editProductModal').style.display = 'none';
        }

        // Product operations
        async function editProduct(id) {
            try {
                const response = await fetch(`inventory.php?action=get_product&id=${id}`);
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.error || `HTTP error! status: ${response.status}`);
                }
                
                // Populate the edit form
                document.getElementById('edit_product_id').value = data.id;
                document.getElementById('edit_name').value = data.name;
                document.getElementById('edit_quantity').value = data.quantity;
                document.getElementById('edit_alert_quantity').value = data.alert_quantity;
                document.getElementById('edit_price').value = data.price;
                
                // Show the edit modal
                openEditProductModal();
            } catch (error) {
                console.error('Error fetching product details:', error);
                alert('Error loading product details: ' + error.message);
            }
        }

        async function deleteProduct(id) {
            if (confirm('Are you sure you want to delete this product?')) {
                try {
                    const response = await fetch(`inventory.php?action=delete&id=${id}`);
                    const data = await response.json();
                    
                    if (!response.ok) {
                        throw new Error(data.error || 'Failed to delete product');
                    }
                    
                    if (data.success) {
                        window.location.reload();
                    } else {
                        throw new Error('Failed to delete product');
                    }
                } catch (error) {
                    console.error('Error deleting product:', error);
                    alert('Error deleting product: ' + error.message);
                }
            }        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('addProductModal')) {
                closeAddProductModal();
            }
            if (event.target == document.getElementById('editProductModal')) {
                closeEditProductModal();
            }
        }
          // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('tbody tr');

            tableRows.forEach(row => {
                if (row.children.length > 1) { // Check if it's not the "no products found" row
                    const productName = row.children[1].textContent.toLowerCase();
                    const productId = row.children[0].textContent.toLowerCase();
                    const shouldShow = productName.includes(searchValue) || productId.includes(searchValue);
                    row.style.display = shouldShow ? '' : 'none';
                }
            });
        });

        // Auto-fill quantity_arrived when quantity is entered in Add Product modal
        document.getElementById('quantity').addEventListener('input', function() {
            const quantityArrivedField = document.getElementById('quantity_arrived');
            if (!quantityArrivedField.value || quantityArrivedField.value == 0) {
                quantityArrivedField.value = this.value;
            }
        });        // Form validation to ensure quantity_arrived is not greater than current quantity
        document.querySelector('#addProductModal form').addEventListener('submit', function(e) {
            const quantity = parseInt(document.getElementById('quantity').value);
            const quantityArrived = parseInt(document.getElementById('quantity_arrived').value);
            const price = parseFloat(document.getElementById('price').value);
            
            if (quantityArrived > quantity) {
                e.preventDefault();
                alert('Quantity arrived (' + quantityArrived + ') cannot be greater than current quantity (' + quantity + ')');
                return false;
            }
              if (price <= 0) {
                e.preventDefault();
                alert('Price must be greater than 0');
                return false;
            }
        });

        // Function to reorder product IDs
        async function reorderProductIds() {
            if (confirm('Are you sure you want to reorder all product IDs? This will renumber all products sequentially.')) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'reorder_ids');
                    
                    const response = await fetch('inventory.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (response.ok) {
                        window.location.reload();
                    } else {
                        throw new Error('Failed to reorder product IDs');
                    }
                } catch (error) {
                    console.error('Error reordering product IDs:', error);
                    alert('Error reordering product IDs: ' + error.message);
                }
            }
        }
    </script><style>
        /* Additional styles for new columns */
        .table th:nth-child(4), 
        .table td:nth-child(4) {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .form-text {
            font-size: 12px;
            color: #6c757d;
            margin-top: 4px;
        }
        
        /* Style for quantity arrived column */
        .table tbody tr:hover td:nth-child(4) {
            background-color: #f0f0f0;
        }    </style>
    
    <!-- Auto-logout system -->
    <script src="css/auto-logout.js"></script>
    <script>
        // Mark body as logged in for auto-logout detection
        document.body.classList.add('logged-in');
        document.body.setAttribute('data-user-id', '<?php echo $_SESSION['user_id']; ?>');
    </script>
</body>
</html>