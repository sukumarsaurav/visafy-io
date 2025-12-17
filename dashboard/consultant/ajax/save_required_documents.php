<?php
require_once '../../../config/db_connect.php';
session_start();
header('Content-Type: application/json');

try {
    // Get user_id from session
    $user_id = $_SESSION['id'] ?? null;
    $user_type = $_SESSION['user_type'] ?? null;

    // Verify user is authenticated
    if (!$user_id || !$user_type) {
        throw new Exception('Unauthorized access');
    }

    // Get organization_id from users table
    $org_query = "SELECT organization_id FROM users WHERE id = ?";
    $org_stmt = $conn->prepare($org_query);
    $org_stmt->bind_param('i', $user_id);
    $org_stmt->execute();
    $org_result = $org_stmt->get_result();
    $org_data = $org_result->fetch_assoc();
    $organization_id = $org_data['organization_id'] ?? null;

    if (!$organization_id) {
        throw new Exception('Organization ID not found');
    }

    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['visa_id']) || !isset($input['documents'])) {
        throw new Exception('Missing required parameters');
    }

    $visa_id = intval($input['visa_id']);
    $documents = $input['documents'];

    // Start transaction
    $conn->begin_transaction();
    
    // First, delete existing required documents for this visa
    $delete_query = "DELETE FROM visa_required_documents WHERE visa_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param('i', $visa_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Error deleting existing documents: ' . $conn->error);
    }
    
    // Insert new required documents
    if (!empty($documents)) {
        $insert_query = "INSERT INTO visa_required_documents (visa_id, document_type_id, is_mandatory, notes, organization_id) 
                        VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        
        foreach ($documents as $doc) {
            $document_id = intval($doc['document_id']);
            $is_mandatory = intval($doc['is_mandatory']);
            $notes = $doc['notes'] ?? null;
            
            $stmt->bind_param('iiisi', $visa_id, $document_id, $is_mandatory, $notes, $organization_id);
            if (!$stmt->execute()) {
                throw new Exception('Error inserting document: ' . $conn->error);
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Required documents saved successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
