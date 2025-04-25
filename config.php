<?php
$host = "localhost";
$user = "root";
$pass = ""; // set your DB password
$dbname = "openclasstutors";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

