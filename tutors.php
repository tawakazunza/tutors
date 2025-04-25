<?php
include 'config.php';

// Get all filter parameters
$subject = isset($_GET['subject']) ? intval($_GET['subject']) : null;
$grade = isset($_GET['grade']) ? intval($_GET['grade']) : null;
$city = isset($_GET['city']) ? intval($_GET['city']) : null;
$method = isset($_GET['method']) ? $_GET['method'] : null;
$name_search = isset($_GET['name_search']) ? trim($_GET['name_search']) : null;

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 6;
$offset = ($page - 1) * $limit;

// Query builder
$conditions = [];
$params = [];
$param_types = ''; // Track parameter types for prepared statement

// Track which tables we've already joined
$joined_tables = [];

// Name search condition (modified to work with LIKE)
if (!empty($name_search)) {
    $conditions[] = "t.full_name LIKE ?";
    $params[] = '%' . $name_search . '%';
    $param_types .= 's';
}

// Existing filter conditions
if ($subject) {
    $joined_tables['tutor_subjects'] = true;
    $conditions[] = "ts_filter.subject_id = ?";
    $params[] = $subject;
    $param_types .= 'i';
}
if ($grade) {
    $joined_tables['tutor_grades'] = true;
    $conditions[] = "tg_filter.grade_id = ?";
    $params[] = $grade;
    $param_types .= 'i';
}
if ($city) {
    $conditions[] = "t.location_id = ?";
    $params[] = $city;
    $param_types .= 'i';
}
if ($method) {
    $conditions[] = "t.teaching_method = ?";
    $params[] = $method;
    $param_types .= 's';
}

$where = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Build the main query with proper joins
$joins = "";
if (isset($joined_tables['tutor_subjects'])) {
    $joins .= " INNER JOIN tutor_subjects ts_filter ON ts_filter.tutor_id = t.id ";
}
if (isset($joined_tables['tutor_grades'])) {
    $joins .= " INNER JOIN tutor_grades tg_filter ON tg_filter.tutor_id = t.id ";
}

$query = "
    SELECT DISTINCT t.*, c.name AS city_name,
    t.slug,  
    GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as subjects,
    GROUP_CONCAT(DISTINCT pm.method_name SEPARATOR ',') as payment_methods,
    GROUP_CONCAT(DISTINCT g.level_name SEPARATOR ',') as grades,
    AVG(r.rating) as avg_rating,
    COUNT(r.id) as review_count
    FROM tutors t 
    LEFT JOIN cities c ON t.location_id = c.id
    LEFT JOIN tutor_subjects ts ON ts.tutor_id = t.id
    LEFT JOIN subjects s ON ts.subject_id = s.id
    LEFT JOIN tutor_payment_methods tpm ON tpm.tutor_id = t.id
    LEFT JOIN payment_methods pm ON tpm.payment_method_id = pm.id
    LEFT JOIN tutor_grades tg ON tg.tutor_id = t.id
    LEFT JOIN grades g ON tg.grade_id = g.id
    LEFT JOIN reviews r ON r.tutor_id = t.id
    $joins 
    $where 
    GROUP BY t.id
    LIMIT $limit OFFSET $offset
";
// Get total count for pagination
$count_query = "
    SELECT COUNT(DISTINCT t.id) as total 
    FROM tutors t 
    LEFT JOIN cities c ON t.location_id = c.id
    LEFT JOIN tutor_subjects ts ON ts.tutor_id = t.id
    LEFT JOIN subjects s ON ts.subject_id = s.id
    LEFT JOIN tutor_payment_methods tpm ON tpm.tutor_id = t.id
    LEFT JOIN payment_methods pm ON tpm.payment_method_id = pm.id
    LEFT JOIN tutor_grades tg ON tg.tutor_id = t.id
    LEFT JOIN grades g ON tg.grade_id = g.id
    LEFT JOIN reviews r ON r.tutor_id = t.id
    $joins 
    $where
";

