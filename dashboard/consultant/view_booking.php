<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Booking Details";
require_once 'includes/header.php';

// Get the booking ID from the URL
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($booking_id <= 0) {
    // No valid booking ID provided, redirect back to bookings list
    header('Location: bookings.php');
    exit;
}

// Get booking details
$query = "SELECT b.*, bs.name as status_name, bs.color as status_color,
          CONCAT(u.first_name, ' ', u.last_name) as client_name, u.email as client_email, u.phone as client_phone,
          vs.visa_service_id, v.visa_type, c.country_name, st.service_name,
          cm.mode_name as consultation_mode, cm.description as mode_description,
          CONCAT(team_u.first_name, ' ', team_u.last_name) as consultant_name,
          vs.base_price, scm.additional_fee,
          (vs.base_price + IFNULL(scm.additional_fee, 0)) as total_price,
          bp.payment_status, bp.payment_method, bp.payment_date, bp.transaction_id
          FROM bookings b
          JOIN booking_statuses bs ON b.status_id = bs.id
          JOIN users u ON b.user_id = u.id
          JOIN visa_services vs ON b.visa_service_id = vs.visa_service_id
          JOIN service_consultation_modes scm ON b.service_consultation_id = scm.service_consultation_id
          JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
          JOIN visas v ON vs.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          JOIN service_types st ON vs.service_type_id = st.service_type_id
          LEFT JOIN team_members tm ON b.team_member_id = tm.id
          LEFT JOIN users team_u ON tm.member_user_id = team_u.id
          LEFT JOIN booking_payments bp ON b.id = bp.booking_id
          WHERE b.id = ? AND b.deleted_at IS NULL";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Booking not found, redirect back to bookings list
    header('Location: bookings.php');
    exit;
}

$booking = $result->fetch_assoc();
$stmt->close();

// Get all booking statuses for status update dropdown
$query = "SELECT * FROM booking_statuses ORDER BY id";
$stmt = $conn->prepare($query);
$stmt->execute();
$statuses_result = $stmt->get_result();
$booking_statuses = [];

if ($statuses_result && $statuses_result->num_rows > 0) {
    while ($row = $statuses_result->fetch_assoc()) {
        $booking_statuses[$row['id']] = $row;
    }
}
$stmt->close();

// Get all team members for assignment dropdown
$query = "SELECT tm.id, tm.member_type as role, 
          u.id as user_id, u.first_name, u.last_name, u.email 
          FROM team_members tm 
          JOIN users u ON tm.member_user_id = u.id 
          WHERE tm.invitation_status = 'accepted' AND u.status = 'active' AND u.deleted_at IS NULL
          ORDER BY u.first_name, u.last_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$team_members_result = $stmt->get_result();
$team_members = [];

if ($team_members_result && $team_members_result->num_rows > 0) {
    while ($row = $team_members_result->fetch_assoc()) {
        $team_members[$row['id']] = $row;
    }
}
$stmt->close();

// Get booking activity logs
$query = "SELECT bal.*, 
          CONCAT(u.first_name, ' ', u.last_name) as user_name
          FROM booking_activity_logs bal
          JOIN users u ON bal.user_id = u.id
          WHERE bal.booking_id = ?
          ORDER BY bal.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$activity_result = $stmt->get_result();
$activity_logs = [];

if ($activity_result && $activity_result->num_rows > 0) {
    while ($row = $activity_result->fetch_assoc()) {
        $activity_logs[] = $row;
    }
}
$stmt->close();

// Get booking documents
$query = "SELECT bd.*, 
          CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
          FROM booking_documents bd
          JOIN users u ON bd.uploaded_by = u.id
          WHERE bd.booking_id = ?
          ORDER BY bd.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$documents_result = $stmt->get_result();
$documents = [];

if ($documents_result && $documents_result->num_rows > 0) {
    while ($row = $documents_result->fetch_assoc()) {
        $documents[] = $row;
    }
}
$stmt->close();

