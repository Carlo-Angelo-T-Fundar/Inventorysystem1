<?php
require_once 'config/db.php';
require_once 'config/auth.php';

// Check if user is admin
$is_admin = false;
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ? AND username = 'admin'");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $is_admin = true;
}

// Handle delete operation
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM items WHERE id = $id");
    header("Location: index.php");
    exit();
}

// Handle search and filtering
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build query with search
$query = "SELECT * FROM items";
if (!empty($search)) {
    $search = "%$search%";
    $query .= " WHERE name LIKE '$search' OR description LIKE '$search'";
}
$query .= " ORDER BY $sort $order";

// Fetch items
$result = $conn->query($query);

// Page title for header
$page_title = "Inventory List";
include 'templates/header.php';
?>

<div class="content">
    <div class="content-header">
        <h1><i class="fas fa-boxes"></i> Inventory List</h1>
        <div class="actions">
            <a href="add_item.php" class="btn primary-btn">
                <i class="fas fa-plus"></i> Add New Item
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="filters">
                <form action="" method="GET" class="search-form">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Search items..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['description']); ?></td>
                                <td>
                                    <span class="quantity <?php echo $row['quantity'] <= 5 ? 'low-stock' : ''; ?>">
                                        <?php echo $row['quantity']; ?>
                                    </span>
                                </td>
                                <td>$<?php echo number_format($row['price'], 2); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                <td class="actions">
                                    <a href="edit_item.php?id=<?php echo $row['id']; ?>" class="btn edit-btn" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="index.php?delete=<?php echo $row['id']; ?>" 
                                       class="btn delete-btn" 
                                       onclick="return confirm('Are you sure you want to delete this item?')"
                                       title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No items found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
