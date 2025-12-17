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

    // Check if visa_id is provided
    if (!isset($_GET['visa_id'])) {
        throw new Exception('Visa ID is required');
    }

    $visa_id = (int)$_GET['visa_id'];

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

    // Query to get required documents for the visa
    $query = "SELECT vrd.document_type_id, dt.name as document_name, 
                     vrd.is_mandatory, vrd.notes
              FROM visa_required_documents vrd
              JOIN document_types dt ON vrd.document_type_id = dt.id
              WHERE vrd.visa_id = ? 
              AND (dt.is_global = 1 OR dt.organization_id = ?)
              ORDER BY dt.name";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $visa_id, $organization_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $result = $stmt->get_result();
    $required_documents = [];
    
    while ($row = $result->fetch_assoc()) {
        $required_documents[] = [
            'document_id' => $row['document_type_id'],
            'document_name' => $row['document_name'],
            'is_mandatory' => $row['is_mandatory'],
            'notes' => $row['notes']
        ];
    }

    // Return empty array if no documents found
    echo json_encode([
        'success' => true,
        'data' => $required_documents
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
