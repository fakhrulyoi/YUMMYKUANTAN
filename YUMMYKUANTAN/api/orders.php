<?php
// api/orders.php - Fixed version with better error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers - VERY IMPORTANT
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration - UPDATE THESE TO MATCH YOUR SETUP
$host = "127.0.0.1:3308";  // Change port if needed (3306 is default MySQL)
$db_name = "yummykuantan_db";
$username = "root";
$password = "";  // Add password if you have one

// Test database connection first
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Database connection failed: ' . $e->getMessage(),
        'debug' => [
            'host' => $host,
            'database' => $db_name,
            'user' => $username
        ]
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Log the request for debugging
error_log("Orders API called - Method: $method, Action: $action");

switch($method) {
    case 'POST':
        // Create new order
        $input = file_get_contents('php://input');
        error_log("Order POST data: " . $input);
        
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => 'Invalid JSON data: ' . json_last_error_msg()
            ]);
            break;
        }
        
        if (!$data) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => 'No data received'
            ]);
            break;
        }
        
        // Validate required fields
        if (!isset($data['customer']) || !isset($data['items']) || empty($data['items'])) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => 'Customer information and items are required',
                'received_data' => array_keys($data)
            ]);
            break;
        }
        
        $customer = $data['customer'];
        $items = $data['items'];
        $total = floatval($data['total'] ?? 0);
        $deliveryFee = floatval($data['deliveryFee'] ?? 0);
        $subtotal = $total - $deliveryFee;
        
        try {
            $pdo->beginTransaction();
            
            // Generate order number
            $orderNumber = 'YK' . date('Ymd') . sprintf('%04d', rand(1, 9999));
            
            // Insert order
            $orderQuery = "INSERT INTO orders (
                order_number, customer_first_name, customer_last_name, customer_email, 
                customer_phone, delivery_address, delivery_date, delivery_time, 
                delivery_method, delivery_fee, subtotal, total_amount, special_instructions,
                payment_status, order_status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())";
            
            $orderStmt = $pdo->prepare($orderQuery);
            $success = $orderStmt->execute([
                $orderNumber,
                $customer['firstName'] ?? '',
                $customer['lastName'] ?? '',
                $customer['email'] ?? '',
                $customer['phone'] ?? '',
                $customer['address'] ?? '',
                $customer['deliveryDate'] ?? date('Y-m-d', strtotime('+2 days')),
                $customer['deliveryTime'] ?? 'morning',
                $customer['deliveryMethod'] ?? 'delivery',
                $deliveryFee,
                $subtotal,
                $total,
                $customer['instructions'] ?? ''
            ]);
            
            if (!$success) {
                throw new Exception('Failed to insert order');
            }
            
            $orderId = $pdo->lastInsertId();
            
            // Insert order items
            $itemQuery = "INSERT INTO order_items (
                order_id, product_id, product_name, product_type, variant_name,
                quantity, unit_price, total_price, is_custom, custom_details
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $itemStmt = $pdo->prepare($itemQuery);
            
            foreach($items as $item) {
                $isCustom = isset($item['custom']) && $item['custom'] ? 1 : 0;
                $customDetails = null;
                
                if ($isCustom && isset($item['details'])) {
                    $customDetails = json_encode($item['details']);
                }
                
                $success = $itemStmt->execute([
                    $orderId,
                    $item['id'] ?? 0,
                    $item['name'] ?? 'Unknown Product',
                    $item['type'] ?? null,
                    $item['variant'] ?? null,
                    $item['quantity'] ?? 1,
                    $item['price'] ?? 0,
                    ($item['price'] ?? 0) * ($item['quantity'] ?? 1),
                    $isCustom,
                    $customDetails
                ]);
                
                if (!$success) {
                    throw new Exception('Failed to insert order item: ' . $item['name']);
                }
            }
            
            $pdo->commit();
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Order created successfully',
                'order_id' => intval($orderId),
                'order_number' => $orderNumber,
                'total' => $total
            ]);
            
        } catch(Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode([
                'error' => true,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'GET':
        // Get orders
        switch($action) {
            case 'list':
                try {
                    $query = "SELECT 
                                o.*,
                                COUNT(oi.id) as item_count,
                                GROUP_CONCAT(
                                    CONCAT(oi.product_name, ' (', oi.quantity, ')')
                                    SEPARATOR ', '
                                ) as items_summary
                              FROM orders o
                              LEFT JOIN order_items oi ON o.id = oi.order_id
                              GROUP BY o.id
                              ORDER BY o.created_at DESC
                              LIMIT 50";
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute();
                    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $formattedOrders = [];
                    foreach($orders as $order) {
                        $formattedOrders[] = [
                            'id' => intval($order['id']),
                            'order_number' => $order['order_number'],
                            'customer_name' => $order['customer_first_name'] . ' ' . $order['customer_last_name'],
                            'customer_email' => $order['customer_email'],
                            'customer_phone' => $order['customer_phone'],
                            'total_amount' => floatval($order['total_amount']),
                            'delivery_fee' => floatval($order['delivery_fee']),
                            'delivery_date' => $order['delivery_date'],
                            'delivery_method' => $order['delivery_method'],
                            'payment_status' => $order['payment_status'],
                            'order_status' => $order['order_status'],
                            'item_count' => intval($order['item_count']),
                            'items_summary' => $order['items_summary'],
                            'created_at' => $order['created_at']
                        ];
                    }
                    
                    http_response_code(200);
                    echo json_encode($formattedOrders);
                    
                } catch(PDOException $e) {
                    http_response_code(500);
                    echo json_encode([
                        'error' => true,
                        'message' => 'Failed to fetch orders: ' . $e->getMessage()
                    ]);
                }
                break;
                
            case 'single':
                if (!isset($_GET['id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => true, 'message' => 'Order ID is required']);
                    break;
                }
                
                try {
                    $orderId = intval($_GET['id']);
                    
                    // Get order details
                    $orderQuery = "SELECT * FROM orders WHERE id = ?";
                    $orderStmt = $pdo->prepare($orderQuery);
                    $orderStmt->execute([$orderId]);
                    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$order) {
                        http_response_code(404);
                        echo json_encode(['error' => true, 'message' => 'Order not found']);
                        break;
                    }
                    
                    // Get order items
                    $itemsQuery = "SELECT * FROM order_items WHERE order_id = ?";
                    $itemsStmt = $pdo->prepare($itemsQuery);
                    $itemsStmt->execute([$orderId]);
                    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $formattedItems = [];
                    foreach($items as $item) {
                        $formattedItem = [
                            'id' => intval($item['id']),
                            'product_name' => $item['product_name'],
                            'product_type' => $item['product_type'],
                            'variant_name' => $item['variant_name'],
                            'quantity' => intval($item['quantity']),
                            'unit_price' => floatval($item['unit_price']),
                            'total_price' => floatval($item['total_price']),
                            'is_custom' => boolval($item['is_custom'])
                        ];
                        
                        if ($item['custom_details']) {
                            $formattedItem['custom_details'] = json_decode($item['custom_details'], true);
                        }
                        
                        $formattedItems[] = $formattedItem;
                    }
                    
                    $orderData = [
                        'id' => intval($order['id']),
                        'order_number' => $order['order_number'],
                        'customer' => [
                            'first_name' => $order['customer_first_name'],
                            'last_name' => $order['customer_last_name'],
                            'email' => $order['customer_email'],
                            'phone' => $order['customer_phone']
                        ],
                        'delivery' => [
                            'address' => $order['delivery_address'],
                            'date' => $order['delivery_date'],
                            'time' => $order['delivery_time'],
                            'method' => $order['delivery_method'],
                            'fee' => floatval($order['delivery_fee'])
                        ],
                        'amounts' => [
                            'subtotal' => floatval($order['subtotal']),
                            'delivery_fee' => floatval($order['delivery_fee']),
                            'total' => floatval($order['total_amount'])
                        ],
                        'status' => [
                            'payment' => $order['payment_status'],
                            'order' => $order['order_status']
                        ],
                        'special_instructions' => $order['special_instructions'],
                        'items' => $formattedItems,
                        'created_at' => $order['created_at'],
                        'updated_at' => $order['updated_at']
                    ];
                    
                    http_response_code(200);
                    echo json_encode($orderData);
                    
                } catch(PDOException $e) {
                    http_response_code(500);
                    echo json_encode([
                        'error' => true,
                        'message' => 'Failed to fetch order: ' . $e->getMessage()
                    ]);
                }
                break;
                
            default:
                http_response_code(400);
                echo json_encode([
                    'error' => true,
                    'message' => 'Invalid action. Use ?action=list or ?action=single&id=123'
                ]);
        }
        break;
        
    case 'PUT':
        // Update order status
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($_GET['id']) || !isset($input['status'])) {
            http_response_code(400);
            echo json_encode(['error' => true, 'message' => 'Order ID and status are required']);
            break;
        }
        
        try {
            $orderId = intval($_GET['id']);
            $status = $input['status'];
            $statusType = $input['type'] ?? 'order'; // 'order' or 'payment'
            
            if ($statusType === 'payment') {
                $query = "UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?";
            } else {
                $query = "UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?";
            }
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$status, $orderId]);
            
            if ($stmt->rowCount() > 0) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Order status updated successfully'
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => true, 'message' => 'Order not found']);
            }
            
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'error' => true,
                'message' => 'Failed to update order: ' . $e->getMessage()
            ]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode([
            'error' => true,
            'message' => 'Method not allowed. Use GET, POST, or PUT'
        ]);
}
?>