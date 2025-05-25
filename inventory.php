<?php
require_once 'config/db.php';
require_once 'config/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    if (isset($_GET['action']) && $_GET['action'] === 'get_product') {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Session expired. Please refresh the page and login again.']);
        exit;
    }
    header("Location: login.php");
    exit();
}

// Function to get all products
function getAllProducts($conn) {
    $sql = "SELECT * FROM products ORDER BY name ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Function to add new product
function addProduct($conn, $name, $quantity, $alert_quantity, $price) {
    $stmt = $conn->prepare("INSERT INTO products (name, quantity, alert_quantity, price) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siid", $name, $quantity, $alert_quantity, $price);
    return $stmt->execute();
}

// Function to update existing product
function deleteProduct($conn, $id) {
    try {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        return $stmt->affected_rows > 0;
    } catch (Exception $e) {
        error_log("Delete product error: " . $e->getMessage());
        throw $e;
    }
}

function updateProduct($conn, $id, $name, $quantity, $alert_quantity, $price) {
    try {
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
        
        return true;
    } catch (Exception $e) {
        error_log("Update product error: " . $e->getMessage());
        throw $e;
    }
}

// Function to get product by ID
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
            return null;
        }
        
        // Convert numeric values to proper types
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

// Handle form submission
// Handle AJAX request for getting product details
if (isset($_GET['action']) && $_GET['action'] === 'get_product' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    try {
        if (!is_numeric($_GET['id'])) {
            throw new Exception('Invalid product ID');
        }
        $product = getProduct($conn, $_GET['id']);
        if ($product) {
            echo json_encode($product);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Handle delete request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    try {
        if (!is_numeric($_GET['id'])) {
            throw new Exception('Invalid product ID');
        }
        if (deleteProduct($conn, $_GET['id'])) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception('Failed to delete product');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = $_POST['name'];
                $quantity = $_POST['quantity'];
                $alert_quantity = $_POST['alert_quantity'];
                $price = $_POST['price'];
                
                if (addProduct($conn, $name, $quantity, $alert_quantity, $price)) {
                    $success = "Product added successfully";
                } else {
                    $error = "Error adding product";
                }
                break;
              case 'edit':
                $id = $_POST['product_id'];
                $name = $_POST['name'];
                $quantity = $_POST['quantity'];
                $alert_quantity = $_POST['alert_quantity'];
                $price = $_POST['price'];
                
                try {
                    if (updateProduct($conn, $id, $name, $quantity, $alert_quantity, $price)) {
                        $success = "Product updated successfully";
                    } else {
                        $error = "Error updating product: " . $conn->error;
                    }
                } catch (Exception $e) {
                    $error = "Error updating product: " . $e->getMessage();
                }
                break;
        }
    }    // Handle AJAX request for getting product details
    if (isset($_GET['action']) && $_GET['action'] === 'get_product' && isset($_GET['id'])) {
        header('Content-Type: application/json');
        
        try {
            if (!is_numeric($_GET['id'])) {
                throw new Exception('Invalid product ID');
            }
            
            $product = getProduct($conn, $_GET['id']);
            if ($product) {
                echo json_encode($product);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Product not found']);
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    
    // Set error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Get all products
$products = getAllProducts($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Inventory System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="admin-profile">
                <div class="admin-info">
                    <h2>ADMIN</h2>
                    <span class="status"><i class="fas fa-circle"></i> Online</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">Dashboard</a>
                <a href="inventory.php" class="nav-item active">Inventory</a>
                <a href="order.php" class="nav-item">Order</a>
                <a href="sales.php" class="nav-item">Sales</a>
                <a href="chart.php" class="nav-item">Chart</a>
            </nav>

            <div class="sidebar-footer">
                <a href="change-password.php" class="change-password">Change Password</a>
                <a href="logout.php" class="logout">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="dashboard-header">
                <h1>Inventory Management</h1>
                <div class="header-actions">
                    <div class="search-bar">
                        <input type="text" id="searchInput" placeholder="Search products..." class="search-input">
                        <button type="button" class="search-btn"><i class="fas fa-search"></i></button>
                    </div>
                    <button class="btn btn-primary" onclick="openAddProductModal()">
                        <i class="fas fa-plus"></i> Add New Product
                    </button>
                </div>
            </header>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Products Table -->
            <div class="card">
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>Quantity</th>
                                <th>Alert Quantity</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['id']); ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['quantity']); ?></td>
                                <td><?php echo htmlspecialchars($product['alert_quantity']); ?></td>
                                <td>$<?php echo number_format($product['price'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $product['quantity'] <= $product['alert_quantity'] ? 'low-stock' : 'in-stock'; ?>">
                                        <?php echo $product['quantity'] <= $product['alert_quantity'] ? 'Low Stock' : 'In Stock'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editProduct(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No products found</td>
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
                    <label for="quantity">Quantity</label>
                    <input type="number" id="quantity" name="quantity" required min="0" class="form-control">
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
    </div>    <!-- Edit Product Modal -->
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
        }        async function editProduct(id) {
            try {
                // Show loading state
                const loadingMessage = 'Loading product details...';
                console.log(loadingMessage);
                
                // Fetch product details
                const response = await fetch(`inventory.php?action=get_product&id=${id}`);
                const contentType = response.headers.get('content-type');
                
                // Check if response is JSON
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Server returned invalid content type. Expected JSON.');
                }
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.error || `HTTP error! status: ${response.status}`);
                }
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                console.log('Product data received:', data);
                
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
                        // Reload the page to show updated list
                        window.location.reload();
                    } else {
                        throw new Error('Failed to delete product');
                    }
                } catch (error) {
                    console.error('Error deleting product:', error);
                    alert('Error deleting product: ' + error.message);
                }
            }
        }

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
                const productName = row.children[1].textContent.toLowerCase();
                row.style.display = productName.includes(searchValue) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
