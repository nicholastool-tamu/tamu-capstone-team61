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
        /* Keep only the thermostat-specific styles */
        .thermostat {
            width: 300px;
            height: 300px;
            margin: 0 auto;
            position: relative;
            background-color: #000;
            border-radius: 50%;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
        }

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

        .current-temp {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .set-temp {
            font-size: 24px;
            color: #3498db;
        }

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
    </style>
</head>
<body>
    <?php include 'common_header.php'; ?>

    <div class="thermostat">
        <div class="dial" id="dial">
            <div class="dial-highlight" id="dialHighlight"></div>
            <div class="dial-center">
                <div class="current-temp" id="currentTemp">72°</div>
                <div class="set-temp" id="setTemp">Set: 72°</div>
            </div>
        </div>
    </div>

    <script>
        const dial = document.getElementById('dial');
        const dialHighlight = document.getElementById('dialHighlight');
        const currentTempDisplay = document.getElementById('currentTemp');
        const setTempDisplay = document.getElementById('setTemp');
        
        let currentTemp = 72;
        let setTemp = 72;
        let isDragging = false;
        let startAngle = 0;

        // Convert temperature to percentage (60° to 80° range)
        function tempToPercentage(temp) {
            return ((temp - 60) / (80 - 60)) * 100;
        }

        // Convert percentage to temperature
        function percentageToTemp(percentage) {
            return Math.round(60 + (percentage / 100) * (80 - 60));
        }

        function updateDisplay() {
            dialHighlight.style.setProperty('--percentage', `${tempToPercentage(setTemp)}%`);
            currentTempDisplay.textContent = `${currentTemp}°`;
            setTempDisplay.textContent = `Set: ${setTemp}°`;
        }

        dial.addEventListener('mousedown', (e) => {
            isDragging = true;
            const rect = dial.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            startAngle = Math.atan2(e.clientY - centerY, e.clientX - centerX);
        });

        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;

            const rect = dial.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            
            const angle = Math.atan2(e.clientY - centerY, e.clientX - centerX);
            const angleDiff = angle - startAngle;
            
            // Convert angle to temperature change
            const tempChange = Math.round(angleDiff * 10);
            setTemp = Math.min(80, Math.max(60, setTemp + tempChange));
            
            startAngle = angle;
            updateDisplay();
        });

        document.addEventListener('mouseup', () => {
            isDragging = false;
        });

        // Initialize display
        updateDisplay();
    </script>
</body>
</html>
