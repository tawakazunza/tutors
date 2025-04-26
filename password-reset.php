<?php
session_start();
include 'config.php';

$error = $success = '';
$token = $_GET['token'] ?? '';

// Verify token
if (!empty($token)) {
    $stmt = $conn->prepare("SELECT id, reset_token_expiry FROM tutors WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $tutor = $result->fetch_assoc();
        $now = date("Y-m-d H:i:s");
        
        if ($tutor['reset_token_expiry'] < $now) {
            $error = "This reset link has expired. Please request a new one.";
            $token = ''; // Invalidate token
        }
    } else {
        $error = "Invalid reset link. Please request a new one.";
        $token = ''; // Invalidate token
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($token)) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters";
    } else {
        // Update password and clear token
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $null = null;
        
        $update = $conn->prepare("UPDATE tutors SET password = ?, reset_token = ?, reset_token_expiry = ? WHERE reset_token = ?");
        $update->bind_param("ssss", $hashed_password, $null, $null, $token);
        
        if ($update->execute()) {
            $success = "Password updated successfully! You can now <a href='login.php'>login</a>.";
        } else {
            $error = "Failed to update password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags-->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Find trusted tutors in Zimbabwe for ECD to A Level. Learn via WhatsApp, Zoom, or in-person. Free listing for tutors. Get started today!">
    <meta property="og:image" content="https://openclass.co.zw/tutors/images/openclass-tutor.webp">
    <meta property="og:url" content="https://openclass.co.zw/tutors/">
    <meta name="author" content="Tay Digital">
    <meta name="keywords" content="OpenClass Tutors">

    <!-- Title Page-->
    <title>Openclass | Tutor Login</title>

    <!-- Fontfaces CSS-->
    <link href="css/font-face.css" rel="stylesheet" media="all">
    <link href="vendor/font-awesome-4.7/css/font-awesome.min.css" rel="stylesheet" media="all">
    <link href="vendor/font-awesome-5/css/fontawesome-all.min.css" rel="stylesheet" media="all">
    <link href="vendor/mdi-font/css/material-design-iconic-font.min.css" rel="stylesheet" media="all">

    <!-- Bootstrap CSS-->
    <link href="vendor/bootstrap-4.1/bootstrap.min.css" rel="stylesheet" media="all">

    <!-- Vendor CSS-->
    <link href="vendor/animsition/animsition.min.css" rel="stylesheet" media="all">
    <link href="vendor/bootstrap-progressbar/bootstrap-progressbar-3.3.4.min.css" rel="stylesheet" media="all">
    <link href="vendor/wow/animate.css" rel="stylesheet" media="all">
    <link href="vendor/css-hamburgers/hamburgers.min.css" rel="stylesheet" media="all">
    <link href="vendor/slick/slick.css" rel="stylesheet" media="all">
    <link href="vendor/select2/select2.min.css" rel="stylesheet" media="all">
    <link href="vendor/perfect-scrollbar/perfect-scrollbar.css" rel="stylesheet" media="all">

    <!-- Main CSS-->
    <link href="css/theme.css" rel="stylesheet" media="all">

</head>
<body class="animsition">
    <div class="page-wrapper">
        <div class="page-content--bge5">
            <div class="container">
                <div class="login-wrap">
                    <div class="login-content">
                        <div class="login-logo">
                            <a href="#">
                                <img src="images/openclass.webp" alt="Openclass" width="300px" height="150px">
                            </a>
                        </div>
                        <div class="login-form">
                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($success)): ?>
                                <div class="alert alert-success"><?= $success ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($token)): ?>
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label>New Password</label>
                                        <input type="password" name="password" class="form-control" required minlength="8">
                                    </div>
                                    <div class="mb-3">
                                        <label>Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control" required minlength="8">
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                                </form>
                            <?php else: ?>
                                <p>Please check your email for a valid reset link or <a href="reset_request.php">request a new one</a>.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
                 <!-- Jquery JS-->
                 <script src="vendor/jquery-3.2.1.min.js"></script>
    <!-- Bootstrap JS-->
    <script src="vendor/bootstrap-4.1/popper.min.js"></script>
    <script src="vendor/bootstrap-4.1/bootstrap.min.js"></script>
    <!-- Vendor JS       -->
    <script src="vendor/slick/slick.min.js">
    </script>
    <script src="vendor/wow/wow.min.js"></script>
    <script src="vendor/animsition/animsition.min.js"></script>
    <script src="vendor/bootstrap-progressbar/bootstrap-progressbar.min.js">
    </script>
    <script src="vendor/counter-up/jquery.waypoints.min.js"></script>
    <script src="vendor/counter-up/jquery.counterup.min.js">
    </script>
    <script src="vendor/circle-progress/circle-progress.min.js"></script>
    <script src="vendor/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="vendor/chartjs/Chart.bundle.min.js"></script>
    <script src="vendor/select2/select2.min.js">
    </script>

    <!-- Main JS-->
    <script src="js/main.js"></script>
</body>
</html>