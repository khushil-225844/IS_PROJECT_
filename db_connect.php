<?php
// XAMPP default credentials
$servername = "localhost";
$username = "root";       // Default XAMPP username is 'root'
$password = "";           // Default XAMPP password is empty
$dbname = "room_booking_system";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>