<?php
session_start();
include 'config.php';

if (!isset($_SESSION['tutor_id'])) {
    header("Location: login.php");
    exit;
}

$tutor_id = $_SESSION['tutor_id'];
$success = $error = "";

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

// Fetch dropdown options
$cities = $conn->query("SELECT id, name FROM cities");
$subjects = $conn->query("SELECT id, name FROM subjects");
$grades = $conn->query("SELECT id, level_name FROM grades");
$payments = $conn->query("SELECT id, method_name FROM payment_methods");

// Fetch all platforms from DB
$platforms_db = $conn->query("SELECT id, name FROM platforms");

// Fetch tutor info
$stmt = $conn->prepare("SELECT * FROM tutors WHERE id = ?");
$stmt->bind_param("i", $tutor_id);
$stmt->execute();
$tutor = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Convert multi-select fields to arrays
$tutor_subjects = [];
$tutor_grades = [];
$tutor_platforms = [];
$payment_methods = [];

// Fetch tutor subjects
$subject_query = $conn->query("SELECT subject_id FROM tutor_subjects WHERE tutor_id = $tutor_id");
while ($row = $subject_query->fetch_assoc()) {
    $tutor_subjects[] = $row['subject_id'];
}

// Fetch tutor grades
$grade_query = $conn->query("SELECT grade_id FROM tutor_grades WHERE tutor_id = $tutor_id");
while ($row = $grade_query->fetch_assoc()) {
    $tutor_grades[] = $row['grade_id'];
}

// Fetch tutor platforms
$platform_query = $conn->query("SELECT platform_id FROM tutor_platforms WHERE tutor_id = $tutor_id");
while ($row = $platform_query->fetch_assoc()) {
    $tutor_platforms[] = $row['platform_id'];
}

// Fetch tutor payment methods
$payment_query = $conn->query("SELECT payment_method_id FROM tutor_payment_methods WHERE tutor_id = $tutor_id");
while ($row = $payment_query->fetch_assoc()) {
    $payment_methods[] = $row['payment_method_id'];
}

