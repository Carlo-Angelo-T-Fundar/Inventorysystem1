<?php
// Script to add auto-logout functionality to all authenticated pages
$pages = [
    'sales.php',
    'inventory_transactions.php',
    'user_activity_logs.php',
    'users_crud.php',
    'profile.php',
    'order.php',
    'export_activity_logs.php',
    'chart.php',
    'change-password.php'
];

$autoLogoutScript = '
    <!-- Auto-logout system -->
    <script src="css/auto-logout.js"></script>
    <script>
        // Mark body as logged in for auto-logout detection
        document.body.classList.add(\'logged-in\');
        document.body.setAttribute(\'data-user-id\', \'<?php echo $_SESSION[\'user_id\']; ?>\');
    </script>';

echo "Adding auto-logout functionality to authenticated pages...\n";

foreach ($pages as $page) {
    if (file_exists($page)) {
        $content = file_get_contents($page);
        
        // Check if auto-logout is already added
        if (strpos($content, 'auto-logout.js') !== false) {
            echo "✅ {$page} - Already has auto-logout\n";
            continue;
        }
        
        // Add auto-logout script before closing body tag
        if (strpos($content, '</body>') !== false) {
            $content = str_replace('</body>', $autoLogoutScript . "\n</body>", $content);
            file_put_contents($page, $content);
            echo "✅ {$page} - Auto-logout added\n";
        } else {
            echo "⚠️ {$page} - No closing body tag found\n";
        }
    } else {
        echo "❌ {$page} - File not found\n";
    }
}

echo "\nAuto-logout integration completed!\n";
?>
