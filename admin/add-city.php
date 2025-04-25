<?php
// cities.php - Admin city management page

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.php");
    exit;
}

// Include database configuration
require_once 'config.php';

// Initialize variables
$city_name = "";
$city_name_err = "";
$success_message = "";
$error_message = "";

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

// Process form data when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check which form was submitted
    if (isset($_POST["add_city"])) {
        // Validate city name
        if (empty(trim($_POST["city_name"]))) {
            $city_name_err = "Please enter a city name.";
        } else {
            $city_name = trim($_POST["city_name"]);

            // Check if city name already exists
            $sql = "SELECT id FROM cities WHERE name = ?";

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $param_city_name);
                $param_city_name = $city_name;

                if ($stmt->execute()) {
                    $stmt->store_result();

                    if ($stmt->num_rows > 0) {
                        $city_name_err = "This city already exists.";
                    }
                } else {
                    $error_message = "Oops! Something went wrong. Please try again later.";
                }
                $stmt->close();
            }
        }

        // Check input errors before inserting into database
        if (empty($city_name_err)) {
            // Prepare an insert statement
            $sql = "INSERT INTO cities (name) VALUES (?)";

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $param_city_name);
                $param_city_name = $city_name;

                if ($stmt->execute()) {
                    $success_message = "City added successfully!";
                    $city_name = ""; // Clear the form
                } else {
                    $error_message = "Something went wrong. Please try again later.";
                }
                $stmt->close();
            }
        }
    } elseif (isset($_POST["delete_city"])) {
        // Process city deletion
        $city_id = trim($_POST["city_id"]);

        $sql = "DELETE FROM cities WHERE id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $city_id);

            if ($stmt->execute()) {
                $success_message = "City deleted successfully!";
            } else {
                $error_message = "Error deleting city. Please try again.";
            }
            $stmt->close();
        }
    } elseif (isset($_POST["update_city"])) {
        // Process city update
        $city_id = trim($_POST["city_id"]);
        $city_name = trim($_POST["city_name"]);

        // Validate city name
        if (empty($city_name)) {
            $error_message = "City name cannot be empty.";
        } else {
            // Check if new name already exists for another city
            $sql = "SELECT id FROM cities WHERE name = ? AND id != ?";

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("si", $city_name, $city_id);

                if ($stmt->execute()) {
                    $stmt->store_result();

                    if ($stmt->num_rows > 0) {
                        $error_message = "This city name already exists.";
                    } else {
                        // Update the city name
                        $update_sql = "UPDATE cities SET name = ? WHERE id = ?";

                        if ($update_stmt = $conn->prepare($update_sql)) {
                            $update_stmt->bind_param("si", $city_name, $city_id);

                            if ($update_stmt->execute()) {
                                $success_message = "City updated successfully!";
                            } else {
                                $error_message = "Error updating city. Please try again.";
                            }
                            $update_stmt->close();
                        }
                    }
                } else {
                    $error_message = "Oops! Something went wrong. Please try again later.";
                }
                $stmt->close();
            }
        }
    }
}

// Pagination setup
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1); // Ensure page is at least 1
$offset = ($page - 1) * $records_per_page;

// Get total number of cities
$total_cities = 0;
$sql = "SELECT COUNT(*) as total FROM cities";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    $total_cities = $row['total'];
    $result->free();
}

// Calculate total pages
$total_pages = ceil($total_cities / $records_per_page);

