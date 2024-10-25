<?php
$servername = "localhost";
$username = "mmclean456-dev"; // Replace with your database username
$password = "LAMP"; // Replace with your database password
$dbname = "smarthome_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully to smarthome_db with user $username!";
?>
