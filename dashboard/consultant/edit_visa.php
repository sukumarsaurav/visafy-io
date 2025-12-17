<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Edit Visa";
$page_specific_css = "assets/css/visa.css";
require_once 'includes/header.php';

// Get visa ID from URL
$visa_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($visa_id <= 0) {
    header("Location: visa.php");
    exit;
}

// Get visa details
$query = "SELECT v.*, c.country_name 
          FROM visas v 
          JOIN countries c ON v.country_id = c.country_id 
          WHERE v.visa_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $visa_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: visa.php");
    exit;
}

$visa = $result->fetch_assoc();
$stmt->close();

// Get all active countries for dropdown
$countries_query = "SELECT country_id, country_name, country_code 
                   FROM countries 
                   WHERE is_active = 1 
                   ORDER BY country_name ASC";
$stmt = $conn->prepare($countries_query);
$stmt->execute();
$countries_result = $stmt->get_result();
$countries = [];
while ($row = $countries_result->fetch_assoc()) {
    $countries[] = $row;
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $country_id = $_POST['country_id'];
    $visa_type = trim($_POST['visa_type']);
    $description = trim($_POST['description']);
    $validity_period = !empty($_POST['validity_period']) ? intval($_POST['validity_period']) : null;
    $fee = !empty($_POST['fee']) ? floatval($_POST['fee']) : null;
    $requirements = trim($_POST['requirements']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $inactive_reason = !$is_active ? trim($_POST['inactive_reason']) : null;
    
    // Validate inputs
    $errors = [];
    if (empty($country_id)) {
        $errors[] = "Please select a country";
    }
    if (empty($visa_type)) {
        $errors[] = "Visa type is required";
    }
    if (!$is_active && empty($inactive_reason)) {
        $errors[] = "Please provide a reason for deactivation";
    }
    
    if (empty($errors)) {
        // Update visa
        $update_query = "UPDATE visas SET 
                        country_id = ?, 
                        visa_type = ?, 
                        description = ?, 
                        validity_period = ?, 
                        fee = ?, 
                        requirements = ?, 
                        is_active = ?, 
                        inactive_reason = ?,
                        inactive_since = CASE 
                            WHEN is_active = 1 AND ? = 0 THEN CURRENT_DATE
                            ELSE inactive_since 
                        END
                        WHERE visa_id = ?";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('issiissii', 
            $country_id, 
            $visa_type, 
            $description, 
            $validity_period, 
            $fee, 
            $requirements, 
            $is_active, 
            $inactive_reason,
            $is_active,
            $visa_id
        );
        
        if ($stmt->execute()) {
            $success_message = "Visa updated successfully";
            $stmt->close();
            
            // Refresh visa data
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $visa_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $visa = $result->fetch_assoc();
            $stmt->close();
        } else {
            $error_message = "Error updating visa: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Edit Visa</h1>
            <p>Update details for <?php echo htmlspecialchars($visa['visa_type']); ?> visa</p>
        </div>
        <div class="action-buttons">
            <a href="visa_details.php?id=<?php echo $visa_id; ?>" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Details
            </a>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <div class="edit-form-container">
        <form action="edit_visa.php?id=<?php echo $visa_id; ?>" method="POST" class="edit-form">
            <div class="form-grid">
                <!-- Basic Information -->
                <div class="form-section">
                    <h2>Basic Information</h2>
                    
                    <div class="form-group">
                        <label for="country_id">Country*</label>
                        <select name="country_id" id="country_id" class="form-control" required>
                            <option value="">Select Country</option>
                            <?php foreach ($countries as $country): ?>
                                <option value="<?php echo $country['country_id']; ?>" 
                                        <?php echo $country['country_id'] == $visa['country_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($country['country_name']); ?> 
                                    (<?php echo htmlspecialchars($country['country_code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="visa_type">Visa Type*</label>
                        <input type="text" name="visa_type" id="visa_type" class="form-control" 
                               value="<?php echo htmlspecialchars($visa['visa_type']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="4"><?php echo htmlspecialchars($visa['description']); ?></textarea>
                    </div>
                </div>

                <!-- Requirements and Validity -->
                <div class="form-section">
                    <h2>Requirements and Validity</h2>
                    
                    <div class="form-group">
                        <label for="requirements">Requirements</label>
                        <textarea name="requirements" id="requirements" class="form-control" rows="4"><?php echo htmlspecialchars($visa['requirements']); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="validity_period">Validity Period (days)</label>
                            <input type="number" name="validity_period" id="validity_period" class="form-control" 
                                   value="<?php echo $visa['validity_period']; ?>" min="1">
                        </div>

                        <div class="form-group">
                            <label for="fee">Base Fee ($)</label>
                            <input type="number" name="fee" id="fee" class="form-control" 
                                   value="<?php echo $visa['fee']; ?>" min="0" step="0.01">
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="form-section">
                    <h2>Status</h2>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="is_active" value="1" 
                                   <?php echo $visa['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active">Active</label>
                        </div>
                    </div>

                    <div class="form-group" id="inactiveReasonGroup" style="display: <?php echo $visa['is_active'] ? 'none' : 'block'; ?>;">
                        <label for="inactive_reason">Reason for Deactivation</label>
                        <textarea name="inactive_reason" id="inactive_reason" class="form-control" rows="3"><?php echo htmlspecialchars($visa['inactive_reason']); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-buttons">
                <button type="button" class="btn secondary-btn" onclick="window.location.href='visa_details.php?id=<?php echo $visa_id; ?>'">
                    Cancel
                </button>
                <button type="submit" class="btn primary-btn">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.edit-form-container {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    margin-top: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.form-section {
    padding: 20px;
    background-color: var(--light-color);
    border-radius: 6px;
}

.form-section h2 {
    color: var(--primary-color);
    font-size: 1.2rem;
    margin: 0 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
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

.form-row {
    display: flex;
    gap: 15px;
}

.form-row .form-group {
    flex: 1;
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
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
}
</style>

<script>
document.getElementById('is_active').addEventListener('change', function() {
    const inactiveReasonGroup = document.getElementById('inactiveReasonGroup');
    const inactiveReason = document.getElementById('inactive_reason');
    
    if (this.checked) {
        inactiveReasonGroup.style.display = 'none';
        inactiveReason.value = '';
    } else {
        inactiveReasonGroup.style.display = 'block';
    }
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
