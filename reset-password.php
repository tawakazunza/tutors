<?php
include 'config.php';
session_start();
if (!isset($_SESSION['tutor_id'])) {
    header("Location: tutor-login.php");
    exit;
}

// Initialize message variables
$message = '';
$message_class = '';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $tutor_id = $_SESSION['tutor_id'];

    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = "All fields are required";
        $message_class = "alert-danger";
    } elseif ($new_password !== $confirm_password) {
        $message = "New passwords do not match";
        $message_class = "alert-danger";
    } elseif (strlen($new_password) < 8) {
        $message = "Password must be at least 8 characters long";
        $message_class = "alert-danger";
    } else {
        // Verify current password
        $sql = "SELECT password FROM tutors WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $tutor_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Verify the current password (assuming passwords are hashed)
            if (password_verify($current_password, $row['password'])) {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // Update the password
                $update_sql = "UPDATE tutors SET password = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $hashed_password, $tutor_id);

                if ($update_stmt->execute()) {
                    $message = "Password updated successfully";
                    $message_class = "alert-success";
                } else {
                    $message = "Error updating password: " . $conn->error;
                    $message_class = "alert-danger";
                }

                $update_stmt->close();
            } else {
                $message = "Current password is incorrect";
                $message_class = "alert-danger";
            }
        } else {
            $message = "Error retrieving user data";
            $message_class = "alert-danger";
        }

        $stmt->close();
    }
}



$tutor_id = $_SESSION['tutor_id'];

// Get tutor profile information
$sql = "SELECT profile_picture, email FROM tutors WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tutor_id);
$stmt->execute();
$result = $stmt->get_result();

$profile_picture = "uploads/avatar-default.jpg"; // Default image

if ($row = $result->fetch_assoc()) {
    if (!empty($row['profile_picture'])) {
        $profile_picture = "uploads/" . htmlspecialchars($row['profile_picture']);
    }
    $tutor_email = htmlspecialchars($row['email']);
}
$stmt->close(); // Close the first statement

// Get average rating
$avg_rating = 0;
$rating_count = 0;

$sql = "SELECT AVG(rating) as average_rating, COUNT(id) as rating_count FROM reviews WHERE tutor_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tutor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Check if average_rating is NULL before formatting
    if ($row['average_rating'] !== NULL) {
        $avg_rating = number_format($row['average_rating'], 1); // Format to 1 decimal place
    } else {
        $avg_rating = "0.0"; // Default value when no ratings exist
    }
    $rating_count = $row['rating_count'];
}
$stmt->close(); // Close the second statement

