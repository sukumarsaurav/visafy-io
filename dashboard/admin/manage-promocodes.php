<?php
// Start output buffering
ob_start();

$page_title = "Manage Promo Codes";
require_once 'includes/header.php';

// Initialize variables
$success_message = '';
$error_message = '';
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_promo']) || isset($_POST['update_promo'])) {
        // Get form data
        $code = trim($_POST['code']);
        $description = trim($_POST['description']);
        $discount_type = $_POST['discount_type'];
        $discount_value = (float)$_POST['discount_value'];
        $start_date = $_POST['start_date'];
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $max_uses = !empty($_POST['max_uses']) ? (int)$_POST['max_uses'] : null;
        $min_plan_price = !empty($_POST['min_plan_price']) ? (float)$_POST['min_plan_price'] : null;
        $applicable_plans = isset($_POST['applicable_plans']) ? implode(',', $_POST['applicable_plans']) : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validate inputs
        $errors = [];
        if (empty($code)) $errors[] = "Promo code is required";
        if (empty($description)) $errors[] = "Description is required";
        if ($discount_value <= 0) $errors[] = "Discount value must be greater than 0";
        if ($discount_type === 'percentage' && $discount_value > 100) {
            $errors[] = "Percentage discount cannot exceed 100%";
        }
        if (empty($start_date)) $errors[] = "Start date is required";
        
        if (empty($errors)) {
            if (isset($_POST['add_promo'])) {
                // Check if code already exists
                $check_query = "SELECT id FROM promo_codes WHERE code = ?";
                $stmt = $conn->prepare($check_query);
                $stmt->bind_param('s', $code);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $error_message = "Promo code already exists";
                } else {
                    // Insert new promo code
                    $insert_query = "INSERT INTO promo_codes (code, description, discount_type, discount_value, 
                                   start_date, end_date, max_uses, min_plan_price, applicable_plans, is_active, created_by) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($insert_query);
                    $stmt->bind_param('sssdssiisii', $code, $description, $discount_type, $discount_value, 
                                    $start_date, $end_date, $max_uses, $min_plan_price, $applicable_plans, $is_active, $_SESSION['id']);
                    
                    if ($stmt->execute()) {
                        $success_message = "Promo code added successfully";
                    } else {
                        $error_message = "Error adding promo code: " . $conn->error;
                    }
                }
            } else {
                // Update existing promo code
                $promo_id = (int)$_POST['promo_id'];
                $update_query = "UPDATE promo_codes SET code = ?, description = ?, discount_type = ?, 
                               discount_value = ?, start_date = ?, end_date = ?, max_uses = ?, 
                               min_plan_price = ?, applicable_plans = ?, is_active = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param('sssdssiisii', $code, $description, $discount_type, $discount_value, 
                                $start_date, $end_date, $max_uses, $min_plan_price, $applicable_plans, $is_active, $promo_id);
                
                if ($stmt->execute()) {
                    $success_message = "Promo code updated successfully";
                    $edit_id = 0; // Reset edit mode
                } else {
                    $error_message = "Error updating promo code: " . $conn->error;
                }
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
    }
    
    // Handle delete
    if (isset($_POST['delete_promo'])) {
        $promo_id = (int)$_POST['promo_id'];
        $delete_query = "DELETE FROM promo_codes WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param('i', $promo_id);
        
        if ($stmt->execute()) {
            $success_message = "Promo code deleted successfully";
        } else {
            $error_message = "Error deleting promo code: " . $conn->error;
        }
    }
}

// Get membership plans for dropdown
$plans_query = "SELECT id, name, price FROM membership_plans ORDER BY price ASC";
$plans_result = $conn->query($plans_query);
$membership_plans = [];
while ($plan = $plans_result->fetch_assoc()) {
    $membership_plans[] = $plan;
}

// Get promo code being edited
$edit_promo = null;
if ($edit_id > 0) {
    $edit_query = "SELECT * FROM promo_codes WHERE id = ?";
    $stmt = $conn->prepare($edit_query);
    $stmt->bind_param('i', $edit_id);
    $stmt->execute();
    $edit_promo = $stmt->get_result()->fetch_assoc();
}

// Get all promo codes with usage stats
$promo_codes_query = "SELECT pc.*, 
                      COUNT(pcu.id) as total_uses,
                      SUM(pcu.discount_amount) as total_discount_amount,
                      CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                      FROM promo_codes pc
                      LEFT JOIN promo_code_usage pcu ON pc.id = pcu.promo_code_id
                      LEFT JOIN users u ON pc.created_by = u.id
                      GROUP BY pc.id
                      ORDER BY pc.created_at DESC";
