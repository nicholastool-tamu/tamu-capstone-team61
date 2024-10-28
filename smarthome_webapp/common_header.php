<!-- Create a new file for the common header elements -->
<div class="header">
    <button class="back-btn" onclick="window.location.href='home_dash.php'">🏠</button>
    <h1><?php echo $pageTitle; ?></h1>
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
</div>

<div class="sidebar" id="sidebar">
    <a href="home_dash.php">Dashboard</a>
    <a href="settings.php">Settings</a>
    <a href="usage.php">Usage</a>
</div>

<script>
    function toggleMenu() {
        document.getElementById('sidebar').classList.toggle('active');
    }
</script>