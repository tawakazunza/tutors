<?php
// Include database configuration
require_once 'config.php';
// Start session
session_start();
// dashboard.php - Admin dashboard after login
// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.php");
    exit;
}

$admin_id = $_SESSION['id'];

// Get admin profile picture
$sql = "SELECT profile_pic, full_name, email FROM admin_users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

$profile_picture = "uploads/profile/avatar-default.jpg"; // Default image
$admin_data = $result->fetch_assoc();

if ($admin_data) {
    if (!empty($admin_data['profile_pic'])) {
        $profile_picture = "uploads/profile/" . htmlspecialchars($admin_data['profile_pic']);
    }
    $_SESSION['full_name'] = $admin_data['full_name'] ?? $_SESSION['username'];
    $_SESSION['email'] = $admin_data['email'] ?? '';
}


// Get all notifications
$notifications = [];
$sql = "SELECT n.*, a.username as admin_name, 
        (SELECT COUNT(*) FROM notification_recipients WHERE notification_id = n.id) as recipient_count
        FROM notifications n
        JOIN admin_users a ON n.created_by = a.id
        ORDER BY n.created_at DESC";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

// Get notification statistics
$stats = [
    'total' => 0,
    'read' => 0,
    'unread' => 0,
    'important' => 0
];

// Total notifications
$sql = "SELECT COUNT(*) as count FROM notifications";
$result = $conn->query($sql);
$stats['total'] = $result->fetch_assoc()['count'];

// Important notifications
$sql = "SELECT COUNT(*) as count FROM notifications WHERE is_important = 1";
$result = $conn->query($sql);
$stats['important'] = $result->fetch_assoc()['count'];

// Read notifications (approximation based on read receipts)
$sql = "SELECT COUNT(DISTINCT notification_id) as count FROM notification_read_status";
$result = $conn->query($sql);
$stats['read'] = $result->fetch_assoc()['count'];

// Calculate unread
$stats['unread'] = $stats['total'] - $stats['read'];
if ($stats['unread'] < 0) $stats['unread'] = 0;


// Get statistics
$stats = [
    'total_tutors' => 0,
    'active_tutors' => 0,
    'pending_tutors' => 0,
    'total_cities' => 0,
    'total_subjects' => 0
];

// Get recent activities (last 5)
$recent_activities = [];
$sql = "SELECT * FROM admin_logs ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $recent_activities[] = $row;
}

// Get recent tutor registrations (last 5)
$recent_tutors = [];
$sql = "SELECT id, full_name, created_at FROM tutors ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $recent_tutors[] = $row;
}

// Total tutors
$sql = "SELECT COUNT(*) as count FROM tutors";
$result = $conn->query($sql);
$stats['total_tutors'] = $result->fetch_assoc()['count'];

// Active tutors
$sql = "SELECT COUNT(*) as count FROM tutors WHERE account_status = 'active'";
$result = $conn->query($sql);
$stats['active_tutors'] = $result->fetch_assoc()['count'];

// Pending tutors
$sql = "SELECT COUNT(*) as count FROM tutors WHERE account_status = 'pending'";
$result = $conn->query($sql);
$stats['pending_tutors'] = $result->fetch_assoc()['count'];

// Total cities
$sql = "SELECT COUNT(*) as count FROM cities";
$result = $conn->query($sql);
$stats['total_cities'] = $result->fetch_assoc()['count'];

