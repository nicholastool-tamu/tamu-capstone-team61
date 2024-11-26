<?php
session_start();

require_once 'login_func.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $result = attemptLogin($_POST['username'], $_POST['password']);
    
    if ($result['success']) {
        header("Location: home_dash.php");
        exit();
    } else {
        $error_message = "Invalid username or password";
    }
}

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
    <title>SHAPP - Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f0f2f5;
        }

        .login-container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }

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
        
        <?php if (isset($error_message)): ?>
            <div class="error-message" style="color: red; text-align: center; margin-bottom: 20px;">
                <?php echo htmlspecialchars($error_message); ?>
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
        
        <a href="start.php" class="back-btn">Back to Start</a>
    </div>
</body>
</html>

