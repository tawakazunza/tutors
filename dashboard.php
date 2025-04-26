<?php
include 'config.php';
session_start();
if (!isset($_SESSION['tutor_id'])) {
    header("Location: tutor-login.php");
    exit;
}
$tutor_id = $_SESSION['tutor_id'];

// Mark notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    
    // Check if this notification applies to this tutor
    $notification = getNotification($conn, $notification_id, $tutor_id);
    
    if ($notification) {
        // Check if already marked as read
        $sql = "SELECT id FROM notification_read_status WHERE notification_id = ? AND tutor_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $notification_id, $tutor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Not marked as read yet, so mark it
            $sql = "INSERT INTO notification_read_status (notification_id, tutor_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $notification_id, $tutor_id);
            $stmt->execute();
        }
        
        // Redirect to avoid resubmission
        header("location: tutor-notifications.php");
        exit;
    }
}

// Get all notifications applicable to this tutor
$notifications = [];

// Get notifications targeted to all tutors
$sql = "SELECT n.*, a.username as admin_name, nrs.read_at
        FROM notifications n
        JOIN admin_users a ON n.created_by = a.id
        LEFT JOIN notification_read_status nrs ON n.id = nrs.notification_id AND nrs.tutor_id = ?
        WHERE n.target_type = 'all'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tutor_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['is_read'] = !is_null($row['read_at']);
    $notifications[] = $row;
}

// Get notifications targeted to active tutors
$sql = "SELECT n.*, a.username as admin_name, nrs.read_at
        FROM notifications n
        JOIN admin_users a ON n.created_by = a.id
        LEFT JOIN notification_read_status nrs ON n.id = nrs.notification_id AND nrs.tutor_id = ?
        JOIN tutors t ON t.id = ?
        WHERE n.target_type = 'active' AND t.account_status = 'active'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $tutor_id, $tutor_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['is_read'] = !is_null($row['read_at']);
    $notifications[] = $row;
}

// Get notifications specifically targeted to this tutor
$sql = "SELECT n.*, a.username as admin_name, nrs.read_at
        FROM notifications n
        JOIN admin_users a ON n.created_by = a.id
        JOIN notification_recipients nr ON n.id = nr.notification_id
        LEFT JOIN notification_read_status nrs ON n.id = nrs.notification_id AND nrs.tutor_id = ?
        WHERE n.target_type = 'specific' AND nr.tutor_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $tutor_id, $tutor_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['is_read'] = !is_null($row['read_at']);
    $notifications[] = $row;
}

// Sort notifications by created_at (newest first)
usort($notifications, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Count unread notifications
$unread_count = 0;
foreach ($notifications as $notification) {
    if (!$notification['is_read']) {
        $unread_count++;
    }
}

// Helper function to check if a notification applies to this tutor
function getNotification($conn, $notification_id, $tutor_id) {
    // First check if the notification exists
    $sql = "SELECT target_type FROM notifications WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $notification_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $notification = $result->fetch_assoc();
    
    // Check if this notification applies to this tutor
    if ($notification['target_type'] == 'all') {
        return $notification;
    } else if ($notification['target_type'] == 'active') {
        // Check if tutor is active
        $sql = "SELECT id FROM tutors WHERE id = ? AND account_status = 'active'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $tutor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $notification;
        }
    } else if ($notification['target_type'] == 'specific') {
        // Check if tutor is in recipients
        $sql = "SELECT id FROM notification_recipients WHERE notification_id = ? AND tutor_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $notification_id, $tutor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $notification;
        }
    }
    
    return null;
}


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

$stmt->close();
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
    <title>Tutor Dashboard | Openclass </title>

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
    <style>
        .notification-item {
            border-left: 4px solid #e9ecef;
            transition: all 0.2s ease;
        }
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        .notification-item.unread {
            border-left: 4px solid #0F1E8A;
            background-color: rgba(15, 30, 138, 0.05);
        }
        .notification-item.important {
            border-left: 4px solid #dc3545;
        }
        .notification-date {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
    </style>

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
                            <a href="notifications.php"><i class="zmdi zmdi-notifications"></i></a>
                            <span class="notification-badge"><?= $unread_count ?></span>
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
                                <p class="text-white">Here's what's happening with your tutoring profile today.</p>
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
                                        <li class="active has-sub">
                                            <a class="js-arrow" href="dashboard.php">
                                                <i class="fas fa-tachometer-alt"></i>Dashboard
                                            </a>
                                        </li>
                                        <li>
                                        <a href="notifications.php">
                                            <i class="fas fa-chart-bar"></i>Notifications</a>
                                        <span class="notification-badge"><?= $unread_count ?></span>
                                        </li>
                                        <li>
                                            <a href="reviews.php">
                                            <i class="fas fa-star"></i>Reviews</a>
                                        </li>
                                        <li>
                                            <a href="profile.php">
                                                <i class="fas fa-user"></i>Profile</a>
                                        </li>
                                        <li>
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
                                    <div class="col-sm-4">
                                        <section class="card text-center">
                                            <div style="background-color:#0F1E8A;" class="card-header">
                                            <h4 class="text-muted text-white">Profile Views</h4>
                                            </div>
                                            <div class="card-body text-secondary">
                                                <div>
                                                    <h3 class="mb-0"><?= $view_stats['unique_views'] ?? 0 ?></h3>
                                                    <small class="text-muted">Total Views</small>
                                                </div>
                                            </div>
                                        </section>
                                    </div>
                                    <div class="col-sm-4">
                                        <section class="card text-center">
                                            <div style="background-color:#0F1E8A;" class="card-header stat-text"><h4 class="text-white">Total Reviews</h4></div>
                                            <div class="card-body text-secondary">
                                                <div class="stat-digit">
                                                    <h3 class="mb-0"><?= $rating_count ?></h3>
                                                    <i class="fas fa-comments text-primary"></i>
                                                </div>
                                            </div>
                                        </section>
                                    </div>
                                    <div class="col-sm-4">
                                        <section class="card text-center">
                                            <div style="background-color:#0F1E8A;" class="card-header stat-text"><h4 class="text-white">Average Rating</h4></div>
                                            <div class="card-body text-secondary">
                                                <div class="stat-digit">
                                                    <?php if ($rating_count > 0): ?>
                                                        <div class="rating-stars">
                                                            <?php
                                                            // Display stars based on rating
                                                            $full_stars = floor($avg_rating);
                                                            $half_star = ($avg_rating - $full_stars) >= 0.5;

                                                            // Display full stars
                                                            for ($i = 1; $i <= $full_stars; $i++) {
                                                                echo '<i class="fas fa-star text-warning"></i>';
                                                            }

                                                            // Display half star if needed
                                                            if ($half_star) {
                                                                echo '<i class="fas fa-star-half-alt text-warning"></i>';
                                                                $i++;
                                                            }

                                                            // Display empty stars
                                                            for (; $i <= 5; $i++) {
                                                                echo '<i class="far fa-star text-warning"></i>';
                                                            }
                                                            ?>
                                                            <h4 class="mb-0"><?= $avg_rating ?> / 5</h4>
                                                            <div class="small text-muted">(<?= $rating_count ?> reviews)</div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-muted">No ratings yet</div>
                                                    <?php endif; ?>
                                                </div>

                                            </div>
                                        </section>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col col-lg-12">
                                        <section class="card">
                                            <div class="card-header">Activity Chart</div>
                                            <div class="card-body text-secondary">
                                                <h3>Coming Soon....</h3>
                                            </div>
                                        </section>
                                    </div>
                                </div>


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
