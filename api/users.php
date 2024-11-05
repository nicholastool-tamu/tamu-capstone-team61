<?php
header('Content-Type: application/json');

include '../includes/databaseConnection.php';
include '../includes/functions.php';

switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		if (isset($_GET['user_id])) {
			getRecord($conn, 'users', ['user_id' => $_GET['user_id']]);
		}
		else {
			getRecord($conn, 'users');
		}
		break;
	
	case 'POST':
		if (isset($_POST['action']) && $_POST['action'] === 'update') {
			//Update user status
			if (isset($_POST['user_id'], $_POST['status'])) {
				updateRecord($conn, 'users', ['status'], ['user_id'], 'si', $_POST['status'], $_POST['user_id']);
			}
			else {
				jsonResponse(false, "User ID and status required for updating.");
			}
		}
		else {
			//Create a new user
			if (isset($_POST['username'], $_POST['email'],$_POST['password'], $_POST['status'])) {
				//Hash password before storing it
				$hashed_password = password_has($_POST['password'], PASSWORD_BYCRYPT);

				//Create new user
				createRecord($conn, 'users', ['username', 'email', 'password', 'status'], 'ssss', $_POST['username'], $_POST['email'], $hashed_password, $_POST['status']);
			}
			else {
				jsonResponse(false, "Username, email, password, and status are required for creation");
			}
		}
		break;
	case 'DELETE':
		parse_str(file_get_contents("php://input"), $input);
		if (isset($input['user_id'])) {
			deleteRecord($conn, 'users', 'user_id', $input['user_id]);
		}
		else {
			jsonResponse(false, "User ID required for deletion.");
		}
		break;

	default:
		jsonResponse(false, "Invalid request method."):
}
?>

