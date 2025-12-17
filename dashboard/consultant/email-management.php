<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if required files exist
$required_files = [
    '../../config/db_connect.php' => 'Database connection file',
    '../../config/email_config.php' => 'Email configuration file',
    '../../vendor/autoload.php' => 'Composer autoload file'
];

foreach ($required_files as $file => $description) {
    if (!file_exists($file)) {
        die("Required file missing: $description ($file)");
    }
}

// Include required files
require_once '../../config/db_connect.php';
require_once '../../config/email_config.php';
require_once '../../vendor/autoload.php';

// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Ensure user is logged in and has a valid user_id
if (!isset($_SESSION['id']) || empty($_SESSION['id'])) {
    // Redirect to login if no user_id is set
    header("Location: login.php");
    exit;
}

// Assign user_id from session['id'] to be consistent with header.php
$_SESSION['user_id'] = $_SESSION['id'];

$page_title = "Email Management";
require_once 'includes/header.php';

// Get consultant ID and organization ID from session and user data
$consultant_id = isset($_SESSION['id']) ? $_SESSION['id'] : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
$organization_id = isset($user['organization_id']) ? $user['organization_id'] : null;

// Verify organization ID is set
if (!$organization_id) {
    die("Organization ID not set. Please log in again.");
}

// Store organization_id in session for use in AJAX calls
$_SESSION['organization_id'] = $organization_id;

// Get all email templates
$query = "SELECT et.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name 
          FROM email_templates et
          JOIN users u ON et.created_by = u.id
          WHERE et.organization_id = ? OR et.is_global = 1
          ORDER BY et.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $organization_id);
$stmt->execute();
$result = $stmt->get_result();

// Get template types for dropdown
$template_types = [
    'general' => 'General',
    'welcome' => 'Welcome Email',
    'password_reset' => 'Password Reset',
    'booking_confirmation' => 'Booking Confirmation',
    'booking_reminder' => 'Booking Reminder',
    'booking_cancellation' => 'Booking Cancellation',
    'application_status' => 'Application Status Update',
    'document_request' => 'Document Request',
    'document_approval' => 'Document Approval',
    'document_rejection' => 'Document Rejection',
    'marketing' => 'Marketing',
    'newsletter' => 'Newsletter'
];

