<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Booking Settings";
$page_specific_css = "assets/css/booking_settings.css";
require_once 'includes/header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = true;
    $error_message = '';
    
    try {
        // Update booking settings
        $update_query = "UPDATE booking_settings SET 
                       booking_interval = ?,
                       min_advance_booking = ?,
                       max_advance_booking = ?,
                       buffer_time = ?,
                       allow_same_day_booking = ?,
                       require_confirmation = ?,
                       send_reminder = ?,
                       reminder_time = ?,
                       cancellation_policy = ?,
                       organization_id = ?
                       WHERE organization_id = ?";
        
        $stmt = $conn->prepare($update_query);
        
        $booking_interval = intval($_POST['booking_interval']);
        $min_advance_booking = intval($_POST['min_advance_booking']);
        $max_advance_booking = intval($_POST['max_advance_booking']);
        $buffer_time = intval($_POST['buffer_time']);
        $allow_same_day_booking = isset($_POST['allow_same_day_booking']) ? 1 : 0;
        $require_confirmation = isset($_POST['require_confirmation']) ? 1 : 0;
        $send_reminder = isset($_POST['send_reminder']) ? 1 : 0;
        $reminder_time = $_POST['reminder_time'];
        $cancellation_policy = trim($_POST['cancellation_policy']);
        
        $stmt->bind_param('iiiiiiissii', 
                         $booking_interval, 
                         $min_advance_booking, 
                         $max_advance_booking, 
                         $buffer_time, 
                         $allow_same_day_booking, 
                         $require_confirmation, 
                         $send_reminder, 
                         $reminder_time, 
                         $cancellation_policy, 
                         $_SESSION['organization_id'],
                         $_SESSION['organization_id']);
        
        if ($stmt->execute()) {
            $success_message = "Booking settings updated successfully.";
        } else {
            throw new Exception("Error updating booking settings: " . $conn->error);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $success = false;
    }
}

// Get current booking settings
$query = "SELECT * FROM booking_settings WHERE organization_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $_SESSION['organization_id']);
$stmt->execute();
$result = $stmt->get_result();
$settings = $result->fetch_assoc();

