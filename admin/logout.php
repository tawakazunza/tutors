<?php
// logout.php - Logs out the admin user

// Initialize the session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("location: admin-login.php");
exit;
?>