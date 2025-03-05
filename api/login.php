<?php
session_start();
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '/var/www/backend/includes/databaseConnection.php';

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (isset($data['username']) && isset($data['password'])) {
	$username = trim($data['username']);
	$password = $data['password'];

	try {
		$stmt = $conn->prepare("SELECT user_id, password FROM users WHERE username = ?");
		if (!$stmt) {
			throw new Exception("Prepare failed: " . $conn->error);
		}

		$stmt->bind_param("s", $username);
		if (!$stmt->execute()) {
			throw new Exception("Execute failed: " . $stmt->error);
		}

		$result = $stmt->get_result();

		//Check if user exists, verify password
		if ($result->num_rows === 1) {
			$user = $result->fetch_assoc();
			if (password_verify($password, $user['password'])) {
				$_SESSION['logged_in'] = true;
				$_SESSION['username'] = $username;
				$_SESSION['user_id'] = $user['user_id'];
				echo json_encode(['success' => true, 'message' => 'Login Successful']);
				exit();
			}
		}

		echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
	}
	catch (Exception $e) {
		echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
	}
} else {
	echo json_encode(['success' => false, 'message' => 'Username and password required']);
}
?>
