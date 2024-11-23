<?php
session_start();

function attemptLogin($username, $password) {
    try {
        $config = require_once 'config.php';
        
        $conn = new mysqli(
            $config['db_host'],
            $config['db_user'],
            $config['db_pass'],
            $config['db_name']
        );

        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }

        $stmt = $conn->prepare("SELECT password FROM USERS WHERE username = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $username);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['logged_in'] = true;
                $_SESSION['username'] = $username;
                return ['success' => true];
            }
        }

        return ['success' => false, 'message' => "Invalid username or password"];
    } catch (Exception $e) {
        return ['success' => false, 'message' => "System error: " . $e->getMessage()];
    }
}

function checkLogin() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: login.php");
        exit();
    }
}
?>