<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login_page.php");
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
		$user = $result->fetch_assoc();
		$user_id = $user['user_id'];
	}
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
	.brightness-containter {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 10px;
		margin-top: 20px;
	}
	.brightness-buttons {
		display: flex;
		gap: 10px;
	}
	.brightness-buttons button {
		padding: 10px 20px;
		font-size: 16px;
		cursor: pointer;
	}
	.brightness-display {
		font-size: 18px;
		font-weight: bold;
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

	<div class="brightness-container" id="brightnessContainer">
		<div class="brightness-display">
			Brightness: <span id="brightnessValue">100%</span>
		</div>
		<div class="brightness-buttons">
			<button id="decreaseBrightness" onclick="changeBrightness('decrease')" disabled>Decrease</button>
			<button id="increaseBrightness" onclick="changeBrightness('increase')" disabled>Increase</button>
		</div>
	</div>
    </div>

    <div class="light-selector">
        <div id="lightList">
            <!-- Light buttons will be populated by JavaScript -->
        </div>
    </div>

	<script src="apiFunctions.js"></script>
	<script>
		let currentLightId = null;
		let currentLightName = null;
		let isLightOn = false;
		let currentBrightness = 100;

		const button = document.getElementById('toggleButton');
		const statusEl = document.getElementById('status');
		const bulb = document.getElementById('bulb');
		const lightList = document.getElementById('lightList');
		const brightnessValueEl = document.getElementById('brightnessValue');
		const decreaseBtn = document.getElementById('decreaseBrightness');
		const increaseBtn = document.getElementById('increaseBrightness');

		function loadLights() {
			const userId = '<?php echo $user_id; ?>';
			apiRequest(`/api/devices.php?device_type=lights&user_id=${userId}`, 'GET', {}, function(result, error) {
				if (error) {
					lightList.innerHTML = `<div class="no-lights-message">Error loading lights.</div>`;
					return;
				}
				if (result.success && result.data && result.data.length > 0) {
					lightList.innerHTML = result.data.map(device => {
						let brightness = 100;
						if (device.device_settings) {
							try {
								const settings = JSON.parse(device.device_settings);
								if (settings.brightness !== undefined) {
									brightness = settings.brightness;
								}
							} catch (e) {}
						}
						const displayName = device.custom_name ? device.custom_name : device.device_name;
						return `<button class="light-select-btn"
								data-id="${device.user_device_id}"
								data-hardware="${device.hardware_device_id}"
								data-name="${displayName}"
								data-status="${device.status}"
								data-brightness="${brightness}">
								${displayName}
							</button>`;
					}).join('');
					document.querySelectorAll('.light-select-btn').forEach(btn => {
						btn.addEventListener('click', function() {
							const mappingId = this.getAttribute('data-id');
                    					const hardwareId = this.getAttribute('data-hardware');
                    					const deviceName = this.getAttribute('data-name');
                    					const deviceStatus = this.getAttribute('data-status');
                    					const brightness = this.getAttribute('data-brightness');
							selectLight(mappingId, hardwareId, deviceName, deviceStatus, brightness);
						});
					});
				} else {
					lightList.innerHTML = `<div class="no-lights-message">No lights found. Add lights in the settings page.</div>`;
				}
			});
		}

		function selectLight(mappingId, hardwareId, deviceName, deviceStatus, brightness) {
			console.log("selectLight called for mapping:", mappingId, "hardware:", hardwareId,  deviceName);
			currentMappingId = mappingId;
			currentHardwareId = hardwareId
			currentLightName = deviceName;
			currentBrightness = brightness || 100;
			document.querySelectorAll('.light-select-btn').forEach(btn => {
				btn.classList.remove('active');
				if (btn.getAttribute('data-id') === mappingId) {
					btn.classList.add('active');
				}
			});
			isLightOn = (deviceStatus && deviceStatus.toLowerCase() === 'on') ? true : false;
			button.disabled = false;
			decreaseBtn.disabled = false;
			increaseBtn.disabled = false;
			updateToggleUI();
			updateBrightnessDisplay();
			updateBrightnessButtons();
		}
		function updateToggleUI() {
			const brightnessContainer = document.getElementById('brightnessContainer');
			if (isLightOn) {
				button.textContent = 'Turn OFF';
				button.classList.remove('off');
				button.classList.add('on');
				statusEl.textContent = `${currentLightName} is ON`;
				bulb.style.opacity = '1';
				brightnessContainer.style.display = 'flex';
				brightnessContainer.style.flexDirection = 'column';
			} else {
				button.textContent = 'Turn ON';
				button.classList.remove('on');
				button.classList.add('off');
				statusEl.textContent = `${currentLightName} is OFF`;
				bulb.style.opacity = '0.3';
				brightnessContainer.style.display = 'none';
			}
		}

		function updateBrightnessDisplay() {
			brightnessValueEl.textContent = currentBrightness + '%';
		}

		function updateBrightnessButtons() {
			decreaseBtn.disabled = (currentBrightness <= 25);
			increaseBtn.disabled = (currentBrightness >= 100);
		}

		function changeBrightness(direction) {
			let newBrightness = currentBrightness;
			if (direction === 'decrease') {
				newBrightness = Math.max(currentBrightness - 25, 25);
			} else if (direction === 'increase') {
				newBrightness = Math.min(currentBrightness + 25, 100);
			}
			if (newBrightness !== currentBrightness) {
				currentBrightness = newBrightness;
				updateBrightnessDisplay();
				updateBrightnessButtons();
				let command = (direction === 'decrease') ? "LED_BRIGHTNESS_DOWN" : "LED_BRIGHTNESS_UP";
				let topic = getMqttTopicForLight(currentHardwareId);
				publishMqttCommand(topic, command);
				setBrightness(currentBrightness);
			}
		}

		function setBrightness(value) {
			if (!currentMappingId || !currentHardwareId) return;
			const payload = {action: "update", device_id: currentMappingId, brightness: parseInt(value)};
			apiRequest('/api/devices.php', 'POST', payload, function(result, error) {
				if (error || !result.success) {
					showNotification("Error updating brightness: " + (error || result.message), false);
				} else {
					showNotification("Brightness updated to: " + value, true);
				}
			});
		}

		function toggleLight() {
			console.log("toggleLight clicked");
			if (!currentMappingId || !currentHardwareId) return;
			const newStatus = isLightOn ? "LIGHT_OFF" : "LIGHT_ON";
			const topic = getMqttTopicForLight(currentHardwareId);
			publishMqttCommand(topic, newStatus);
			const payload = {action: "update", device_id: currentMappingId, status: newStatus};
			apiRequest('/api/devices.php', 'POST', payload, function(result, error) {
				if (error || !result.success) {
					showNotification("Error updating light: " + (error || result.message), false);
					return;
				}
				const normalizedStatus = newStatus.toLowerCase().includes('on') ? 'on' : 'off';
				isLightOn = (normalizedStatus === 'on');
				const activeBtn = document.querySelector('.light-select-btn.active');
				if (activeBtn) {
					activeBtn.setAttribute('data-status', normalizedStatus);
				}
				updateToggleUI();
				showNotification(`Light status updated to: ${normalizedStatus}`, true);
			});
		}
		function getMqttTopicForLight(hardwareId) {
    			if (hardwareId == 1) {
        			return "device/led";
    			} else if (hardwareId == 2) {
        			return "device/relay";
    			}
    			// Default fallback
    			return "device/led";
		}
		function publishMqttCommand(topic, payload) {
    			const message = {
        			topic: topic,
        			payload: payload
    			};
    			apiRequest('/api/publish.php', 'POST', message, function(result, error) {
        			if (error || !result.success) {
            				showNotification("MQTT publish error: " + (error || result.message), false);
        			} else {
            			console.log("MQTT message published:", payload);
        			}
    			});
		}

		document.addEventListener('DOMContentLoaded', loadLights);
	</script>
</body>
</html>
