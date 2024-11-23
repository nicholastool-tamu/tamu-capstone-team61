<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Home - Light</title>
    <?php 
    $pageTitle = "Light Control";
    include 'common_styles.php';
    ?>
    <style>
        /* Keep only the light-specific styles */
        .light-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .light-status {
            font-size: 24px;
            margin: 20px 0;
        }

        .light-btn {
            padding: 20px 40px;
            font-size: 20px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .light-btn.on {
            background-color: #f1c40f;
            box-shadow: 0 0 20px rgba(241, 196, 15, 0.5);
        }

        .light-btn.off {
            background-color: #95a5a6;
        }

        .bulb-icon {
            font-size: 200px;
            margin: 20px 0;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <?php include 'common_header.php'; ?>

    <div class="light-container">
        <div class="bulb-icon" id="bulb">💡</div>
        <div class="light-status" id="status">Light is OFF</div>
        <button class="light-btn off" id="toggleButton" onclick="toggleLight()">
            Turn ON
        </button>
    </div>

    <script>
        let isLightOn = false;
        const button = document.getElementById('toggleButton');
        const status = document.getElementById('status');
        const bulb = document.getElementById('bulb');

        function toggleLight() {
            isLightOn = !isLightOn;
            
            if (isLightOn) {
                button.textContent = 'Turn OFF';
                button.classList.remove('off');
                button.classList.add('on');
                status.textContent = 'Light is ON';
                bulb.style.opacity = '1';
            } else {
                button.textContent = 'Turn ON';
                button.classList.remove('on');
                button.classList.add('off');
                status.textContent = 'Light is OFF';
                bulb.style.opacity = '0.3';
            }
        }
    </script>
</body>
</html>
