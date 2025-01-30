
<div class="header">
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