<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
include 'config.php';
include 'functions.php';

// Check if form has been submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Debug: View all submitted data
    echo "<pre>POST data: ";
    print_r($_POST);
    echo "</pre>";

    // Initialize variables with proper validation
    // Changed to FILTER_SANITIZE_FULL_SPECIAL_CHARS for better compatibility
    $fullName = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
    $slug = generateUniqueSlug($conn, $fullName);

    // Basic form validation
    $errors = [];

    // Validate required fields
    $required_fields = [
        'full_name' => $fullName,
        'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
        'phone_number' => filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
        'location_id' => filter_input(INPUT_POST, 'location_id', FILTER_VALIDATE_INT),
        'teaching_method' => filter_input(INPUT_POST, 'teaching_method', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
        'rate_per_hour' => filter_input(INPUT_POST, 'rate_per_hour', FILTER_VALIDATE_FLOAT),
        'biography' => filter_input(INPUT_POST, 'biography', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
        'password' => $_POST['password'] ?? null, // Don't sanitize passwords
        'exam_body' => filter_input(INPUT_POST, 'exam_body', FILTER_SANITIZE_FULL_SPECIAL_CHARS)
    ];

    // IMPORTANT: Limit the exam_body to prevent "Data too long" error
    // Check the actual length allowed in your database and adjust accordingly
    if (strlen($required_fields['exam_body']) > 20) { // Assuming 20 is the max length
        $required_fields['exam_body'] = substr($required_fields['exam_body'], 0, 20);
    }

    foreach ($required_fields as $field => $value) {
        if (empty($value) && $value !== '0') { // Consider '0' as valid input
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
        }
    }

    // Validate email format
    if (!empty($required_fields['email']) && !filter_var($required_fields['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // Check if email already exists
    if (!empty($required_fields['email'])) {
        $email = $required_fields['email'];
        $check_email = $conn->prepare("SELECT id FROM tutors WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $result = $check_email->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Email is already registered";
        }
        $check_email->close();
    }

    // Fixed: Validate subjects with better error handling
    if (!isset($_POST['subjects']) || !is_array($_POST['subjects']) || empty($_POST['subjects'])) {
        $errors[] = "At least one subject must be selected";
    }

    // Fixed: Validate grades with better error handling
    if (!isset($_POST['grades']) || !is_array($_POST['grades']) || empty($_POST['grades'])) {
        $errors[] = "At least one grade level must be selected";
    }

    // Fixed: Validate online platforms if online/both teaching method selected
    if (in_array($required_fields['teaching_method'], ['online', 'both'])) {
        if (!isset($_POST['platforms']) || !is_array($_POST['platforms']) || empty($_POST['platforms'])) {
            $errors[] = "At least one online platform must be selected for online teaching";
        }
    }

    // Fixed: Validate payment methods with better error handling
    if (!isset($_POST['payment_methods']) || !is_array($_POST['payment_methods']) || empty($_POST['payment_methods'])) {
        $errors[] = "At least one payment method must be selected";
    }

    // Debug: View validation errors
    if (!empty($errors)) {
        echo "<pre>Validation errors: ";
        print_r($errors);
        echo "</pre>";
    }

    // If no errors, process registration
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();

        try {
            // Hash password for security
            $hashed_password = password_hash($required_fields['password'], PASSWORD_DEFAULT);

            // Insert into tutors table
            $stmt = $conn->prepare("INSERT INTO tutors (full_name, email, phone_number, location_id, teaching_method, rate_per_hour, biography, password, created_at, slug, exam_body) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");

            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $bind_result = $stmt->bind_param("sssisdssss",
                $required_fields['full_name'],
                $required_fields['email'],
                $required_fields['phone_number'],
                $required_fields['location_id'],
                $required_fields['teaching_method'],
                $required_fields['rate_per_hour'],
                $required_fields['biography'],
                $hashed_password,
                $slug,
                $required_fields['exam_body']
            );

            if (!$bind_result) {
                throw new Exception("Bind failed: " . $stmt->error);
            }

            $execute_result = $stmt->execute();

            if (!$execute_result) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $tutor_id = $conn->insert_id;
            $stmt->close();

            // Insert subject relationships with proper error handling
            if (isset($_POST['subjects']) && is_array($_POST['subjects']) && !empty($_POST['subjects'])) {
                $subject_stmt = $conn->prepare("INSERT INTO tutor_subjects (tutor_id, subject_id) VALUES (?, ?)");
                if (!$subject_stmt) {
                    throw new Exception("Failed to prepare subject statement: " . $conn->error);
                }

                foreach ($_POST['subjects'] as $subject_id) {
                    $subject_id = filter_var($subject_id, FILTER_VALIDATE_INT);
                    if ($subject_id === false) continue;

                    $subject_stmt->bind_param("ii", $tutor_id, $subject_id);
                    if (!$subject_stmt->execute()) {
                        throw new Exception("Failed to insert subject: " . $subject_stmt->error);
                    }
                }
                $subject_stmt->close();
            }

            // Insert grade level relationships with proper error handling
            if (isset($_POST['grades']) && is_array($_POST['grades']) && !empty($_POST['grades'])) {
                $grade_stmt = $conn->prepare("INSERT INTO tutor_grades (tutor_id, grade_id) VALUES (?, ?)");
                if (!$grade_stmt) {
                    throw new Exception("Failed to prepare grade statement: " . $conn->error);
                }

                foreach ($_POST['grades'] as $grade_id) {
                    $grade_id = filter_var($grade_id, FILTER_VALIDATE_INT);
                    if ($grade_id === false) continue;

                    $grade_stmt->bind_param("ii", $tutor_id, $grade_id);
                    if (!$grade_stmt->execute()) {
                        throw new Exception("Failed to insert grade: " . $grade_stmt->error);
                    }
                }
                $grade_stmt->close();
            }

            // Insert platform relationships if applicable with proper error handling
            if (in_array($required_fields['teaching_method'], ['online', 'both']) &&
                isset($_POST['platforms']) && is_array($_POST['platforms']) && !empty($_POST['platforms'])) {
                $platform_stmt = $conn->prepare("INSERT INTO tutor_platforms (tutor_id, platform_id) VALUES (?, ?)");
                if (!$platform_stmt) {
                    throw new Exception("Failed to prepare platform statement: " . $conn->error);
                }

                foreach ($_POST['platforms'] as $platform_id) {
                    $platform_id = filter_var($platform_id, FILTER_VALIDATE_INT);
                    if ($platform_id === false) continue;

                    $platform_stmt->bind_param("ii", $tutor_id, $platform_id);
                    if (!$platform_stmt->execute()) {
                        throw new Exception("Failed to insert platform: " . $platform_stmt->error);
                    }
                }
                $platform_stmt->close();
            }

            // Insert payment method relationships with proper error handling
            if (isset($_POST['payment_methods']) && is_array($_POST['payment_methods']) && !empty($_POST['payment_methods'])) {
                $payment_stmt = $conn->prepare("INSERT INTO tutor_payment_methods (tutor_id, payment_method_id) VALUES (?, ?)");
                if (!$payment_stmt) {
                    throw new Exception("Failed to prepare payment method statement: " . $conn->error);
                }

                foreach ($_POST['payment_methods'] as $payment_id) {
                    $payment_id = filter_var($payment_id, FILTER_VALIDATE_INT);
                    if ($payment_id === false) continue;

                    $payment_stmt->bind_param("ii", $tutor_id, $payment_id);
                    if (!$payment_stmt->execute()) {
                        throw new Exception("Failed to insert payment method: " . $payment_stmt->error);
                    }
                }
                $payment_stmt->close();
            }

            // All queries successful, commit transaction
            $conn->commit();

            // Debug: Success message
            echo "Registration successful! Redirecting...";

            // Redirect to success page with success message
            header("Location: tutor-login.php?registration=success");
            exit();

        } catch (Exception $e) {
            // An error occurred, rollback changes
            $conn->rollback();
            $registration_error = "Registration failed: " . $e->getMessage();
            echo "<div class='alert alert-danger'>$registration_error</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Openclass | Register as Tutor</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .registration-card {
            border-radius: 15px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .card-header {
            background-color: #0F1E8A;
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        .section-title {
            color: #0F1E8A;
            font-weight: 600;
            margin: 1.5rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
        }
        .btn-primary {
            background-color: #0F1E8A;
            border-color: #0F1E8A;
            padding: 10px 25px;
            font-weight: 500;
            border-radius: 8px;
        }
        .btn-primary:hover {
            background-color: #0c176b;
            border-color: #0c176b;
        }
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.5rem;
        }
        .form-check-input:checked {
            background-color: #0F1E8A;
            border-color: #0F1E8A;
        }
        .platform-options {
            display: none;
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 0.5rem;
        }
        .other-input {
            display: none;
            margin-top: 10px;
        }
        @media (max-width: 768px) {
            .checkbox-group {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card registration-card">
                <div class="card-header">
                    <h2><i class="fas fa-chalkboard-teacher me-2"></i>Register as a Tutor</h2>
                </div>
                <div class="card-body p-4">
                    <?php if(isset($errors) && !empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h5 class="alert-heading">Please fix these errors:</h5>
                            <ul class="mb-0">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if(isset($registration_error)): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($registration_error); ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                        <!-- Personal Information -->
                        <h4 class="section-title"><i class="fas fa-user me-2"></i>Personal Information</h4>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required
                                       value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="phone_number" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone_number" name="phone_number" required
                                       value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="location_id" class="form-label">Location (City)</label>
                                <select class="form-select" id="location_id" name="location_id" required>
                                    <option value="">Select City</option>
                                    <?php
                                    $res = $conn->query("SELECT * FROM cities ORDER BY name");
                                    while($row = $res->fetch_assoc()) {
                                        $selected = (isset($_POST['location_id']) && $_POST['location_id'] == $row['id']) ? 'selected' : '';
                                        echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <!-- Teaching Information -->
                        <h4 class="section-title"><i class="fas fa-book me-2"></i>Teaching Information</h4>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Exam Body</label>
                                <select class="form-select" name="exam_body" id="exam_body" required onchange="toggleOtherExamBody()">
                                    <option value="">Select Exam Body</option>
                                    <option value="ZIMSEC" <?= (isset($_POST['exam_body']) && $_POST['exam_body'] == 'ZIMSEC') ? 'selected' : '' ?>>ZIMSEC</option>
                                    <option value="Cambridge" <?= (isset($_POST['exam_body']) && $_POST['exam_body'] == 'Cambridge') ? 'selected' : '' ?>>Cambridge</option>
                                    <option value="HEXCO" <?= (isset($_POST['exam_body']) && $_POST['exam_body'] == 'HEXCO') ? 'selected' : '' ?>>HEXCO</option>
                                    <option value="Other" <?= (isset($_POST['exam_body']) && $_POST['exam_body'] == 'Other') ? 'selected' : '' ?>>Other</option>
                                </select>
                                <div id="otherExamBody" class="other-input">
                                    <input type="text" class="form-control" name="other_exam_body" id="other_exam_body"
                                           placeholder="Please specify exam body"
                                           value="<?php echo isset($_POST['other_exam_body']) ? htmlspecialchars($_POST['other_exam_body']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="rate_per_hour" class="form-label">Rate Per Hour ($)</label>
                                <input type="number" class="form-control" id="rate_per_hour" name="rate_per_hour"
                                       step="0.01" min="1" required
                                       value="<?php echo isset($_POST['rate_per_hour']) ? htmlspecialchars($_POST['rate_per_hour']) : ''; ?>">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Subjects</label>
                            <div class="checkbox-group">
                                <?php
                                $subjects = $conn->query("SELECT * FROM subjects ORDER BY name");
                                while($sub = $subjects->fetch_assoc()) {
                                    $checked = (isset($_POST['subjects']) && is_array($_POST['subjects']) && in_array($sub['id'], $_POST['subjects'])) ? 'checked' : '';
                                    echo '<div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="subjects[]" value="'.$sub['id'].'" id="subject_'.$sub['id'].'" '.$checked.'>
                                                <label class="form-check-label" for="subject_'.$sub['id'].'">'.$sub['name'].'</label>
                                            </div>';
                                }
                                ?>
                            </div>
                            <small class="text-danger">Please select at least one subject</small>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Grade Levels</label>
                            <div class="checkbox-group">
                                <?php
                                $grades = $conn->query("SELECT * FROM grades ORDER BY level_name");
                                while($grade = $grades->fetch_assoc()) {
                                    $checked = (isset($_POST['grades']) && is_array($_POST['grades']) && in_array($grade['id'], $_POST['grades'])) ? 'checked' : '';
                                    echo '<div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="grades[]" value="'.$grade['id'].'" id="grade_'.$grade['id'].'" '.$checked.'>
                                                <label class="form-check-label" for="grade_'.$grade['id'].'">'.$grade['level_name'].'</label>
                                            </div>';
                                }
                                ?>
                            </div>
                            <small class="text-danger">Please select at least one grade level</small>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Teaching Method</label>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="teaching_method" id="online"
                                           value="online" onchange="togglePlatformOptions()"
                                        <?= (isset($_POST['teaching_method']) && $_POST['teaching_method'] == 'online') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="online">Online</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="teaching_method" id="in_person"
                                           value="in_person" onchange="togglePlatformOptions()"
                                        <?= (isset($_POST['teaching_method']) && $_POST['teaching_method'] == 'in_person') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="in_person">In Person</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="teaching_method" id="both"
                                           value="both" onchange="togglePlatformOptions()"
                                        <?= (isset($_POST['teaching_method']) && $_POST['teaching_method'] == 'both') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="both">Both</label>
                                </div>
                            </div>
                        </div>

                        <div id="platformsSection" class="mt-3 platform-options">
                            <label class="form-label">Preferred Online Platforms</label>
                            <div class="checkbox-group">
                                <?php
                                $platforms = $conn->query("SELECT * FROM platforms ORDER BY name");
                                while($plat = $platforms->fetch_assoc()) {
                                    $checked = (isset($_POST['platforms']) && is_array($_POST['platforms']) && in_array($plat['id'], $_POST['platforms'])) ? 'checked' : '';
                                    echo '<div class="form-check">
                                                <input class="form-check-input platform-checkbox" type="checkbox" name="platforms[]" value="'.$plat['id'].'" id="platform_'.$plat['id'].'" '.$checked.'>
                                                <label class="form-check-label" for="platform_'.$plat['id'].'">'.$plat['name'].'</label>
                                            </div>';
                                }
                                ?>
                            </div>
                            <small class="text-danger platform-message" style="display:none;">Please select at least one platform for online teaching</small>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Payment Methods</label>
                            <div class="checkbox-group">
                                <?php
                                $payments = $conn->query("SELECT * FROM payment_methods ORDER BY method_name");
                                while($pm = $payments->fetch_assoc()) {
                                    $checked = (isset($_POST['payment_methods']) && is_array($_POST['payment_methods']) && in_array($pm['id'], $_POST['payment_methods'])) ? 'checked' : '';
                                    echo '<div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="payment_methods[]" value="'.$pm['id'].'" id="payment_'.$pm['id'].'" '.$checked.'>
                                                <label class="form-check-label" for="payment_'.$pm['id'].'">'.$pm['method_name'].'</label>
                                            </div>';
                                }
                                ?>
                            </div>
                            <small class="text-danger">Please select at least one payment method</small>
                        </div>

                        <!-- Account Information -->
                        <h4 class="section-title"><i class="fas fa-lock me-2"></i>Account Information</h4>
                        <div class="mb-3">
                            <label for="biography" class="form-label">Biography</label>
                            <textarea class="form-control" id="biography" name="biography" rows="5" required><?php echo isset($_POST['biography']) ? htmlspecialchars($_POST['biography']) : ''; ?></textarea>
                            <div class="form-text">Tell students about your qualifications, teaching style, and experience.</div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="form-text">Minimum 8 characters with letters, numbers, and symbols.</div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus me-2"></i>Register as Tutor
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Function to toggle platform options based on teaching method
    function togglePlatformOptions() {
        const method = document.querySelector('input[name="teaching_method"]:checked');
        const platformsSection = document.getElementById('platformsSection');
        const platformMessage = document.querySelector('.platform-message');

        if (method && (method.value === 'online' || method.value === 'both')) {
            platformsSection.style.display = 'block';
            platformMessage.style.display = 'block';

            // Don't set required on checkboxes as it doesn't work as expected
            // Instead we'll validate on form submission
        } else {
            platformsSection.style.display = 'none';
            platformMessage.style.display = 'none';

            // Clear any platform selections when not needed
            if (method && method.value === 'in_person') {
                document.querySelectorAll('.platform-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                });
            }
        }
    }

    // Function to toggle "Other" exam body input field
    function toggleOtherExamBody() {
        const examBodySelect = document.getElementById('exam_body');
        const otherExamBodyDiv = document.getElementById('otherExamBody');
        const otherExamBodyInput = document.getElementById('other_exam_body');

        if (examBodySelect.value === 'Other') {
            otherExamBodyDiv.style.display = 'block';
            otherExamBodyInput.required = true;

            // When submitting with "Other" selected, use the text input value
            document.querySelector('form').addEventListener('submit', function(e) {
                if (examBodySelect.value === 'Other' && otherExamBodyInput.value.trim() !== '') {
                    // Create a hidden input to store the custom exam body value
                    let hiddenExamBody = document.createElement('input');
                    hiddenExamBody.type = 'hidden';
                    hiddenExamBody.name = 'exam_body';
                    hiddenExamBody.value = otherExamBodyInput.value.trim().substring(0, 20); // Limit to 20 chars
                    this.appendChild(hiddenExamBody);

                    // Disable the select to prevent its value from being submitted
                    examBodySelect.disabled = true;
                }
            });
        } else {
            otherExamBodyDiv.style.display = 'none';
            otherExamBodyInput.required = false;
        }
    }

    // Custom form validation before submission
    document.querySelector('form').addEventListener('submit', function(e) {
        let hasErrors = false;

        // Check subjects
        const subjectsChecked = document.querySelectorAll('input[name="subjects[]"]:checked').length > 0;
        if (!subjectsChecked) {
            hasErrors = true;
        }

        // Check grades
        const gradesChecked = document.querySelectorAll('input[name="grades[]"]:checked').length > 0;
        if (!gradesChecked) {
            hasErrors = true;
        }

        // Check payment methods
        const paymentsChecked = document.querySelectorAll('input[name="payment_methods[]"]:checked').length > 0;
        if (!paymentsChecked) {
            hasErrors = true;
        }

        // Check platforms if online or both is selected
        const teachingMethod = document.querySelector('input[name="teaching_method"]:checked');
        if (teachingMethod && (teachingMethod.value === 'online' || teachingMethod.value === 'both')) {
            const platformsChecked = document.querySelectorAll('.platform-checkbox:checked').length > 0;
            if (!platformsChecked) {
                hasErrors = true;
            }
        }

        // If any validation fails, prevent form submission
        if (hasErrors) {
            e.preventDefault();
            alert('Please fill all required fields including checkboxes for subjects, grades, platforms (if online teaching), and payment methods.');
        }
    });

    // Initialize options on page load
    document.addEventListener('DOMContentLoaded', function() {
        togglePlatformOptions();
        toggleOtherExamBody();
    });
</script>
</body>
</html>