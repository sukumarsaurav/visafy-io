<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Edit Service";
$page_specific_css = "assets/css/services.css";
require_once 'includes/header.php';

// Get service ID from URL
$service_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$service_id) {
    die("Service ID not provided");
}

// Get consultant ID and organization ID from session
$consultant_id = isset($_SESSION['id']) ? $_SESSION['id'] : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
$organization_id = isset($user['organization_id']) ? $user['organization_id'] : null;

// Verify organization ID is set
if (!$organization_id) {
    die("Organization ID not set. Please log in again.");
}

// Get service details
$query = "SELECT vs.*, v.visa_type, c.country_name, st.service_name
          FROM visa_services vs
          JOIN visas v ON vs.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          JOIN service_types st ON vs.service_type_id = st.service_type_id
          WHERE vs.visa_service_id = ? AND vs.organization_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $service_id, $organization_id);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$service) {
    die("Service not found or you don't have permission to edit it");
}

// Get consultation modes
$query = "SELECT cm.*, scm.additional_fee, scm.duration_minutes
          FROM consultation_modes cm
          LEFT JOIN service_consultation_modes scm ON cm.consultation_mode_id = scm.consultation_mode_id 
          AND scm.visa_service_id = ?
          ORDER BY cm.mode_name";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $service_id);
$stmt->execute();
$modes_result = $stmt->get_result();
$modes = [];

if ($modes_result && $modes_result->num_rows > 0) {
    while ($row = $modes_result->fetch_assoc()) {
        $modes[] = $row;
    }
}
$stmt->close();

// Get required documents
$query = "SELECT * FROM service_documents WHERE visa_service_id = ? ORDER BY document_name";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $service_id);
$stmt->execute();
$documents_result = $stmt->get_result();
$documents = [];

if ($documents_result && $documents_result->num_rows > 0) {
    while ($row = $documents_result->fetch_assoc()) {
        $documents[] = $row;
    }
}
$stmt->close();

