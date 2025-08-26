<?php
class Order {
    private $conn;
    private $table_name = "orders";
    private $items_table = "order_items";

    public $id;
    public $order_number;
    public $customer_id;
    public $total_amount;
    public $delivery_fee;
    public $status;
    public $delivery_date;
    public $delivery_time;
    public $delivery_address_id;
    public $special_instructions;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create order
    function create() {
    $query = "INSERT INTO orders 
              SET order_number=:order_number, 
                  customer_first_name=:first_name,
                  customer_last_name=:last_name,
                  customer_email=:email,
                  customer_phone=:phone,
                  delivery_address=:address,
                  delivery_date=:delivery_date,
                  delivery_time=:delivery_time,
                  delivery_method=:delivery_method,
                  delivery_fee=:delivery_fee,
                  subtotal=:subtotal,
                  total_amount=:total_amount,
                  special_instructions=:instructions";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->order_number = htmlspecialchars(strip_tags($this->order_number));
        $this->customer_id = htmlspecialchars(strip_tags($this->customer_id));
        $this->total_amount = htmlspecialchars(strip_tags($this->total_amount));
        $this->delivery_fee = htmlspecialchars(strip_tags($this->delivery_fee));
        $this->delivery_date = htmlspecialchars(strip_tags($this->delivery_date));
        $this->delivery_time = htmlspecialchars(strip_tags($this->delivery_time));
        $this->delivery_address_id = htmlspecialchars(strip_tags($this->delivery_address_id));
        $this->special_instructions = htmlspecialchars(strip_tags($this->special_instructions));

        // Bind values
        $stmt->bindParam(":order_number", $this->order_number);
        $stmt->bindParam(":customer_id", $this->customer_id);
        $stmt->bindParam(":total_amount", $this->total_amount);
        $stmt->bindParam(":delivery_fee", $this->delivery_fee);
        $stmt->bindParam(":delivery_date", $this->delivery_date);
        $stmt->bindParam(":delivery_time", $this->delivery_time);
        $stmt->bindParam(":delivery_address_id", $this->delivery_address_id);
        $stmt->bindParam(":special_instructions", $this->special_instructions);

        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    // Add order item
    function addItem($order_id, $product_id, $variant_id, $quantity, $unit_price, $is_custom = false, $custom_details = null) {
        $query = "INSERT INTO " . $this->items_table . "
                  SET order_id=:order_id, product_id=:product_id, 
                      variant_id=:variant_id, quantity=:quantity,
                      unit_price=:unit_price, total_price=:total_price,
                      is_custom=:is_custom, custom_details=:custom_details";

        $stmt = $this->conn->prepare($query);

        $total_price = $quantity * $unit_price;
        $custom_details_json = $custom_details ? json_encode($custom_details) : null;

        $stmt->bindParam(":order_id", $order_id);
        $stmt->bindParam(":product_id", $product_id);
        $stmt->bindParam(":variant_id", $variant_id);
        $stmt->bindParam(":quantity", $quantity);
        $stmt->bindParam(":unit_price", $unit_price);
        $stmt->bindParam(":total_price", $total_price);
        $stmt->bindParam(":is_custom", $is_custom);
        $stmt->bindParam(":custom_details", $custom_details_json);

        return $stmt->execute();
    }

    // Get customer orders
    function getCustomerOrders($customer_id) {
        $query = "SELECT o.*, 
                         GROUP_CONCAT(
                             CONCAT(p.name, ' (', oi.quantity, ')') 
                             SEPARATOR ', '
                         ) as items
                  FROM " . $this->table_name . " o
                  LEFT JOIN " . $this->items_table . " oi ON o.id = oi.order_id
                  LEFT JOIN products p ON oi.product_id = p.id
                  WHERE o.customer_id = ?
                  GROUP BY o.id
                  ORDER BY o.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $customer_id);
        $stmt->execute();

        return $stmt;
    }
}