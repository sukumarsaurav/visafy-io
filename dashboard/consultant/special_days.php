<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Special Days & Holidays";
$page_specific_css = "assets/css/special_days.css";
require_once 'includes/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_special_day'])) {
        $date = $_POST['date'];
        $description = trim($_POST['description']);
        $is_closed = isset($_POST['is_closed']) ? 1 : 0;
        $alternative_open_time = $is_closed ? null : $_POST['alternative_open_time'];
        $alternative_close_time = $is_closed ? null : $_POST['alternative_close_time'];
        
        // Validate inputs
        if (empty($date) || empty($description)) {
            $error_message = "Date and description are required.";
        } else {
            // Check if special day already exists
            $check_query = "SELECT id FROM special_days WHERE date = ? AND organization_id = ?";
            $stmt = $conn->prepare($check_query);
            $stmt->bind_param('si', $date, $_SESSION['organization_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "A special day already exists for this date.";
            } else {
                // Insert new special day
                $insert_query = "INSERT INTO special_days (date, description, is_closed, alternative_open_time, 
                               alternative_close_time, organization_id) 
                               VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param('ssisss', $date, $description, $is_closed, 
                                $alternative_open_time, $alternative_close_time, $_SESSION['organization_id']);
                
                if ($stmt->execute()) {
                    $success_message = "Special day added successfully.";
                } else {
                    $error_message = "Error adding special day: " . $conn->error;
                }
            }
            $stmt->close();
        }
    } elseif (isset($_POST['update_special_day'])) {
        $id = $_POST['special_day_id'];
        $description = trim($_POST['description']);
        $is_closed = isset($_POST['is_closed']) ? 1 : 0;
        $alternative_open_time = $is_closed ? null : $_POST['alternative_open_time'];
        $alternative_close_time = $is_closed ? null : $_POST['alternative_close_time'];
        
        if (empty($description)) {
            $error_message = "Description is required.";
        } else {
            $update_query = "UPDATE special_days SET description = ?, is_closed = ?, 
                           alternative_open_time = ?, alternative_close_time = ? 
                           WHERE id = ? AND organization_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param('sisssi', $description, $is_closed, 
                            $alternative_open_time, $alternative_close_time, 
                            $id, $_SESSION['organization_id']);
            
            if ($stmt->execute()) {
                $success_message = "Special day updated successfully.";
            } else {
                $error_message = "Error updating special day: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Handle deletion
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    
    $delete_query = "DELETE FROM special_days WHERE id = ? AND organization_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param('ii', $id, $_SESSION['organization_id']);
    
    if ($stmt->execute()) {
        $success_message = "Special day deleted successfully.";
    } else {
        $error_message = "Error deleting special day: " . $conn->error;
    }
    $stmt->close();
}

// Get all special days
$query = "SELECT * FROM special_days WHERE organization_id = ? ORDER BY date";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $_SESSION['organization_id']);
$stmt->execute();
$result = $stmt->get_result();
$special_days = [];

while ($row = $result->fetch_assoc()) {
    $special_days[] = $row;
}
$stmt->close();
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Special Days & Holidays</h1>
            <p>Manage holidays and special operating hours</p>
        </div>
        <div>
            <button type="button" class="btn primary-btn" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add Special Day
            </button>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Alternative Hours</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($special_days)): ?>
                    <tr>
                        <td colspan="5" class="text-center">No special days or holidays set</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($special_days as $day): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($day['date'])); ?></td>
                            <td><?php echo htmlspecialchars($day['description']); ?></td>
                            <td>
                                <?php if ($day['is_closed']): ?>
                                    <span class="status-badge inactive">Closed</span>
                                <?php else: ?>
                                    <span class="status-badge active">Open (Modified Hours)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$day['is_closed']): ?>
                                    <?php echo substr($day['alternative_open_time'], 0, 5); ?> - 
                                    <?php echo substr($day['alternative_close_time'], 0, 5); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <button type="button" class="btn-action btn-edit" 
                                        onclick="editSpecialDay(<?php echo $day['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn-action btn-delete" 
                                        onclick="deleteSpecialDay(<?php echo $day['id']; ?>, '<?php echo date('M d, Y', strtotime($day['date'])); ?>')">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Special Day Modal -->
