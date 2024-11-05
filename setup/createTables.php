<?php

include '../includes/databaseConnection.php';

//Check if the database exists and select it
function initializeDatabase($conn) {
	$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
	if($conn->query($sql) === TRUE {
		echo "Database '$dbname' created or already exists.<br>";
		conn->select_db($dbname);
	}
	else {
		die("Error creating database: " . $conn->error . "<br>");
	}
}

//Create Devices table in PHP using SQL Script for purposes of redeploying

function createDevicesTable($conn) {
	$createDevicesTable = "CREATE TABLE IF NOT EXISTS Devices (
		id INT AUTO_INCREMENT PRIMARY KEY,
		device_name VARCHAR(100) NOT NULL,
		status VARCHAR(10) NOT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
		);
	";
	//Test creation of Devices table
	if ($conn->query($createDevicesTable) === TRUE) {
		echo "Devices table created successfully.<br>';
		}
	else {
		echo "Error creating Devices table: " . $conn->error . "<br>";
	}
}
//Create Users table in PHP using SQL script for purposes of redeploying
function createUsersTable($conn) {
	$createUsersTable = "CREATE TABLE IF NOT EXISTS Users (
		id INT AUTO_INCREMENT PRIMARY KEY,
		username VARCHAR(50) NOT NULL,
		email VARCHAR(100) NOT NULL,
		password VARCHAR(255) NOT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
		);
	";
	//Test creation of Users table
	if ($conn->query($createUsersTable) === TRUE) {
		echo "Users table created successfully.<br>";
	}
	else {
		echo "Error creating Users table: " . $conn->error . "<br>";
	}
}
//Run the functions
createDevicesTable($conn);
createUsersTable($conn);

//Close connection
$conn->close();

?>

