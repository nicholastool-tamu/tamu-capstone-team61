<?php
header('Content-Type: application/json');

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
		parse_str(file_get_contents("php://input"), $input);
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

