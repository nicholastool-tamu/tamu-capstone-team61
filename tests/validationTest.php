<?php

require_once '../includes/databaseConnection.php';
require_once '../includes/functions.php';

header('Content-Type: applciation/json');

//Function to add test results
function addtestCase(&$response, $title, $results) {
	$timestamp = date('Y-m-d H:i:s');
	echo "[$timestamp] Running $title\n"; //Print output with timestamp to compare to jsonResponse 
	$response[] = [
		"test" => $title,
		"results" => $results,
		"timestamp" => $timestamp
	];
	sleep(3); //Pause 3 seconds between tests
}
//Function to simulate HTTP Requests
function simulateHttpRequest($method, $url, $data = []) {
	$curlHandle = curl_init();

	//Configure HTTP method and data
	if ($method === 'POST') {
		curl_setopt($curlHandle, CURLOPT_POST, true);
		curl_setopt($curlHandle, CURLOPT_POSTFIELDS, http_build_query($data));
	}
	elseif ($method === 'GET' && !empty($data)) {
		$url .= '?' . http_build_query($data);
	}
	elseif ($method === 'PUT') {
		curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($curlHandle, CURLOPT_POSTFIELDS, http_build_query($data));
	}
	elseif ($method === 'DELETE' {
		curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($curlHandle, CURLOPT_POSTFIELDS, http_build_query($data));

	//Set URL and options
	curl_setopt($curlHandle, CURLOPT_URL, $url);
	curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true); //Return Response as a string
	curl_setopt($curlHandle, CURLOPT_HEADER, false); //Exclude headers in output
	curl_setopt($curlHandle, CURLOPT_TIMEOUT, 10); //10 second timeout

	//Execute request and get response, then close cURL handle
	$response = curl_exec($curlHandle);
	$httpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE); //Gets the HTTP status code
	curl_close($curlHandle);

	//Return HTTP status code and decoded JSON Response
	return [
		"http_code" => $httpCode, 
		"response" => json_decode($response, true)
	];
}

//Beginning of main execution
$response = [];
$apiUrl = "http://192.168.1.23/api";

//Database Tests
addtestCase($response, "Test 1: Check 'users' table exists", [testCheckUsersTable($conn)]);
addtestCase($response, "Test 2: Check 'devices' table exists", [testCheckDevicesTable($conn)]);
addtestCase($response, "Test 3: Insert a new user", [testInsertUser($conn)]);
addtestCase($response, "Test 4: Insert a duplicate user", [testInsertDuplicateUser($conn)]);
addtestCase($response, "Test 5: Fetch all users", [testFetchAllUsers($conn)]);
addtestCase($response, "Test 6: Update user status", [testUpdateUserStatus($conn)]);
addtestCase($response, "Test 7: Insert a new device", [testInsertDevice($conn)]);
addtestCase($response, "Test 8: Fetch all devices", [testFetchAllDevices($conn)]);
addtestCase($response, "Test 9: Delete user and verify devices", [testDeleteUserAndVerifyDevices($conn)]);

//API Tests
addtestCase($response, "Test 10: Create a new user via API", [testCreateUserApi($apiUrl)]);
addtestCase($response, "Test 11: Fetch all users via API", [testFetchAllUsersApi($apiUrl)]);
addtestCase($response, "Test 12: Fecth single user by ID via API", [testFetchSingleUserApi($apiUrl)]);
addtestCase($response, "Test 13: Create a new device via API", [testCreateDeviceApi($apiUrl)]);
addtestCase($response, "Test 14: Fetch all devices via API", [testFecthAllDevicesApi($apiUrl)]);
addtestCase($response, "Test 15: Update user status via API", [testUpdateUserApi($apiUrl)]);
addtestCase($response, "Test 16: Update device status via API", [testUpdateDeviceApi($apiUrl)]);
addtestCase($response, "Test 17: Handle invalid request via API", [testInvalidRequestApi($apiUrl)]);