// Handle booking status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status_id = $_POST['status_id'];
    $admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';
    
    try {
        // Get current status for logging
        $get_current_status = "SELECT status_id, admin_notes FROM bookings WHERE id = ?";
        $stmt = $conn->prepare($get_current_status);
        $stmt->bind_param('i', $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current = $result->fetch_assoc();
        $old_status_id = $current['status_id'];
        $current_notes = $current['admin_notes'] ?? '';
        $stmt->close();
        
        // Update booking status
        $update_query = "UPDATE bookings SET status_id = ?, admin_notes = CONCAT(IFNULL(admin_notes, ''), '\n[', NOW(), '] Status updated: ', ?) WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('isi', $new_status_id, $admin_notes, $booking_id);
        $stmt->execute();
        $stmt->close();
        
        // Get status names for logging
        $status_query = "SELECT name FROM booking_statuses WHERE id IN (?, ?)";
        $stmt = $conn->prepare($status_query);
        $stmt->bind_param('ii', $old_status_id, $new_status_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $statuses = $result->fetch_all(MYSQLI_ASSOC);
        $old_status_name = $statuses[0]['name'];
        $new_status_name = $statuses[1]['name'];
        $stmt->close();
        
        // Create activity log entry
        $log_description = "Status changed from " . ucfirst(str_replace('_', ' ', $old_status_name)) . 
                           " to " . ucfirst(str_replace('_', ' ', $new_status_name));
        if (!empty($admin_notes)) {
            $log_description .= ". Notes: " . $admin_notes;
        }
        
        $log_query = "INSERT INTO booking_activity_logs (booking_id, user_id, activity_type, description) VALUES (?, ?, 'status_changed', ?)";
        $stmt = $conn->prepare($log_query);
        $stmt->bind_param('iis', $booking_id, $_SESSION['id'], $log_description);
        $stmt->execute();
        $stmt->close();
        
        $success_message = "Booking status updated successfully";
        // Refresh the page to show updated data
        header("Location: view_booking.php?id=$booking_id&success=1");
        exit;
    } catch (Exception $e) {
        $error_message = "Error updating booking status: " . $e->getMessage();
    }
}

// Handle booking assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_consultant'])) {
    $consultant_id = $_POST['consultant_id'];
    
    try {
        // Update booking
        $update_query = "UPDATE bookings SET team_member_id = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ii', $consultant_id, $booking_id);
        $stmt->execute();
        $stmt->close();
        
        // Add activity log
        $consultant_name = $team_members[$consultant_id]['first_name'] . ' ' . $team_members[$consultant_id]['last_name'];
        $log_query = "INSERT INTO booking_activity_logs 
                     (booking_id, user_id, activity_type, description) 
                     VALUES (?, ?, 'assigned', ?)";
        $description = "Booking assigned to {$consultant_name}";
        
        $stmt = $conn->prepare($log_query);
        $stmt->bind_param('iis', $booking_id, $_SESSION['id'], $description);
        $stmt->execute();
        $stmt->close();
        
        $success_message = "Booking assigned successfully";
        header("Location: view_booking.php?id=$booking_id&success=2");
        exit;
    } catch (Exception $e) {
        $error_message = "Error assigning booking: " . $e->getMessage();
    }
}

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    $cancellation_reason = trim($_POST['cancellation_reason']);
    
    // Get the cancellation status ID
    $cancel_status_query = "SELECT id FROM booking_statuses WHERE name = 'cancelled_by_admin'";
    $stmt = $conn->prepare($cancel_status_query);
    $stmt->execute();
    $cancel_status = $stmt->get_result()->fetch_assoc();
    $cancel_status_id = $cancel_status['id'];
    $stmt->close();
    
    try {
        // Update booking status
        $update_query = "UPDATE bookings SET 
                        status_id = ?,
                        cancelled_by = ?, 
                        cancellation_reason = ?, 
                        cancelled_at = NOW(),
                        admin_notes = CONCAT(IFNULL(admin_notes, ''), '\n[', NOW(), '] Cancelled: ', ?)
                        WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('iissi', $cancel_status_id, $_SESSION['id'], $cancellation_reason, $cancellation_reason, $booking_id);
        $stmt->execute();
        $stmt->close();
        
        // Create activity log entry
        $log_description = "Booking cancelled by admin. Reason: " . $cancellation_reason;
        $log_query = "INSERT INTO booking_activity_logs (booking_id, user_id, activity_type, description) VALUES (?, ?, 'cancelled', ?)";
        $stmt = $conn->prepare($log_query);
        $stmt->bind_param('iis', $booking_id, $_SESSION['id'], $log_description);
        $stmt->execute();
        $stmt->close();
        
        $success_message = "Booking cancelled successfully";
        header("Location: view_booking.php?id=$booking_id&success=3");
        exit;
    } catch (Exception $e) {
        $error_message = "Error cancelling booking: " . $e->getMessage();
    }
}

