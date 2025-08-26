<?php
class Customer {
    private $conn;
    private $table_name = "customers";

    public $id;
    public $first_name;
    public $last_name;
    public $email;
    public $phone;
    public $password_hash;
    public $date_of_birth;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create customer
    function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET first_name=:first_name, last_name=:last_name, 
                      email=:email, phone=:phone, password_hash=:password_hash,
                      date_of_birth=:date_of_birth";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->password_hash = password_hash($this->password_hash, PASSWORD_DEFAULT);
        $this->date_of_birth = htmlspecialchars(strip_tags($this->date_of_birth));

        // Bind values
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":password_hash", $this->password_hash);
        $stmt->bindParam(":date_of_birth", $this->date_of_birth);

        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    // Login customer
    function login($email, $password) {
        $query = "SELECT id, first_name, last_name, email, phone, password_hash 
                  FROM " . $this->table_name . " 
                  WHERE email = ? LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $email);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row && password_verify($password, $row['password_hash'])) {
            $this->id = $row['id'];
            $this->first_name = $row['first_name'];
            $this->last_name = $row['last_name'];
            $this->email = $row['email'];
            $this->phone = $row['phone'];
            
            return true;
        }

        return false;
    }

    // Check if email exists
    function emailExists() {
        $query = "SELECT id, first_name, last_name, email, phone 
                  FROM " . $this->table_name . " 
                  WHERE email = ? LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->email);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->id = $row['id'];
            $this->first_name = $row['first_name'];
            $this->last_name = $row['last_name'];
            $this->phone = $row['phone'];
            return true;
        }

        return false;
    }
}