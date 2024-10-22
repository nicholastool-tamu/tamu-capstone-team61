<?php
//Include files for connection and basic functions
include '../includes/databaseConnection.php';
include '../includes/functions.php';

//Content type for UI client
header('Content-Type: application/json');

$requestMethod = $_SERVER["REQUEST_METHOD"];

//Handling GET requests
if($requestMethod == 'GET') {
	//Use getRecord from functions.php
	getRecord($conn, 'Devices');
}
//Handling POST requests
elseif ($requestMethod == 'POST' {
	//Capture and sanitize data
	$deviceID = sanitizeInput($_POST['device_id']);
	$status = sanitizeInput($_POST['status']);

	//Use updateRecord from functions.php
	updateRecord($conn, 'Devices', ['status'], ['id'], "si", $status, $deviceID);
}
else {
	//Handle unsupported request methods
	jsonResponse(false, "Invalid request method.");
}

//Close connection after finished
$conn->close();
?>



