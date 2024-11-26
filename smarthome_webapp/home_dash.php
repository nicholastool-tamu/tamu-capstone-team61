<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        <button class="dashboard-btn" onclick="window.location.href='lights.php'">
            Light
        </button>
       
        <button class="dashboard-btn" onclick="window.location.href='sound.php'">
            Speaker
        </button>
        
        <button class="dashboard-btn" onclick="window.location.href='thermo.php'">
            Thermostat
        </button>
    </div>

</body>
</html>
