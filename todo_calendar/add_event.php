<?php
// add_event.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = htmlspecialchars($_POST['title']);
    $date = $_POST['date'];

    // Load existing events
    $events = [];
    if (file_exists('events.json')) {
        $events = json_decode(file_get_contents('events.json'), true);
    }

    // Add new event
    $events[] = [
        'title' => $title,
        'date' => $date,
    ];

    // Save events back to the file
    file_put_contents('events.json', json_encode($events, JSON_PRETTY_PRINT));

    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
