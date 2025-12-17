<?php
// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../../../logs/php_errors.log');
ini_set('max_execution_time', 30); // Set reasonable timeout

// Set content type to JSON
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Check if required files exist
    $required_files = [
        '../../../config/db_connect.php' => 'Database connection file',
        '../../../config/email_config.php' => 'Email configuration file'
    ];

    foreach ($required_files as $file => $description) {
        if (!file_exists($file)) {
            error_log("Required file missing: $description ($file)");
            throw new Exception("Required file missing: $description ($file)");
        }
    }

    // Include required files
    require_once '../../../config/db_connect.php';
    require_once '../../../config/email_config.php';

    // Check if user is logged in
    if (!isset($_SESSION['id'])) {
        error_log("User not authenticated - Session ID not set");
        throw new Exception('Not authenticated');
    }

    // Get organization ID
    if (isset($_SESSION['organization_id'])) {
        $organization_id = $_SESSION['organization_id'];
        error_log("Organization ID from session: " . $organization_id);
    } else {
        // If not in session, get it from the database
        $user_id = $_SESSION['id'];
        error_log("Getting organization ID from database for user_id: " . $user_id);
        
        $query = "SELECT organization_id FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Database prepare error: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            $organization_id = $user_data['organization_id'];
            $_SESSION['organization_id'] = $organization_id;
            error_log("Organization ID retrieved from database: " . $organization_id);
        } else {
            error_log("Organization ID not found in database for user_id: " . $user_id);
            throw new Exception('Organization ID not set');
        }
    }

    // Get template ID from request
    error_log("GET request data: " . print_r($_GET, true));
    $template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    error_log("Template ID from request: " . $template_id);

    if (!$template_id) {
        error_log("Invalid template ID: " . $template_id);
        throw new Exception('Template ID is required');
    }

    // Get template details with organization check
    $query = "SELECT et.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name 
              FROM email_templates et
              JOIN users u ON et.created_by = u.id
              WHERE et.id = ? AND (et.organization_id = ? OR et.is_global = 1)";
    
    error_log("Executing query: " . $query);
    error_log("Query parameters - template_id: " . $template_id . ", organization_id: " . $organization_id);
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Database prepare error: " . $conn->error);
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param('ii', $template_id, $organization_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $template = $result->fetch_assoc();

    error_log("Query result: " . print_r($template, true));

    if (!$template) {
        error_log("Template not found or access denied for template_id: " . $template_id);
        throw new Exception('Template not found or access denied');
    }

    // Return template data
    echo json_encode([
        'success' => true,
        'template' => $template
    ]);

} catch (Exception $e) {
    error_log("Error in get_email_template.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}