<?php
// Include database connection and authentication
require_once '../../../includes/config.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/db.php';

// Check if user is logged in and has consultant role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'consultant') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get organization ID from session
$organization_id = isset($_SESSION['organization_id']) ? $_SESSION['organization_id'] : null;
$consultant_id = $_SESSION['user_id'];

// Check if document ID is provided
if (!isset($_POST['document_id']) || empty($_POST['document_id'])) {
    echo json_encode(['success' => false, 'message' => 'Document ID is required']);
    exit;
}

$document_id = intval($_POST['document_id']);

// Get document information to verify ownership and get file path
$query = "SELECT file_path FROM generated_documents 
          WHERE id = ? AND organization_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $document_id, $organization_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Document not found or you do not have permission to delete it']);
    exit;
}

$document = $result->fetch_assoc();
$file_path = $document['file_path'];

// Begin transaction
$conn->begin_transaction();

try {
    // Delete document record from database
    $delete_query = "DELETE FROM generated_documents WHERE id = ? AND organization_id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param('ii', $document_id, $organization_id);
    $delete_result = $delete_stmt->execute();
    
    if (!$delete_result) {
        throw new Exception("Failed to delete document record");
    }
    
    // Delete the physical file if it exists
    $file_path_on_server = $_SERVER['DOCUMENT_ROOT'] . $file_path;
    if (file_exists($file_path_on_server)) {
        if (!unlink($file_path_on_server)) {
            // Log error but continue with transaction
            error_log("Failed to delete file: $file_path_on_server");
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Document deleted successfully']);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Close database connection
$stmt->close();
$delete_stmt->close();
$conn->close();
?> 