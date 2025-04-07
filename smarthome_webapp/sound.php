<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
	header("location: login.php");
	exit();
}

$username = $_GET['username'] ?? null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Home - Speaker</title>
    <?php 
    $pageTitle = "Speaker Control";
    include 'common_styles.php';
    ?>
    <style>
        body {
            background-color: black;
            margin: 0;
            padding: 0;
        }

        .speaker-container {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            color: white;
        }

        .speaker-emoji {
            font-size: 100px;
            position: absolute;
            top: 20vh;
            left: 50%;
            transform: translateX(-50%);
        }

        .volume-control {
            position: absolute;
            top: calc(20vh + 180px);
            width: 100%;
            text-align: center;
        }

        .volume-value {
            font-size: 24px;
            margin-bottom: 16px;
            color: white;
            user-select: none;
            pointer-events: none;
        }

        .volume-slider {
            width: 280px;
            height: 4px;
            -webkit-appearance: none;
            background: #555;
            border-radius: 2px;
            margin: 0 auto;
        }

        .volume-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            background: #6200EE;
            border-radius: 50%;
            cursor: pointer;
        }

        .playback-controls {
            position: absolute;
            top: calc(20vh + 300px);
            left: 50%;
            transform: translateX(-50%);
        }

        .control-btn {
            width: 80px;
            height: 80px;
            border-radius: 40px;
            border: none;
            background-color: #6200EE;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
	.no-speaker-message {
		position: absolute;
		top: calc(20vh + 180px);
		width: 100%;
		text-align: center;
		color: white;
		font-size: 24px;
		display: none;
	}
    </style>
</head>
<body>
    <?php include 'common_header.php'; ?>

    <div class="speaker-container">
        <div class="speaker-emoji">🔊</div>
        
        <div class="volume-control" id="volumeControl">
            <div class="volume-value" id="volumeValue">Volume: 50%</div>
            <input type="range"
                   class="volume-slider"
                   min="0"
                   max="100"
		   step="10"
                   value="50"
                   id="volumeSlider">
        </div>

        <div class="playback-controls">
            <button class="control-btn" id="playPauseBtn" onclick="togglePlayPause()">
                ▶
            </button>
        </div>
	<div class="no-speaker-message" id="noSpeakerMessage">
		No speaker found. Please add a device in the settings page.
	</div>
    </div>

	<script src="apiFunctions.js"></script>
	<script>
		let speakerMappingId = null;
		let speakerHardwareId = null;
		document.addEventListener('DOMContentLoaded', function() {
			const userId = '<?php echo $user_id; ?>';
			apiRequest(`/api/devices.php?device_type=speaker&user_id=${userId}`, 'GET', {}, function(result, error) {
				console.log("API result:", result);
				if (error || !result.success || !result.data || result.data.length === 0) {
					console.log(result);
					document.getElementById('volumeControl').style.display = 'none';
					document.getElementById('playPauseBtn').style.display ='none';
					document.getElementById('noSpeakerMessage').style.display = 'block';
					return;
				}
				const device = result.data[0];
				speakerMappingId = device.user_device_id;
				speakerHardwareId = device.hardware_device_id;
				let volume = 50;
				if (device.volume !== undefined && device.volume !== null) {
					volume = device.volume;
				} else if (device.device_settings) {
					try {
						const settings = JSON.parse(device.device_settings);
						if (settings.volume !== undefined) {
							volume = settings.volume;
						}
					} catch (e) {
						console.error("Error parsing device_settings:", e);
					}
				}
				document.getElementById('volumeSlider').value = volume;
				document.getElementById('volumeValue').textContent = "Volume: " + volume + "%";
			});
		});

		document.getElementById('volumeSlider').addEventListener('change', function() {
			const volume = parseInt(this.value);
			setVolume(volume);
		});

		function getMqttTopicForSpeaker() {
			return "device/speaker";
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

		function setVolume(volume) {
			if (!speakerMappingId) return;
			const topic = getMqttTopicForSpeaker();
			publishMqttCommand(topic, "SPEAKER_VOLUME:" + volume);
			const payload = {action: "update", device_id: speakerMappingId, volume: volume};
			apiRequest('/api/devices.php', 'POST', payload, function(result, error) {
				if (error || !result.success) {
					showNotification("Error updating volume: " + (error || result.message), false);
				} else {
					document.getElementById('volumeValue').textContent = "Volume: " + volume + "%";
					showNotification("Volume updated to: " + volume, true);
				}
			});
		}

		function togglePlayPause() {
			const btn = document.getElementById('playPauseBtn');
			let command = "";
			if (btn.textContent.trim() === '▶') {
				btn.textContent = '⏸';
				command = "SPEAKER_ON";
			} else {
				btn.textContent = '▶';
				command = "SPEAKER_OFF";
			}
			const topic = getMqttTopicForSpeaker();
			publishMqttCommand(topic, command);

			const payload = { action: "update", device_id: speakerMappingId, status: command };
    				apiRequest('/api/devices.php', 'POST', payload, function(result, error) {
        			if (error || !result.success) {
            				showNotification("Error updating speaker status: " + (error || result.message), false);
        			} else {
            				showNotification("Speaker status updated to: " + command, true);
        			}
    			});
		}
	</script>

</body>
</html>
