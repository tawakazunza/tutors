<?php
include 'config.php';
include 'functions.php';

// Get all tutors
$result = $conn->query("SELECT id, full_name FROM tutors WHERE slug IS NULL");

while ($tutor = $result->fetch_assoc()) {
    $slug = generateUniqueSlug($conn, $tutor['full_name'], $tutor['id']);
    
    $stmt = $conn->prepare("UPDATE tutors SET slug = ? WHERE id = ?");
    $stmt->bind_param("si", $slug, $tutor['id']);
    $stmt->execute();
}

echo "Slugs added successfully!";
?>