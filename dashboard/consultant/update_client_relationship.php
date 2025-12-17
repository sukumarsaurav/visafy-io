<?php
// Include database connection
ob_start();
require_once 'includes/header.php';

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_relationship'])) {
    $applicant_id = $_POST['applicant_id'];
    $relationship_type = $_POST['relationship_type'];
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $consultant_id = $_SESSION['id'];
    $organization_id = $_SESSION['organization_id'];
    
    // Check if a relationship already exists
    $check_query = "SELECT id FROM applicant_consultant_relationships 
                   WHERE applicant_id = ? AND consultant_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('ii', $applicant_id, $consultant_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update existing relationship
        $relationship_id = $check_result->fetch_assoc()['id'];
        $update_query = "UPDATE applicant_consultant_relationships 
                        SET relationship_type = ?, notes = ?, updated_at = NOW() 
                        WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('ssi', $relationship_type, $notes, $relationship_id);
        
        if ($update_stmt->execute()) {
            $success = true;
        } else {
            $error = "Error updating relationship: " . $conn->error;
        }
        $update_stmt->close();
    } else {
        // Insert new relationship
        $insert_query = "INSERT INTO applicant_consultant_relationships 
                        (applicant_id, consultant_id, relationship_type, notes, organization_id) 
                        VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param('iissi', $applicant_id, $consultant_id, $relationship_type, $notes, $organization_id);
        
        if ($insert_stmt->execute()) {
            $success = true;
        } else {
            $error = "Error creating relationship: " . $conn->error;
        }
        $insert_stmt->close();
    }
    $check_stmt->close();
    
    // Redirect back to clients page
    if (isset($success)) {
        header("Location: clients.php?success=3");
    } else {
        header("Location: clients.php?error=" . urlencode($error));
    }
    exit;
} else {
    // If not POST request, redirect to clients page
    header("Location: clients.php");
    exit;
}
?> 