// Fetch cities for current page
$cities = array();
$sql = "SELECT id, name FROM cities ORDER BY name ASC LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $offset, $records_per_page);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $cities[] = $row;
    }
    $result->free();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Required meta tags-->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="manage tutor locations">
    <meta name="author" content="Openclass">
    <meta name="keywords" content="tutor locations">

    <!-- Title Page-->
    <title>Openclass | Manage Locations</title>

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
        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
        .pagination .page-item.active .page-link {
            background-color: #0F1E8A;
            border-color: #0F1E8A;
        }
        .pagination .page-link {
            color: #0F1E8A;
        }
        .actions .btn {
            margin-right: 5px;
        }
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
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
                                <li class="list-inline-item text-white"> / Manage Locations</li>
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
                            <p>Administrative Dashboard - Manage Cities</p>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- END WELCOME-->
    <!-- PAGE CONTENT-->
    <div class="page-container3">
        <section class="alert-wrap p-t-70 p-b-70 col-md-12">
            <?php if(!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if(!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
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
                                            <i class="fas fa-bell"></i>Notifications</a>
                                        <span class="inbox-num"><?= $stats['unread'] ?></span>
                                    </li>
                                    <li>
                                        <a href="manage-tutors.php">
                                            <i class="fas fa-users"></i>Manage Tutors</a>
                                    </li>
                                    <li class="active has-sub">
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
                                <div class="col-lg-12">
                                    <!-- RECENT REPORT-->
                                    <div class="card m-b-40 ">
                                        <div style="background-color:#0F1E8A;" class="card-header text-white">
                                            <h2 style="color:white !important;">City Management</h2>
                                            <p>Add, edit, or remove cities from the database</p>
                                        </div>

                                        <div>
                                            <!-- Add City Form -->
                                            <div class="form-section">
                                                <h4>Add New City</h4>
                                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="row g-3">
                                                    <div class="col-md-6">
                                                        <label for="city_name" class="form-label">City Name</label>
                                                        <input type="text" class="form-control <?php echo (!empty($city_name_err)) ? 'is-invalid' : ''; ?>"
                                                               id="city_name" name="city_name" value="<?php echo htmlspecialchars($city_name); ?>">
                                                        <span class="invalid-feedback"><?php echo $city_name_err; ?></span>
                                                    </div>
                                                    <div class="col-12">
                                                        <button type="submit" name="add_city" class="btn btn-primary">Add City</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- END RECENT REPORT-->
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <!-- DATA TABLE-->
                                    <?php if(count($cities) > 0): ?>
                                        <div class="table-responsive m-b-40">
                                            <table class="table table-borderless table-data3">
                                                <thead style="background-color:#0F1E8A;">
                                                <tr>
                                                    <th>ID</th>
                                                    <th>City Name</th>
                                                    <th>Actions</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach($cities as $city): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($city["id"]); ?></td>
                                                        <td><?php echo htmlspecialchars($city["name"]); ?></td>
                                                        <td class="actions">
                                                            <button type="button" class="btn btn-sm btn-primary edit-btn"
                                                                    data-bs-toggle="modal" data-bs-target="#editCityModal"
                                                                    data-id="<?php echo $city["id"]; ?>"
                                                                    data-name="<?php echo htmlspecialchars($city["name"]); ?>">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger delete-btn"
                                                                    data-bs-toggle="modal" data-bs-target="#deleteCityModal"
                                                                    data-id="<?php echo $city["id"]; ?>"
                                                                    data-name="<?php echo htmlspecialchars($city["name"]); ?>">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>

                                            <!-- Pagination -->
                                            <nav aria-label="Cities pagination">
                                                <ul class="pagination">
                                                    <?php if($page > 1): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                                                                <span aria-hidden="true">&laquo;</span>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>

                                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                                        </li>
                                                    <?php endfor; ?>

                                                    <?php if($page < $total_pages): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                                                                <span aria-hidden="true">&raquo;</span>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </nav>
                                            <div class="text-center text-muted">
                                                Showing <?= count($cities) ?> of <?= $total_cities ?> cities
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">No cities found. Add a new city above.</div>
                                    <?php endif; ?>
                                    <!-- END DATA TABLE                  -->
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<!-- Edit City Modal -->
<div class="modal fade" id="editCityModal" tabindex="-1" aria-labelledby="editCityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCityModalLabel">Edit City</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="modal-body">
                    <input type="hidden" name="city_id" id="edit_city_id">
                    <div class="mb-3">
                        <label for="edit_city_name" class="form-label">City Name</label>
                        <input type="text" class="form-control" id="edit_city_name" name="city_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_city" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete City Modal -->
<div class="modal fade" id="deleteCityModal" tabindex="-1" aria-labelledby="deleteCityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteCityModalLabel">Delete City</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this city?</p>
                <p><strong>City: </strong><span id="delete_city_name"></span></p>
            </div>
            <div class="modal-footer">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <input type="hidden" name="city_id" id="delete_city_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_city" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Jquery JS-->
<script src="../vendor/jquery-3.2.1.min.js"></script>
<!-- Bootstrap JS-->
<script src="../vendor/bootstrap-4.1/popper.min.js"></script>
<script src="../vendor/bootstrap-4.1/bootstrap.min.js"></script>
<!-- Vendor JS       -->
<script src="../vendor/slick/slick.min.js">
</script>
<script src="../vendor/wow/wow.min.js"></script>
<script src="../vendor/animsition/animsition.min.js"></script>
<script src="../vendor/bootstrap-progressbar/bootstrap-progressbar.min.js">
</script>
<script src="../vendor/counter-up/jquery.waypoints.min.js"></script>
<script src="../vendor/counter-up/jquery.counterup.min.js">
</script>
<script src="../vendor/circle-progress/circle-progress.min.js"></script>
<script src="../vendor/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="../vendor/chartjs/Chart.bundle.min.js"></script>
<script src="../vendor/select2/select2.min.js">
</script>

<!-- Main JS-->
<script src="../js/main.js"></script>
<script>
    // Edit button click handler
    document.querySelectorAll('.edit-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            var cityId = this.getAttribute('data-id');
            var cityName = this.getAttribute('data-name');

            document.getElementById('edit_city_id').value = cityId;
            document.getElementById('edit_city_name').value = cityName;
        });
    });

    // Delete button click handler
    document.querySelectorAll('.delete-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            var cityId = this.getAttribute('data-id');
            var cityName = this.getAttribute('data-name');

            document.getElementById('delete_city_id').value = cityId;
            document.getElementById('delete_city_name').textContent = cityName;
        });
    });
</script>
</body>
</html>