<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once 'config/database.php';

// Database connection
$database = new Database();
$pdo = $database->getConnection();

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Helper functions for database operations
function fetchAll($query, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        throw new Exception("Database query failed: " . $e->getMessage());
    }
}

function fetchOne($query, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        throw new Exception("Database query failed: " . $e->getMessage());
    }
}

function insertAndGetId($query, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        throw new Exception("Database insert failed: " . $e->getMessage());
    }
}

function executeQuery($query, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($query);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        throw new Exception("Database query failed: " . $e->getMessage());
    }
}

// Get request method and endpoint
$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$endpoint = $request[1] ?? '';

// Handle different endpoints
switch ($endpoint) {
    case 'dashboard':
        handleDashboard();
        break;
        
    case 'products':
        handleProducts($method);
        break;
        
    case 'orders':
        handleOrders($method);
        break;
        
    case 'customers':
        handleCustomers($method);
        break;
        
    case 'login':
        handleLogin();
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
}

// Dashboard data
function handleDashboard() {
    try {
        // Get statistics
        $stats = [
            'totalRevenue' => fetchOne("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE status != 'cancelled'")['total'] ?? 0,
            'totalOrders' => fetchOne("SELECT COUNT(*) as count FROM orders")['count'] ?? 0,
            'totalCustomers' => fetchOne("SELECT COUNT(*) as count FROM customers")['count'] ?? 0,
            'pendingOrders' => fetchOne("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'")['count'] ?? 0,
            'averageOrderValue' => fetchOne("SELECT COALESCE(AVG(total_amount), 0) as avg FROM orders WHERE status != 'cancelled'")['avg'] ?? 0
        ];
        
        // Get recent orders
        $recentOrders = fetchAll("
            SELECT o.*, 
                   COALESCE(c.first_name, 'Guest') as first_name, 
                   COALESCE(c.last_name, '') as last_name, 
                   COALESCE(c.email, '') as email 
            FROM orders o 
            LEFT JOIN customers c ON o.customer_id = c.id 
            ORDER BY o.created_at DESC 
            LIMIT 10
        ");
        
        // Get sales data for chart (last 7 days)
        $salesData = fetchAll("
            SELECT DATE(created_at) as date, 
                   COALESCE(SUM(total_amount), 0) as total 
            FROM orders 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
            AND status != 'cancelled'
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        
        echo json_encode([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recentOrders' => $recentOrders,
                'salesData' => $salesData
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Products CRUD
function handleProducts($method) {
    switch ($method) {
        case 'GET':
            getProducts();
            break;
        case 'POST':
            createProduct();
            break;
        case 'PUT':
            updateProduct();
            break;
        case 'DELETE':
            deleteProduct();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function getProducts() {
    try {
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 10;
        $offset = ($page - 1) * $limit;
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        
        $where = "WHERE 1=1";
        $params = [];
        
        if ($search) {
            $where .= " AND (name LIKE ? OR description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($category) {
            $where .= " AND category = ?";
            $params[] = $category;
        }
        
        $total = fetchOne("SELECT COUNT(*) as count FROM products $where", $params)['count'];
        
        $products = fetchAll("
            SELECT p.*,
                   (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id) as variant_count
            FROM products p 
            $where 
            ORDER BY p.created_at DESC 
            LIMIT $limit OFFSET $offset
        ", $params);
        
        // Get variants for each product
        foreach ($products as &$product) {
            if ($product['has_variants']) {
                $product['variants'] = fetchAll(
                    "SELECT * FROM product_variants WHERE product_id = ?",
                    [$product['id']]
                );
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $products,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function createProduct() {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            throw new Exception("Invalid JSON data");
        }
        
        // Validate required fields
        $required = ['name', 'category', 'base_price'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }
        
        $productId = insertAndGetId(
            "INSERT INTO products (name, category, base_price, description, image_url, has_variants, is_active, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['name'],
                $data['category'],
                $data['base_price'],
                $data['description'] ?? '',
                $data['image_url'] ?? '',
                isset($data['variants']) && count($data['variants']) > 0 ? 1 : 0,
                $data['is_active'] ?? 1
            ]
        );
        
        // Insert variants if provided
        if (isset($data['variants']) && is_array($data['variants'])) {
            foreach ($data['variants'] as $variant) {
                insertAndGetId(
                    "INSERT INTO product_variants (product_id, name, price, stock_quantity, created_at) 
                     VALUES (?, ?, ?, ?, NOW())",
                    [
                        $productId,
                        $variant['name'],
                        $variant['price'],
                        $variant['stock_quantity'] ?? 0
                    ]
                );
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Product created successfully',
            'id' => $productId
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function updateProduct() {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['id'])) {
            throw new Exception("Product ID is required");
        }
        
        $productId = $data['id'];
        
        // Update product
        executeQuery(
            "UPDATE products SET 
             name = ?, category = ?, base_price = ?, description = ?, 
             image_url = ?, is_active = ?, updated_at = NOW()
             WHERE id = ?",
            [
                $data['name'],
                $data['category'],
                $data['base_price'],
                $data['description'] ?? '',
                $data['image_url'] ?? '',
                $data['is_active'] ?? 1,
                $productId
            ]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Product updated successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function deleteProduct() {
    try {
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            throw new Exception("Product ID is required");
        }
        
        // Delete variants first
        executeQuery("DELETE FROM product_variants WHERE product_id = ?", [$id]);
        
        // Delete product
        executeQuery("DELETE FROM products WHERE id = ?", [$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Orders CRUD
function handleOrders($method) {
    switch ($method) {
        case 'GET':
            getOrders();
            break;
        case 'POST':
            createOrder();
            break;
        case 'PUT':
            updateOrder();
            break;
        case 'DELETE':
            deleteOrder();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function getOrders() {
    try {
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 10;
        $offset = ($page - 1) * $limit;
        $status = $_GET['status'] ?? '';
        
        $where = "WHERE 1=1";
        $params = [];
        
        if ($status) {
            $where .= " AND o.status = ?";
            $params[] = $status;
        }
        
        $total = fetchOne("SELECT COUNT(*) as count FROM orders o $where", $params)['count'];
        
        $orders = fetchAll("
            SELECT o.*, 
                   COALESCE(c.first_name, 'Guest') as customer_first_name,
                   COALESCE(c.last_name, '') as customer_last_name,
                   COALESCE(c.email, '') as customer_email,
                   COALESCE(c.phone, '') as customer_phone
            FROM orders o 
            LEFT JOIN customers c ON o.customer_id = c.id 
            $where
            ORDER BY o.created_at DESC 
            LIMIT $limit OFFSET $offset
        ", $params);
        
        // Get order items for each order
        foreach ($orders as &$order) {
            $order['items'] = fetchAll(
                "SELECT oi.*, p.name as product_name, p.image_url 
                 FROM order_items oi 
                 JOIN products p ON oi.product_id = p.id 
                 WHERE oi.order_id = ?",
                [$order['id']]
            );
        }
        
        echo json_encode([
            'success' => true,
            'data' => $orders,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function createOrder() {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['items']) || empty($data['items'])) {
            throw new Exception("Order items are required");
        }
        
        // Calculate total
        $total = 0;
        foreach ($data['items'] as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        
        // Create order
        $orderId = insertAndGetId(
            "INSERT INTO orders (customer_id, total_amount, status, delivery_date, delivery_address, special_instructions, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['customer_id'] ?? null,
                $total,
                $data['status'] ?? 'pending',
                $data['delivery_date'] ?? null,
                $data['delivery_address'] ?? '',
                $data['special_instructions'] ?? ''
            ]
        );
        
        // Insert order items
        foreach ($data['items'] as $item) {
            insertAndGetId(
                "INSERT INTO order_items (order_id, product_id, variant_name, quantity, unit_price, total_price) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $orderId,
                    $item['product_id'],
                    $item['variant_name'] ?? '',
                    $item['quantity'],
                    $item['price'],
                    $item['price'] * $item['quantity']
                ]
            );
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Order created successfully',
            'id' => $orderId
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function updateOrder() {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['id'])) {
            throw new Exception("Order ID is required");
        }
        
        executeQuery(
            "UPDATE orders SET 
             status = ?, delivery_date = ?, delivery_address = ?, 
             special_instructions = ?, updated_at = NOW()
             WHERE id = ?",
            [
                $data['status'],
                $data['delivery_date'] ?? null,
                $data['delivery_address'] ?? '',
                $data['special_instructions'] ?? '',
                $data['id']
            ]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Order updated successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function deleteOrder() {
    try {
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            throw new Exception("Order ID is required");
        }
        
        // Delete order items first
        executeQuery("DELETE FROM order_items WHERE order_id = ?", [$id]);
        
        // Delete order
        executeQuery("DELETE FROM orders WHERE id = ?", [$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Order deleted successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Customers CRUD
function handleCustomers($method) {
    switch ($method) {
        case 'GET':
            getCustomers();
            break;
        case 'POST':
            createCustomer();
            break;
        case 'PUT':
            updateCustomer();
            break;
        case 'DELETE':
            deleteCustomer();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function getCustomers() {
    try {
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 10;
        $offset = ($page - 1) * $limit;
        $search = $_GET['search'] ?? '';
        
        $where = "WHERE 1=1";
        $params = [];
        
        if ($search) {
            $where .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $total = fetchOne("SELECT COUNT(*) as count FROM customers $where", $params)['count'];
        
        $customers = fetchAll("
            SELECT c.*,
                   (SELECT COUNT(*) FROM orders WHERE customer_id = c.id) as total_orders,
                   (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE customer_id = c.id AND status != 'cancelled') as total_spent
            FROM customers c 
            $where
            ORDER BY c.created_at DESC 
            LIMIT $limit OFFSET $offset
        ", $params);
        
        echo json_encode([
            'success' => true,
            'data' => $customers,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function createCustomer() {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            throw new Exception("Invalid JSON data");
        }
        
        $required = ['first_name', 'email'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }
        
        // Check if email already exists
        $existing = fetchOne("SELECT id FROM customers WHERE email = ?", [$data['email']]);
        if ($existing) {
            throw new Exception("Email already exists");
        }
        
        $customerId = insertAndGetId(
            "INSERT INTO customers (first_name, last_name, email, phone, address, created_at) 
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                $data['first_name'],
                $data['last_name'] ?? '',
                $data['email'],
                $data['phone'] ?? '',
                $data['address'] ?? ''
            ]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Customer created successfully',
            'id' => $customerId
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function updateCustomer() {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['id'])) {
            throw new Exception("Customer ID is required");
        }
        
        executeQuery(
            "UPDATE customers SET 
             first_name = ?, last_name = ?, email = ?, phone = ?, 
             address = ?, updated_at = NOW()
             WHERE id = ?",
            [
                $data['first_name'],
                $data['last_name'] ?? '',
                $data['email'],
                $data['phone'] ?? '',
                $data['address'] ?? '',
                $data['id']
            ]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Customer updated successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function deleteCustomer() {
    try {
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            throw new Exception("Customer ID is required");
        }
        
        // Check if customer has orders
        $orders = fetchOne("SELECT COUNT(*) as count FROM orders WHERE customer_id = ?", [$id]);
        if ($orders['count'] > 0) {
            throw new Exception("Cannot delete customer with existing orders");
        }
        
        executeQuery("DELETE FROM customers WHERE id = ?", [$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Customer deleted successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Login function
function handleLogin() {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['username']) || !isset($data['password'])) {
            throw new Exception("Username and password are required");
        }
        
        // Simple admin login - in production, use proper authentication
        if ($data['username'] === 'admin' && $data['password'] === 'admin123') {
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => 1,
                    'username' => 'admin',
                    'role' => 'administrator'
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>