<?php
session_start();
require_once '/var/www/backend/includes/databaseConnection.php';
require_once '/var/www/backend/includes/functions.php';

if (!isset($_GET['token'])) {
	outputPage("Invalid verification link.", false);
	exit();
}

$token = $_GET['token'];
$stmt = $conn->prepare("SELECT user_id FROM users WHERE verificationToken = ? and STATUS = 'pending' AND tokenExpiration > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
	$row = $result->fetch_assoc();
	$userId = $row['user_id'];
	$updateStmt= $conn->prepare("UPDATE users SET status = 'active', email_verified = 1, verificationToken = NULL, tokenExpiration = NULL WHERE user_id = ?");
	$updateStmt->bind_param("i", $userId);
	if ($updateStmt->execute()) {
		outputPage("Your email has been verified, and you may now log in.", true);
	} else {
		outputPage("Verification failed. Please try again.", false);
	}
	$updateStmt->close();
} else {
	outputPage("Invalid verification link.", false);
}
$stmt->close();

function outputPage($message, $isSuccess = true) {
	?>
	<!DOCTYPE html>
	<html lang="en">
	<head>
		<meta charset="UTF-8" />
		<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
		<meta http-equiv="Pragma" content="no-cache" />
		<meta http-equiv="Expires" content="0" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<title>Email Verification</title>
		<script src="/var/www/html/smarthome_webapp/apiFunctions.js"></script>
	</head>
	<body>
		<script src="/apiFunctions.js"></script>
		<script>
			showNotification(<?php echo json_encode($message); ?>, <?php echo $isSuccess ? 'true' : 'false'; ?>, 10000);
			setTimeout(function() {
				window.location.replace('login_page.php');
			}, 10000);
		</script>
	</body>
	</html>
	<?php
}
?>
