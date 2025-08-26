<?php
// test_connection.php - Database test for XAMPP
?>
<!DOCTYPE html>
<html>
<head>
    <title>YummyKuantan Database Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #007bff; }
        .section { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; }
    </style>
</head>
<body>

<h2>🧪 YummyKuantan Database Connection Test</h2>

<?php
// Database configuration for XAMPP
$host = "127.0.0.1:3308";  // XAMPP MySQL port
$db_name = "yummykuantan_db";
$username = "root";
$password = "";

echo "<div class='section'>";
echo "<h3>Configuration:</h3>";
echo "<ul>";
echo "<li><strong>Host:</strong> " . $host . "</li>";
echo "<li><strong>Database:</strong> " . $db_name . "</li>";
echo "<li><strong>Username:</strong> " . $username . "</li>";
echo "<li><strong>Password:</strong> " . (empty($password) ? "No password" : "Password set") . "</li>";
echo "<li><strong>PHP Version:</strong> " . phpversion() . "</li>";
echo "</ul>";
echo "</div>";

echo "<div class='section'>";
echo "<h3>Connection Test:</h3>";

try {
    echo "<p class='info'>🔄 Attempting to connect to database...</p>";
    
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p class='success'>✅ <strong>Database connection successful!</strong></p>";
    
    // Test if tables exist
    echo "<h4>📋 Tables Check:</h4>";
    
    $tables = ['products', 'categories', 'orders', 'order_items', 'customers', 'product_variants', 'product_images'];
    
    foreach($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if($stmt->rowCount() > 0) {
                echo "<p class='success'>✅ Table '$table' exists</p>";
                
                // Count records
                $countStmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
                echo "<p class='info' style='margin-left: 20px;'>📊 Records: $count</p>";
                
            } else {
                echo "<p class='error'>❌ Table '$table' not found</p>";
            }
        } catch(Exception $e) {
            echo "<p class='error'>❌ Error checking table '$table': " . $e->getMessage() . "</p>";
        }
    }
    
    // Test sample query
    echo "<h4>🧪 Sample Data Test:</h4>";
    try {
        $stmt = $pdo->query("SELECT * FROM products LIMIT 3");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if(count($products) > 0) {
            echo "<p class='success'>✅ Successfully retrieved sample products:</p>";
            echo "<ul>";
            foreach($products as $product) {
                echo "<li>" . htmlspecialchars($product['name']) . " - RM " . $product['price'] . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class='warning'>⚠️ No products found in database</p>";
            echo "<p class='info'>💡 You may need to import your SQL file</p>";
        }
        
    } catch(Exception $e) {
        echo "<p class='error'>❌ Error retrieving products: " . $e->getMessage() . "</p>";
    }
    
} catch(PDOException $e) {
    echo "<p class='error'>❌ <strong>Database connection failed!</strong></p>";
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
    
    echo "<h4>🔧 Troubleshooting Steps:</h4>";
    echo "<ol>";
    echo "<li>✅ XAMPP is running (I can see this from your screenshot)</li>";
    echo "<li>Check if MySQL is started in XAMPP Control Panel</li>";
    echo "<li>Verify port 3308 is correct (your XAMPP shows MySQL on 3308)</li>";
    echo "<li>Create database 'yummykuantan_db' in phpMyAdmin if it doesn't exist</li>";
    echo "<li>Import your SQL file: <code>yummykuantan_db (1).sql</code></li>";
    echo "<li>Check username/password credentials</li>";
    echo "</ol>";
    
    echo "<h4>📝 Quick Database Setup:</h4>";
    echo "<ol>";
    echo "<li>Open phpMyAdmin: <a href='http://localhost/phpmyadmin' target='_blank'>http://localhost/phpmyadmin</a></li>";
    echo "<li>Create new database named: <code>yummykuantan_db</code></li>";
    echo "<li>Import your SQL file: <code>yummykuantan_db (1).sql</code></li>";
    echo "</ol>";
}
echo "</div>";

// Test API endpoints
echo "<div class='section'>";
echo "<h3>🌐 API Endpoints Test:</h3>";

$baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/';

echo "<p>Testing API endpoints from: <strong>" . $baseUrl . "</strong></p>";
echo "<ul>";
echo "<li>📦 <a href='{$baseUrl}api/products.php?action=list' target='_blank'>Products API</a></li>";
echo "<li>⭐ <a href='{$baseUrl}api/products.php?action=featured' target='_blank'>Featured Products API</a></li>";
echo "<li>🛒 <a href='{$baseUrl}api/orders.php?action=list' target='_blank'>Orders API</a></li>";
echo "</ul>";

echo "<h4>📁 File Structure Check:</h4>";
$requiredFiles = [
    'api/products.php',
    'api/orders.php',
    'index.html',
    'admin.html'
];

foreach($requiredFiles as $file) {
    if(file_exists($file)) {
        echo "<p class='success'>✅ $file exists</p>";
    } else {
        echo "<p class='error'>❌ $file missing</p>";
    }
}

echo "</div>";

echo "<div class='section'>";
echo "<h3>🚀 Next Steps:</h3>";
echo "<ol>";
echo "<li>If database connection failed: Set up database in phpMyAdmin</li>";
echo "<li>If files are missing: Create the API files</li>";
echo "<li>Test your website: <a href='index.html'>index.html</a></li>";
echo "<li>Run API test: <a href='api_test.html'>api_test.html</a></li>";
echo "</ol>";
echo "</div>";

?>

</body>
</html>