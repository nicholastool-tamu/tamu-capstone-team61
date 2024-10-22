<?php
//Load sensitive information through .env file
$env = parse_ini_file('.env');

$servername = $env['DB_HOST'];
$username = $env['DB_USER'];
$password = $env['DB_PASSWORD'];
$dbname = $env['DB_NAME'];

//Create connection to the database using mysqli, allows for OOP
$conn = new mysqli($servername, $username, $password, $dbname);

//Check connection, show error if connection fails
if ($conn->connect_error) {
	die ("Connection failed: " . $conn->connect-error);
}
echo "Connected successfully to The Smart Home Database!"; 
?>
