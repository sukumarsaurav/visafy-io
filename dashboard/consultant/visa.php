<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Visa Management";
$page_specific_css = "assets/css/visa.css";
require_once 'includes/header.php';

// Get the consultant ID and organization ID from the session
$consultant_id = $_SESSION["id"];
$organization_id = isset($user['organization_id']) ? $user['organization_id'] : null;

// Verify organization ID is set
if (!$organization_id) {
    die("Organization ID not set. Please log in again.");
}

// Get organization details
$org_query = "SELECT o.*, c.company_name 
              FROM organizations o 
              LEFT JOIN consultants c ON c.user_id = ?
              WHERE o.id = ?";
$org_stmt = $conn->prepare($org_query);
$org_stmt->bind_param("ii", $consultant_id, $organization_id);
$org_stmt->execute();
$org_result = $org_stmt->get_result();
$organization = $org_result->fetch_assoc();
$org_stmt->close();

// Get all countries - Using prepared statement
$query = "SELECT country_id, country_name, country_code, is_active, inactive_reason, inactive_since, is_global 
          FROM countries 
          WHERE (is_global = 1 OR organization_id = ?)
          ORDER BY country_name ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $organization_id);
$stmt->execute();
$result = $stmt->get_result();
$countries = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $countries[] = $row;
    }
}
$stmt->close();

// Get visas for each country
$visas = [];
if (!empty($countries)) {
    $country_ids = array_column($countries, 'country_id');
    $country_ids_str = implode(',', $country_ids);
    
    $visa_query = "SELECT v.visa_id, v.country_id, v.visa_type, v.description, v.validity_period, 
                   v.fee, v.requirements, v.is_active, v.inactive_reason, v.inactive_since, 
                   v.is_global, v.organization_id, c.country_name 
                   FROM visas v 
                   JOIN countries c ON v.country_id = c.country_id 
                   WHERE v.country_id IN ($country_ids_str) 
                   AND (v.is_global = 1 OR v.organization_id = ?)
                   ORDER BY c.country_name, v.visa_type";
    
    $stmt = $conn->prepare($visa_query);
    $stmt->bind_param('i', $organization_id);
    $stmt->execute();
    $visa_result = $stmt->get_result();
    
    if ($visa_result && $visa_result->num_rows > 0) {
        while ($row = $visa_result->fetch_assoc()) {
            if (!isset($visas[$row['country_id']])) {
                $visas[$row['country_id']] = [];
            }
            $visas[$row['country_id']][] = $row;
        }
    }
    $stmt->close();
}

