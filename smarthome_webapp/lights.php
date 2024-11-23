<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Define character encoding and viewport settings -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Home - Light</title>
    <?php 
    // Set page title for common header and include shared styles
    $pageTitle = "Light Control";
    include 'common_styles.php';
    ?>
    <style>
        /* Container for light control elements */
        .light-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        /* Style for light status text */
        .light-status {
            font-size: 24px;
            margin: 20px 0;
        }

        /* Base styles for light control button */
        .light-btn {
            padding: 20px 40px;
            font-size: 20px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
        }

        /* Styles for when light is ON */
        .light-btn.on {
            background-color: #f1c40f;     /* Yellow background */
            box-shadow: 0 0 20px rgba(241, 196, 15, 0.5);  /* Glowing effect */
        }

        /* Styles for when light is OFF */
        .light-btn.off {
            background-color: #95a5a6;     /* Gray background */
        }

        /* Style for the bulb emoji icon */
        .bulb-icon {
            font-size: 200px;
            margin: 20px 0;
            opacity: 0.3;                  /* Dimmed when light is off */
        }
    </style>
</head>
<body>
    <!-- Include common header component -->
    <?php include 'common_header.php'; ?>

    <!-- Main light control interface -->
    <div class="light-container">
        <!-- Light bulb emoji that changes opacity based on state -->
        <div class="bulb-icon" id="bulb">💡</div>
        <!-- Text showing current light status -->
        <div class="light-status" id="status">Light is OFF</div>
        <!-- Toggle button for light control -->
        <button class="light-btn off" id="toggleButton" onclick="toggleLight()">
            Turn ON
        </button>
    </div>

    <script>
        // Track light state
        let isLightOn = false;
        // Get DOM elements
        const button = document.getElementById('toggleButton');
        const status = document.getElementById('status');
        const bulb = document.getElementById('bulb');

        // Function to toggle light state and update UI
        function toggleLight() {
            isLightOn = !isLightOn;    // Toggle state
            
            if (isLightOn) {
                // Update UI elements for ON state
                button.textContent = 'Turn OFF';
                button.classList.remove('off');
                button.classList.add('on');
                status.textContent = 'Light is ON';
                bulb.style.opacity = '1';
            } else {
                // Update UI elements for OFF state
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
