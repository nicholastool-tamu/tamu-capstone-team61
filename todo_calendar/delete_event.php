<?php
// delete_event.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $title = $_POST['title'];

    if (file_exists('events.json')) {
        $events = json_decode(file_get_contents('events.json'), true);

        // Filter out the event to delete
        $events = array_filter($events, function ($event) use ($title, $date) {
            return !($event['title'] === $title && $event['date'] === $date);
        });

        // Re-index array
        $events = array_values($events);

        // Save events back to the file
        file_put_contents('events.json', json_encode($events, JSON_PRETTY_PRINT));

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No events found']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
