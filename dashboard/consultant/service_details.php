<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Service Details";
$page_specific_css = "assets/css/services.css";
require_once 'includes/header.php';

// Get consultant ID and organization ID from session
$consultant_id = isset($_SESSION['id']) ? $_SESSION['id'] : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
$organization_id = isset($user['organization_id']) ? $user['organization_id'] : null;

// Verify organization ID is set
if (!$organization_id) {
    die("Organization ID not set. Please log in again.");
}

// Get the visa service ID from URL parameter
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger'>Invalid service ID.</div>";
    require_once 'includes/footer.php';
    exit;
}
$visa_service_id = $_GET['id'];

// Get detailed information about the visa service
$query = "SELECT vs.*, v.visa_type, c.country_name, st.service_name, vs.is_bookable
          FROM visa_services vs
          JOIN visas v ON vs.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          JOIN service_types st ON vs.service_type_id = st.service_type_id
          WHERE vs.visa_service_id = ? AND vs.organization_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $visa_service_id, $organization_id);
$stmt->execute();
$service_result = $stmt->get_result();

if ($service_result->num_rows == 0) {
    echo "<div class='alert alert-danger'>Service not found or you don't have permission to view it.</div>";
    require_once 'includes/footer.php';
    exit;
}

$service = $service_result->fetch_assoc();
$stmt->close();

// Get consultation modes for this service
$query = "SELECT scm.*, cm.mode_name, cm.description as mode_description, cm.is_custom
          FROM service_consultation_modes scm
          JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
          WHERE scm.visa_service_id = ? AND scm.organization_id = ?
          ORDER BY cm.mode_name";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $visa_service_id, $organization_id);
$stmt->execute();
$consultation_modes_result = $stmt->get_result();
$consultation_modes = [];

if ($consultation_modes_result && $consultation_modes_result->num_rows > 0) {
    while ($row = $consultation_modes_result->fetch_assoc()) {
        $consultation_modes[] = $row;
    }
}
$stmt->close();

// Get required documents for this service
$query = "SELECT sd.*
          FROM service_documents sd
          WHERE sd.visa_service_id = ? AND sd.organization_id = ?
          ORDER BY sd.is_required DESC, sd.document_name";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $visa_service_id, $organization_id);
$stmt->execute();
$documents_result = $stmt->get_result();
$documents = [];

if ($documents_result && $documents_result->num_rows > 0) {
    while ($row = $documents_result->fetch_assoc()) {
        $documents[] = $row;
    }
}
$stmt->close();

// Get booking settings if this service is bookable
$booking_settings = null;
if ($service['is_bookable']) {
    $query = "SELECT * FROM service_booking_settings
              WHERE visa_service_id = ? AND organization_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $visa_service_id, $organization_id);
    $stmt->execute();
    $booking_settings_result = $stmt->get_result();
    
    if ($booking_settings_result && $booking_settings_result->num_rows > 0) {
        $booking_settings = $booking_settings_result->fetch_assoc();
    }
    $stmt->close();
}

// Get booking statistics
$bookings_stats = [
    'total' => 0,
    'completed' => 0,
    'pending' => 0,
    'cancelled' => 0,
    'upcoming' => 0
];

$query = "SELECT b.id, bs.name as status_name, 
          COUNT(*) as total,
          SUM(CASE WHEN bs.name = 'completed' THEN 1 ELSE 0 END) as completed,
          SUM(CASE WHEN bs.name = 'pending' THEN 1 ELSE 0 END) as pending,
          SUM(CASE WHEN bs.name IN ('cancelled_by_user', 'cancelled_by_admin', 'cancelled_by_consultant') THEN 1 ELSE 0 END) as cancelled,
          SUM(CASE WHEN bs.name = 'confirmed' AND b.booking_datetime > NOW() THEN 1 ELSE 0 END) as upcoming
          FROM bookings b
          JOIN booking_statuses bs ON b.status_id = bs.id
          WHERE b.visa_service_id = ? AND b.consultant_id = ? AND b.deleted_at IS NULL";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $visa_service_id, $consultant_id);
$stmt->execute();
$bookings_result = $stmt->get_result();

if ($bookings_result && $bookings_result->num_rows > 0) {
    $bookings_stats = $bookings_result->fetch_assoc();
}
$stmt->close();

