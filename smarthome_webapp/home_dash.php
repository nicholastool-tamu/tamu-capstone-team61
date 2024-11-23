<?php
// Start a new or resume existing session
session_start();
// Check if user is not logged in, redirect to login page
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Define character encoding and viewport settings -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <?php 
    // Set page title for common header and include shared styles
    $pageTitle = "Dashboard";
    include 'common_styles.php';
    ?>
    <style>
        /* CSS Grid layout for dashboard buttons */
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); /* Responsive grid columns */
            gap: 20px;                /* Space between grid items */
            padding: 20px;            /* Internal spacing */
            max-width: 1200px;        /* Maximum width of dashboard */
            margin: 0 auto;           /* Center the dashboard */
        }

        /* Style for individual dashboard buttons */
        .dashboard-btn {
            padding: 30px;
            border: none;
            border-radius: 10px;          /* Rounded corners */
            background-color: #3498db;    /* Blue background */
            color: white;                 /* White text */
            font-size: 18px;
            cursor: pointer;
            transition: transform 0.2s;    /* Smooth hover animation */
        }

        /* Hover effect for dashboard buttons */
        .dashboard-btn:hover {
            transform: scale(1.05);        /* Slightly enlarge button on hover */
        }
    </style>
</head>
<body>
    <!-- Include common header component -->
    <?php include 'common_header.php'; ?>

    <!-- Main dashboard container with navigation buttons -->
    <div class="dashboard">
        <!-- Light control button -->
        <button class="dashboard-btn" onclick="window.location.href='lights.php'">
            Light
        </button>
        <!-- Speaker control button -->
        <button class="dashboard-btn" onclick="window.location.href='sound.php'">
            Speaker
        </button>
        <!-- Thermostat control button -->
        <button class="dashboard-btn" onclick="window.location.href='thermo.php'">
            Thermostat
        </button>
    </div>

    <!-- Sidebar navigation menu -->
    <div class="sidebar" id="sidebar">
        <a href="settings.php">Settings</a>
        <a href="usage.php">Usage</a>
    </div>

    <script>
        // Function to toggle sidebar visibility
        function toggleMenu() {
            document.getElementById('sidebar').classList.toggle('active');
        }
    </script>
</body>
</html>
