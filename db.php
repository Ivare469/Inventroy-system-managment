<?php
// db.php - Silent Database Connection
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "inventory_db";

// Suppress default error reporting to prevent HTML leakage
mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli($host, $user, $pass, $dbname);

// We check for errors in api.php instead of using 'die()' here
?>