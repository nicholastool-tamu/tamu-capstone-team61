<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Awesome Game Center</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: 'Arial', sans-serif;
            background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        @keyframes gradient {
            0% {background-position: 0% 50%;}
            50% {background-position: 100% 50%;}
            100% {background-position: 0% 50%;}
        }
        .container {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        h1 {
            color: #ffffff;
            font-size: 3em;
            margin-bottom: 30px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            margin: 10px;
            font-size: 1.2em;
            color: white;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        .btn:active {
            transform: translateY(-1px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Awesome Game Center</h1>
        <a href="coolestgametest.php" class="btn">Super Cool Pong</a>
        <a href="newtest.php" class="btn">Guess the Number</a>
    </div>
</body>
</html>

