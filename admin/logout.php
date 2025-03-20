<?php
session_start();
session_unset(); // Clear all session variables
session_destroy(); // Destroy the session

// Redirect to admin login page
header("Location: login.php");
exit();
?>
