<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	http_response_code(200);
	exit();
}


require_once '../includes/databaseConnection.php';
require_once '../includes/functions.php';

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
		if (isset($_POST['username'],  $_POST['email'], $_POST['password'])) {
			//Hash password, set default status
			$hashed_password = password_hash($_POST['password'], PASSWORD_BCRYPT);
			$status = isset($_POST['status']) ? $_POST['status'] : 'active';

			//Create new user
			createRecord($conn, 'users', ['username', 'email', 'password', 'status'], 'ssss', $_POST['username'], $_POST['email'], $hashed_password, $status);
		}
		else {
			jsonResponse(false, "Username, email, password, and status are required for creation");
		}
		break;
	case 'PUT':
	//Handle PUT requests to update user data
		parse_str(file_get_contents('php://input'), $putData);

		if (isset($putData['user_id']) && isset($putData['fields']) && isset($putData['values']) && isset($putData['types'])) {
			updateEntity($conn, 'users', $putData['fields'], $putData['values'], $putData['types'], 'user_id', $putData['user_id']);
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

