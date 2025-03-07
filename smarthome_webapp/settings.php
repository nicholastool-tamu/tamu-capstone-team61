<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login_page.php");
    exit();
}
$userId =isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Home - Settings</title>
    <?php 
    $pageTitle = "Settings";
    include 'common_styles.php';
    ?>
    <style>
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .section {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .section h2 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }

        .profile-info {
            font-size: 18px;
            color: #555;
        }

        .device-type {
            margin-bottom: 20px;
        }

        .device-button {
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-bottom: 10px;
            text-align: left;
        }

        .device-list {
            display: none;
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
        }

        .device-item {
            padding: 8px;
            margin: 5px 0;
            background-color: white;
            border-radius: 3px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .delete-btn {
            background-color: #ff4444;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 5px 10px;
            cursor: pointer;
        }

        .add-btn {
            background-color: #2196F3;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 8px;
            width: 100%;
            cursor: pointer;
            margin-top: 5px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            width: 300px;
        }

        .modal input {
            width: 100%;
            padding: 8px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 3px;
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 15px;
        }

        .modal-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }

        .confirm-btn {
            background-color: #4CAF50;
            color: white;
        }

        .cancel-btn {
            background-color: #999;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'common_header.php'; ?>
    
    <div class="settings-container">
        <div class="section">
            <h2>Profile</h2>
            <div class="profile-info">
                Username: <?php echo htmlspecialchars($_SESSION['username']); ?>
            </div>
        </div>

        <div class="section">
            <h2>Devices</h2>
            <div class="device-type">
                <button class="device-button" onclick="toggleDeviceList('lights')">Lights</button>
                <div id="lights-list" class="device-list">
                    <div id="lights-items"></div>
                    <button class="add-btn" onclick="showAddDeviceModal('lights')">Add +</button>
                </div>
            </div>

            <div class="device-type">
                <button class="device-button" onclick="toggleDeviceList('speaker')">Speaker</button>
                <div id="speaker-list" class="device-list">
                    <div id="speaker-items"></div>
                    <button class="add-btn" onclick="showAddDeviceModal('speaker')">Add +</button>
                </div>
            </div>

            <div class="device-type">
                <button class="device-button" onclick="toggleDeviceList('thermostat')">Thermostat</button>
                <div id="thermostat-list" class="device-list">
                    <div id="thermostat-items"></div>
                    <button class="add-btn" onclick="showAddDeviceModal('thermostat')">Add +</button>
                </div>
            </div>
        </div>
    </div>

    <div id="addDeviceModal" class="modal">
        <div class="modal-content">
            <h3>Add New Device</h3>
            <input type="text" id="newDeviceName" placeholder="Enter device name">
            <div class="modal-buttons">
                <button class="modal-btn cancel-btn" onclick="hideAddDeviceModal()">Cancel</button>
                <button class="modal-btn confirm-btn" onclick="addDevice()">Add</button>
            </div>
        </div>
    </div>
	<script src="apiFunctions.js"></script>
    <script>
        let currentDeviceType = '';
	const userId = "<?php echo $userId; ?>";
	console.log("user id from session:", userId);
        function toggleDeviceList(type) {
            const list = document.getElementById(`${type}-list`);
            list.style.display = list.style.display === 'block' ? 'none' : 'block';
        }

        function showAddDeviceModal(type) {
            currentDeviceType = type;
            document.getElementById('addDeviceModal').style.display = 'flex';
            document.getElementById('newDeviceName').value = '';
        }

        function hideAddDeviceModal() {
            document.getElementById('addDeviceModal').style.display = 'none';
        }

        function addDevice() {
            const name = document.getElementById('newDeviceName').value.trim();
            if (name) {
            	const payload = {action: "add", device_type: currentDeviceType, device_name: name, user_id: userId, status: "inactive"};
			apiRequest('/api/devices.php', 'POST', payload, function(result, error) {
			if (error || !result.success) {
				showNotification("Error adding device: " + (error || result.message), false);
			} else {
				showNotification("Device added successfully", true)
				fetchDevices(currentDeviceType);
				hideAddDeviceModal();
			}
		});
            }
        }

        function deleteDevice(type, deviceId) {
        	const payload = {action: "delete", device_id: deviceId};
		apiRequest('/api/devices.php', 'DELETE', payload, function(result, error) {
			if (error || !result.success) {
				showNotification("Error deleting device: " + (error || result.message), false)
			} else {
				showNotification("Device deleted successfully", true);
				fetchDevices(type);
			}
		});
        }

        function fetchDevices(type) {
		apiRequest(`/api/devices.php?device_type=${type}&user_id=${userId}`, 'GET', null, function(result,error) {
			console.log("Fetch Devices result:", result);
            		const container = document.getElementById(`${type}-items`);
			if (error) {
				container.innerHTML = `<p>Error loading ${type}.</p>`;
				return;
			}
			if (result.success && result.data) {
            			container.innerHTML = result.data.map(device => `
                			<div class="device-item">
                    			${device.device_name}
                    			<button class="delete-btn" onclick="deleteDevice('${type}', ${device.device_id})">Delete</button>
                			</div>
            			`).join('');
			} else {
				container.innerHTML = `<p>No ${type} found.</p>`;
			}
		});
        }

	document.addEventListener('DOMContentLoaded', function() {
		document.getElementById('lights-list').style.display = 'block';
		fetchDevices('lights');
		fetchDevices('speaker');
		fetchDevices('thermostat');
	});
    </script>
</body>
</html>
