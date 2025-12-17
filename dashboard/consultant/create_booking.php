<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Create Booking";
$page_specific_css = "assets/css/create_booking.css";
require_once 'includes/header.php';

// Get all visa services for this organization
$services_query = "SELECT vs.*, v.visa_type, c.country_name, st.service_name 
                  FROM visa_services vs
                  JOIN visas v ON vs.visa_id = v.visa_id
                  JOIN countries c ON v.country_id = c.country_id
                  JOIN service_types st ON vs.service_type_id = st.service_type_id
                  WHERE vs.organization_id = ? 
                  AND vs.is_active = 1 
                  AND vs.is_bookable = 1
                  ORDER BY c.country_name, v.visa_type, st.service_name";
$stmt = $conn->prepare($services_query);
$stmt->bind_param('i', $_SESSION['organization_id']);
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all staff members (consultants)
$staff_query = "SELECT u.id, u.first_name, u.last_name, c.company_name 
                FROM users u
                JOIN consultants c ON u.id = c.user_id
                WHERE u.organization_id = ? 
                AND u.user_type = 'consultant'
                AND u.status = 'active'
                ORDER BY u.first_name, u.last_name";
$stmt = $conn->prepare($staff_query);
$stmt->bind_param('i', $_SESSION['organization_id']);
$stmt->execute();
$staff = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name = trim($_POST['client_name']);
    $client_email = trim($_POST['client_email']);
    $client_phone = trim($_POST['client_phone']);
    $visa_service_id = intval($_POST['service_id']);
    $consultant_id = intval($_POST['staff_id']);
    $booking_date = $_POST['booking_date'];
    $booking_time = $_POST['booking_time'];
    $notes = trim($_POST['notes']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($client_name)) {
        $errors[] = "Client name is required.";
    }
    
    if (empty($client_email)) {
        $errors[] = "Client email is required.";
    } elseif (!filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    if (empty($client_phone)) {
        $errors[] = "Client phone is required.";
    }
    
    if ($visa_service_id <= 0) {
        $errors[] = "Please select a service.";
    }
    
    if ($consultant_id <= 0) {
        $errors[] = "Please select a consultant.";
    }
    
    if (empty($booking_date)) {
        $errors[] = "Booking date is required.";
    }
    
    if (empty($booking_time)) {
        $errors[] = "Booking time is required.";
    }
    
    // Check if the selected time slot is available
    if (empty($errors)) {
        $datetime = $booking_date . ' ' . $booking_time;
        
        // Get service duration from service_consultation_modes
        $duration_query = "SELECT duration_minutes 
                          FROM service_consultation_modes 
                          WHERE visa_service_id = ? 
                          AND consultant_id = ? 
                          LIMIT 1";
        $stmt = $conn->prepare($duration_query);
        $stmt->bind_param('ii', $visa_service_id, $consultant_id);
        $stmt->execute();
        $duration_result = $stmt->get_result()->fetch_assoc();
        $duration = $duration_result ? $duration_result['duration_minutes'] : 60; // Default to 60 minutes
        $stmt->close();
        
        // Calculate end time
        $end_datetime = date('Y-m-d H:i:s', strtotime($datetime . ' + ' . $duration . ' minutes'));
        
        // Check for overlapping bookings
        $overlap_query = "SELECT id FROM bookings 
                         WHERE consultant_id = ? 
                         AND ((booking_datetime <= ? AND end_datetime > ?) 
                         OR (booking_datetime < ? AND end_datetime >= ?)
                         OR (booking_datetime >= ? AND end_datetime <= ?))
                         AND deleted_at IS NULL";
        
        $stmt = $conn->prepare($overlap_query);
        $stmt->bind_param('issssss', $consultant_id, $end_datetime, $datetime, $datetime, $end_datetime, $datetime, $end_datetime);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "The selected time slot is not available. Please choose a different time.";
        }
        $stmt->close();
    }
    
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Get pending status ID
            $status_query = "SELECT id FROM booking_statuses WHERE name = 'pending' LIMIT 1";
            $stmt = $conn->prepare($status_query);
            $stmt->execute();
            $status_id = $stmt->get_result()->fetch_assoc()['id'];
            $stmt->close();
            
            // Insert booking
            $insert_query = "INSERT INTO bookings (
                user_id, visa_service_id, consultant_id, 
                booking_datetime, end_datetime, duration_minutes, 
                client_notes, organization_id, status_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param('iiissiiii', 
                $_SESSION['user_id'], 
                $visa_service_id, 
                $consultant_id, 
                $datetime, 
                $end_datetime, 
                $duration, 
                $notes, 
                $_SESSION['organization_id'],
                $status_id
            );
            
            if ($stmt->execute()) {
                $booking_id = $conn->insert_id;
                
                // Create activity log
                $log_query = "INSERT INTO booking_activity_logs (
                    booking_id, user_id, activity_type, description
                ) VALUES (?, ?, 'created', 'Booking created manually')";
                
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
                $log_stmt->execute();
                $log_stmt->close();
                
                // Schedule reminder
                $reminder_query = "INSERT INTO booking_reminders (
                    booking_id, reminder_type, scheduled_time
                ) VALUES (?, 'email', DATE_SUB(?, INTERVAL 24 HOUR))";
                
                $reminder_stmt = $conn->prepare($reminder_query);
                $reminder_stmt->bind_param('is', $booking_id, $datetime);
                $reminder_stmt->execute();
                $reminder_stmt->close();
                
                // Commit transaction
                $conn->commit();
                $success_message = "Booking created successfully.";
                
                // Clear form
                $_POST = array();
            } else {
                throw new Exception("Error creating booking: " . $conn->error);
            }
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = $e->getMessage();
        }
        
        $stmt->close();
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Create Booking</h1>
            <p>Schedule a new appointment</p>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <form action="create_booking.php" method="POST" id="bookingForm">
            <div class="form-grid">
                <div class="form-group">
                    <label for="client_name">Client Name*</label>
                    <input type="text" id="client_name" name="client_name" class="form-control" 
                           value="<?php echo isset($_POST['client_name']) ? htmlspecialchars($_POST['client_name']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="client_email">Client Email*</label>
                    <input type="email" id="client_email" name="client_email" class="form-control" 
                           value="<?php echo isset($_POST['client_email']) ? htmlspecialchars($_POST['client_email']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="client_phone">Client Phone*</label>
                    <input type="tel" id="client_phone" name="client_phone" class="form-control" 
                           value="<?php echo isset($_POST['client_phone']) ? htmlspecialchars($_POST['client_phone']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="service_id">Service*</label>
                    <select id="service_id" name="service_id" class="form-control" required>
                        <option value="">Select a service</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo $service['id']; ?>" 
                                    <?php echo (isset($_POST['service_id']) && $_POST['service_id'] == $service['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($service['service_name']); ?> 
                                (<?php echo $service['duration_minutes']; ?> minutes)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="staff_id">Consultant*</label>
                    <select id="staff_id" name="staff_id" class="form-control" required>
                        <option value="">Select a consultant</option>
                        <?php foreach ($staff as $member): ?>
                            <option value="<?php echo $member['id']; ?>" 
                                    <?php echo (isset($_POST['staff_id']) && $_POST['staff_id'] == $member['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['company_name'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="booking_date">Date*</label>
                    <input type="date" id="booking_date" name="booking_date" class="form-control" 
                           value="<?php echo isset($_POST['booking_date']) ? $_POST['booking_date'] : ''; ?>" 
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="booking_time">Time*</label>
                    <input type="time" id="booking_time" name="booking_time" class="form-control" 
                           value="<?php echo isset($_POST['booking_time']) ? $_POST['booking_time'] : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group full-width">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                </div>
            </div>
            
            <div class="form-buttons">
                <button type="submit" class="btn submit-btn">Create Booking</button>
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

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.form-group.full-width {
    grid-column: 1 / -1;
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
    .form-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('booking_date').min = today;
    
    // Form validation
    document.getElementById('bookingForm').addEventListener('submit', function(e) {
        const date = document.getElementById('booking_date').value;
        const time = document.getElementById('booking_time').value;
        
        if (date && time) {
            const selectedDateTime = new Date(date + 'T' + time);
            const now = new Date();
            
            if (selectedDateTime < now) {
                e.preventDefault();
                alert('Please select a future date and time.');
            }
        }
    });
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
