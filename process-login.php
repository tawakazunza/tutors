<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, full_name, password FROM tutors WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $tutor = $res->fetch_assoc();
        if (password_verify($password, $tutor['password'])) {
            // Login success
            $_SESSION['tutor_id'] = $tutor['id'];
            $_SESSION['tutor_name'] = $tutor['full_name'];
            header("Location: tutor_dashboard.php");
            exit;
        }
    }

    // Login failed
    $_SESSION['error'] = "Invalid email or password.";
    header("Location: login.php");
    exit;
}
