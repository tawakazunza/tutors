<?php
ob_start(); // Start output buffering at the very beginning
session_start();
include 'config.php';
include 'functions.php'; // file with trackProfileView function

// Get tutor by slug
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

if (!$slug) {
    header("Location: tutors.php");
    exit();
}

// Fetch tutor basic info by slug
$stmt = $conn->prepare("
    SELECT t.*, c.name AS city_name 
    FROM tutors t 
    LEFT JOIN cities c ON t.location_id = c.id 
    WHERE t.slug = ?
");

$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: tutors.php");
    exit();
}

$tutor = $result->fetch_assoc();
$tutor_id = $tutor['id'];

// Track this profile view
trackProfileView($conn, $tutor_id);

// Get view statistics
$stats_stmt = $conn->prepare("
    SELECT 
        total_views, 
        unique_views,
        (SELECT COUNT(DISTINCT ip_address) FROM profile_view_history WHERE tutor_id = ?) AS unique_visitors
    FROM profile_views 
    WHERE tutor_id = ?
");
$stats_stmt->bind_param("ii", $tutor_id, $tutor_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$view_stats = $stats_result->fetch_assoc();

// Fetch subjects with their IDs
$subjects = $conn->query("
    SELECT s.id, s.name 
    FROM tutor_subjects ts
    JOIN subjects s ON ts.subject_id = s.id
    WHERE ts.tutor_id = $tutor_id
");

// Fetch grades
$grades = $conn->query("
    SELECT g.level_name 
    FROM tutor_grades tg
    JOIN grades g ON tg.grade_id = g.id
    WHERE tg.tutor_id = $tutor_id
");

// Fetch platforms (actual join from DB)
$platforms = $conn->query("
    SELECT p.name 
    FROM tutor_platforms tp
    JOIN platforms p ON tp.platform_id = p.id
    WHERE tp.tutor_id = $tutor_id
");

// Calculate average rating
$rating_result = $conn->query("
    SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
    FROM reviews 
    WHERE tutor_id = $tutor_id
");
$rating_info = $rating_result->fetch_assoc();
$avg_rating = round($rating_info['avg_rating'] ?? 0, 1);
$review_count = $rating_info['review_count'] ?? 0;

// Fetch payment methods
$payment_methods = $conn->query("
    SELECT pm.method_name 
    FROM tutor_payment_methods tpm
    JOIN payment_methods pm ON tpm.payment_method_id = pm.id
    WHERE tpm.tutor_id = $tutor_id
");

// Handle review form submission
$review_success = false;
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_review'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $rating = (int) $_POST['rating'];
    $review = trim($_POST['review']);

    if ($name && $email && $rating && $review) {
        $stmt = $conn->prepare("INSERT INTO reviews (tutor_id, name, email, rating, review) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issis", $tutor_id, $name, $email, $rating, $review);
        
        if ($stmt->execute()) {
            // Set success message in session
            $_SESSION['review_success'] = true;
            // Redirect to clear POST data - using current script path
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        } else {
            $error_message = 'Error submitting review. Please try again.';
        }
    } else {
        $error_message = 'All fields are required.';
    }
}

// Check if there's a success message from previous submission
if (isset($_SESSION['review_success']) && $_SESSION['review_success'] === true) {
    $review_success = true;
    // Clear the session variable
    unset($_SESSION['review_success']);
}

// Clear the output buffer before sending any HTML
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Find trusted tutors in Zimbabwe for ECD to A Level. Learn via WhatsApp, Zoom, or in-person. Free listing for tutors. Get started today!">
    <meta property="og:image" content="https://openclass.co.zw/tutors/images/openclass-tutor.webp">
    <meta property="og:url" content="https://openclass.co.zw/tutors/">
    <meta name="author" content="Tay Digital">
    <meta name="keywords" content="OpenClass Tutors">

    <!-- Title Page-->
    <title>Openclass Tutor Profile | <?= htmlspecialchars($tutor['full_name']) ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Main CSS-->
    <link href="css/theme.css" rel="stylesheet" media="all">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400;1,700;1,900&family=Noto+Sans+Georgian:wght@100..900&display=swap" rel="stylesheet">

    <style>
        /* Font styles */
        body {
            font-family: 'Noto Sans Georgian', sans-serif;
        }
        
        h1, h2, h3, h4, h5, h6, .hero-heading, .card-title {
            font-family: 'Merriweather', serif;
        }
        
        .nav-link {
            font-family: 'Noto Sans Georgian', sans-serif;
            font-weight: 600;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }
        
        .btn {
            font-family: 'Noto Sans Georgian', sans-serif;
            font-weight: 600;
        }
        
        .card-text {
            font-family: 'Noto Sans Georgian', sans-serif;
        }
        
        .bg-primary {
            background-color: #0F1E8A !important;
        }
        
        .nav-link.active {
            border-bottom: 2px solid #dc3545;
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        /* Ensure dropdown works properly */
        .dropdown:hover .dropdown-menu {
            display: block;
        }
        
        /* Tab styles */
        .nav-tabs .nav-link {
            color: #0078d7;
        }
        .nav-tabs .nav-link:hover,
        .nav-tabs .nav-link:focus {
            background-color: #e9ecef;
            color: #0056b3;
        }
        .nav-tabs .nav-link.active {
            background-color: #0078d7;
            color: white;
            border-color: #0078d7;
        }
        .tab-content {
            padding: 0.5rem;
            border-top: none;
            border-radius: 0 0 0.25rem 0.25rem;
        }
        
        /* Image error handling */
        .profile-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #dee2e6;
        }

        /* Rating styles */
        .star-rating-display {
            font-size: 1.25rem;
            color: #ffc107;
        }
        
        .rating-text {
            color: #6c757d;
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }
        
        /* Fix for potential loading issues */
        .card {
            opacity: 1;
            transition: none !important;
        }
        
        img {
            opacity: 1 !important;
        }
    </style>
</head>
<body>
  <div class="page-wrapper">
    <!-- Top navigation bar with dark background -->
    <div class="bg-primary py-2">
        <div class="container">
            <div class="row">
                <!-- Left side links - collapses on mobile with toggle button -->
                <div class="col-lg-8 col-md-6 d-none d-md-block">
                    <div class="d-flex">
                        <a href="https://openclass.co.zw/frequently-asked-questions-on-schools-in-zimbabwe/" class="text-white me-3 text-decoration-none">FAQ's On Schools In Zim</a>
                        <a href="https://openclass.co.zw/teacher-swops/" class="text-white me-3 text-decoration-none">Teacher Swops</a>
                        <a href="https://openclass.co.zw/notes/" class="text-white me-3 text-decoration-none">Notes</a>
                        <a href="/tutors/admin/admin-login.php" class="text-white mr-3">Admin Login</a>
                        <a href="https://openclass.co.zw/contribute/" class="text-white text-decoration-none">Contribute</a>
                    </div>
                </div>
                
                <!-- Right side social icons - always visible -->
                <div class="col-lg-4 col-md-6 col-12 text-md-end text-center">
                    <a href="https://www.facebook.com/groups/846304606191536" class="text-white mx-2"><i class="fab fa-facebook-f"></i></a>
                    <a href="mailto:learn@openclass.co.zw" class="text-white mx-2"><i class="fas fa-envelope"></i></a>
                    <a href="https://www.linkedin.com/groups/9876043/" class="text-white mx-2"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main navigation -->
    <header class="bg-white shadow-sm">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-light p-0">
                <!-- Logo area -->
                <a class="navbar-brand" href="#">
                    <img src="/tutors/images/openclass.webp" alt="Openclass" width="250" height="60">
                </a>
                
                <!-- Mobile hamburger menu -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" 
                        aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <!-- Collapsible navigation content -->
                <div class="collapse navbar-collapse" id="mainNavbar">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link fw-bold text-primary" href="https://openclass.co.zw/">HOME</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link fw-bold text-primary dropdown-toggle" href="#" id="servicesDropdown" 
                               role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                OUR SERVICES
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="servicesDropdown">
                                <li><a class="dropdown-item" href="https://openclass.co.zw/school-system-development/">School System Development</a></li>
                                <li><a class="dropdown-item" href="https://openclass.co.zw/school-promotion/">School Promotion</a></li>
                                <li><a class="dropdown-item" href="https://openclass.co.zw/web-development/">Web Development</a></li>
                                <li><a class="dropdown-item" href="https://openclass.co.zw/whatsapp-bot-design-and-software-development/">Whatsapp Bot Design and Software Development</a></li>
                                <li><a class="dropdown-item" href="https://openclass.co.zw/personalized-email-hosting/">Personalized Email Hosting</a></li>
                                <li><a class="dropdown-item" href="https://openclass.co.zw/graphic-designing/">Graphic Designing</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link fw-bold text-primary dropdown-toggle" href="#" id="schoolsDropdown" 
                               role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                SCHOOL LISTINGS
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="schoolsDropdown">
                                <li><a class="dropdown-item" href="#">Primary Schools</a></li>
                                <li><a class="dropdown-item" href="#">Secondary Schools</a></li>
                                <li><a class="dropdown-item" href="#">Colleges</a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-bold text-primary" href="https://openclass.co.zw/advertise-with-us/">ADVERTISE WITH US</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-bold text-primary" href="https://openclass.co.zw/classifieds/">CLASSIFIEDS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-bold text-primary" href="https://openclass.co.zw/downloads/">DOWNLOADS</a>
                        </li>
                    </ul>
                    
                    <!-- Facebook button -->
                    <div class="my-2 my-lg-0">
                        <a href="/tutors/register.php" class="btn btn-danger rounded-pill px-4">Tutor Login</a>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <!-- Optional: Search bar (shown in the bottom of your screenshot) -->
    <div class="bg-light py-2">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search...">
                        <button class="btn btn-danger" type="button">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="row">
            <!-- LEFT COLUMN -->
            <div class="col-md-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-body text-center">
                        <?php
                        // Determine the correct image path
                        $default_image = '/tutors/uploads/avatar-default.jpg';
                        $image_path = $default_image; // Default image

                        if (!empty($tutor['profile_picture'])) {
                            // If profile picture exists, check if the file actually exists
                            $relative_path = '/tutors/uploads/' . $tutor['profile_picture'];

                            if (file_exists($relative_path)) {
                                // Use the image if file exists
                                $image_path = '/tutors/uploads/' . htmlspecialchars($tutor['profile_picture']);
                            }
                        }
                        ?>
                        
                        <div class="mb-3">
                            <img src="<?= !empty($tutor['profile_picture']) ? '/tutors/uploads/' . htmlspecialchars($tutor['profile_picture']) : $default_image ?>"
                                 alt="<?= htmlspecialchars($tutor['full_name']) ?>'s Profile Picture"
                                 class="profile-image"
                                 loading="eager">
                        </div>
                        
                        <h4 class="card-title"><?= htmlspecialchars($tutor['full_name']) ?></h4>
                        <p class="text-muted"><?= htmlspecialchars($tutor['email']) ?></p>
                        
                        <!-- ADDED: Average Rating Display -->
                        <div class="mb-3">
                            <div class="star-rating-display">
                                <?php 
                                $full_stars = floor($avg_rating);
                                $half_star = $avg_rating - $full_stars >= 0.5;
                                $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
                                
                                // Full stars
                                for ($i = 0; $i < $full_stars; $i++) {
                                    echo '★';
                                }
                                
                                // Half star
                                if ($half_star) {
                                    echo '⯪';
                                }
                                
                                // Empty stars
                                for ($i = 0; $i < $empty_stars; $i++) {
                                    echo '☆';
                                }
                                ?>
                            </div>
                            <div class="rating-text">
                                <?= $avg_rating ?>/5 rating • <?= $review_count ?> review<?= $review_count !== 1 ? 's' : '' ?>
                            </div>
                        </div>
                        
                        <!-- Better button styling with proper links -->
                        <div class="d-grid gap-2 d-md-block">
                            <a href="tel:<?= htmlspecialchars($tutor['phone_number']) ?>" class="btn btn-primary">
                                <i class="fas fa-phone-alt me-2"></i>Call Tutor
                            </a>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $tutor['phone_number']) ?>" class="btn btn-success">
                                <i class="fab fa-whatsapp me-2"></i>Chat on WhatsApp
                            </a>
                        </div>
                        
                        <hr>
                        
                        <div class="text-start">
                            <p class="mb-2">
                                <strong>Location:</strong> 
                                <a href="tutors.php?city=<?= $tutor['location_id'] ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($tutor['city_name']) ?>
                                </a>
                            </p>
                            <p class="mb-2"><strong>Rate/hr:</strong> $<?= number_format($tutor['rate_per_hour'], 2) ?></p>
                            <p class="mb-2"><strong>Teaching Method:</strong> <?= ucfirst($tutor['teaching_method']) ?></p>
                        </div>
                        
                        <?php if ($tutor['teaching_method'] == 'online' || $tutor['teaching_method'] == 'both'): ?>
                            <hr>
                            <p class="mb-2"><strong>Platforms:</strong></p>
                            <div class="d-flex flex-wrap gap-2 justify-content-center">
                                <?php 
                                $platforms->data_seek(0);
                                while ($p = $platforms->fetch_assoc()): 
                                    $platformName = htmlspecialchars($p['name']);
                                    $platformUrl = urlencode($p['name']);
                                ?>
                                    <a class="btn btn-outline-warning btn-sm" href="/tutors/tutors.php?platform=<?= $platformUrl ?>">
                                        <?= $platformName ?>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN -->
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="home-tab" data-bs-toggle="tab" data-bs-target="#home" type="button" role="tab" aria-controls="home" aria-selected="true">Biography</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="false">Reviews</button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="myTabContent">
                            <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
                                <p><?= nl2br(htmlspecialchars($tutor['biography'])) ?></p>
                            </div>
                            <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                                <!-- Reviews Tab Content -->
                                <h5 class="mb-3">Student Reviews</h5>
                                <?php
                                $review_stmt = $conn->prepare("SELECT * FROM reviews WHERE tutor_id = ? ORDER BY created_at DESC");
                                $review_stmt->bind_param("i", $tutor_id);
                                $review_stmt->execute();
                                $reviews_result = $review_stmt->get_result();

                                if ($reviews_result->num_rows > 0):
                                    while ($rev = $reviews_result->fetch_assoc()):
                                ?>
                                    <div class="mb-3 border-bottom pb-2">
                                        <strong><?= htmlspecialchars($rev['name']) ?></strong> 
                                        <span class="text-warning">
                                            <?php
                                            for ($i = 0; $i < $rev['rating']; $i++) {
                                                echo '★';
                                            }
                                            for ($i = 0; $i < 5 - $rev['rating']; $i++) {
                                                echo '☆';
                                            }
                                            ?>
                                        </span>
                                        <br>
                                        <small class="text-muted"><?= date("F j, Y", strtotime($rev['created_at'])) ?></small>
                                        <p><?= nl2br(htmlspecialchars($rev['review'])) ?></p>
                                    </div>
                                <?php endwhile; else: ?>
                                    <p>No reviews yet. Be the first to leave one!</p>
                                <?php endif; ?>

                                <!-- Submit a new review -->
                                <hr>
                                <h5 class="mt-4">Leave a Review</h5>
                                
                                <?php if ($review_success): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        Thank you for your review!
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?= htmlspecialchars($error_message) ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" class="mt-3" id="reviewForm">
                                    <div class="mb-2">
                                        <input type="text" name="name" class="form-control" placeholder="Your name" required>
                                    </div>
                                    <div class="mb-2">
                                        <input type="email" name="email" class="form-control" placeholder="Your email" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Rating:</label>
                                        <div id="star-rating" class="d-flex gap-1">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="star fs-3 text-muted" data-value="<?= $i ?>" style="cursor:pointer;">☆</span>
                                            <?php endfor; ?>
                                        </div>
                                        <input type="hidden" name="rating" id="rating" required>
                                    </div>
                                    <div class="mb-2">
                                        <textarea name="review" class="form-control" rows="3" placeholder="Your review" required></textarea>
                                    </div>
                                    <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5>Subjects Taught</h5>
                        <ul class="list-inline">
                            <?php
                            $subjects->data_seek(0);
                            while ($s = $subjects->fetch_assoc()):
                                $sName = htmlspecialchars($s['name']);
                                $sId = $s['id'];
                            ?>
                                <li class="list-inline-item">
                                    <a href="/tutors/tutors.php?subject=<?= $sId ?>" class="btn btn-outline-success"><?= $sName ?></a>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5>Grade Levels</h5>
                        <ul class="list-inline">
                            <?php
                            $grades->data_seek(0);
                            while ($g = $grades->fetch_assoc()):
                                $gName = htmlspecialchars($g['level_name']);
                                $gIdQuery = $conn->query("SELECT id FROM grades WHERE level_name = '{$conn->real_escape_string($gName)}' LIMIT 1");
                                $gId = $gIdQuery->fetch_assoc()['id'];
                            ?>
                                <li class="list-inline-item">
                                    <a class="btn btn-outline-secondary" href="/tutors/tutors.php?grade=<?= $gId ?>"><?= $gName ?></a>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
  </div>

  <!-- Bootstrap JS - Only load one version -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
        const stars = document.querySelectorAll('.star');
        const ratingInput = document.getElementById('rating');

        stars.forEach(star => {
            star.addEventListener('click', function () {
                const value = this.getAttribute('data-value');
                ratingInput.value = value;

                updateStars(value);
            });
        });
        
        function updateStars(value) {
            stars.forEach(s => {
                s.textContent = s.getAttribute('data-value') <= value ? '★' : '☆';
                s.classList.toggle('text-warning', s.getAttribute('data-value') <= value);
                s.classList.toggle('text-muted', s.getAttribute('data-value') > value);
            });
        }
    });
  </script>
</body>
</html>