// Get recent bookings for this service
$query = "SELECT b.id, b.booking_datetime, b.end_datetime, u.first_name, u.last_name, bs.name as status_name
          FROM bookings b
          JOIN users u ON b.user_id = u.id
          JOIN booking_statuses bs ON b.status_id = bs.id
          WHERE b.visa_service_id = ? AND b.consultant_id = ? AND b.deleted_at IS NULL
          ORDER BY b.booking_datetime DESC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $visa_service_id, $consultant_id);
$stmt->execute();
$recent_bookings_result = $stmt->get_result();
$recent_bookings = [];

if ($recent_bookings_result && $recent_bookings_result->num_rows > 0) {
    while ($row = $recent_bookings_result->fetch_assoc()) {
        $recent_bookings[] = $row;
    }
}
$stmt->close();

// Get service availability slots
$slots_query = "SELECT COUNT(*) as total_slots,
               SUM(CASE WHEN is_available = 1 AND current_bookings < max_bookings THEN 1 ELSE 0 END) as available_slots,
               MIN(slot_date) as first_available_date,
               MAX(slot_date) as last_available_date
               FROM service_availability_slots
               WHERE visa_service_id = ? AND consultant_id = ? AND slot_date >= CURDATE()";
$slots_stmt = $conn->prepare($slots_query);
$slots_stmt->bind_param('ii', $visa_service_id, $consultant_id);
$slots_stmt->execute();
$slots_result = $slots_stmt->get_result();
$slots_stats = $slots_result->fetch_assoc();
$slots_stmt->close();
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Service Details</h1>
            <p>View detailed information about this service</p>
        </div>
        <div>
            <a href="services.php" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Services
            </a>
            <a href="edit_service.php?id=<?php echo $visa_service_id; ?>" class="btn primary-btn">
                <i class="fas fa-edit"></i> Edit Service
            </a>
        </div>
    </div>

    <div class="service-details-container">
        <!-- Basic Service Information -->
        <div class="service-card">
            <div class="service-header">
                <h2><?php echo htmlspecialchars($service['service_name']); ?> - <?php echo htmlspecialchars($service['visa_type']); ?></h2>
                <div class="service-status">
                    <?php if ($service['is_active']): ?>
                    <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                    <?php else: ?>
                    <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                    <?php endif; ?>
                    <?php if ($service['is_bookable']): ?>
                    <span class="status-badge bookable"><i class="fas fa-check-circle"></i> Bookable</span>
                    <?php else: ?>
                    <span class="status-badge not-bookable"><i class="fas fa-times-circle"></i> Not Bookable</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="service-content">
                <div class="info-row">
                    <div class="info-label">Country:</div>
                    <div class="info-value"><?php echo htmlspecialchars($service['country_name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Base Price:</div>
                    <div class="info-value">$<?php echo number_format($service['base_price'], 2); ?></div>
                </div>
                <?php if (!empty($service['description'])): ?>
                <div class="info-row">
                    <div class="info-label">Description:</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($service['description'])); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($service['is_bookable'] && !empty($service['booking_instructions'])): ?>
                <div class="info-row">
                    <div class="info-label">Booking Instructions:</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($service['booking_instructions'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="details-grid">
            <!-- Consultation Modes -->
            <div class="service-card">
                <div class="service-header">
                    <h3>Consultation Modes</h3>
                    <a href="edit_service.php?id=<?php echo $visa_service_id; ?>#consultation-modes" class="btn-sm secondary-btn">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                </div>
                <div class="service-content">
                    <?php if (empty($consultation_modes)): ?>
                    <p class="empty-message">No consultation modes configured for this service.</p>
                    <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Mode</th>
                                <th>Additional Fee</th>
                                <th>Duration</th>
                                <th>Availability</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consultation_modes as $mode): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($mode['mode_name']); ?>
                                    <?php if ($mode['is_custom']): ?><span class="badge custom-badge">Custom</span><?php endif; ?>
                                </td>
                                <td>$<?php echo number_format($mode['additional_fee'], 2); ?></td>
                                <td><?php echo $mode['duration_minutes']; ?> minutes</td>
                                <td>
                                    <?php if ($mode['is_available']): ?>
                                    <span class="status-badge active"><i class="fas fa-check"></i> Available</span>
                                    <?php else: ?>
                                    <span class="status-badge inactive"><i class="fas fa-times"></i> Unavailable</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Required Documents -->
            <div class="service-card">
                <div class="service-header">
                    <h3>Required Documents</h3>
                    <a href="documents.php?service_id=<?php echo $visa_service_id; ?>" class="btn-sm secondary-btn">
                        <i class="fas fa-edit"></i> Manage
                    </a>
                </div>
                <div class="service-content">
                    <?php if (empty($documents)): ?>
                    <p class="empty-message">No required documents configured for this service.</p>
                    <?php else: ?>
                    <ul class="document-list">
                        <?php foreach ($documents as $doc): ?>
                        <li class="document-item">
                            <div class="document-name">
                                <?php echo htmlspecialchars($doc['document_name']); ?>
                                <?php if ($doc['is_required']): ?>
                                <span class="badge required-badge">Required</span>
                                <?php else: ?>
                                <span class="badge optional-badge">Optional</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($doc['description'])): ?>
                            <div class="document-description"><?php echo htmlspecialchars($doc['description']); ?></div>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($service['is_bookable'] && $booking_settings): ?>
            <!-- Booking Settings -->
            <div class="service-card">
                <div class="service-header">
                    <h3>Booking Settings</h3>
                    <a href="booking_settings.php?id=<?php echo $visa_service_id; ?>" class="btn-sm secondary-btn">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                </div>
                <div class="service-content">
                    <div class="info-row">
                        <div class="info-label">Minimum Notice:</div>
                        <div class="info-value"><?php echo $booking_settings['min_notice_hours']; ?> hours</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Maximum Advance Booking:</div>
                        <div class="info-value"><?php echo $booking_settings['max_advance_days']; ?> days</div>
                    </div>
                    <?php if (isset($booking_settings['buffer_before_minutes']) && $booking_settings['buffer_before_minutes'] > 0): ?>
                    <div class="info-row">
                        <div class="info-label">Buffer Before:</div>
                        <div class="info-value"><?php echo $booking_settings['buffer_before_minutes']; ?> minutes</div>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($booking_settings['buffer_after_minutes']) && $booking_settings['buffer_after_minutes'] > 0): ?>
                    <div class="info-row">
                        <div class="info-label">Buffer After:</div>
                        <div class="info-value"><?php echo $booking_settings['buffer_after_minutes']; ?> minutes</div>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($booking_settings['payment_required']) && $booking_settings['payment_required']): ?>
                    <div class="info-row">
                        <div class="info-label">Payment Required:</div>
                        <div class="info-value">Yes</div>
                    </div>
                    <?php if ($booking_settings['deposit_amount'] > 0): ?>
                    <div class="info-row">
                        <div class="info-label">Deposit Amount:</div>
                        <div class="info-value">$<?php echo number_format($booking_settings['deposit_amount'], 2); ?></div>
                    </div>
                    <?php elseif ($booking_settings['deposit_percentage'] > 0): ?>
                    <div class="info-row">
                        <div class="info-label">Deposit Percentage:</div>
                        <div class="info-value"><?php echo $booking_settings['deposit_percentage']; ?>%</div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Availability Stats -->
            <div class="service-card">
                <div class="service-header">
                    <h3>Availability</h3>
                    <a href="service_availability.php?id=<?php echo $visa_service_id; ?>" class="btn-sm secondary-btn">
                        <i class="fas fa-calendar-alt"></i> Manage
                    </a>
                </div>
                <div class="service-content">
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-value"><?php echo $slots_stats['total_slots'] ?? 0; ?></div>
                            <div class="stat-label">Total Slots</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?php echo $slots_stats['available_slots'] ?? 0; ?></div>
                            <div class="stat-label">Available Slots</div>
                        </div>
                    </div>
                    
                    <?php if (isset($slots_stats['first_available_date']) && $slots_stats['first_available_date']): ?>
                    <div class="availability-dates">
                        <div class="date-range">
                            <div class="date-label">First Available:</div>
                            <div class="date-value"><?php echo date('M j, Y', strtotime($slots_stats['first_available_date'])); ?></div>
                        </div>
                        <div class="date-range">
                            <div class="date-label">Last Available:</div>
                            <div class="date-value"><?php echo date('M j, Y', strtotime($slots_stats['last_available_date'])); ?></div>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="empty-message">No availability slots configured.</p>
                    <div class="text-center mt-3">
                        <a href="generate_slots.php?id=<?php echo $visa_service_id; ?>" class="btn secondary-btn">
                            <i class="fas fa-magic"></i> Generate Slots
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Booking Statistics -->
            <div class="service-card">
                <div class="service-header">
                    <h3>Booking Statistics</h3>
                </div>
                <div class="service-content">
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-value"><?php echo $bookings_stats['total'] ?? 0; ?></div>
                            <div class="stat-label">Total Bookings</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?php echo $bookings_stats['completed'] ?? 0; ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?php echo $bookings_stats['upcoming'] ?? 0; ?></div>
                            <div class="stat-label">Upcoming</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?php echo $bookings_stats['cancelled'] ?? 0; ?></div>
                            <div class="stat-label">Cancelled</div>
                        </div>
                    </div>
                    
                    <?php if (!empty($recent_bookings)): ?>
                    <h4 class="section-title">Recent Bookings</h4>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Client</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $booking): ?>
                            <tr>
                                <td>
                                    <?php echo date('M j, Y', strtotime($booking['booking_datetime'])); ?><br>
                                    <span class="time-range">
                                        <?php echo date('g:i A', strtotime($booking['booking_datetime'])); ?> - 
                                        <?php echo date('g:i A', strtotime($booking['end_datetime'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></td>
                                <td>
                                    <?php 
                                    $status_class = '';
                                    switch ($booking['status_name']) {
                                        case 'completed':
                                            $status_class = 'active';
                                            break;
                                        case 'confirmed':
                                            $status_class = 'bookable';
                                            break;
                                        case 'pending':
                                            $status_class = 'pending';
                                            break;
                                        default:
                                            $status_class = 'inactive';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo ucfirst($booking['status_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_booking.php?id=<?php echo $booking['id']; ?>" class="btn-action btn-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if ($bookings_stats['total'] > 5): ?>
                    <div class="text-center mt-3">
                        <a href="bookings.php?service_id=<?php echo $visa_service_id; ?>" class="btn secondary-btn">
                            View All Bookings
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <p class="empty-message">No bookings for this service yet.</p>
                    <?php endif; ?>
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

.btn {
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    border-radius: 4px;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.85rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border-radius: 12px;
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

.status-badge.bookable {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge.not-bookable {
    background-color: rgba(133, 135, 150, 0.1);
    color: var(--secondary-color);
}

.status-badge.pending {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.status-badge i {
    font-size: 8px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    text-align: left;
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
}

.data-table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--dark-color);
}

.data-table tbody tr:hover {
    background-color: rgba(4, 33, 103, 0.03);
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    font-size: 14px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    color: white;
    transition: background-color 0.2s;
}

.btn-view {
    background-color: var(--primary-color);
}

.btn-view:hover {
    background-color: #031c56;
}

.btn-edit {
    background-color: var(--warning-color);
}

.btn-edit:hover {
    background-color: #e0b137;
}

.btn-calendar {
    background-color: #4e73df;
}

.btn-calendar:hover {
    background-color: #375ad3;
}

/* Service details specific styles */
.service-details-container {
    margin-top: 20px;
}

.service-card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px;
    overflow: hidden;
}

.service-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
}

.service-header h2, .service-header h3 {
    margin: 0;
    color: var(--primary-color);
}

.service-header h3 {
    font-size: 1.2rem;
}

.service-status {
    display: flex;
    gap: 10px;
}

.service-content {
    padding: 20px;
}

.info-row {
    display: flex;
    margin-bottom: 12px;
}

.info-label {
    font-weight: 600;
    width: 160px;
    color: var(--dark-color);
}

.info-value {
    flex: 1;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.document-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.document-item {
    padding: 10px 0;
    border-bottom: 1px solid var(--border-color);
}

.document-item:last-child {
    border-bottom: none;
}

.document-name {
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.document-description {
    font-size: 0.9rem;
    color: var(--secondary-color);
    margin-top: 5px;
}

.badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 600;
}

.required-badge {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.optional-badge {
    background-color: rgba(133, 135, 150, 0.1);
    color: var(--secondary-color);
}

.custom-badge {
    background-color: rgba(78, 115, 223, 0.1);
    color: #4e73df;
    margin-left: 5px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-box {
    padding: 15px;
    background-color: #f8f9fc;
    border-radius: 5px;
    text-align: center;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary-color);
}

.stat-label {
    font-size: 0.9rem;
    color: var(--secondary-color);
    margin-top: 5px;
}

.empty-message {
    color: var(--secondary-color);
    font-style: italic;
    text-align: center;
    padding: 20px 0;
}

.section-title {
    font-size: 1.1rem;
    color: var(--primary-color);
    margin: 20px 0 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.time-range {
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.availability-dates {
    margin-top: 15px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.date-range {
    background-color: #f8f9fc;
    padding: 10px;
    border-radius: 5px;
}

.date-label {
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.date-value {
    font-weight: 600;
    color: var(--primary-color);
    margin-top: 5px;
}

.mt-3 {
    margin-top: 15px;
}

.text-center {
    text-align: center;
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
</style>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>
<?php require_once 'includes/footer.php'; ?>
