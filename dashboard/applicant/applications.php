<?php
// Set page title
$page_title = "My Applications - Applicant";

// Include header
include('includes/header.php');

// Get applicant ID
$applicant_id = $user_id;

// Function to format date and time
function formatDateTime($datetime, $format = 'M j, Y g:i A') {
    if (empty($datetime)) return 'N/A';
    return date($format, strtotime($datetime));
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'draft':
            return 'badge-secondary';
        case 'submitted':
            return 'badge-info';
        case 'under_review':
            return 'badge-primary';
        case 'additional_documents_requested':
            return 'badge-warning';
        case 'processing':
            return 'badge-info';
        case 'approved':
            return 'badge-success';
        case 'rejected':
            return 'badge-danger';
        case 'on_hold':
            return 'badge-warning';
        case 'completed':
            return 'badge-success';
        case 'cancelled':
            return 'badge-danger';
        default:
            return 'badge-light';
    }
}

// Get query parameters for filtering
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_visa = isset($_GET['visa']) ? intval($_GET['visa']) : 0;
$filter_consultant = isset($_GET['consultant']) ? intval($_GET['consultant']) : 0;

// Handle document upload
if (isset($_POST['upload_document']) && isset($_POST['application_id']) && isset($_POST['document_type_id'])) {
    $application_id = intval($_POST['application_id']);
    $document_type_id = intval($_POST['document_type_id']);
    
    // Verify this application belongs to the applicant
    $verify_query = "SELECT a.id, a.organization_id 
                    FROM applications a 
                    WHERE a.id = ? AND a.user_id = ? AND a.deleted_at IS NULL";
    $stmt = $conn->prepare($verify_query);
    $stmt->bind_param("ii", $application_id, $applicant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $app_data = $result->fetch_assoc();
        $organization_id = $app_data['organization_id'];
        
        // Handle file upload
        if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0) {
            $file_name = $_FILES['document_file']['name'];
            $file_tmp = $_FILES['document_file']['tmp_name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Allowed file extensions
            $allowed_extensions = array('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png');
            
            if (in_array($file_ext, $allowed_extensions)) {
                // Create unique filename
                $new_file_name = 'doc_' . time() . '_' . uniqid() . '.' . $file_ext;
                $upload_dir = '../../uploads/documents/';
                
                // Ensure directory exists
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $upload_path = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Save file path to database
                    $file_path = 'uploads/documents/' . $new_file_name;
                    
                    // Check if document already exists
                    $check_query = "SELECT id FROM application_documents 
                                    WHERE application_id = ? AND document_type_id = ?";
                    $stmt = $conn->prepare($check_query);
                    $stmt->bind_param("ii", $application_id, $document_type_id);
                    $stmt->execute();
                    $check_result = $stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        // Update existing document
                        $doc_data = $check_result->fetch_assoc();
                        $update_query = "UPDATE application_documents 
                                        SET file_path = ?, 
                                        status = 'submitted', 
                                        submitted_by = ?, 
                                        submitted_at = NOW() 
                                        WHERE id = ?";
                        $stmt = $conn->prepare($update_query);
                        $stmt->bind_param("sii", $file_path, $applicant_id, $doc_data['id']);
                        
                        if ($stmt->execute()) {
                            // Add activity log
                            $log_query = "INSERT INTO application_activity_logs 
                                        (application_id, user_id, activity_type, description) 
                                        VALUES (?, ?, 'document_updated', 'Document updated by applicant')";
                            $stmt = $conn->prepare($log_query);
                            $stmt->bind_param("ii", $application_id, $applicant_id);
                            $stmt->execute();
                            
                            $success_message = "Document updated successfully.";
                        } else {
                            $error_message = "Failed to update document record. Please try again.";
                        }
                    } else {
                        // Insert new document
                        $insert_query = "INSERT INTO application_documents 
                                        (application_id, document_type_id, file_path, status, submitted_by, 
                                        submitted_at, organization_id) 
                                        VALUES (?, ?, ?, 'submitted', ?, NOW(), ?)";
                        $stmt = $conn->prepare($insert_query);
                        $stmt->bind_param("iisii", $application_id, $document_type_id, $file_path, $applicant_id, $organization_id);
                        
                        if ($stmt->execute()) {
                            // Add activity log
                            $log_query = "INSERT INTO application_activity_logs 
                                        (application_id, user_id, activity_type, description) 
                                        VALUES (?, ?, 'document_added', 'Document added by applicant')";
                            $stmt = $conn->prepare($log_query);
                            $stmt->bind_param("ii", $application_id, $applicant_id);
                            $stmt->execute();
                            
                            $success_message = "Document uploaded successfully.";
                        } else {
                            $error_message = "Failed to save document record. Please try again.";
                        }
                    }
                } else {
                    $error_message = "Failed to upload document. Please try again.";
                }
            } else {
                $error_message = "Invalid file type. Allowed file types: PDF, DOC, DOCX, JPG, JPEG, PNG.";
            }
        } else {
            $error_message = "Please select a file to upload.";
        }
    } else {
        $error_message = "Invalid application or you don't have permission to upload documents.";
    }
}

