<?php
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
		if (isset($_GET['user_id'])) {
			getRecord($conn, 'users', ['user_id' => $_GET['user_id']]);
		}
		else {
			getRecord($conn, 'users');
		}
		break;

	case 'POST':
		if (isset($_POST['username'], $_POST['email'], $_POST['password'], $_POST['status'])) {
			//Hash password, set default status
			$hashed_password = password_hash($_POST['password'], PASSWORD_BCRYPT);
			//Create new user
			createRecord($conn, 'users', ['username', 'email', 'password', 'status'], 'ssss', $_POST['username'], $_POST['email'], $hashed_password, $_POST['status']);
		}
		else {
			jsonResponse(false, "Username, email, password, and status are required for creation");
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

