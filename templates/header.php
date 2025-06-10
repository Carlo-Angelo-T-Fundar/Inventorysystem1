<?php
/**
 * HTML Header Template
 * 
 * Common HTML head section and navigation setup for all pages.
 * Includes authentication checking and admin role verification.
 */
require_once __DIR__ . '/../config/auth.php';

// Verify admin privileges for administrative interface elements
$is_admin = false;
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ? AND username = 'admin'");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $is_admin = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title><?php echo isset($page_title) ? $page_title . " - " : ""; ?>Inventory System</title>
    
    <!-- Core application stylesheets -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/sidebar.css">
    
    <!-- Automatic session timeout functionality -->
    <script src="css/auto-logout.js"></script>
    
    <!-- Navigation highlighting script for current page indication -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get current page name from PHP
            const currentPage = '<?php echo $current_page; ?>';
            
            // Find and highlight current nav link
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href && href.includes(currentPage)) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</head>
<body class="logged-in page-<?php echo $current_page; ?>" data-user-id="<?php echo $_SESSION['user_id']; ?>" data-page="<?php echo $current_page; ?>">    <div class="app-container">
        <!-- sidebar navigation included separately -->

                    <!-- Main Content -->
        <main class="main-content">
            <div class="content-wrapper">
