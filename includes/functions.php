<?php

//Function for cleaning inputs to prevent injection
function cleanInput($data) {
	return htmlspecialchars(stripslashes(trim($data)));
}
function jsonResponse($success, $message, $data = []) {
	header = 'Content-Type: application/json');
	echo json_encode(array(
		'success' => $success,
		'message' => $message,
		'data' => $data
	));
	exit();
}

function getRecord($conn, $table) {
	//Build sql query to select all records
	$query = "SELECT * FROM $table";
	$result = $conn->query(query);
	
	//Check if connection was correct, if so get records
	if ($result) {
		$records = array();
		while ($row = $result->fetch_assoc()) {
			$records[] = $row;
		}
		jsonResponse(true, ucfirst($table) . " retreived successfully.", $records);
	}
	else {
		jsonResponse(false, "Failed to retreive " . $table . ".");
}

function updateRecord($conn, $table, $fields, $conditions, $types, ...$params) {
	//Error handling conditional statements
	//Check if any of the fields are empty, throw error if any fields are empty
	if (empty($fields) || empty($conditions) || (empty($params)) {
		jsonResponse(false, "Missing required fields, conditions, or parameters.");
	}
	
	//Ensure correct ampunt of parameters
	$expectedParamsCount = strlen($types);
	if (count($params) !== $expectedParamsCount) {
		jsonResponse(false, "Number of parameters is incorrect");
	}



	//Prepare fields and conditions for the SQL query
	$fieldSet = implode(' = ?, ' $fields) . ' = ?';
	$conditionSet = implode(' = ? AND ', $conditions) . ' = ?';

	//Build the query and connect
	$query = "UPDATE $table SET $fieldSet WHERE $conditionSet";
	$stmt = $conn->prepare($query);

	//Check if query preparation was successful
	if (!$stmt) {
		jsonResponse(false, "Failed to prepare statement: " . $conn->error);
	}
	//Bind parameters to query
	stmt->bind_param($types, ...$params);

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

?>
