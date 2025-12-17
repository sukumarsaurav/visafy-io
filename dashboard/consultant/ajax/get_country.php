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

    // Check if country ID is provided
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid country ID');
    }

    $country_id = (int)$_GET['id'];

    // Get country details
    $query = "SELECT * FROM countries 
              WHERE country_id = ? AND (organization_id = ? OR is_global = 1)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param('ii', $country_id, $organization_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $result = $stmt->get_result();
    $country = $result->fetch_assoc();

    if (!$country) {
        throw new Exception('Country not found or access denied');
    }

    echo json_encode([
        'success' => true,
        'country' => $country
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 