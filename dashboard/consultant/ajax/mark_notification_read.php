<?php
session_start();
require_once '../../../config/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Get notification ID from POST
$notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
$user_id = $_SESSION['id'];

if ($notification_id > 0) {
    // Call the stored procedure to mark notification as read
    $stmt = $conn->prepare("CALL mark_notification_read(?, ?)");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $success = $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => $success]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
}
?>
