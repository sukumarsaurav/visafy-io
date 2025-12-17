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

    if (!isset($_GET['country_id'])) {
        throw new Exception('Country ID is required');
    }

    $country_id = (int)$_GET['country_id'];
    
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

    // Query to get visa types
    $query = "SELECT v.visa_id, v.visa_type, v.description, v.validity_period, v.fee, v.requirements,
                     v.is_global, v.organization_id
              FROM visas v
              WHERE v.country_id = ? 
              AND v.is_active = 1 
              AND (
                  v.is_global = 1 
                  OR v.organization_id = ?
              )
              ORDER BY v.visa_type";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $country_id, $organization_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $result = $stmt->get_result();
    $visa_types = [];
    
    while ($row = $result->fetch_assoc()) {
        $row['is_organization_specific'] = !$row['is_global'];
        $visa_types[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $visa_types
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
