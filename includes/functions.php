<?php

//Function for cleaning inputs to prevent injection
function cleanInput($data) {
	return htmlspecialchars(stripslashes(trim($data)));
}
function jsonResponse($success, $message, $data = []) {
	if (ob_get_length()) {
		ob_clean();
	}
	//Output responses to web server
	header('Content-Type: application/json');
	echo json_encode([
		'success' => $success,
		'message' => $message,
		'data' => $data
	]);
	exit();
}

function enforceSessionCheck() {
	require_once '/var/www/backend/includes/databaseConnection.php';
	$stmt = $conn->prepare("SELECT status FROM users WHERE user_id = ?");
	if (!$stmt) {
		session_destroy();
		header("Location: login_page.php");
		exit();
	}
	$stmt->bind_param("i", $_SESSION['user_id']);
	$stmt->execute();
	$result = $stmt->get_result();
	if ($result->num_rows === 1) {
		$row = $result->fetch_assoc();
		if ($row['status'] !== 'active') {
			session_destroy();
			header("Location: login_page.php");
			exit();
		}
	} else {
		session_destroy();
		header("Location: login_page.php");
		exit();
	}
	$stmt->close();
}

function sendVerificationEmail($userEmail, $token) {
	$subject = "Verify your email for Smart Home";
	$verificationLink = "https://absolutely-vocal-lionfish.ngrok-free.app/api/verifyEmail.php?token=" . urlencode($token);

	$message = "Hello, \n\n";
	$message .= "Thank you for signing up for Smart Home.\n";
	$message .= "Please click the link below to verify your email:\n";
	$message .= $verificationLink . "\n\n";
	$message .= "If you did not sign up for Smart Home, ignore this email.";

	$headers = "From: smarthomecapstone@gmail.com\r\n";
	$headers .= "Reply-To: smarthomecapstone@gmail.com\r\n";
	$headers .= "X-Mailer: PHP/" . phpversion();

	$sent = mail($userEmail, $subject, $message, $headers);
	error_log("sendVerificationEmail: mail() returned " . ($sent ? "true" : "false") . " for email: $userEmail");
	return $sent;
}

function getRecord($conn, $table, $conditions = []) {
	//Build sql query to select all records
	$query = "SELECT * FROM $table";
	$params = [];
	$types = "";

	if (!empty($conditions)) {
		$whereClause = [];
		foreach ($conditions as $key => $value) {
			$whereClause[] = "$key = ?";
			$params[] = $value;
			$types .= is_int($value) ? "i" : "s"; //Determines if type is integer or string dynamically
		}
		$query .= " WHERE " . implode(" AND ", $whereClause);
	}
	
	$stmt = $conn->prepare($query);

	if (!empty($params)) {
		$stmt->bind_param($types, ...$params);
	}

	$stmt->execute();
	$result = $stmt->get_result();

	//Check if connection was correct, if so get records
	if ($result) {
		$records = [];
		while ($row = $result->fetch_assoc()) {
			$records[] = $row;
		}
		jsonResponse(true, ucfirst($table) . " retrieved successfully.", $records);
	}
	else {
		jsonResponse(false, "Failed to retreive " . $table . ".");
	}
	$stmt->close();
}
function updateRecord($conn, $table, $fields, $conditions, $types, ...$params) {
	//Error handling conditional statements
	//Check if any of the fields are empty, throw error if any fields are empty
	if (empty($fields) || empty($conditions) || empty($params)) {
		jsonResponse(false, "Missing required fields, conditions, or parameters.");
	}
	
	//Ensure correct ampunt of parameters
	$expectedParamsCount = strlen($types);
	if (count($params) !== $expectedParamsCount) {
		jsonResponse(false, "Number of parameters is incorrect");
	}



	//Prepare fields and conditions for the SQL query
	$fieldSet = implode(' = ?, ', $fields) . ' = ?';
	$conditionSet = implode(' = ? AND ', $conditions) . ' = ?';

	//Build the query and connect
	$query = "UPDATE $table SET $fieldSet WHERE $conditionSet";
	$stmt = $conn->prepare($query);

	//Check if query preparation was successful
	if (!$stmt) {
		jsonResponse(false, "Failed to prepare statement: " . $conn->error);
	}
	//Bind parameters to query
	$stmt->bind_param($types, ...$params);

	//Execute and check if successful
	if ($stmt->execute()) {
		jsonResponse(true, ucfirst($table) . " updated successfully.");
	}
	else {
		jsonResponse(false, "Failed to update " .$table . ".");
	}
	
	//Close statement to free resources
	$stmt->close();
}

function updateEntity($conn, $table, $fields, $values, $types, $id_field, $id_value) {
	if (empty($fields) || empty($values) || empty($id_value)) {
		jsonResponse(false, "Fields, values and ID value are required.");
	}
	//Converts to arrays
	$fields = is_array($fields) ? $fields : [$fields];
	$values = is_array($values) ? $values : [$values];

	if (count($fields) !== count($values)) {
		jsonresponse(false, "Number of fields and values must match.");
	}

	$fieldSet = implode(' = ?, ', $fields). ' = ?';
	$query = "UPDATE $table SET $fieldSet WHERE $id_field = ?";
	$stmt = $conn->prepare($query);

	if (!$stmt) {
		jsonResponse(false, "Failed to prepare statement: " . $conn->error);
	}

	$allValues = array_merge($values, [$id_value]);
	$allTypes = $types . 'i';
	$stmt->bind_param($allTypes, ...$allValues);

	if($stmt->execute()) {
		jsonResponse(true, ucfirst($table) . "updated successfully.");
	}
	else {
		jsonResponse(false, "Failed to update " . $table . ".");
	}

	$stmt->close();
}

function createRecord($conn, $table, $fields, $types, ...$params) {
	//Error handling for missing inputs
	if(empty($fields) || empty($params)) {
		return ['success' => false, 'message' => "Missing required fields or parameters"];
	}

	//Check for correct amount of parameters
	$expectedParamsCount = strlen($types);
	if(count($params) !== $expectedParamsCount) {
		return ['success' => false, 'message' => "Number of parameters is incorrect."];
	}

	//Prepare for SQL Query
	$fieldList = implode(', ', $fields);
	$placeholders = implode(', ', array_fill(0, count($fields), '?'));

	//Build query and prepare statement
	$query = "INSERT INTO $table ($fieldList) VALUES ($placeholders)";
	$stmt = $conn->prepare($query);

	//Check if query preparation was successful
	if (!$stmt) {
		return ['success' => false, 'message' => "Failed to prepare statement: " . $conn->error];
	}

	//Bind parameters, execute and check if successful
	$stmt->bind_param($types, ...$params);

	if ($stmt->execute()) {
		return ['success' => true, 'message' => ucfirst($table) . " created successfully."];
	}
	else {
		return ['success' => false, 'message' => "Failed to create " . $table . "."];
	}

	//Close statement to free resources
	$stmt->close();
}

function deleteRecord($conn, $table, $id_field, $id_value) {
	//Build query and prepare statement
	$query = "DELETE FROM $table WHERE $id_field = ?";
	$stmt = $conn->prepare($query);

	//Check if preparation was successful
	if (!$stmt) {
		jsonResponse(false, "Failed to prepare statement: " . $conn->error);
	}

	//Bind ID to the query
	$stmt->bind_param("i", $id_value);

	//Execute and check if successful
	if ($stmt->execute()) {
		jsonResponse(true, ucfirst($table) . " deleted successfully.");
	}
	else {
		jsonResponse(false, "Failed to delete " . $table . ".");
	}

	//Close statement to free resources
	$stmt->close();
}
?>
