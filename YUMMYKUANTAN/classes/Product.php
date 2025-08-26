<?php
class Product {
    private $conn;
    private $table_name = "products";
    private $variants_table = "product_variants";

    public $id;
    public $name;
    public $category;
    public $base_price;
    public $description;
    public $image_url;
    public $is_active;
    public $has_variants;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Read all products
   function readAll() {
    $query = "SELECT p.*, 
                     GROUP_CONCAT(DISTINCT pi.image_path ORDER BY pi.is_primary DESC, pi.sort_order ASC SEPARATOR '|') as images,
                     GROUP_CONCAT(DISTINCT CONCAT(pv.name, ':', pv.price) ORDER BY pv.price ASC SEPARATOR '|') as variants
              FROM products p
              LEFT JOIN product_images pi ON p.id = pi.product_id
              LEFT JOIN product_variants pv ON p.id = pv.product_id
              WHERE p.status = 'active'
              GROUP BY p.id
              ORDER BY p.name ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    // Read single product
    function readOne() {
        $query = "SELECT p.*, 
                         GROUP_CONCAT(
                             CONCAT(pv.size, ':', pv.price) 
                             ORDER BY pv.price ASC 
                             SEPARATOR '|'
                         ) as variants
                  FROM " . $this->table_name . " p
                  LEFT JOIN " . $this->variants_table . " pv ON p.id = pv.product_id
                  WHERE p.id = ? AND p.is_active = 1
                  GROUP BY p.id
                  LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->name = $row['name'];
            $this->category = $row['category'];
            $this->base_price = $row['base_price'];
            $this->description = $row['description'];
            $this->image_url = $row['image_url'];
            $this->is_active = $row['is_active'];
            $this->has_variants = $row['has_variants'];
            
            return $row;
        }
        
        return false;
    }

    // Create product
    function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET name=:name, category=:category, base_price=:base_price, 
                      description=:description, image_url=:image_url, 
                      has_variants=:has_variants";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->category = htmlspecialchars(strip_tags($this->category));
        $this->base_price = htmlspecialchars(strip_tags($this->base_price));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->image_url = htmlspecialchars(strip_tags($this->image_url));

        // Bind values
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":base_price", $this->base_price);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":image_url", $this->image_url);
        $stmt->bindParam(":has_variants", $this->has_variants);

        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    // Search products
    function search($keywords) {
        $query = "SELECT p.*, 
                         GROUP_CONCAT(
                             CONCAT(pv.size, ':', pv.price) 
                             ORDER BY pv.price ASC 
                             SEPARATOR '|'
                         ) as variants
                  FROM " . $this->table_name . " p
                  LEFT JOIN " . $this->variants_table . " pv ON p.id = pv.product_id
                  WHERE p.is_active = 1 AND (p.name LIKE ? OR p.description LIKE ?)
                  GROUP BY p.id
                  ORDER BY p.name ASC";

        $stmt = $this->conn->prepare($query);

        $keywords = htmlspecialchars(strip_tags($keywords));
        $keywords = "%{$keywords}%";

        $stmt->bindParam(1, $keywords);
        $stmt->bindParam(2, $keywords);

        $stmt->execute();

        return $stmt;
    }
}