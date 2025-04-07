<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


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
	<meta http-equiv="Cache-Control" content="no-cache, no-store, mustrevalidate" />
	<meta http-equiv="Pragma" content="no-cache" />
	<meta http-equiv="Expires" content="0" />
	<script>
	window.addEventListener('pageshow', function(event) {
		if (event.persisted) {
			window.location.reload();
		}
	});
	</script>
	<script>
	window.history.pushState(null, "", window.location.href);
	window.addEventListener("popstate", function(event) {
		window.history.replace("start.php");
	});
	</script>
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
        
        <form id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Password" required>
            </div>

            <button type="submit" class="login-btn">Login</button>
        </form>
        
        <a href="start.php" class="back-btn">Back to Start</a>
    </div>

	<script src="apiFunctions.js"> </script>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const loginForm = document.getElementById('loginForm');
			if (loginForm) {
				loginForm.addEventListener('submit', function(e) {
					e.preventDefault();

					const payload = {
						username: document.getElementById('username').value.trim(),
						password: document.getElementById('password').value
					};

					apiRequest('/api/login.php', 'POST', payload, function(result, error) {
						if (error) {
							showNotification("Error: " + error, false);
						} else {
							showNotification(result.message, result.success);
							if (result.success) {
								setTimeout(() => {window.location.href = 'home_dash.php';}, 1200);
							}
						}
					});
				});
			} else {
				console.error('Element with id "loginForm" not found.');
			}
		});
	</script>
</body>
</html>