// Handle POST update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $conn->begin_transaction();
        
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $location = $_POST['location'];
        $teaching_method = $_POST['teaching_method'];
        $rate = $_POST['rate'];
        $biography = $_POST['biography'];

        // Handle profile picture upload
        $profile_pic = $tutor['profile_picture'];
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
            $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $new_name = "profile_" . $tutor_id . "_" . time() . "." . $ext;
            $upload_path = "uploads/" . $new_name;

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $profile_pic = $new_name;
            } else {
                throw new Exception("Failed to upload profile picture.");
            }
        }

        // Update tutors table
        $stmt = $conn->prepare("UPDATE tutors SET full_name=?, email=?, phone_number=?, location_id=?, teaching_method=?, rate_per_hour=?, biography=?, profile_picture=? WHERE id=?");
        $stmt->bind_param("ssssssssi", $full_name, $email, $phone, $location, $teaching_method, $rate, $biography, $profile_pic, $tutor_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating profile: " . $conn->error);
        }
        $stmt->close();

        // Update tutor_subjects junction table
        $conn->query("DELETE FROM tutor_subjects WHERE tutor_id = $tutor_id");
        if (!empty($_POST['subjects'])) {
            $subject_stmt = $conn->prepare("INSERT INTO tutor_subjects (tutor_id, subject_id) VALUES (?, ?)");
            foreach ($_POST['subjects'] as $subject_id) {
                $subject_stmt->bind_param("ii", $tutor_id, $subject_id);
                $subject_stmt->execute();
            }
            $subject_stmt->close();
        }

        // Update tutor_grades junction table
        $conn->query("DELETE FROM tutor_grades WHERE tutor_id = $tutor_id");
        if (!empty($_POST['grade_levels'])) {
            $grade_stmt = $conn->prepare("INSERT INTO tutor_grades (tutor_id, grade_id) VALUES (?, ?)");
            foreach ($_POST['grade_levels'] as $grade_id) {
                $grade_stmt->bind_param("ii", $tutor_id, $grade_id);
                $grade_stmt->execute();
            }
            $grade_stmt->close();
        }

        // Update tutor_platforms junction table
        $conn->query("DELETE FROM tutor_platforms WHERE tutor_id = $tutor_id");
        if (!empty($_POST['online_platforms'])) {
            $platform_stmt = $conn->prepare("INSERT INTO tutor_platforms (tutor_id, platform_id) VALUES (?, ?)");
            foreach ($_POST['online_platforms'] as $platform_id) {
                $platform_stmt->bind_param("ii", $tutor_id, $platform_id);
                $platform_stmt->execute();
            }
            $platform_stmt->close();
        }

        // Update tutor_payment_methods junction table
        $conn->query("DELETE FROM tutor_payment_methods WHERE tutor_id = $tutor_id");
        if (!empty($_POST['payment_method'])) {
            $payment_stmt = $conn->prepare("INSERT INTO tutor_payment_methods (tutor_id, payment_method_id) VALUES (?, ?)");
            foreach ($_POST['payment_method'] as $payment_id) {
                $payment_stmt->bind_param("ii", $tutor_id, $payment_id);
                $payment_stmt->execute();
            }
            $payment_stmt->close();
        }

        // Commit transaction
        $conn->commit();
        
        $success = "Profile updated successfully.";
        $_SESSION['tutor_name'] = $full_name;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error = $e->getMessage();
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
    <title><?= htmlspecialchars($_SESSION['tutor_name']) ?> | Openclass Tutors</title>

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
                                <img src="<?= $tutor['profile_picture'] ? 'uploads/' . $tutor['profile_picture'] : 'https://via.placeholder.com/150' ?>" class="rounded-circle img-thumbnail profile-pic mb-2" alt="Profile Picture" style="width: 80px; height: 80px; object-fit: cover;">
                            </div>
                            <div class="content">
                                <a class="js-acc-btn" href="#"><?= htmlspecialchars($_SESSION['tutor_name']) ?></a>
                            </div>
                            <div class="account-dropdown js-dropdown">
                                <div class="info clearfix">
                                    <div class="image">
                                        <a href="#">
                                            <img src="<?= $tutor['profile_picture'] ? 'uploads/' . $tutor['profile_picture'] : 'https://via.placeholder.com/150' ?>" class="rounded-circle img-thumbnail profile-pic mb-2" alt="Profile Picture" style="width: 50px; height: 50px; object-fit: cover;">
                                        </a>
                                    </div>
                                    <div class="content">
                                        <h5 class="name">
                                            <a href="#"><?= htmlspecialchars($_SESSION['tutor_name']) ?></a>
                                        </h5>
                                        <span class="email"><?= htmlspecialchars($tutor['email']) ?></span>
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
                                <li class="list-inline-item">Profile</li>
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
                            <p class="text-white">Your current profile information.</p>
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
                <div class="container">
                    <div class="row">
                        <div class="col-md-12">
                            <?php if ($success): ?>
                                <div class="alert alert-success"><?= $success ?></div>
                            <?php elseif ($error): ?>
                                <div class="alert alert-danger"><?= $error ?></div>
                            <?php endif; ?>
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
                                        <li>
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
                                        <li class="active has-sub">
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
                            <form method="POST" enctype="multipart/form-data">
                                <div class="row">
                                    
                                    <div class="col-lg-4">
                                        <!-- RECENT REPORT-->
                                        <div class="card">
                                            <div style="background-color: #0F1E8A;" class="card-header text-white">Profile Picture</div>
                                            <div class="card-body">
                                                <div>
                                                <div class="text-center">
                                                    <img src="<?= $tutor['profile_picture'] ? 'uploads/' . $tutor['profile_picture'] : 'https://via.placeholder.com/150' ?>" class="rounded-circle img-thumbnail profile-pic mb-2" alt="Profile Picture" style="width: 200px; height: 200px; object-fit: cover;">
                                                    <input type="file" name="profile_picture" class="form-control-file">
                                                </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- END RECENT REPORT-->
                                    </div>
                                    <div class="col-lg-8">
                                        <!-- CHART PERCENT-->
                                        <div class="card">
                                            <div style="background-color: #0F1E8A;" class="card-header text-white">Profile Information</div>
                                                <div class="card-body">
                                                    <div>
                                                        <div class="mb-3">
                                                            <label>Full Name</label>
                                                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($tutor['full_name']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>Email Address</label>
                                                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($tutor['email']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>Phone Number</label>
                                                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($tutor['phone_number']) ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>Location</label>
                                                            <select name="location" class="form-control">
                                                                <?php
                                                                $cities->data_seek(0);
                                                                while ($city = $cities->fetch_assoc()): ?>
                                                                    <option value="<?= $city['id'] ?>" <?= $city['id'] == $tutor['location_id'] ? 'selected' : '' ?>><?= $city['name'] ?></option>
                                                                <?php endwhile; ?>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>Subjects</label>
                                                            <select name="subjects[]" class="form-control" multiple>
                                                                <?php
                                                                $subjects->data_seek(0);
                                                                while ($s = $subjects->fetch_assoc()): ?>
                                                                    <option value="<?= $s['id'] ?>" <?= in_array($s['id'], $tutor_subjects) ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                                                                <?php endwhile; ?>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>Grade Levels</label>
                                                            <select name="grade_levels[]" class="form-control" multiple>
                                                                <?php
                                                                $grades->data_seek(0);
                                                                while ($g = $grades->fetch_assoc()): ?>
                                                                    <option value="<?= $g['id'] ?>" <?= in_array($g['id'], $tutor_grades) ? 'selected' : '' ?>><?= htmlspecialchars($g['level_name']) ?></option>
                                                                <?php endwhile; ?>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>Teaching Method</label><br>
                                                            <?php
                                                            $methods = ['online' => 'Online', 'in_person' => 'In Person', 'both' => 'Both'];
                                                            foreach ($methods as $val => $label): ?>
                                                                <div class="form-check form-check-inline">
                                                                    <input type="radio" class="form-check-input" name="teaching_method" value="<?= $val ?>" <?= $tutor['teaching_method'] === $val ? 'checked' : '' ?>>
                                                                    <label class="form-check-label"><?= $label ?></label>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <div class="mb-3" id="platformsSection">
                                                            <label for="online_platforms" class="form-label">Online Platforms</label>
                                                            <select name="online_platforms[]" id="online_platforms" class="form-control" multiple>
                                                                <?php
                                                                $platforms_db->data_seek(0);
                                                                while ($platform = $platforms_db->fetch_assoc()): ?>
                                                                    <option value="<?= $platform['id'] ?>"
                                                                        <?= in_array($platform['id'], $tutor_platforms) ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars(ucwords($platform['name'])) ?>
                                                                    </option>
                                                                <?php endwhile; ?>
                                                            </select>
                                                            <small class="form-text text-muted">Hold down Ctrl (Windows) or Command (Mac) to select multiple.</small>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>Rate Per Hour (e.g., USD 10)</label>
                                                            <input type="text" name="rate" class="form-control" value="<?= htmlspecialchars($tutor['rate_per_hour']) ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label>Payment Method</label>
                                                            <select name="payment_method[]" class="form-control" multiple>
                                                                <?php
                                                                $payments->data_seek(0);
                                                                while ($payment = $payments->fetch_assoc()): ?>
                                                                    <option value="<?= $payment['id'] ?>" <?= in_array($payment['id'], $payment_methods) ? 'selected' : '' ?>><?= htmlspecialchars($payment['method_name']) ?></option>
                                                                <?php endwhile; ?>
                                                            </select>

                                                        </div>
                                                        <div class="mb-3">
                                                            <label>Biography</label>
                                                            <textarea name="biography" class="form-control" rows="4"><?= htmlspecialchars($tutor['biography']) ?></textarea>
                                                        </div>
                                                        <button type="submit" class="btn btn-primary">Update Profile</button>
                                                    </div>
                                                </div>

                                        </div>
                                        <!-- END CHART PERCENT-->
                                    </div>
                                    
                                </div>
                            </form>    
                                
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

<script>
function togglePlatforms() {
    const method = document.querySelector('input[name="teaching_method"]:checked').value;
    document.getElementById('platformsSection').style.display = (method === 'online' || method === 'both') ? 'block' : 'none';
}
document.querySelectorAll('input[name="teaching_method"]').forEach(radio => {
    radio.addEventListener('change', togglePlatforms);
});
togglePlatforms(); // Run on load
</script>
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