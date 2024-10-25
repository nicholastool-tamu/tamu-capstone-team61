<?php
session_start();

if (!isset($_SESSION['target_number'])) {
    $_SESSION['target_number'] = rand(1, 100);
    $_SESSION['attempts'] = 0;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guess = intval($_POST['guess']);
    $_SESSION['attempts']++;

    if ($guess === $_SESSION['target_number']) {
        $message = "Congratulations! You guessed the number in {$_SESSION['attempts']} attempts!";
        // Reset the game
        unset($_SESSION['target_number']);
        unset($_SESSION['attempts']);
    } elseif ($guess < $_SESSION['target_number']) {
        $message = "Too low! Try again.";
    } else {
        $message = "Too high! Try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guess the Number Game</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(45deg, #3498db, #8e44ad);
            color: #fff;
        }
        .game-container {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
            max-width: 400px;
            width: 100%;
        }
        h1 {
            margin-bottom: 20px;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        input, button {
            margin: 10px;
            padding: 12px;
            font-size: 18px;
            border: none;
            border-radius: 5px;
        }
        input {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            width: 60%;
        }
        button {
            background: #2ecc71;
            color: white;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        button:hover {
            background: #27ae60;
        }
        #message {
            margin-top: 20px;
            font-weight: bold;
            font-size: 1.2em;
            min-height: 1.5em;
        }
        #attempts {
            margin-top: 10px;
            font-style: italic;
        }
        .progress-bar {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 13px;
            height: 20px;
            padding: 3px;
            margin-top: 20px;
        }
        .progress {
            background: #2ecc71;
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease-in-out;
        }
    </style>
</head>
<body>
    <div class="game-container">
        <h1>Guess the Number</h1>
        <p>I'm thinking of a number between 1 and 100. Can you guess it?</p>
        <form method="post" id="guessForm">
            <input type="number" name="guess" id="guess" min="1" max="100" required placeholder="Enter your guess">
            <button type="submit">Submit</button>
        </form>
        <div id="message"><?php echo $message; ?></div>
        <div id="attempts">Attempts: <?php echo $_SESSION['attempts']; ?></div>
        <div class="progress-bar">
            <div class="progress" style="width: 0%"></div>
        </div>
    </div>

    <script>
        const form = document.getElementById('guessForm');
        const message = document.getElementById('message');
        const attemptsDiv = document.getElementById('attempts');
        const progress = document.querySelector('.progress');

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(form);
            
            fetch('newtest.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                message.textContent = doc.getElementById('message').textContent;
                attemptsDiv.textContent = doc.getElementById('attempts').textContent;
                
                const attempts = parseInt(attemptsDiv.textContent.split(': ')[1]);
                const progressWidth = Math.min(attempts * 10, 100);
                progress.style.width = `${progressWidth}%`;

                if (message.textContent.includes('Congratulations')) {
                    celebrateWin();
                }
            });
        });

        function celebrateWin() {
            const colors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff'];
            let i = 0;
            const interval = setInterval(() => {
                document.body.style.background = `linear-gradient(45deg, ${colors[i % colors.length]}, ${colors[(i + 1) % colors.length]})`;
                i++;
                if (i >= 20) clearInterval(interval);
            }, 100);
        }
    </script>
</body>
</html>
