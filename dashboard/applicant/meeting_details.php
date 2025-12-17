<?php
// Set page title
ob_start();
$page_title = "Meeting Details - Applicant";

// Include header
include('includes/header.php');

// Get applicant ID
$applicant_id = $user_id;

// Check if meeting ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: meetings.php");
    exit;
}

$meeting_id = intval($_GET['id']);

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

// Get meeting information with detailed joins
$query = "SELECT 
    b.id,
    b.reference_number,
    b.booking_datetime,
    b.end_datetime,
    b.duration_minutes,
    b.client_notes,
    b.language_preference,
    b.meeting_link,
    b.location,
    b.time_zone,
    b.cancellation_reason,
    b.cancelled_at,
    bs.name AS status_name,
    bs.color AS status_color,
    CONCAT(client.first_name, ' ', client.last_name) AS client_name,
    client.email AS client_email,
    client.phone AS client_phone,
    CONCAT(cons.first_name, ' ', cons.last_name) AS consultant_name,
    cons.email AS consultant_email,
    cons.phone AS consultant_phone,
    cons.profile_picture AS consultant_profile,
    cons.id AS consultant_id,
    c.company_name,
    v.visa_type,
    co.country_name,
    st.service_name,
    cm.mode_name AS consultation_mode,
    vs.base_price,
    scm.additional_fee,
    (vs.base_price + IFNULL(scm.additional_fee, 0)) AS total_price,
    bp.payment_status,
    bp.payment_date,
    bp.transaction_id,
    bp.amount,
    bp.currency,
    o.name AS organization_name,
    (SELECT COUNT(*) FROM booking_documents WHERE booking_id = b.id) AS document_count
FROM 
    bookings b
JOIN 
    booking_statuses bs ON b.status_id = bs.id
JOIN 
    users client ON b.user_id = client.id
JOIN 
    users cons ON b.consultant_id = cons.id
JOIN 
    consultants c ON cons.id = c.user_id
JOIN 
    visa_services vs ON b.visa_service_id = vs.visa_service_id
JOIN 
    service_consultation_modes scm ON b.service_consultation_id = scm.service_consultation_id
JOIN 
    consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
JOIN 
    visas v ON vs.visa_id = v.visa_id
JOIN 
    countries co ON v.country_id = co.country_id
JOIN 
    service_types st ON vs.service_type_id = st.service_type_id
JOIN 
    organizations o ON b.organization_id = o.id
LEFT JOIN 
    booking_payments bp ON b.id = bp.booking_id
WHERE 
    b.id = ? AND b.user_id = ? AND b.deleted_at IS NULL";

$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $meeting_id, $applicant_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: meetings.php");
    exit;
}

$meeting = $result->fetch_assoc();
$stmt->close();

// Format date and time
$meeting_date = date('l, F j, Y', strtotime($meeting['booking_datetime']));
$meeting_time = date('g:i A', strtotime($meeting['booking_datetime']));
$meeting_end_time = date('g:i A', strtotime($meeting['end_datetime']));

// Get consultant profile picture
$profile_img = '../../assets/images/default-profile.svg';
if (!empty($meeting['consultant_profile'])) {
    // Check both new and legacy path structures
    if (file_exists('../../uploads/users/' . $meeting['consultant_id'] . '/profile/' . $meeting['consultant_profile'])) {
        $profile_img = '../../uploads/users/' . $meeting['consultant_id'] . '/profile/' . $meeting['consultant_profile'];
    } elseif (file_exists('../../uploads/profiles/' . $meeting['consultant_profile'])) {
        $profile_img = '../../uploads/profiles/' . $meeting['consultant_profile'];
    }
}

// Check if the meeting can be joined (within 1 hour of start time)
$can_join = $meeting['status_name'] === 'confirmed' && (abs(strtotime($meeting['booking_datetime']) - time()) < 3600);

// Check if the meeting can be cancelled (not in past and not already cancelled)
$can_cancel = in_array($meeting['status_name'], ['pending', 'confirmed']) && (strtotime($meeting['booking_datetime']) > time());

// Get booking documents if any
$documents_query = "SELECT id, document_name, document_path, created_at AS uploaded_at, notes AS description 
                   FROM booking_documents 
                   WHERE booking_id = ? 
                   ORDER BY created_at DESC";
$stmt = $conn->prepare($documents_query);
$stmt->bind_param('i', $meeting_id);
$stmt->execute();
$documents_result = $stmt->get_result();
$stmt->close();

