<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
	header("location: login.php");
	exit();
}

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$username = $_GET['username'] ?? null;
if (!$user_id && $username) {
	require_once '/var/www/backend/includes/databaseConnection.php';
	$stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
	$stmt->bind_param("s", $username);
	$stmt->execute();
	$result = $stmt->get_result();
	if ($result->num_rows === 1) {
		$user = $result->retch_assoc();
		$user_id = $user['user_id'];
	}
}
?>

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

        .current-temp { font-size: 48px; font-weight: bold; margin-bottom: 5px; }
        .set-temp { font-size: 24px; color: #3498db; }

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

        .manual-control {
            margin: 20px auto;
            text-align: center;
            max-width: 300px;
        }

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
	.no-thermo-message {
		text-align: center;
		font-size: 24px;
		color: white;
		margin-top: 20px;
		display: none;
	}
    </style>
</head>
<body>
    <?php include 'common_header.php'; ?>
    <div id="thermoContainer">
    <div class="thermostat" id="thermostatDial">
        <div class="dial" id="dial">
            <div class="dial-highlight" id="dialHighlight"></div>
            <div class="dial-center">
                <div class="current-temp" id="currentTemp">72°</div>
                <div class="set-temp" id="setTemp">Set: 72°</div>
            </div>
        </div>
    </div>

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

	<div class="no-thermo-message" id="noThermoMessage">
		No thermostat found. Please add a device in settings.
	</div>
	<script src="apiFunctions.js"></script>
	<script>
		let thermoDeviceId = null;
		let currentTemp = 72;
		let setTemp = 72;
		let isDragging = false;
		let startAngle = 0;
        	const dial = document.getElementById('dial');
        	const dialHighlight = document.getElementById('dialHighlight');
        	const currentTempDisplay = document.getElementById('currentTemp');
        	const setTempDisplay = document.getElementById('setTemp');
		const manualTempInput = document.getElementById('manualTemp');
		const setManualTempButton = document.getElementById('setManualTemp');
		const thermoContainer = document.getElementById('thermoContainer');
		const noThermoMessage = document.getElementById('noThermoMessage');


        	function tempToPercentage(temp) {
            		return ((temp - 60) / (80 - 60)) * 100;
        	}

        	function percentageToTemp(percentage) {
            		return Math.round(60 + (percentage / 100) * (80 - 60));
        	}

        	function updateDisplay() {
            		dialHighlight.style.setProperty('--percentage', `${tempToPercentage(setTemp)}%`);
            		currentTempDisplay.textContent = `${currentTemp}°`;
            		setTempDisplay.textContent = `Set: ${setTemp}°`;
        	}

		function updateTemperature() {
			if (!thermoDeviceId) {
				console.error("Thermo device ID is not set!");
				return;
			}
			const payload = {action: "update", device_id: thermoDeviceId, temperature: setTemp};
			apiRequest('/api/devices.php', 'POST', payload, function(result,error) {
				if (error || !result.success) {
					showNotification("Error updating temperature: " + (error || result.message), false);
				} else {
					showNotification("Temperature updated to: " + setTemp + "°", true);
				}
			});
		}

        dial.addEventListener('mousedown', function(e) {
            	isDragging = true;
            	const rect = dial.getBoundingClientRect();
            	const centerX = rect.left + rect.width / 2;
            	const centerY = rect.top + rect.height / 2;
            	startAngle = Math.atan2(e.clientY - centerY, e.clientX - centerX);
		initialTemp = setTemp;
        });

        document.addEventListener('mousemove', function(e) {
            if (!isDragging) return;

            const rect = dial.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            
            const angle = Math.atan2(e.clientY - centerY, e.clientX - centerX);
            const angleDiff = angle - startAngle;
            const scaleFactor = 20 / (2 * Math.PI);
            const tempChange = angleDiff * scaleFactor;;
            setTemp = Math.min(80, Math.max(60, Math.round(initialTemp + tempChange)));
            updateDisplay();
        });

        document.addEventListener('mouseup', function() {
        	if (isDragging) {
			isDragging = false;
			updateTemperature();
		}
        });

        setManualTempButton.addEventListener('click', function() {
            const newTemp = parseInt(manualTempInput.value);
            if (newTemp >= 60 && newTemp <= 80) {
                setTemp = newTemp;
                updateDisplay();
		updateTemperature();
                manualTempInput.value = '';
            } else {
                showNotification('Please enter a temperature between 60° and 80°F', false);
            }
        });

        manualTempInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                setManualTempButton.click();
            }
        });

        manualTempInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });

        updateDisplay();

	document.addEventListener('DOMContentLoaded', function() {
		const userId = '<?php echo $user_id; ?>';
		apiRequest(`/api/devices.php?device_type=thermostat&user_id=${userId}`, 'GET', {}, function(result,error) {
			if (error || !result.success || !result.data || result.data.length === 0) {
				thermoContainer.style.display = 'none';
				noThermoMessage.style.display = 'block';
				return;
			}
			const device = result.data[0];
			thermoDeviceId = device.device_id;
			if (device.temperature !== undefined && device.temperature !== null) {
				currentTemp = setTemp = device.temperature;
			} else if (device.device_settings) {
				try {
					const settings = JSON.parse(device.device_settings);
					if (settings.temperature !== undefined) {
						currentTemp = setTemp = settings.temperature;
					}
				} catch (e) {
					console.error("Error parsing device_settings:", e);
				}
			}
			updateDisplay();
		});
	});
    </script>
</body>
</html>
