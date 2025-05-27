<?php
// Database configuration
define('DB_HOST', 'localhost'); // or your host
define('DB_USER', 'root');      // your database username
define('DB_PASS', '');          // your database password
define('DB_NAME', 'city_depository'); // your database name

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// echo "Connected successfully"; // Optional: for testing connection
?>
