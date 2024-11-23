<?php
// Initialize a new or resume an existing session to track user data across pages
session_start();
?>

<!DOCTYPE html> <!-- Declares this as an HTML5 document -->
<html lang="en"> <!-- Sets the language of the document to English -->
<head>
    <!-- Meta tags for character encoding and responsive viewport -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Home Automation</title>

    <style>
        /* Root styles for the entire page
         * Uses flexbox for centered layout
         * Sets minimum height to full viewport
         */
        body {
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f0f2f5;
        }

        /* Main content container
         * White box with rounded corners and shadow
         */
        .container {
            text-align: center;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Base button styles
         * Used for both login and signup buttons
         * Includes hover animation
         */
        .button {
            display: inline-block;
            padding: 12px 24px;
            margin: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        /* Login button specific styles - green theme */
        .login-btn {
            background-color: #4CAF50;
            color: white;
        }

        /* Signup button specific styles - blue theme */
        .signup-btn {
            background-color: #2196F3;
            color: white;
        }

        /* Subtle opacity reduction on button hover */
        .button:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <!-- Main content wrapper -->
    <div class="container">
        <!-- Application title and description -->
        <h1>Welcome to SHAPP</h1>
        <p>Control your IOT devices.</p>

        <!-- Navigation buttons container -->
        <div class="buttons">
            <!-- Login and signup links styled as buttons -->
            <a href="login_page.php" class="button login-btn">Login</a>
            <a href="sign_up.php" class="button signup-btn">Sign Up</a>
        </div>
    </div>
</body>
</html>