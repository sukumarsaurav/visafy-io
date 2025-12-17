<?php
// Set content type to JSON
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Include database connection
    require_once '../../../config/db_connect.php';

    // Check if user is logged in
    if (!isset($_SESSION['id'])) {
        throw new Exception('Not authenticated');
    }

    // Get organization ID
    if (isset($_SESSION['organization_id'])) {
        $organization_id = $_SESSION['organization_id'];
    } else {
        // If not in session, get it from the database
        $user_id = $_SESSION['id'];
        $query = "SELECT organization_id FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            $organization_id = $user_data['organization_id'];
            $_SESSION['organization_id'] = $organization_id;
        } else {
            throw new Exception('Organization ID not set');
        }
    }

    // Get template ID from POST data
    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

    if (!$template_id) {
        throw new Exception('Template ID is required');
    }

    // Get template details
    $query = "SELECT * FROM email_templates WHERE id = ? AND (organization_id = ? OR is_global = 1)";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param('ii', $template_id, $organization_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $template = $result->fetch_assoc();

    if (!$template) {
        throw new Exception('Template not found or access denied');
    }

    // Return template data
    echo json_encode([
        'success' => true,
        'template' => [
            'id' => $template['id'],
            'name' => $template['name'],
            'subject' => $template['subject'],
            'content' => $template['content'],
            'template_type' => $template['template_type'],
            'created_at' => $template['created_at'],
            'updated_at' => $template['updated_at']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 