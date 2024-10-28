<?php
// Initialize a new or resume an existing session to track user data across pages
session_start();
?>

<!DOCTYPE html> <!-- Declares this as an HTML5 document -->
<html lang="en"> <!-- Sets the language of the document to English -->
<head>
    <!-- Specifies character encoding for the document -->
    <meta charset="UTF-8">
    <!-- Makes the page responsive by setting viewport properties -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Sets the page title shown in browser tab -->
    <title>Smart Home Automation</title>
    <!-- CSS styles begin here -->
    <style>
        /* Applies to entire page body */
        body {
            font-family: Arial, sans-serif;  /* Sets the default font */
            display: flex;                   /* Uses flexbox for layout */
            flex-direction: column;          /* Stacks elements vertically */
            align-items: center;             /* Centers items horizontally */
            justify-content: center;         /* Centers items vertically */
            min-height: 100vh;               /* Makes body at least full viewport height */
            margin: 0;                       /* Removes default margin */
            background-color: #f0f2f5;       /* Sets light gray background */
        }

        /* Styles for the main content container */
        .container {
            text-align: center;              /* Centers text content */
            padding: 20px;                   /* Adds space inside container */
            background-color: white;         /* White background for container */
            border-radius: 10px;             /* Rounds container corners */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); /* Adds subtle shadow */
        }

        /* Base styles for both buttons */
        .button {
            display: inline-block;           /* Makes links behave like buttons */
            padding: 12px 24px;              /* Adds space inside buttons */
            margin: 10px;                    /* Adds space between buttons */
            border: none;                    /* Removes default border */
            border-radius: 5px;              /* Rounds button corners */
            cursor: pointer;                 /* Shows hand cursor on hover */
            font-size: 16px;                 /* Sets button text size */
            text-decoration: none;           /* Removes underline from links */
            transition: background-color 0.3s; /* Smooth color transition on hover */
        }

        /* Specific styles for login button */
        .login-btn {
            background-color: #4CAF50;       /* Green background */
            color: white;                    /* White text */
        }

        /* Specific styles for signup button */
        .signup-btn {
            background-color: #2196F3;       /* Blue background */
            color: white;                    /* White text */
        }

        /* Hover effect for buttons */
        .button:hover {
            opacity: 0.9;                    /* Slightly fades button when hovered */
        }
    </style>
</head>
<body>
    <!-- Main content container -->
    <div class="container">
        <!-- Main heading for the page -->
        <h1>Welcome to Smart Home</h1>
        <!-- Subheading/description text -->
        <p>Control your home from anywhere, anytime.</p>

        <!-- Container for navigation buttons -->
        <div class="buttons">
            <!-- Login button linking to login page -->
            <a href="login.php" class="button login-btn">Login</a>
            <!-- Sign up button linking to signup page -->
            <a href="signup.php" class="button signup-btn">Sign Up</a>
        </div>
    </div>
</body>
</html>