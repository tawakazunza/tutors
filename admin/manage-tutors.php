<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: admin-login.php");
    exit;
}

// Include database configuration
require_once 'config.php';

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

// Initialize variables
$profile_picture = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : '../images/default-profile.jpg';
$stats = ['unread' => 0]; // Default value
$notifications = []; // Empty array by default
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'active';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10; // Number of records per page
$start = ($page - 1) * $per_page;


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



// Handle actions (suspend/delete)
if (isset($_POST['action']) && isset($_POST['tutor_id'])) {
    $tutor_id = (int)$_POST['tutor_id'];
    $action = $_POST['action'];

    if ($action == 'suspend') {
        $query = "UPDATE tutors SET account_status = 'suspended' WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $tutor_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action == 'delete') {
        $query = "DELETE FROM tutors WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $tutor_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action == 'activate') {
        $query = "UPDATE tutors SET account_status = 'active' WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $tutor_id);
        $stmt->execute();
        $stmt->close();
    }

    // Build the redirect URL
    $query_params = [];
    if (!empty($search)) {
        $query_params[] = "search=" . urlencode($search);
    }
    if (!empty($status_filter) && $status_filter != 'active') {
        $query_params[] = "status=" . urlencode($status_filter);
    }
    if ($page > 1) {
        $query_params[] = "page=" . $page;
    }

    $redirect_url = $_SERVER['PHP_SELF'];
    if (!empty($query_params)) {
        $redirect_url .= "?" . implode("&", $query_params);
    }

    // Redirect to avoid form resubmission
    header("Location: " . $redirect_url);
    exit;
}

// Build the base SQL queries
$sql_base = "FROM tutors WHERE ";
$where_clauses = [];
$params = [];
$types = '';

// Add status filter
if ($status_filter !== 'all') {
    $status_value = $status_filter === 'active' ? 'active' :
        ($status_filter === 'suspended' ? 'suspended' : 'pending');
    $where_clauses[] = "account_status = ?";
    $params[] = $status_value;
    $types .= 's';
} else {
    $where_clauses[] = "1=1"; // Always true condition
}

// Add search condition if search term is provided
if (!empty($search)) {
    $search_term = "%$search%";
    $where_clauses[] = "(full_name LIKE ? OR email LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'ss';
}

// Combine all where clauses
$where_sql = implode(" AND ", $where_clauses);

// Build the complete SQL queries
$count_sql = "SELECT COUNT(*) as total " . $sql_base . $where_sql;
$sql = "SELECT * " . $sql_base . $where_sql . " ORDER BY created_at DESC LIMIT ?, ?";

// Copy parameters for the main query (which includes LIMIT parameters)
$main_params = $params;
$main_params[] = $start;
$main_params[] = $per_page;
$main_types = $types . 'ii';

// Prepare and execute the count query
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    // Dynamic parameter binding using references
    $count_refs = array();
    $count_refs[0] = $types;

    for ($i = 0; $i < count($params); $i++) {
        $count_refs[$i+1] = &$params[$i];
    }

    call_user_func_array(array($count_stmt, 'bind_param'), $count_refs);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$count_stmt->close();

// Calculate total pages
$total_pages = ceil($total_rows / $per_page);

