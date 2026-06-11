<?php
// 1. Resume the current session
session_start();

// 2. Remove all session variables
session_unset();

// 3. Destroy the session completely
session_destroy();

// 4. Redirect the user back to the login screen
header("Location: index.php");
exit();
?>