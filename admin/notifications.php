<?php
// Include database configuration
require_once 'config.php';
// Start session
session_start();

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

// Process notification creation
$notification_msg = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_notification'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $target_type = $_POST['target_type'];
    $important = isset($_POST['important']) ? 1 : 0;

    // Validate inputs
    if (empty($subject) || empty($message)) {
        $notification_msg = '<div class="alert alert-danger">Please fill all required fields</div>';
    } else {
        // Create notification
        $sql = "INSERT INTO notifications (subject, message, created_by, target_type, is_important) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssisi", $subject, $message, $admin_id, $target_type, $important);

        if ($stmt->execute()) {
            $notification_id = $stmt->insert_id;

            // If specific tutors are selected
            if ($target_type == 'specific' && isset($_POST['tutor_ids'])) {
                foreach ($_POST['tutor_ids'] as $tutor_id) {
                    $sql = "INSERT INTO notification_recipients (notification_id, tutor_id) VALUES (?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $notification_id, $tutor_id);
                    $stmt->execute();
                }
            }

            // Log the activity
            $activity = "Created a new notification: " . $subject;
            $sql = "INSERT INTO admin_logs (admin_id, action, details) VALUES (?, 'create_notification', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $admin_id, $activity);
            $stmt->execute();

            $notification_msg = '<div class="alert alert-success">Notification created successfully!</div>';
        } else {
            $notification_msg = '<div class="alert alert-danger">Error creating notification: ' . $conn->error . '</div>';
        }
    }
}

// Delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notification_id = $_GET['delete'];

    // Check if notification exists and belongs to this admin
    $sql = "SELECT id FROM notifications WHERE id = ? AND created_by = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notification_id, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Delete notification recipients first
        $sql = "DELETE FROM notification_recipients WHERE notification_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $notification_id);
        $stmt->execute();

        // Delete the notification
        $sql = "DELETE FROM notifications WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $notification_id);
        $stmt->execute();

        $notification_msg = '<div class="alert alert-success">Notification deleted successfully!</div>';
    }
}