// Total subjects
$sql = "SELECT COUNT(*) as count FROM subjects";
$result = $conn->query($sql);
$stats['total_subjects'] = $result->fetch_assoc()['count'];



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Required meta tags-->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Administration Dashboard">
    <meta name="keywords" content="Openclass Tutors">

    <!-- Title Page-->
    <title>Openclass | Admin Dashboard</title>

    <!-- Fontfaces CSS-->
    <link href="../css/font-face.css" rel="stylesheet" media="all">
    <link href="../vendor/font-awesome-4.7/css/font-awesome.min.css" rel="stylesheet" media="all">
    <link href="../vendor/font-awesome-5/css/fontawesome-all.min.css" rel="stylesheet" media="all">
    <link href="../vendor/mdi-font/css/material-design-iconic-font.min.css" rel="stylesheet" media="all">

    <!-- Bootstrap CSS-->
    <link href="../vendor/bootstrap-4.1/bootstrap.min.css" rel="stylesheet" media="all">

    <!-- Vendor CSS-->
    <link href="../vendor/animsition/animsition.min.css" rel="stylesheet" media="all">
    <link href="../vendor/bootstrap-progressbar/bootstrap-progressbar-3.3.4.min.css" rel="stylesheet" media="all">
    <link href="../vendor/wow/animate.css" rel="stylesheet" media="all">
    <link href="../vendor/css-hamburgers/hamburgers.min.css" rel="stylesheet" media="all">
    <link href="../vendor/slick/slick.css" rel="stylesheet" media="all">
    <link href="../vendor/select2/select2.min.css" rel="stylesheet" media="all">
    <link href="../vendor/perfect-scrollbar/perfect-scrollbar.css" rel="stylesheet" media="all">

    <!-- Main CSS-->
    <link href="../css/theme.css" rel="stylesheet" media="all">

    <style>
        .stat-card {
            transition: all 0.3s ease;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .bg-tutors {
            background: linear-gradient(45deg, #0F1E8A, #1a3af5);
        }
        .bg-active {
            background: linear-gradient(45deg, #28a745, #5cb85c);
        }
        .bg-pending {
            background: linear-gradient(45deg, #ffc107, #ffd351);
        }
        .bg-cities {
            background: linear-gradient(45deg, #17a2b8, #5bc0de);
        }
        .bg-subjects {
            background: linear-gradient(45deg, #6f42c1, #9b59b6);
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
                        <img src="../images/openclass.webp" alt="Openclass" width="200" height="60" />
                    </a>
                </div>
                <div class="header__tool">
                    <div class="header-button-item has-noti js-item-menu">
                        <i class="zmdi zmdi-notifications"></i>
                        <div class="notifi-dropdown js-dropdown">
                            <div class="notifi__title">
                                <p>You have <?= $stats['unread'] ?? '0'; ?> Unread Notifications</p>
                            </div>
                            <?php foreach(array_slice($notifications, 0, 3) as $notif): ?>
                                <div class="notifi__item">
                                    <div class="bg-c1 img-cir img-40">
                                        <i class="zmdi zmdi-email-open"></i>
                                    </div>
                                    <div class="content">
                                        <p><?= htmlspecialchars($notif['subject']) ?></p>
                                        <span class="date"><?= date('F j, Y H:i', strtotime($notif['created_at'])) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="notifi__footer">
                                <a href="notifications.php">All notifications</a>
                            </div>
                        </div>
                    </div>

                    <div class="account-wrap">
                        <div class="account-item account-item--style2 clearfix js-item-menu">
                            <div class="image">
                                <img src="<?= $profile_picture ?>" alt="Profile Picture" style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%;">
                            </div>
                            <div class="content">
                                <a class="js-acc-btn" href="#"><?= htmlspecialchars($_SESSION['username']) ?></a>
                            </div>
                            <div class="account-dropdown js-dropdown">
                                <div class="info clearfix">
                                    <div class="image">
                                        <img src="<?= $profile_picture ?>" alt="<?= htmlspecialchars($_SESSION['full_name']) ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 50%;" />
                                    </div>
                                    <div class="content">
                                        <h5 class="name">
                                            <a href="#"><?= htmlspecialchars($_SESSION['full_name']) ?></a>
                                        </h5>
                                        <span class="email"><?= htmlspecialchars($_SESSION['email']) ?></span>
                                    </div>
                                </div>
                                <div class="account-dropdown__body">
                                    <div class="account-dropdown__item">
                                        <a href="profile.php?id=<?= $_SESSION['id'] ?>">
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
                        <div class="au-breadcrumb-left">
                            <span class="au-breadcrumb-span">You are here:</span>
                            <ul class="list-unstyled list-inline au-breadcrumb__list">
                                <li class="list-inline-item active text-white">
                                    <a href="#">Home</a>
                                </li>
                                <li class="list-inline-item seprate">
                                    <span>/</span>
                                </li>
                                <li class="list-inline-item text-white"><a href="dashboard.php">Dashboard</a></li>
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
                                <span><?= htmlspecialchars($_SESSION['full_name']) ?></span>, Welcome back</h1>
                            <p>Administrative Dashboard - Overview</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- END WELCOME-->

    <!-- PAGE CONTENT-->
    <div class="page-container3">
        <section class="alert-wrap p-t-70 p-b-70">
            <div class="container">
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card text-white p-4 bg-tutors">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-value"><?= $stats['total_tutors'] ?></div>
                                    <div class="stat-label">Total Tutors</div>
                                </div>
                                <i class="fas fa-users stat-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-white p-4 bg-active">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-value"><?= $stats['active_tutors'] ?></div>
                                    <div class="stat-label">Active Tutors</div>
                                </div>
                                <i class="fas fa-user-check stat-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-white p-4 bg-pending">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-value"><?= $stats['pending_tutors'] ?></div>
                                    <div class="stat-label">Pending Tutors</div>
                                </div>
                                <i class="fas fa-user-clock stat-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-white p-4 bg-cities">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-value"><?= $stats['total_cities'] ?></div>
                                    <div class="stat-label">Cities</div>
                                </div>
                                <i class="fas fa-map-marker-alt stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
                                            <span class="inbox-num"><?= $stats['unread'] ?? '0'; ?></span>
                                    </li>
                                    <li>
                                        <a href="manage-tutors.php">
                                            <i class="fas fa-users"></i>Manage Tutors</a>
                                    </li>
                                    <li>
                                        <a href="add-city.php">
                                            <i class="fas fa-map"></i>Manage Cities</a>
                                    </li>
                                    <li>
                                        <a href="add-grade.php">
                                            <i class="fas fa-plus"></i>Manage Grades</a>
                                    </li>
                                    <li>
                                        <a href="add-payments.php">
                                            <i class="fas fa-edit" aria-hidden="true"></i>Manage Payments</a>
                                    </li>
                                    <li>
                                        <a href="add-payments.php">
                                            <i class="fas fa-book"></i>Manage Subjects</a>
                                    </li>
                                    <li>
                                        <a href="profile.php">
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
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <strong>Recent Activity</strong>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Activity</th>
                                                        <th>Date</th>
                                                        <th>Admin</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php foreach ($recent_activities as $index => $activity): ?>
                                                        <tr>
                                                            <td><?= $index + 1 ?></td>
                                                            <td><?= htmlspecialchars($activity['action']) ?></td>
                                                            <td><?= date('M d, Y H:i', strtotime($activity['created_at'])) ?></td>
                                                            <td><?= htmlspecialchars($activity['admin_name'] ?? 'System') ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row m-t-30">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <strong>Tutor Status</strong>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="tutorStatusChart" height="200"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <strong>Recent Registrations</strong>
                                        </div>
                                        <div class="card-body">
                                            <ul class="list-group list-group-flush">
                                                <?php foreach ($recent_tutors as $tutor): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <a href="tutor-profile.php?id=<?= $tutor['id'] ?>">
                                                            <?= htmlspecialchars($tutor['full_name']) ?>
                                                        </a>
                                                        <span class="badge badge-primary badge-pill">
            <?= date('M d', strtotime($tutor['created_at'])) ?>
        </span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row m-t-30">
                                <div class="col-md-12">
                                    <div class="copyright">
                                        <p>Copyright © 2025 Openclass. All rights reserved.</p>
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
<script src="../vendor/jquery-3.2.1.min.js"></script>
<!-- Bootstrap JS-->
<script src="../vendor/bootstrap-4.1/popper.min.js"></script>
<script src="../vendor/bootstrap-4.1/bootstrap.min.js"></script>
<!-- Vendor JS-->
<script src="../vendor/slick/slick.min.js"></script>
<script src="../vendor/wow/wow.min.js"></script>
<script src="../vendor/animsition/animsition.min.js"></script>
<script src="../vendor/bootstrap-progressbar/bootstrap-progressbar.min.js"></script>
<script src="../vendor/counter-up/jquery.waypoints.min.js"></script>
<script src="../vendor/counter-up/jquery.counterup.min.js"></script>
<script src="../vendor/circle-progress/circle-progress.min.js"></script>
<script src="../vendor/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../vendor/chartjs/Chart.bundle.min.js"></script>
<script src="../vendor/select2/select2.min.js"></script>

<!-- Main JS-->
<script src="../js/main.js"></script>

<script>
    // Tutor Status Chart
    var ctx = document.getElementById('tutorStatusChart').getContext('2d');
    var tutorStatusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Active Tutors', 'Pending Tutors'],
            datasets: [{
                data: [<?= $stats['active_tutors'] ?>, <?= $stats['pending_tutors'] ?>],
                backgroundColor: [
                    '#28a745',
                    '#ffc107'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                position: 'bottom'
            }
        }
    });
</script>
</body>
</html>