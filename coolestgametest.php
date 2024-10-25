<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Cool Pong</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #000;
            font-family: Arial, sans-serif;
            overflow: hidden;
        }
        #gameCanvas {
            border: 2px solid #fff;
        }
        #gameInfo {
            position: absolute;
            top: 10px;
            left: 10px;
            color: #fff;
            font-size: 20px;
        }
        #gameOverPopup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(0, 0, 0, 0.8);
            color: #fff;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        #gameOverPopup button {
            margin-top: 10px;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <canvas id="gameCanvas" width="800" height="600"></canvas>
    <div id="gameInfo">
        <p>Score: <span id="score">0</span></p>
    </div>
    <div id="gameOverPopup">
        <h2>Game Over!</h2>
        <p>Your score: <span id="finalScore"></span></p>
        <button onclick="restartGame()">Play Again</button>
        <button onclick="location.href='test.php'">Back to Main Menu</button>
    </div>

    <script>
        const canvas = document.getElementById('gameCanvas');
        const ctx = canvas.getContext('2d');
        const scoreElement = document.getElementById('score');
        const gameOverPopup = document.getElementById('gameOverPopup');
        const finalScoreElement = document.getElementById('finalScore');

        const paddleWidth = 10;
        const paddleHeight = 100;
        const ballSize = 10;

        let playerPaddle = { x: 0, y: canvas.height / 2 - paddleHeight / 2, dy: 0 };
        let cpuPaddle = { x: canvas.width - paddleWidth, y: canvas.height / 2 - paddleHeight / 2 };
        let ball = { x: canvas.width / 2, y: canvas.height / 2, dx: 5, dy: 5 };
        let score = 0;

        function drawPaddle(x, y) {
            ctx.fillStyle = '#fff';
            ctx.fillRect(x, y, paddleWidth, paddleHeight);
        }

        function drawBall() {
            ctx.beginPath();
            ctx.arc(ball.x, ball.y, ballSize, 0, Math.PI * 2);
            ctx.fillStyle = '#fff';
            ctx.fill();
            ctx.closePath();
        }

        function movePaddles() {
            playerPaddle.y += playerPaddle.dy;
            playerPaddle.y = Math.max(Math.min(playerPaddle.y, canvas.height - paddleHeight), 0);

            // CPU paddle movement
            const paddleCenter = cpuPaddle.y + paddleHeight / 2;
            const ballCenter = ball.y;
            const diff = ballCenter - paddleCenter;
            cpuPaddle.y += diff * 0.1; // Adjust this value to change CPU difficulty
            cpuPaddle.y = Math.max(Math.min(cpuPaddle.y, canvas.height - paddleHeight), 0);
        }

        function moveBall() {
            ball.x += ball.dx;
            ball.y += ball.dy;

            // Collision with top and bottom walls
            if (ball.y - ballSize < 0 || ball.y + ballSize > canvas.height) {
                ball.dy = -ball.dy;
            }

            // Collision with paddles
            if (
                (ball.x - ballSize < paddleWidth && ball.y > playerPaddle.y && ball.y < playerPaddle.y + paddleHeight) ||
                (ball.x + ballSize > canvas.width - paddleWidth && ball.y > cpuPaddle.y && ball.y < cpuPaddle.y + paddleHeight)
            ) {
                ball.dx = -ball.dx * 1.05; // Increase speed slightly
                if (ball.x - ballSize < paddleWidth) {
                    score++;
                    updateScore();
                }
            }

            // Game over condition
            if (ball.x < 0) {
                gameOver();
            }

            // CPU scores (just reset the ball)
            if (ball.x > canvas.width) {
                resetBall();
            }
        }

        function resetBall() {
            ball.x = canvas.width / 2;
            ball.y = canvas.height / 2;
            ball.dx = -5; // Always start towards the player
            ball.dy = (Math.random() > 0.5 ? 1 : -1) * 5;
        }

        function updateScore() {
            scoreElement.textContent = score;
        }

        function gameOver() {
            finalScoreElement.textContent = score;
            gameOverPopup.style.display = 'block';
        }

        function restartGame() {
            score = 0;
            updateScore();
            resetBall();
            gameOverPopup.style.display = 'none';
        }

        function gameLoop() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            drawPaddle(playerPaddle.x, playerPaddle.y);
            drawPaddle(cpuPaddle.x, cpuPaddle.y);
            drawBall();

            movePaddles();
            moveBall();

            requestAnimationFrame(gameLoop);
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowUp') {
                playerPaddle.dy = -5;
            } else if (e.key === 'ArrowDown') {
                playerPaddle.dy = 5;
            }
        });

        document.addEventListener('keyup', (e) => {
            if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                playerPaddle.dy = 0;
            }
        });

        gameLoop();
    </script>
</body>
</html>

