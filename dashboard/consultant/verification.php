<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../../config/db_connect.php';

// Check if user is logged in and is a consultant
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'consultant') {
    header("Location: ../../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = "Verification Documents";
require_once '../../includes/header.php';

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $document_type = filter_input(INPUT_POST, 'document_type', FILTER_SANITIZE_STRING);
    
    // Check if document type is selected
    if (empty($document_type)) {
        $error_message = "Please select a document type.";
    }
    // Check if file is uploaded
    elseif (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = "Error uploading file. Please try again.";
    }
    else {
        // Process file upload
        $file_name = $_FILES['document_file']['name'];
        $file_tmp = $_FILES['document_file']['tmp_name'];
        $file_size = $_FILES['document_file']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Check file size (limit to 5MB)
        if ($file_size > 5242880) {
            $error_message = "File is too large. Maximum size is 5MB.";
        }
        // Check file extension
        elseif (!in_array($file_ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
            $error_message = "Invalid file format. Allowed formats: PDF, JPG, JPEG, PNG.";
        }
        else {
            // Create directory if it doesn't exist
            $upload_dir = "../../uploads/consultant_documents/" . $user_id . "/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique file name
            $new_file_name = uniqid() . '_' . $document_type . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;
            $db_path = "uploads/consultant_documents/" . $user_id . "/" . $new_file_name;
            
            // Move file to upload directory
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Insert document record into database
                $query = "INSERT INTO consultant_verifications 
                          (consultant_id, document_type, document_path, uploaded_at) 
                          VALUES (?, ?, ?, NOW())";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param('iss', $user_id, $document_type, $db_path);
                
                if ($stmt->execute()) {
                    $success_message = "Document uploaded successfully and is pending verification.";
                } else {
                    $error_message = "Error saving document information: " . $conn->error;
                }
            } else {
                $error_message = "Error moving uploaded file. Please try again.";
            }
        }
    }
}

// Get verification status
$status_query = "SELECT cp.is_verified, cp.verified_at, CONCAT(u.first_name, ' ', u.last_name) AS verified_by_name
                FROM consultant_profiles cp
                LEFT JOIN users u ON cp.verified_by = u.id
                WHERE cp.consultant_id = ?";

$status_stmt = $conn->prepare($status_query);
$status_stmt->bind_param('i', $user_id);
$status_stmt->execute();
$status_result = $status_stmt->get_result();
$verification_status = $status_result->fetch_assoc();

// Get uploaded documents
$docs_query = "SELECT id, document_type, document_path, uploaded_at, verified, verified_at 
              FROM consultant_verifications 
              WHERE consultant_id = ? 
              ORDER BY uploaded_at DESC";

$docs_stmt = $conn->prepare($docs_query);
$docs_stmt->bind_param('i', $user_id);
$docs_stmt->execute();
$docs_result = $docs_stmt->get_result();
$documents = [];

if ($docs_result->num_rows > 0) {
    while ($row = $docs_result->fetch_assoc()) {
        $documents[] = $row;
    }
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Verification Status</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($verification_status['is_verified']) && $verification_status['is_verified']): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            Your profile is verified by Visafy.
                            <?php if ($verification_status['verified_at']): ?>
                                <br>
                                <small>Verified on <?php echo date('F j, Y', strtotime($verification_status['verified_at'])); ?> 
                                <?php if ($verification_status['verified_by_name']): ?>
                                by <?php echo htmlspecialchars($verification_status['verified_by_name']); ?>
                                <?php endif; ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Your profile is not verified yet. Please upload the required documents for verification.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">
                    <h5>Upload Verification Document</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success">
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="" method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="document_type" class="form-label">Document Type</label>
                            <select class="form-select" id="document_type" name="document_type" required>
                                <option value="">Select Document Type</option>
                                <option value="business_license">Business License</option>
                                <option value="registration_certificate">Business Registration Certificate</option>
                                <option value="professional_certification">Professional Certification</option>
                                <option value="id_proof">ID Proof</option>
                                <option value="address_proof">Address Proof</option>
                                <option value="insurance_certificate">Professional Indemnity Insurance</option>
                                <option value="accreditation_certificate">Accreditation Certificate</option>
                                <option value="other">Other Document</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="document_file" class="form-label">Document File</label>
                            <input class="form-control" type="file" id="document_file" name="document_file" accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="form-text">Allowed formats: PDF, JPG, JPEG, PNG. Maximum size: 5MB.</div>
                        </div>
                        
                        <button type="submit" name="upload_document" class="btn btn-primary">
                            <i class="fas fa-upload me-2"></i> Upload Document
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5>Required Documents</h5>
                </div>
                <div class="card-body">
                    <p>To get verified on Visafy, please upload the following documents:</p>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Business License
                            <?php 
                            $has_license = false;
                            foreach ($documents as $doc) {
                                if ($doc['document_type'] === 'business_license') {
                                    $has_license = true;
                                    if ($doc['verified']) {
                                        echo '<span class="badge bg-success">Verified</span>';
                                    } else {
                                        echo '<span class="badge bg-warning">Pending</span>';
                                    }
                                    break;
                                }
                            }
                            if (!$has_license) {
                                echo '<span class="badge bg-danger">Missing</span>';
                            }
                            ?>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Business Registration Certificate
                            <?php 
                            $has_reg = false;
                            foreach ($documents as $doc) {
                                if ($doc['document_type'] === 'registration_certificate') {
                                    $has_reg = true;
                                    if ($doc['verified']) {
                                        echo '<span class="badge bg-success">Verified</span>';
                                    } else {
                                        echo '<span class="badge bg-warning">Pending</span>';
                                    }
                                    break;
                                }
                            }
                            if (!$has_reg) {
                                echo '<span class="badge bg-danger">Missing</span>';
                            }
                            ?>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            ID Proof
                            <?php 
                            $has_id = false;
                            foreach ($documents as $doc) {
                                if ($doc['document_type'] === 'id_proof') {
                                    $has_id = true;
                                    if ($doc['verified']) {
                                        echo '<span class="badge bg-success">Verified</span>';
                                    } else {
                                        echo '<span class="badge bg-warning">Pending</span>';
                                    }
                                    break;
                                }
                            }
                            if (!$has_id) {
                                echo '<span class="badge bg-danger">Missing</span>';
                            }
                            ?>
                        </li>
                    </ul>
                    
                    <div class="mt-3 small text-muted">
                        <p>Additional documents may be requested based on your business type and location. Verification typically takes 1-3 business days after all required documents are submitted.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">
                    <h5>Uploaded Documents</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($documents)): ?>
                        <div class="alert alert-info">
                            You haven't uploaded any verification documents yet.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Document Type</th>
                                        <th>Upload Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $document): ?>
                                        <tr>
                                            <td><?php echo ucwords(str_replace('_', ' ', $document['document_type'])); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($document['uploaded_at'])); ?></td>
                                            <td>
                                                <?php if ($document['verified']): ?>
                                                    <span class="badge bg-success">Verified</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pending Review</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="../../<?php echo htmlspecialchars($document['document_path']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <?php if (!$document['verified']): ?>
                                                    <a href="delete-document.php?id=<?php echo $document['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this document?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .card {
        margin-bottom: 20px;
    }
    .card-header {
        background-color: #f8f9fa;
    }
    .badge {
        font-size: 85%;
    }
</style>

<?php require_once '../../includes/footer.php'; ?>