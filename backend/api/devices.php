<?php

require_once '../includes/databaseConnection.php';
require_once '../includes/functions.php';


header('Content-Type: application/json');

// Decode JSON inputs if received
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

switch ($_SERVER['REQUEST_METHOD']) {

    case 'GET':
        // If a user_id is provided, retrieve mapped devices for that user
        if (isset($_GET['user_id'])) {
	    $user_id = $_GET['user_id'];
            $query = "SELECT
                          ud.user_device_id,
                          ud.user_id,
                          hd.hardware_device_id,
                          hd.device_type,
                          hd.device_name,
			  hd.status,
			  hd.device_settings,
			  ud.custom_name
                      FROM user_devices ud
                      JOIN hardware_devices hd ON ud.hardware_device_id = hd.hardware_device_id
                      WHERE ud.user_id = ?";
	    if (isset($_GET['device_type'])) {
	    	$query .= " AND hd.device_type = ?";
	    }
            $stmt = $conn->prepare($query);
            if (isset($_GET['device_type'])) {
		$stmt->bind_param("is", $user_id, $_GET['device_type']);
	    } else {
		$stmt->bind_param("i", $user_id);
	    }
            $stmt->execute();
            $result = $stmt->get_result();
            $devices = $result->fetch_all(MYSQLI_ASSOC);
            jsonResponse(true, "Devices retrieved successfully.", $devices);
            $stmt->close();
        } else if (isset($_GET['device_id'])) {
            getRecord($conn, 'hardware_devices', ['hardware_device_id' => $_GET['device_id']]);
        } else {
            jsonResponse(false, "User ID or Device ID required.");
        }
        break;

    case 'POST':
	//Update device settings
        if (isset($_POST['action']) && $_POST['action'] === 'update') {
            if (isset($_POST['device_id'])) {
		$mapping_id = (int)$_POST['device_id'];

            // Retrieve the hardware_device_id from user_devices.
            	$stmt = $conn->prepare("SELECT hardware_device_id FROM user_devices WHERE user_device_id = ?");
            	$stmt->bind_param("i", $mapping_id);
            	$stmt->execute();
            	$result = $stmt->get_result();
            	if ($row = $result->fetch_assoc()) {
                	$hardware_device_id = $row['hardware_device_id'];
            	} else {
                	jsonResponse(false, "Mapping not found.");
                	exit();
            	}
            	$stmt->close();

                $settingsToUpdate = [];
                $types = "";
                $params = [];
                if (isset($_POST['brightness'])) {
                    $settingsToUpdate['brightness'] = (int)$_POST['brightness'];
                }
                if (isset($_POST['volume'])) {
                    $settingsToUpdate['volume'] = (int)$_POST['volume'];
                }
                if (isset($_POST['temperature'])) {
                    $settingsToUpdate['temperature'] = (int)$_POST['temperature'];
                }
                if (!empty($settingsToUpdate)) {
                    $jsonQuery = "UPDATE hardware_devices SET device_settings = JSON_SET(COALESCE(device_settings, '{}')";
                    foreach ($settingsToUpdate as $key => $value) {
                        $jsonQuery .= ", '$." . $key . "', ?";
                        $types .= "i";
                        $params[] = $value;
                    }
                    $jsonQuery .= ") WHERE hardware_device_id = ?";
                    $types .= "i";
                    $params[] = $hardware_device_id;
                    $stmt = $conn->prepare($jsonQuery);
                    $stmt->bind_param($types, ...$params);
                    if ($stmt->execute()) {
                        jsonResponse(true, "Device settings updated successfully.");
                    } else {
                        jsonResponse(false, "Failed to update device settings.");
                    }
                    $stmt->close();
                } else if (isset($_POST['status'])) {
		    $incoming_status = $_POST['status'];
		    if (strcasecmp($incoming_status, "LIGHT_ON") === 0) {
         		$normalized_status = "on";
    		    } else if (strcasecmp($incoming_status, "LIGHT_OFF") === 0) {
         		$normalized_status = "off";
		    } else if (strcasecmp($incoming_status, "SPEAKER_ON") === 0) {
			$normalized_status = "on";
		    } else if (strcasecmp($incoming_status, "SPEAKER_OFF") === 0) {
			$normalized_status= "off";
    		    } else {
         		$normalized_status = $incoming_status; // Use as-is for other cases.
    		    }
                updateRecord($conn, 'hardware_devices', ['status'], ['hardware_device_id'], 'si', $normalized_status, $hardware_device_id);
                } else {
                    jsonResponse(false, "Device ID and parameter required for updating.");
                }
            } else {
                jsonResponse(false, "Device ID required for updating.");
            }
        }
	//Create new device for user using new mapping tables
        else if (isset($_POST['action']) && $_POST['action'] === 'add_mapping') {
            if (isset($_POST['user_id'], $_POST['device_type'], $_POST['custom_name'])) {
                $user_id = (int)$_POST['user_id'];
                $device_type = $_POST['device_type'];
		$custom_name = $_POST['custom_name'];
                $query = "SELECT COUNT(*) AS available FROM hardware_devices WHERE device_type = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("s", $device_type);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $available = intval($row['available']);
                $stmt->close();

                $query = "SELECT COUNT(*) AS mapped
                          FROM user_devices ud
                          JOIN hardware_devices hd ON ud.hardware_device_id = hd.hardware_device_id
                          WHERE ud.user_id = ? AND hd.device_type = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("is", $user_id, $device_type);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $mapped = intval($row['mapped']);
                $stmt->close();

                if ($mapped >= $available) {
                    jsonResponse(false, "No available hardware devices for type: " . $device_type);
                    exit();
                }

                $query = "SELECT hd.hardware_device_id
                          FROM hardware_devices hd
                          WHERE hd.device_type = ? AND hd.hardware_device_id NOT IN (
                              SELECT hardware_device_id FROM user_devices WHERE user_id = ?
                          )
                          LIMIT 1";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("si", $device_type, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $hardware_device_id = intval($row['hardware_device_id']);
                    $stmt->close();

                    $query = "INSERT INTO user_devices (user_id, hardware_device_id, custom_name) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("iis", $user_id, $hardware_device_id, $custom_name);
                    if ($stmt->execute()) {
                        jsonResponse(true, "Device mapping added successfully.");
                    } else {
                        jsonResponse(false, "Error adding device mapping: " . $conn->error);
                    }
                    $stmt->close();
                } else {
                    jsonResponse(false, "No available hardware device found for type: " . $device_type);
                }
            } else {
                jsonResponse(false, "User ID and Device Type required for mapping.");
            }
        }
        else {
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
            } else {
                jsonResponse(false, "Device name, type, and status required for creation.");
            }
        }
        break;

    case 'PUT':
        if (!isset($input)) {
            parse_str(file_get_contents("php://input"), $input);
        }
        if (isset($input['device_id'], $input['fields'], $input['values'], $input['types'])) {
            updateEntity($conn, 'devices', $input['fields'], $input['values'], $input['types'], 'device_id', $input['device_id']);
        } else {
            jsonResponse(false, "Device ID, fields, values, and types are required for updating.");
        }
        break;

    case 'DELETE':
        $rawData = file_get_contents("php://input");
        $input = json_decode($rawData, true);
	if (isset($input['action']) && $input['action'] === 'delete_mapping') {
            if (isset($input['user_device_id'])) {
                $user_device_id = (int)$input['user_device_id'];
                $query = "DELETE FROM user_devices WHERE user_device_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $user_device_id);
                if ($stmt->execute()) {
                    jsonResponse(true, "Device mapping deleted successfully.");
                } else {
                    jsonResponse(false, "Failed to delete device mapping.");
                }
                $stmt->close();
            } else {
                jsonResponse(false, "User device ID required for deletion.");
            }
        } else if (isset($input['device_id'])) {
            deleteRecord($conn, 'devices', 'device_id', $input['device_id']);
        } else {
            jsonResponse(false, "Device ID required for deletion.");
        }
        break;

    default:
        jsonResponse(false, "Invalid request method.");
}
?>