// Handle country creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_country'])) {
    $country_name = trim($_POST['country_name']);
    $country_code = trim($_POST['country_code']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    if (empty($country_name)) {
        $errors[] = "Country name is required";
    }
    if (empty($country_code)) {
        $errors[] = "Country code is required";
    } elseif (strlen($country_code) !== 3) {
        $errors[] = "Country code must be exactly 3 characters";
    }
    
    // Check if country code already exists for this organization
    $check_query = "SELECT country_id FROM countries WHERE country_code = ? AND (organization_id = ? OR is_global = 1)";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('si', $country_code, $organization_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $errors[] = "Country code already exists";
    }
    $check_stmt->close();
    
    if (empty($errors)) {
        // Insert new country - always organization specific
        $insert_query = "INSERT INTO countries (country_name, country_code, is_active, organization_id, is_global) 
                        VALUES (?, ?, ?, ?, 0)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('ssii', $country_name, $country_code, $is_active, $organization_id);
        
        if ($stmt->execute()) {
            $success_message = "Country added successfully";
            $stmt->close();
            header("Location: visa.php?success=1");
            exit;
        } else {
            $error_message = "Error adding country: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle country edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_country'])) {
    $country_id = $_POST['edit_country_id'];
    $country_name = trim($_POST['country_name']);
    $country_code = trim($_POST['country_code']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    if (empty($country_name)) {
        $errors[] = "Country name is required";
    }
    if (empty($country_code)) {
        $errors[] = "Country code is required";
    } elseif (strlen($country_code) !== 3) {
        $errors[] = "Country code must be exactly 3 characters";
    }
    
    // Check if country code already exists for other organizations
    $check_query = "SELECT country_id FROM countries WHERE country_code = ? AND country_id !=?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('si', $country_code, $country_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $errors[] = "Country code already exists";
    }
    $check_stmt->close();
    
    if (empty($errors)) {
        // Update country
        $update_query = "UPDATE countries SET country_name = ?, country_code = ?, is_active = ? WHERE country_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ssii', $country_name, $country_code, $is_active, $country_id);
        
        if ($stmt->execute()) {
            $success_message = "Country updated successfully";
            $stmt->close();
            header("Location: visa.php?success=5");
            exit;
        } else {
            $error_message = "Error updating country: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle visa creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_visa'])) {
    $country_id = $_POST['country_id'];
    $visa_type = trim($_POST['visa_type']);
    $description = trim($_POST['description']);
    $validity_period = !empty($_POST['validity_period']) ? intval($_POST['validity_period']) : null;
    $fee = !empty($_POST['fee']) ? floatval($_POST['fee']) : null;
    $requirements = trim($_POST['requirements']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    if (empty($country_id)) {
        $errors[] = "Please select a country";
    }
    if (empty($visa_type)) {
        $errors[] = "Visa type is required";
    }
    
    // Check if country is global or belongs to this organization
    $check_country = "SELECT is_global FROM countries WHERE country_id = ? AND (is_global = 1 OR organization_id = ?)";
    $check_stmt = $conn->prepare($check_country);
    $check_stmt->bind_param('ii', $country_id, $organization_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $errors[] = "Invalid country selected";
    }
    $check_stmt->close();
    
    if (empty($errors)) {
        // Insert new visa - always organization specific
        $insert_query = "INSERT INTO visas (country_id, visa_type, description, validity_period, fee, 
                        requirements, is_active, organization_id, is_global) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)";
        $stmt = $conn->prepare($insert_query);
        
        // Convert fee to string to ensure proper decimal handling
        $fee_str = $fee !== null ? number_format($fee, 2, '.', '') : null;
        
        $stmt->bind_param('issiisii', 
            $country_id,      // i - integer
            $visa_type,       // s - string
            $description,     // s - string
            $validity_period, // i - integer
            $fee_str,        // s - string (decimal)
            $requirements,    // s - string
            $is_active,       // i - integer
            $organization_id  // i - integer
        );
        
        if ($stmt->execute()) {
            $success_message = "Visa added successfully";
            $stmt->close();
            header("Location: visa.php?success=2");
            exit;
        } else {
            $error_message = "Error adding visa: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle country toggle (activate/deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_country'])) {
    $country_id = $_POST['country_id'];
    $new_status = $_POST['new_status'];
    $inactive_reason = isset($_POST['inactive_reason']) ? trim($_POST['inactive_reason']) : null;
    
    // Update status
    $inactive_since = null;
    if ($new_status == 0) {
        $inactive_since = date('Y-m-d');
        $update_query = "UPDATE countries SET is_active = ?, inactive_reason = ?, inactive_since = ? WHERE country_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('issi', $new_status, $inactive_reason, $inactive_since, $country_id);
    } else {
        $update_query = "UPDATE countries SET is_active = ?, inactive_reason = NULL, inactive_since = NULL WHERE country_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ii', $new_status, $country_id);
    }
    
    if ($stmt->execute()) {
        $status_message = ($new_status == 1) ? "Country activated successfully" : "Country deactivated successfully";
        $stmt->close();
        header("Location: visa.php?success=3&message=" . urlencode($status_message));
        exit;
    } else {
        $error_message = "Error updating country status: " . $conn->error;
        $stmt->close();
    }
}

// Handle visa toggle (activate/deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_visa'])) {
    $visa_id = $_POST['visa_id'];
    $new_status = $_POST['new_status'];
    $inactive_reason = isset($_POST['inactive_reason']) ? trim($_POST['inactive_reason']) : null;
    
    // Update status
    $inactive_since = null;
    if ($new_status == 0) {
        $inactive_since = date('Y-m-d');
        $update_query = "UPDATE visas SET is_active = ?, inactive_reason = ?, inactive_since = ? WHERE visa_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('issi', $new_status, $inactive_reason, $inactive_since, $visa_id);
    } else {
        $update_query = "UPDATE visas SET is_active = ?, inactive_reason = NULL, inactive_since = NULL WHERE visa_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ii', $new_status, $visa_id);
    }
    
    if ($stmt->execute()) {
        $status_message = ($new_status == 1) ? "Visa activated successfully" : "Visa deactivated successfully";
        $stmt->close();
        header("Location: visa.php?success=4&message=" . urlencode($status_message));
        exit;
    } else {
        $error_message = "Error updating visa status: " . $conn->error;
        $stmt->close();
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Country added successfully";
            break;
        case 2:
            $success_message = "Visa added successfully";
            break;
        case 3:
        case 4:
            $success_message = isset($_GET['message']) ? $_GET['message'] : "Status updated successfully";
            break;
        case 5:
            $success_message = "Country updated successfully";
            break;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Visa Management</h1>
            <p>Manage countries and their available visa types.</p>
        </div>
        <div class="action-buttons">
            <button type="button" class="btn primary-btn" id="addCountryBtn">
                <i class="fas fa-plus"></i> Add Country
            </button>
            <button type="button" class="btn primary-btn" id="addVisaBtn">
                <i class="fas fa-plus"></i> Add Visa
            </button>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="visa-filters">
        <div class="filter-group">
            <label for="country-filter">Country:</label>
            <select id="country-filter" class="filter-control">
                <option value="all">All Countries</option>
                <?php foreach ($countries as $country): ?>
                    <option value="<?php echo $country['country_id']; ?>">
                        <?php echo htmlspecialchars($country['country_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="status-filter">Status:</label>
            <select id="status-filter" class="filter-control">
                <option value="all">All Statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="search-group">
            <input type="text" id="search-visa" class="search-control" placeholder="Search...">
            <button id="search-btn" class="search-btn"><i class="fas fa-search"></i></button>
        </div>
    </div>
    
    <!-- Countries and Visas Section -->
    <div class="countries-container">
        <?php if (empty($countries)): ?>
            <div class="empty-state">
                <i class="fas fa-globe"></i>
                <p>No countries added yet. Add a country to get started.</p>
            </div>
        <?php else: ?>
            <?php foreach ($countries as $country): ?>
                <div class="country-card" data-country-id="<?php echo $country['country_id']; ?>" data-status="<?php echo $country['is_active'] ? 'active' : 'inactive'; ?>">
                    <div class="country-header">
                        <div class="country-info">
                            <h3>
                                <?php echo htmlspecialchars($country['country_name']); ?>
                                <span class="country-code">(<?php echo htmlspecialchars($country['country_code']); ?>)</span>
                                <?php if ($country['is_global']): ?>
                                    <span class="global-badge"><i class="fas fa-globe"></i> Global</span>
                                <?php endif; ?>
                            </h3>
                            <?php if ($country['is_active']): ?>
                                <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                            <?php else: ?>
                                <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                                <?php if (!empty($country['inactive_reason'])): ?>
                                    <span class="inactive-reason">Reason: <?php echo htmlspecialchars($country['inactive_reason']); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="country-actions">
                            <?php if (!$country['is_global']): ?>
                                <?php if ($country['is_active']): ?>
                                    <button type="button" class="btn-action deactivate-btn" onclick="toggleCountryStatus(<?php echo $country['country_id']; ?>, 0)">
                                        <i class="fas fa-ban"></i> Deactivate
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn-action activate-btn" onclick="toggleCountryStatus(<?php echo $country['country_id']; ?>, 1)">
                                        <i class="fas fa-check"></i> Activate
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn-action edit-btn" onclick="editCountry(<?php echo $country['country_id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            <?php else: ?>
                                <span class="read-only-badge">Read Only</span>
                            <?php endif; ?>
                            <button type="button" class="btn-action add-visa-btn" onclick="addVisaForCountry(<?php echo $country['country_id']; ?>)">
                                <i class="fas fa-plus"></i> Add Visa
                            </button>
                        </div>
                    </div>
                    
                    <?php if (isset($visas[$country['country_id']]) && !empty($visas[$country['country_id']])): ?>
                        <div class="visas-table-container">
                            <table class="visas-table">
                                <thead>
                                    <tr>
                                        <th>Visa Type</th>
                                        <th>Validity</th>
                                        <th>Fee</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($visas[$country['country_id']] as $visa): ?>
                                        <tr class="visa-row" data-status="<?php echo $visa['is_active'] ? 'active' : 'inactive'; ?>">
                                            <td>
                                                <div class="visa-name">
                                                    <?php echo htmlspecialchars($visa['visa_type']); ?>
                                                    <?php if ($visa['is_global']): ?>
                                                        <span class="global-badge"><i class="fas fa-globe"></i> Global</span>
                                                    <?php else: ?>
                                                        <span class="org-badge"><i class="fas fa-building"></i> Organization</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($visa['description'])): ?>
                                                    <div class="visa-description"><?php echo htmlspecialchars(substr($visa['description'], 0, 80)) . (strlen($visa['description']) > 80 ? '...' : ''); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($visa['validity_period'])): ?>
                                                    <?php echo $visa['validity_period']; ?> days
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($visa['fee'])): ?>
                                                    $<?php echo number_format($visa['fee'], 2); ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($visa['is_active']): ?>
                                                    <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                                                <?php else: ?>
                                                    <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="actions-cell">
                                                <button type="button" class="btn-action btn-view" title="View Details" data-id="<?php echo $visa['visa_id']; ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <button type="button" class="btn-action btn-edit" title="Edit" data-id="<?php echo $visa['visa_id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <button type="button" class="btn-action btn-documents" 
                                                        title="Manage Required Documents" onclick="manageRequiredDocuments(<?php echo $visa['visa_id']; ?>, '<?php echo htmlspecialchars(addslashes($visa['visa_type'])); ?>')">
                                                    <i class="fas fa-file-alt"></i>
                                                </button>
                                                
                                                <?php if ($visa['is_active']): ?>
                                                    <button type="button" class="btn-action btn-deactivate" 
                                                            title="Deactivate" onclick="toggleVisaStatus(<?php echo $visa['visa_id']; ?>, 0)">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn-action btn-activate" 
                                                            title="Activate" onclick="toggleVisaStatus(<?php echo $visa['visa_id']; ?>, 1)">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="no-visas-message">
                            <i class="fas fa-info-circle"></i> No visas available for this country.
                            <button type="button" class="btn-link" onclick="addVisaForCountry(<?php echo $country['country_id']; ?>)">
                                Add a visa
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Country Modal -->
<div class="modal" id="addCountryModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Country</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="visa.php" method="POST" id="addCountryForm">
                    
                        <div class="form-group">
                            <label for="country_name">Country Name*</label>
                            <input type="text" name="country_name" id="country_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="country_code">Country Code* (3 Characters)</label>
                            <input type="text" name="country_code" id="country_code" class="form-control" maxlength="3" required>
                        </div>
                   
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="country_is_active" value="1" checked>
                            <label for="country_is_active">Active</label>
                        </div>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_country" class="btn submit-btn">Add Country</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Visa Modal -->
<div class="modal" id="addVisaModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Visa</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="visa.php" method="POST" id="addVisaForm">
                    <div class="form-group">
                        <label for="visa_country_id">Country*</label>
                        <select name="country_id" id="visa_country_id" class="form-control" required>
                            <option value="">Select Country</option>
                            <?php foreach ($countries as $country): ?>
                                <?php if ($country['is_active']): ?>
                                    <option value="<?php echo $country['country_id']; ?>">
                                        <?php echo htmlspecialchars($country['country_name']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="visa_type">Visa Type*</label>
                        <input type="text" name="visa_type" id="visa_type" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="validity_period">Validity Period (days)</label>
                            <input type="number" name="validity_period" id="validity_period" class="form-control" min="1">
                        </div>
                        <div class="form-group">
                            <label for="fee">Fee ($)</label>
                            <input type="number" name="fee" id="fee" class="form-control" min="0" step="0.01">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="requirements">Requirements</label>
                        <textarea name="requirements" id="requirements" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="visa_is_active" value="1" checked>
                            <label for="visa_is_active">Active</label>
                        </div>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_visa" class="btn submit-btn">Add Visa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toggle Country Status Modal -->
<div class="modal" id="toggleCountryStatusModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="toggleCountryStatusTitle">Deactivate Country</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="visa.php" method="POST" id="toggleCountryStatusForm">
                    <input type="hidden" name="country_id" id="toggle_country_id">
                    <input type="hidden" name="new_status" id="toggle_country_status">
                    <input type="hidden" name="toggle_country" value="1">
                    
                    <div class="form-group" id="inactiveReasonGroup">
                        <label for="inactive_reason">Reason for Deactivation</label>
                        <textarea name="inactive_reason" id="inactive_reason" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn submit-btn" id="toggleCountryStatusBtn">Deactivate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toggle Visa Status Modal -->
<div class="modal" id="toggleVisaStatusModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="toggleVisaStatusTitle">Deactivate Visa</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="visa.php" method="POST" id="toggleVisaStatusForm">
                    <input type="hidden" name="visa_id" id="toggle_visa_id">
                    <input type="hidden" name="new_status" id="toggle_visa_status">
                    <input type="hidden" name="toggle_visa" value="1">
                    
                    <div class="form-group" id="visaInactiveReasonGroup">
                        <label for="visa_inactive_reason">Reason for Deactivation</label>
                        <textarea name="inactive_reason" id="visa_inactive_reason" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn submit-btn" id="toggleVisaStatusBtn">Deactivate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Manage Required Documents Modal -->
<div class="modal" id="manageDocumentsModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="documentsModalTitle">Manage Required Documents</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="documentsModalLoading" class="text-center p-4">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Loading documents...</p>
                </div>
                <div id="documentsModalContent" style="display: none;">
                    <p class="mb-3">Select documents required for <strong id="visaTypeDisplay"></strong>:</p>
                    
                    <form id="requiredDocumentsForm">
                        <input type="hidden" id="modal_visa_id" name="visa_id">
                        
                        <div class="document-categories">
                            <!-- Document categories and types will be loaded here via AJAX -->
                        </div>
                        
                        <div class="form-buttons mt-4">
                            <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn submit-btn" id="saveRequiredDocsBtn">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Country Modal -->
<div class="modal" id="editCountryModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Country</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="visa.php" method="POST" id="editCountryForm">
                    <input type="hidden" name="edit_country_id" id="edit_country_id">
                    
                    <div class="form-group">
                        <label for="edit_country_name">Country Name*</label>
                        <input type="text" name="country_name" id="edit_country_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_country_code">Country Code* (3 Characters)</label>
                        <input type="text" name="country_code" id="edit_country_code" class="form-control" maxlength="3" required>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="edit_country_is_active" value="1">
                            <label for="edit_country_is_active">Active</label>
                        </div>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_country" class="btn submit-btn">Update Country</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Visa Details Modal -->
<div class="modal" id="viewVisaModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Visa Details</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="visaDetailsLoading" class="text-center p-4">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Loading visa details...</p>
                </div>
                <div id="visaDetailsContent" style="display: none;">
                    <div class="visa-details-grid">
                        <div class="detail-group">
                            <label>Visa Type</label>
                            <p id="detail_visa_type"></p>
                        </div>
                        <div class="detail-group">
                            <label>Country</label>
                            <p id="detail_country"></p>
                        </div>
                        <div class="detail-group">
                            <label>Validity Period</label>
                            <p id="detail_validity"></p>
                        </div>
                        <div class="detail-group">
                            <label>Fee</label>
                            <p id="detail_fee"></p>
                        </div>
                        <div class="detail-group full-width">
                            <label>Description</label>
                            <p id="detail_description"></p>
                        </div>
                        <div class="detail-group full-width">
                            <label>Requirements</label>
                            <p id="detail_requirements"></p>
                        </div>
                        <div class="detail-group">
                            <label>Status</label>
                            <p id="detail_status"></p>
                        </div>
                        <div class="detail-group">
                            <label>Created Date</label>
                            <p id="detail_created"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn cancel-btn" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Visa Modal -->
<div class="modal" id="editVisaModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Visa</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editVisaForm" method="POST" action="visa.php">
                    <input type="hidden" name="edit_visa_id" id="edit_visa_id">
                    
                    <div class="form-group">
                        <label for="edit_visa_type">Visa Type*</label>
                        <input type="text" name="visa_type" id="edit_visa_type" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_validity_period">Validity Period (days)</label>
                            <input type="number" name="validity_period" id="edit_validity_period" class="form-control" min="1">
                        </div>
                        <div class="form-group">
                            <label for="edit_fee">Fee ($)</label>
                            <input type="number" name="fee" id="edit_fee" class="form-control" min="0" step="0.01">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_requirements">Requirements</label>
                        <textarea name="requirements" id="edit_requirements" class="form-control" rows="4"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="edit_visa_is_active" value="1">
                            <label for="edit_visa_is_active">Active</label>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_visa" class="btn submit-btn">Update Visa</button>
                    </div>
                </form>
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
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-color: #e3e6f0;
    --warning-color: #f6c23e;
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

.action-buttons {
    display: flex;
    gap: 10px;
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

.visa-filters {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-group label {
    font-weight: 500;
    color: var(--dark-color);
}

.filter-control {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background-color: white;
    color: var(--dark-color);
    min-width: 120px;
}

.search-group {
    display: flex;
    margin-left: auto;
}

.search-control {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px 0 0 4px;
    background-color: white;
    color: var(--dark-color);
    min-width: 200px;
}

.search-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 0 4px 4px 0;
    cursor: pointer;
}

.countries-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.country-card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.country-header {
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--light-color);
}

.country-info {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.country-info h3 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.3rem;
}

.country-code {
    color: var(--secondary-color);
    font-size: 1rem;
    font-weight: normal;
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

.inactive-reason {
    margin-left: 10px;
    font-size: 12px;
    color: var(--secondary-color);
    font-style: italic;
}

.country-actions {
    display: flex;
    gap: 10px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    background-color: var(--secondary-color);
    color: white;
}

.activate-btn {
    background-color: var(--success-color);
}

.activate-btn:hover {
    background-color: #18b07b;
}

.deactivate-btn {
    background-color: var(--danger-color);
}

.deactivate-btn:hover {
    background-color: #d44235;
}

.edit-btn {
    background-color: var(--warning-color);
}

.edit-btn:hover {
    background-color: #e0b137;
}

.add-visa-btn {
    background-color: var(--primary-color);
}

.add-visa-btn:hover {
    background-color: #031c56;
}

.visas-table-container {
    padding: 15px;
}

.visas-table {
    width: 100%;
    border-collapse: collapse;
}

.visas-table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    text-align: left;
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
}

.visas-table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--dark-color);
}

.visas-table tbody tr:hover {
    background-color: rgba(4, 33, 103, 0.03);
}

.visas-table tbody tr:last-child td {
    border-bottom: none;
}

.visa-name {
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 5px;
}

.visa-description {
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.actions-cell {
    width: 200px;
}

.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-toggle {
    background-color: transparent;
    border: none;
    color: var(--secondary-color);
    cursor: pointer;
    padding: 6px 8px;
    border-radius: 4px;
}

.dropdown-toggle:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    background-color: white;
    min-width: 160px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    border-radius: 4px;
    z-index: 1;
}

.dropdown-menu.show {
    display: block;
}

.dropdown-item {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    color: var(--dark-color);
    text-decoration: none;
    gap: 8px;
    cursor: pointer;
}

.dropdown-item:hover {
    background-color: var(--light-color);
}

.dropdown-item i {
    width: 16px;
    text-align: center;
}

.no-visas-message {
    padding: 20px;
    text-align: center;
    color: var(--secondary-color);
    font-style: italic;
}

.btn-link {
    background: none;
    border: none;
    color: var(--primary-color);
    text-decoration: underline;
    cursor: pointer;
    padding: 0;
    font-size: inherit;
}

.empty-state {
    text-align: center;
    padding: 50px 20px;
    color: var(--secondary-color);
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
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
    max-width: 600px;
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

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.checkbox-group {
    display: flex;
    align-items: center;
}

.checkbox-group input[type="checkbox"] {
    margin-right: 10px;
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

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .action-buttons {
        width: 100%;
    }
    
    .primary-btn {
        flex: 1;
        justify-content: center;
    }
    
    .visa-filters {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .search-group {
        width: 100%;
        margin-left: 0;
    }
    
    .search-control {
        flex: 1;
    }
    
    .country-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .country-actions {
        width: 100%;
        justify-content: space-between;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .visas-table {
        display: block;
        overflow-x: auto;
    }
}

.btn-documents {
    background-color: #4e73df;
}

.btn-documents:hover {
    background-color: #375ad3;
}

.document-categories {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 15px;
}

.document-category {
    margin-bottom: 20px;
}

.category-title {
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 1px solid var(--border-color);
    font-weight: 600;
    color: var(--primary-color);
}

.document-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding-left: 15px;
}

.document-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.document-checkbox {
    margin: 0;
}

.mandatory-checkbox {
    margin-left: auto;
}

.notes-field {
    width: 100%;
    margin-top: 8px;
    display: none;
}

.notes-toggle {
    font-size: 12px;
    color: var(--secondary-color);
    cursor: pointer;
    margin-left: 10px;
}

.notes-toggle:hover {
    text-decoration: underline;
}

.text-center {
    text-align: center;
}

.p-4 {
    padding: 1rem;
}

.mt-2 {
    margin-top: 0.5rem;
}

.mb-3 {
    margin-bottom: 0.75rem;
}

.mt-4 {
    margin-top: 1rem;
}

.modal-lg {
    max-width: 800px;
}

.global-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 6px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    background-color: rgba(78, 115, 223, 0.1);
    color: #4e73df;
    margin-left: 8px;
}

.global-badge i {
    font-size: 10px;
}

.org-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 6px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    background-color: rgba(28, 200, 138, 0.1);
    color: #1cc88a;
    margin-left: 8px;
}

.org-badge i {
    font-size: 10px;
}

.visa-details-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    padding: 20px;
}

.detail-group {
    margin-bottom: 15px;
}

.detail-group.full-width {
    grid-column: 1 / -1;
}

.detail-group label {
    display: block;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 5px;
}

.detail-group p {
    margin: 0;
    color: var(--dark-color);
    line-height: 1.5;
}

.detail-group p:empty::before {
    content: '-';
    color: var(--secondary-color);
    font-style: italic;
}
</style>

<script>
// Modal functionality
// Open Add Country modal
document.getElementById('addCountryBtn').addEventListener('click', function() {
    document.getElementById('addCountryModal').style.display = 'block';
});

// Open Add Visa modal
document.getElementById('addVisaBtn').addEventListener('click', function() {
    document.getElementById('addVisaModal').style.display = 'block';
});

// Close modals when close button is clicked
document.querySelectorAll('[data-dismiss="modal"]').forEach(function(element) {
    element.addEventListener('click', function() {
        document.querySelectorAll('.modal').forEach(function(modal) {
            modal.style.display = 'none';
        });
    });
});

// Close modals when clicking outside of it
window.addEventListener('click', function(event) {
    document.querySelectorAll('.modal').forEach(function(modal) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});

// Function to add visa for specific country
function addVisaForCountry(countryId) {
    document.getElementById('visa_country_id').value = countryId;
    document.getElementById('addVisaModal').style.display = 'block';
}

// Function to edit country
function editCountry(countryId) {
    // Get country details via AJAX and populate the form
    fetch('ajax/get_country.php?id=' + countryId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const country = data.country;
                document.getElementById('edit_country_id').value = country.country_id;
                document.getElementById('edit_country_name').value = country.country_name;
                document.getElementById('edit_country_code').value = country.country_code;
                document.getElementById('edit_country_is_active').checked = country.is_active == 1;
                
                // Show the modal
                document.getElementById('editCountryModal').style.display = 'block';
            } else {
                alert('Error loading country details. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error fetching country details:', error);
            alert('Error loading country details. Please try again.');
        });
}

// Function to toggle country status
function toggleCountryStatus(countryId, newStatus) {
    document.getElementById('toggle_country_id').value = countryId;
    document.getElementById('toggle_country_status').value = newStatus;
    
    const modal = document.getElementById('toggleCountryStatusModal');
    const title = document.getElementById('toggleCountryStatusTitle');
    const button = document.getElementById('toggleCountryStatusBtn');
    const reasonGroup = document.getElementById('inactiveReasonGroup');
    
    if (newStatus == 1) {
        title.textContent = 'Activate Country';
        button.textContent = 'Activate';
        reasonGroup.style.display = 'none';
    } else {
        title.textContent = 'Deactivate Country';
        button.textContent = 'Deactivate';
        reasonGroup.style.display = 'block';
    }
    
    modal.style.display = 'block';
}

// Function to toggle visa status
function toggleVisaStatus(visaId, newStatus) {
    document.getElementById('toggle_visa_id').value = visaId;
    document.getElementById('toggle_visa_status').value = newStatus;
    
    const modal = document.getElementById('toggleVisaStatusModal');
    const title = document.getElementById('toggleVisaStatusTitle');
    const button = document.getElementById('toggleVisaStatusBtn');
    const reasonGroup = document.getElementById('visaInactiveReasonGroup');
    
    if (newStatus == 1) {
        title.textContent = 'Activate Visa';
        button.textContent = 'Activate';
        reasonGroup.style.display = 'none';
    } else {
        title.textContent = 'Deactivate Visa';
        button.textContent = 'Deactivate';
        reasonGroup.style.display = 'block';
    }
    
    modal.style.display = 'block';
}

// Country filtering functionality
function filterCountries() {
    const countryFilter = document.getElementById('country-filter').value;
    const statusFilter = document.getElementById('status-filter').value;
    const searchQuery = document.getElementById('search-visa').value.toLowerCase();
    
    document.querySelectorAll('.country-card').forEach(function(card) {
        const countryId = card.getAttribute('data-country-id');
        const status = card.getAttribute('data-status');
        const countryName = card.querySelector('h3').textContent.toLowerCase();
        const visaRows = card.querySelectorAll('.visa-row');
        
        let showCountry = true;
        
        // Apply country filter
        if (countryFilter !== 'all' && countryId !== countryFilter) {
            showCountry = false;
        }
        
        // Apply status filter
        if (statusFilter !== 'all' && status !== statusFilter) {
            showCountry = false;
        }
        
        // Apply search filter to country name
        if (searchQuery && !countryName.includes(searchQuery)) {
            // If search doesn't match country name, check visa names
            let visaMatch = false;
            visaRows.forEach(function(row) {
                const visaName = row.querySelector('.visa-name').textContent.toLowerCase();
                if (visaName.includes(searchQuery)) {
                    visaMatch = true;
                }
            });
            
            if (!visaMatch) {
                showCountry = false;
            }
        }
        
        card.style.display = showCountry ? 'block' : 'none';
    });
}

// Add event listeners for filters
document.getElementById('country-filter').addEventListener('change', filterCountries);
document.getElementById('status-filter').addEventListener('change', filterCountries);
document.getElementById('search-btn').addEventListener('click', filterCountries);
document.getElementById('search-visa').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        filterCountries();
    }
});

// Country code validation - force uppercase
document.getElementById('country_code').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});

// Function to load document types and categories
function loadDocumentTypes(visaId) {
    if (!visaId) {
        console.error('Visa ID is required');
        return;
    }

    // First, get all document types grouped by category
    fetch('ajax/get_document_types.php')
        .then(response => response.json())
        .then(types => {
            if (!types.success) {
                throw new Error(types.error || 'Error loading document types');
            }
            
            // Then get currently required documents for this visa
            return fetch('ajax/get_required_documents.php?visa_id=' + visaId)
                .then(response => response.json())
                .then(requiredDocs => {
                    if (!requiredDocs.success) {
                        throw new Error(requiredDocs.error || 'Error loading required documents');
                    }
                    
                    // Build the document selection UI
                    buildDocumentSelectionUI(types.data, requiredDocs.data);
                    
                    // Hide loading, show content
                    document.getElementById('documentsModalLoading').style.display = 'none';
                    document.getElementById('documentsModalContent').style.display = 'block';
                });
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('documentsModalLoading').style.display = 'none';
            alert('Error: ' + error.message);
        });
}

// Function to manage required documents for a visa
function manageRequiredDocuments(visaId, visaType) {
    // Set visa ID and title
    document.getElementById('modal_visa_id').value = visaId;
    document.getElementById('visaTypeDisplay').textContent = visaType;
    
    // Show loading, hide content
    document.getElementById('documentsModalLoading').style.display = 'block';
    document.getElementById('documentsModalContent').style.display = 'none';
    
    // Show modal
    document.getElementById('manageDocumentsModal').style.display = 'block';
    
    // Load document types and categories
    loadDocumentTypes(visaId);
}

// Function to build document selection UI
function buildDocumentSelectionUI(documentsByCategory, requiredDocs) {
    const container = document.querySelector('.document-categories');
    container.innerHTML = '';
    
    // Create a map of required documents for easy lookup
    const requiredDocsMap = {};
    requiredDocs.forEach(doc => {
        requiredDocsMap[doc.document_name] = {
            is_mandatory: doc.is_mandatory,
            notes: doc.notes || ''
        };
    });
    
    // Build UI for each category
    for (const category in documentsByCategory) {
        const documents = documentsByCategory[category];
        
        const categoryDiv = document.createElement('div');
        categoryDiv.className = 'document-category';
        
        const categoryTitle = document.createElement('div');
        categoryTitle.className = 'category-title';
        categoryTitle.textContent = category;
        categoryDiv.appendChild(categoryTitle);
        
        const documentList = document.createElement('div');
        documentList.className = 'document-list';
        
        documents.forEach(doc => {
            const isRequired = requiredDocsMap.hasOwnProperty(doc.name);
            const isMandatory = isRequired && requiredDocsMap[doc.name].is_mandatory === '1';
            const notes = isRequired ? requiredDocsMap[doc.name].notes : '';
            
            const documentItem = document.createElement('div');
            documentItem.className = 'document-item';
            
            // Document selection checkbox
            const checkboxLabel = document.createElement('label');
            checkboxLabel.className = 'document-label';
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'document-checkbox';
            checkbox.name = 'documents[]';
            checkbox.value = doc.id;
            checkbox.checked = isRequired;
            checkbox.dataset.documentName = doc.name;
            
            checkboxLabel.appendChild(checkbox);
            checkboxLabel.appendChild(document.createTextNode(' ' + doc.name));
            
            // Add a notes toggle
            const notesToggle = document.createElement('span');
            notesToggle.className = 'notes-toggle';
            notesToggle.textContent = 'Add Notes';
            notesToggle.onclick = function() {
                const notesField = this.parentElement.nextElementSibling;
                notesField.style.display = notesField.style.display === 'none' ? 'block' : 'none';
                this.textContent = notesField.style.display === 'none' ? 'Add Notes' : 'Hide Notes';
            };
            
            // Mandatory checkbox
            const mandatoryLabel = document.createElement('label');
            mandatoryLabel.className = 'mandatory-label';
            
            const mandatoryCheckbox = document.createElement('input');
            mandatoryCheckbox.type = 'checkbox';
            mandatoryCheckbox.className = 'mandatory-checkbox';
            mandatoryCheckbox.name = 'mandatory_' + doc.id;
            mandatoryCheckbox.checked = isMandatory;
            mandatoryCheckbox.disabled = !isRequired;
            
            checkbox.onchange = function() {
                mandatoryCheckbox.disabled = !this.checked;
                if (!this.checked) {
                    mandatoryCheckbox.checked = false;
                }
            };
            
            mandatoryLabel.appendChild(document.createTextNode('Mandatory '));
            mandatoryLabel.appendChild(mandatoryCheckbox);
            
            documentItem.appendChild(checkboxLabel);
            documentItem.appendChild(notesToggle);
            documentItem.appendChild(mandatoryLabel);
            
            // Notes textarea (initially hidden)
            const notesContainer = document.createElement('div');
            notesContainer.className = 'notes-field';
            notesContainer.style.display = notes ? 'block' : 'none';
            
            const notesTextarea = document.createElement('textarea');
            notesTextarea.className = 'form-control';
            notesTextarea.name = 'notes_' + doc.id;
            notesTextarea.placeholder = 'Add notes about this document';
            notesTextarea.rows = 2;
            notesTextarea.value = notes;
            
            notesContainer.appendChild(notesTextarea);
            
            documentList.appendChild(documentItem);
            documentList.appendChild(notesContainer);
            
            if (notes) {
                notesToggle.textContent = 'Hide Notes';
            }
        });
        
        categoryDiv.appendChild(documentList);
        container.appendChild(categoryDiv);
    }
    
    // Add save button event listener
    document.getElementById('saveRequiredDocsBtn').onclick = saveRequiredDocuments;
}

// Function to save required documents
function saveRequiredDocuments() {
    const form = document.getElementById('requiredDocumentsForm');
    const visaId = document.getElementById('modal_visa_id').value;
    
    // Get all selected documents
    const documentCheckboxes = form.querySelectorAll('.document-checkbox:checked');
    const documents = [];
    
    documentCheckboxes.forEach(checkbox => {
        const docId = checkbox.value;
        const mandatoryCheckbox = form.querySelector(`input[name="mandatory_${docId}"]`);
        const notesTextarea = form.querySelector(`textarea[name="notes_${docId}"]`);
        
        documents.push({
            document_id: docId,
            is_mandatory: mandatoryCheckbox.checked ? 1 : 0,
            notes: notesTextarea ? notesTextarea.value : ''
        });
    });
    
    // Send data to server
    fetch('ajax/save_required_documents.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            visa_id: visaId,
            documents: documents
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Required documents saved successfully!');
            document.getElementById('manageDocumentsModal').style.display = 'none';
        } else {
            alert('Error: ' + (data.message || 'Could not save required documents'));
        }
    })
    .catch(error => {
        console.error('Error saving required documents:', error);
        alert('Error saving required documents. Please try again.');
    });
}