// Build query to get all applications for this user with filters
$query = "SELECT a.id, a.reference_number, a.submitted_at, a.created_at, a.expected_completion_date,
                 a.priority, a.notes,
                 aps.name AS status_name, aps.color AS status_color, aps.description AS status_description,
                 v.visa_type, c.country_name,
                 CONCAT(u.first_name, ' ', u.last_name) AS consultant_name, u.profile_picture,
                 o.name AS organization_name,
                 (SELECT COUNT(*) FROM application_documents WHERE application_id = a.id) AS total_documents,
                 (SELECT COUNT(*) FROM application_documents WHERE application_id = a.id AND status = 'submitted') AS submitted_documents,
                 (SELECT COUNT(*) FROM application_documents WHERE application_id = a.id AND status = 'approved') AS approved_documents
          FROM applications a
          JOIN application_statuses aps ON a.status_id = aps.id
          JOIN visas v ON a.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          JOIN users u ON a.consultant_id = u.id
          JOIN organizations o ON a.organization_id = o.id
          WHERE a.user_id = ? AND a.deleted_at IS NULL";

// Add filters if provided
$params = [$applicant_id];
$types = "i";

if (!empty($filter_status)) {
    $query .= " AND aps.name = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($filter_visa)) {
    $query .= " AND a.visa_id = ?";
    $params[] = $filter_visa;
    $types .= "i";
}

if (!empty($filter_consultant)) {
    $query .= " AND a.consultant_id = ?";
    $params[] = $filter_consultant;
    $types .= "i";
}

// Order by priority and submission date
$query .= " ORDER BY 
           FIELD(a.priority, 'urgent', 'high', 'normal', 'low'),
           CASE WHEN aps.name IN ('completed', 'cancelled', 'rejected') 
                THEN 0 ELSE 1 END DESC, 
           a.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$applications_result = $stmt->get_result();

// Get list of consultants for filter dropdown
$consultants_query = "SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) AS consultant_name
                     FROM applications a
                     JOIN users u ON a.consultant_id = u.id
                     WHERE a.user_id = ? AND a.deleted_at IS NULL
                     ORDER BY consultant_name";
$stmt = $conn->prepare($consultants_query);
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$consultants_result = $stmt->get_result();

// Get list of statuses for filter dropdown
$statuses_query = "SELECT DISTINCT aps.name, aps.color
                  FROM applications a
                  JOIN application_statuses aps ON a.status_id = aps.id
                  WHERE a.user_id = ? AND a.deleted_at IS NULL
                  ORDER BY aps.name";
$stmt = $conn->prepare($statuses_query);
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$statuses_result = $stmt->get_result();

// Get list of visas for filter dropdown
$visas_query = "SELECT DISTINCT v.visa_id, v.visa_type, c.country_name
                FROM applications a
                JOIN visas v ON a.visa_id = v.visa_id
                JOIN countries c ON v.country_id = c.country_id
                WHERE a.user_id = ? AND a.deleted_at IS NULL
                ORDER BY c.country_name, v.visa_type";
$stmt = $conn->prepare($visas_query);
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$visas_result = $stmt->get_result();
?>

