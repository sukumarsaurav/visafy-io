<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'config/db_connect.php';


// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Check if booking ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$booking_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Get booking information
$query = "SELECT 
    b.id,
    b.reference_number,
    b.booking_datetime,
    b.end_datetime,
    b.duration_minutes,
    b.client_notes,
    b.language_preference,
    bs.name AS status_name,
    bs.color AS status_color,
    CONCAT(client.first_name, ' ', client.last_name) AS client_name,
    client.email AS client_email,
    CONCAT(cons.first_name, ' ', cons.last_name) AS consultant_name,
    c.company_name,
    v.visa_type,
    co.country_name,
    st.service_name,
    cm.mode_name AS consultation_mode,
    vs.base_price,
    scm.additional_fee,
    (vs.base_price + IFNULL(scm.additional_fee, 0)) AS total_price,
    bp.payment_status,
    o.name AS organization_name
FROM 
    bookings b
JOIN 
    booking_statuses bs ON b.status_id = bs.id COLLATE utf8mb4_general_ci
JOIN 
    users client ON b.user_id = client.id COLLATE utf8mb4_general_ci
JOIN 
    users cons ON b.consultant_id = cons.id COLLATE utf8mb4_general_ci
JOIN 
    consultants c ON cons.id = c.user_id COLLATE utf8mb4_general_ci
JOIN 
    visa_services vs ON b.visa_service_id = vs.visa_service_id COLLATE utf8mb4_general_ci
JOIN 
    service_consultation_modes scm ON b.service_consultation_id = scm.service_consultation_id COLLATE utf8mb4_general_ci
JOIN 
    consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id COLLATE utf8mb4_general_ci
JOIN 
    visas v ON vs.visa_id = v.visa_id COLLATE utf8mb4_general_ci
JOIN 
    countries co ON v.country_id = co.country_id COLLATE utf8mb4_general_ci
JOIN 
    service_types st ON vs.service_type_id = st.service_type_id COLLATE utf8mb4_general_ci
JOIN 
    organizations o ON b.organization_id = o.id COLLATE utf8mb4_general_ci
LEFT JOIN 
    booking_payments bp ON b.id = bp.booking_id COLLATE utf8mb4_general_ci
WHERE 
    b.id = ? AND (b.user_id = ? OR b.consultant_id = ? OR ? IN (
        SELECT member_user_id FROM team_members 
        WHERE consultant_id = b.consultant_id COLLATE utf8mb4_general_ci AND invitation_status = 'accepted'
    ))";

$stmt = $conn->prepare($query);
$stmt->bind_param('iiii', $booking_id, $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: dashboard.php");
    exit;
}

$booking = $result->fetch_assoc();
$stmt->close();

$page_title = "Booking Confirmation";
require_once 'includes/header.php';

// Format date and time
$booking_date = date('l, F j, Y', strtotime($booking['booking_datetime']));
$booking_time = date('g:i A', strtotime($booking['booking_datetime']));
$booking_end_time = date('g:i A', strtotime($booking['end_datetime']));

// Get payment information
$payment_query = "SELECT 
    id, 
    amount, 
    currency, 
    payment_method, 
    payment_status, 
    payment_date,
    transaction_id
FROM 
    booking_payments
WHERE 
    booking_id = ?
ORDER BY 
    id DESC
LIMIT 1";

$payment_stmt = $conn->prepare($payment_query);
$payment_stmt->bind_param('i', $booking_id);
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();
$payment = $payment_result->fetch_assoc();
$payment_stmt->close();
?>

