<?php
// Turn off all error reporting for display
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// But ensure errors are still being logged
error_reporting(E_ALL);

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include only the logging functions
require_once 'includes/logging_functions.php';

// Custom error handler to capture all errors/notices
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $message = sprintf("%s on line %d: %s", basename($errfile), $errline, $errstr);
    logError($errfile, $message);
    return true;
});

require_once 'login_func.php';

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $result = attemptLogin($_POST['username'], $_POST['password']);
    
    if ($result['success']) {
        logNotice(__FILE__, "Successfully logged in!", 'success');
        header("Location: home_dash.php");
        exit();
    } else {
        logError(__FILE__, $result['message']);
        header("Location: errors.php");
        exit();
    }
}

// Check for signup success message
if (isset($_SESSION['signup_success'])) {
    $success_message = "Account created successfully! Please login.";
    unset($_SESSION['signup_success']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Home - Login</title>
    <style>
        /* Main page styling */
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f0f2f5;
        }

        /* Login form container */
        .login-container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        /* Form header */
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }

        /* Form input styling */
        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 16px;
        }

        /* Login button styling */
        .login-btn {
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }

        .login-btn:hover {
            background-color: #45a049;
        }

        /* Back button styling */
        .back-btn {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
        }

        .back-btn:hover {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Login to Smart Home</h1>
        <?php if (isset($success_message)): ?>
            <div class="success-message" style="color: green; text-align: center; margin-bottom: 20px;">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <form action="login_page.php" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="login-btn">Login</button>
        </form>
        
        <!-- Back button to return to start page -->
        <a href="start.php" class="back-btn">Back to Start</a>
    </div>
</body>
</html>

