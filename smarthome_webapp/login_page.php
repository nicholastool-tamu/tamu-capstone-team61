<?php
// Start a new or resume existing session
session_start();

// Include login functionality
require_once 'login_func.php';

// Handle POST request (login form submission)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Attempt login with submitted credentials
    $result = attemptLogin($_POST['username'], $_POST['password']);
    
    // If login successful, redirect to dashboard
    if ($result['success']) {
        header("Location: home_dash.php");
        exit();
    } else {
        // Set error message for failed login
        $error_message = "Invalid username or password";
    }
}

// Check for successful signup message
if (isset($_SESSION['signup_success'])) {
    $success_message = "Account created successfully! Please login.";
    unset($_SESSION['signup_success']);  // Clear the message after displaying
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Define character encoding and viewport settings -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHAPP - Login</title>
    <style>
        /* Main page layout */
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f0f2f5;
        }

        /* Login form container styling */
        .login-container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        /* Page title styling */
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }

        /* Form group container */
        .form-group {
            margin-bottom: 20px;
        }

        /* Form label styling */
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }

        /* Input field styling */
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

        /* Login button hover effect */
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

        /* Back button hover effect */
        .back-btn:hover {
            color: #333;
        }
    </style>
</head>
<body>
    <!-- Main login container -->
    <div class="login-container">
        <h1>Login to Smart Home</h1>
        
        <!-- Display success message if account was just created -->
        <?php if (isset($success_message)): ?>
            <div class="success-message" style="color: green; text-align: center; margin-bottom: 20px;">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Display error message if login failed -->
        <?php if (isset($error_message)): ?>
            <div class="error-message" style="color: red; text-align: center; margin-bottom: 20px;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Login form -->
        <form action="login_page.php" method="POST">
            <!-- Username input field -->
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <!-- Password input field -->
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <!-- Submit button -->
            <button type="submit" class="login-btn">Login</button>
        </form>
        
        <!-- Back button to return to start page -->
        <a href="start.php" class="back-btn">Back to Start</a>
    </div>
</body>
</html>

