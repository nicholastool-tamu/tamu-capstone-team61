<?php
// publish.php
require_once '../includes/functions.php';

// Read the raw POST data
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

// Ensure both topic and payload are provided
if (!$data || !isset($data['topic']) || !isset($data['payload'])) {
    jsonResponse(false, "Invalid request. 'topic' and 'payload' are required.");
    exit();
}

$topic = $data['topic'];
$payload = $data['payload'];

// Log for debugging purposes
error_log("Publishing to topic: " . $topic . " with payload: " . $payload);

// Build the command safely using escapeshellarg
$command = "mosquitto_pub -h localhost -t " . escapeshellarg($topic) . " -m " . escapeshellarg($payload);

// Execute the command
exec($command, $output, $return_var);

if ($return_var === 0) {
    jsonResponse(true, "Message published successfully.");
} else {
    jsonResponse(false, "Failed to publish message. Command output: " . implode("\n", $output));
}
?>