//Output results
$conn->close()
echo json_encode(["tests" => $response], JSON_PRETTY_PRINT;

//TEST CASES BEGIN HERE

//Test 1: Check if users table exists
function testCheckUsersTable($conn) {
	$query = "SHOW TABLES LIKE 'users'";
	$result = $conn->query($query);

	return $result && $result->num_rows > 0
		? ["status" => "pass", "message" => "'users' table exists."]
		: ["status" => "fail", "message" => "'users' table does not exist."];
}

//Test 2: Check if devices table exists
function testCheckDevicesTable($conn) {
	$query = "SHOW TABLES LIKE 'devices'";
	$result = $conn->query($query);

	return $result && $result->num_rows > 0
		? ["status" => "pass", "message" => "'devices' table exists."]
		: ["status" => "fail", "message" => "'devices' table does not exist."];
}

//Test 3: Insert a new user
function testInsertUser($conn) {
	$query = "INSERT INTO users (username, email, password, status)
		VALUES ('test_user', 'test@example.com', 'hashed_password', 'active')";

	return $conn->query($query) === TRUE
		? ["status" => "pass", "message" => "New user inserted successfully."]
		: ["status" => "fail", "message" => "Error inserting new user: " . $conn->error];
}

//Test 4: Insert a duplicate user
function testInsertDuplicateUser($conn) {
	$query = "INSERT INTO users (username, email, password, status)
		VALUES ('test_user', 'test@example.com', 'hashed_password', 'active')";

	return $conn->query($query) === TRUE
		? ["status" => "fail", "message" => "Duplicate user inserted, not supposed to happen!"]
		: ["status" => "pass", "message" => "Duplicate user insertion failed as expected." . $conn->error];
}

//Test 5: Fetch all users
function testFetchAllUsers($conn) {
	$query = "SELECT * FROM users";
	$result = $conn->query($query);

	if ($result && $result->num_rows > 0) {
		$users = [];
		while ($row = $result->fetch_assoc()) {
			$users[] = $row;
		}
		return ["status" => "pass", "message" => "Fetched all users successfully.", "data" => $users];
	}
	else {
		return ["status" => "fail", "message" => "No users found."];
	}
}

//Test 6: Update user status
function testUpdateUserStatus($conn) {
	$query = "UPDATE users SET status = 'inactive' WHERE username = 'test_user'";

	return $conn->query($query) === TRUE
		? ["status" => "pass", "message" => "User Status updated successfully."]
		: ["status" => "fail", "message" => "Error updating user status: " . $conn->error];
}

//Test 7: Insert a device
function testInsertDevice($conn) {
	$query = "INSERT INTO devices (device_name, device_type, status, user_id)
		VALUES ('Test Device', 'Light', 'offline', 1)";

	return $conn->query($query) === TRUE
		? ["status" => "pass", "message" => "New device inserted successfully."]
		: ["status" => "fail", "message" => "Error inserting new device: " . $conn->error];
}

//Test 8: Fetch all devices
function testFetchAllDevices($conn) {
	$query = "SELECT * FROM devices";
	$result = $conn->query($query);

	if ($result && $result->num_rows > 0) {
		$devices = [];
		while ($row = $result->fetch_assoc()) {
			$devices[] = $row;
		}
		return ["status" => "pass", "message" => "Fetched all devices successfully.", "data" =>$devices];
	}
	else {
		return ["status" => "fail", "message" => "No devices found."];
	}
}

//Test 9: Delete a user & check effects of doing so
function testDeleteUserAndVerifyDevices($conn) {
	$deleteUserQuery = "DELETE FROM users WHERE username = 'test_user'";
	$result = $conn->query($deleteUserQuery);

	if ($result) {
		$veriifyDevicesQuery = "SELECT * FROM devices WHERE user_id IS NULL";
		$verifyResult = $conn->query($verifyDevicesQuery);

		if ($verifyResult && $verifyResult->num_rows > 0) {
			return ["status" => "pass", "message" => "User deleted and devices updated to NULL as expected."];
		}
		else {
			return ["status" => "fail", "message" => "Devices were not updated as expected after user deletion."];
		}
	}
	else {
		return ["status" => "fail", "message" => "Error deleting user: " . $conn->error];
	}
}

//Test 10: Create User using API
function testCreateUserApi($apiUrl) {
	$data = ['username' => 'api_user', 'email' => 'api_user@example.com', 'password' => 'securepassword'];
	$response = simulateHttpRequest('POST', $apiUrl . '/users.php', $data);

	if ($response['http_code'] === 200 && $response['response']['success']) {
		return ["status" => "pass", "message" => "User created successfully via API.", "api_response" => $response['response']];
	}
	else {
		return ["status" => "fail", "message" => "Failed to create user via API.", "api_response" => $response['response']];
	}
}

//Test 11: Fecth all users via API
function testFetchAllUsersApi($ApiUrl) {
	$response = simulateHttpRequest('GET', $apiUrl . '/users.php');

	if ($response['http_code'] === 200 && $response['response']['success']) {
		return ["status" => "pass", "message" => "Fetched all users via API.", "api_response" => $response['response']];
	}
	else {
		return["status" => "fail", "message" => "Failed to create user via API.", "api_response" => $response['response']];
	}
}

//Test 12: Fetch a single user  via API
function testFetchSingleUserApi($apiUrl, $userId) {
	$data = ['user_id' => $userId];
	$response = simulateHttpRequest('GET', $apiUrl . '/users,php', $data);

	if ($response['http_code'] === 200 && $response['response']['success']) {
		return ["status" => "pass", "message" => "Fetched user details via API", "api_response" => $response['response']];
	}
	else {
		return ["status" => "fail", "message" => "Failed to fetch user details via API.", "api_response" => $response['response']];
	}
}

//Test 13: Create new device using API
function testCreateDeviceApi($apiUrl) {
	$data = ['device_name' => 'Test Light', 'device_type' => 'Light', 'status' => 'off', 'user_id' = 1];
	$response = simulateHttpRequest('POST', $apiUrl . '/devices.php', $data);


	if ($response['http_code'] === 200 && $response['response']['success']) {
		return ["status" => "pass", "message" => "Device created successfully via API.", "api_response" => $response['response']];
	}
	else {
		return ["status" => "fail", "message" => "Failed to create device via API", "api_response" => $response['response']];
	}
}

//Test 14: Fetch all devices via API
function testFetchAllDevicesApi($apiUrl) {
	$response = simulateHttpRequest('GET', $apiUrl . '/devices.php');

	if ($response['http_code'] === 200 && $response['response']['success']) {
		return ["status" => "pass", "message" => "Fetched all devices via API.", "api_response" => $response['response']];
	}
	else {
		return ["status" => "fail", "messgae" => "Failed to fetch devices via PAI.", "api_response" => $response['response']];
	}
}

//Test 15: Update user details via API
function testUpdateUserApi($apiUrl) {
	$data = ['user_id' => 1, 'username' => 'updated_user', 'email' => 'updated_email@example.com', 'status' => 'active']:
	$response = simulateHttpRequest('PUT', $apiUrl . '/users.php', $data);

	if ($response['http_code'] === 200 && $response['response']['success']) {
		return ["status" => "pass", "message" => "User data updated successfully.", "api_response" => $response['response']];
	}
	else {
		return ["status" => "fail", "message" => "Failed to update user data.", "api_response" => $response['response']];
	}
}

//Test 16: Update device details via API
function testUpdateDeviceApi($apiUrl) {
	$data = ['device_id' => 1, 'device_name' => 'Updated Device Name', 'status' => 'on'];
	$response = simulateHttprequest('PUT', $apiUrl . '/devices.php', $data);

	if ($response['http_code'] === 200 && $response['response']['success']) {
		return ["status" => "pass", "message" => "Device updated successfully.", "api_response" => $response['response']];
	}
	else {
		return ["status" => "fail", "message" => "Failed to update device parameters.", "api_response" => $response['response']];
	}
}

//Test 17: Test an invalid request to the api
function testInvalidRequestapi($apiUrl) {
	$data = ['invalid_param' => 'test_value'];
	$response = simulateHttpRequest('POST', $apiUrl . '/devices.php', $data);

	if ($response['http_code'] === 400 || !$response['response']['success']) {
		return["status" => "pass", "message" => "API correctly handled invalid request.", "api_response" => $response['response']];
	}
	else {
		return ["status" => "fail", "message" => "API failed to handle invalid request.", "api_response" => $response['response']];
	}
}
?>
