<?php
require_once '../../../config/db_connect.php';
session_start();
header('Content-Type: application/json');

// Debug all incoming data
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Raw POST data: " . file_get_contents('php://input'));
error_log("GET parameters: " . print_r($_GET, true));
error_log("POST parameters: " . print_r($_POST, true));
error_log("REQUEST parameters: " . print_r($_REQUEST, true));

try {
    // Get user_id from session
    $user_id = $_SESSION['id'] ?? null;
    $user_type = $_SESSION['user_type'] ?? null;

    // Debug session data
    error_log("User ID: " . $user_id);
    error_log("User Type: " . $user_type);
    error_log("Session data: " . print_r($_SESSION, true));

    // Verify user is authenticated
    if (!$user_id || !$user_type) {
        throw new Exception('Unauthorized access');
    }

    // Check for document_type_id in all possible places
    $document_type_id = null;
    if (isset($_GET['document_type_id'])) {
        $document_type_id = $_GET['document_type_id'];
        error_log("Found document_type_id in GET: " . $document_type_id);
    } elseif (isset($_POST['document_type_id'])) {
        $document_type_id = $_POST['document_type_id'];
        error_log("Found document_type_id in POST: " . $document_type_id);
    } elseif (isset($_REQUEST['document_type_id'])) {
        $document_type_id = $_REQUEST['document_type_id'];
        error_log("Found document_type_id in REQUEST: " . $document_type_id);
    }

    if (!$document_type_id) {
        error_log("No document_type_id found in any request method");
        throw new Exception('Document Type ID is required. Please select a document type.');
    }

    if (empty($document_type_id)) {
        error_log("document_type_id is empty");
        throw new Exception('Document Type ID cannot be empty. Please select a valid document type.');
    }

    $document_type_id = (int)$document_type_id;
    if ($document_type_id <= 0) {
        error_log("Invalid document_type_id: " . $document_type_id);
        throw new Exception('Invalid Document Type ID. Please select a valid document type.');
    }
    
    error_log("Processing document_type_id: " . $document_type_id);
    
    // Get organization_id from users table
    $org_query = "SELECT organization_id FROM users WHERE id = ?";
    $org_stmt = $conn->prepare($org_query);
    $org_stmt->bind_param('i', $user_id);
    $org_stmt->execute();
    $org_result = $org_stmt->get_result();
    $org_data = $org_result->fetch_assoc();
    $organization_id = $org_data['organization_id'] ?? null;

    error_log("Organization ID: " . $organization_id);

    if (!$organization_id) {
        throw new Exception('Organization ID not found');
    }

    // First, let's check if the document type exists
    $check_doc_type = "SELECT id, name FROM document_types WHERE id = ?";
    $check_stmt = $conn->prepare($check_doc_type);
    $check_stmt->bind_param('i', $document_type_id);
    $check_stmt->execute();
    $doc_type_result = $check_stmt->get_result();
    $doc_type = $doc_type_result->fetch_assoc();
    
    error_log("Document Type Check: " . print_r($doc_type, true));

    if (!$doc_type) {
        throw new Exception('Selected document type does not exist');
    }

    // Query to get templates with debugging
    $query = "SELECT dt.id, dt.name, dt.content, dt.is_active,
                     CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                     dt.created_at,
                     dt.organization_id,
                     dt.document_type_id
              FROM document_templates dt
              JOIN users u ON dt.created_by = u.id
              WHERE dt.document_type_id = ? 
              AND dt.is_active = 1 
              AND dt.organization_id = ?
              ORDER BY dt.name";
              
    error_log("Query: " . $query);
    error_log("Parameters: document_type_id=" . $document_type_id . ", organization_id=" . $organization_id);
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $document_type_id, $organization_id);
    
    if (!$stmt->execute()) {
        error_log("Query execution failed: " . $stmt->error);
        throw new Exception('Database error: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $templates = [];
    
    // Debug the number of rows found
    error_log("Number of templates found: " . $result->num_rows);
    
    while ($row = $result->fetch_assoc()) {
        // Format the created date
        $row['created_at'] = date('M d, Y', strtotime($row['created_at']));
        $templates[] = $row;
        
        // Debug each template
        error_log("Template found: " . print_r($row, true));
    }

    // Let's also check if there are any templates at all for this document type
    $check_templates = "SELECT COUNT(*) as total FROM document_templates WHERE document_type_id = ?";
    $check_templates_stmt = $conn->prepare($check_templates);
    $check_templates_stmt->bind_param('i', $document_type_id);
    $check_templates_stmt->execute();
    $total_templates = $check_templates_stmt->get_result()->fetch_assoc()['total'];
    
    error_log("Total templates for document type: " . $total_templates);

    echo json_encode([
        'success' => true,
        'data' => $templates,
        'debug' => [
            'document_type_id' => $document_type_id,
            'organization_id' => $organization_id,
            'total_templates' => $total_templates,
            'document_type_exists' => !empty($doc_type),
            'document_type_name' => $doc_type['name'] ?? 'Not found',
            'get_parameters' => $_GET,
            'post_parameters' => $_POST,
            'request_parameters' => $_REQUEST,
            'request_method' => $_SERVER['REQUEST_METHOD']
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in get_templates.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'error_trace' => $e->getTraceAsString(),
            'get_parameters' => $_GET,
            'post_parameters' => $_POST,
            'request_parameters' => $_REQUEST,
            'request_method' => $_SERVER['REQUEST_METHOD']
        ]
    ]);
}
