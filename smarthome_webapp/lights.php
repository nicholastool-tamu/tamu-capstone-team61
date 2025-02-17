<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login_page.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">   <!-- boiler plate-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Home - Light</title>
    <?php 
    $pageTitle = "Light Control";
    include 'common_styles.php'; // Including css styling for the header
    ?>
    <style>
        .light-container { /* setting container for light control to align elemts in column and be centered */
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .light-status { /* text*/
            font-size: 24px;
            margin: 20px 0;
        }

        .light-btn { /*button styling*/
            padding: 20px 40px;
            font-size: 20px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .light-btn.on { /*button styling dependent on on status*/
            background-color: #f1c40f;     
            box-shadow: 0 0 20px rgba(241, 196, 15, 0.5);  
        }

        .light-btn.off { /*button styling dependent on off status*/
            background-color: #95a5a6;     
        }

        .bulb-icon { /*text styling for bulb emoji*/
            font-size: 200px;
            margin: 20px 0;
            opacity: 0.3;            /*opacity changes to idnicate on or off, initializes as off*/       
        }

        /* New light selector styles */
        .light-selector {
            width: 100%;
            max-width: 400px;
            margin: 20px auto;
            padding: 15px;
            background-color: #f5f5f5;  /* Changed from white to light grey */
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .light-select-btn {
            width: 100%;
            padding: 10px 15px;
            margin: 5px 0;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-align: left;
            transition: background-color 0.3s;
        }

        .light-select-btn.active {
            background-color: #4CAF50;
        }

        .no-lights-message {
            text-align: center;
            color: #666;
            padding: 20px;
        }
    </style>
</head>
<body>
    <?php include 'common_header.php'; //includes header javascript ?> 

    <div class="light-container"> 
        <div class="bulb-icon" id="bulb">💡</div>
        <button class="light-btn off" id="toggleButton" onclick="toggleLight()" disabled>
            Turn ON
        </button>
        <div class="light-status" id="status">Select a light</div> 
    </div>

    <div class="light-selector">
        <div id="lightList">
            <!-- Light buttons will be populated by JavaScript -->
        </div>
    </div>

    <script>
        let currentLight = null;
        let isLightOn = false;
        //Get elements 
        const button = document.getElementById('toggleButton');
        const status = document.getElementById('status');
        const bulb = document.getElementById('bulb');
        const lightList = document.getElementById('lightList');

        // Load and display lights from localStorage
        function loadLights() {
            const savedDevices = localStorage.getItem('devices');
            if (savedDevices) {
                const devices = JSON.parse(savedDevices);
                const lights = devices.lights || [];
                
                if (lights.length === 0) {
                    lightList.innerHTML = `
                        <div class="no-lights-message">
                            No lights found. Add lights in the settings page.
                        </div>`;
                    return;
                }

                lightList.innerHTML = lights.map(light => `
                    <button class="light-select-btn" onclick="selectLight('${light}')">${light}</button>
                `).join('');
            }
        }

        function selectLight(lightName) {
            currentLight = lightName;
            document.querySelectorAll('.light-select-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent === lightName) {
                    btn.classList.add('active');
                }
            });
            
            // Reset light state
            isLightOn = false;
            button.disabled = false;
            button.textContent = 'Turn ON';
            button.classList.remove('on');
            button.classList.add('off');
            status.textContent = `${lightName} is OFF`;
            bulb.style.opacity = '0.3';
        }

        function toggleLight() {
            if (!currentLight) return;
            
            isLightOn = !isLightOn;
            
            if (isLightOn) {
                button.textContent = 'Turn OFF';
                button.classList.remove('off');
                button.classList.add('on');
                status.textContent = `${currentLight} is ON`;
                bulb.style.opacity = '1';
            } else {
                button.textContent = 'Turn ON';
                button.classList.remove('on');
                button.classList.add('off');
                status.textContent = `${currentLight} is OFF`;
                bulb.style.opacity = '0.3';
            }
        }

        // Initialize the lights
        loadLights();
    </script>
</body>
</html>
