<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login_page.php");
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

	<script src="apiFunctions.js"></script>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const userId = '<?php echo $user_id; ?>';
			apiRequest('/api/devices.php?device_type=light&user_id=${user_id}', 'GET', {}, function(result, error) {
				const deviceList = document.getElementById('lightList');
				deviceList.innerHTML = '<p>No devices found!</p>';
				if (error) {
					showNotification("Error fetching devices: " + error, false);
					return;
				}
				if (result.success && result.data && result.data.length > 0) {
					result.data.forEach(device => {
						const deviceItem = document.createElement('div');
						deviceItem.className = 'device-item';
						deviceItem.innerHTML = `<div>${device.device_name} - Status: <span id="status-${device.device_id}">${device.status}</span></div>
							<button onclick="toggleLight('${device.device_id}', '$device.status}')">Toggle Light</button>
							<input type="range" min="0" max="100" value="${device.brightness}" onchange="setBrightness('${device.device_id}', this.value)">
						`;
						deviceList.appendChild(deviceItem);
					});
				} else {
					deviceList.innerHtml = '<p>No lights found.</p>';
				}
			});
		});

		function toggleLight(deviceId, currentStatus) {
			const newStatus = currentStatus === 'on' ? 'off' : 'on';
			const payload = {action: "update", device_id: deviceId, status: newStatus};
			apiRequest('/api/devices.php', 'POST', payload, function(result, error) {
				if (error || !result.success) {
					showNotification("Error updating light: " + (error || result.message), false);
				} else {
					document.getElementById('status-${deviceId}').textContent = newStatus;
					showNotification("Light status updated to: " + newStatus, true);
				}
			});
		}

		function setBrightness(deviceId, brightness) {
			const payload = {action: "update", device_id: deviceId, brightness: brightness};
			apiRequest('/api/devices.php', 'POST', payload, function(result, error) {
				if (error || !result.success) {
					showNotification("Error updating brightness: " + (error || result.message), false);
				} else {
					showNotification("Brightness updated to: " + brightness, true);
				}
			});
		}
	</script>
</body>
</html>
