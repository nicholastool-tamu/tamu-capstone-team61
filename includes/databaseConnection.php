<?php


//Load sensitive information through .env file
$env = parse_ini_file('/var/www/html/.env');

$servername = $env['DB_HOST'];
$username = $env['DB_USER'];
$password = $env['DB_PASSWORD'];
$dbname = $env['DB_NAME'];

//Create connection to the database using mysqli, allows for OOP
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT; //Allows exception throwing in mysqli

//Check connection, show error if connection fails
if ($conn->connect_error) {
	die("Connection failed");
} 
?>
