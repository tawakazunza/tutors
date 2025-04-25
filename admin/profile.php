<?php
// profile.php - Admin profile management page

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.php");
    exit;
}

// Include database configuration
require_once 'config.php';

// Initialize variables for profile updates
$new_username = $new_email = $current_password = $new_password = $confirm_password = '';
$username_err = $email_err = $current_password_err = $new_password_err = $confirm_password_err = '';
$profile_update_msg = $password_update_msg = $profile_pic_msg = '';
$admin_id = $_SESSION['id'];

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch current admin information
$stmt = $conn->prepare("SELECT username, email, full_name, profile_pic FROM admin_users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_data = $result->fetch_assoc();
$stmt->close();

// Update profile picture
if (isset($_POST['update_profile_pic'])) {
    // Check if image file is a actual image or fake image
    if(isset($_FILES["profile_pic"]) && $_FILES["profile_pic"]["error"] == 0) {
        $allowed_types = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png");
        $file_name = $_FILES["profile_pic"]["name"];
        $file_type = $_FILES["profile_pic"]["type"];
        $file_size = $_FILES["profile_pic"]["size"];
        
        // Verify file extension
        $ext = pathinfo($file_name, PATHINFO_EXTENSION);
        
        if (array_key_exists($ext, $allowed_types) && in_array($file_type, $allowed_types)) {
            // Check file size - 5MB maximum
            $maxsize = 5 * 1024 * 1024;
            if ($file_size > $maxsize) {
                $profile_pic_msg = "Error: File size is larger than the allowed limit (5MB).";
            } else {
                // Create unique filename
                $new_filename = "admin_" . $admin_id . "_" . time() . "." . $ext;
                $upload_dir = "uploads/profile_pics/";
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $target_file = $upload_dir . $new_filename;
                
                // Upload file
                if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
                    // Delete old profile picture if it exists and is not the default
                    if (!empty($admin_data['profile_pic']) && $admin_data['profile_pic'] != 'default.png' && file_exists($upload_dir . $admin_data['profile_pic'])) {
                        unlink($upload_dir . $admin_data['profile_pic']);
                    }
                    
                    // Update database with new profile picture
                    $stmt = $conn->prepare("UPDATE admin_users SET profile_pic = ? WHERE id = ?");
                    $stmt->bind_param("si", $new_filename, $admin_id);
                    
                    if ($stmt->execute()) {
                        $profile_pic_msg = "Profile picture updated successfully.";
                        $admin_data['profile_pic'] = $new_filename; // Update current page data
                    } else {
                        $profile_pic_msg = "Error updating profile picture in database.";
                    }
                    $stmt->close();
                } else {
                    $profile_pic_msg = "Error uploading file.";
                }
            }
        } else {
            $profile_pic_msg = "Error: Please upload a valid image file (JPG, JPEG, PNG, GIF).";
        }
    } else {
        $profile_pic_msg = "Error: " . $_FILES["profile_pic"]["error"];
    }
}

// Update profile information (username and email)
if (isset($_POST['update_profile'])) {
    // Validate new username
    if (empty(trim($_POST['new_username']))) {
        $username_err = "Please enter a username.";
    } else {
        $new_username = trim($_POST['new_username']);
        
        // Check if username is different from current one
        if ($new_username != $admin_data['username']) {
            // Check if username already exists
            $stmt = $conn->prepare("SELECT id FROM admin_users WHERE username = ? AND id != ?");
            $stmt->bind_param("si", $new_username, $admin_id);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $username_err = "This username is already taken.";
            }
            $stmt->close();
        }
    }
    
    // Validate new email
    if (empty(trim($_POST['new_email']))) {
        $email_err = "Please enter an email.";
    } else {
        $new_email = trim($_POST['new_email']);
        
        // Check if email is different from current one
        if ($new_email != $admin_data['email']) {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM admin_users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $new_email, $admin_id);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $email_err = "This email is already taken.";
            }
            $stmt->close();
        }
    }
    
    // Check input errors before updating the database
    if (empty($username_err) && empty($email_err)) {
        // Update admin info in the database
        $stmt = $conn->prepare("UPDATE admin_users SET username = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $new_username, $new_email, $admin_id);
        
        if ($stmt->execute()) {
            // Update session username if changed
            if ($_SESSION['username'] != $new_username) {
                $_SESSION['username'] = $new_username;
            }
            
            // Update the page data
            $admin_data['username'] = $new_username;
            $admin_data['email'] = $new_email;
            
            $profile_update_msg = "Profile updated successfully.";
        } else {
            $profile_update_msg = "Something went wrong. Please try again later.";
        }
        $stmt->close();
    }
}

