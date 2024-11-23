<?php
// Function to add a new user to the database
// Takes username and password as parameters
function addUser($username, $password) {
    // Import database configuration from config.php
    $config = require_once 'config.php';
    
    // Create new MySQL database connection using config values
    $conn = new mysqli(
        $config['db_host'],    // Database host address
        $config['db_user'],    // Database username
        $config['db_pass'],    // Database password
        $config['db_name']     // Database name
    );

    // Check if the database connection was successful
    if ($conn->connect_error) {
        return ["success" => false, "message" => "Connection failed"];
    }

    // Prepare SQL statement to check if username already exists
    $check_stmt = $conn->prepare("SELECT username FROM USERS WHERE username = ?");
    $check_stmt->bind_param("s", $username);    // Bind username parameter ('s' for string)
    $check_stmt->execute();                     // Execute the prepared statement
    $result = $check_stmt->get_result();        // Get the result set
    
    // If username exists, return error message
    if ($result->num_rows > 0) {
        return ["success" => false, "message" => "Username already exists"];
    }
    
    // Create secure hash of the password using PHP's built-in password_hash function
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Prepare SQL statement to insert new user
    $stmt = $conn->prepare("INSERT INTO USERS (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $hashed_password);    // Bind parameters ('ss' for two strings)
    
    // Execute insert statement and return appropriate success/error message
    if ($stmt->execute()) {
        return ["success" => true, "message" => "User successfully created"];
    } else {
        return ["success" => false, "message" => "Error creating user"];
    }
}
?>
