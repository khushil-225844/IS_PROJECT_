<?php
// DEFENSE NOTE: Environment Configuration
// Why? These are the credentials required to connect to our local XAMPP/WAMP stack database. 
// In a real-world production environment, we would never leave the password blank. We would use environment variables (.env files) to hide a highly secure password and create a specific MySQL user with restricted privileges instead of using the 'root' superuser.
$servername = "localhost";
$username = "root";       // Default XAMPP username is 'root'
$password = "";           // Default XAMPP password is empty
$dbname = "room_booking_system";

// DEFENSE NOTE: Database Connection API
// Why? We are using the MySQLi (MySQL Improved) extension in an Object-Oriented format (new mysqli). 
// We chose MySQLi over older, deprecated mysql functions because it supports prepared statements, which are our primary defense against SQL Injection attacks.
$conn = new mysqli($servername, $username, $password, $dbname);

// DEFENSE NOTE: Fail-Safe Error Handling
// Why? This checks the object property 'connect_error'. If the database server is offline or the credentials are wrong, the die() function immediately stops the execution of the entire PHP script. 
// This is a critical security measure. If we didn't stop execution here, the rest of the application would try to run database queries, which would cause ugly fatal errors that could reveal sensitive system information to hackers.
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>