// Get profile view statistics
$stats_stmt = $conn->prepare("
    SELECT 
        total_views, 
        unique_views,
        (SELECT COUNT(DISTINCT ip_address) FROM profile_view_history WHERE tutor_id = ?) AS unique_visitors
    FROM profile_views 
    WHERE tutor_id = ?
");
$stats_stmt->bind_param("ii", $_SESSION['tutor_id'], $_SESSION['tutor_id']);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$view_stats = $stats_result->fetch_assoc();
$stats_stmt->close(); // Close the stats statement

// Finally, close the connection
$conn->close();
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
    <title>Reset Password | Openclass</title>

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
    <!-- HEADER DESKTOP-->
    <header class="header-desktop4">
        <div class="container">
            <div class="header4-wrap">
                <div class="header__logo">
                    <a href="#">
                        <img src="images/openclass.webp" alt="Openclass" width="200" height="60" />
                    </a>
                </div>
                <div class="header__tool">
                    <div class="header-button-item has-noti js-item-menu">
                        <i class="zmdi zmdi-notifications"></i>
                        <div class="notifi-dropdown js-dropdown">
                            <div class="notifi__title">
                                <p>You have 3 Notifications</p>
                            </div>
                            <div class="notifi__item">
                                <div class="bg-c1 img-cir img-40">
                                    <i class="zmdi zmdi-email-open"></i>
                                </div>
                                <div class="content">
                                    <p>You got a email notification</p>
                                    <span class="date">April 12, 2018 06:50</span>
                                </div>
                            </div>
                            <div class="notifi__item">
                                <div class="bg-c2 img-cir img-40">
                                    <i class="zmdi zmdi-account-box"></i>
                                </div>
                                <div class="content">
                                    <p>Your account has been blocked</p>
                                    <span class="date">April 12, 2018 06:50</span>
                                </div>
                            </div>
                            <div class="notifi__item">
                                <div class="bg-c3 img-cir img-40">
                                    <i class="zmdi zmdi-file-text"></i>
                                </div>
                                <div class="content">
                                    <p>You got a new file</p>
                                    <span class="date">April 12, 2018 06:50</span>
                                </div>
                            </div>
                            <div class="notifi__footer">
                                <a href="#">All notifications</a>
                            </div>
                        </div>
                    </div>

                    <div class="account-wrap">
                        <div class="account-item account-item--style2 clearfix js-item-menu">
                            <div class="image">
                                <img src="<?= $profile_picture ?>" class="rounded-circle img-thumbnail profile-pic mb-2" alt="Profile Picture" style="width: 50px; height: 50px; object-fit: cover;">
                            </div>
                            <div class="content">
                                <a class="js-acc-btn" href="#"><?= htmlspecialchars($_SESSION['tutor_name']) ?></a>
                            </div>
                            <div class="account-dropdown js-dropdown">
                                <div class="info clearfix">
                                    <div class="image">
                                        <a href="#">
                                            <img src="<?= $profile_picture ?>" class="rounded-circle img-thumbnail profile-pic mb-2" alt="Profile Picture" style="width: 80px; height: 80px; object-fit: cover;">
                                        </a>
                                    </div>
                                    <div class="content">
                                        <h5 class="name">
                                            <a href="#"><?= htmlspecialchars($_SESSION['tutor_name']) ?></a>
                                        </h5>
                                        <span class="email"><?= $tutor_email ?></span>
                                    </div>
                                </div>
                                <div class="account-dropdown__body">
                                    <div class="account-dropdown__item">
                                        <a href="profile.php?id=<?= $_SESSION['tutor_id'] ?>">
                                            <i class="zmdi zmdi-account"></i>Account</a>
                                    </div>
                                    <div class="account-dropdown__item">
                                        <a href="#">
                                            <i class="zmdi zmdi-settings"></i>Setting</a>
                                    </div>
                                    <div class="account-dropdown__item">
                                        <a href="#">
                                            <i class="zmdi zmdi-money-box"></i>Billing</a>
                                    </div>
                                </div>
                                <div class="account-dropdown__footer">
                                    <a href="logout.php">
                                        <i class="zmdi zmdi-power"></i>Logout</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <!-- END HEADER DESKTOP -->
    <!-- WELCOME-->
    <section style="background-color:#0F1E8A;" class="welcome2 p-t-40 p-b-55">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <div class="au-breadcrumb3">
                        <div class="au-breadcrumb-left text-white">
                            <span class="au-breadcrumb-span">You are here:</span>
                            <ul class="list-unstyled list-inline au-breadcrumb__list">
                                <li class="list-inline-item active">
                                    <a href="#">Home</a>
                                </li>
                                <li class="list-inline-item seprate">
                                    <span>/</span>
                                </li>
                                <li class="list-inline-item">Dashboard</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="welcome2-inner m-t-60">
                        <div class="welcome2-greeting">
                            <h1 class="title-6">Hi
                                <span><?= htmlspecialchars($_SESSION['tutor_name']) ?></span>, Welcome back</h1>
                            <p class="text-white">Reset your login credentials.</p>
                        </div>
                        <form class="form-header form-header2" action="" method="post">
                            <input class="au-input au-input--w435" type="text" name="search" placeholder="Search...">
                            <button class="au-btn--submit" type="submit">
                                <i class="zmdi zmdi-search"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- END WELCOME-->
    <!-- PAGE CONTENT-->
    <div class="page-container3">
        <section class="alert-wrap p-t-70 p-b-70">

        </section>
        <section>
            <div class="container">
                <div class="row">
                    <div class="col-xl-3">
                        <!-- MENU SIDEBAR-->
                        <aside class="menu-sidebar3 js-spe-sidebar">
                            <nav class="navbar-sidebar2 navbar-sidebar3">
                                <ul class="list-unstyled navbar__list">
                                    <li>
                                        <a class="js-arrow" href="dashboard.php">
                                            <i class="fas fa-tachometer-alt"></i>Dashboard
                                        </a>
                                    </li>
                                    <li>
                                        <a href="inbox.php">
                                            <i class="fas fa-chart-bar"></i>Inbox</a>
                                        <span class="inbox-num">3</span>
                                    </li>
                                    <li>
                                        <a href="reviews.php">
                                            <i class="fas fa-star"></i>Reviews</a>
                                    </li>
                                    <li>
                                        <a href="profile.php">
                                            <i class="fas fa-user"></i>Profile</a>
                                    </li>
                                    <li class="active has-sub">
                                        <a href="reset-password.php">
                                            <i class="fas fa-lock"></i>Reset Password</a>
                                    </li>
                                </ul>
                            </nav>
                        </aside>
                        <!-- END MENU SIDEBAR-->
                    </div>
                    <div class="col-xl-9">
                        <!-- PAGE CONTENT-->
                        <div class="page-content">
                            <div class="row">
                                <div class="col col-lg-12">
                                    <section class="card">
                                        <!-- HTML Form -->
                                            <div style="background-color: #0F1E8A;" class="card-header text-white">
                                                <strong>Reset Password</strong>
                                            </div>
                                            <div class="card-body card-block">
                                                <?php if (!empty($message)): ?>
                                                    <div class="alert <?= $message_class ?> alert-dismissible fade show" role="alert">
                                                        <?= $message ?>
                                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                <?php endif; ?>

                                                <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="form-horizontal">
                                                    <div class="row form-group">
                                                        <div class="col col-md-3">
                                                            <label for="current_password" class="form-control-label">Current Password</label>
                                                        </div>
                                                        <div class="col-12 col-md-9">
                                                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                                                        </div>
                                                    </div>

                                                    <div class="row form-group">
                                                        <div class="col col-md-3">
                                                            <label for="new_password" class="form-control-label">New Password</label>
                                                        </div>
                                                        <div class="col-12 col-md-9">
                                                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                                                            <small class="form-text text-muted">Password must be at least 8 characters long</small>
                                                        </div>
                                                    </div>

                                                    <div class="row form-group">
                                                        <div class="col col-md-3">
                                                            <label for="confirm_password" class="form-control-label">Confirm Password</label>
                                                        </div>
                                                        <div class="col-12 col-md-9">
                                                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-actions form-group">
                                                        <button type="submit" class="btn btn-primary btn-sm">Update Password</button>
                                                    </div>
                                                </form>
                                            </div>

                                    </section>
                                </div>
                            </div></div>


                            <div class="row">
                                <div class="col-md-12">
                                    <div class="copyright">
                                        <p>Copyright © 2025 Openclass. All rights reserved. <a href="https://openclass.co.zw">Openclass</a>.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- END PAGE CONTENT-->
                    </div>
                </div>
            </div>
        </section>
    </div>
    <!-- END PAGE CONTENT  -->
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
