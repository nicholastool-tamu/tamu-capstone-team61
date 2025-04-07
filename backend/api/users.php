<?php
ob_start();
header('Content-Type: application/json');

require_once '../includes/databaseConnection.php';
require_once '../includes/functions.php';

//Decode JSON inputs given
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
	$rawData = file_get_contents('php://input');
	$jsonData = json_decode($rawData, true);
	if (is_array($jsonData)) {
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$_POST = array_merge($_POST, $jsonData);
		} else {
			$input = $jsonData;
		}
	}
}

switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		if (isset($_GET['username']) || isset($_GET['email'])) {
            		$conditions = [];
            		$params = [];
            		$types = "";

        		if (isset($_GET['username'])) {
                		$conditions[] = "username = ?";
                		$params[] = $_GET['username'];
                		$types .= "s";
            		}
            		if (isset($_GET['email'])) {
                		$conditions[] = "email = ?";
                		$params[] = $_GET['email'];
                		$types .= "s";
            		}

            		$query = "SELECT * FROM users";
            		if (!empty($conditions)) {
                		$query .= " WHERE " . implode(" OR ", $conditions);
            		}

            		$stmt = $conn->prepare($query);
            		if (!empty($params)) {
                		$stmt->bind_param($types, ...$params);
            		}
            		$stmt->execute();
            		$result = $stmt->get_result();
            		if ($result->num_rows > 0) {
				if (isset($_GET['email'])) {
                        		jsonResponse(false, "Email already exists.", ["exists" => true, "field" => "email"]);
					$stmt->close();
					exit();
				}
				if (isset($_GET['username'])) {
					jsonResponse(false, "Username already exists.", ["exists" => true, "field" => "username"]);
					$stmt->close();
					exit();
				}
            		} else {
                		jsonResponse(true, "Available", ["exists" => false]);
            			$stmt->close();
            			exit();
			}
        	}
		else if (isset($_GET['user_id'])) {
			getRecord($conn, 'users', ['user_id' => $_GET['user_id']]);
		}
		else {
			getRecord($conn, 'users');
		}
		break;

	case 'POST':
		if (isset($_POST['username'], $_POST['email'], $_POST['password'])) {
			//Hash password, set default status
			$hashed_password = password_hash($_POST['password'], PASSWORD_BCRYPT);
			$token = bin2hex(random_bytes(16));
			$status = 'pending';
			$tokenExpiration = date('Y-m-d H:i:s', time() + 3600);
			//Create new user
			$result = createRecord($conn, 'users', ['username', 'email', 'password', 'status', 'verificationToken', 'tokenExpiration'], 'ssssss', $_POST['username'], $_POST['email'], $hashed_password, $status, $token, $tokenExpiration);

			if ($result['success']) {
				if (sendVerificationEmail($_POST['email'], $token)) {
					jsonResponse(true, "Account created. Please check email to verify account.");
				} else {
					jsonResponse(false, "Account created, but failed to send verification email. Please try again later.");
				}
			} else {
				jsonResponse(false, $result['message']);
			}
		} else {
			jsonResponse(false, "Required fields are missing.");
		}
		break;
	case 'PUT':
	//Handle PUT requests to update user data
		if (!isset($input)) {
			parse_str(file_get_contents("php://input"), $input);
		}

		if (isset($input['user_id']) && isset($input['fields']) && isset($input['values']) && isset($input['types'])) {
			updateEntity($conn, 'users', $input['fields'], $input['values'], $input['types'], 'user_id', $input['user_id']);
		}
		else {
			jsonResponse(false, "User ID, fields, values, and types required for updating.");
		}
		break;

	case 'DELETE':
		parse_str(file_get_contents("php://input"), $input);
		if (isset($input['user_id'])) {
			deleteRecord($conn, 'users', 'user_id', $input['user_id']);
		}
		else {
			jsonResponse(false, "User ID required for deletion.");
		}
		break;

	default:
		jsonResponse(false, "Invalid request method.");
}
?>

