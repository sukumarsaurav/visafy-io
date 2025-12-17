<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in and has a valid user_id
if (!isset($_SESSION['user_id']) && isset($_SESSION['id'])) {
    // Copy id to user_id for compatibility
    $_SESSION['user_id'] = $_SESSION['id'];
} elseif (!isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
    // Redirect to login if no user_id is set
    header("Location: login.php");
    exit;
}

$page_title = "Document Management";
$page_specific_css = "assets/css/documents.css";
require_once 'includes/header.php';

// Get the consultant ID from the session
$consultant_id = $_SESSION["id"];

// Get organization details
$org_query = "SELECT o.*, c.company_name 
              FROM organizations o 
              LEFT JOIN consultants c ON c.user_id = ?
              WHERE o.id = (SELECT organization_id FROM users WHERE id = ?)";
$org_stmt = $conn->prepare($org_query);
$org_stmt->bind_param("ii", $consultant_id, $consultant_id);
$org_stmt->execute();
$org_result = $org_stmt->get_result();
$organization = $org_result->fetch_assoc();
$org_stmt->close();

// Get the organization ID
$organization_id = $organization['id'] ?? null;

// Verify organization ID is set
if (!$organization_id) {
    die("Organization ID not set. Please log in again.");
}

// Get all document categories
$query = "SELECT * FROM document_categories 
          WHERE organization_id = ? OR is_global = TRUE 
          ORDER BY name";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $organization_id);
$stmt->execute();
$categories_result = $stmt->get_result();
$categories = [];

if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}
$stmt->close();

// Get all document types
$query = "SELECT dt.*, dc.name as category_name 
          FROM document_types dt 
          JOIN document_categories dc ON dt.category_id = dc.id 
          WHERE dt.organization_id = ? OR dt.is_global = TRUE
          ORDER BY dt.name";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $organization_id);
$stmt->execute();
$document_types_result = $stmt->get_result();
$document_types = [];

if ($document_types_result && $document_types_result->num_rows > 0) {
    while ($row = $document_types_result->fetch_assoc()) {
        $document_types[] = $row;
    }
}
$stmt->close();

// Get all document templates
$query = "SELECT dt.*, dty.name as document_type_name, CONCAT(u.first_name, ' ', u.last_name) as created_by_name 
          FROM document_templates dt 
          JOIN document_types dty ON dt.document_type_id = dty.id
          JOIN users u ON dt.created_by = u.id
          WHERE dt.organization_id = ?
          ORDER BY dt.name";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $organization_id);
$stmt->execute();
$templates_result = $stmt->get_result();
$templates = [];

if ($templates_result && $templates_result->num_rows > 0) {
    while ($row = $templates_result->fetch_assoc()) {
        $templates[] = $row;
    }
}
$stmt->close();

// Get users (clients) for the generated documents dropdown
$query = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name, email FROM users WHERE user_type = 'applicant' ORDER BY first_name, last_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$clients_result = $stmt->get_result();
$clients = [];

if ($clients_result && $clients_result->num_rows > 0) {
    while ($row = $clients_result->fetch_assoc()) {
        $clients[] = $row;
    }
}
$stmt->close();