// Handle rescheduling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_booking'])) {
    $new_datetime = $_POST['new_datetime'];
    $duration_minutes = $_POST['duration_minutes'];
    $reschedule_notes = trim($_POST['reschedule_notes']);
    
    // Get the rescheduled status ID
    $status_query = "SELECT id FROM booking_statuses WHERE name = 'rescheduled'";
    $stmt = $conn->prepare($status_query);
    $stmt->execute();
    $status = $stmt->get_result()->fetch_assoc();
    $status_id = $status['id'];
    $stmt->close();
    
    try {
        // Update booking status
        $update_status_query = "UPDATE bookings SET 
                        status_id = ?,
                        admin_notes = CONCAT(IFNULL(admin_notes, ''), '\n[', NOW(), '] Rescheduled: ', ?)
                        WHERE id = ?";
        $stmt = $conn->prepare($update_status_query);
        $stmt->bind_param('isi', $status_id, $reschedule_notes, $booking_id);
        $stmt->execute();
        $stmt->close();
        
        // Update additional rescheduling fields
        $update_query = "UPDATE bookings SET 
                        booking_datetime = ?,
                        duration_minutes = ?,
                        end_datetime = DATE_ADD(?, INTERVAL ? MINUTE),
                        reschedule_count = reschedule_count + 1
                        WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('sisis', $new_datetime, $duration_minutes, $new_datetime, $duration_minutes, $booking_id);
        $stmt->execute();
        $stmt->close();
        
        // Create activity log entry
        $log_description = "Booking rescheduled to " . date('Y-m-d H:i', strtotime($new_datetime)) . 
                           " for " . $duration_minutes . " minutes. Notes: " . $reschedule_notes;
        
        $log_query = "INSERT INTO booking_activity_logs (booking_id, user_id, activity_type, description) 
                      VALUES (?, ?, 'rescheduled', ?)";
        $stmt = $conn->prepare($log_query);
        $stmt->bind_param('iis', $booking_id, $_SESSION['id'], $log_description);
        $stmt->execute();
        $stmt->close();
        
        $success_message = "Booking rescheduled successfully";
        header("Location: view_booking.php?id=$booking_id&success=4");
        exit;
    } catch (Exception $e) {
        $error_message = "Error rescheduling booking: " . $e->getMessage();
    }
}

// Handle booking completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_booking'])) {
    $completion_notes = trim($_POST['completion_notes']);
    
    // Get the completed status ID
    $status_query = "SELECT id FROM booking_statuses WHERE name = 'completed'";
    $stmt = $conn->prepare($status_query);
    $stmt->execute();
    $status = $stmt->get_result()->fetch_assoc();
    $status_id = $status['id'];
    $stmt->close();
    
    try {
        // Update booking status and completion details
        $update_query = "UPDATE bookings SET 
                        status_id = ?,
                        completed_by = ?,
                        completion_notes = ?,
                        admin_notes = CONCAT(IFNULL(admin_notes, ''), '\n[', NOW(), '] Completed: ', ?)
                        WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('iissi', $status_id, $_SESSION['id'], $completion_notes, $completion_notes, $booking_id);
        $stmt->execute();
        $stmt->close();
        
        // Create activity log entry
        $log_description = "Booking marked as completed. Notes: " . $completion_notes;
        $log_query = "INSERT INTO booking_activity_logs (booking_id, user_id, activity_type, description) VALUES (?, ?, 'completed', ?)";
        $stmt = $conn->prepare($log_query);
        $stmt->bind_param('iis', $booking_id, $_SESSION['id'], $log_description);
        $stmt->execute();
        $stmt->close();
        
        $success_message = "Booking marked as completed";
        header("Location: view_booking.php?id=$booking_id&success=5");
        exit;
    } catch (Exception $e) {
        $error_message = "Error completing booking: " . $e->getMessage();
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Booking status updated successfully";
            break;
        case 2:
            $success_message = "Booking assigned successfully";
            break;
        case 3:
            $success_message = "Booking cancelled successfully";
            break;
        case 4:
            $success_message = "Booking rescheduled successfully";
            break;
        case 5:
            $success_message = "Booking marked as completed";
            break;
    }
}

