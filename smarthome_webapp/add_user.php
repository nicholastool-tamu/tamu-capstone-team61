<?php
function addUser($username, $password) {
    $config = require_once 'config.php';
    
    // Create connection
    $conn = new mysqli(
        $config['db_host'],
        $config['db_user'],
        $config['db_pass'],
        $config['db_name']
    );

    // Check connection
    if ($conn->connect_error) {
        return ["success" => false, "message" => "Connection failed"];
    }

    // Check if username already exists
    $check_stmt = $conn->prepare("SELECT username FROM USERS WHERE username = ?");
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ["success" => false, "message" => "Username already exists"];
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Prepare and execute insert statement
    $stmt = $conn->prepare("INSERT INTO USERS (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $hashed_password);
    
    if ($stmt->execute()) {
        return ["success" => true, "message" => "User successfully created"];
    } else {
        return ["success" => false, "message" => "Error creating user"];
    }
}
?>