// Handle document category form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    // Initialize variables with default values
    $name = '';
    $description = '';
    $is_global = 0; // Always set to 0 for consultants
    
    // Get and validate name
    if (isset($_POST['name'])) {
    $name = trim($_POST['name']);
    }
    
    // Get and validate description
    if (isset($_POST['description'])) {
    $description = trim($_POST['description']);
    }
    
    // Validate inputs
    $errors = [];
    if (empty($name)) {
        $errors[] = "Category name is required";
    }
    
    if (empty($errors)) {
        // Check if category already exists
        $check_query = "SELECT id FROM document_categories WHERE name = ? AND organization_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('si', $name, $organization_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "Document category already exists";
        }
        $check_stmt->close();
    }
    
    if (empty($errors)) {
        // Insert new category
        $insert_query = "INSERT INTO document_categories (name, description, organization_id, is_global) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('ssii', $name, $description, $organization_id, $is_global);
        
        if ($stmt->execute()) {
            $success_message = "Document category added successfully";
            $stmt->close();
            header("Location: documents.php?success=1");
            exit;
        } else {
            $error_message = "Error adding document category: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle document type form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_document_type'])) {
    // Initialize variables with default values
    $name = '';
    $category_id = '';
    $description = '';
    $is_active = 1;
    
    // Get and validate inputs
    if (isset($_POST['type_name'])) {
        $name = trim($_POST['type_name']);
    }
    
    if (isset($_POST['category_id'])) {
        $category_id = trim($_POST['category_id']);
    }
    
    if (isset($_POST['type_description'])) {
        $description = trim($_POST['type_description']);
    }
    
    // Validate inputs
    $errors = [];
    if (empty($name)) {
        $errors[] = "Document type name is required";
    }
    if (empty($category_id)) {
        $errors[] = "Category is required";
    }
    
    if (empty($errors)) {
        // Check if document type already exists for this organization
        $check_query = "SELECT id FROM document_types WHERE name = ? AND organization_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('si', $name, $organization_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
            $errors[] = "Document type with this name already exists in your organization";
        }
        $check_stmt->close();
    }
    
    if (empty($errors)) {
        // Insert new document type
        $insert_query = "INSERT INTO document_types (name, category_id, description, organization_id, is_active, is_global) 
                        VALUES (?, ?, ?, ?, ?, 0)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('sisii', $name, $category_id, $description, $organization_id, $is_active);
        
        if ($stmt->execute()) {
            $success_message = "Document type added successfully";
            $stmt->close();
            header("Location: documents.php?success=2");
            exit;
        } else {
            $error_message = "Error adding document type: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle document template form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_template'])) {
    // Initialize variables with default values
    $template_name = '';
    $document_type_id = '';
    $content = '';
    $is_active = 1;
    
    // Get and validate inputs
    if (isset($_POST['template_name'])) {
    $template_name = trim($_POST['template_name']);
    }
    
    if (isset($_POST['document_type_id'])) {
        $document_type_id = trim($_POST['document_type_id']);
    }
    
    if (isset($_POST['content'])) {
    $content = trim($_POST['content']);
    }
    
    // Validate inputs
    $errors = [];
    if (empty($template_name)) {
        $errors[] = "Template name is required";
    }
    if (empty($document_type_id)) {
        $errors[] = "Document type is required";
    }
    if (empty($content)) {
        $errors[] = "Template content is required";
    }
    
    if (empty($errors)) {
        // Check if template name already exists for this organization
        $check_query = "SELECT id FROM document_templates WHERE name = ? AND organization_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('si', $template_name, $organization_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "Template with this name already exists in your organization";
        }
        $check_stmt->close();
    }
    
    if (empty($errors)) {
        // Insert new template
        $insert_query = "INSERT INTO document_templates (name, document_type_id, content, is_active, organization_id, consultant_id, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('sisiiss', $template_name, $document_type_id, $content, $is_active, $organization_id, $consultant_id, $consultant_id);
        
        if ($stmt->execute()) {
            $success_message = "Document template added successfully";
            $stmt->close();
            header("Location: documents.php?success=3");
            exit;
        } else {
            $error_message = "Error adding document template: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle generated document form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_document'])) {
    $name = trim($_POST['document_name']);
    $document_type_id = $_POST['document_type_id'];
    $template_id = $_POST['template_id'];
    $client_id = $_POST['client_id'];
    $application_id = !empty($_POST['application_id']) ? $_POST['application_id'] : null;
    $booking_id = !empty($_POST['booking_id']) ? $_POST['booking_id'] : null;
    $created_by = $_SESSION['user_id']; 
    $organization_id = isset($_SESSION['organization_id']) ? $_SESSION['organization_id'] : 0;
    $consultant_id = $_SESSION['user_id']; 
    
    // Validate inputs
    $errors = [];
    if (empty($name)) {
        $errors[] = "Document name is required";
    }
    if (empty($document_type_id)) {
        $errors[] = "Document type is required";
    }
    if (empty($template_id)) {
        $errors[] = "Template is required";
    }
    if (empty($client_id)) {
        $errors[] = "Client is required";
    }
    
    if (empty($errors)) {
        // Generate filename
        $filename = 'doc_' . time() . '_' . $client_id . '.pdf';
        $file_path = 'uploads/documents/' . $filename;
        
        // In a real implementation, you would generate the actual document here
        // For now, we'll just insert the record
        
        // Insert new generated document
        $insert_query = "INSERT INTO generated_documents (name, document_type_id, template_id, client_id, file_path, created_by, generated_date, organization_id, application_id, booking_id, consultant_id) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('siiisisiii', $name, $document_type_id, $template_id, $client_id, $file_path, $created_by, $organization_id, $application_id, $booking_id, $consultant_id);
        
        if ($stmt->execute()) {
            $success_message = "Document generated successfully";
            $stmt->close();
            header("Location: documents.php?success=4");
            exit;
        } else {
            $error_message = "Error generating document: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle document category deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $category_id = $_POST['category_id'];
    
    // Check if category is in use
    $check_query = "SELECT id FROM document_types WHERE category_id = ? LIMIT 1";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $category_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_message = "Cannot delete category as it is currently in use by document types";
    } else {
        // Delete category
        $delete_query = "DELETE FROM document_categories WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param('i', $category_id);
        
        if ($stmt->execute()) {
            $success_message = "Document category deleted successfully";
            $stmt->close();
            header("Location: documents.php?success=5");
            exit;
        } else {
            $error_message = "Error deleting document category: " . $conn->error;
            $stmt->close();
        }
    }
    $check_stmt->close();
}

// Handle document type deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document_type'])) {
    $document_type_id = $_POST['document_type_id'];
    
    // Check if document type is in use by templates
    $check_query = "SELECT id FROM document_templates WHERE document_type_id = ? LIMIT 1";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $document_type_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_message = "Cannot delete document type as it is currently in use by templates";
    } else {
        // Delete document type
        $delete_query = "DELETE FROM document_types WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param('i', $document_type_id);
        
        if ($stmt->execute()) {
            $success_message = "Document type deleted successfully";
            $stmt->close();
            header("Location: documents.php?success=6");
            exit;
        } else {
            $error_message = "Error deleting document type: " . $conn->error;
            $stmt->close();
        }
    }
    $check_stmt->close();
}

// Get all generated documents for this organization
$generated_documents_query = "SELECT gd.id, gd.name as document_name, gd.file_path, gd.generated_date, 
                                   gd.application_id, gd.booking_id,
                                   dt.name AS type_name, 
                                   dtpl.name AS template_name,
                                   CONCAT(c.first_name, ' ', c.last_name) AS client_name,
                                   CONCAT(u.first_name, ' ', u.last_name) AS generated_by,
                                   a.reference_number AS application_reference,
                                   b.reference_number AS booking_reference
                            FROM generated_documents gd
                            JOIN document_types dt ON gd.document_type_id = dt.id
                            JOIN document_templates dtpl ON gd.template_id = dtpl.id
                            JOIN users c ON gd.client_id = c.id
                            JOIN users u ON gd.created_by = u.id
                            LEFT JOIN applications a ON gd.application_id = a.id
                            LEFT JOIN bookings b ON gd.booking_id = b.id
                            WHERE gd.organization_id = ?
                            ORDER BY gd.generated_date DESC";
$generated_documents_stmt = $conn->prepare($generated_documents_query);
$generated_documents_stmt->bind_param('i', $organization_id);
$generated_documents_stmt->execute();
$generated_documents_result = $generated_documents_stmt->get_result();
$generated_documents = [];

if ($generated_documents_result && $generated_documents_result->num_rows > 0) {
    while ($row = $generated_documents_result->fetch_assoc()) {
        $generated_documents[] = $row;
    }
}
$generated_documents_stmt->close();

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Document category added successfully";
            break;
        case 2:
            $success_message = "Document type added successfully";
            break;
        case 3:
            $success_message = "Document template added successfully";
            break;
        case 4:
            $success_message = "Document generated successfully";
            break;
        case 5:
            $success_message = "Document category deleted successfully";
            break;
        case 6:
            $success_message = "Document type deleted successfully";
            break;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Document Management</h1>
            <p>Manage document categories, types, templates and generate documents</p>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Tab Navigation -->
    <div class="tabs-container">
        <div class="tabs">
            <button class="tab-btn active" data-tab="generated-documents">Generated Documents</button>
            <button class="tab-btn" data-tab="templates">Document Templates</button>
            <button class="tab-btn" data-tab="document-types">Document Types</button>
            <button class="tab-btn" data-tab="categories">Categories</button>
        </div>
        
        <!-- Generated Documents Tab -->
        <div class="tab-content active" id="generated-documents-tab">
            <div class="tab-header">
                <h2>Generated Documents</h2>
                <button type="button" class="btn primary-btn" id="generateDocumentBtn">
                    <i class="fas fa-plus"></i> Generate Document
                </button>
            </div>
            
            <div class="tab-body">
                <?php if (empty($generated_documents)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <p>No documents generated yet. Generate a document to get started!</p>
                    </div>
                <?php else: ?>
                    <div class="documents-container">
                        <div class="documents-list">
                            <div class="filter-controls">
                                <div class="filter-row">
                                    <div class="filter-group">
                                        <label for="filter-type">Filter by Type:</label>
                                        <select id="filter-type" class="form-control">
                                            <option value="">All Types</option>
                                            <?php 
                                            $unique_types = [];
                                            foreach ($generated_documents as $doc) {
                                                if (!in_array($doc['type_name'], $unique_types)) {
                                                    $unique_types[] = $doc['type_name'];
                                                    echo '<option value="' . htmlspecialchars($doc['type_name']) . '">' . 
                                                        htmlspecialchars($doc['type_name']) . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label for="filter-client">Filter by Client:</label>
                                        <select id="filter-client" class="form-control">
                                            <option value="">All Clients</option>
                                            <?php 
                                            $unique_clients = [];
                                            foreach ($generated_documents as $doc) {
                                                if (!in_array($doc['client_name'], $unique_clients)) {
                                                    $unique_clients[] = $doc['client_name'];
                                                    echo '<option value="' . htmlspecialchars($doc['client_name']) . '">' . 
                                                        htmlspecialchars($doc['client_name']) . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <button id="reset-filters" class="btn btn-secondary">Reset Filters</button>
                                    </div>
                                </div>
                            </div>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Document Name</th>
                                        <th>Document Type</th>
                                        <th>Template</th>
                                        <th>Client</th>
                                        <th>Generated Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($generated_documents as $document): ?>
                                        <tr class="document-row" data-id="<?php echo $document['id']; ?>">
                                            <td><?php echo htmlspecialchars($document['document_name']); ?></td>
                                            <td><?php echo htmlspecialchars($document['type_name']); ?></td>
                                            <td><?php echo htmlspecialchars($document['template_name']); ?></td>
                                            <td><?php echo htmlspecialchars($document['client_name']); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($document['generated_date'])); ?></td>
                                            <td class="actions-cell">
                                        <a href="<?php echo htmlspecialchars($document['file_path']); ?>"
                                            class="btn-action btn-view" title="View Document" target="_blank">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button type="button" class="btn-action btn-details" title="View Details" 
                                                        onclick="showDocumentDetails(<?php echo htmlspecialchars(json_encode($document)); ?>)">
                                                    <i class="fas fa-info-circle"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="document-details-container" id="document-details-container">
                            <!-- Document details will be loaded here via JavaScript -->
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Document Templates Tab -->
        <div class="tab-content" id="templates-tab">
            <div class="tab-header">
                <h2>Document Templates</h2>
                <button type="button" class="btn primary-btn" id="addTemplateBtn">
                    <i class="fas fa-plus"></i> Add Template
                </button>
            </div>
            
            <div class="tab-body">
                <?php if (empty($templates)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-code"></i>
                        <p>No document templates yet. Add a template to get started!</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Template Name</th>
                                <th>Document Type</th>
                                <th>Created By</th>
                                <th>Created Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $template): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($template['name']); ?></td>
                                    <td><?php echo htmlspecialchars($template['document_type_name']); ?></td>
                                    <td><?php echo htmlspecialchars($template['created_by_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($template['created_at'])); ?></td>
                                    <td>
                                        <?php if ($template['is_active']): ?>
                                            <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                                        <?php else: ?>
                                            <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                <a href="edit_template.php?id=<?php echo $template['id']; ?>"
                                    class="btn-action btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                <a href="view_template.php?id=<?php echo $template['id']; ?>"
                                    class="btn-action btn-view" title="View Template">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Document Types Tab -->
        <div class="tab-content" id="document-types-tab">
            <div class="tab-header">
                <h2>Document Types</h2>
                <button type="button" class="btn primary-btn" id="addDocumentTypeBtn">
                    <i class="fas fa-plus"></i> Add Document Type
                </button>
            </div>
            
            <div class="tab-body">
                <?php if (empty($document_types)): ?>
                    <div class="empty-state">
                        <i class="fas fa-list-alt"></i>
                        <p>No document types yet. Add a document type to get started!</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Document Type</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($document_types as $type): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($type['name']); ?></td>
                                    <td><?php echo htmlspecialchars($type['category_name']); ?></td>
                                    <td>
                                        <?php 
                                            echo !empty($type['description']) 
                                                ? htmlspecialchars(substr($type['description'], 0, 100)) . (strlen($type['description']) > 100 ? '...' : '') 
                                                : '-'; 
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($type['is_active']): ?>
                                            <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                                        <?php else: ?>
                                            <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <button type="button" class="btn-action btn-edit" 
                                                onclick="editDocumentType(<?php echo $type['id']; ?>, '<?php echo addslashes($type['name']); ?>', '<?php echo addslashes($type['description']); ?>', <?php echo $type['category_id']; ?>, <?php echo $type['is_active']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn-action btn-deactivate" 
                                                onclick="confirmDeleteDocumentType(<?php echo $type['id']; ?>, '<?php echo addslashes($type['name']); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Categories Tab -->
        <div class="tab-content" id="categories-tab">
            <div class="tab-header">
                <h2>Document Categories</h2>
                <button type="button" class="btn primary-btn" id="addCategoryBtn">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>
            
            <div class="tab-body">
                <?php if (empty($categories)): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder"></i>
                        <p>No document categories yet. Add a category to get started!</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td>
                                        <?php 
                                            echo !empty($category['description']) 
                                                ? htmlspecialchars(substr($category['description'], 0, 100)) . (strlen($category['description']) > 100 ? '...' : '') 
                                                : '-'; 
                                        ?>
                                    </td>
                                    <td class="actions-cell">
                                        <button type="button" class="btn-action btn-edit" 
                                                onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>', '<?php echo addslashes($category['description']); ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn-action btn-deactivate" 
                                                onclick="confirmDeleteCategory(<?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal" id="addCategoryModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Document Category</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="documents.php" method="POST" id="addCategoryForm">
                    <div class="form-group">
                        <label for="name">Category Name*</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                    </div>
                    <input type="hidden" name="category_id" id="category_id" value="">
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_category" class="btn submit-btn">Save Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Document Type Modal -->
<div class="modal" id="addDocumentTypeModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Document Type</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="documents.php" method="POST" id="addDocumentTypeForm">
                    <div class="form-group">
                        <label for="category_id">Category*</label>
                        <select name="category_id" id="category_id" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="type_name">Type Name*</label>
                        <input type="text" name="type_name" id="type_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="type_description">Description</label>
                        <textarea name="type_description" id="type_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group checkbox-group">
                        <input type="checkbox" name="type_is_active" id="type_is_active" checked>
                        <label for="type_is_active">Active</label>
                    </div>
                    <input type="hidden" name="document_type_id" id="edit_document_type_id" value="">
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_document_type" class="btn submit-btn">Save Document Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Template Modal -->
<div class="modal" id="addTemplateModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Document Template</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="documents.php" method="POST" id="addTemplateForm">
                    <div class="form-group">
                        <label for="template_name">Template Name*</label>
                        <input type="text" name="template_name" id="template_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="document_type_id">Document Type*</label>
                        <select name="document_type_id" id="document_type_id" class="form-control" required>
                            <option value="">Select Document Type</option>
                            <?php foreach ($document_types as $type): ?>
                                <?php if ($type['is_active']): ?>
                                    <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="content">Template Content*</label>
                        <div class="editor-toolbar">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="formatText('bold')"><i class="fas fa-bold"></i></button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="formatText('italic')"><i class="fas fa-italic"></i></button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="formatText('underline')"><i class="fas fa-underline"></i></button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="formatText('heading')"><i class="fas fa-heading"></i></button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="insertVariable()"><i class="fas fa-code"></i> Insert Variable</button>
                        </div>
                        <textarea name="content" id="content" class="form-control editor" rows="15" required></textarea>
                    </div>
                    <div class="form-group checkbox-group">
                        <input type="checkbox" name="template_is_active" id="template_is_active" checked>
                        <label for="template_is_active">Active</label>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_template" class="btn submit-btn">Save Template</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Generate Document Modal -->
<div class="modal" id="generateDocumentModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Generate Document</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="documents.php" method="POST" id="generateDocumentForm">
                    <div class="form-group">
                        <label for="document_name">Document Name*</label>
                        <input type="text" name="document_name" id="document_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="document_type_id">Document Type*</label>
                        <select name="document_type_id" id="gen_document_type_id" class="form-control" required>
                            <option value="">Select Document Type</option>
                            <?php foreach ($document_types as $type): ?>
                                <?php if ($type['is_active']): ?>
                            <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?>
                            </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="template_id">Template*</label>
                        <select name="template_id" id="template_id" class="form-control" required disabled>
                            <option value="">Select Template</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="client_id">Client*</label>
                        <select name="client_id" id="client_id" class="form-control" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>">
                                <?php echo htmlspecialchars($client['full_name']); ?>
                                (<?php echo htmlspecialchars($client['email']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="application_id">Related Application (Optional)</label>
                        <select name="application_id" id="application_id" class="form-control">
                            <option value="">None</option>
                            <?php
                            // Get active applications for this organization
                            $applications_query = "SELECT a.id, a.reference_number, CONCAT(u.first_name, ' ', u.last_name) AS applicant_name 
                                                 FROM applications a 
                                                 JOIN users u ON a.user_id = u.id 
                                                 WHERE a.organization_id = ? 
                                                 AND a.deleted_at IS NULL
                                                 ORDER BY a.created_at DESC
                                                 LIMIT 50";
                            $applications_stmt = $conn->prepare($applications_query);
                            $applications_stmt->bind_param('i', $organization_id);
                            $applications_stmt->execute();
                            $applications_result = $applications_stmt->get_result();
                            
                            if ($applications_result && $applications_result->num_rows > 0) {
                                while ($application = $applications_result->fetch_assoc()) {
                                    echo '<option value="' . $application['id'] . '">' . 
                                        htmlspecialchars($application['reference_number'] . ' - ' . $application['applicant_name']) . 
                                        '</option>';
                                }
                            }
                            $applications_stmt->close();
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="booking_id">Related Booking (Optional)</label>
                        <select name="booking_id" id="booking_id" class="form-control">
                            <option value="">None</option>
                            <?php
                            // Get active bookings for this organization
                            $bookings_query = "SELECT b.id, b.reference_number, CONCAT(u.first_name, ' ', u.last_name) AS client_name 
                                             FROM bookings b 
                                             JOIN users u ON b.user_id = u.id 
                                             WHERE b.organization_id = ? 
                                             AND b.deleted_at IS NULL
                                             ORDER BY b.booking_datetime DESC
                                             LIMIT 50";
                            $bookings_stmt = $conn->prepare($bookings_query);
                            $bookings_stmt->bind_param('i', $organization_id);
                            $bookings_stmt->execute();
                            $bookings_result = $bookings_stmt->get_result();
                            
                            if ($bookings_result && $bookings_result->num_rows > 0) {
                                while ($booking = $bookings_result->fetch_assoc()) {
                                    echo '<option value="' . $booking['id'] . '">' . 
                                        htmlspecialchars($booking['reference_number'] . ' - ' . $booking['client_name']) . 
                                        '</option>';
                                }
                            }
                            $bookings_stmt->close();
                            ?>
                        </select>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="generate_document" class="btn submit-btn">Generate Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Hidden forms for actions -->
<form id="deleteCategoryForm" action="documents.php" method="POST" style="display: none;">
    <input type="hidden" name="category_id" id="delete_category_id">
    <input type="hidden" name="delete_category" value="1">
</form>

<form id="deleteDocumentTypeForm" action="documents.php" method="POST" style="display: none;">
    <input type="hidden" name="document_type_id" id="delete_document_type_id">
    <input type="hidden" name="delete_document_type" value="1">
</form>

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

.primary-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

.primary-btn:hover {
    background-color: #031c56;
}

.tabs-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.tabs {
    display: flex;
    border-bottom: 1px solid var(--border-color);
}

.tab-btn {
    padding: 12px 20px;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--secondary-color);
    font-weight: 500;
    position: relative;
}

.tab-btn:hover {
    color: var(--primary-color);
}

.tab-btn.active {
    color: var(--primary-color);
}

.tab-btn.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 100%;
    height: 3px;
    background-color: var(--primary-color);
}

.tab-content {
    display: none;
    padding: 20px;
}

.tab-content.active {
    display: block;
}

.tab-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.tab-header h2 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.4rem;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    text-align: left;
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
}

.data-table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--dark-color);
}

.data-table tbody tr:hover {
    background-color: rgba(4, 33, 103, 0.03);
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.active {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge.inactive {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.status-badge i {
    font-size: 8px;
}

.actions-cell {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    font-size: 14px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    color: white;
    transition: background-color 0.2s;
}

.btn-view {
    background-color: var(--primary-color);
}

.btn-view:hover {
    background-color: #031c56;
}

.btn-edit {
    background-color: var(--warning-color);
}

.btn-edit:hover {
    background-color: #e0b137;
}

.btn-download {
    background-color: var(--success-color);
}

.btn-download:hover {
    background-color: #19b67f;
}

.btn-email {
    background-color: var(--secondary-color);
}

.btn-email:hover {
    background-color: #707483;
}

.btn-deactivate {
    background-color: var(--danger-color);
}

.btn-deactivate:hover {
    background-color: #d44235;
}

.empty-state {
    text-align: center;
    padding: 50px 20px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.alert {
    padding: 12px 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
    border: 1px solid rgba(231, 74, 59, 0.2);
}

.alert-success {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
    border: 1px solid rgba(28, 200, 138, 0.2);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    overflow: auto;
}

.modal-dialog {
    margin: 80px auto;
    max-width: 500px;
}

.modal-dialog.modal-lg {
    max-width: 700px;
}

.modal-content {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
}

.modal-title {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.4rem;
}

.close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--secondary-color);
}

.modal-body {
    padding: 20px;
}

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    flex: 1;
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
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(4, 33, 103, 0.1);
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.checkbox-group input[type="checkbox"] {
    margin: 0;
}

.form-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.cancel-btn {
    background-color: white;
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
}

.submit-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
}

.submit-btn:hover {
    background-color: #031c56;
}

/* AI Template Generator styles */
.ai-generator-controls {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.ai-btn {
    background-color: #4e73df;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    transition: background-color 0.2s;
    display: flex;
    align-items: center;
    gap: 5px;
}

.ai-btn:hover {
    background-color: #375ad3;
}

.ai-btn i {
    font-size: 14px;
}

.ai-status {
    margin-left: 10px;
    font-size: 14px;
    color: var(--secondary-color);
    display: none;
}

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .tabs {
        overflow-x: auto;
    }
    
    .data-table {
        display: block;
        overflow-x: auto;
    }
    
    .modal-dialog {
        margin: 60px 15px;
    }
}

.tabs-container {
    margin-bottom: 20px;
}

.tab-links {
    display: flex;
    border-bottom: 1px solid #ddd;
}

.tab-link {
    padding: 10px 15px;
    cursor: pointer;
    border: 1px solid transparent;
    border-bottom: none;
    margin-bottom: -1px;
    background-color: #f8f9fa;
}

.tab-link.active {
    background-color: #fff;
    border-color: #ddd;
    border-bottom-color: #fff;
}

.tab-content {
    display: none;
    padding: 20px;
    border: 1px solid #ddd;
    border-top: none;
}

.tab-content.active {
    display: block;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-control {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.btn {
    padding: 8px 15px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.btn-primary {
    background-color: #007bff;
    color: #fff;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #888;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 10px;
}

.documents-container {
    display: flex;
    gap: 20px;
}

.documents-list {
    flex: 2;
}

.document-details-container {
    flex: 1;
    min-width: 300px;
    border-left: 1px solid #ddd;
    padding-left: 20px;
}

.document-details {
    background-color: #f9f9f9;
    border-radius: 5px;
    padding: 15px;
}

.document-details h3 {
    margin-top: 0;
    color: #333;
}

.document-actions {
    margin-top: 15px;
    display: flex;
    gap: 10px;
}

.view-btn {
    background-color: #28a745;
    color: white;
}

.delete-btn {
    background-color: #dc3545;
    color: white;
}

.document-row {
    cursor: pointer;
}

.document-row.selected {
    background-color: #e6f7ff;
}

.btn-action {
    background: none;
    border: none;
    color: #007bff;
    cursor: pointer;
    font-size: 16px;
    margin-right: 5px;
}

.btn-view {
    color: #28a745;
}

.btn-details {
    color: #17a2b8;
}

.filter-controls {
    margin-bottom: 20px;
    background-color: #f9f9f9;
    padding: 15px;
    border-radius: 5px;
}

.filter-row {
    display: flex;
    gap: 15px;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.editor-toolbar {
    margin-bottom: 10px;
    padding: 5px;
    background-color: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.editor-toolbar .btn {
    margin-right: 5px;
}

.editor {
    font-family: 'Courier New', Courier, monospace;
    line-height: 1.5;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    resize: vertical;
}

.editor:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(4, 33, 103, 0.1);
}
</style>

<script>
// Tab functionality
document.querySelectorAll('.tab-btn').forEach(function(tab) {
    tab.addEventListener('click', function() {
        // Remove active class from all tabs
        document.querySelectorAll('.tab-btn').forEach(function(t) {
            t.classList.remove('active');
        });
        
        // Add active class to clicked tab
        this.classList.add('active');
        
        // Hide all tab content
        document.querySelectorAll('.tab-content').forEach(function(content) {
            content.classList.remove('active');
        });
        
        // Show corresponding tab content
        const tabId = this.getAttribute('data-tab');
        document.getElementById(tabId + '-tab').classList.add('active');
    });
});

// Modal functionality
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modals when close button is clicked
document.querySelectorAll('[data-dismiss="modal"]').forEach(function(element) {
    element.addEventListener('click', function() {
        const modal = this.closest('.modal');
        if (modal) {
            modal.style.display = 'none';
        }
    });
});

// Close modal when clicking outside of it
window.addEventListener('click', function(event) {
    document.querySelectorAll('.modal').forEach(function(modal) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});

// Open modals when buttons are clicked
document.getElementById('addCategoryBtn').addEventListener('click', function() {
    // Reset form
    document.getElementById('addCategoryForm').reset();
    document.getElementById('category_id').value = '';
    document.querySelector('#addCategoryModal .modal-title').textContent = 'Add Document Category';
    document.querySelector('#addCategoryForm button[type="submit"]').textContent = 'Save Category';
    openModal('addCategoryModal');
});

document.getElementById('addDocumentTypeBtn').addEventListener('click', function() {
    // Reset form
    document.getElementById('addDocumentTypeForm').reset();
    document.getElementById('edit_document_type_id').value = '';
    document.querySelector('#addDocumentTypeModal .modal-title').textContent = 'Add Document Type';
    document.querySelector('#addDocumentTypeForm button[type="submit"]').textContent = 'Save Document Type';
    openModal('addDocumentTypeModal');
});

document.getElementById('addTemplateBtn').addEventListener('click', function() {
    // Reset form
    document.getElementById('addTemplateForm').reset();
    openModal('addTemplateModal');
});

document.getElementById('generateDocumentBtn').addEventListener('click', function() {
    // Reset form
    document.getElementById('generateDocumentForm').reset();
    // Reset template dropdown
    const templateSelect = document.getElementById('template_id');
    templateSelect.innerHTML = '<option value="">Select Template</option>';
    templateSelect.disabled = true;
    openModal('generateDocumentModal');
});

// Function to edit category
function editCategory(id, name, description) {
    document.getElementById('category_id').value = id;
    document.getElementById('name').value = name;
    document.getElementById('description').value = description;
    
    document.querySelector('#addCategoryModal .modal-title').textContent = 'Edit Document Category';
    document.querySelector('#addCategoryForm button[type="submit"]').textContent = 'Update Category';
    
    openModal('addCategoryModal');
}

// Function to edit document type
function editDocumentType(id, name, description, categoryId, isActive) {
    document.getElementById('edit_document_type_id').value = id;
    document.getElementById('type_name').value = name;
    document.getElementById('type_description').value = description;
    document.getElementById('category_id').value = categoryId;
    document.getElementById('type_is_active').checked = isActive === 1;
    
    document.querySelector('#addDocumentTypeModal .modal-title').textContent = 'Edit Document Type';
    document.querySelector('#addDocumentTypeForm button[type="submit"]').textContent = 'Update Document Type';
    
    
    openModal('addDocumentTypeModal');
}

// Function to view template
function viewTemplate(id) {
    // Redirect to template viewer page
    window.location.href = 'view_template.php?id=' + id;
}

// Function to send document email
function sendDocumentEmail(id) {
    if (confirm('Are you sure you want to send this document via email?')) {
        window.location.href = 'send_document_email.php?id=' + id;
    }
}

// Function to confirm category deletion
function confirmDeleteCategory(id, name) {
    if (confirm('Are you sure you want to delete the category "' + name + '"? This cannot be undone.')) {
        document.getElementById('delete_category_id').value = id;
        document.getElementById('deleteCategoryForm').submit();
    }
}

// Function to confirm document type deletion
function confirmDeleteDocumentType(id, name) {
    if (confirm('Are you sure you want to delete the document type "' + name + '"? This cannot be undone.')) {
        document.getElementById('delete_document_type_id').value = id;
        document.getElementById('deleteDocumentTypeForm').submit();
    }
}

// Load templates based on document type selection
document.getElementById('gen_document_type_id').addEventListener('change', function() {
    const documentTypeId = this.value;
    const templateSelect = document.getElementById('template_id');
    
    if (documentTypeId) {
        // Enable the template select
        templateSelect.disabled = false;
        
        // Use AJAX to fetch templates for the selected document type
        fetch('ajax/get_templates.php?document_type_id=' + documentTypeId)
            .then(response => response.json())
            .then(data => {
                templateSelect.innerHTML = '<option value="">Select Template</option>';
                
                if (data.length > 0) {
                    data.forEach(function(template) {
                        const option = document.createElement('option');
                        option.value = template.id;
                        option.textContent = template.name + (template.is_organization_specific ? ' (Organization)' : ' (Global)');
                        templateSelect.appendChild(option);
                    });
                } else {
                    templateSelect.innerHTML = '<option value="">No templates found</option>';
                }
            })
            .catch(error => {
                console.error('Error fetching templates:', error);
                templateSelect.innerHTML = '<option value="">Error loading templates</option>';
            });
    } else {
        // Reset and disable the template select
        templateSelect.innerHTML = '<option value="">Select Template</option>';
        templateSelect.disabled = true;
    }
});

// Editor functions
function formatText(command) {
    const editor = document.getElementById('content');
    const start = editor.selectionStart;
    const end = editor.selectionEnd;
    const selectedText = editor.value.substring(start, end);
    let formattedText = '';

    switch(command) {
        case 'bold':
            formattedText = `**${selectedText}**`;
            break;
        case 'italic':
            formattedText = `*${selectedText}*`;
            break;
        case 'underline':
            formattedText = `__${selectedText}__`;
            break;
        case 'heading':
            formattedText = `# ${selectedText}`;
            break;
    }

    editor.value = editor.value.substring(0, start) + formattedText + editor.value.substring(end);
    editor.focus();
    editor.setSelectionRange(start + formattedText.length, start + formattedText.length);
}

function insertVariable() {
    const variables = [
        { name: 'client_name', description: 'Client Name' },
        { name: 'client_email', description: 'Client Email' },
        { name: 'client_phone', description: 'Client Phone' },
        { name: 'consultant_name', description: 'Consultant Name' },
        { name: 'consultant_email', description: 'Consultant Email' },
        { name: 'application_reference', description: 'Application Reference' },
        { name: 'booking_reference', description: 'Booking Reference' },
        { name: 'current_date', description: 'Current Date' }
    ];

    const select = document.createElement('select');
    select.className = 'form-control';
    select.style.width = '200px';
    select.style.display = 'inline-block';
    select.style.marginLeft = '10px';

    variables.forEach(variable => {
        const option = document.createElement('option');
        option.value = `{${variable.name}}`;
        option.textContent = variable.description;
        select.appendChild(option);
    });

    const editor = document.getElementById('content');
    const start = editor.selectionStart;
    const variable = select.value;
    
    editor.value = editor.value.substring(0, start) + variable + editor.value.substring(editor.selectionEnd);
    editor.focus();
    editor.setSelectionRange(start + variable.length, start + variable.length);
}

$(document).ready(function() {
    // Initialize tabs
    $('.tabs-container .tab-link').click(function() {
        var tabId = $(this).attr('data-tab');
        
        $('.tabs-container .tab-link').removeClass('active');
        $('.tab-content').removeClass('active');
        
        $(this).addClass('active');
        $("#" + tabId).addClass('active');
    });
    
    // Show the first tab by default
    $('.tabs-container .tab-link:first-child').click();
    
    // Add click handler for document rows
    $(document).on('click', '.document-row', function() {
        var documentId = $(this).data('id');
        var document = null;
        
        // Find the document data
        <?php foreach ($generated_documents as $index => $document): ?>
        if (documentId == <?php echo $document['id']; ?>) {
            document = <?php echo json_encode($document); ?>;
        }
        <?php endforeach; ?>
        
        if (document) {
            showDocumentDetails(document);
        }
    });
    
    // Show first document details by default if available
    <?php if (!empty($generated_documents)): ?>
    showDocumentDetails(<?php echo json_encode($generated_documents[0]); ?>);
    <?php endif; ?>

    // Filter functionality
    $('#filter-type, #filter-client').on('change', function() {
        filterDocuments();
    });
    
    $('#reset-filters').on('click', function() {
        $('#filter-type, #filter-client').val('');
        filterDocuments();
    });
    
    function filterDocuments() {
        var typeFilter = $('#filter-type').val().toLowerCase();
        var clientFilter = $('#filter-client').val().toLowerCase();
        
        $('.document-row').each(function() {
            var row = $(this);
            var typeText = row.find('td:nth-child(2)').text().toLowerCase();
            var clientText = row.find('td:nth-child(4)').text().toLowerCase();
            
            var typeMatch = typeFilter === '' || typeText.includes(typeFilter);
            var clientMatch = clientFilter === '' || clientText.includes(clientFilter);
            
            if (typeMatch && clientMatch) {
                row.show();
            } else {
                row.hide();
            }
        });
        
        // Show first visible document details
        if ($('.document-row:visible').length > 0) {
            $('.document-row:visible:first').click();
        } else {
            $('#document-details-container').empty();
        }
    }

    // Delete document button handler
    $(document).on('click', '.delete-btn', function() {
        if (confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
            var documentId = $(this).data('id');
            
            $.ajax({
                url: 'ajax/delete_document.php',
                type: 'POST',
                data: {
                    document_id: documentId
                },
                success: function(response) {
                    try {
                        var result = JSON.parse(response);
                        if (result.success) {
                            // Remove the document row from the table
                            $('.document-row[data-id="' + documentId + '"]').remove();
                            
                            // Clear the document details container
                            $('#document-details-container').empty();
                            
                            // Show success message
                            alert('Document deleted successfully.');
                            
                            // If no documents left, reload the page to show empty state
                            if ($('.document-row').length === 0) {
                                location.reload();
                            } else {
                                // Show the first document details
                                $('.document-row:first').click();
                            }
                        } else {
                            alert('Error: ' + result.message);
                        }
                    } catch (e) {
                        alert('An error occurred while deleting the document.');
                    }
                },
                error: function() {
                    alert('An error occurred while deleting the document.');
                }
            });
        }
    });
});

// Function to show document details
function showDocumentDetails(document) {
    var detailsContainer = $('#document-details-container');
    var detailsHtml = `
        <div class="document-details">
            <h3>${escapeHtml(document.document_name)}</h3>
            <p><strong>Template:</strong> ${escapeHtml(document.template_name)}</p>
            <p><strong>Type:</strong> ${escapeHtml(document.type_name)}</p>
            <p><strong>Client:</strong> ${escapeHtml(document.client_name)}</p>`;
            
    if (document.application_id && document.application_reference) {
        detailsHtml += `<p><strong>Application:</strong> ${escapeHtml(document.application_reference)}</p>`;
    }
    
    if (document.booking_id && document.booking_reference) {
        detailsHtml += `<p><strong>Booking:</strong> ${escapeHtml(document.booking_reference)}</p>`;
    }
    
    detailsHtml += `
            <p><strong>Generated by:</strong> ${escapeHtml(document.generated_by)}</p>
            <p><strong>Date:</strong> ${formatDate(document.generated_date)}</p>
            <div class="document-actions">
                <a href="${escapeHtml(document.file_path)}" class="btn view-btn" target="_blank">View Document</a>
                <button type="button" class="btn delete-btn" data-id="${document.id}">Delete</button>
            </div>
        </div>
    `;
    
    detailsContainer.html(detailsHtml);
    detailsContainer.show();
    
    // Highlight the selected document row
    $('.document-row').removeClass('selected');
    $(`.document-row[data-id="${document.id}"]`).addClass('selected');
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Helper function to format date
function formatDate(dateString) {
    var date = new Date(dateString);
    return date.toLocaleDateString('en-GB', { 
        day: '2-digit', 
        month: 'short', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>