// Update password
if (isset($_POST['update_password'])) {
    // Validate current password
    if (empty(trim($_POST['current_password']))) {
        $current_password_err = "Please enter your current password.";
    } else {
        $current_password = trim($_POST['current_password']);
        
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM admin_users WHERE id = ?");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (!password_verify($current_password, $row['password'])) {
                $current_password_err = "The current password you entered is not correct.";
            }
        }
        $stmt->close();
    }
    
    // Validate new password
    if (empty(trim($_POST['new_password']))) {
        $new_password_err = "Please enter the new password.";
    } elseif (strlen(trim($_POST['new_password'])) < 8) {
        $new_password_err = "Password must have at least 8 characters.";
    } else {
        $new_password = trim($_POST['new_password']);
    }
    
    // Validate confirm password
    if (empty(trim($_POST['confirm_password']))) {
        $confirm_password_err = "Please confirm the password.";
    } else {
        $confirm_password = trim($_POST['confirm_password']);
        if (empty($new_password_err) && ($new_password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Check input errors before updating the database
    if (empty($current_password_err) && empty($new_password_err) && empty($confirm_password_err)) {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password in the database
        $stmt = $conn->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $admin_id);
        
        if ($stmt->execute()) {
            $password_update_msg = "Password updated successfully.";
        } else {
            $password_update_msg = "Something went wrong. Please try again later.";
        }
        $stmt->close();
    }
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Required meta tags-->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="au theme template">
    <meta name="author" content="Hau Nguyen">
    <meta name="keywords" content="au theme template">

    <!-- Title Page-->
    <title>Openclass | Admin Profile</title>

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
                                <?php if (!empty($_SESSION['profile_pic']) && file_exists($_SESSION['profile_pic'])): ?>
                                    <img src="<?= htmlspecialchars($_SESSION['profile_pic']) ?>" alt="<?= htmlspecialchars($_SESSION['tutor_name']) ?>" />
                                <?php else: ?>
                                    <img src="../uploads/avatar-default.jpg" alt="<?= htmlspecialchars($_SESSION['tutor_name']) ?>" />
                                <?php endif; ?>
                                </div>
                                <div class="content">
                                    <a class="js-acc-btn" href="#"><?= htmlspecialchars($_SESSION['tutor_name']) ?></a>
                                </div>
                                <div class="account-dropdown js-dropdown">
                                    <div class="info clearfix">
                                        <div class="image">
                                        <?php if (!empty($_SESSION['profile_pic']) && file_exists($_SESSION['profile_pic'])): ?>
                                            <img src="<?= htmlspecialchars($_SESSION['profile_pic']) ?>" alt="<?= htmlspecialchars($_SESSION['full_name']) ?>" />
                                        <?php else: ?>
                                            <img src="../uploads/avatar-default.jpg" alt="<?= htmlspecialchars($_SESSION['full_name']) ?>" />
                                        <?php endif; ?>
                                        </div>
                                        <div class="content">
                                            <h5 class="name">
                                                <a href="#"><?= htmlspecialchars($_SESSION['tutor_name']) ?></a>
                                            </h5>
                                            <span class="email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></span>
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
        <section class="welcome2 p-t-40 p-b-55">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <div class="au-breadcrumb3">
                            <div class="au-breadcrumb-left">
                                <span class="au-breadcrumb-span">You are here:</span>
                                <ul class="list-unstyled list-inline au-breadcrumb__list">
                                    <li class="list-inline-item active">
                                        <a href="#">Home</a>
                                    </li>
                                    <li class="list-inline-item seprate">
                                        <span>/</span>
                                    </li>
                                    <li class="list-inline-item"><a href="dashboard.php">Dashboard</a></li>
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
                                <p>Administrative pages</p>
                            </div>
                            <form class="form-header form-header2" action="" method="post">
                                <input class="au-input au-input--w435" type="text" name="search" placeholder="Search for datas &amp; reports...">
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
                                            <a class="js-arrow" href="#">
                                                <i class="fas fa-tachometer-alt"></i>Dashboard
                                                <span class="arrow">
                                                    <i class="fas fa-angle-down"></i>
                                                </span>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="notifications.php">
                                                <i class="fas fa-chart-bar"></i>Inbox</a>
                                            <span class="inbox-num">3</span>
                                        </li>
                                        <li>
                                            <a href="manage-tutors.php">
                                                <i class="fas fa-user"></i>Manage Tutors</a>
                                        </li>
                                        <li>
                                            <a href="add-city.php">
                                            <i class="fas fa-star"></i>Manage Cities</a>
                                        </li>
                                        <li>
                                            <a href="add-grade.php">
                                                <i class="fas fa-user"></i>Manage Grades</a>
                                        </li>
                                        <li>
                                            <a href="add-payments.php">
                                                <i class="fas fa-user"></i>Manage Payments</a>
                                        </li>
                                        <li>
                                            <a href="add-payments.php">
                                                <i class="fas fa-user"></i>Manage Subjects</a>
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
                                        <div class="recent-report3 m-b-40">
                                            <div class="title-wrap">
                                                <!-- Profile Picture Section -->
                                                <div class="profile-section">
                                                    <h4>Profile Picture</h4>
                                                    <?php if(!empty($profile_pic_msg)): ?>
                                                        <div class="alert alert-<?php echo strpos($profile_pic_msg, 'Error') !== false ? 'danger' : 'success'; ?>">
                                                            <?php echo $profile_pic_msg; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-3 text-center">
                                                            <img src="<?php echo !empty($admin_data['profile_pic']) ? 'uploads/profile_pics/' . htmlspecialchars($admin_data['profile_pic']) : 'uploads/profile_pics/default.png'; ?>" 
                                                                class="profile-pic mb-3" alt="Profile Picture">
                                                        </div>
                                                        <div class="col-md-9">
                                                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                                                                <div class="mb-3">
                                                                    <label for="profile_pic" class="form-label">Upload New Picture</label>
                                                                    <input type="file" class="form-control" id="profile_pic" name="profile_pic" accept="image/*" class="form-control-file">
                                                                </div>
                                                                <button type="submit" name="update_profile_pic" class="btn btn-primary">Update Picture</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="filters m-b-55">
                                            </div>
                                            <div class="chart-wrap">
                                                <!-- Profile Information Section -->
                                                <div class="profile-section">
                                                    <h4>Profile Information</h4>
                                                    <?php if(!empty($profile_update_msg)): ?>
                                                        <div class="alert alert-<?php echo strpos($profile_update_msg, 'went wrong') !== false ? 'danger' : 'success'; ?>">
                                                            <?php echo $profile_update_msg; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                                        <div class="mb-3">
                                                            <label for="new_username" class="form-label">Username</label>
                                                            <input type="text" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" 
                                                                id="new_username" name="new_username" value="<?php echo htmlspecialchars($admin_data['username']); ?>">
                                                            <span class="invalid-feedback"><?php echo $username_err; ?></span>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="new_email" class="form-label">Email</label>
                                                            <input type="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" 
                                                                id="new_email" name="new_email" value="<?php echo htmlspecialchars($admin_data['email']); ?>">
                                                            <span class="invalid-feedback"><?php echo $email_err; ?></span>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="full_name" class="form-label">Full Name (read-only)</label>
                                                            <input type="text" class="form-control" id="full_name" value="<?php echo htmlspecialchars($admin_data['full_name']); ?>" readonly>
                                                        </div>
                                                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- END RECENT REPORT-->
                                        <div class="row">
                                    <div class="col-lg-12">
                                        <div class="au-card au-card--no-shadow au-card--no-pad m-b-40 au-card--border">
                                            <div class="au-card-title">
                                                                                                   
                                                <button class="au-btn-plus">
                                                    <i class="zmdi zmdi-plus"></i>
                                                </button>
                                            </div>
                                            <div class="recent-report3 m-b-40">
                                                    <!-- Change Password Section -->
                                                    <div class="profile-section">
                                                        <h4>Change Password</h4>
                                                        <?php if(!empty($password_update_msg)): ?>
                                                            <div class="alert alert-<?php echo strpos($password_update_msg, 'went wrong') !== false ? 'danger' : 'success'; ?>">
                                                                <?php echo $password_update_msg; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                                            <div class="mb-3">
                                                                <label for="current_password" class="form-label">Current Password</label>
                                                                <input type="password" class="form-control <?php echo (!empty($current_password_err)) ? 'is-invalid' : ''; ?>" 
                                                                    id="current_password" name="current_password">
                                                                <span class="invalid-feedback"><?php echo $current_password_err; ?></span>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="new_password" class="form-label">New Password</label>
                                                                <input type="password" class="form-control <?php echo (!empty($new_password_err)) ? 'is-invalid' : ''; ?>" 
                                                                    id="new_password" name="new_password">
                                                                <span class="invalid-feedback"><?php echo $new_password_err; ?></span>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                                <input type="password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" 
                                                                    id="confirm_password" name="confirm_password">
                                                                <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                                                            </div>
                                                            <button type="submit" name="update_password" class="btn btn-primary">Change Password</button>
                                                        </form>
                                                    </div>  
                                            </div>
                                        </div>
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
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(input) {
            var preview = document.getElementById('preview');
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
    </script>
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
</body>
</html>