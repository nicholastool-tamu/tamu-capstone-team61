<?php
header('Content-Type: application/json');

include '../includes/databaseConnection.php';
include '../includes/functions.php';

$requestMethod = $_SERVER["REQUEST_METHOD'];

//Handling GET request to fetch all users
if ($requestMethod == 'GET') {
	getRecord($conn, 'Users');
}
elseif ($requestMethod == 'POST') {
	//Capture and Sanitize
	$userId = sanitizeInput($_POST['user_id']);
	$username = sanitizeInput($_POST['username']);
	$email = sanitizeInput($_POST['email']);
	$password = sanitizeInput($_POST['password']);
	
	//Need to hash password before saving to database
	$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

	updateRecord($conn, 'Users', ['username', 'email', 'password'], ['id'], "sss", $username, $email, $hashedPassword, $userId);
}
else {
	jsonResponse(false, "Invalid request method.");
}

$conn->close();
?>
