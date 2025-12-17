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

$user_id = $_SESSION['id'];

// Call the stored procedure to mark all notifications as read
$stmt = $conn->prepare("CALL mark_all_notifications_read(?)");
$stmt->bind_param("i", $user_id);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
?>
