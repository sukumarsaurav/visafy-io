<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Working Hours";
$page_specific_css = "assets/css/working_hours.css";
require_once 'includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = true;
    $error_message = '';
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Delete existing working hours
        $delete_query = "DELETE FROM working_hours WHERE organization_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param('i', $_SESSION['organization_id']);
        $stmt->execute();
        $stmt->close();
        
        // Insert new working hours
        $insert_query = "INSERT INTO working_hours (day_of_week, is_working_day, open_time, close_time, organization_id) 
                        VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        foreach ($days as $day) {
            $is_working_day = isset($_POST[$day . '_working']) ? 1 : 0;
            $open_time = $is_working_day ? $_POST[$day . '_open'] : null;
            $close_time = $is_working_day ? $_POST[$day . '_close'] : null;
            
            $stmt->bind_param('sisii', $day, $is_working_day, $open_time, $close_time, $_SESSION['organization_id']);
            $stmt->execute();
        }
        
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        $success_message = "Working hours updated successfully.";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error updating working hours: " . $e->getMessage();
        $success = false;
    }
}

// Get current working hours
$query = "SELECT * FROM working_hours WHERE organization_id = ? ORDER BY FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $_SESSION['organization_id']);
$stmt->execute();
$result = $stmt->get_result();
$working_hours = [];

while ($row = $result->fetch_assoc()) {
    $working_hours[$row['day_of_week']] = $row;
}
$stmt->close();
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Working Hours</h1>
            <p>Set your business operating hours</p>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <form action="working_hours.php" method="POST" id="workingHoursForm">
            <div class="working-hours-grid">
                <?php
                $days = [
                    'monday' => 'Monday',
                    'tuesday' => 'Tuesday',
                    'wednesday' => 'Wednesday',
                    'thursday' => 'Thursday',
                    'friday' => 'Friday',
                    'saturday' => 'Saturday',
                    'sunday' => 'Sunday'
                ];
                
                foreach ($days as $day_key => $day_name):
                    $day_data = $working_hours[$day_key] ?? [
                        'is_working_day' => 0,
                        'open_time' => null,
                        'close_time' => null
                    ];
                ?>
                    <div class="day-row">
                        <div class="day-name">
                            <div class="checkbox-group">
                                <input type="checkbox" 
                                       id="<?php echo $day_key; ?>_working" 
                                       name="<?php echo $day_key; ?>_working" 
                                       <?php echo $day_data['is_working_day'] ? 'checked' : ''; ?>>
                                <label for="<?php echo $day_key; ?>_working"><?php echo $day_name; ?></label>
                            </div>
                        </div>
                        <div class="time-inputs">
                            <div class="time-group">
                                <label for="<?php echo $day_key; ?>_open">Open Time</label>
                                <input type="time" 
                                       id="<?php echo $day_key; ?>_open" 
                                       name="<?php echo $day_key; ?>_open" 
                                       value="<?php echo $day_data['open_time']; ?>"
                                       <?php echo $day_data['is_working_day'] ? '' : 'disabled'; ?>>
                            </div>
                            <div class="time-group">
                                <label for="<?php echo $day_key; ?>_close">Close Time</label>
                                <input type="time" 
                                       id="<?php echo $day_key; ?>_close" 
                                       name="<?php echo $day_key; ?>_close" 
                                       value="<?php echo $day_data['close_time']; ?>"
                                       <?php echo $day_data['is_working_day'] ? '' : 'disabled'; ?>>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="form-buttons">
                <button type="submit" class="btn submit-btn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<style>
.content {
    padding: 20px;
}

.header-container {
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

.card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.working-hours-grid {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.day-row {
    display: flex;
    align-items: center;
    padding: 15px;
    border-radius: 6px;
    background-color: var(--light-color);
}

.day-name {
    width: 150px;
    flex-shrink: 0;
}

.time-inputs {
    display: flex;
    gap: 20px;
    flex-grow: 1;
}

.time-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.time-group label {
    font-size: 12px;
    color: var(--secondary-color);
}

.time-group input {
    padding: 8px;
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

.checkbox-group label {
    font-weight: 500;
    color: var(--dark-color);
}

.form-buttons {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
}

.btn {
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    border: none;
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
    .day-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .day-name {
        width: 100%;
    }
    
    .time-inputs {
        width: 100%;
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle checkbox changes
    document.querySelectorAll('input[type="checkbox"]').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const dayKey = this.id.split('_')[0];
            const timeInputs = document.querySelectorAll(`#${dayKey}_open, #${dayKey}_close`);
            
            timeInputs.forEach(function(input) {
                input.disabled = !this.checked;
                if (!this.checked) {
                    input.value = '';
                }
            }, this);
        });
    });
    
    // Form validation
    document.getElementById('workingHoursForm').addEventListener('submit', function(e) {
        const checkboxes = document.querySelectorAll('input[type="checkbox"]:checked');
        
        if (checkboxes.length === 0) {
            e.preventDefault();
            alert('Please select at least one working day.');
            return;
        }
        
        let isValid = true;
        checkboxes.forEach(function(checkbox) {
            const dayKey = checkbox.id.split('_')[0];
            const openTime = document.getElementById(`${dayKey}_open`).value;
            const closeTime = document.getElementById(`${dayKey}_close`).value;
            
            if (!openTime || !closeTime) {
                isValid = false;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Please set both open and close times for all selected working days.');
        }
    });
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