// Get booking settings if service is bookable
$booking_settings = null;
if ($service['is_bookable']) {
    $query = "SELECT * FROM service_booking_settings WHERE visa_service_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $service_id);
    $stmt->execute();
    $booking_settings = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        // Update basic service information
        $query = "UPDATE visa_services SET 
                  description = ?,
                  base_price = ?,
                  is_active = ?,
                  is_bookable = ?,
                  updated_at = NOW()
                  WHERE visa_service_id = ? AND organization_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sdiisi', 
            $_POST['description'],
            $_POST['base_price'],
            $_POST['is_active'],
            $_POST['is_bookable'],
            $service_id,
            $organization_id
        );
        $stmt->execute();
        $stmt->close();

        // Update consultation modes
        $query = "DELETE FROM service_consultation_modes WHERE visa_service_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $service_id);
        $stmt->execute();
        $stmt->close();

        if (isset($_POST['modes']) && is_array($_POST['modes'])) {
            $query = "INSERT INTO service_consultation_modes 
                     (visa_service_id, consultation_mode_id, additional_fee, duration_minutes) 
                     VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            
            foreach ($_POST['modes'] as $mode_id => $mode_data) {
                if (isset($mode_data['selected']) && $mode_data['selected']) {
                    $stmt->bind_param('iidi', 
                        $service_id,
                        $mode_id,
                        $mode_data['additional_fee'],
                        $mode_data['duration']
                    );
                    $stmt->execute();
                }
            }
            $stmt->close();
        }

        // Update required documents
        $query = "DELETE FROM service_documents WHERE visa_service_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $service_id);
        $stmt->execute();
        $stmt->close();

        if (isset($_POST['documents']) && is_array($_POST['documents'])) {
            $query = "INSERT INTO service_documents 
                     (visa_service_id, document_name, description, is_required) 
                     VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            
            foreach ($_POST['documents'] as $doc) {
                if (!empty($doc['name'])) {
                    $stmt->bind_param('issi', 
                        $service_id,
                        $doc['name'],
                        $doc['description'],
                        $doc['is_required']
                    );
                    $stmt->execute();
                }
            }
            $stmt->close();
        }

        // Update booking settings if service is bookable
        if ($_POST['is_bookable']) {
            if ($booking_settings) {
                $query = "UPDATE service_booking_settings SET 
                         min_notice_hours = ?,
                         max_advance_days = ?,
                         buffer_before_minutes = ?,
                         buffer_after_minutes = ?,
                         payment_required = ?,
                         deposit_amount = ?,
                         deposit_percentage = ?,
                         updated_at = NOW()
                         WHERE visa_service_id = ?";
            } else {
                $query = "INSERT INTO service_booking_settings 
                         (min_notice_hours, max_advance_days, buffer_before_minutes, 
                          buffer_after_minutes, payment_required, deposit_amount, 
                          deposit_percentage, visa_service_id, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            }
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('iiiiiddi',
                $_POST['min_notice_hours'],
                $_POST['max_advance_days'],
                $_POST['buffer_before_minutes'],
                $_POST['buffer_after_minutes'],
                $_POST['payment_required'],
                $_POST['deposit_amount'],
                $_POST['deposit_percentage'],
                $service_id
            );
            $stmt->execute();
            $stmt->close();
        } else {
            // Delete booking settings if service is no longer bookable
            $query = "DELETE FROM service_booking_settings WHERE visa_service_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $service_id);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        header("Location: service_details.php?id=" . $service_id . "&success=1");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error = "An error occurred while updating the service. Please try again.";
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Edit Service</h1>
            <p>Update service information and settings</p>
        </div>
        <div class="header-actions">
            <a href="service_details.php?id=<?php echo $service_id; ?>" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Details
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="edit-form">
        <div class="form-section">
            <h2>Basic Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <label>Country</label>
                    <span><?php echo htmlspecialchars($service['country_name']); ?></span>
                </div>
                <div class="info-item">
                    <label>Visa Type</label>
                    <span><?php echo htmlspecialchars($service['visa_type']); ?></span>
                </div>
                <div class="info-item">
                    <label>Service Type</label>
                    <span><?php echo htmlspecialchars($service['service_name']); ?></span>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($service['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="base_price">Base Price ($)</label>
                <input type="number" id="base_price" name="base_price" step="0.01" min="0" value="<?php echo $service['base_price']; ?>" required>
            </div>

            <div class="form-group">
                <label>Status</label>
                <div class="checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" value="1" <?php echo $service['is_active'] ? 'checked' : ''; ?>>
                        Active
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_bookable" value="1" <?php echo $service['is_bookable'] ? 'checked' : ''; ?>>
                        Bookable
                    </label>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2>Consultation Modes</h2>
            <div class="modes-grid">
                <?php foreach ($modes as $mode): ?>
                <div class="mode-card">
                    <div class="mode-header">
                        <label class="checkbox-label">
                            <input type="checkbox" name="modes[<?php echo $mode['consultation_mode_id']; ?>][selected]" 
                                   value="1" <?php echo isset($mode['additional_fee']) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($mode['mode_name']); ?>
                        </label>
                    </div>
                    <div class="mode-fields">
                        <div class="form-group">
                            <label>Additional Fee ($)</label>
                            <input type="number" name="modes[<?php echo $mode['consultation_mode_id']; ?>][additional_fee]" 
                                   step="0.01" min="0" value="<?php echo $mode['additional_fee'] ?? 0; ?>">
                        </div>
                        <div class="form-group">
                            <label>Duration (minutes)</label>
                            <input type="number" name="modes[<?php echo $mode['consultation_mode_id']; ?>][duration]" 
                                   min="15" step="15" value="<?php echo $mode['duration_minutes'] ?? 30; ?>">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-section">
            <h2>Required Documents</h2>
            <div id="documents-container">
                <?php foreach ($documents as $index => $doc): ?>
                <div class="document-item">
                    <div class="document-header">
                        <div class="form-group">
                            <label>Document Name</label>
                            <input type="text" name="documents[<?php echo $index; ?>][name]" 
                                   value="<?php echo htmlspecialchars($doc['document_name']); ?>" required>
                        </div>
                        <button type="button" class="btn danger-btn remove-document">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="documents[<?php echo $index; ?>][description]" rows="2"><?php echo htmlspecialchars($doc['description']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="documents[<?php echo $index; ?>][is_required]" value="1" 
                                   <?php echo $doc['is_required'] ? 'checked' : ''; ?>>
                            Required
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn secondary-btn" id="add-document">
                <i class="fas fa-plus"></i> Add Document
            </button>
        </div>

        <div class="form-section" id="booking-settings-section" style="display: <?php echo $service['is_bookable'] ? 'block' : 'none'; ?>">
            <h2>Booking Settings</h2>
            <div class="info-grid">
                <div class="form-group">
                    <label for="min_notice_hours">Minimum Notice (hours)</label>
                    <input type="number" id="min_notice_hours" name="min_notice_hours" min="0" 
                           value="<?php echo $booking_settings['min_notice_hours'] ?? 24; ?>" required>
                </div>
                <div class="form-group">
                    <label for="max_advance_days">Maximum Advance Booking (days)</label>
                    <input type="number" id="max_advance_days" name="max_advance_days" min="1" 
                           value="<?php echo $booking_settings['max_advance_days'] ?? 30; ?>" required>
                </div>
                <div class="form-group">
                    <label for="buffer_before_minutes">Buffer Before (minutes)</label>
                    <input type="number" id="buffer_before_minutes" name="buffer_before_minutes" min="0" 
                           value="<?php echo $booking_settings['buffer_before_minutes'] ?? 15; ?>" required>
                </div>
                <div class="form-group">
                    <label for="buffer_after_minutes">Buffer After (minutes)</label>
                    <input type="number" id="buffer_after_minutes" name="buffer_after_minutes" min="0" 
                           value="<?php echo $booking_settings['buffer_after_minutes'] ?? 15; ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="payment_required" value="1" 
                           <?php echo ($booking_settings['payment_required'] ?? false) ? 'checked' : ''; ?>>
                    Payment Required
                </label>
            </div>

            <div id="payment-details" style="display: <?php echo ($booking_settings['payment_required'] ?? false) ? 'block' : 'none'; ?>">
                <div class="info-grid">
                    <div class="form-group">
                        <label for="deposit_amount">Deposit Amount ($)</label>
                        <input type="number" id="deposit_amount" name="deposit_amount" step="0.01" min="0" 
                               value="<?php echo $booking_settings['deposit_amount'] ?? 0; ?>">
                    </div>
                    <div class="form-group">
                        <label for="deposit_percentage">Deposit Percentage (%)</label>
                        <input type="number" id="deposit_percentage" name="deposit_percentage" step="1" min="0" max="100" 
                               value="<?php echo $booking_settings['deposit_percentage'] ?? 0; ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn primary-btn">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <a href="service_details.php?id=<?php echo $service_id; ?>" class="btn secondary-btn">
                Cancel
            </a>
        </div>
    </form>
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
    text-decoration: none;
}

.primary-btn:hover {
    background-color: #031c56;
    color: white;
    text-decoration: none;
}

.secondary-btn {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-align: center;
    text-decoration: none;
    transition: all 0.2s;
}

.secondary-btn:hover {
    background-color: var(--light-color);
    text-decoration: none;
    color: var(--primary-color);
}

.danger-btn {
    background-color: var(--danger-color);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.danger-btn:hover {
    background-color: #d44235;
    color: white;
}

.btn {
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    border-radius: 4px;
}

.edit-form {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid var(--border-color);
}

.form-section:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.form-section h2 {
    color: var(--primary-color);
    font-size: 1.4rem;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    color: var(--dark-color);
    font-weight: 500;
    margin-bottom: 5px;
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 1rem;
}

.form-group input[type="text"]:focus,
.form-group input[type="number"]:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(4, 33, 103, 0.1);
}

.form-group textarea {
    resize: vertical;
}

.checkbox-group {
    display: flex;
    gap: 20px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.info-item {
    margin-bottom: 15px;
}

.info-item label {
    display: block;
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 5px;
}

.info-item span {
    color: var(--secondary-color);
}

.modes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.mode-card {
    background-color: var(--light-color);
    padding: 15px;
    border-radius: 4px;
}

.mode-header {
    margin-bottom: 15px;
}

.mode-fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.document-item {
    background-color: var(--light-color);
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.document-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 15px;
}

.document-header .form-group {
    flex: 1;
    margin-bottom: 0;
}

.remove-document {
    padding: 8px;
    color: var(--danger-color);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
}

.remove-document:hover {
    color: #d44235;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 30px;
}

#add-document {
    margin-top: 15px;
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

@media (max-width: 768px) {
    .modes-grid,
    .info-grid {
        grid-template-columns: 1fr;
    }

    .mode-fields {
        grid-template-columns: 1fr;
    }

    .document-header {
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle bookable checkbox
    const bookableCheckbox = document.querySelector('input[name="is_bookable"]');
    const bookingSettingsSection = document.getElementById('booking-settings-section');
    
    bookableCheckbox.addEventListener('change', function() {
        bookingSettingsSection.style.display = this.checked ? 'block' : 'none';
    });

    // Handle payment required checkbox
    const paymentRequiredCheckbox = document.querySelector('input[name="payment_required"]');
    const paymentDetails = document.getElementById('payment-details');
    
    paymentRequiredCheckbox.addEventListener('change', function() {
        paymentDetails.style.display = this.checked ? 'block' : 'none';
    });

    // Handle document management
    const documentsContainer = document.getElementById('documents-container');
    const addDocumentButton = document.getElementById('add-document');
    let documentCount = <?php echo count($documents); ?>;

    addDocumentButton.addEventListener('click', function() {
        const documentItem = document.createElement('div');
        documentItem.className = 'document-item';
        documentItem.innerHTML = `
            <div class="document-header">
                <div class="form-group">
                    <label>Document Name</label>
                    <input type="text" name="documents[${documentCount}][name]" required>
                </div>
                <button type="button" class="btn danger-btn remove-document">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="documents[${documentCount}][description]" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="documents[${documentCount}][is_required]" value="1">
                    Required
                </label>
            </div>
        `;
        documentsContainer.appendChild(documentItem);
        documentCount++;
    });

    documentsContainer.addEventListener('click', function(e) {
        if (e.target.closest('.remove-document')) {
            e.target.closest('.document-item').remove();
        }
    });
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>
<?php require_once 'includes/footer.php'; ?>
