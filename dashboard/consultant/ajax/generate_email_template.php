<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../../../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$prompt = isset($data['prompt']) ? trim($data['prompt']) : '';

if (empty($prompt)) {
    echo json_encode(['success' => false, 'error' => 'Prompt is required']);
    exit;
}

// Generate template
$template = '
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="text-align: center; margin-bottom: 30px;">
        <img src="https://visafy.io/logo.png" alt="Visafy Logo" style="max-width: 200px;">
    </div>
    
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
        <h2 style="color: #042167; margin-top: 0;">{subject}</h2>
        <p>Dear {first_name},</p>
        <p>This is a sample email template generated based on your requirements. You can customize this template further using the editor.</p>
        <p>Best regards,<br>Visafy Team</p>
    </div>
    
    <div style="text-align: center; color: #6c757d; font-size: 12px; margin-top: 30px;">
        <p>This email was sent to {email}</p>
        <p>&copy; ' . date('Y') . ' Visafy. All rights reserved.</p>
    </div>
</div>';

echo json_encode(['success' => true, 'template' => $template]); 