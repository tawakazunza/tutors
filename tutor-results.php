<?php
include 'config.php';

// Filters
$subject = isset($_GET['subject']) ? intval($_GET['subject']) : null;
$grade = isset($_GET['grade']) ? intval($_GET['grade']) : null;
$city = isset($_GET['city']) ? intval($_GET['city']) : null;
$method = isset($_GET['method']) ? $_GET['method'] : null;

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 6;
$offset = ($page - 1) * $limit;

// Query builder
$conditions = [];
$params = [];
$joined_tables = [];

if ($subject) {
    $joined_tables['tutor_subjects'] = true;
    $conditions[] = "ts_filter.subject_id = ?";
    $params[] = $subject;
}
if ($grade) {
    $joined_tables['tutor_grades'] = true;
    $conditions[] = "tg_filter.grade_id = ?";
    $params[] = $grade;
}
if ($city) {
    $conditions[] = "t.location_id = ?";
    $params[] = $city;
}
if ($method) {
    $conditions[] = "t.teaching_method = ?";
    $params[] = $method;
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

$count_query = "
    SELECT COUNT(DISTINCT t.id) as total 
    FROM tutors t 
    $joins 
    $where
";

$stmt = $conn->prepare($query);
if ($params) {
    $types = str_repeat("i", count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!-- Tutors Grid -->
<div class="row">
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
                        <?= htmlspecialchars($tutor['subjects']) ?>
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
    // Break row every 3 tutors
    if ($counter % 3 === 0 && !$result->num_rows <= $counter):
    ?>
</div><div class="row">
    <?php endif; ?>

    <?php endwhile; ?>

    <?php if ($counter === 0): ?>
        <div class="col-12">
            <div class="no-results-card text-center py-5">
                <div class="no-results-icon mb-4">
                    <i class="fas fa-user-graduate fa-4x text-muted"></i>
                </div>
                <h3 class="mb-3">No Tutors Found</h3>
                <p class="text-muted mb-4">We couldn't find any tutors matching your criteria.</p>
                <div class="suggestions">
                    <p class="mb-2">Try these suggestions:</p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check-circle text-primary mr-2"></i> Broaden your search filters</li>
                        <li><i class="fas fa-check-circle text-primary mr-2"></i> Check different subjects or locations</li>
                        <li><i class="fas fa-check-circle text-primary mr-2"></i> Consider online tutoring if you selected in-person only</li>
                    </ul>
                </div>
                <div class="btn-group" role="group">
                <button class="btn btn-primary mt-4 reset-filters">Reset All Filters</button>
                <a href="/tutors/register.php" class="btn btn-outline-success mt-4 reset-filters">Register As Tutor</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Pagination -->
<nav>
    <ul class="pagination justify-content-center">
        <?php
        $count_stmt = $conn->prepare($count_query);
        if ($params) {
            $types = str_repeat("i", count($params));
            $count_stmt->bind_param($types, ...$params);
        }
        $count_stmt->execute();
        $total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
        $total_pages = ceil($total_rows / $limit);

        // Build pagination URL that preserves all filter parameters
        $current_params = $_GET;

        for ($i = 1; $i <= $total_pages; $i++) {
            $active = ($i == $page) ? "active" : "";

            // Create a copy of current parameters
            $page_params = $current_params;

            // Update the page parameter
            $page_params['page'] = $i;

            // Build the query string
            $query_string = http_build_query($page_params);

            echo "<li class='page-item $active'><a class='page-link' href='?{$query_string}'>$i</a></li>";
        }
        ?>
    </ul>
</nav>