$promo_codes_result = $conn->query($promo_codes_query);
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1><i class="fas fa-tags"></i> Manage Promo Codes</h1>
            <p>Create and manage promotional codes for membership plans</p>
        </div>
    </div>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <!-- Promo Code Form -->
    <div class="card-container">
        <div class="action-card full-width">
            <div class="action-card-header">
                <h5><?php echo $edit_id ? 'Edit Promo Code' : 'Add New Promo Code'; ?></h5>
            </div>
            <div class="action-card-body">
                <form action="manage-promocodes.php<?php echo $edit_id ? "?edit=$edit_id" : ''; ?>" method="POST" class="promo-form">
                    <?php if ($edit_id): ?>
                        <input type="hidden" name="promo_id" value="<?php echo $edit_id; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="code">Promo Code*</label>
                            <input type="text" id="code" name="code" class="form-control" required 
                                   value="<?php echo $edit_promo ? htmlspecialchars((string)($edit_promo['code'] ?? '')) : ''; ?>"
                                   <?php echo $edit_id ? 'readonly' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label for="description">Description*</label>
                            <input type="text" id="description" name="description" class="form-control" required
                                   value="<?php echo $edit_promo ? htmlspecialchars((string)($edit_promo['description'] ?? '')) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="discount_type">Discount Type*</label>
                            <select id="discount_type" name="discount_type" class="form-control" required>
                                <option value="percentage" <?php echo ($edit_promo && $edit_promo['discount_type'] === 'percentage') ? 'selected' : ''; ?>>Percentage</option>
                                <option value="fixed" <?php echo ($edit_promo && $edit_promo['discount_type'] === 'fixed') ? 'selected' : ''; ?>>Fixed Amount</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="discount_value">Discount Value*</label>
                            <input type="number" id="discount_value" name="discount_value" class="form-control" required step="0.01" min="0"
                                   value="<?php echo $edit_promo ? htmlspecialchars((string)($edit_promo['discount_value'] ?? '')) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">Start Date*</label>
                            <input type="datetime-local" id="start_date" name="start_date" class="form-control" required
                                   value="<?php echo $edit_promo && !empty($edit_promo['start_date']) ? date('Y-m-d\TH:i', strtotime($edit_promo['start_date'])) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date (Optional)</label>
                            <input type="datetime-local" id="end_date" name="end_date" class="form-control"
                                   value="<?php echo ($edit_promo && !empty($edit_promo['end_date'])) ? date('Y-m-d\TH:i', strtotime($edit_promo['end_date'])) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="max_uses">Maximum Uses (Optional)</label>
                            <input type="number" id="max_uses" name="max_uses" class="form-control" min="1"
                                   value="<?php echo $edit_promo && !empty($edit_promo['max_uses']) ? htmlspecialchars((string)$edit_promo['max_uses']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="min_plan_price">Minimum Plan Price (Optional)</label>
                            <input type="number" id="min_plan_price" name="min_plan_price" class="form-control" step="0.01" min="0"
                                   value="<?php echo $edit_promo && !empty($edit_promo['min_plan_price']) ? htmlspecialchars((string)$edit_promo['min_plan_price']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Applicable Plans (Optional)</label>
                        <div class="checkbox-group">
                            <?php foreach ($membership_plans as $plan): ?>
                                <label class="checkbox-inline">
                                    <input type="checkbox" name="applicable_plans[]" value="<?php echo $plan['id']; ?>"
                                           <?php echo ($edit_promo && !empty($edit_promo['applicable_plans']) && strpos($edit_promo['applicable_plans'], (string)$plan['id']) !== false) ? 'checked' : ''; ?>>
                                    <?php echo htmlspecialchars($plan['name']); ?> ($<?php echo number_format($plan['price'], 2); ?>)
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-inline">
                            <input type="checkbox" name="is_active" value="1" 
                                   <?php echo (!$edit_promo || $edit_promo['is_active']) ? 'checked' : ''; ?>>
                            Active
                        </label>
                    </div>
                    
                    <div class="form-buttons">
                        <?php if ($edit_id): ?>
                            <a href="manage-promocodes.php" class="btn secondary-btn">Cancel</a>
                            <button type="submit" name="update_promo" class="btn primary-btn">Update Promo Code</button>
                        <?php else: ?>
                            <button type="submit" name="add_promo" class="btn primary-btn">Add Promo Code</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Promo Codes List -->
    <div class="card-container">
        <div class="action-card full-width">
            <div class="action-card-header">
                <h5>All Promo Codes</h5>
                <p>View and manage existing promotional codes</p>
            </div>
            <div class="action-card-body">
                <div class="table-responsive">
                    <table class="table" id="promoCodesTable">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Description</th>
                                <th>Discount</th>
                                <th>Validity</th>
                                <th>Usage</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($promo = $promo_codes_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($promo['code'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($promo['description'] ?? ''); ?></td>
                                    <td>
                                        <?php if ($promo['discount_type'] === 'percentage'): ?>
                                            <?php echo isset($promo['discount_value']) ? number_format((float)$promo['discount_value'], 0) : '0'; ?>%
                                        <?php else: ?>
                                            $<?php echo isset($promo['discount_value']) ? number_format((float)$promo['discount_value'], 2) : '0.00'; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($promo['start_date'])) {
                                            echo date('M j, Y', strtotime($promo['start_date']));
                                            if (!empty($promo['end_date'])) {
                                                echo ' - ' . date('M j, Y', strtotime($promo['end_date']));
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo isset($promo['total_uses']) ? number_format((int)$promo['total_uses']) : '0';
                                        if (!empty($promo['max_uses'])) {
                                            echo ' / ' . number_format((int)$promo['max_uses']);
                                        }
                                        if (isset($promo['total_discount_amount']) && $promo['total_discount_amount'] !== null) {
                                            echo '<br><small>Total savings: $' . number_format((float)$promo['total_discount_amount'], 2) . '</small>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $promo['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $promo['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($promo['created_by_name'] ?? 'Unknown'); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="manage-promocodes.php?edit=<?php echo $promo['id']; ?>" class="btn btn-sm btn-edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="manage-promocodes.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this promo code?');">
                                                <input type="hidden" name="promo_id" value="<?php echo $promo['id']; ?>">
                                                <button type="submit" name="delete_promo" class="btn btn-sm btn-delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
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
    --info-color: #36b9cc;
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

.card-container {
    margin-bottom: 20px;
}

.action-card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.full-width {
    width: 100%;
}

.action-card-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--light-color);
}

.action-card-header h5 {
    margin: 0;
    color: var(--primary-color);
    font-size: 16px;
    font-weight: 600;
}

.action-card-body {
    padding: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    color: var(--dark-color);
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.form-control:focus {
    border-color: var(--primary-color);
    outline: none;
}

.checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 5px;
}

.checkbox-inline {
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--dark-color);
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 15px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: background-color 0.2s;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
}

.secondary-btn {
    background-color: var(--secondary-color);
    color: white;
    border: none;
}

.primary-btn:hover {
    background-color: #031c56;
}

.secondary-btn:hover {
    background-color: #717484;
}

.form-buttons {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    text-align: left;
    padding: 12px;
    border-bottom: 2px solid var(--border-color);
}

.table td {
    padding: 12px;
    border-bottom: 1px solid var(--border-color);
    color: var(--dark-color);
}

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
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

.action-buttons {
    display: flex;
    gap: 5px;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 14px;
}

.btn-edit {
    color: var(--primary-color);
    background: rgba(78, 115, 223, 0.1);
    border: none;
}

.btn-delete {
    color: var(--danger-color);
    background: rgba(231, 74, 59, 0.1);
    border: none;
}

.btn-edit:hover {
    background: rgba(78, 115, 223, 0.2);
}

.btn-delete:hover {
    background: rgba(231, 74, 59, 0.2);
}

.alert {
    padding: 12px 20px;
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

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .table-responsive {
        overflow-x: auto;
    }
}

/* DataTables customization */
.dataTables_wrapper .dataTables_length select {
    padding: 5px;
    border-radius: 4px;
    border: 1px solid var(--border-color);
}

.dataTables_wrapper .dataTables_filter input {
    padding: 5px 10px;
    border-radius: 4px;
    border: 1px solid var(--border-color);
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: 5px 10px;
    margin: 0 2px;
    border-radius: 4px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: var(--primary-color);
    color: white !important;
    border: none;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTables
    $('#promoCodesTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        responsive: true
    });
    
    // Discount type change handler
    const discountTypeSelect = document.getElementById('discount_type');
    const discountValueInput = document.getElementById('discount_value');
    
    if (discountTypeSelect && discountValueInput) {
        discountTypeSelect.addEventListener('change', function() {
            if (this.value === 'percentage') {
                discountValueInput.max = '100';
                if (parseFloat(discountValueInput.value) > 100) {
                    discountValueInput.value = '100';
                }
            } else {
                discountValueInput.removeAttribute('max');
            }
        });
        
        // Trigger change event on load
        discountTypeSelect.dispatchEvent(new Event('change'));
    }
});
</script>

<?php
require_once 'includes/footer.php';
ob_end_flush();
?>