// Prepare and execute the main query
$stmt = $conn->prepare($sql);
if (!empty($main_params)) {
    // Dynamic parameter binding using references
    $main_refs = array();
    $main_refs[0] = $main_types;

    for ($i = 0; $i < count($main_params); $i++) {
        $main_refs[$i+1] = &$main_params[$i];
    }

    call_user_func_array(array($stmt, 'bind_param'), $main_refs);
}
$stmt->execute();
$result = $stmt->get_result();

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
    <title>Openclass | Tutors Management</title>

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
        .badge-active {
            background-color: #28a745;
            color:white;
        }
        .badge-suspended {
            background-color: #dc3545;
        }
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }
        .action-btn {
            margin: 2px;
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        .status-filter {
            margin-bottom: 15px;
        }
        .search-box {
            margin-bottom: 20px;
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
                                <li class="list-inline-item text-white">Manage Tutors</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="welcome2-inner m-t-60">
                        <div class="welcome2-greeting">
                            <h1 class="title-6">Tutors Management
                                <span><?= htmlspecialchars($_SESSION['full_name']) ?></span></h1>
                            <p>Manage Tutors</p>
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
            <!-- Alert messages would go here -->
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
                                            <h2 class="stat-value mb-0"><?= $stats['total'] ?? 0; ?></h2>
                                    </li>
                                    <li class="active has-sub">
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
                                <div class="col-lg-12">
                                    <!-- RECENT REPORT-->
                                    <div class="table-responsive m-b-40">
                                        <div class="title-wrap">
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <div class="search-box">
                                                        <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="form-inline">
                                                            <div class="input-group">
                                                                <input type="text" name="search" class="form-control" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search); ?>">
                                                                <div class="input-group-append">
                                                                    <button type="submit" class="btn btn-primary">
                                                                        <i class="fa fa-search"></i> Search
                                                                    </button>
                                                                    <?php if (!empty($search)): ?>
                                                                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary">Clear</a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="status-filter float-right">
                                                        <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                                                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                                            <select name="status" class="form-control" onchange="this.form.submit()">
                                                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Tutors</option>
                                                                <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended Tutors</option>
                                                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending Tutors</option>
                                                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Tutors</option>
                                                            </select>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="chart-wrap">
                                            <!-- Tutors Table -->
                                            <?php if ($result->num_rows > 0): ?>
                                                <table class="table table-borderless table-striped table-data3">
                                                    <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Name</th>
                                                        <th>Email</th>
                                                        <th>Phone</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php while ($row = $result->fetch_assoc()): ?>
                                                        <tr>
                                                            <td><?php echo $row['id']; ?></td>
                                                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                            <td><?php echo htmlspecialchars($row['phone_number']); ?></td>
                                                            <td>
                                                                <?php
                                                                $badge_class = '';
                                                                if ($row['account_status'] === 'active') {
                                                                    $badge_class = 'badge-active';
                                                                } elseif ($row['account_status'] === 'suspended') {
                                                                    $badge_class = 'badge-suspended';
                                                                } elseif ($row['account_status'] === 'pending') {
                                                                    $badge_class = 'badge-pending';
                                                                }
                                                                ?>
                                                                <span class="badge <?php echo $badge_class; ?>">
                                                                    <?php echo ucfirst($row['account_status']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group" role="group">
                                                                    <?php if ($row['account_status'] === 'active'): ?>
                                                                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="display:inline;">
                                                                            <input type="hidden" name="tutor_id" value="<?php echo $row['id']; ?>">
                                                                            <input type="hidden" name="action" value="suspend">
                                                                            <button type="submit" class="btn btn-warning btn-sm action-btn" title="Suspend">
                                                                                <i class="fa fa-pause"></i>
                                                                            </button>
                                                                        </form>
                                                                    <?php elseif ($row['account_status'] === 'suspended'): ?>
                                                                        <form method="POST" action="<?php echo $_SERVER['PHP_SELF'] . (!empty($search) ? "?search=$search" : "") . "&status=$status_filter&page=$page"; ?>" style="display:inline;">
                                                                            <input type="hidden" name="tutor_id" value="<?php echo $row['id']; ?>">
                                                                            <input type="hidden" name="action" value="activate">
                                                                            <button type="submit" class="btn btn-success btn-sm action-btn" title="Activate">
                                                                                <i class="fa fa-play"></i>
                                                                            </button>
                                                                        </form>
                                                                    <?php endif; ?>

                                                                    <form method="POST" action="<?php echo $_SERVER['PHP_SELF'] . (!empty($search) ? "?search=$search" : "") . "&status=$status_filter&page=$page"; ?>" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this tutor? This action cannot be undone.');">
                                                                        <input type="hidden" name="tutor_id" value="<?php echo $row['id']; ?>">
                                                                        <input type="hidden" name="action" value="delete">
                                                                        <button type="submit" class="btn btn-danger btn-sm action-btn" title="Delete">
                                                                            <i class="fa fa-trash"></i>
                                                                        </button>
                                                                    </form>

                                                                    <a href="view-tutor.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm action-btn" title="View Details">
                                                                        <i class="fa fa-eye"></i>
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                    </tbody>
                                                </table>

                                                <!-- Pagination -->
                                                <?php if ($total_pages > 1): ?>
                                                    <nav aria-label="Tutor management pagination">
                                                        <ul class="pagination justify-content-center mt-4">
                                                            <?php if ($page > 1): ?>
                                                                <li class="page-item">
                                                                    <a class="page-link" href="<?php echo $_SERVER['PHP_SELF'] . "?page=" . ($page - 1) . (!empty($search) ? "&search=$search" : "") . "&status=$status_filter"; ?>">
                                                                        &laquo; Previous
                                                                    </a>
                                                                </li>
                                                            <?php else: ?>
                                                                <li class="page-item disabled">
                                                                    <a class="page-link" href="#">&laquo; Previous</a>
                                                                </li>
                                                            <?php endif; ?>

                                                            <?php
                                                            // Calculate range of page numbers to display
                                                            $start_page = max(1, $page - 2);
                                                            $end_page = min($total_pages, $page + 2);

                                                            // First page + ellipsis
                                                            if ($start_page > 1) {
                                                                echo '<li class="page-item"><a class="page-link" href="' . $_SERVER['PHP_SELF'] . '?page=1' . (!empty($search) ? "&search=$search" : "") . "&status=$status_filter" . '">1</a></li>';
                                                                if ($start_page > 2) {
                                                                    echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                                                }
                                                            }

                                                            // Main page numbers
                                                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                                    <a class="page-link" href="<?php echo $_SERVER['PHP_SELF'] . "?page=$i" . (!empty($search) ? "&search=$search" : "") . "&status=$status_filter"; ?>">
                                                                        <?php echo $i; ?>
                                                                    </a>
                                                                </li>
                                                            <?php endfor; ?>

                                                            <?php
                                                            // Last page + ellipsis
                                                            if ($end_page < $total_pages) {
                                                                if ($end_page < $total_pages - 1) {
                                                                    echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                                                }
                                                                echo '<li class="page-item"><a class="page-link" href="' . $_SERVER['PHP_SELF'] . '?page=' . $total_pages . (!empty($search) ? "&search=$search" : "") . "&status=$status_filter" . '">' . $total_pages . '</a></li>';
                                                            }
                                                            ?>

                                                            <?php if ($page < $total_pages): ?>
                                                                <li class="page-item">
                                                                    <a class="page-link" href="<?php echo $_SERVER['PHP_SELF'] . "?page=" . ($page + 1) . (!empty($search) ? "&search=$search" : "") . "&status=$status_filter"; ?>">
                                                                        Next &raquo;
                                                                    </a>
                                                                </li>
                                                            <?php else: ?>
                                                                <li class="page-item disabled">
                                                                    <a class="page-link" href="#">Next &raquo;</a>
                                                                </li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </nav>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="alert alert-info">
                                                    No tutors found matching your criteria.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <!-- END RECENT REPORT-->
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

<?php
// Close statement and connection
$stmt->close();
$conn->close();
?>
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
    $(document).ready(function() {
        // Initialize tooltips
        $('[title]').tooltip();

        // Confirm before deleting
        $('.delete-btn').click(function(e) {
            if (!confirm('Are you sure you want to delete this tutor? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
</script>
</body>
</html>