<div class="container">
    <div class="confirmation-header">
        <div class="confirmation-badge">
            <i class="fas fa-check-circle"></i>
        </div>
        <h1>Booking Confirmed</h1>
        <p>Your consultation has been successfully booked.</p>
        
        <?php if (isset($_SESSION['booking_success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['booking_success']; 
                    unset($_SESSION['booking_success']);
                ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="confirmation-container">
        <div class="booking-details">
            <div class="booking-reference">
                Booking Reference: <strong><?php echo htmlspecialchars($booking['reference_number']); ?></strong>
            </div>
            
            <div class="booking-status">
                Status: <span class="status-badge" style="background-color: <?php echo htmlspecialchars($booking['status_color']); ?>;">
                    <?php echo htmlspecialchars($booking['status_name']); ?>
                </span>
            </div>
            
            <div class="detail-section">
                <h3>Appointment Details</h3>
                
                <div class="detail-row">
                    <div class="detail-label">Date:</div>
                    <div class="detail-value"><?php echo $booking_date; ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Time:</div>
                    <div class="detail-value"><?php echo $booking_time . ' - ' . $booking_end_time; ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Duration:</div>
                    <div class="detail-value"><?php echo $booking['duration_minutes']; ?> minutes</div>
                </div>
            </div>
            
            <div class="detail-section">
                <h3>Service Details</h3>
                
                <div class="detail-row">
                    <div class="detail-label">Service:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($booking['country_name'] . ' - ' . $booking['visa_type'] . ' - ' . $booking['service_name']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Consultation Mode:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($booking['consultation_mode']); ?></div>
                </div>
                
                <?php if (!empty($booking['client_notes'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Notes:</div>
                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($booking['client_notes'])); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="detail-row">
                    <div class="detail-label">Preferred Language:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($booking['language_preference']); ?></div>
                </div>
            </div>
            
            <div class="detail-section">
                <h3>Consultant Information</h3>
                
                <div class="detail-row">
                    <div class="detail-label">Consultant:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($booking['consultant_name']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Company:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($booking['company_name']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Organization:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($booking['organization_name']); ?></div>
                </div>
            </div>
            
            <div class="detail-section">
                <h3>Payment Information</h3>
                
                <div class="detail-row">
                    <div class="detail-label">Total Amount:</div>
                    <div class="detail-value"><?php echo number_format($booking['total_price'], 2) . ' ' . ($payment ? $payment['currency'] : 'USD'); ?></div>
                </div>
                
                <?php if ($payment): ?>
                <div class="detail-row">
                    <div class="detail-label">Payment Status:</div>
                    <div class="detail-value">
                        <span class="payment-status <?php echo $payment['payment_status']; ?>">
                            <?php echo ucfirst($payment['payment_status']); ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($payment['payment_status'] === 'completed' || $payment['payment_status'] === 'partially_refunded'): ?>
                <div class="detail-row">
                    <div class="detail-label">Payment Date:</div>
                    <div class="detail-value"><?php echo date('F j, Y', strtotime($payment['payment_date'])); ?></div>
                </div>
                
                <?php if ($payment['transaction_id']): ?>
                <div class="detail-row">
                    <div class="detail-label">Transaction ID:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($payment['transaction_id']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="confirmation-sidebar">
            <div class="action-card">
                <h3>Next Steps</h3>
                <ul class="action-list">
                    <li>
                        <i class="fas fa-calendar-alt"></i>
                        <span>You'll receive a confirmation email with details of your booking.</span>
                    </li>
                    <li>
                        <i class="fas fa-bell"></i>
                        <span>A reminder will be sent 24 hours before your appointment.</span>
                    </li>
                    <?php if ($booking['payment_status'] === 'pending'): ?>
                    <li>
                        <i class="fas fa-credit-card"></i>
                        <span>Complete your payment to secure your booking.</span>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <div class="action-buttons">
                    <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                    <a href="view-booking.php?id=<?php echo $booking_id; ?>" class="btn btn-secondary">View Booking Details</a>
                    <?php if ($booking['payment_status'] === 'pending'): ?>
                    <a href="process-payment.php?booking_id=<?php echo $booking_id; ?>" class="btn btn-success">Complete Payment</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="info-card">
                <h3>Need to Make Changes?</h3>
                <p>If you need to reschedule or cancel your booking, you can do so up to 24 hours before your appointment.</p>
                <div class="action-links">
                    <a href="reschedule-booking.php?id=<?php echo $booking_id; ?>" class="action-link">
                        <i class="fas fa-calendar-plus"></i> Reschedule Booking
                    </a>
                    <a href="cancel-booking.php?id=<?php echo $booking_id; ?>" class="action-link">
                        <i class="fas fa-times-circle"></i> Cancel Booking
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="create-application-container">
        <h3>Would you like to create an application?</h3>
        <p>You can create a visa application now based on this consultation booking.</p>
        <a href="create-application.php?booking_id=<?php echo $booking_id; ?>" class="btn btn-outline">Create Application</a>
    </div>
</div>

<style>
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 30px 15px;
}

.confirmation-header {
    text-align: center;
    margin-bottom: 40px;
}

.confirmation-badge {
    font-size: 60px;
    color: #28a745;
    margin-bottom: 20px;
}

.confirmation-header h1 {
    color: #042167;
    margin-bottom: 10px;
}

.alert {
    padding: 15px;
    border-radius: 4px;
    margin: 20px auto;
    max-width: 600px;
}

.alert-success {
    background-color: rgba(40, 167, 69, 0.1);
    border: 1px solid rgba(40, 167, 69, 0.2);
    color: #28a745;
}

.confirmation-container {
    display: flex;
    gap: 30px;
    margin-bottom: 40px;
}

.booking-details {
    flex: 2;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 30px;
}

.confirmation-sidebar {
    flex: 1;
}

.booking-reference {
    font-size: 18px;
    margin-bottom: 10px;
}

.booking-status {
    margin-bottom: 20px;
}

.status-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    color: white;
    font-size: 14px;
    font-weight: 500;
}

.detail-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.detail-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.detail-section h3 {
    color: #042167;
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
    color: #666;
}

.detail-value {
    flex: 2;
    color: #333;
}

.payment-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.payment-status.completed {
    background-color: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.payment-status.pending {
    background-color: rgba(255, 193, 7, 0.1);
    color: #ffc107;
}

.payment-status.failed {
    background-color: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}

.payment-status.partially_refunded, .payment-status.refunded {
    background-color: rgba(23, 162, 184, 0.1);
    color: #17a2b8;
}

.action-card, .info-card {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 20px;
}

.action-card h3, .info-card h3 {
    color: #042167;
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 18px;
}

.action-list {
    list-style: none;
    padding: 0;
    margin: 0 0 20px 0;
}

.action-list li {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    align-items: flex-start;
}

.action-list li i {
    color: #042167;
    font-size: 16px;
    margin-top: 2px;
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn {
    padding: 10px 15px;
    border-radius: 4px;
    text-align: center;
    text-decoration: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-block;
}

.btn-primary {
    background-color: #042167;
    color: white;
    border: none;
}

.btn-primary:hover {
    background-color: #031854;
}

.btn-secondary {
    background-color: #f0f0f0;
    color: #333;
    border: none;
}

.btn-secondary:hover {
    background-color: #e0e0e0;
}

.btn-success {
    background-color: #28a745;
    color: white;
    border: none;
}

.btn-success:hover {
    background-color: #218838;
}

.btn-outline {
    background-color: transparent;
    border: 1px solid #042167;
    color: #042167;
}

.btn-outline:hover {
    background-color: #f0f4ff;
}

.info-card p {
    margin-bottom: 15px;
    color: #666;
}

.action-links {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.action-link {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #042167;
    text-decoration: none;
    padding: 8px 0;
    border-radius: 4px;
    transition: all 0.2s;
}

.action-link:hover {
    text-decoration: underline;
}

.create-application-container {
    background-color: #f0f4ff;
    border-radius: 8px;
    padding: 30px;
    text-align: center;
    margin-bottom: 30px;
}

.create-application-container h3 {
    color: #042167;
    margin-top: 0;
    margin-bottom: 10px;
}

.create-application-container p {
    color: #666;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .confirmation-container {
        flex-direction: column;
    }
    
    .booking-details, 
    .confirmation-sidebar {
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

<?php require_once 'includes/footer.php'; ?>