// Handle form submission to create/update template
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_id = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
    $template_name = isset($_POST['template_name']) ? trim($_POST['template_name']) : '';
    $template_subject = isset($_POST['template_subject']) ? trim($_POST['template_subject']) : '';
    $template_type = isset($_POST['template_type']) ? trim($_POST['template_type']) : '';
    $template_content = isset($_POST['template_content']) ? trim($_POST['template_content']) : '';
    
    // Validate inputs
    $errors = [];
    if (empty($template_name)) {
        $errors[] = "Template name is required";
    }
    if (empty($template_subject)) {
        $errors[] = "Subject is required";
    }
    if (empty($template_content)) {
        $errors[] = "Content is required";
    }
    if (empty($template_type)) {
        $errors[] = "Template type is required";
    }
    
    if (empty($errors)) {
        // Check if we're updating or creating a new template
        if ($template_id > 0) {
            // Verify template belongs to organization
            $check_query = "SELECT id FROM email_templates WHERE id = ? AND (organization_id = ? OR is_global = 1)";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param('ii', $template_id, $organization_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                $error_message = "Template not found or access denied";
            } else {
                // Update existing template
                $stmt = $conn->prepare("UPDATE email_templates SET name = ?, subject = ?, content = ?, template_type = ?, updated_at = NOW() WHERE id = ? AND (organization_id = ? OR is_global = 1)");
                $stmt->bind_param('ssssii', $template_name, $template_subject, $template_content, $template_type, $template_id, $organization_id);
                
                if ($stmt->execute()) {
                    $success_message = "Email template updated successfully";
                } else {
                    $error_message = "Error updating template: " . $conn->error;
                }
            }
        } else {
            // Create new template
            $stmt = $conn->prepare("INSERT INTO email_templates (name, subject, content, template_type, created_by, organization_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param('ssssii', $template_name, $template_subject, $template_content, $template_type, $_SESSION['id'], $organization_id);
            
            if ($stmt->execute()) {
                $success_message = "Email template created successfully";
                // Refresh the page to display the new template
                header("Location: email-management.php?success=created");
                exit;
            } else {
                $error_message = "Error creating template: " . $conn->error;
            }
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Get email queue status for dashboard stats
$queue_stats_query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                      FROM email_queue 
                      WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$queue_stats = $conn->query($queue_stats_query)->fetch_assoc();
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Email Management</h1>
            <p>Create and manage email templates for various system notifications</p>
        </div>
        <div class="header-actions">
            <button id="createTemplateBtn" class="btn primary-btn">
                <i class="fas fa-plus"></i> Create Template
            </button>
        </div>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success']) && $_GET['success'] === 'created'): ?>
        <div class="alert alert-success">Email template created successfully</div>
    <?php endif; ?>
    
    <!-- Stats Dashboard -->
    <div class="stats-dashboard">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="stat-content">
                <h3>Total Templates</h3>
                <p class="stat-value"><?php echo $result->num_rows; ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3>Pending Emails</h3>
                <p class="stat-value"><?php echo $queue_stats['pending'] ?? 0; ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3>Sent (30d)</h3>
                <p class="stat-value"><?php echo $queue_stats['sent'] ?? 0; ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon danger-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <h3>Failed (30d)</h3>
                <p class="stat-value"><?php echo $queue_stats['failed'] ?? 0; ?></p>
            </div>
        </div>
    </div>
    
    <!-- Templates Table -->
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Subject</th>
                    <th>Type</th>
                    <th>Created By</th>
                    <th>Created Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($template = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($template['name']); ?></td>
                            <td><?php echo htmlspecialchars($template['subject']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo htmlspecialchars($template['template_type']); ?>">
                                    <?php echo htmlspecialchars($template_types[$template['template_type']]); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($template['created_by_name']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($template['created_at'])); ?></td>
                            <td class="actions-cell">
                                <button class="btn action-btn preview-btn" data-id="<?php echo $template['id']; ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn action-btn edit-btn" 
                                        data-id="<?php echo $template['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($template['name']); ?>"
                                        data-subject="<?php echo htmlspecialchars($template['subject']); ?>"
                                        data-type="<?php echo htmlspecialchars($template['template_type']); ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn action-btn delete-btn" data-id="<?php echo $template['id']; ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button class="btn action-btn test-btn" data-id="<?php echo $template['id']; ?>">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">No email templates found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Template Editor Modal -->
<div id="templateEditorModal" class="modal">
    <div class="modal-content large-modal">
        <div class="modal-header">
            <h2 id="modalTitle">Create Email Template</h2>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="templateForm" method="POST" action="email-management.php">
                <input type="hidden" id="template_id" name="template_id" value="0">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="template_name">Template Name*</label>
                        <input type="text" id="template_name" name="template_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="template_type">Template Type*</label>
                        <select id="template_type" name="template_type" class="form-control" required>
                            <?php foreach ($template_types as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="template_subject">Email Subject*</label>
                    <input type="text" id="template_subject" name="template_subject" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="template_content">Email Content*</label>
                    <div class="editor-tabs">
                        <button type="button" class="tab-btn active" data-tab="visual">Visual Editor</button>
                        <button type="button" class="tab-btn" data-tab="html">HTML Editor</button>
                    </div>
                    
                    <div class="editor-tools">
                        <button type="button" class="tool-btn" data-command="bold"><i class="fas fa-bold"></i></button>
                        <button type="button" class="tool-btn" data-command="italic"><i class="fas fa-italic"></i></button>
                        <button type="button" class="tool-btn" data-command="underline"><i class="fas fa-underline"></i></button>
                        <button type="button" class="tool-btn" data-command="insertUnorderedList"><i class="fas fa-list-ul"></i></button>
                        <button type="button" class="tool-btn" data-command="insertOrderedList"><i class="fas fa-list-ol"></i></button>
                        <button type="button" class="tool-btn" data-command="createLink"><i class="fas fa-link"></i></button>
                        <button type="button" class="tool-btn" data-command="insertImage"><i class="fas fa-image"></i></button>
                        <div class="color-picker">
                            <input type="color" id="colorPicker" value="#000000">
                            <label for="colorPicker"><i class="fas fa-palette"></i></label>
                        </div>
                        <select class="heading-select">
                            <option value="">Paragraph</option>
                            <option value="h1">Heading 1</option>
                            <option value="h2">Heading 2</option>
                            <option value="h3">Heading 3</option>
                        </select>
                        <button type="button" class="tool-btn" data-command="insertVariable"><i class="fas fa-code"></i> Insert Variable</button>
                    </div>
                    
                    <div class="editor-container">
                        <div class="editor-wrapper">
                            <div id="editor" contenteditable="true" class="editor"></div>
                            <textarea id="html_editor" class="html-editor" style="display:none;"></textarea>
                            <textarea id="template_content" name="template_content" style="display:none;"></textarea>
                        </div>
                        <div class="preview-panel">
                            <h4>Preview</h4>
                            <div id="emailPreview" class="email-preview"></div>
                        </div>
                    </div>
                    
                    <div class="variable-helper">
                        <p><strong>Available Variables:</strong></p>
                        <div class="variable-tags">
                            <span class="variable-tag" data-variable="{first_name}">{first_name}</span>
                            <span class="variable-tag" data-variable="{last_name}">{last_name}</span>
                            <span class="variable-tag" data-variable="{email}">{email}</span>
                            <span class="variable-tag" data-variable="{company_name}">{company_name}</span>
                            <span class="variable-tag" data-variable="{booking_date}">{booking_date}</span>
                            <span class="variable-tag" data-variable="{booking_time}">{booking_time}</span>
                            <span class="variable-tag" data-variable="{booking_reference}">{booking_reference}</span>
                            <span class="variable-tag" data-variable="{service_name}">{service_name}</span>
                            <span class="variable-tag" data-variable="{consultant_name}">{consultant_name}</span>
                            <span class="variable-tag" data-variable="{current_date}">{current_date}</span>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn cancel-btn" id="cancelBtn">Cancel</button>
                    <button type="button" class="btn ai-btn" id="aiGenerateBtn">
                        <i class="fas fa-magic"></i> AI Generate
                    </button>
                    <button type="submit" class="btn submit-btn">Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Email Preview</h2>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <div class="preview-info">
                <p><strong>Template Name:</strong> <span id="previewName"></span></p>
                <p><strong>Subject:</strong> <span id="previewSubject"></span></p>
                <p><strong>Type:</strong> <span id="previewType"></span></p>
            </div>
            <div class="email-preview-container">
                <div id="previewContent" class="email-preview-content"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn secondary-btn" id="closePreviewBtn">Close</button>
        </div>
    </div>
</div>

<!-- Test Email Modal -->
<div id="testEmailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Send Test Email</h2>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="testEmailForm" method="post">
                <input type="hidden" id="test_template_id" name="template_id">
                
                <div class="form-group">
                    <label for="test_email">Recipient Email*</label>
                    <input type="email" id="test_email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group test-variables">
                    <h4>Test Variables</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="test_first_name">First Name</label>
                            <input type="text" id="test_first_name" name="first_name" class="form-control" value="John">
                        </div>
                        
                        <div class="form-group">
                            <label for="test_last_name">Last Name</label>
                            <input type="text" id="test_last_name" name="last_name" class="form-control" value="Doe">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="test_booking_date">Booking Date</label>
                            <input type="date" id="test_booking_date" name="booking_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="test_booking_time">Booking Time</label>
                            <input type="time" id="test_booking_time" name="booking_time" class="form-control" value="10:00">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="test_booking_reference">Booking Reference</label>
                            <input type="text" id="test_booking_reference" name="booking_reference" class="form-control" value="BK123456">
                        </div>
                        
                        <div class="form-group">
                            <label for="test_service_name">Service Name</label>
                            <input type="text" id="test_service_name" name="service_name" class="form-control" value="Visa Consultation">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="test_consultant_name">Consultant Name</label>
                            <input type="text" id="test_consultant_name" name="consultant_name" class="form-control" value="Jane Smith">
                        </div>
                        
                        <div class="form-group">
                            <label for="test_company_name">Company Name</label>
                            <input type="text" id="test_company_name" name="company_name" class="form-control" value="Visafy">
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn cancel-btn" id="cancelTestBtn">Cancel</button>
                    <button type="submit" class="btn submit-btn">Send Test Email</button>
                </div>
            </form>
            <div id="testResult" class="test-result" style="display: none;"></div>
        </div>
    </div>
</div>

<!-- AI Template Generator Modal -->
<div id="aiGeneratorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>AI Email Generator</h2>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <p class="modal-description">Describe the email template you want to create, and our AI assistant will generate it for you.</p>
            
            <div class="form-group">
                <label for="aiPrompt">Describe your email template*</label>
                <textarea id="aiPrompt" class="form-control" rows="4" placeholder="Example: Generate an HTML email template for booking confirmation with logo at top, confirmation details in the middle, and contact information in the footer."></textarea>
            </div>
            
            <div class="form-group">
                <label for="aiSubject">Email Subject*</label>
                <input type="text" id="aiSubject" class="form-control" placeholder="Example: Your booking confirmation">
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn cancel-btn" id="cancelAiBtn">Cancel</button>
                <button type="button" class="btn submit-btn" id="generateBtn">
                    <i class="fas fa-magic"></i> Generate Template
                </button>
            </div>
            
            <div id="aiLoader" class="ai-loader" style="display: none;">
                <div class="spinner"></div>
                <p>Generating your template...</p>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Confirm Delete</h2>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete this email template? This action cannot be undone.</p>
            <input type="hidden" id="delete_template_id">
            
            <div class="form-actions">
                <button type="button" class="btn cancel-btn" id="cancelDeleteBtn">Cancel</button>
                <button type="button" class="btn danger-btn" id="confirmDeleteBtn">Delete Template</button>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --primary-color: #042167;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --danger-color: #e74a3b;
    --warning-color: #f6c23e;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-color: #e3e6f0;
    --modal-bg: #ffffff;
}

.content {
    padding: 20px;
}

.header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.header-container h1 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.8rem;
}

.header-container p {
    margin: 5px 0 0;
    color: var(--secondary-color);
}

.alert {
    padding: 12px 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-success {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
    border: 1px solid rgba(28, 200, 138, 0.2);
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
    border: 1px solid rgba(231, 74, 59, 0.2);
}

.stats-dashboard {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    flex: 1;
    min-width: 200px;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    padding: 15px;
    display: flex;
    align-items: center;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: rgba(4, 33, 103, 0.1);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    margin-right: 15px;
}

.success-icon {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.danger-icon {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.stat-content h3 {
    margin: 0;
    font-size: 0.9rem;
    color: var(--secondary-color);
}

.stat-value {
    font-size: 1.8rem;
    font-weight: bold;
    margin: 5px 0 0;
    color: var(--dark-color);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    background-color: white;
    margin-bottom: 20px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
}

.data-table th, .data-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.data-table th {
    background-color: #f8f9fc;
    color: var(--primary-color);
    font-weight: 500;
}

.data-table tbody tr:hover {
    background-color: #f8f9fc;
}

.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
}

.badge-general { background-color: #e2e3e5; color: #383d41; }
.badge-welcome { background-color: #d4edda; color: #155724; }
.badge-password_reset { background-color: #cce5ff; color: #004085; }
.badge-booking_confirmation { background-color: #d1ecf1; color: #0c5460; }
.badge-document_request { background-color: #fff3cd; color: #856404; }
.badge-marketing { background-color: #f8d7da; color: #721c24; }
.badge-newsletter { background-color: #e2e3e5; color: #383d41; }

.actions-cell {
    white-space: nowrap;
}

.action-btn {
    background: none;
    border: none;
    color: var(--primary-color);
    cursor: pointer;
    padding: 5px;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.action-btn:hover {
    color: var(--primary-color);
    background-color: rgba(4, 33, 103, 0.1);
    border-radius: 4px;
}

.delete-btn {
    color: var(--danger-color);
}

.delete-btn:hover {
    color: var(--danger-color);
    background-color: rgba(231, 74, 59, 0.1);
}

.test-btn {
    color: var(--success-color);
}

.test-btn:hover {
    color: var(--success-color);
    background-color: rgba(28, 200, 138, 0.1);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background-color: var(--modal-bg);
    margin: 50px auto;
    padding: 0;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    width: 80%;
    max-width: 600px;
    animation: modalopen 0.3s;
}

.large-modal {
    max-width: 90%;
    width: 95%;
}

.modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.3rem;
    color: var(--primary-color);
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid var(--border-color);
    text-align: right;
}

.close {
    color: var(--secondary-color);
    float: right;
    font-size: 1.5rem;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: var(--primary-color);
    text-decoration: none;
}

@keyframes modalopen {
    from {opacity: 0; transform: translateY(-20px);}
    to {opacity: 1; transform: translateY(0);}
}

/* Form Styles */
.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.form-row .form-group {
    flex: 1;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--dark-color);
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 1rem;
    transition: border-color 0.15s ease-in-out;
}

.form-control:focus {
    border-color: var(--primary-color);
    outline: none;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.btn {
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 0.9rem;
    cursor: pointer;
    border: none;
    font-weight: 500;
    transition: all 0.2s;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
}

.primary-btn:hover {
    background-color: #031a54;
}

.secondary-btn {
    background-color: var(--secondary-color);
    color: white;
}

.secondary-btn:hover {
    background-color: #717580;
}

.submit-btn {
    background-color: var(--success-color);
    color: white;
}

.submit-btn:hover {
    background-color: #169970;
}

.cancel-btn {
    background-color: #f8f9fc;
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
}

.cancel-btn:hover {
    background-color: #e9ecef;
}

.danger-btn {
    background-color: var(--danger-color);
    color: white;
}

.danger-btn:hover {
    background-color: #c82333;
}

.ai-btn {
    background-color: #6f42c1;
    color: white;
}

.ai-btn:hover {
    background-color: #5a32a3;
}

/* Editor Styles */
.editor-tools {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    padding: 10px;
    background-color: #f1f3f9;
    border: 1px solid var(--border-color);
    border-top-left-radius: 4px;
    border-top-right-radius: 4px;
}

.tool-btn {
    background: none;
    border: none;
    padding: 6px 10px;
    cursor: pointer;
    border-radius: 4px;
    color: var(--dark-color);
}

.tool-btn:hover {
    background-color: rgba(4, 33, 103, 0.1);
}

.color-picker {
    position: relative;
    margin: 0 5px;
}

.color-picker input {
    opacity: 0;
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.color-picker label {
    padding: 6px 10px;
    border-radius: 4px;
    cursor: pointer;
}

.color-picker:hover label {
    background-color: rgba(4, 33, 103, 0.1);
}

.heading-select {
    padding: 6px 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background-color: white;
}

.editor-container {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.editor-wrapper {
    flex: 3;
}

.editor {
    min-height: 400px;
    padding: 15px;
    border: 1px solid var(--border-color);
    border-top: none;
    background-color: white;
    overflow-y: auto;
    font-family: Arial, sans-serif;
}

.preview-panel {
    flex: 2;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background-color: white;
}

.preview-panel h4 {
    margin: 0;
    padding: 10px;
    background-color: #f1f3f9;
    border-bottom: 1px solid var(--border-color);
}

.email-preview {
    padding: 15px;
    overflow-y: auto;
    max-height: 400px;
}

.variable-helper {
    margin-top: 15px;
    padding: 15px;
    background-color: #f8f9fc;
    border: 1px solid var(--border-color);
    border-radius: 4px;
}

.variable-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.variable-tag {
    display: inline-block;
    padding: 5px 10px;
    background-color: #e2e3e5;
    border-radius: 4px;
    font-size: 0.9rem;
    cursor: pointer;
}

.variable-tag:hover {
    background-color: #d6d8db;
}

/* Preview Modal Styles */
.preview-info {
    padding: 15px;
    background-color: #f8f9fc;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    margin-bottom: 20px;
}

.email-preview-container {
    border: 1px solid var(--border-color);
    border-radius: 4px;
    overflow: hidden;
}

.email-preview-content {
    padding: 15px;
    max-height: 500px;
    overflow-y: auto;
}

/* Editor Tabs */
.editor-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.tab-btn {
    padding: 8px 16px;
    border: 1px solid var(--border-color);
    background: #f8f9fc;
    cursor: pointer;
    border-radius: 4px;
    font-size: 0.9rem;
}

.tab-btn.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

/* HTML Editor */
.html-editor {
    width: 100%;
    min-height: 400px;
    padding: 15px;
    border: 1px solid var(--border-color);
    font-family: monospace;
    font-size: 14px;
    line-height: 1.5;
    resize: vertical;
}

/* Test Variables Form */
.test-variables {
    margin-top: 20px;
    padding: 15px;
    background-color: #f8f9fc;
    border: 1px solid var(--border-color);
    border-radius: 4px;
}

.test-variables h4 {
    margin-top: 0;
    margin-bottom: 15px;
    color: var(--primary-color);
}

.test-variables .form-row {
    margin-bottom: 15px;
}

.test-variables .form-row:last-child {
    margin-bottom: 0;
}

.test-variables .form-group {
    margin-bottom: 0;
}

.test-variables label {
    font-size: 0.9rem;
    color: var(--dark-color);
}

.test-variables .form-control {
    font-size: 0.9rem;
}

/* Test Result Styles */
.test-result {
    margin-top: 15px;
    padding: 10px;
    border-radius: 4px;
}

.test-result.success {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
    border: 1px solid rgba(28, 200, 138, 0.2);
}

.test-result.error {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
    border: 1px solid rgba(231, 74, 59, 0.2);
}

.success-message {
    color: #28a745;
    background-color: #d4edda;
    padding: 10px;
    border-radius: 4px;
    border-left: 4px solid #28a745;
}

.error-message {
    color: #dc3545;
    background-color: #f8d7da;
    padding: 10px;
    border-radius: 4px;
    border-left: 4px solid #dc3545;
}

.error-details {
    font-family: monospace;
    background-color: #f8f9fa;
    padding: 8px;
    margin-top: 8px;
    border-radius: 4px;
    font-size: 0.9em;
    white-space: pre-wrap;
    word-break: break-word;
}

/* AI Loader */
.ai-loader {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
    margin-top: 20px;
}

.spinner {
    border: 4px solid rgba(0, 0, 0, 0.1);
    border-left-color: #6f42c1;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin-bottom: 15px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive Adjustments */
@media (max-width: 992px) {
    .editor-container {
        flex-direction: column;
    }
    
    .preview-panel {
        max-height: 250px;
        overflow-y: auto;
    }
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .stats-dashboard {
        flex-direction: column;
    }
    
    .stat-card {
        min-width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal handling
    const modals = document.querySelectorAll('.modal');
    const modalClosers = document.querySelectorAll('.close, #cancelBtn, #closePreviewBtn, #cancelTestBtn, #cancelAiBtn, #cancelDeleteBtn');
    
    // Open modal function
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
        document.body.style.overflow = 'hidden'; // Prevent body scrolling
    }
    
    // Close all modals function
    function closeAllModals() {
        modals.forEach(modal => {
            modal.style.display = 'none';
        });
        document.body.style.overflow = 'auto'; // Re-enable body scrolling
    }
    
    // Close modal by clicking outside
    window.onclick = function(event) {
        modals.forEach(modal => {
            if (event.target === modal) {
                closeAllModals();
            }
        });
    };
    
    // Setup modal closers
    modalClosers.forEach(closer => {
        closer.addEventListener('click', closeAllModals);
    });
    
    // Create new template button
    const createTemplateBtn = document.getElementById('createTemplateBtn');
    createTemplateBtn.addEventListener('click', function() {
        // Reset form
        document.getElementById('templateForm').reset();
        document.getElementById('template_id').value = 0;
        document.getElementById('editor').innerHTML = '';
        document.getElementById('emailPreview').innerHTML = '';
        document.getElementById('modalTitle').textContent = 'Create Email Template';
        
        openModal('templateEditorModal');
    });
    
    // Editor tabs functionality
    const editorTabs = document.querySelectorAll('.tab-btn');
    const editor = document.getElementById('editor');
    const htmlEditor = document.getElementById('html_editor');
    const contentTextarea = document.getElementById('template_content');
    
    editorTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Update active tab
            editorTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Show/hide appropriate editor
            if (this.dataset.tab === 'visual') {
                editor.style.display = 'block';
                htmlEditor.style.display = 'none';
                // Update HTML editor content
                htmlEditor.value = editor.innerHTML;
            } else {
                editor.style.display = 'none';
                htmlEditor.style.display = 'block';
                // Update visual editor content
                editor.innerHTML = htmlEditor.value;
            }
            
            // Update content textarea
            contentTextarea.value = editor.innerHTML;
        });
    });
    
    // HTML editor change handler
    htmlEditor.addEventListener('input', function() {
        editor.innerHTML = this.value;
        contentTextarea.value = this.value;
        document.getElementById('emailPreview').innerHTML = this.value;
    });
    
    // Handle form submission
    document.getElementById('templateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get form data
        const formData = new FormData();
        formData.append('template_id', document.getElementById('template_id').value);
        formData.append('template_name', document.getElementById('template_name').value);
        formData.append('template_subject', document.getElementById('template_subject').value);
        formData.append('template_type', document.getElementById('template_type').value);
        formData.append('template_content', document.getElementById('template_content').value);
        
        // Validate required fields
        const errors = [];
        if (!formData.get('template_name')) errors.push('Template name is required');
        if (!formData.get('template_subject')) errors.push('Subject is required');
        if (!formData.get('template_content')) errors.push('Content is required');
        if (!formData.get('template_type')) errors.push('Template type is required');
        
        if (errors.length > 0) {
            alert(errors.join('\n'));
            return;
        }
        
        // Submit form
        fetch('email-management.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.redirected) {
                window.location.href = response.url;
            } else {
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Error submitting form:', error);
            alert('Error saving template. Please try again.');
        });
    });
    
    // Edit template button functionality
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const templateId = this.dataset.id;
            
            // Reset form
            document.getElementById('templateForm').reset();
            document.getElementById('editor').innerHTML = '';
            document.getElementById('emailPreview').innerHTML = '';
            
            // Show loading state
            const editor = document.getElementById('editor');
            editor.innerHTML = '<div class="loading">Loading template...</div>';
            openModal('templateEditorModal');
            
            // Fetch template content via AJAX
            fetch('ajax/get_email_template.php?id=' + templateId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Error parsing JSON:', text);
                            throw new Error('Invalid JSON response from server');
                        }
                    });
                })
                .then(data => {
                    if (data.success) {
                        // Populate form fields
                        document.getElementById('template_id').value = data.template.id;
                        document.getElementById('template_name').value = data.template.name;
                        document.getElementById('template_subject').value = data.template.subject;
                        document.getElementById('template_type').value = data.template.template_type;
                        
                        // Set editor content
                        editor.innerHTML = data.template.content;
                        document.getElementById('template_content').value = data.template.content;
                        document.getElementById('emailPreview').innerHTML = data.template.content;
                        
                        document.getElementById('modalTitle').textContent = 'Edit Email Template';
                    } else {
                        editor.innerHTML = '<div class="error-message">Error: ' + data.error + '</div>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching template:', error);
                    editor.innerHTML = '<div class="error-message">Error loading template: ' + error.message + '</div>';
                });
        });
    });
    
    // Preview template button functionality
    document.querySelectorAll('.preview-btn').forEach(button => {
        button.addEventListener('click', function() {
            const templateId = this.dataset.id;
            
            // Show loading state
            const previewContent = document.getElementById('previewContent');
            previewContent.innerHTML = '<div class="loading">Loading template...</div>';
            openModal('previewModal');
            
            // Fetch template details via AJAX
            fetch('ajax/get_email_template.php?id=' + templateId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate preview modal
                        document.getElementById('previewName').textContent = data.template.name;
                        document.getElementById('previewSubject').textContent = data.template.subject;
                        document.getElementById('previewType').textContent = 
                            document.querySelector(`.badge-${data.template.template_type}`).textContent.trim();
                        document.getElementById('previewContent').innerHTML = data.template.content;
                    } else {
                        document.getElementById('previewContent').innerHTML = 
                            '<div class="error-message">Error: ' + data.error + '</div>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching template:', error);
                    document.getElementById('previewContent').innerHTML = 
                        '<div class="error-message">Error loading template. Please try again.</div>';
                });
        });
    });
    
    // Delete template button functionality
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const templateId = this.dataset.id;
            document.getElementById('delete_template_id').value = templateId;
            openModal('deleteConfirmModal');
        });
    });
    
    // Confirm delete button
    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        const templateId = document.getElementById('delete_template_id').value;
        
        // Prepare form data
        const formData = new FormData();
        formData.append('id', templateId);
        
        // Delete via AJAX
        fetch('ajax/delete_email_template.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close modal and refresh page to show updated list
                closeAllModals();
                window.location.reload();
            } else {
                alert('Error deleting template: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error deleting template:', error);
            alert('Error deleting template. Please try again.');
        });
    });
    
    // Test email button functionality
    document.querySelectorAll('.test-btn').forEach(button => {
        button.addEventListener('click', function() {
            const templateId = this.dataset.id;
            
            // Fetch template details first
            fetch('ajax/get_email_template.php?id=' + templateId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('test_template_id').value = templateId;
                        document.getElementById('testResult').style.display = 'none';
                        document.getElementById('testEmailForm').reset();
                        openModal('testEmailModal');
                    } else {
                        alert('Error loading template: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error fetching template:', error);
                    alert('Error loading template. Please try again.');
                });
        });
    });
    
    // Handle test email form submission
    document.getElementById('testEmailForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get form data
        const formData = new FormData();
        formData.append('template_id', document.getElementById('test_template_id').value);
        formData.append('email', document.getElementById('test_email').value);
        formData.append('first_name', document.getElementById('test_first_name').value);
        formData.append('last_name', document.getElementById('test_last_name').value);
        formData.append('booking_date', document.getElementById('test_booking_date').value);
        formData.append('booking_time', document.getElementById('test_booking_time').value);
        formData.append('booking_reference', document.getElementById('test_booking_reference').value);
        formData.append('service_name', document.getElementById('test_service_name').value);
        formData.append('consultant_name', document.getElementById('test_consultant_name').value);
        formData.append('company_name', document.getElementById('test_company_name').value);
        
        // Show loading indicator
        const testResult = document.getElementById('testResult');
        testResult.innerHTML = '<p><i class="fas fa-spinner fa-spin"></i> Sending test email...</p>';
        testResult.style.display = 'block';
        
        // Send test email via AJAX
        fetch('ajax/send_test_email.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                testResult.innerHTML = '<p class="success-message"><i class="fas fa-check-circle"></i> ' + 
                    (data.message || 'Email sent successfully!') + '</p>';
                testResult.className = 'test-result success';
            } else {
                testResult.innerHTML = '<p class="error-message"><i class="fas fa-exclamation-circle"></i> ' + 
                    (data.error || 'Failed to send email.') + '</p>';
                testResult.className = 'test-result error';
            }
        })
        .catch(error => {
            console.error('Error sending test email:', error);
            testResult.innerHTML = '<p class="error-message"><i class="fas fa-exclamation-circle"></i> Error: ' + 
                error.message + '</p>';
            testResult.className = 'test-result error';
        });
    });
    
    // AI Generate button
    document.getElementById('aiGenerateBtn').addEventListener('click', function() {
        document.getElementById('aiPrompt').value = '';
        document.getElementById('aiSubject').value = document.getElementById('template_subject').value || '';
        document.getElementById('aiLoader').style.display = 'none';
        openModal('aiGeneratorModal');
    });
    
    // Generate AI template button
    document.getElementById('generateBtn').addEventListener('click', function() {
        const prompt = document.getElementById('aiPrompt').value;
        const subject = document.getElementById('aiSubject').value;
        
        if (!prompt) {
            alert('Please describe what kind of template you want to generate.');
            return;
        }
        
        // Show loader
        document.getElementById('aiLoader').style.display = 'flex';
        
        // Construct a detailed prompt with the subject
        const fullPrompt = `Generate an HTML email template for ${subject ? 'email with subject "' + subject + '"' : 'general email'}: ${prompt}. The template should use responsive design and include placeholders like {first_name}, {email}, etc. where appropriate.`;
        
        // Call AI template generator API
        fetch('ajax/generate_email_template.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ prompt: fullPrompt })
        })
        .then(response => response.json())
        .then(data => {
            // Hide loader
            document.getElementById('aiLoader').style.display = 'none';
            
            if (data.success) {
                // Update the editor with generated template
                editor.innerHTML = data.template;
                contentTextarea.value = data.template;
                document.getElementById('emailPreview').innerHTML = data.template;
                
                // Set the subject if provided
                if (subject) {
                    document.getElementById('template_subject').value = subject;
                }
                
                // Close AI modal and show editor
                closeAllModals();
                openModal('templateEditorModal');
            } else {
                alert('Error generating template: ' + data.error);
            }
        })
        .catch(error => {
            // Hide loader
            document.getElementById('aiLoader').style.display = 'none';
            console.error('Error generating template:', error);
            alert('Error connecting to AI service. Please try again later.');
        });
    });

    // Add loading and error styles
    const style = document.createElement('style');
    style.textContent = `
        .loading {
            padding: 20px;
            text-align: center;
            color: #666;
        }
        .error-message {
            padding: 20px;
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            margin: 10px 0;
        }
    `;
    document.head.appendChild(style);
});
</script>