<div class="content">
    <div class="dashboard-header">
        <h1>My Applications</h1>
        <p>Manage your visa applications and track their progress</p>
    </div>
    
    <?php if (isset($success_message)): ?>
    <div class="alert alert-success">
        <?php echo $success_message; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>
    
    <div class="dashboard-section">
        <div class="section-header">
            <h2>Application Status</h2>
            <div class="action-buttons">
                <a href="my_documents.php" class="btn btn-outline">
                    <i class="fas fa-folder"></i> My Documents
                </a>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-container mb-4">
            <form action="" method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">All Statuses</option>
                        <?php while ($status = $statuses_result->fetch_assoc()): ?>
                            <option value="<?php echo $status['name']; ?>" <?php if ($filter_status === $status['name']) echo 'selected'; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $status['name'])); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="visa">Visa Type</label>
                    <select name="visa" id="visa" class="form-control">
                        <option value="">All Visa Types</option>
                        <?php while ($visa = $visas_result->fetch_assoc()): ?>
                            <option value="<?php echo $visa['visa_id']; ?>" <?php if ($filter_visa === (int)$visa['visa_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($visa['country_name'] . ' - ' . $visa['visa_type']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="consultant">Consultant</label>
                    <select name="consultant" id="consultant" class="form-control">
                        <option value="">All Consultants</option>
                        <?php while ($consultant = $consultants_result->fetch_assoc()): ?>
                            <option value="<?php echo $consultant['id']; ?>" <?php if ($filter_consultant === (int)$consultant['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($consultant['consultant_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="applications.php" class="btn btn-outline">Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Applications List -->
        <?php if ($applications_result->num_rows > 0): ?>
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Visa Type</th>
                        <th>Consultant</th>
                        <th>Status</th>
                        <th>Documents</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($application = $applications_result->fetch_assoc()): 
                        $is_active = !in_array($application['status_name'], ['completed', 'cancelled', 'rejected']);
                        $can_upload = in_array($application['status_name'], ['draft', 'submitted', 'under_review', 'additional_documents_requested']);
                        
                        // Get consultant profile picture
                        $profile_img = '../../assets/images/default-profile.jpg';
                        if (!empty($application['profile_picture'])) {
                            if (file_exists('../../uploads/profiles/' . $application['profile_picture'])) {
                                $profile_img = '../../uploads/profiles/' . $application['profile_picture'];
                            }
                        }
                        
                        // Calculate document progress percentage
                        $doc_progress = 0;
                        if ($application['total_documents'] > 0) {
                            $doc_progress = round(($application['approved_documents'] + $application['submitted_documents']) / $application['total_documents'] * 100);
                        }
                    ?>
                    <tr>
                        <td>
                            <a href="application_details.php?id=<?php echo $application['id']; ?>" class="reference-link">
                                <?php echo $application['reference_number']; ?>
                            </a>
                            <?php if ($application['priority'] == 'urgent'): ?>
                                <span class="priority-badge urgent"><i class="fas fa-exclamation-circle"></i> Urgent</span>
                            <?php elseif ($application['priority'] == 'high'): ?>
                                <span class="priority-badge high"><i class="fas fa-arrow-up"></i> High</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="visa-info">
                                <div class="visa-type"><?php echo htmlspecialchars($application['visa_type']); ?></div>
                                <div class="country-name"><?php echo htmlspecialchars($application['country_name']); ?></div>
                            </div>
                        </td>
                        <td>
                            <div class="consultant-info">
                                <img src="<?php echo $profile_img; ?>" alt="Profile" class="consultant-img">
                                <div>
                                    <div class="consultant-name"><?php echo htmlspecialchars($application['consultant_name']); ?></div>
                                    <div class="organization"><?php echo htmlspecialchars($application['organization_name']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge" style="background-color: <?php echo $application['status_color']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $application['status_name'])); ?>
                            </span>
                            <?php if (!empty($application['expected_completion_date'])): ?>
                                <div class="expected-date">
                                    <i class="far fa-calendar-alt"></i> 
                                    Expected: <?php echo formatDateTime($application['expected_completion_date'], 'M j, Y'); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="document-progress">
                                <div class="progress-bar">
                                    <div class="progress" style="width: <?php echo $doc_progress; ?>%;"></div>
                                </div>
                                <div class="document-stats">
                                    <span class="approved"><?php echo $application['approved_documents']; ?> approved</span> / 
                                    <span class="submitted"><?php echo $application['submitted_documents']; ?> submitted</span> / 
                                    <span class="total"><?php echo $application['total_documents']; ?> total</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="submission-date">
                                <?php if (!empty($application['submitted_at'])): ?>
                                    <div class="date"><?php echo formatDateTime($application['submitted_at'], 'M j, Y'); ?></div>
                                    <div class="time"><?php echo formatDateTime($application['submitted_at'], 'g:i A'); ?></div>
                                <?php else: ?>
                                    <span class="not-submitted">Not submitted</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="application_details.php?id=<?php echo $application['id']; ?>" class="btn-action btn-view" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <?php if ($can_upload): ?>
                                <button type="button" class="btn-action btn-success upload-docs-btn" 
                                        data-toggle="modal" 
                                        data-target="#uploadDocumentModal"
                                        data-application-id="<?php echo $application['id']; ?>"
                                        data-reference="<?php echo $application['reference_number']; ?>"
                                        title="Upload Documents">
                                    <i class="fas fa-file-upload"></i>
                                </button>
                                <?php endif; ?>
                                
                                <a href="application_timeline.php?id=<?php echo $application['id']; ?>" class="btn-action btn-info" title="View Timeline">
                                    <i class="fas fa-history"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>You don't have any applications yet. Please contact your consultant to initiate an application.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Upload Document Modal -->
<div class="modal fade" id="uploadDocumentModal" tabindex="-1" role="dialog" aria-labelledby="uploadDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadDocumentModalLabel">Upload Document</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <p><strong>Application Reference:</strong> <span id="applicationRef"></span></p>
                    
                    <div class="form-group">
                        <label for="document_type_id">Document Type</label>
                        <select class="form-control" id="document_type_id" name="document_type_id" required>
                            <option value="">Select Document Type</option>
                            <!-- This will be populated via AJAX -->
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="document_file">Upload File</label>
                        <input type="file" class="form-control-file" id="document_file" name="document_file" required>
                        <small class="form-text text-muted">Allowed file types: PDF, DOC, DOCX, JPG, JPEG, PNG. Max file size: 5MB.</small>
                    </div>
                    
                    <input type="hidden" id="application_id" name="application_id" value="">
                    <input type="hidden" name="upload_document" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Upload Document</button>
                </div>
            </form>
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
    --info-color: #36b9cc;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-color: #e3e6f0;
    --message-color: #4e73df;
    --notification-color: #f6c23e;
}

.content {
    padding: 20px;
}

.dashboard-header {
    margin-bottom: 20px;
}

.dashboard-header h1 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.8rem;
}

.dashboard-header p {
    margin: 5px 0 0;
    color: var(--secondary-color);
}

.dashboard-section {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    margin-bottom: 30px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.section-header h2 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.2rem;
}

.filters-container {
    background-color: var(--light-color);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.filter-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.filter-group {
    margin-bottom: 15px;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--dark-color);
    font-size: 0.9rem;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 0.9rem;
}

.filter-actions {
    display: flex;
    align-items: flex-end;
    gap: 10px;
}

.dashboard-table {
    width: 100%;
    border-collapse: collapse;
}

.dashboard-table th {
    text-align: left;
    padding: 10px;
    font-weight: 600;
    color: var(--primary-color);
    font-size: 0.85rem;
}

.dashboard-table td {
    padding: 10px;
    border-top: 1px solid var(--border-color);
    font-size: 0.9rem;
}

.reference-link {
    font-weight: 500;
    color: var(--primary-color);
    text-decoration: none;
}

.reference-link:hover {
    text-decoration: underline;
}

.priority-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 6px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 500;
    color: white;
    margin-left: 6px;
}

