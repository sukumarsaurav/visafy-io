<?php
// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../../../logs/php_errors.log');
ini_set('max_execution_time', 60); // Increase timeout to 60 seconds

// Set content type to JSON
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configure PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\SMTP;

try {
    // Check if required files exist
    $required_files = [
        '../../../config/db_connect.php' => 'Database connection file',
        '../../../config/email_config.php' => 'Email configuration file'
    ];

    foreach ($required_files as $file => $description) {
        if (!file_exists($file)) {
            throw new Exception("Required file missing: $description ($file)");
        }
    }

    // Include required files
    require_once '../../../config/db_connect.php';
    require_once '../../../config/email_config.php';

    // Check if PHPMailer is available
    if (!file_exists('../../../vendor/autoload.php')) {
        throw new Exception("Composer autoload file not found. Please run 'composer install' in the project root.");
    }
    
    // Include PHPMailer
    require_once '../../../vendor/autoload.php';
    
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        throw new Exception("PHPMailer class not found. Please make sure phpmailer/phpmailer is installed via Composer.");
    }

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

    // Get POST data
    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
    $email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : '';
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';

    // Validate inputs
    if (!$template_id) {
        throw new Exception('Template ID is required');
    }

    if (!$email) {
        throw new Exception('Email address is required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
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

    // Replace variables in content
    $content = $template['content'];
    $subject = $template['subject'];

    $replacements = [
        '{first_name}' => $first_name,
        '{last_name}' => $last_name,
        '{email}' => $email,
        '{current_date}' => date('Y-m-d'),
        '{company_name}' => 'Visafy'
    ];

    $content = str_replace(array_keys($replacements), array_values($replacements), $content);
    $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);

    // Initialize PHPMailer
    $mail = new PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port = SMTP_PORT;

    // Set shorter timeouts
    $mail->Timeout = 15;
    $mail->SMTPKeepAlive = true;
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    // Recipients
    $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
    $mail->addAddress($email);
    $mail->addReplyTo(EMAIL_REPLY_TO);

    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $content;
    $mail->AltBody = strip_tags($content);

    // Send email
    try {
        $mail->send();

        // Log the email in queue
        $query = "INSERT INTO email_queue (recipient_email, subject, content, status, scheduled_time, created_by, organization_id) 
                  VALUES (?, ?, ?, 'sent', NOW(), ?, ?)";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param('sssii', $email, $subject, $content, $_SESSION['id'], $organization_id);
        $stmt->execute();

        // Return success response
        echo json_encode([
            'success' => true, 
            'message' => 'Test email sent successfully to ' . $email,
            'details' => [
                'template' => $template['name'],
                'recipient' => $email,
                'subject' => $subject
            ]
        ]);
    } catch (PHPMailerException $e) {
        // Log the failed email
        if (isset($conn) && isset($email) && isset($subject) && isset($content)) {
            $query = "INSERT INTO email_queue (recipient_email, subject, content, status, error_message, scheduled_time, created_by, organization_id) 
                      VALUES (?, ?, ?, 'failed', ?, NOW(), ?, ?)";
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $error_message = $e->getMessage();
                $stmt->bind_param('ssssii', $email, $subject, $content, $error_message, $_SESSION['id'], $organization_id);
                $stmt->execute();
            }
        }
        
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to send email: ' . $e->getMessage()
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}