$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-5071836192516328"
            crossorigin="anonymous"></script>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-TNGSE543ZB"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'G-TNGSE543ZB');
    </script>
    <!-- Required meta tags-->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Find trusted tutors in Zimbabwe for ECD to A Level. Learn via WhatsApp, Zoom, or in-person. Free listing for tutors. Get started today!">
    <meta property="og:image" content="https://openclass.co.zw/tutors/images/openclass-tutor.webp">
    <meta property="og:url" content="https://openclass.co.zw/tutors/">
    <meta name="author" content="Tay Digital">
    <meta name="keywords" content="OpenClass Tutors">

    <!-- Title Page-->
    <title>Openclass | Tutors</title>

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
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,300;0,400;0,700;0,900;1,300;1,400;1,700;1,900&family=Noto+Sans+Georgian:wght@100..900&display=swap" rel="stylesheet">

</head>
<body class="animsition">
<div class="page-wrapper">
    <!-- HEADER DESKTOP-->
    <!-- Top navigation bar with dark background -->
    <div class="bg-primary py-2">
        <div class="container">
            <div class="row">
                <div class="col-lg-3"></div>
                <!-- Left side links - collapses on mobile with toggle button -->
                <div class="col-lg-6 col-md-6 d-none d-md-block topbar">
                    <div class="d-flex">
                        <a href="https://openclass.co.zw/frequently-asked-questions-on-schools-in-zimbabwe/" class="text-white mr-3">FAQ's On Schools In Zim |</a>
                        <a href="https://openclass.co.zw/teacher-swops/" class="text-white mr-3">Teacher Swops |</a>
                        <a href="https://openclass.co.zw/notes/" class="text-white mr-3">Notes |</a>
                        <a href="https://openclass.co.zw/contribute/" class="text-white">Contribute</a>
                    </div>
                </div>

                <!-- Right side social icons - always visible -->
                <div class="col-lg-3 col-md-6 col-12 text-md-right text-center">
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
                

                <!-- Mobile hamburger menu -->
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNavbar"
                        aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <!-- Collapsible navigation content -->
                <div class="collapse navbar-collapse" id="mainNavbar">
                    <ul class="navbar-nav mr-auto">
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold text-primary" href="https://openclass.co.zw/">HOME</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link font-weight-bold text-primary dropdown-toggle" href="#" id="servicesDropdown"
                               role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                OUR SERVICES
                            </a>
                            <div class="dropdown-menu" aria-labelledby="servicesDropdown">
                                <a class="dropdown-item" href="https://openclass.co.zw/school-system-development/">School System Development</a>
                                <a class="dropdown-item" href="https://openclass.co.zw/school-promotion/">School Promotion</a>
                                <a class="dropdown-item" href="https://openclass.co.zw/web-development/">Web Development</a>
                                <a class="dropdown-item" href="https://openclass.co.zw/whatsapp-bot-design-and-software-development/">Whatsapp Bot Design and Software Development</a>
                                <a class="dropdown-item" href="https://openclass.co.zw/personalized-email-hosting/">Personalized Email Hosting</a>
                                <a class="dropdown-item" href="https://openclass.co.zw/graphic-designing/">Graphic Designing</a>
                            </div>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link font-weight-bold text-primary dropdown-toggle" href="#" id="schoolsDropdown"
                               role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                SCHOOL LISTINGS
                            </a>
                            <div class="dropdown-menu" aria-labelledby="schoolsDropdown">
                                <a class="dropdown-item" href="https://openclass.co.zw/top-ten-boarding-schools-in-zimbabwe/">Boarding Schools</a>
                                <a class="dropdown-item" href="https://openclass.co.zw/schools-offering-icdl-in-zimbabwe/">ICDL Schools</a>
                                <a class="dropdown-item" href="https://openclass.co.zw/girls-high-schools-in-zimbabwe/">Girls Schools</a>
                                <a class="dropdown-item" href="https://openclass.co.zw/boys-high-schools-in-zimbabwe/">Boys Schools</a>
                                <a class="dropdown-item" href="https://openclass.co.zw/tag/mixed-school/">Mixed Schools</a>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold text-primary" href="https://openclass.co.zw/advertise-with-us/">ADVERTISE WITH US</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold text-primary" href="https://openclass.co.zw/classifieds/">CLASSIFIEDS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold text-primary active" href="https://openclass.co.zw/tutors/" style="border-bottom: 2px solid red;">TUTORS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold text-primary" href="https://openclass.co.zw/downloads/">DOWNLOADS</a>
                        </li>
                    </ul>

                    <!-- Facebook button -->
                    <div class="my-2 my-lg-0">
                        <a style="radius: 50px;" href="tutor-login.php" class="btn btn-danger rounded-pill px-2" data-toggle="tooltip" data-placement="bottom" title="Whatsapp Group With Tutors">Join Whatsapp Group</a>
                    </div>

                </div>
            </nav>
        </div>
    </header>

    <!-- Search bar -->
    <div class="bg-light py-2">
        <div class="container">
            <div class="row justify-content-center">
            </div>
        </div>
    </div>

    <style>
        .bg-primary {
            background-color: #0F1E8A !important; 
        }
        /* Font styles */
        body {
            font-family: 'Noto Sans Georgian', sans-serif;
        }

        h1, h2, h3, h4, h5, h6, .hero-heading, .card-title {
            font-family: 'Merriweather', serif;
        }

        .topbar {
            font-family: 'Merriweather', sans-serif;
            font-weight: 400;
            size:0.875rem !important;
        }

        #mainNavbar {
            height: 90px !important;
        }

        .nav-link {
            font-family: 'Merriweather', sans-serif;
            font-weight: 700;
            color:  #0F1E8A !important;
            size:16px
        }

        .btn {
            font-family: 'Noto Sans Georgian', sans-serif;
            font-weight: 600;
        }

        .card-text {
            font-family: 'Noto Sans Georgian', sans-serif;
        }

        .nav-link {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        .nav-link.active {
            border-bottom: 2px solid #B12929;
        }

        .btn-danger {
            background-color: #B12929;
        }
        .payment-pills {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 0.3rem;
            margin-left: 0.5rem;
        }

        .payment-pills .badge {
            font-weight: normal;
            padding: 0.4rem 0.8rem;
            background-color: #f8f9fa;
            border-color: #dee2e6;
        }
        /* Loading spinner styles */
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        .alert-info {
            border-left: 4px solid #17a2b8;
            background-color: #f8f9fa;
        }

        /* Mobile optimizations */
        @media (max-width: 991.98px) {
            .navbar-nav {
                padding: 1rem 0;
            }

            .nav-link.active {
                border-bottom: none;
                border-left: 4px solid #B12929;
                padding-left: 0.5rem;
            }

            .my-2 {
                text-align: center;
                margin-bottom: 1rem !important;
            }
        }
    </style>


    <!-- CSS for responsive hero section -->
    <style>
        /* Ensure image has proper height */
        .hero-image {
            height: 300px;
            object-fit: cover;
        }

        /* Responsive heading size */
        .hero-heading {
            font-size: 1.75rem;
        }
                .subject-pill {
            display: inline-block;
            margin-right: 5px;
            margin-bottom: 10px;
            padding: 0.50em 0.8em;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .subject-pill:hover {
            background-color: #E2322D !important;
            color: white !important;
            transform: translateY(-1px);
        }


        /* Responsive styling for larger screens */
        @media (min-width: 768px) {
            /* No Results Styling */
            .no-results-card {
                background-color: #f8f9fa;
                border-radius: 10px;
                padding: 40px;
                margin: 20px 0;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                border: 1px dashed #dee2e6;
            }

            .no-results-icon {
                color: #6c757d;
                opacity: 0.7;
            }

            .no-results-card h3 {
                color: #343a40;
                font-weight: 600;
            }

            .suggestions {
                max-width: 400px;
                margin: 0 auto;
                text-align: left;
            }

            .suggestions li {
                padding: 5px 0;
                color: #495057;
            }

            .reset-filters {
                padding: 8px 24px;
                font-weight: 500;
                border-radius: 50px;
                transition: all 0.3s ease;
            }

            .reset-filters:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }

            .hero-heading {
                font-size: 2.25rem;
            }

            .text-content {
                max-width: 90%;
                margin: 0 auto;
            }
        }

        @media (min-width: 992px) {
            .hero-heading {
                font-size: 2.5rem;
            }
        }

        /* Fix for button width on mobile */
        @media (max-width: 767px) {
            .btn {
                max-width: 90%;
            }
        }
    </style>

    <!-- CSS styles for hero section -->
    <style>
        .hero-section {
            margin-bottom: 3rem;
        }

        .hero-section h1 {
            font-size: 2.2rem;
            line-height: 1.3;
        }

        .hero-section p {
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .btn-danger {
            background-color: #e2322d;
            border-color: #e2322d;
        }

        .btn-outline-danger {
            color: #e2322d;
            border-color: #e2322d;
        }

        .btn-outline-danger:hover {
            background-color: #e2322d;
            color: white;
        }

        .object-fit-cover {
            object-fit: cover;
        }

        @media (max-width: 991px) {
            .hero-section .row {
                flex-direction: column-reverse;
            }

            .hero-section .col-lg-7 img {
                max-height: 350px;
            }
        }
    </style>
    <!-- END  HERO SECTION -->


    <div class="container py-5">
        <h2 class="mb-4 text-center">Find a Tutor</h2>
        <!-- AdSense Ad: Above Search Form -->
        <div class="text-center my-4">
            <ins class="adsbygoogle"
                 style="display:block"
                 data-ad-client="ca-pub-5071836192516328"
                 data-ad-slot="2355224655"
                 data-ad-format="auto"
                 data-full-width-responsive="true"></ins>
            <script>
                (adsbygoogle = window.adsbygoogle || []).push({});
            </script>
        </div>

                <!-- Search bar with name search and filters -->
        <div class="bg-light py-3">
            <div class="container">
                <form id="tutorSearchForm" method="get" class="row g-3">
                    <!-- Hidden fields to preserve other filters when searching by name -->
                    <?php if ($subject): ?>
                        <input type="hidden" name="subject" value="<?= $subject ?>">
                    <?php endif; ?>
                    <?php if ($grade): ?>
                        <input type="hidden" name="grade" value="<?= $grade ?>">
                    <?php endif; ?>
                    <?php if ($city): ?>
                        <input type="hidden" name="city" value="<?= $city ?>">
                    <?php endif; ?>
                    <?php if ($method): ?>
                        <input type="hidden" name="method" value="<?= $method ?>">
                    <?php endif; ?>
                    
                    <!-- Name Search Field -->
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="text" name="name_search" class="form-control" 
                                placeholder="Search by tutor name..." 
                                value="<?= htmlspecialchars($name_search ?? '') ?>">
                            <div class="input-group-append">
                                <button class="btn btn-danger" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Existing Filters -->
                    <div class="col-md-2">
                        <select name="subject" class="form-control" onchange="this.form.submit()">
                            <option value="">All Subjects</option>
                            <?php
                            $subs = $conn->query("SELECT * FROM subjects");
                            while ($s = $subs->fetch_assoc()) {
                                $selected = ($subject == $s['id']) ? "selected" : "";
                                echo "<option value='{$s['id']}' $selected>{$s['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <select name="grade" class="form-control" onchange="this.form.submit()">
                            <option value="">All Grades</option>
                            <?php
                            $grades_list = $conn->query("SELECT * FROM grades");
                            while ($g = $grades_list->fetch_assoc()) {
                                $selected = ($grade == $g['id']) ? "selected" : "";
                                echo "<option value='{$g['id']}' $selected>{$g['level_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <select name="city" class="form-control" onchange="this.form.submit()">
                            <option value="">All Locations</option>
                            <?php
                            $cities = $conn->query("SELECT * FROM cities");
                            while ($c = $cities->fetch_assoc()) {
                                $selected = ($city == $c['id']) ? "selected" : "";
                                echo "<option value='{$c['id']}' $selected>{$c['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <select name="method" class="form-control" onchange="this.form.submit()">
                            <option value="">All Methods</option>
                            <option value="online" <?= $method == "online" ? "selected" : "" ?>>Online</option>
                            <option value="in_person" <?= $method == "in_person" ? "selected" : "" ?>>In Person</option>
                            <option value="both" <?= $method == "both" ? "selected" : "" ?>>Both</option>
                        </select>
                    </div>
                    
                    <div class="col-md-12 mt-2 text-center">
                        <a href="?" class="btn btn-secondary">Reset All Filters</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tutors Grid -->
        <div class="row mt-4">
            <?php
            $counter = 0;
            while ($tutor = $result->fetch_assoc()):
            $counter++;
            ?>
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <!-- Profile Picture -->
                        <div class="text-center mb-3">
                            <?php if(!empty($tutor['profile_picture'])): ?>
                                <img src="uploads/<?= htmlspecialchars($tutor['profile_picture']) ?>"
                                     class="rounded-circle img-thumbnail"
                                     alt="<?= htmlspecialchars($tutor['full_name']) ?>"
                                     style="width: 100px; height: 100px; object-fit: cover;">
                            <?php else: ?>
                                <img src="uploads/avatar-default.jpg"
                                     class="rounded-circle img-thumbnail"
                                     alt="Default Profile"
                                     style="width: 100px; height: 100px; object-fit: cover;">
                            <?php endif; ?>
                        </div>

                        <!-- Tutor name -->
                        <h5 class="card-title text-center"><?= htmlspecialchars($tutor['full_name']) ?></h5>

                        <!-- Rating display -->
                        <div class="text-center mb-2">
                            <?php
                            $rating = round($tutor['avg_rating'] ?? 0);
                            $review_count = $tutor['review_count'] ?? 0;

                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $rating) {
                                    echo '<i class="fas fa-star text-warning"></i>';
                                } else {
                                    echo '<i class="far fa-star text-warning"></i>';
                                }
                            }

                            echo '<span class="ml-2">(' . ($review_count > 0 ? number_format($tutor['avg_rating'], 1) : 'No') . ' ';
                            echo $review_count == 1 ? 'Review' : 'Reviews';
                            echo ')</span>';
                            ?>
                        </div>

                        <!-- Location and teaching method -->
                        <p class="card-text"><i class="fas fa-map-marker-alt mr-2"></i> <?= htmlspecialchars($tutor['city_name']) ?></p>
                        <p class="card-text"><i class="fas fa-chalkboard-teacher mr-2"></i><strong>Teaching Platform :</strong> <?= ucfirst($tutor['teaching_method']) ?></p>

                        <!-- Subjects taught -->
                        <p class="card-text">
                            <strong>Subjects:</strong>
                            <?php if(!empty($tutor['subjects'])): ?>
                                <?php 
                                // First, store the exploded subjects in a variable
                                $subject_list = explode(', ', $tutor['subjects']);
                                
                                // Then loop through the variable
                                foreach($subject_list as $subject_name): 
                                    // Clean up the subject name
                                    $subject_name = trim($subject_name);
                                    
                                    // Get subject ID for this subject name
                                    $subj_stmt = $conn->prepare("SELECT id FROM subjects WHERE name = ?");
                                    $subj_stmt->bind_param("s", $subject_name);
                                    $subj_stmt->execute();
                                    $subj_result = $subj_stmt->get_result();
                                    $subject_data = $subj_result->fetch_assoc();
                                    $subject_id = $subject_data['id'] ?? 0;
                                    
                                    // Build URL with all current filters plus the subject filter
                                    $filter_params = $_GET;
                                    $filter_params['subject'] = $subject_id;
                                    if (isset($filter_params['page'])) unset($filter_params['page']);
                                    $filter_url = http_build_query($filter_params);
                                ?>
                                    <a href="?<?= htmlspecialchars($filter_url) ?>" class="badge badge-pill badge-light border subject-pill" title="Filter by this subject">
                                        <?= htmlspecialchars($subject_name) ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted">Not specified</span>
                            <?php endif; ?>
                        </p>

                        <!-- Payment methods -->
                        <p class="card-text">
                            <strong>Payment:</strong>
                            <?php if(!empty($tutor['payment_methods'])): ?>
                        <div class="payment-pills">
                            <?php
                            $methods = explode(',', $tutor['payment_methods']);
                            foreach($methods as $method): ?>
                                <span class="badge badge-pill badge-light border"><?= trim($method) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                            <span class="text-muted">Not specified</span>
                        <?php endif; ?>
                        </p>

                        <!-- Grades display -->
                        <p class="card-text">
                            <strong>Grades:</strong>
                            <?php if(!empty($tutor['grades'])): ?>
                        <div class="payment-pills">
                            <?php
                            $grade_list = explode(',', $tutor['grades']);
                            foreach($grade_list as $g): ?>
                                <span class="badge badge-pill badge-light border"><?= trim($g) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <span class="text-muted">Not specified</span>
                    <?php endif; ?>
                        </p>

                    </div>
                    <div class="card-footer">
                        <div class="mt-auto">
                            <a href="tutor-profile/<?= htmlspecialchars($tutor['slug']) ?>" class="btn btn-primary">View Profile</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            if ($counter % 3 === 0):
            ?>

        </div><!-- Close current row -->

        <!-- AdSense Ad -->
        <div class="row my-4">
            <div class="col-12">
                <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-5071836192516328"
                        crossorigin="anonymous"></script>
                <ins class="adsbygoogle"
                     style="display:block"
                     data-ad-client="ca-pub-5071836192516328"
                     data-ad-slot="2355224655"
                     data-ad-format="auto"
                     data-full-width-responsive="true"></ins>
                <script>
                    (adsbygoogle = window.adsbygoogle || []).push({});
                </script>
            </div>
        </div>

        <!-- Open new row for next tutors -->
        <div class="row">
            <?php endif; ?>

            <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center mt-4">
                <?php
                // Build pagination URL that preserves all filter parameters
                $current_params = $_GET;
                
                // Previous page link
                if ($page > 1) {
                    $current_params['page'] = $page - 1;
                    $query_string = http_build_query($current_params);
                    echo '<li class="page-item"><a class="page-link" href="?'.$query_string.'">&laquo; Previous</a></li>';
                }
                
                // Page numbers
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    $current_params['page'] = 1;
                    $query_string = http_build_query($current_params);
                    echo '<li class="page-item"><a class="page-link" href="?'.$query_string.'">1</a></li>';
                    if ($start_page > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $current_params['page'] = $i;
                    $query_string = http_build_query($current_params);
                    $active = ($i == $page) ? 'active' : '';
                    echo '<li class="page-item '.$active.'"><a class="page-link" href="?'.$query_string.'">'.$i.'</a></li>';
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    $current_params['page'] = $total_pages;
                    $query_string = http_build_query($current_params);
                    echo '<li class="page-item"><a class="page-link" href="?'.$query_string.'">'.$total_pages.'</a></li>';
                }
                
                // Next page link
                if ($page < $total_pages) {
                    $current_params['page'] = $page + 1;
                    $query_string = http_build_query($current_params);
                    echo '<li class="page-item"><a class="page-link" href="?'.$query_string.'">Next &raquo;</a></li>';
                }
                ?>
            </ul>
        </nav>
        <?php endif; ?>
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
<script>
    $(document).ready(function() {
        // Handle reset filters button click
        $('.reset-filters').on('click', function() {
            // Reset all form fields
            $('#tutorFilterForm')[0].reset();
            // Submit the form
            $('#tutorFilterForm').submit();
        });
    });
</script>
<script>
$(document).ready(function() {
    // Submit form when any filter changes
    $('#tutorSearchForm select').change(function() {
        $('#tutorSearchForm').submit();
    });
    
    // Handle reset filters
    $('.reset-filters').click(function() {
        // Reset all form fields
        $('#tutorSearchForm')[0].reset();
        // Remove name search from URL
        window.location.href = window.location.pathname;
    });
});
</script>
</body>
</html>