<?php
include 'db.php';

// Helper to sanitize inputs
function clean($data) {
    return htmlspecialchars(trim($data));
}

// Get & sanitize inputs
$full_name = clean($_POST['full_name']);
$email = clean($_POST['email']);
$phone = clean($_POST['phone_number']);
$location_id = intval($_POST['location_id']);
$teaching_method = $_POST['teaching_method'];
$rate_per_hour = floatval($_POST['rate_per_hour']);
$biography = clean($_POST['biography']);
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

// Checkbox inputs
$subjects = isset($_POST['subjects']) ? $_POST['subjects'] : [];
$grades = isset($_POST['grades']) ? $_POST['grades'] : [];
$platforms = isset($_POST['platforms']) ? $_POST['platforms'] : [];
$payment_methods = isset($_POST['payment_methods']) ? $_POST['payment_methods'] : [];

// Check for required fields
if (empty($full_name) || empty($email) || empty($password) || empty($subjects) || empty($grades)) {
    die("Missing required fields. Please go back and complete all required fields.");
}

// Check if email already exists
$check = $conn->prepare("SELECT id FROM tutors WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    die("Email already registered.");
}
$check->close();

// Insert into tutors table
$stmt = $conn->prepare("INSERT INTO tutors (full_name, email, phone_number, location_id, teaching_method, rate_per_hour, biography, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssisdss", $full_name, $email, $phone, $location_id, $teaching_method, $rate_per_hour, $biography, $password);

if ($stmt->execute()) {
    $tutor_id = $stmt->insert_id;

    // Insert subjects
    $sub_stmt = $conn->prepare("INSERT INTO tutor_subjects (tutor_id, subject_id) VALUES (?, ?)");
    foreach ($subjects as $sub_id) {
        $sub_stmt->bind_param("ii", $tutor_id, $sub_id);
        $sub_stmt->execute();
    }

    // Insert grades
    $grade_stmt = $conn->prepare("INSERT INTO tutor_grades (tutor_id, grade_id) VALUES (?, ?)");
    foreach ($grades as $grade_id) {
        $grade_stmt->bind_param("ii", $tutor_id, $grade_id);
        $grade_stmt->execute();
    }

    // Insert platforms (if applicable)
    if ($teaching_method == 'online' || $teaching_method == 'both') {
        $plat_stmt = $conn->prepare("INSERT INTO tutor_platforms (tutor_id, platform_id) VALUES (?, ?)");
        foreach ($platforms as $plat_id) {
            $plat_stmt->bind_param("ii", $tutor_id, $plat_id);
            $plat_stmt->execute();
        }
    }

    // Insert payment methods
    $pay_stmt = $conn->prepare("INSERT INTO tutor_payment_methods (tutor_id, payment_method_id) VALUES (?, ?)");
    foreach ($payment_methods as $method_id) {
        $pay_stmt->bind_param("ii", $tutor_id, $method_id);
        $pay_stmt->execute();
    }

    echo "Registration successful! You can now <a href='login.php'>log in</a>.";
} else {
    echo "Something went wrong. Please try again.";
}

$stmt->close();
$conn->close();
?>
