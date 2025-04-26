<?php
// get_tutor_notification.php - Handles AJAX requests for tutor notification details
require_once 'config.php';
session_start();

// Check if tutor is logged in
if (!isset($_SESSION['tutor_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$tutor_id = $_SESSION['tutor_id'];

// Check if notification ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    exit;
}

$notification_id = $_GET['id'];

// Get notification details and check if it applies to this tutor
$notification = null;

// Check all possibilities (all tutors, active tutors, specific tutors)
$sql = "SELECT n.*, a.username as admin_name, nrs.read_at IS NOT NULL as is_read
        FROM notifications n
        JOIN admin_users a ON n.created_by = a.id
        LEFT JOIN notification_read_status nrs ON n.id = nrs.notification_id AND nrs.tutor_id = ?
        WHERE n.id = ? AND (
            n.target_type = 'all' 
            OR (n.target_type = 'active' AND EXISTS (SELECT 1 FROM tutors WHERE id = ? AND account_status = 'active'))
            OR (n.target_type = 'specific' AND EXISTS (SELECT 1 FROM notification_recipients WHERE notification_id = ? AND tutor_id = ?))
        )";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiii", $tutor_id, $notification_id, $tutor_id, $notification_id, $tutor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Notification not found or not applicable']);
    exit;
}

$notification = $result->fetch_assoc();

// Format the date
$notification['created_at'] = date('F j, Y H:i', strtotime($notification['created_at']));

// Convert message newlines to HTML breaks for proper display
$notification['message'] = nl2br(htmlspecialchars($notification['message']));

// Send response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'notification' => $notification
]);
?>