<?php
// Set page title
$page_title = "My Meetings - Applicant";

// Include header
include('includes/header.php');

// Get applicant ID
$applicant_id = $user_id;

// Function to format date and time
function formatDateTime($datetime, $format = 'M j, Y g:i A') {
    return date($format, strtotime($datetime));
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending':
            return 'badge-warning';
        case 'confirmed':
            return 'badge-success';
        case 'cancelled_by_user':
        case 'cancelled_by_admin':
        case 'cancelled_by_consultant':
            return 'badge-danger';
        case 'completed':
            return 'badge-primary';
        case 'rescheduled':
            return 'badge-info';
        case 'no_show':
            return 'badge-secondary';
        default:
            return 'badge-light';
    }
}

// Get query parameters for filtering
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$filter_consultant = isset($_GET['consultant']) ? intval($_GET['consultant']) : 0;

// Handle meeting cancellation request
if (isset($_POST['cancel_meeting']) && isset($_POST['booking_id'])) {
    $booking_id = intval($_POST['booking_id']);
    $reason = trim($_POST['cancellation_reason']);
    
    // Verify this booking belongs to the applicant
    $verify_query = "SELECT id FROM bookings WHERE id = ? AND user_id = ? AND deleted_at IS NULL";
    $stmt = $conn->prepare($verify_query);
    $stmt->bind_param("ii", $booking_id, $applicant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Get cancelled status ID
        $status_query = "SELECT id FROM booking_statuses WHERE name = 'cancelled_by_user' LIMIT 1";
        $status_result = $conn->query($status_query);
        $status_row = $status_result->fetch_assoc();
        $cancelled_status_id = $status_row['id'];
        
        // Update booking status
        $update_query = "UPDATE bookings SET 
                        status_id = ?, 
                        cancellation_reason = ?, 
                        cancelled_by = ?, 
                        cancelled_at = NOW() 
                        WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("isii", $cancelled_status_id, $reason, $applicant_id, $booking_id);
        
        if ($stmt->execute()) {
            // Add activity log
            $log_query = "INSERT INTO booking_activity_logs 
                         (booking_id, user_id, activity_type, description) 
                         VALUES (?, ?, 'cancelled', ?)";
            $log_desc = "Booking cancelled by applicant. Reason: " . $reason;
            $stmt = $conn->prepare($log_query);
            $stmt->bind_param("iis", $booking_id, $applicant_id, $log_desc);
            $stmt->execute();
            
            $success_message = "Meeting cancelled successfully.";
        } else {
            $error_message = "Failed to cancel meeting. Please try again.";
        }
    } else {
        $error_message = "Invalid meeting or you don't have permission to cancel it.";
    }
}

// Build query to get all bookings for this user with status filter
$query = "SELECT b.id, b.reference_number, b.booking_datetime, b.end_datetime, b.duration_minutes,
                 b.meeting_link, b.location, b.client_notes, b.time_zone, b.language_preference,
                 bs.name AS status_name, bs.color AS status_color, 
                 v.visa_type, c.country_name, st.service_name, cm.mode_name AS consultation_mode,
                 CONCAT(u.first_name, ' ', u.last_name) AS consultant_name, u.profile_picture,
                 o.name AS organization_name,
                 (SELECT COUNT(*) FROM booking_documents WHERE booking_id = b.id) AS document_count
          FROM bookings b
          JOIN booking_statuses bs ON b.status_id = bs.id
          JOIN visa_services vs ON b.visa_service_id = vs.visa_service_id
          JOIN service_consultation_modes scm ON b.service_consultation_id = scm.service_consultation_id
          JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
          JOIN visas v ON vs.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          JOIN service_types st ON vs.service_type_id = st.service_type_id
          JOIN users u ON b.consultant_id = u.id
          JOIN organizations o ON b.organization_id = o.id
          WHERE b.user_id = ? AND b.deleted_at IS NULL";

// Add filters if provided
$params = [$applicant_id];
$types = "i";

