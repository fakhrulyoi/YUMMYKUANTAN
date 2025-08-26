<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../classes/Customer.php';

$database = new Database();
$db = $database->getConnection();

$customer = new Customer($db);

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'POST':
        $action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
        
        switch($action) {
            case 'register':
                $data = json_decode(file_get_contents("php://input"));

                if(
                    !empty($data->first_name) &&
                    !empty($data->last_name) &&
                    !empty($data->email) &&
                    !empty($data->password)
                ) {
                    $customer->first_name = $data->first_name;
                    $customer->last_name = $data->last_name;
                    $customer->email = $data->email;
                    $customer->phone = isset($data->phone) ? $data->phone : '';
                    $customer->password_hash = $data->password;
                    $customer->date_of_birth = isset($data->date_of_birth) ? $data->date_of_birth : null;

                    // Check if email already exists
                    if($customer->emailExists()) {
                        http_response_code(400);
                        echo json_encode(array("message" => "Email already exists."));
                    } else {
                        if($customer->create()) {
                            http_response_code(201);
                            echo json_encode(array("message" => "Customer was created successfully."));
                        } else {
                            http_response_code(503);
                            echo json_encode(array("message" => "Unable to create customer."));
                        }
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(array("message" => "Unable to create customer. Data is incomplete."));
                }
                break;

            case 'login':
                $data = json_decode(file_get_contents("php://input"));

                if(!empty($data->email) && !empty($data->password)) {
                    if($customer->login($data->email, $data->password)) {
                        http_response_code(200);
                        echo json_encode(array(
                            "message" => "Login successful.",
                            "customer" => array(
                                "id" => $customer->id,
                                "first_name" => $customer->first_name,
                                "last_name" => $customer->last_name,
                                "email" => $customer->email,
                                "phone" => $customer->phone
                            )
                        ));
                    } else {
                        http_response_code(401);
                        echo json_encode(array("message" => "Login failed. Invalid credentials."));
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(array("message" => "Email and password are required."));
                }
                break;

            default:
                http_response_code(400);
                echo json_encode(array("message" => "Invalid action."));
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method not allowed."));
}
?>