<?php
//Include files for connection and basic functions
require_once '../includes/databaseConnection.php';
require_once '../includes/functions.php';
//Content type for UI client
header('Content-Type: application/json');

//Decode JSON inputs if received
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
//Main Switch Case to handle request method
switch ($_SERVER['REQUEST_METHOD']) {
	case 'GET':
		//Check if a specific device is being requested, otherwise give data for all devices
		if (isset($_GET['device_id'])) {
			getRecord($conn, 'devices', ['device_id' => $_GET['device_id']]);
		}
		else {
			$conditions = [];
			$params = [];

			if (isset($_GET['device_type'])) {
				$conditions[] = "device_type = ?";
				$params[] = $_GET['device_type'];
			}
			if (isset($_GET['user_id'])) {
				$conditions[] = "user_id = ?";
				$params[] = $_GET['user_id'];
			}

			$query = "SELECT * FROM devices";
			if (count($conditions) > 0) {
				$query .= " WHERE " . implode(" AND ", $conditions);
			}

			$stmt = $conn->prepare($query);
			if ($params) {
				$stmt->bind_param(str_repeat('s', count($params)), ...$params); //NEED TO CHANGE FOR INTS
			}
			$stmt->execute();
			$result = $stmt->get_result();
			$devices = $result->fetch_all(MYSQLI_ASSOC);
			jsonResponse(true, $devices);
		}
		break;

	case 'POST':
		if (isset($_POST['action']) && $_POST['action'] === 'update') {
		//Update device status
			if (isset($_POST['device_id'], $_POST['status'])) {
				updateRecord($conn, 'devices', ['status'], ['device_id'], 'si', $_POST['status'], $_POST['device_id']);
			}
			else {
				jsonResponse(false, "Device ID and status required for updating.");
			}
		}
		else {
			//Create new device
			if (isset($_POST['device_name'], $_POST['device_type'], $_POST['status'], $_POST['user_id'])) {
				createRecord($conn, 'devices', ['device_name', 'device_type', 'status', 'user_id'], 'sssi', $_POST['device_name'], $_POST['device_type'], $_POST['status'], $_POST['user_id']);
			}
			else {
				jsonResponse(false, "Device name, type, and status required for creation.");
			}
		}
		break;

	case 'PUT':
		//Handle PUT requests for updating devices
		if (!isset($input)) {
			parse_str(file_get_contents("php://input"), $input);
		}
		if (isset($input['device_id'], $input['fields'], $input['values'], $input['types'])) {
			updateEntity($conn, 'devices', $input['fields'], $input['values'], $input['types'],'device_id',  $input['device_id']);
		}
		else {
			jsonResponse(false, "Device ID, fields, values, and types are required for updating.");
		}
		break;

	case 'DELETE':
		parse_str(file_get_contents("php://input"), $input);
		if (isset($input['device_id'])) {
			deleteRecord($conn, 'devices', 'device_id', $input['device_id']);
		}
		else {
			jsonResponse(false, "Device ID required for deletion.");
		}
		break;

	default:
		jsonResponse(false, "Invalid request method.");
}
?>