if (!empty($filter_status)) {
    $query .= " AND bs.name = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($filter_date)) {
    $query .= " AND DATE(b.booking_datetime) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

if (!empty($filter_consultant)) {
    $query .= " AND b.consultant_id = ?";
    $params[] = $filter_consultant;
    $types .= "i";
}

// Order by date (newest first for completed, oldest first for upcoming)
$query .= " ORDER BY 
           CASE WHEN bs.name IN ('completed', 'cancelled_by_user', 'cancelled_by_admin', 'cancelled_by_consultant', 'no_show') 
                THEN 0 ELSE 1 END DESC, 
           b.booking_datetime ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings_result = $stmt->get_result();

// Get list of consultants for filter dropdown
$consultants_query = "SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) AS consultant_name
                     FROM bookings b
                     JOIN users u ON b.consultant_id = u.id
                     WHERE b.user_id = ? AND b.deleted_at IS NULL
                     ORDER BY consultant_name";
$stmt = $conn->prepare($consultants_query);
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$consultants_result = $stmt->get_result();

// Get list of statuses for filter dropdown
$statuses_query = "SELECT DISTINCT bs.name, bs.color
                  FROM bookings b
                  JOIN booking_statuses bs ON b.status_id = bs.id
                  WHERE b.user_id = ? AND b.deleted_at IS NULL
                  ORDER BY bs.name";
$stmt = $conn->prepare($statuses_query);
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$statuses_result = $stmt->get_result();
?>

<div class="content">
    <div class="dashboard-header">
        <h1>My Meetings</h1>
        <p>Manage your scheduled and past meetings with consultants</p>
    </div>
    
    <?php if (isset($success_message)): ?>
    <div class="alert alert-success">
        <?php echo $success_message; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>
    
    <div class="dashboard-section">
        <div class="section-header">
            <h2>Meeting Schedule</h2>
            <a href="book_consultation.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Book New Meeting
            </a>
        </div>
        
        <!-- Filters -->
        <div class="filters-container mb-4">
            <form action="" method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">All Statuses</option>
                        <?php while ($status = $statuses_result->fetch_assoc()): ?>
                            <option value="<?php echo $status['name']; ?>" <?php if ($filter_status === $status['name']) echo 'selected'; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $status['name'])); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="date">Date</label>
                    <input type="date" name="date" id="date" class="form-control" value="<?php echo $filter_date; ?>">
                </div>
                <div class="filter-group">
                    <label for="consultant">Consultant</label>
                    <select name="consultant" id="consultant" class="form-control">
                        <option value="">All Consultants</option>
                        <?php while ($consultant = $consultants_result->fetch_assoc()): ?>
                            <option value="<?php echo $consultant['id']; ?>" <?php if ($filter_consultant === (int)$consultant['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($consultant['consultant_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="meetings.php" class="btn btn-outline">Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Meetings List -->
        <?php if ($bookings_result->num_rows > 0): ?>
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Date & Time</th>
                        <th>Consultant</th>
                        <th>Service</th>
                        <th>Mode</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($booking = $bookings_result->fetch_assoc()): 
                        $is_upcoming = in_array($booking['status_name'], ['pending', 'confirmed']);
                        $is_past = in_array($booking['status_name'], ['completed', 'cancelled_by_user', 'cancelled_by_admin', 'cancelled_by_consultant', 'no_show']);
                        $can_cancel = in_array($booking['status_name'], ['pending', 'confirmed']) && (strtotime($booking['booking_datetime']) > time());
                        $can_join = $booking['status_name'] === 'confirmed' && (abs(strtotime($booking['booking_datetime']) - time()) < 3600); // Can join within 1 hour
                        
                        // Get consultant profile picture
                        $profile_img = '../../assets/images/default-profile.jpg';
                        if (!empty($booking['profile_picture'])) {
                            if (file_exists('../../uploads/profiles/' . $booking['profile_picture'])) {
                                $profile_img = '../../uploads/profiles/' . $booking['profile_picture'];
                            }
                        }
                    ?>
                    <tr>
                        <td>
                            <a href="meeting_details.php?id=<?php echo $booking['id']; ?>" class="reference-link">
                                <?php echo $booking['reference_number']; ?>
                            </a>
                        </td>
                        <td>
                            <div class="meeting-time">
                                <div class="date"><?php echo formatDateTime($booking['booking_datetime'], 'M j, Y'); ?></div>
                                <div class="time"><?php echo formatDateTime($booking['booking_datetime'], 'g:i A'); ?> - <?php echo formatDateTime($booking['end_datetime'], 'g:i A'); ?></div>
                                <div class="duration"><?php echo $booking['duration_minutes']; ?> mins</div>
                            </div>
                        </td>
                        <td>
                            <div class="consultant-info">
                                <img src="<?php echo $profile_img; ?>" alt="Profile" class="consultant-img">
                                <div>
                                    <div class="consultant-name"><?php echo htmlspecialchars($booking['consultant_name']); ?></div>
                                    <div class="organization"><?php echo htmlspecialchars($booking['organization_name']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="service-info">
                                <div class="service-name"><?php echo htmlspecialchars($booking['service_name']); ?></div>
                                <div class="visa-type"><?php echo htmlspecialchars($booking['visa_type']); ?> - <?php echo htmlspecialchars($booking['country_name']); ?></div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($booking['consultation_mode']); ?></td>
                        <td>
                            <span class="status-badge" style="background-color: <?php echo $booking['status_color']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $booking['status_name'])); ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="meeting_details.php?id=<?php echo $booking['id']; ?>" class="btn-action btn-view" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <?php if ($can_join && !empty($booking['meeting_link'])): ?>
                                <a href="<?php echo htmlspecialchars($booking['meeting_link']); ?>" target="_blank" class="btn-action btn-success" title="Join Meeting">
                                    <i class="fas fa-video"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($can_cancel): ?>
                                <button type="button" class="btn-action btn-danger cancel-meeting-btn" 
                                        data-toggle="modal" 
                                        data-target="#cancelMeetingModal"
                                        data-booking-id="<?php echo $booking['id']; ?>"
                                        data-reference="<?php echo $booking['reference_number']; ?>"
                                        title="Cancel Meeting">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-day"></i>
                <p>You don't have any meetings scheduled. <a href="book_consultation.php">Book a consultation</a> to get started.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Cancel Meeting Modal -->
<div class="modal fade" id="cancelMeetingModal" tabindex="-1" role="dialog" aria-labelledby="cancelMeetingModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancelMeetingModalLabel">Cancel Meeting</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <p>Are you sure you want to cancel this meeting? This action cannot be undone.</p>
                    <p><strong>Meeting Reference:</strong> <span id="cancelMeetingRef"></span></p>
                    
                    <div class="form-group">
                        <label for="cancellation_reason">Reason for Cancellation</label>
                        <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="3" required></textarea>
                    </div>
                    
                    <input type="hidden" id="booking_id" name="booking_id" value="">
                    <input type="hidden" name="cancel_meeting" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Cancel Meeting</button>
                </div>
            </form>
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
    --message-color: #4e73df;
    --notification-color: #f6c23e;
}

.content {
    padding: 20px;
}

.dashboard-header {
    margin-bottom: 20px;
}

.dashboard-header h1 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.8rem;
}

.dashboard-header p {
    margin: 5px 0 0;
    color: var(--secondary-color);
}

.dashboard-section {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    margin-bottom: 30px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.section-header h2 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.2rem;
}

.filters-container {
    background-color: var(--light-color);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.filter-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.filter-group {
    margin-bottom: 15px;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--dark-color);
    font-size: 0.9rem;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 0.9rem;
}

.filter-actions {
    display: flex;
    align-items: flex-end;
    gap: 10px;
}

.dashboard-table {
    width: 100%;
    border-collapse: collapse;
}

.dashboard-table th {
    text-align: left;
    padding: 10px;
    font-weight: 600;
    color: var(--primary-color);
    font-size: 0.85rem;
}

.dashboard-table td {
    padding: 10px;
    border-top: 1px solid var(--border-color);
    font-size: 0.9rem;
}

.reference-link {
    font-weight: 500;
    color: var(--primary-color);
    text-decoration: none;
}

.reference-link:hover {
    text-decoration: underline;
}

.meeting-time .date {
    font-weight: 500;
}

.meeting-time .time {
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.meeting-time .duration {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.consultant-info {
    display: flex;
    align-items: center;
}

.consultant-img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 10px;
}

.consultant-name {
    font-weight: 500;
}

.organization {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.service-info .service-name {
    font-weight: 500;
}

.service-info .visa-type {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    color: white;
}

.action-buttons {
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
    text-decoration: none;
    color: white;
}

.btn-view {
    background-color: var(--primary-color);
}

.btn-view:hover {
    background-color: #031c56;
}

.btn-success {
    background-color: var(--success-color);
}

.btn-success:hover {
    background-color: #169b6b;
}

.btn-danger {
    background-color: var(--danger-color);
}

.btn-danger:hover {
    background-color: #c13a2d;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: background-color 0.2s;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: #031c56;
}

.btn-secondary {
    background-color: var(--secondary-color);
    color: white;
}

.btn-outline {
    background-color: transparent;
    border: 1px solid var(--border-color);
    color: var(--secondary-color);
}

.btn-outline:hover {
    background-color: var(--light-color);
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 36px;
    margin-bottom: 10px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0;
    font-size: 0.9rem;
}

.empty-state a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
}

.empty-state a:hover {
    text-decoration: underline;
}

.alert {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    position: relative;
}

.alert-success {
    background-color: rgba(28, 200, 138, 0.1);
    border: 1px solid rgba(28, 200, 138, 0.2);
    color: var(--success-color);
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.1);
    border: 1px solid rgba(231, 74, 59, 0.2);
    color: var(--danger-color);
}

.close {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 16px;
    color: inherit;
    opacity: 0.8;
    background: none;
    border: none;
    cursor: pointer;
}

.close:hover {
    opacity: 1;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color);
    padding: 15px;
}

.modal-body {
    padding: 15px;
}

.modal-footer {
    border-top: 1px solid var(--border-color);
    padding: 15px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

@media (max-width: 992px) {
    .filter-form {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        justify-content: flex-start;
    }
    
    .dashboard-table {
        display: block;
        overflow-x: auto;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle cancel meeting button clicks
    const cancelButtons = document.querySelectorAll('.cancel-meeting-btn');
    cancelButtons.forEach(button => {
        button.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-booking-id');
            const reference = this.getAttribute('data-reference');
            
            document.getElementById('booking_id').value = bookingId;
            document.getElementById('cancelMeetingRef').textContent = reference;
        });
    });
});
</script>

<?php
// Include footer
include('includes/footer.php');
?>
