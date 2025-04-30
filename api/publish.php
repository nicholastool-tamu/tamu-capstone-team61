<?php
require_once '../includes/functions.php';

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data || !isset($data['topic']) || !isset($data['payload'])) {
    jsonResponse(false, "Invalid request. 'topic' and 'payload' are required.");
    exit();
}

$topic = $data['topic'];
$payload = $data['payload'];

error_log("Publishing to topic: " . $topic . " with payload: " . $payload);

$command = "mosquitto_pub -h localhost -t " . escapeshellarg($topic) . " -m " . escapeshellarg($payload);

exec($command, $output, $return_var);

if ($return_var === 0) {
    jsonResponse(true, "Message published successfully.");
} else {
    jsonResponse(false, "Failed to publish message. Command output: " . implode("\n", $output));
}
?>
