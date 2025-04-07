<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Home - Sign Up</title>
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
        input[type="password"],
        input[type="email"] {
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
        
        <form id="signupForm">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
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
	<script src="apiFunctions.js"></script>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		const signupForm = document.getElementById('signupForm');
		if (signupForm) {
			signupForm.addEventListener('submit', function(e) {
				e.preventDefault();

				//Retrieve inputs
				const username = document.getElementById('username').value.trim();
				const email = document.getElementById('email').value.trim();
				const password = document.getElementById('password').value;
				const confirmPassword = document.getElementById('confirm_password').value;

				//Password validations
				if (password !== confirmPassword) {
					showNotification("Passwords do not match", false);
					return;
				}
				if (password.length < 8) {
					showNotitication("Password must be at least 8 characters", false);
					return;
				}
				const checkUrl = `/api/users.php?username=${encodeURIComponent(username)}&email=${encodeURIComponent(email)}`;
            			apiRequest(checkUrl, 'GET', null, function(checkResult, checkError) {
                			if (checkError) {
                    				showNotification("Error checking availability: " + checkError, false);
                    				return;
                			}

                			if (checkResult.exists) {
                    				showNotification(checkResult.message, false);
                    				return;
                			}


					const payload = {
						username: username, email: email, password: password, confirm_password: confirmPassword
					};

					apiRequest('/api/users.php', 'POST', payload, function(result, error) {
						if (error) {
							showNotification("Error: " + error, false);
						} else {
							showNotification(result.message, result.success);
							if (result.success) {
								setTimeout(() => {
									window.location.href = 'login_page.php';
								}, 1200);
							}
						}
					});
				});
			});
		} else {
			console.error('Element with id "signupForm" not found.');
		}
	});
	</script>
</body>
</html>