// Handle error messages
if (isset($_GET['error'])) {
    $error_message = $_GET['error'];
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Booking Details</h1>
            <p>View and manage booking information</p>
        </div>
        <div class="action-buttons">
            <a href="bookings.php" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Bookings
            </a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="booking-container">
        <!-- Booking Header -->
        <div class="booking-header">
            <div class="booking-ref">
                <span class="label">Ref #</span>
                <span class="value"><?php echo htmlspecialchars($booking['reference_number']); ?></span>
            </div>
            
            <div class="booking-status">
                <span class="status-badge" style="background-color: <?php echo $booking['status_color']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $booking['status_name'])); ?>
                </span>
            </div>
            
            <div class="booking-actions">
                <?php if (in_array($booking['status_name'], ['pending', 'confirmed'])): ?>
                    <button type="button" class="btn-action btn-edit" title="Update Status" 
                            onclick="openEditModal(<?php echo $booking_id; ?>)">
                        <i class="fas fa-edit"></i> Update Status
                    </button>
                    
                    <?php if (empty($booking['consultant_name'])): ?>
                        <button type="button" class="btn-action btn-assign" title="Assign Consultant" 
                                onclick="openAssignModal(<?php echo $booking_id; ?>, '<?php echo addslashes($booking['client_name']); ?>')">
                            <i class="fas fa-user-plus"></i> Assign Consultant
                        </button>
                    <?php endif; ?>
                    
                    <button type="button" class="btn-action btn-reschedule" title="Reschedule" 
                            onclick="openRescheduleModal(<?php echo $booking_id; ?>, '<?php echo $booking['booking_datetime']; ?>', <?php echo $booking['duration_minutes']; ?>)">
                        <i class="fas fa-calendar-alt"></i> Reschedule
                    </button>
                    
                    <button type="button" class="btn-action btn-cancel" title="Cancel Booking" 
                            onclick="openCancelModal(<?php echo $booking_id; ?>, '<?php echo addslashes($booking['reference_number']); ?>')">
                        <i class="fas fa-times"></i> Cancel Booking
                    </button>
                <?php endif; ?>
                
                <?php if ($booking['status_name'] === 'confirmed'): ?>
                    <button type="button" class="btn-action btn-complete" title="Mark as Completed" 
                            onclick="openCompleteModal(<?php echo $booking_id; ?>, '<?php echo addslashes($booking['reference_number']); ?>')">
                        <i class="fas fa-check"></i> Mark as Completed
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Booking Details Grid -->
        <div class="booking-details">
            <!-- Left Column -->
            <div class="details-column">
                <div class="details-section">
                    <h3>Client Information</h3>
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="detail-label">Name</span>
                            <span class="detail-value"><?php echo htmlspecialchars($booking['client_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email</span>
                            <span class="detail-value"><?php echo htmlspecialchars($booking['client_email']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Phone</span>
                            <span class="detail-value"><?php echo htmlspecialchars($booking['client_phone'] ?? 'Not provided'); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="details-section">
                    <h3>Booking Information</h3>
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="detail-label">Date</span>
                            <span class="detail-value"><?php echo date('F d, Y', strtotime($booking['booking_datetime'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Time</span>
                            <span class="detail-value"><?php echo date('h:i A', strtotime($booking['booking_datetime'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Duration</span>
                            <span class="detail-value"><?php echo $booking['duration_minutes']; ?> minutes</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status</span>
                            <span class="detail-value">
                                <span class="status-badge" style="background-color: <?php echo $booking['status_color']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $booking['status_name'])); ?>
                                </span>
                            </span>
                        </div>
                        <?php if (!empty($booking['cancelled_at'])): ?>
                            <div class="detail-item full-width">
                                <span class="detail-label">Cancelled At</span>
                                <span class="detail-value"><?php echo date('F d, Y h:i A', strtotime($booking['cancelled_at'])); ?></span>
                            </div>
                            <div class="detail-item full-width">
                                <span class="detail-label">Cancellation Reason</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['cancellation_reason']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($booking['reschedule_count'] > 0): ?>
                            <div class="detail-item">
                                <span class="detail-label">Rescheduled</span>
                                <span class="detail-value"><?php echo $booking['reschedule_count']; ?> times</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="details-section">
                    <h3>Service Details</h3>
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="detail-label">Visa Type</span>
                            <span class="detail-value"><?php echo htmlspecialchars($booking['visa_type']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Country</span>
                            <span class="detail-value"><?php echo htmlspecialchars($booking['country_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Service</span>
                            <span class="detail-value"><?php echo htmlspecialchars($booking['service_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Consultation Mode</span>
                            <span class="detail-value"><?php echo htmlspecialchars($booking['consultation_mode']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Base Price</span>
                            <span class="detail-value">$<?php echo number_format($booking['base_price'], 2); ?></span>
                        </div>
                        <?php if (!empty($booking['additional_fee']) && $booking['additional_fee'] > 0): ?>
                            <div class="detail-item">
                                <span class="detail-label">Additional Fee</span>
                                <span class="detail-value">$<?php echo number_format($booking['additional_fee'], 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="detail-item">
                            <span class="detail-label">Total Price</span>
                            <span class="detail-value">$<?php echo number_format($booking['total_price'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="details-column">
                <div class="details-section">
                    <h3>Consultant Information</h3>
                    <div class="details-grid">
                        <?php if (!empty($booking['consultant_name'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">Consultant</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['consultant_name']); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="detail-item">
                                <span class="detail-label">Consultant</span>
                                <span class="detail-value not-assigned">Not assigned</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($booking['meeting_link'])): ?>
                <div class="details-section">
                    <h3>Meeting Details</h3>
                    <div class="details-grid">
                        <div class="detail-item full-width">
                            <span class="detail-label">Meeting Link</span>
                            <span class="detail-value">
                                <a href="<?php echo htmlspecialchars($booking['meeting_link']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($booking['meeting_link']); ?>
                                </a>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($booking['location'])): ?>
                <div class="details-section">
                    <h3>Location Information</h3>
                    <div class="details-grid">
                        <div class="detail-item full-width">
                            <span class="detail-label">Location</span>
                            <span class="detail-value"><?php echo htmlspecialchars($booking['location']); ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($booking['client_notes'])): ?>
                <div class="details-section">
                    <h3>Client Notes</h3>
                    <div class="details-grid">
                        <div class="detail-item full-width">
                            <div class="notes-container">
                                <?php echo nl2br(htmlspecialchars($booking['client_notes'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($booking['admin_notes'])): ?>
                <div class="details-section">
                    <h3>Admin Notes</h3>
                    <div class="details-grid">
                        <div class="detail-item full-width">
                            <div class="notes-container">
                                <?php echo nl2br(htmlspecialchars($booking['admin_notes'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($booking['payment_status'])): ?>
                <div class="details-section">
                    <h3>Payment Information</h3>
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="detail-label">Payment Status</span>
                            <span class="detail-value">
                                <?php 
                                $status_class = '';
                                switch($booking['payment_status']) {
                                    case 'completed':
                                        $status_class = 'status-success';
                                        break;
                                    case 'pending':
                                        $status_class = 'status-warning';
                                        break;
                                    case 'failed':
                                        $status_class = 'status-danger';
                                        break;
                                    case 'refunded':
                                    case 'partially_refunded':
                                        $status_class = 'status-info';
                                        break;
                                }
                                ?>
                                <span class="payment-status <?php echo $status_class; ?>">
                                    <?php echo ucfirst($booking['payment_status']); ?>
                                </span>
                            </span>
                        </div>
                        <?php if (!empty($booking['payment_method'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Payment Method</span>
                            <span class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $booking['payment_method'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($booking['payment_date'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Payment Date</span>
                            <span class="detail-value"><?php echo date('F d, Y', strtotime($booking['payment_date'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($booking['transaction_id'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Transaction ID</span>
                            <span class="detail-value"><?php echo htmlspecialchars($booking['transaction_id']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tabs for Additional Information -->
        <div class="booking-tabs">
            <div class="tabs">
                <button class="tab-btn active" data-tab="activity">Activity Log</button>
                <button class="tab-btn" data-tab="documents">Documents</button>
            </div>
            
            <!-- Activity Log Tab -->
            <div class="tab-content active" id="activity-tab">
                <?php if (empty($activity_logs)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>No activity logs found for this booking.</p>
                    </div>
                <?php else: ?>
                    <div class="activity-timeline">
                        <?php foreach ($activity_logs as $log): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <span class="activity-type"><?php echo ucfirst(str_replace('_', ' ', $log['activity_type'])); ?></span>
                                        <span class="activity-date"><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></span>
                                    </div>
                                    <div class="timeline-body">
                                        <p><?php echo htmlspecialchars($log['description']); ?></p>
                                        <div class="activity-user">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($log['user_name']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Documents Tab -->
            <div class="tab-content" id="documents-tab">
                <?php if (empty($documents)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <p>No documents found for this booking.</p>
                    </div>
                <?php else: ?>
                    <table class="documents-table">
                        <thead>
                            <tr>
                                <th>Document Name</th>
                                <th>Type</th>
                                <th>Uploaded By</th>
                                <th>Upload Date</th>
                                <th>File Size</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $document): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($document['document_name']); ?></td>
                                    <td><?php echo htmlspecialchars($document['document_type']); ?></td>
                                    <td><?php echo htmlspecialchars($document['uploaded_by_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($document['created_at'])); ?></td>
                                    <td><?php echo formatFileSize($document['file_size']); ?></td>
                                    <td class="actions-cell">
                                        <a href="<?php echo htmlspecialchars($document['document_path']); ?>" class="btn-action btn-view" title="View Document" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo htmlspecialchars($document['document_path']); ?>" class="btn-action btn-download" title="Download Document" download>
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <?php if ($document['uploaded_by'] === $_SESSION['id'] || $_SESSION['user_type'] === 'admin'): ?>
                                            <button type="button" class="btn-action btn-delete" title="Delete Document" 
                                                    onclick="confirmDeleteDocument(<?php echo $document['id']; ?>, '<?php echo addslashes($document['document_name']); ?>')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <div class="mt-4">
                    <button type="button" class="btn primary-btn" onclick="openUploadDocumentModal()">
                        <i class="fas fa-upload"></i> Upload Document
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Consultant Modal -->
<div class="modal" id="assignModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Assign Consultant</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="view_booking.php?id=<?php echo $booking_id; ?>" method="POST" id="assignForm">
                    <input type="hidden" name="booking_id" id="assign_booking_id" value="<?php echo $booking_id; ?>">
                    
                    <p>Assigning consultant for booking with client: <strong id="assign_client_name"></strong></p>
                    
                    <div class="form-group">
                        <label for="consultant_id">Select Consultant*</label>
                        <select name="consultant_id" id="consultant_id" class="form-control" required>
                            <option value="">Select Consultant</option>
                            <?php foreach ($team_members as $member): ?>
                                <option value="<?php echo $member['id']; ?>">
                                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?> 
                                    (<?php echo htmlspecialchars($member['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="assign_consultant" class="btn submit-btn">Assign Consultant</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Booking Modal -->
<div class="modal" id="cancelModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Cancel Booking</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="view_booking.php?id=<?php echo $booking_id; ?>" method="POST" id="cancelForm">
                    <input type="hidden" name="booking_id" id="cancel_booking_id" value="<?php echo $booking_id; ?>">
                    
                    <p>You are about to cancel booking <strong id="cancel_reference"></strong>. This action cannot be undone.</p>
                    
                    <div class="form-group">
                        <label for="cancellation_reason">Cancellation Reason*</label>
                        <textarea name="cancellation_reason" id="cancellation_reason" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Close</button>
                        <button type="submit" name="cancel_booking" class="btn submit-btn danger-btn">Cancel Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
                    
<!-- Reschedule Booking Modal -->
<div class="modal" id="rescheduleModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Reschedule Booking</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="view_booking.php?id=<?php echo $booking_id; ?>" method="POST" id="rescheduleForm">
                    <input type="hidden" name="booking_id" id="reschedule_booking_id" value="<?php echo $booking_id; ?>">
                    
                    <div class="form-group">
                        <label for="new_datetime">New Date and Time*</label>
                        <input type="datetime-local" name="new_datetime" id="new_datetime" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="duration_minutes">Duration (minutes)*</label>
                        <input type="number" name="duration_minutes" id="duration_minutes" class="form-control" min="15" step="15" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reschedule_notes">Rescheduling Notes*</label>
                        <textarea name="reschedule_notes" id="reschedule_notes" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="reschedule_booking" class="btn submit-btn">Reschedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
                
<!-- Complete Booking Modal -->
<div class="modal" id="completeModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Mark Booking as Completed</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="view_booking.php?id=<?php echo $booking_id; ?>" method="POST" id="completeForm">
                    <input type="hidden" name="booking_id" id="complete_booking_id" value="<?php echo $booking_id; ?>">
                    
                    <p>You are marking booking <strong id="complete_reference"></strong> as completed.</p>
                    
                    <div class="form-group">
                        <label for="completion_notes">Completion Notes*</label>
                        <textarea name="completion_notes" id="completion_notes" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="complete_booking" class="btn submit-btn">Mark as Completed</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal" id="statusModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Booking Status</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="view_booking.php?id=<?php echo $booking_id; ?>" method="POST" id="statusForm">
                    <input type="hidden" name="booking_id" id="status_booking_id" value="<?php echo $booking_id; ?>">
                    
                    <div class="form-group">
                        <label for="status_id">New Status*</label>
                        <select name="status_id" id="status_id" class="form-control" required>
                            <option value="">Select Status</option>
                            <?php foreach ($booking_statuses as $status): ?>
                                <option value="<?php echo $status['id']; ?>" <?php echo ($booking['status_id'] == $status['id']) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $status['name'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_notes">Admin Notes</label>
                        <textarea name="admin_notes" id="admin_notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn submit-btn">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Upload Document Modal -->
<div class="modal" id="uploadDocumentModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Upload Document</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="ajax/upload_booking_document.php" method="POST" id="uploadDocumentForm" enctype="multipart/form-data">
                    <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                    
                    <div class="form-group">
                        <label for="document_name">Document Name*</label>
                        <input type="text" name="document_name" id="document_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="document_type">Document Type*</label>
                        <select name="document_type" id="document_type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="contract">Contract</option>
                            <option value="invoice">Invoice</option>
                            <option value="receipt">Receipt</option>
                            <option value="visa_application">Visa Application</option>
                            <option value="identification">Identification</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="document_file">File*</label>
                        <input type="file" name="document_file" id="document_file" class="form-control-file" required>
                        <small class="form-text text-muted">Max file size: 10MB. Supported formats: PDF, DOC, DOCX, JPG, PNG</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="document_notes">Notes</label>
                        <textarea name="document_notes" id="document_notes" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_private" id="is_private" value="1" checked>
                            <label for="is_private">Private (Only visible to team members)</label>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn submit-btn">Upload Document</button>
                    </div>
                </form>
                <div class="upload-progress" style="display: none;">
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <p class="progress-text">Uploading... 0%</p>
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
    --warning-color: #f6c23e;
    --info-color: #36b9cc;
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
    text-decoration: none;
}

.primary-btn:hover {
    background-color: #031c56;
    text-decoration: none;
    color: white;
}

.secondary-btn {
    background-color: var(--light-color);
    color: var(--primary-color);
    border: 1px solid var(--border-color);
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.secondary-btn:hover {
    background-color: #f0f3f9;
    text-decoration: none;
    color: var(--primary-color);
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

/* Booking Container */
.booking-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px;
}

/* Booking Header */
.booking-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
}

.booking-ref {
    display: flex;
    flex-direction: column;
}

.booking-ref .label {
    font-size: 12px;
    color: var(--secondary-color);
}

.booking-ref .value {
    font-size: 18px;
    font-weight: 600;
    color: var(--primary-color);
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
    color: white;
}

.booking-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 15px;
    border-radius: 4px;
    font-size: 14px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    color: white;
    transition: background-color 0.2s;
}

.btn-edit {
    background-color: var(--warning-color);
}

.btn-edit:hover {
    background-color: #e0b137;
    color: white;
    text-decoration: none;
}

.btn-assign {
    background-color: var(--info-color);
}

.btn-assign:hover {
    background-color: #2fa6b9;
    color: white;
    text-decoration: none;
}

.btn-reschedule {
    background-color: #9932CC;
}

.btn-reschedule:hover {
    background-color: #8021a8;
    color: white;
    text-decoration: none;
}

.btn-cancel {
    background-color: var(--danger-color);
}

.btn-cancel:hover {
    background-color: #d44235;
    color: white;
    text-decoration: none;
}

.btn-complete {
    background-color: var(--success-color);
}

.btn-complete:hover {
    background-color: #18b07b;
    color: white;
    text-decoration: none;
}

.btn-view {
    background-color: var(--primary-color);
}

.btn-view:hover {
    background-color: #031c56;
    color: white;
    text-decoration: none;
}

.btn-download {
    background-color: var(--secondary-color);
}

.btn-download:hover {
    background-color: #717380;
    color: white;
    text-decoration: none;
}

.btn-delete {
    background-color: var(--danger-color);
}

.btn-delete:hover {
    background-color: #d44235;
    color: white;
    text-decoration: none;
}

/* Booking Details */
.booking-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    padding: 20px;
}

.details-column {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.details-section {
    background-color: var(--light-color);
    border-radius: 5px;
    padding: 15px;
    border: 1px solid var(--border-color);
}

.details-section h3 {
    margin: 0 0 15px 0;
    color: var(--primary-color);
    font-size: 1.2rem;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px 20px;
}

.detail-item {
    display: flex;
    flex-direction: column;
}

.detail-item.full-width {
    grid-column: 1 / -1;
}

.detail-label {
    font-size: 12px;
    color: var(--secondary-color);
    margin-bottom: 5px;
}

.detail-value {
    font-size: 14px;
    color: var(--dark-color);
}

.not-assigned {
    font-style: italic;
    color: var(--secondary-color);
}

.notes-container {
    background-color: white;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 10px;
    max-height: 200px;
    overflow-y: auto;
    font-size: 14px;
    color: var(--dark-color);
}

.payment-status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    color: white;
}

.status-success {
    background-color: var(--success-color);
}

.status-warning {
    background-color: var(--warning-color);
}

.status-danger {
    background-color: var(--danger-color);
}

.status-info {
    background-color: var(--info-color);
}

/* Tabs */
.booking-tabs {
    padding: 0 20px 20px;
}

.tabs {
    display: flex;
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 20px;
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
}

.tab-content.active {
    display: block;
}

/* Activity Timeline */
.activity-timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 25px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    top: 0;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background-color: var(--primary-color);
    z-index: 1;
}

.timeline-item:not(:last-child) .timeline-marker::after {
    content: '';
    position: absolute;
    top: 14px;
    left: 50%;
    transform: translateX(-50%);
    width: 2px;
    height: calc(100% + 25px);
    background-color: var(--border-color);
}

.timeline-content {
    background-color: white;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    padding: 15px;
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.activity-type {
    font-weight: 600;
    color: var(--primary-color);
}

.activity-date {
    color: var(--secondary-color);
    font-size: 14px;
}

.timeline-body p {
    margin: 0 0 10px 0;
    font-size: 14px;
}

.activity-user {
    color: var(--secondary-color);
    font-size: 13px;
}

/* Documents Table */
.documents-table {
    width: 100%;
    border-collapse: collapse;
}

.documents-table th {
    background-color: var(--light-color);
    padding: 10px 15px;
    text-align: left;
    font-weight: 600;
    color: var(--primary-color);
}

.documents-table td {
    padding: 10px 15px;
    border-top: 1px solid var(--border-color);
}

.actions-cell {
    display: flex;
    gap: 5px;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
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

.form-control-file {
    width: 100%;
    padding: 10px 0;
    font-size: 14px;
}

.form-text {
    display: block;
    margin-top: 5px;
    font-size: 12px;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
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

.danger-btn {
    background-color: var(--danger-color);
}

.danger-btn:hover {
    background-color: #d44235;
}

.submit-btn:hover {
    background-color: #031c56;
}

.mt-4 {
    margin-top: 2rem;
}

.progress {
    height: 8px;
    margin: 10px 0;
    border-radius: 4px;
    background-color: var(--border-color);
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background-color: var(--primary-color);
}

.progress-text {
    font-size: 14px;
    color: var(--secondary-color);
    margin: 0;
}

@media (max-width: 768px) {
    .booking-details {
        grid-template-columns: 1fr;
    }
    
    .details-grid {
        grid-template-columns: 1fr;
    }
    
    .booking-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .booking-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
}
</style>

<script>
// Helper function to format file size
function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + " bytes";
    else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + " KB";
    else if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + " MB";
    else return (bytes / 1073741824).toFixed(1) + " GB";
}

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

// Open assign modal
function openAssignModal(bookingId, clientName) {
    document.getElementById('assign_client_name').textContent = clientName;
    openModal('assignModal');
}

// Open cancel modal
function openCancelModal(bookingId, reference) {
    document.getElementById('cancel_reference').textContent = reference;
    openModal('cancelModal');
}

// Open reschedule modal
function openRescheduleModal(bookingId, datetime, duration) {
    // Format datetime for datetime-local input
    const date = new Date(datetime);
    const formattedDate = date.toISOString().slice(0, 16);
    document.getElementById('new_datetime').value = formattedDate;
    document.getElementById('duration_minutes').value = duration;
    
    openModal('rescheduleModal');
}

// Open complete modal
function openCompleteModal(bookingId, reference) {
    document.getElementById('complete_reference').textContent = reference;
    openModal('completeModal');
}

// Open edit status modal
function openEditModal(bookingId) {
    openModal('statusModal');
}

// Open upload document modal
function openUploadDocumentModal() {
    openModal('uploadDocumentModal');
}

// Confirm document deletion
function confirmDeleteDocument(documentId, documentName) {
    if (confirm(`Are you sure you want to delete the document "${documentName}"?`)) {
        window.location.href = `ajax/delete_document.php?id=${documentId}&booking_id=<?php echo $booking_id; ?>`;
    }
}

// Handle tab functionality
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

// Handle document upload with progress bar
document.getElementById('uploadDocumentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const formData = new FormData(form);
    const progressBar = document.querySelector('.progress-bar');
    const progressText = document.querySelector('.progress-text');
    const progressDiv = document.querySelector('.upload-progress');
    
    form.style.display = 'none';
    progressDiv.style.display = 'block';
    
    const xhr = new XMLHttpRequest();
    
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const percentComplete = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = percentComplete + '%';
            progressText.textContent = `Uploading... ${percentComplete}%`;
        }
    });
    
    xhr.addEventListener('load', function() {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            if (response.success) {
                window.location.href = 'view_booking.php?id=<?php echo $booking_id; ?>&success=document_uploaded';
            } else {
                alert('Error uploading document: ' + response.error);
                form.style.display = 'block';
                progressDiv.style.display = 'none';
            }
        } else {
            alert('Error uploading document. Please try again.');
            form.style.display = 'block';
            progressDiv.style.display = 'none';
        }
    });
    
    xhr.addEventListener('error', function() {
        alert('Network error occurred. Please try again.');
        form.style.display = 'block';
        progressDiv.style.display = 'none';
    });
    
    xhr.open('POST', form.action, true);
    xhr.send(formData);
});

// Prevent form submission if cancellation reason is empty
document.getElementById('cancelForm').addEventListener('submit', function(e) {
    const reason = document.getElementById('cancellation_reason').value.trim();
    if (reason === '') {
        e.preventDefault();
        alert('Please provide a cancellation reason.');
    }
});
</script>

<?php
// Helper function to format file sizes
function formatFileSize($bytes) {
    if ($bytes < 1024) return $bytes . " bytes";
    else if ($bytes < 1048576) return round($bytes / 1024, 1) . " KB";
    else if ($bytes < 1073741824) return round($bytes / 1048576, 1) . " MB";
    else return round($bytes / 1073741824, 1) . " GB";
}

// End output buffering and send content to browser
ob_end_flush();

require_once 'includes/footer.php';
?>