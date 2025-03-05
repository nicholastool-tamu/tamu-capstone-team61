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
			$types = '';

			if (isset($_GET['device_type'])) {
				$conditions[] = "device_type = ?";
				$params[] = $_GET['device_type'];
				$types .= 's';
			}
			if (isset($_GET['user_id'])) {
				$conditions[] = "user_id = ?";
				$params[] = $_GET['user_id'];
				$types .= 'i';
			}

			$query = "SELECT * FROM devices";
			if (count($conditions) > 0) {
				$query .= " WHERE " . implode(" AND ", $conditions);
			}

			$stmt = $conn->prepare($query);
			if ($params) {
				$stmt->bind_param($types, ...$params);
			}
			$stmt->execute();
			$result = $stmt->get_result();
			$devices = $result->fetch_all(MYSQLI_ASSOC);
			jsonResponse(true,"Devices retreived successfully.", $devices);
		}
		break;

	case 'POST':
		if (isset($_POST['action']) && $_POST['action'] === 'update') {
		//Update device status
			if (isset($_POST['device_id'])) {
				$settingsToUpdate = [];
				$types = "";
				$params = [];

				if (isset($_POST['brightness'])) {
					$settingsToUpdate['brightness'] =(int)$_POST['brightness'];
				}
				if (isset($_POST['volume'])) {
					$settingsToUpdate['volume'] = (int)$_POST['volume'];
				}
				if (isset($_POST['temperature'])) {
					$settingsToUpdate['temperature'] = (int)$_POST['temperature'];
				}

				if (!empty($settingsToUpdate)) {
					$jsonQuery = "UPDATE devices SET device_settings = JSON_SET(COALESCE(device_settings, '{}')";
					foreach ($settingsToUpdate as $key => $value) {
						$jsonQuery .= ", '$." . $key . "', ?";
						$types .= "i";
						$params[] = $value;
					}
					$jsonQuery .=") WHERE device_id = ?";
					$types .= "i";
					$params[] = $_POST['device_id'];
					$stmt = $conn->prepare($jsonQuery);
					$stmt->bind_param($types, ...$params);
					if ($stmt->execute()) {
						jsonResponse(true, "Device settings updated successfully.");
					} else {
						jsonResponse(false, "Failed to update device settings.");
					}
					$stmt->close();
				}
				else if (isset($_POST['status'])) {
					updateRecord($conn, 'devices', ['status'], ['device_id'], 'si', $_POST['status'], $_POST['device_id']);
				} else {
					jsonResponse(false, "Device ID and parameter required for updating.");
				}
			} else {
				jsonResponse(false, "Device ID required for updating.");
			}
		}
		else {
			//Create new device
			if (isset($_POST['device_name'], $_POST['device_type'], $_POST['status'], $_POST['user_id'])) {
				$defaultSettings = null;
				switch ($_POST['device_type']) {
					case 'lights':
						$defaultSettings = json_encode(["brightness" => 100]);
						break;
					case 'speaker':
						$defaultSettings = json_encode(["volume" => 50]);
						break;
					case 'thermostat':
						$defaultSettings = json_encode(["temperature" => 72]);
						break;
					default:
						$defaultSettings = null;
				}
				createRecord($conn, 'devices', ['device_name', 'device_type', 'status', 'user_id', 'device_settings'], 'sssis', $_POST['device_name'], $_POST['device_type'], $_POST['status'], $_POST['user_id'], $defaultSettings);
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
		$rawData = file_get_contents("php://input");
		$input = json_decode($rawData, true);
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




