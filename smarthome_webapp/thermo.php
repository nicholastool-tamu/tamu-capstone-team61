<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Home - Thermostat</title>
    <?php 
    $pageTitle = "Thermostat";
    include 'common_styles.php';
    ?>
    <style>
        /* Thermostat dial styling
         * Creates a circular interface with dark theme
         */
        .thermostat {
            width: 300px;
            height: 300px;
            margin: 0 auto;
            position: relative;
            background-color: #000;
            border-radius: 50%;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
        }

        /* Inner dial that users can interact with */
        .dial {
            width: 280px;
            height: 280px;
            position: absolute;
            top: 10px;
            left: 10px;
            border-radius: 50%;
            background: #1a1a1a;
            cursor: pointer;
        }

        /* Center display area showing temperature */
        .dial-center {
            width: 200px;
            height: 200px;
            position: absolute;
            top: 40px;
            left: 40px;
            border-radius: 50%;
            background: #222;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
        }

        /* Temperature display styling */
        .current-temp { font-size: 48px; font-weight: bold; margin-bottom: 5px; }
        .set-temp { font-size: 24px; color: #3498db; }

        /* Visual indicator for temperature setting
         * Uses conic gradient for circular progress
         */
        .dial-highlight {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: conic-gradient(
                from 0deg,
                #3498db 0%,
                #3498db var(--percentage),
                transparent var(--percentage),
                transparent 100%
            );
            opacity: 0.3;
        }

        /* Manual temperature control section */
        .manual-control {
            margin: 20px auto;
            text-align: center;
            max-width: 300px;
        }

        /* Input field and button styling */
        .temp-input {
            padding: 8px;
            width: 100px;
            margin-right: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        .temp-button {
            padding: 8px 16px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <?php include 'common_header.php'; ?>

    <!-- Main thermostat interface -->
    <div class="thermostat">
        <div class="dial" id="dial">
            <div class="dial-highlight" id="dialHighlight"></div>
            <div class="dial-center">
                <div class="current-temp" id="currentTemp">72°</div>
                <div class="set-temp" id="setTemp">Set: 72°</div>
            </div>
        </div>
    </div>

    <!-- Manual temperature input section -->
    <div class="manual-control">
        <input type="number" 
               id="manualTemp" 
               min="60" 
               max="80" 
               step="1" 
               placeholder="60-80°F"
               class="temp-input">
        <button id="setManualTemp" class="temp-button">Set Temperature</button>
    </div>

    <script>
        // DOM element references
        const dial = document.getElementById('dial');
        const dialHighlight = document.getElementById('dialHighlight');
        const currentTempDisplay = document.getElementById('currentTemp');
        const setTempDisplay = document.getElementById('setTemp');
        
        // State variables
        let currentTemp = 72;
        let setTemp = 72;
        let isDragging = false;
        let startAngle = 0;

        // Utility functions for temperature conversion
        function tempToPercentage(temp) {
            return ((temp - 60) / (80 - 60)) * 100;
        }

        function percentageToTemp(percentage) {
            return Math.round(60 + (percentage / 100) * (80 - 60));
        }

        // Update the visual display of temperatures
        function updateDisplay() {
            dialHighlight.style.setProperty('--percentage', `${tempToPercentage(setTemp)}%`);
            currentTempDisplay.textContent = `${currentTemp}°`;
            setTempDisplay.textContent = `Set: ${setTemp}°`;
        }

        // Event listeners for dial rotation
        dial.addEventListener('mousedown', (e) => {
            isDragging = true;
            const rect = dial.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            startAngle = Math.atan2(e.clientY - centerY, e.clientX - centerX);
        });

        // Handle temperature adjustment via dial rotation
        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;

            const rect = dial.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            
            const angle = Math.atan2(e.clientY - centerY, e.clientX - centerX);
            const angleDiff = angle - startAngle;
            
            const tempChange = Math.round(angleDiff * 10);
            setTemp = Math.min(80, Math.max(60, setTemp + tempChange));
            
            startAngle = angle;
            updateDisplay();
        });

        document.addEventListener('mouseup', () => {
            isDragging = false;
        });

        // Manual temperature control handlers
        const manualTempInput = document.getElementById('manualTemp');
        const setManualTempButton = document.getElementById('setManualTemp');

        setManualTempButton.addEventListener('click', () => {
            const newTemp = parseInt(manualTempInput.value);
            if (newTemp >= 60 && newTemp <= 80) {
                setTemp = newTemp;
                updateDisplay();
                manualTempInput.value = '';
            } else {
                alert('Please enter a temperature between 60° and 80°F');
            }
        });

        // Additional input handlers for better UX
        manualTempInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                setManualTempButton.click();
            }
        });

        manualTempInput.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });

        // Initialize the display
        updateDisplay();
    </script>
</body>
</html>
