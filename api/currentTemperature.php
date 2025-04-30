<?php
require_once '../includes/functions.php';

$file = __DIR__ . '/current_temperature.json';
if (file_exists($file)) {
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if ($data !== null) {
        header('Content-Type: application/json');
        // Wrap the output in a success response:
        echo json_encode(array_merge(array("success" => true), $data));
        exit();
    } else {
        jsonResponse(false, "Temperature data is invalid.");
        exit();
    }
} else {
    jsonResponse(false, "Temperature data not available.");
    exit();
}
?>

