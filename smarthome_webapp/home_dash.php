<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    header("Location: login_page.php");
    exit();
}
require_once '/var/www/backend/includes/functions.php';
enforceSessionCheck();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
	<meta http-equiv="Pragma" content="no-cache" />
	<meta http-equiv=Expires" content="0" />
	<script>
	window.addEventListener('pageshow', function(event) {
		if (event.persisted) {
			window.location.reload();
		}
	});
	</script>
    <title>Dashboard</title>
    <?php 
    $pageTitle = "Dashboard";
    include 'common_styles.php';
    ?>
    <style>
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px;                
            padding: 20px;            
            max-width: 1200px;        
            margin: 0 auto;           
        }

        
        .dashboard-btn {
            padding: 30px;
            border: none;
            border-radius: 10px;          
            background-color: #3498db;   
            color: white;                
            font-size: 18px;
            cursor: pointer;
            transition: transform 0.2s;    
        }

        
        .dashboard-btn:hover {
            transform: scale(1.05);        
        }
    </style>
</head>
<body>
    <?php include 'common_header.php'; ?>


    <div class="dashboard">
        <button class="dashboard-btn" onclick="window.location.href='lights.php?username=<?php echo $_SESSION['username']; ?>'">
            Light
        </button>
       
        <button class="dashboard-btn" onclick="window.location.href='sound.php?username=<?php echo $_SESSION['username']; ?>'">
            Speaker
        </button>
        
        <button class="dashboard-btn" onclick="window.location.href='thermo.php?username=<?php echo $_SESSION['username']; ?>'">
            Thermostat
        </button>
    </div>

</body>
</html>