// Function to view visa details
function viewVisaDetails(visaId) {
    // Show loading state
    document.getElementById('visaDetailsLoading').style.display = 'block';
    document.getElementById('visaDetailsContent').style.display = 'none';
    
    // Show modal
    document.getElementById('viewVisaModal').style.display = 'block';
    
    // Fetch visa details
    fetch('ajax/get_visa_details.php?id=' + visaId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const visa = data.visa;
                
                // Populate details
                document.getElementById('detail_visa_type').textContent = visa.visa_type;
                document.getElementById('detail_country').textContent = visa.country_name;
                document.getElementById('detail_validity').textContent = visa.validity_period ? visa.validity_period + ' days' : '-';
                document.getElementById('detail_fee').textContent = visa.fee ? '$' + parseFloat(visa.fee).toFixed(2) : '-';
                document.getElementById('detail_description').textContent = visa.description || '';
                document.getElementById('detail_requirements').textContent = visa.requirements || '';
                document.getElementById('detail_status').innerHTML = visa.is_active ? 
                    '<span class="status-badge active"><i class="fas fa-circle"></i> Active</span>' :
                    '<span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>';
                document.getElementById('detail_created').textContent = new Date(visa.created_at).toLocaleDateString();
                
                // Hide loading, show content
                document.getElementById('visaDetailsLoading').style.display = 'none';
                document.getElementById('visaDetailsContent').style.display = 'block';
            } else {
                throw new Error(data.error || 'Failed to load visa details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('visaDetailsLoading').style.display = 'none';
            alert('Error loading visa details: ' + error.message);
        });
}

// Function to edit visa
function editVisa(visaId) {
    // Fetch visa details
    fetch('ajax/get_visa_details.php?id=' + visaId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const visa = data.visa;
                
                // Populate form
                document.getElementById('edit_visa_id').value = visa.visa_id;
                document.getElementById('edit_visa_type').value = visa.visa_type;
                document.getElementById('edit_description').value = visa.description || '';
                document.getElementById('edit_validity_period').value = visa.validity_period || '';
                document.getElementById('edit_fee').value = visa.fee || '';
                document.getElementById('edit_requirements').value = visa.requirements || '';
                document.getElementById('edit_visa_is_active').checked = visa.is_active == 1;
                
                // Show modal
                document.getElementById('editVisaModal').style.display = 'block';
            } else {
                throw new Error(data.error || 'Failed to load visa details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading visa details: ' + error.message);
        });
}

// Update the existing action buttons to use the new functions
document.querySelectorAll('.btn-view').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        const visaId = this.dataset.id;
        viewVisaDetails(visaId);
    });
});

document.querySelectorAll('.btn-edit').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        const visaId = this.dataset.id;
        editVisa(visaId);
    });
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
