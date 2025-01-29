<?php

require_once '../includes/databaseConnection.php';
require_once '../includes/functions.php';



//Check if the database exists and select it
function initializeDatabase($conn, $dbname) {
	$query = "CREATE DATABASE IF NOT EXISTS $dbname";
	if($conn->query($query) === TRUE) {
		if($conn->select_db($dbname)) {
			jsonResponse(true, "Database created or already exists.");
		}
		else {
			jsonResponse(false, "Failed to select database: " . $conn->error);
		}
	}
	else {
		jsonResponse(false, "Error creating database: " . $conn->error);
	}
}



//Create Users table in PHP using SQL script for purposes of redeploying
function createUsersTable($conn) {
	$createUsersTable = "CREATE TABLE IF NOT EXISTS users (
		user_id INT AUTO_INCREMENT PRIMARY KEY,
		username VARCHAR(50) NOT NULL UNIQUE,
		email VARCHAR(100) NOT NULL UNIQUE,
		password VARCHAR(255) NOT NULL,
		status VARCHAR(50) DEFAULT 'inactive' NOT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
		)
	";
	//Test creation of Users table
	if ($conn->query($createUsersTable) === TRUE) {
		jsonResponse(true, "Users table created successfully.");
	}
	else {
		jsonResponse(false,"Error creating Users table: " . $conn->error);
	}

}
//Create Devices Table
function createDevicesTable($conn) {
	$createDevicesTable = "CREATE TABLE IF NOT EXISTS devices (
		device_id INT AUTO_INCREMENT PRIMARY KEY,
		device_name VARCHAR(100) NOT NULL,
		device_type VARCHAR(50) NOT NULL,
		status VARCHAR(50) DEFAULT 'offline' NOT NULL,
		user_id INT,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
	)";

	if ($conn->query($createDevicesTable) === TRUE) {
		jsonResponse(true, "Devices table created successfully.");
	}
	else {
		jsonResponse(false, "Error creating Devices table: " . $conn->error);
	}
}
//Run the functions
initializeDatabase($conn, $dbname);
createUsersTable($conn);
createDevicesTable($conn);

//Close connection
$conn->close();

?>

