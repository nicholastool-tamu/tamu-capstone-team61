<?php
session_start();

// Function to attempt user login with username and password
function attemptLogin($username, $password) {
    try {
        // Import database configuration
        $config = require_once 'config.php';
        
        // Create new database connection using config values
        $conn = new mysqli(
            $config['db_host'],    // Database host
            $config['db_user'],    // Database username
            $config['db_pass'],    // Database password
            $config['db_name']     // Database name
        );

        // Check for connection errors
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }

        // Prepare SQL statement to select user's password
        $stmt = $conn->prepare("SELECT password FROM USERS WHERE username = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // Bind username parameter and execute query
        $stmt->bind_param("s", $username);    // 's' indicates string parameter
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        // Get query results
        $result = $stmt->get_result();

        // Check if user exists and verify password
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Verify password hash matches
            if (password_verify($password, $user['password'])) {
                // Set session variables on successful login
                $_SESSION['logged_in'] = true;
                $_SESSION['username'] = $username;
                return ['success' => true];
            }
        }

        // Return error for invalid credentials
        return ['success' => false, 'message' => "Invalid username or password"];
    } catch (Exception $e) {
        // Return error message for system errors
        return ['success' => false, 'message' => "System error: " . $e->getMessage()];
    }
}

// Function to check if user is logged in
function checkLogin() {
    // If not logged in, redirect to login page
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: login.php");
        exit();
    }
}
?>