<div class="modal" id="specialDayModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Special Day</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="special_days.php" method="POST" id="specialDayForm">
                    <input type="hidden" name="special_day_id" id="special_day_id" value="">
                    
                    <div class="form-group">
                        <label for="date">Date*</label>
                        <input type="date" name="date" id="date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description*</label>
                        <input type="text" name="description" id="description" class="form-control" 
                               placeholder="e.g., Christmas Day, Company Event" required>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <input type="checkbox" name="is_closed" id="is_closed" value="1" checked>
                        <label for="is_closed">Office Closed</label>
                    </div>
                    
                    <div id="alternative_hours" style="display: none;">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="alternative_open_time">Open Time*</label>
                                <input type="time" name="alternative_open_time" id="alternative_open_time" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="alternative_close_time">Close Time*</label>
                                <input type="time" name="alternative_close_time" id="alternative_close_time" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_special_day" class="btn submit-btn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
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

.table-responsive {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th, .table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    color: white;
}

.status-badge.active {
    background-color: var(--success-color);
}

.status-badge.inactive {
    background-color: var(--danger-color);
}

.actions-cell {
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
    color: white;
}

.btn-edit {
    background-color: var(--warning-color);
}

.btn-delete {
    background-color: var(--danger-color);
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
}

.modal-dialog {
    margin: 80px auto;
    max-width: 500px;
}

.modal-content {
    background-color: white;
    border-radius: 8px;
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

.btn {
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    border: none;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    gap: 8px;
}

.cancel-btn {
    background-color: white;
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
}

.submit-btn {
    background-color: var(--primary-color);
    color: white;
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
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .table-responsive {
        overflow-x: auto;
    }
    
    .modal-dialog {
        margin: 60px 15px;
    }
}
</style>

<script>
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

// Open add modal
function openAddModal() {
    document.getElementById('special_day_id').value = '';
    document.getElementById('specialDayForm').reset();
    document.querySelector('#specialDayModal .modal-title').textContent = 'Add Special Day';
    
    // Set default date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('date').value = tomorrow.toISOString().split('T')[0];
    
    openModal('specialDayModal');
}

// Toggle alternative hours when is_closed changes
document.getElementById('is_closed').addEventListener('change', function() {
    const alternativeHours = document.getElementById('alternative_hours');
    alternativeHours.style.display = this.checked ? 'none' : 'block';
    
    const timeInputs = document.querySelectorAll('#alternative_open_time, #alternative_close_time');
    timeInputs.forEach(function(input) {
        input.required = !this.checked;
    }, this);
});

// Edit special day
function editSpecialDay(id) {
    fetch('ajax/get_special_day.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('special_day_id').value = data.id;
            document.getElementById('date').value = data.date;
            document.getElementById('description').value = data.description;
            document.getElementById('is_closed').checked = data.is_closed == 1;
            
            if (data.is_closed != 1) {
                document.getElementById('alternative_hours').style.display = 'block';
                document.getElementById('alternative_open_time').value = data.alternative_open_time.substring(0, 5);
                document.getElementById('alternative_close_time').value = data.alternative_close_time.substring(0, 5);
            } else {
                document.getElementById('alternative_hours').style.display = 'none';
            }
            
            document.querySelector('#specialDayModal .modal-title').textContent = 'Edit Special Day';
            document.querySelector('button[name="add_special_day"]').name = 'update_special_day';
            openModal('specialDayModal');
        })
        .catch(error => {
            console.error('Error fetching special day:', error);
            alert('Error loading special day data.');
        });
}

// Delete special day
function deleteSpecialDay(id, date) {
    if (confirm('Are you sure you want to delete the special day for ' + date + '?')) {
        window.location.href = 'special_days.php?delete_id=' + id;
    }
}
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
