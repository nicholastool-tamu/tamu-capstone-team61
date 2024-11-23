<!-- Main header container -->
<div class="header">
    <!-- Dynamic page title that gets its value from the $pageTitle PHP variable -->
    <h1><?php echo $pageTitle; ?></h1>
    <!-- Hamburger menu button (☰) that calls toggleMenu() when clicked -->
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
</div>

<!-- Sidebar navigation menu container -->
<div class="sidebar" id="sidebar">
    <!-- Navigation links to different pages -->
    <a href="home_dash.php">Dashboard</a>
    <a href="settings.php">Settings</a>
    <a href="usage.php">Usage</a>
</div>

<!-- JavaScript section -->
<script>
    // Function to toggle the 'active' class on the sidebar
    // This likely shows/hides the sidebar when the menu button is clicked
    function toggleMenu() {
        document.getElementById('sidebar').classList.toggle('active');
    }
</script>