// If no settings exist, create default settings
if (!$settings) {
    $insert_query = "INSERT INTO booking_settings (
                    booking_interval, 
                    min_advance_booking, 
                    max_advance_booking, 
                    buffer_time, 
                    allow_same_day_booking, 
                    require_confirmation, 
                    send_reminder, 
                    reminder_time, 
                    cancellation_policy, 
                    organization_id
                ) VALUES (30, 60, 30, 15, 1, 0, 1, '24:00:00', 'Cancellations must be made at least 24 hours in advance.', ?)";
    
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param('i', $_SESSION['organization_id']);
    $stmt->execute();
    $stmt->close();
    
    // Fetch the newly created settings
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $_SESSION['organization_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $settings = $result->fetch_assoc();
}
$stmt->close();
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Booking Settings</h1>
            <p>Configure your booking preferences</p>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <form action="booking_settings.php" method="POST" id="settingsForm">
            <div class="settings-grid">
                <div class="settings-section">
                    <h2>Time Slots</h2>
                    
                    <div class="form-group">
                        <label for="booking_interval">Booking Interval (minutes)*</label>
                        <select id="booking_interval" name="booking_interval" class="form-control" required>
                            <option value="15" <?php echo $settings['booking_interval'] == 15 ? 'selected' : ''; ?>>15 minutes</option>
                            <option value="30" <?php echo $settings['booking_interval'] == 30 ? 'selected' : ''; ?>>30 minutes</option>
                            <option value="45" <?php echo $settings['booking_interval'] == 45 ? 'selected' : ''; ?>>45 minutes</option>
                            <option value="60" <?php echo $settings['booking_interval'] == 60 ? 'selected' : ''; ?>>1 hour</option>
                        </select>
                        <small>Time slots will be divided into intervals of this length</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="buffer_time">Buffer Time Between Appointments (minutes)*</label>
                        <input type="number" id="buffer_time" name="buffer_time" class="form-control" 
                               value="<?php echo $settings['buffer_time']; ?>" min="0" max="60" required>
                        <small>Time to prepare between appointments</small>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h2>Booking Window</h2>
                    
                    <div class="form-group">
                        <label for="min_advance_booking">Minimum Advance Booking (minutes)*</label>
                        <input type="number" id="min_advance_booking" name="min_advance_booking" class="form-control" 
                               value="<?php echo $settings['min_advance_booking']; ?>" min="0" required>
                        <small>Minimum time before an appointment can be booked</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_advance_booking">Maximum Advance Booking (days)*</label>
                        <input type="number" id="max_advance_booking" name="max_advance_booking" class="form-control" 
                               value="<?php echo $settings['max_advance_booking']; ?>" min="1" max="365" required>
                        <small>Maximum number of days in advance a booking can be made</small>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="allow_same_day_booking" name="allow_same_day_booking" 
                               <?php echo $settings['allow_same_day_booking'] ? 'checked' : ''; ?>>
                        <label for="allow_same_day_booking">Allow Same-Day Bookings</label>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h2>Notifications</h2>
                    
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="require_confirmation" name="require_confirmation" 
                               <?php echo $settings['require_confirmation'] ? 'checked' : ''; ?>>
                        <label for="require_confirmation">Require Confirmation for New Bookings</label>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="send_reminder" name="send_reminder" 
                               <?php echo $settings['send_reminder'] ? 'checked' : ''; ?>>
                        <label for="send_reminder">Send Reminder Emails</label>
                    </div>
                    
                    <div class="form-group" id="reminder_time_group" style="display: <?php echo $settings['send_reminder'] ? 'block' : 'none'; ?>">
                        <label for="reminder_time">Reminder Time</label>
                        <select id="reminder_time" name="reminder_time" class="form-control">
                            <option value="24:00:00" <?php echo $settings['reminder_time'] == '24:00:00' ? 'selected' : ''; ?>>24 hours before</option>
                            <option value="12:00:00" <?php echo $settings['reminder_time'] == '12:00:00' ? 'selected' : ''; ?>>12 hours before</option>
                            <option value="06:00:00" <?php echo $settings['reminder_time'] == '06:00:00' ? 'selected' : ''; ?>>6 hours before</option>
                            <option value="03:00:00" <?php echo $settings['reminder_time'] == '03:00:00' ? 'selected' : ''; ?>>3 hours before</option>
                            <option value="01:00:00" <?php echo $settings['reminder_time'] == '01:00:00' ? 'selected' : ''; ?>>1 hour before</option>
                        </select>
                    </div>
                </div>
                
                <div class="settings-section full-width">
                    <h2>Cancellation Policy</h2>
                    
                    <div class="form-group">
                        <label for="cancellation_policy">Policy Text*</label>
                        <textarea id="cancellation_policy" name="cancellation_policy" class="form-control" rows="4" required><?php echo htmlspecialchars($settings['cancellation_policy']); ?></textarea>
                        <small>This policy will be shown to clients when they make a booking</small>
                    </div>
                </div>
            </div>
            
            <div class="form-buttons">
                <button type="submit" class="btn submit-btn">Save Settings</button>
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

.settings-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 30px;
}

.settings-section {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.settings-section.full-width {
    grid-column: 1 / -1;
}

.settings-section h2 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.2rem;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.form-group label {
    font-weight: 500;
    color: var(--dark-color);
}

.form-control {
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb), 0.1);
}

select.form-control {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23666' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    padding-right: 30px;
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.checkbox-group input[type="checkbox"] {
    margin: 0;
}

small {
    color: var(--secondary-color);
    font-size: 12px;
}

.form-buttons {
    margin-top: 30px;
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
    .settings-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle reminder time select when send_reminder checkbox changes
    document.getElementById('send_reminder').addEventListener('change', function() {
        document.getElementById('reminder_time_group').style.display = this.checked ? 'block' : 'none';
    });
    
    // Form validation
    document.getElementById('settingsForm').addEventListener('submit', function(e) {
        const minAdvance = parseInt(document.getElementById('min_advance_booking').value);
        const maxAdvance = parseInt(document.getElementById('max_advance_booking').value);
        
        if (minAdvance >= maxAdvance * 24 * 60) {
            e.preventDefault();
            alert('Minimum advance booking time must be less than maximum advance booking time.');
        }
    });
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