.priority-badge.urgent {
    background-color: var(--danger-color);
}

.priority-badge.high {
    background-color: var(--warning-color);
    color: #000;
}

.visa-info .visa-type {
    font-weight: 500;
}

.visa-info .country-name {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.consultant-info {
    display: flex;
    align-items: center;
}

.consultant-img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 10px;
}

.consultant-name {
    font-weight: 500;
}

.organization {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    color: white;
}

.expected-date {
    font-size: 0.8rem;
    color: var(--secondary-color);
    margin-top: 4px;
}

.document-progress {
    width: 100%;
}

.progress-bar {
    height: 6px;
    width: 100%;
    background-color: var(--light-color);
    border-radius: 3px;
    margin-bottom: 4px;
    overflow: hidden;
}

.progress-bar .progress {
    height: 100%;
    background-color: var(--success-color);
}

.document-stats {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.document-stats .approved {
    color: var(--success-color);
}

.document-stats .submitted {
    color: var(--info-color);
}

.submission-date .date {
    font-weight: 500;
}

.submission-date .time {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.not-submitted {
    font-size: 0.9rem;
    color: var(--secondary-color);
    font-style: italic;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 4px;
    font-size: 14px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    color: white;
}

.btn-view {
    background-color: var(--primary-color);
}

.btn-view:hover {
    background-color: #031c56;
}

.btn-success {
    background-color: var(--success-color);
}

.btn-success:hover {
    background-color: #169b6b;
}

.btn-info {
    background-color: var(--info-color);
}

.btn-info:hover {
    background-color: #2c9faf;
}

.btn-danger {
    background-color: var(--danger-color);
}

.btn-danger:hover {
    background-color: #c13a2d;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: background-color 0.2s;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: #031c56;
}

.btn-secondary {
    background-color: var(--secondary-color);
    color: white;
}

.btn-outline {
    background-color: transparent;
    border: 1px solid var(--border-color);
    color: var(--secondary-color);
}

.btn-outline:hover {
    background-color: var(--light-color);
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 36px;
    margin-bottom: 10px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0;
    font-size: 0.9rem;
}

.empty-state a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
}

.empty-state a:hover {
    text-decoration: underline;
}

.alert {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    position: relative;
}

.alert-success {
    background-color: rgba(28, 200, 138, 0.1);
    border: 1px solid rgba(28, 200, 138, 0.2);
    color: var(--success-color);
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.1);
    border: 1px solid rgba(231, 74, 59, 0.2);
    color: var(--danger-color);
}