// Get booking activity logs
$logs_query = "SELECT bal.id, bal.activity_type, bal.description, bal.created_at, 
                     CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.profile_picture
              FROM booking_activity_logs bal
              LEFT JOIN users u ON bal.user_id = u.id
              WHERE bal.booking_id = ?
              ORDER BY bal.created_at DESC";
$stmt = $conn->prepare($logs_query);
$stmt->bind_param('i', $meeting_id);
$stmt->execute();
$logs_result = $stmt->get_result();
$stmt->close();
?>

<div class="content">
    <div class="dashboard-header">
        <div class="header-actions">
            <a href="meetings.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Meetings
            </a>
        </div>
        <h1>Meeting Details</h1>
        <p>View information about your scheduled meeting</p>
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
    
    <div class="meeting-details-container">
        <div class="meeting-details">
            <div class="meeting-header">
                <div class="meeting-reference">
                    Reference: <strong><?php echo htmlspecialchars($meeting['reference_number']); ?></strong>
                </div>
                
                <div class="meeting-status">
                    Status: <span class="status-badge" style="background-color: <?php echo htmlspecialchars($meeting['status_color']); ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $meeting['status_name'])); ?>
                    </span>
                </div>
            </div>
            
            <div class="detail-section">
                <h3>Appointment Details</h3>
                
                <div class="detail-row">
                    <div class="detail-label">Date:</div>
                    <div class="detail-value"><?php echo $meeting_date; ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Time:</div>
                    <div class="detail-value"><?php echo $meeting_time . ' - ' . $meeting_end_time; ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Duration:</div>
                    <div class="detail-value"><?php echo $meeting['duration_minutes']; ?> minutes</div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Time Zone:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($meeting['time_zone']); ?></div>
                </div>
                
                <?php if ($meeting['consultation_mode'] === 'In-Person' && !empty($meeting['location'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Location:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($meeting['location']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($meeting['consultation_mode'] === 'Online' && !empty($meeting['meeting_link']) && $can_join): ?>
                <div class="detail-row">
                    <div class="detail-label">Meeting Link:</div>
                    <div class="detail-value">
                        <a href="<?php echo htmlspecialchars($meeting['meeting_link']); ?>" target="_blank" class="meeting-link">
                            <i class="fas fa-video"></i> Join Meeting
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="detail-section">
                <h3>Service Details</h3>
                
                <div class="detail-row">
                    <div class="detail-label">Service:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($meeting['service_name']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Visa Type:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($meeting['visa_type']); ?> - <?php echo htmlspecialchars($meeting['country_name']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Consultation Mode:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($meeting['consultation_mode']); ?></div>
                </div>
                
                <?php if (!empty($meeting['client_notes'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Your Notes:</div>
                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($meeting['client_notes'])); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="detail-row">
                    <div class="detail-label">Preferred Language:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($meeting['language_preference']); ?></div>
                </div>
            </div>
            
            <div class="detail-section">
                <h3>Consultant Information</h3>
                
                <div class="consultant-profile">
                    <img src="<?php echo $profile_img; ?>" alt="Consultant Profile" class="consultant-img">
                    <div class="consultant-details">
                        <h4><?php echo htmlspecialchars($meeting['consultant_name']); ?></h4>
                        <p class="company-name"><?php echo htmlspecialchars($meeting['company_name']); ?></p>
                        <p class="organization-name"><?php echo htmlspecialchars($meeting['organization_name']); ?></p>
                    </div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Email:</div>
                    <div class="detail-value">
                        <a href="mailto:<?php echo htmlspecialchars($meeting['consultant_email']); ?>">
                            <?php echo htmlspecialchars($meeting['consultant_email']); ?>
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($meeting['consultant_phone'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Phone:</div>
                    <div class="detail-value">
                        <a href="tel:<?php echo htmlspecialchars($meeting['consultant_phone']); ?>">
                            <?php echo htmlspecialchars($meeting['consultant_phone']); ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="detail-section">
                <h3>Payment Information</h3>
                
                <div class="detail-row">
                    <div class="detail-label">Total Amount:</div>
                    <div class="detail-value">
                        <?php echo number_format($meeting['total_price'], 2) . ' ' . ($meeting['currency'] ?? 'USD'); ?>
                    </div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Payment Status:</div>
                    <div class="detail-value">
                        <span class="payment-status <?php echo $meeting['payment_status']; ?>">
                            <?php echo ucfirst($meeting['payment_status'] ?? 'pending'); ?>
                        </span>
                    </div>
                </div>
                
                <?php if (!empty($meeting['payment_date'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Payment Date:</div>
                    <div class="detail-value"><?php echo date('F j, Y', strtotime($meeting['payment_date'])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($meeting['transaction_id'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Transaction ID:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($meeting['transaction_id']); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($meeting['status_name'] === 'cancelled_by_user' || $meeting['status_name'] === 'cancelled_by_consultant' || $meeting['status_name'] === 'cancelled_by_admin'): ?>
            <div class="detail-section">
                <h3>Cancellation Details</h3>
                
                <div class="detail-row">
                    <div class="detail-label">Cancelled On:</div>
                    <div class="detail-value"><?php echo date('F j, Y g:i A', strtotime($meeting['cancelled_at'])); ?></div>
                </div>
                
                <?php if (!empty($meeting['cancellation_reason'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Reason:</div>
                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($meeting['cancellation_reason'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($documents_result->num_rows > 0): ?>
            <div class="detail-section">
                <h3>Documents</h3>
                <div class="documents-list">
                    <?php while ($document = $documents_result->fetch_assoc()): ?>
                    <div class="document-item">
                        <div class="document-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="document-details">
                            <div class="document-name"><?php echo htmlspecialchars($document['file_name']); ?></div>
                            <div class="document-date"><?php echo date('M j, Y', strtotime($document['uploaded_at'])); ?></div>
                            <?php if (!empty($document['description'])): ?>
                            <div class="document-description"><?php echo htmlspecialchars($document['description']); ?></div>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo htmlspecialchars($document['file_path']); ?>" class="document-download" download>
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($logs_result->num_rows > 0): ?>
            <div class="detail-section">
                <h3>Activity Log</h3>
                <div class="activity-timeline">
                    <?php while ($log = $logs_result->fetch_assoc()): 
                        $log_profile_img = '../../assets/images/default-profile.svg';
                        if (!empty($log['profile_picture'])) {
                            if (file_exists('../../uploads/users/' . $log['user_id'] . '/profile/' . $log['profile_picture'])) {
                                $log_profile_img = '../../uploads/users/' . $log['user_id'] . '/profile/' . $log['profile_picture'];
                            } elseif (file_exists('../../uploads/profiles/' . $log['profile_picture'])) {
                                $log_profile_img = '../../uploads/profiles/' . $log['profile_picture'];
                            }
                        }
                    ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <img src="<?php echo $log_profile_img; ?>" alt="User" class="user-img">
                        </div>
                        <div class="activity-content">
                            <div class="activity-header">
                                <span class="activity-user"><?php echo htmlspecialchars($log['user_name']); ?></span>
                                <span class="activity-time"><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></span>
                            </div>
                            <div class="activity-description">
                                <?php echo htmlspecialchars($log['description']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="meeting-sidebar">
            <div class="action-card">
                <h3>Meeting Actions</h3>
                <div class="action-buttons">
                    <?php if ($can_join && !empty($meeting['meeting_link'])): ?>
                    <a href="<?php echo htmlspecialchars($meeting['meeting_link']); ?>" target="_blank" class="btn btn-success btn-block">
                        <i class="fas fa-video"></i> Join Meeting
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($can_cancel): ?>
                    <button type="button" class="btn btn-danger btn-block" data-toggle="modal" data-target="#cancelMeetingModal">
                        <i class="fas fa-times-circle"></i> Cancel Meeting
                    </button>
                    <?php endif; ?>
                    
                    <a href="messages.php?consultant=<?php echo $meeting['consultant_id']; ?>" class="btn btn-primary btn-block">
                        <i class="fas fa-comment"></i> Message Consultant
                    </a>
                </div>
            </div>
            
            <div class="info-card">
                <h3>Need Help?</h3>
                <p>If you have any questions about your meeting or need to make changes, please contact your consultant directly or our support team.</p>
                <a href="../../support.php" class="btn btn-outline btn-block">
                    <i class="fas fa-question-circle"></i> Contact Support
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Meeting Modal -->
<?php if ($can_cancel): ?>
<div class="modal fade" id="cancelMeetingModal" tabindex="-1" role="dialog" aria-labelledby="cancelMeetingModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancelMeetingModalLabel">Cancel Meeting</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="meetings.php" method="POST">
                <div class="modal-body">
                    <p>Are you sure you want to cancel this meeting? This action cannot be undone.</p>
                    <p><strong>Meeting Reference:</strong> <?php echo htmlspecialchars($meeting['reference_number']); ?></p>
                    
                    <div class="form-group">
                        <label for="cancellation_reason">Reason for Cancellation</label>
                        <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="3" required></textarea>
                    </div>
                    
                    <input type="hidden" name="booking_id" value="<?php echo $meeting_id; ?>">
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
<?php endif; ?>

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

.dashboard-header {
    margin-bottom: 20px;
    position: relative;
}

.header-actions {
    position: absolute;
    top: 0;
    right: 0;
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

.meeting-details-container {
    display: flex;
    gap: 30px;
    margin-bottom: 40px;
}

.meeting-details {
    flex: 2;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 30px;
}

.meeting-sidebar {
    flex: 1;
}

.meeting-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}

.meeting-reference {
    font-size: 16px;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    color: white;
}

.detail-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}

.detail-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.detail-section h3 {
    color: var(--primary-color);
    margin-bottom: 15px;
    font-size: 18px;
}

.detail-row {
    display: flex;
    margin-bottom: 10px;
}

.detail-label {
    flex: 1;
    font-weight: 500;
    color: var(--secondary-color);
}

.detail-value {
    flex: 2;
    color: var(--dark-color);
}

.consultant-profile {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}

.consultant-img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 20px;
}

.consultant-details h4 {
    margin: 0 0 5px;
    color: var(--primary-color);
    font-size: 18px;
}

.company-name {
    margin: 0 0 5px;
    font-weight: 500;
}

.organization-name {
    margin: 0;
    color: var(--secondary-color);
    font-size: 14px;
}

.payment-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.payment-status.completed {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.payment-status.pending {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.payment-status.failed {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.payment-status.partially_refunded, .payment-status.refunded {
    background-color: rgba(54, 185, 204, 0.1);
    color: var(--info-color);
}

.meeting-link {
    color: var(--primary-color);
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.meeting-link:hover {
    text-decoration: underline;
}

.documents-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.document-item {
    display: flex;
    align-items: center;
    padding: 10px;
    background-color: var(--light-color);
    border-radius: 8px;
}

.document-icon {
    font-size: 24px;
    color: var(--primary-color);
    margin-right: 15px;
}

.document-details {
    flex: 1;
}

.document-name {
    font-weight: 500;
    margin-bottom: 3px;
}

.document-date {
    font-size: 12px;
    color: var(--secondary-color);
}

.document-description {
    font-size: 14px;
    color: var(--dark-color);
    margin-top: 5px;
}

.document-download {
    color: var(--primary-color);
    font-size: 18px;
}

.activity-timeline {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.activity-item {
    display: flex;
    gap: 15px;
}

.activity-icon {
    flex-shrink: 0;
}

.user-img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.activity-content {
    flex: 1;
}

.activity-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.activity-user {
    font-weight: 500;
}

.activity-time {
    font-size: 12px;
    color: var(--secondary-color);
}

.activity-description {
    color: var(--dark-color);
    font-size: 14px;
}

.action-card, .info-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    margin-bottom: 20px;
}

.action-card h3, .info-card h3 {
    color: var(--primary-color);
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 18px;
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.info-card p {
    margin-bottom: 15px;
    color: var(--secondary-color);
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: background-color 0.2s;
}

.btn-block {
    display: flex;
    width: 100%;
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

.btn-success {
    background-color: var(--success-color);
    color: white;
}

.btn-success:hover {
    background-color: #169b6b;
}

.btn-danger {
    background-color: var(--danger-color);
    color: white;
}

.btn-danger:hover {
    background-color: #c13a2d;
}

.btn-outline {
    background-color: transparent;
    border: 1px solid var(--border-color);
    color: var(--secondary-color);
}

.btn-outline:hover {
    background-color: var(--light-color);
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

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

@media (max-width: 992px) {
    .meeting-details-container {
        flex-direction: column;
    }
    
    .meeting-details, 
    .meeting-sidebar {
        width: 100%;
    }
    
    .detail-row {
        flex-direction: column;
    }
    
    .detail-label {
        margin-bottom: 5px;
    }
}
</style>

<script>
// Any JavaScript functionality can be added here
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any components that need JavaScript
    
    // Example: Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });
});
</script>
<?php
// End output buffering and send content to browser
ob_end_flush();
?>
<?php
// Include footer
include('includes/footer.php');
?>