<?php
// Set content type to JSON
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Include database connection
    require_once '../../../config/db_connect.php';

    // Check if user is logged in
    if (!isset($_SESSION['id'])) {
        throw new Exception('Not authenticated');
    }

    // Get template ID from POST data
    $template_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if (!$template_id) {
        throw new Exception('Invalid template ID');
    }

    // Delete the template
    $query = "DELETE FROM email_templates WHERE id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param('i', $template_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Template deleted successfully']);
    } else {
        throw new Exception('Failed to delete template: ' . $stmt->error);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 