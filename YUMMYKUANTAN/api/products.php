<?php
// api/products.php - Fixed for XAMPP with port 3308
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration - XAMPP with port 3308
$host = "127.0.0.1:3308";
$db_name = "yummykuantan_db";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Database connection failed: ' . $e->getMessage(),
        'debug_info' => [
            'host' => $host,
            'database' => $db_name,
            'php_version' => phpversion()
        ]
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch($method) {
    case 'GET':
        switch($action) {
            case 'list':
                try {
                    // Get all products with their images and variants
                    $query = "SELECT 
                                p.id,
                                p.name,
                                p.type,
                                p.price,
                                p.description,
                                p.image as primary_image,
                                p.featured,
                                p.status
                              FROM products p 
                              WHERE p.status = 'active'
                              ORDER BY p.featured DESC, p.name ASC";
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute();
                    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get additional images for each product
                    $imageQuery = "SELECT product_id, image_path FROM product_images ORDER BY sort_order ASC";
                    $imageStmt = $pdo->prepare($imageQuery);
                    $imageStmt->execute();
                    $allImages = $imageStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Group images by product_id
                    $imagesByProduct = [];
                    foreach($allImages as $img) {
                        $imagesByProduct[$img['product_id']][] = $img['image_path'];
                    }
                    
                    // Get variants for each product
                    $variantQuery = "SELECT product_id, name, price FROM product_variants ORDER BY price ASC";
                    $variantStmt = $pdo->prepare($variantQuery);
                    $variantStmt->execute();
                    $allVariants = $variantStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Group variants by product_id
                    $variantsByProduct = [];
                    foreach($allVariants as $variant) {
                        $variantsByProduct[$variant['product_id']][] = [
                            'name' => $variant['name'],
                            'price' => floatval($variant['price'])
                        ];
                    }
                    
                    // Combine data
                    $productsWithDetails = [];
                    foreach($products as $product) {
                        $productId = $product['id'];
                        
                        // Start with primary image, then add additional images
                        $images = [];
                        if($product['primary_image']) {
                            $images[] = $product['primary_image'];
                        }
                        if(isset($imagesByProduct[$productId])) {
                            $images = array_merge($images, $imagesByProduct[$productId]);
                        }
                        
                        $productsWithDetails[] = [
                            'id' => intval($product['id']),
                            'name' => $product['name'],
                            'type' => $product['type'],
                            'price' => floatval($product['price']),
                            'description' => $product['description'],
                            'images' => array_unique($images), // Remove duplicates
                            'variants' => isset($variantsByProduct[$productId]) ? $variantsByProduct[$productId] : [],
                            'featured' => boolval($product['featured'])
                        ];
                    }
                    
                    http_response_code(200);
                    echo json_encode($productsWithDetails);
                    
                } catch(PDOException $e) {
                    http_response_code(500);
                    echo json_encode([
                        'error' => true,
                        'message' => 'Failed to fetch products: ' . $e->getMessage()
                    ]);
                }
                break;
                
            case 'single':
                if(!isset($_GET['id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => true, 'message' => 'Product ID is required']);
                    break;
                }
                
                try {
                    $productId = intval($_GET['id']);
                    
                    // Get product details
                    $query = "SELECT * FROM products WHERE id = ? AND status = 'active'";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$productId]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if(!$product) {
                        http_response_code(404);
                        echo json_encode(['error' => true, 'message' => 'Product not found']);
                        break;
                    }
                    
                    // Get product images
                    $imageQuery = "SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC";
                    $imageStmt = $pdo->prepare($imageQuery);
                    $imageStmt->execute([$productId]);
                    $images = $imageStmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Add primary image if not in images
                    if($product['image'] && !in_array($product['image'], $images)) {
                        array_unshift($images, $product['image']);
                    }
                    
                    // Get product variants
                    $variantQuery = "SELECT name, price FROM product_variants WHERE product_id = ? ORDER BY price ASC";
                    $variantStmt = $pdo->prepare($variantQuery);
                    $variantStmt->execute([$productId]);
                    $variants = $variantStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Format variants
                    $formattedVariants = [];
                    foreach($variants as $variant) {
                        $formattedVariants[] = [
                            'name' => $variant['name'],
                            'price' => floatval($variant['price'])
                        ];
                    }
                    
                    $productData = [
                        'id' => intval($product['id']),
                        'name' => $product['name'],
                        'type' => $product['type'],
                        'price' => floatval($product['price']),
                        'description' => $product['description'],
                        'images' => $images,
                        'variants' => $formattedVariants,
                        'featured' => boolval($product['featured'])
                    ];
                    
                    http_response_code(200);
                    echo json_encode($productData);
                    
                } catch(PDOException $e) {
                    http_response_code(500);
                    echo json_encode([
                        'error' => true,
                        'message' => 'Failed to fetch product: ' . $e->getMessage()
                    ]);
                }
                break;
                
            case 'featured':
                try {
                    $query = "SELECT * FROM products WHERE status = 'active' AND featured = 1 ORDER BY name ASC LIMIT 6";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute();
                    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $featuredProducts = [];
                    foreach($products as $product) {
                        $featuredProducts[] = [
                            'id' => intval($product['id']),
                            'name' => $product['name'],
                            'type' => $product['type'],
                            'price' => floatval($product['price']),
                            'description' => $product['description'],
                            'images' => [$product['image']],
                            'featured' => true
                        ];
                    }
                    
                    http_response_code(200);
                    echo json_encode($featuredProducts);
                    
                } catch(PDOException $e) {
                    http_response_code(500);
                    echo json_encode([
                        'error' => true,
                        'message' => 'Failed to fetch featured products: ' . $e->getMessage()
                    ]);
                }
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'Invalid action']);
        }
        break;
        
    case 'POST':
        // Handle product creation (admin only)
        $input = json_decode(file_get_contents('php://input'), true);
        
        if(!$input) {
            http_response_code(400);
            echo json_encode(['error' => true, 'message' => 'Invalid JSON data']);
            break;
        }
        
        // Validate required fields
        $required = ['name', 'type', 'price', 'description'];
        foreach($required as $field) {
            if(!isset($input[$field]) || empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => "Field '$field' is required"]);
                exit();
            }
        }
        
        try {
            $query = "INSERT INTO products (name, type, price, description, image, featured, status) 
                      VALUES (?, ?, ?, ?, ?, ?, 'active')";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                $input['name'],
                $input['type'],
                floatval($input['price']),
                $input['description'],
                isset($input['image']) ? $input['image'] : null,
                isset($input['featured']) ? intval($input['featured']) : 0
            ]);
            
            $productId = $pdo->lastInsertId();
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Product created successfully',
                'product_id' => intval($productId)
            ]);
            
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'error' => true,
                'message' => 'Failed to create product: ' . $e->getMessage()
            ]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => true, 'message' => 'Method not allowed']);
}
?>