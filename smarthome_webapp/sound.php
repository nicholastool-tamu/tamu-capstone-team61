<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
	header("location: login.php");
	exit();
}

$username = $_GET['username'] ?? null;

require_once '/var/www/backend/includes/databaseConnection.php';

$user_id = null;
if ($username) {
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
    </style>
</head>
<body>
    <?php include 'common_header.php'; ?>

    <div class="speaker-container">
        <div class="speaker-emoji">🔊</div>
        
        <div class="volume-control">
            <div class="volume-value" id="volumeValue">Volume: 50%</div>
            <input type="range" 
                   class="volume-slider" 
                   min="0" 
                   max="100" 
                   value="50" 
                   id="volumeSlider">
        </div>

        <div class="playback-controls">
            <button class="control-btn" id="playPauseBtn" onclick="togglePlayPause()">
                ▶
            </button>
        </div>
    </div>

	<script src="apiFunctions.js"></script>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const userId = '';
			apiRequest('/api/devices.php?device_type=speaker&userId=${userId}', 'GET', {}, function(result, error) {
				const deviceList = document.getElementById('deviceList');
				deviceList.innerHTML = 'No devices found!';
				if (error) {
					showNotification("Error fetching devices: " + error, false);
					return;
				}
				if (result.success && result.data && result.data.length > 0) {
					result.data.forEach(device => {
						const deviceItem = document.createElement('div');
						deviceItem.className = 'device-item';
						deviceItem.innerHTML = `
							<div>${device.device_name} - Volume: <span id="volume-${device.device_id}">${device.volume}</span>%</div>
							<input type="range" min="0" max="100" value="${device.volume}" onchange="setVolume('${device.device_id}', this.value)">
						`;
						deviceList.appendChild(deviceItem);
					});
				} else {
					deviceList.innerHTML = '<p>No speakers found.</p>';
				}
			});
		});

		function setVolume(deviceId, volume) {
			const payload = {action: "update", device_id: deviceId, volume: volume};
			apiRequest('/api/devices.php', 'POST', payload, function(result, error) {
				if (error || !result.success) {
					showNotification("Error updating volume: " + (error || result.message), false);
				} else {
					document.getElementById(`volume-${deviceId}`).textContent = volume;
					showNotification("Volume updated to: " + volume, true);
				}
			});
		}
	</script>

</body>
</html>