// Get all tutors for the dropdown
$tutors = [];
$sql = "SELECT id, full_name FROM tutors WHERE account_status = 'active' ORDER BY full_name";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $tutors[] = $row;
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
    <title>Openclass | Notifications Management</title>

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
        .bg-total {
            background: linear-gradient(45deg, #0F1E8A, #1a3af5);
        }
        .bg-read {
            background: linear-gradient(45deg, #28a745, #5cb85c);
        }
        .bg-unread {
            background: linear-gradient(45deg, #ffc107, #ffd351);
        }
        .bg-important {
            background: linear-gradient(45deg, #dc3545, #ff6b6b);
        }
        .notification-item {
            border-left: 4px solid #e9ecef;
            transition: all 0.2s ease;
        }
        .notification-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        .notification-item.important {
            border-left: 4px solid #dc3545;
        }
        .select2-container {
            width: 100% !important;
        }
        .notification-action {
            visibility: hidden;
        }
        .notification-item:hover .notification-action {
            visibility: visible;
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
                                <p>You have <?= $stats['unread'] ?> Unread Notifications</p>
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
                                <li class="list-inline-item seprate">
                                    <span>/</span>
                                </li>
                                <li class="list-inline-item text-white">Notifications</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="welcome2-inner m-t-60">
                        <div class="welcome2-greeting">
                            <h1 class="title-6">Notification Management
                                <span><?= htmlspecialchars($_SESSION['full_name']) ?></span></h1>
                            <p>Create and manage notifications for tutors</p>
                        </div>
                        <form class="form-header form-header2" action="" method="post">
                            <input class="au-input au-input--w435" type="text" name="search" placeholder="Search notifications...">
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
            <div class="container">
                <!-- ALERT-->
                <?= $notification_msg ?>
                <!-- END ALERT-->
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
                                    <li>
                                        <a class="js-arrow" href="dashboard.php">
                                            <i class="fas fa-tachometer-alt"></i>Dashboard
                                        </a>
                                    </li>
                                    <li class="active has-sub">
                                        <a href="notifications.php">
                                            <i class="fas fa-bell"></i>Notifications</a>
                                        <span class="inbox-num"><?= $stats['unread'] ?></span>
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
                            <!-- Notification Dashboard -->
                            <div class="row m-b-20">
                                <div class="col-md-3">
                                    <div class="stat-card bg-total text-white p-3 mb-3">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <p class="stat-label mb-0">Total</p>
                                                <h2 class="stat-value mb-0"><?= $stats['total'] ?></h2>
                                            </div>
                                            <div class="stat-icon">
                                                <i class="zmdi zmdi-notifications"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card bg-read text-white p-3 mb-3">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <p class="stat-label mb-0">Read</p>
                                                <h2 class="stat-value mb-0"><?= $stats['read'] ?></h2>
                                            </div>
                                            <div class="stat-icon">
                                                <i class="zmdi zmdi-check-circle"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card bg-unread text-white p-3 mb-3">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <p class="stat-label mb-0">Unread</p>
                                                <h2 class="stat-value mb-0"><?= $stats['unread'] ?></h2>
                                            </div>
                                            <div class="stat-icon">
                                                <i class="zmdi zmdi-email"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card bg-important text-white p-3 mb-3">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <p class="stat-label mb-0">Important</p>
                                                <h2 class="stat-value mb-0"><?= $stats['important'] ?></h2>
                                            </div>
                                            <div class="stat-icon">
                                                <i class="zmdi zmdi-alert-circle"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Create New Notification -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <strong>Create New Notification</strong>
                                </div>
                                <div class="card-body">
                                    <form action="" method="post">
                                        <div class="form-group">
                                            <label for="subject" class="form-control-label">Subject</label>
                                            <input type="text" id="subject" name="subject" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="message" class="form-control-label">Message</label>
                                            <textarea id="message" name="message" rows="4" class="form-control" required></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-control-label">Target Audience</label>
                                            <div class="form-check">
                                                <div class="radio">
                                                    <label for="all_tutors" class="form-check-label">
                                                        <input type="radio" id="all_tutors" name="target_type" value="all" class="form-check-input" checked>All Tutors
                                                    </label>
                                                </div>
                                                <div class="radio">
                                                    <label for="active_tutors" class="form-check-label">
                                                        <input type="radio" id="active_tutors" name="target_type" value="active" class="form-check-input">Active Tutors Only
                                                    </label>
                                                </div>
                                                <div class="radio">
                                                    <label for="specific_tutors" class="form-check-label">
                                                        <input type="radio" id="specific_tutors" name="target_type" value="specific" class="form-check-input">Specific Tutors
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group" id="tutor_selection" style="display: none;">
                                            <label for="tutor_ids" class="form-control-label">Select Tutors</label>
                                            <select id="tutor_ids" name="tutor_ids[]" class="form-control select2" multiple>
                                                <?php foreach($tutors as $tutor): ?>
                                                    <option value="<?= $tutor['id'] ?>"><?= htmlspecialchars($tutor['full_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <div class="checkbox">
                                                <label class="form-check-label">
                                                    <input type="checkbox" name="important" class="form-check-input">
                                                    Mark as Important
                                                </label>
                                            </div>
                                        </div>
                                        <div class="form-actions">
                                            <button type="submit" name="create_notification" class="btn btn-primary">
                                                <i class="fa fa-paper-plane"></i> Send Notification
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Notifications List -->
                            <div class="card">
                                <div class="card-header">
                                    <strong class="card-title">Recent Notifications</strong>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-borderless table-striped">
                                            <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Sent By</th>
                                                <th>Recipients</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php if(empty($notifications)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">No notifications found</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach($notifications as $notification): ?>
                                                    <tr class="notification-item <?= $notification['is_important'] ? 'important' : '' ?>">
                                                        <td>
                                                            <?php if($notification['is_important']): ?>
                                                                <span class="badge badge-danger mr-2">Important</span>
                                                            <?php endif; ?>
                                                            <?= htmlspecialchars($notification['subject']) ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($notification['admin_name']) ?></td>
                                                        <td>
                                                            <?php
                                                            if ($notification['target_type'] == 'all') {
                                                                echo "All Tutors";
                                                            } elseif ($notification['target_type'] == 'active') {
                                                                echo "Active Tutors";
                                                            } else {
                                                                echo $notification['recipient_count'] . " Tutors";
                                                            }
                                                            ?>
                                                        </td>
                                                        <td><?= date('M j, Y H:i', strtotime($notification['created_at'])) ?></td>
                                                        <td>
                                                            <div class="table-data-feature">
                                                                <button class="item" data-toggle="tooltip" data-placement="top" title="View" onclick="viewNotification(<?= $notification['id'] ?>)">
                                                                    <i class="zmdi zmdi-eye"></i>
                                                                </button>
                                                                <a href="notifications.php?delete=<?= $notification['id'] ?>" class="item notification-action" data-toggle="tooltip" data-placement="top" title="Delete" onclick="return confirm('Are you sure you want to delete this notification?')">
                                                                    <i class="zmdi zmdi-delete"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Notification View Modal -->
                            <div class="modal fade" id="notificationModal" tabindex="-1" role="dialog" aria-labelledby="notificationModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-lg" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="notificationModalTitle"></h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body" id="notificationModalBody">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
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
<!-- Vendor JS       -->
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
    $(document).ready(function() {
        // Initialize Select2
        $('.select2').select2();

        // Show/hide tutor selection based on target type
        $('input[name="target_type"]').change(function() {
            if($(this).val() === 'specific') {
                $('#tutor_selection').show();
            } else {
                $('#tutor_selection').hide();
            }
        });
    });

    // View notification details
    function viewNotification(id) {
        // In a real implementation, this would fetch from the server
        // For now we'll use AJAX to get notification details
        $.ajax({
            url: 'notification_details.php',
            type: 'GET',
            data: {id: id},
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    $('#notificationModalTitle').text(response.notification.subject);

                    let modalContent = `
                    <div class="card">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-3 font-weight-bold">Created By:</div>
                                <div class="col-md-9">${response.notification.admin_name}</div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-3 font-weight-bold">Date:</div>
                                <div class="col-md-9">${response.notification.created_at}</div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-3 font-weight-bold">Recipients:</div>
                                <div class="col-md-9">${response.notification.target_type}</div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-3 font-weight-bold">Status:</div>
                                <div class="col-md-9">
                                    ${response.notification.is_important ? '<span class="badge badge-danger">Important</span>' : '<span class="badge badge-success">Normal</span>'}
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 font-weight-bold mb-2">Message:</div>
                                <div class="col-md-12">
                                    <div class="p-3 bg-light rounded">${response.notification.message}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    ${response.recipients.length > 0 ? `
                    <div class="card mt-3 text-white">
                        <div class="card-header">
                            <strong>Read Status</strong>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-borderless table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tutor</th>
                                            <th>Status</th>
                                            <th>Read Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${response.recipients.map(r => `
                                            <tr>
                                                <td>${r.tutor_name}</td>
                                                <td>${r.read_at ? '<span class="badge badge-success">Read</span>' : '<span class="badge badge-secondary">Unread</span>'}</td>
                                                <td>${r.read_at || '-'}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>` : ''}
                `;

                    $('#notificationModalBody').html(modalContent);
                    $('#notificationModal').modal('show');
                } else {
                    alert('Error loading notification details');
                }
            },
            error: function() {
                alert('Error loading notification details');
            }
        });
    }
    </body>
</html>