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

    // Get document types grouped by category
    $query = "SELECT dt.id, dt.name, dt.description, dc.name as category_name
              FROM document_types dt
              JOIN document_categories dc ON dt.category_id = dc.id
              WHERE (dt.is_global = 1 OR dt.organization_id = ?)
              AND dt.is_active = 1
              ORDER BY dc.name, dt.name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $organization_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $result = $stmt->get_result();
    
    // Group documents by category
    $documents_by_category = [];
    while ($row = $result->fetch_assoc()) {
        $category = $row['category_name'];
        if (!isset($documents_by_category[$category])) {
            $documents_by_category[$category] = [];
        }
        $documents_by_category[$category][] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $documents_by_category
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
