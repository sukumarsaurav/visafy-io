<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../../config/db_connect.php';

// Check if user is logged in and is a consultant
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'consultant') {
    header("Location: ../../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if document ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid document ID.";
    header("Location: verification.php");
    exit;
}

$document_id = intval($_GET['id']);

// Verify document belongs to the consultant and is not already verified
$check_query = "SELECT id, document_path, verified 
               FROM consultant_verifications 
               WHERE id = ? AND consultant_id = ?";

$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param('ii', $document_id, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    $_SESSION['error_message'] = "Document not found or you don't have permission to delete it.";
    header("Location: verification.php");
    exit;
}

$document = $check_result->fetch_assoc();

// Check if document is already verified
if ($document['verified']) {
    $_SESSION['error_message'] = "Verified documents cannot be deleted.";
    header("Location: verification.php");
    exit;
}

// Delete the document file
$file_path = "../../" . $document['document_path'];
if (file_exists($file_path)) {
    unlink($file_path);
}

// Delete the database record
$delete_query = "DELETE FROM consultant_verifications WHERE id = ?";
$delete_stmt = $conn->prepare($delete_query);
$delete_stmt->bind_param('i', $document_id);

if ($delete_stmt->execute()) {
    $_SESSION['success_message'] = "Document deleted successfully.";
} else {
    $_SESSION['error_message'] = "Error deleting document: " . $conn->error;
}

// Redirect back to verification page
header("Location: verification.php");
exit;
?> 