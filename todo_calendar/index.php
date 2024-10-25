<?php
// index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Calendar To-Do List Web App</title>
    <link rel="stylesheet" href="style.css">
    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <!-- JavaScript libraries -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src="script.js" defer></script>
</head>
<body>
    <h1>Calendar To-Do List Web App</h1>
    <div id='calendar'></div>

    <!-- Modal for Adding Events -->
    <div id="event-modal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeModal()">&times;</span>
            <h2>Add Event</h2>
            <form id="event-form">
                <label for="event-title">Event Title:</label>
                <input type="text" id="event-title" name="title" required>
                <label for="event-date">Event Date:</label>
                <input type="date" id="event-date" name="date" required>
                <button type="submit">Add Event</button>
            </form>
        </div>
    </div>
</body>
</html>
