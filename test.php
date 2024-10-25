<?php
// Display the current date and time
echo "<h1>Welcome to the Smart Home!</h1>";
echo "<p>The current date and time is: <strong>" . date("Y-m-d H:i:s") . "</strong></p>";

// Display server information
echo "<h2>Server Information:</h2>";
echo "<ul>";
echo "<li>Server IP: " . $_SERVER['SERVER_ADDR'] . "</li>";
echo "<li>Your IP: " . $_SERVER['REMOTE_ADDR'] . "</li>";
echo "<li>Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</li>";
echo "<li>PHP Version: " . phpversion() . "</li>";
echo "</ul>";

// Generate a random number
echo "<h2>Random Number Generator:</h2>";
echo "<p>Your lucky number is: <strong>" . rand(1, 100) . "</strong></p>";

// Simple form to collect user input
if (isset($_POST['name'])) {
    $name = htmlspecialchars($_POST['name']);
    echo "<h2>Hello, $name!</h2>";
} else {
    echo '
    <h2>Tell Us Your Name:</h2>
    <form method="post">
        <input type="text" name="name" placeholder="Enter your name" required>
        <input type="submit" value="Submit">
    </form>
    ';
}

// Simple JSON data output
$data = array("status" => "success", "message" => "PHP is working!");
echo "<h2>JSON Data:</h2>";
echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
?>
