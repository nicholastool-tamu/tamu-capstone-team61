<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        require_once 'add_user.php';
        
        // Basic validation
        if (strlen($_POST['password']) < 8) {
            $error_message = "Password must be at least 8 characters long";
        } elseif ($_POST['password'] !== $_POST['confirm_password']) {
            $error_message = "Passwords do not match";
        } else {
            $result = addUser($_POST['username'], $_POST['password']);
            
            if ($result['success']) {
                $_SESSION['signup_success'] = true;
                header("Location: login_page.php");
                exit();
            } else {
                $error_message = $result['message'];
            }
        }
    } catch (Exception $e) {
        $error_message = "System error: " . $e->getMessage();
        error_log("Sign up error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Home - Sign Up</title>
    <style>
        /* Reuse the same styling as login.php */
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f0f2f5;
        }

        .signup-container {
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

        .signup-btn {
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

        .signup-btn:hover {
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
    <div class="signup-container">
        <h1>Sign Up for Smart Home</h1>
        <?php if (isset($error_message)): ?>
            <div class="error-message" style="color: red; text-align: center; margin-bottom: 20px;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <form action="sign_up.php" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="signup-btn">Sign Up</button>
        </form>
        
        <a href="start.php" class="back-btn">Back to Start</a>
    </div>
</body>
</html>
