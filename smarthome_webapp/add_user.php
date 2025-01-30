<?php

function addUser($username, $password) {
    // Import database configuration from config.php
    $config = require_once 'config.php';
    
    // Create new database connection using config values
    $conn = new mysqli(
        $config['db_host'],    
        $config['db_user'],    
        $config['db_pass'],    
        $config['db_name']     
    );

    // Check if the database connection was successful
    if ($conn->connect_error) {
        return ["success" => false, "message" => "Connection failed"];
    }

    // Prepare to check if username already exists
    $check_stmt = $conn->prepare("SELECT username FROM USERS WHERE username = ?");
    $check_stmt->bind_param("s", $username);    // Bind username parameter
    $check_stmt->execute();                     // Execute the prepared statement
    $result = $check_stmt->get_result();        // Get the result set
    
    // If username exists, return error message
    if ($result->num_rows > 0) {
        return ["success" => false, "message" => "Username already exists"];
    }
    
    // Create hash of the password 
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Prepare to insert new user
    $stmt = $conn->prepare("INSERT INTO USERS (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $hashed_password);    // Bind parameters 
    
    // Execute insert statement and return success/error message
    if ($stmt->execute()) {
        return ["success" => true, "message" => "User successfully created"];
    } else {
        return ["success" => false, "message" => "Error creating user"];
    }
}
?>