.close {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 16px;
    color: inherit;
    opacity: 0.8;
    background: none;
    border: none;
    cursor: pointer;
}

.close:hover {
    opacity: 1;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color);
    padding: 15px;
}

.modal-body {
    padding: 15px;
}

.modal-footer {
    border-top: 1px solid var(--border-color);
    padding: 15px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.form-control-file {
    display: block;
    width: 100%;
    padding: 8px 0;
}

.form-text {
    margin-top: 5px;
    font-size: 0.8rem;
}

@media (max-width: 992px) {
    .filter-form {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        justify-content: flex-start;
    }
    
    .dashboard-table {
        display: block;
        overflow-x: auto;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle upload document button clicks
    const uploadButtons = document.querySelectorAll('.upload-docs-btn');
    uploadButtons.forEach(button => {
        button.addEventListener('click', function() {
            const applicationId = this.getAttribute('data-application-id');
            const reference = this.getAttribute('data-reference');
            
            document.getElementById('application_id').value = applicationId;
            document.getElementById('applicationRef').textContent = reference;
            
            // Fetch available document types for this application
            fetchDocumentTypes(applicationId);
        });
    });
    
    // Function to fetch document types via AJAX
    function fetchDocumentTypes(applicationId) {
        // Clear previous options
        const selectElement = document.getElementById('document_type_id');
        selectElement.innerHTML = '<option value="">Select Document Type</option>';
        
        // Fetch document types via AJAX
        fetch('ajax/get_application_document_types.php?application_id=' + applicationId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add options for each document type
                    data.document_types.forEach(docType => {
                        const option = document.createElement('option');
                        option.value = docType.id;
                        option.textContent = docType.name;
                        
                        // Add indicator for mandatory documents
                        if (docType.is_mandatory) {
                            option.textContent += ' (Required)';
                        }
                        
                        // Add status indicator
                        if (docType.status) {
                            switch (docType.status) {
                                case 'pending':
                                    option.textContent += ' - Pending';
                                    break;
                                case 'submitted':
                                    option.textContent += ' - Submitted';
                                    break;
                                case 'approved':
                                    option.textContent += ' - Approved';
                                    break;
                                case 'rejected':
                                    option.textContent += ' - Rejected';
                                    break;
                            }
                        }
                        
                        selectElement.appendChild(option);
                    });
                } else {
                    // Show error
                    alert('Error loading document types: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while loading document types.');
            });
    }
    
    // Close alert messages after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const closeBtn = alert.querySelector('.close');
            if (closeBtn) {
                closeBtn.click();
            }
        });
    }, 5000);
});
</script>

<?php
// Include footer
include('includes/footer.php');
?>
