<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Client Details";
$page_specific_css = "assets/css/clients.css";
require_once 'includes/header.php';

// Get organization_id from session user
$organization_id = isset($_SESSION['organization_id']) ? $_SESSION['organization_id'] : 0;
$current_user_id = $_SESSION['id'];

// Check if client ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: clients.php");
    exit;
}

$client_id = $_GET['id'];

// Get client details
$query = "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.status, u.profile_picture, 
          u.email_verified, u.created_at,
          a.date_of_birth, a.nationality as nationality, a.current_country, a.place_of_birth, 
          acr.relationship_type, acr.notes as relationship_notes,
          COUNT(DISTINCT b.id) as booking_count
          FROM users u
          LEFT JOIN applicants a ON u.id = a.user_id
          LEFT JOIN applicant_consultant_relationships acr ON a.user_id = acr.applicant_id
          LEFT JOIN bookings b ON u.id = b.user_id AND b.organization_id = ?
          WHERE u.id = ? AND u.user_type = 'applicant'
          GROUP BY u.id, u.first_name, u.last_name, u.email, u.phone, u.status, u.profile_picture, 
                  u.email_verified, u.created_at, a.date_of_birth, a.nationality, 
                  a.current_country, a.place_of_birth, acr.relationship_type, acr.notes";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $organization_id, $client_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $client = $result->fetch_assoc();
} else {
    // Client not found or doesn't belong to this organization
    header("Location: clients.php");
    exit;
}
$stmt->close();

// Get booking history
$query = "SELECT b.id, b.reference_number, b.booking_datetime, b.end_datetime, 
          bs.name as status_name, bs.color as status_color,
          v.visa_type, st.service_name, cm.mode_name as consultation_mode,
          CONCAT(cu.first_name, ' ', cu.last_name) as consultant_name,
          COALESCE(bf.rating, 0) as rating
          FROM bookings b
          JOIN booking_statuses bs ON b.status_id = bs.id
          JOIN visa_services vs ON b.visa_service_id = vs.visa_service_id
          JOIN visas v ON vs.visa_id = v.visa_id
          JOIN service_types st ON vs.service_type_id = st.service_type_id
          JOIN service_consultation_modes scm ON b.service_consultation_id = scm.service_consultation_id
          JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
          JOIN users cu ON b.consultant_id = cu.id
          LEFT JOIN booking_feedback bf ON b.id = bf.booking_id
          WHERE b.user_id = ? AND b.organization_id = ? AND b.deleted_at IS NULL
          ORDER BY b.booking_datetime DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $client_id, $organization_id);
$stmt->execute();
$bookings_result = $stmt->get_result();
$bookings = [];

if ($bookings_result && $bookings_result->num_rows > 0) {
    while ($row = $bookings_result->fetch_assoc()) {
        $bookings[] = $row;
    }
}
$stmt->close();

// Get applications
$query = "SELECT a.id, a.reference_number, a.created_at, 
          v.visa_type, c.country_name, 
          aps.name as status_name, aps.color as status_color
          FROM applications a
          JOIN visas v ON a.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          JOIN application_statuses aps ON a.status_id = aps.id
          WHERE a.user_id = ? AND a.organization_id = ? AND a.deleted_at IS NULL
          ORDER BY a.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $client_id, $organization_id);
$stmt->execute();
$applications_result = $stmt->get_result();
$applications = [];

if ($applications_result && $applications_result->num_rows > 0) {
    while ($row = $applications_result->fetch_assoc()) {
        $applications[] = $row;
    }
}
$stmt->close();

// Handle client account deactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_client'])) {
    $user_id = $_POST['user_id'];
    
    // Soft delete - update status to suspended
    $update_query = "UPDATE users SET status = 'suspended' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('i', $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Client account deactivated successfully";
        $stmt->close();
        header("Location: view_client.php?id=$user_id&success=1");
        exit;
    } else {
        $error_message = "Error deactivating client account: " . $conn->error;
        $stmt->close();
    }
}

// Handle client account reactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_client'])) {
    $user_id = $_POST['user_id'];
    
    // Update status to active
    $update_query = "UPDATE users SET status = 'active' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('i', $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Client account reactivated successfully";
        $stmt->close();
        header("Location: view_client.php?id=$user_id&success=2");
        exit;
    } else {
        $error_message = "Error reactivating client account: " . $conn->error;
        $stmt->close();
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Client account deactivated successfully";
            break;
        case 2:
            $success_message = "Client account reactivated successfully";
            break;
        case 3:
            $success_message = "Client relationship updated successfully";
            break;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Client Details</h1>
            <p>View and manage client information</p>
        </div>
        <div>
            <a href="clients.php" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Clients
            </a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Client Profile Section -->
    <div class="client-profile-container">
        <div class="client-profile-header">
            <div class="client-avatar-large">
                <?php if (!empty($client['profile_picture']) && file_exists('../../uploads/profiles/' . $client['profile_picture'])): ?>
                    <img src="../../uploads/profiles/<?php echo $client['profile_picture']; ?>" alt="Profile picture">
                <?php else: ?>
                    <div class="initials-large">
                        <?php echo substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="client-info">
                <h2><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></h2>
                <div class="client-badges">
                    <span class="status-badge <?php echo $client['status'] === 'active' ? 'active' : 'inactive'; ?>">
                        <i class="fas fa-circle"></i> <?php echo ucfirst($client['status']); ?>
                    </span>
                    
                    <?php if (!empty($client['relationship_type'])): ?>
                        <span class="relationship-badge <?php echo $client['relationship_type']; ?>">
                            <?php echo ucfirst($client['relationship_type']); ?> Client
                        </span>
                    <?php else: ?>
                        <span class="relationship-badge">Client</span>
                    <?php endif; ?>
                    
                    <?php if ($client['email_verified']): ?>
                        <span class="status-badge email-verified">
                            <i class="fas fa-check-circle"></i> Email Verified
                        </span>
                    <?php else: ?>
                        <span class="status-badge email-pending">
                            <i class="fas fa-exclamation-circle"></i> Email Not Verified
                        </span>
                    <?php endif; ?>
                </div>
                <div class="client-actions">
                    <?php if ($client['status'] === 'active'): ?>
                        <button type="button" class="btn danger-btn" 
                                onclick="confirmAction('deactivate', <?php echo $client['id']; ?>)">
                            <i class="fas fa-user-slash"></i> Deactivate Account
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn success-btn" 
                                onclick="confirmAction('activate', <?php echo $client['id']; ?>)">
                            <i class="fas fa-user-check"></i> Activate Account
                        </button>
                    <?php endif; ?>
                    
                    <button type="button" class="btn primary-btn" 
                            onclick="openRelationshipModal(<?php echo $client['id']; ?>, '<?php echo $client['relationship_type']; ?>')">
                        <i class="fas fa-user-friends"></i> Update Relationship
                    </button>
                    
                    <a href="create_booking.php?client_id=<?php echo $client['id']; ?>" class="btn info-btn">
                        <i class="fas fa-calendar-plus"></i> Create Booking
                    </a>

                    <a href="client_bookings.php?client_id=<?php echo $client['id']; ?>" class="btn secondary-btn">
                        <i class="fas fa-calendar-alt"></i> View All Bookings
                    </a>
                </div>
            </div>
        </div>
        
        <div class="client-details-section">
            <h3>Personal Information</h3>
            <div class="details-grid">
                <div class="detail-item">
                    <div class="detail-label">Email</div>
                    <div class="detail-value"><?php echo htmlspecialchars($client['email']); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Phone</div>
                    <div class="detail-value"><?php echo !empty($client['phone']) ? htmlspecialchars($client['phone']) : '—'; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Date of Birth</div>
                    <div class="detail-value"><?php echo !empty($client['date_of_birth']) ? date('M d, Y', strtotime($client['date_of_birth'])) : '—'; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Nationality</div>
                    <div class="detail-value"><?php echo !empty($client['nationality']) ? htmlspecialchars($client['nationality']) : '—'; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Current Country</div>
                    <div class="detail-value"><?php echo !empty($client['current_country']) ? htmlspecialchars($client['current_country']) : '—'; ?></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Place of Birth</div>
                    <div class="detail-value"><?php echo !empty($client['place_of_birth']) ? htmlspecialchars($client['place_of_birth']) : '—'; ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Member Since</div>
                    <div class="detail-value"><?php echo date('M d, Y', strtotime($client['created_at'])); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Total Bookings</div>
                    <div class="detail-value"><?php echo $client['booking_count']; ?></div>
                </div>
                
                <?php if (!empty($client['relationship_notes'])): ?>
                <div class="detail-item full-width">
                    <div class="detail-label">Relationship Notes</div>
                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($client['relationship_notes'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Booking History Section -->
        <div class="client-details-section">
            <h3>Booking History</h3>
            <?php if (empty($bookings)): ?>
                <div class="empty-state small">
                    <i class="fas fa-calendar-day"></i>
                    <p>No bookings found for this client.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Ref #</th>
                                <th>Date & Time</th>
                                <th>Service</th>
                                <th>Consultation Type</th>
                                <th>Consultant</th>
                                <th>Status</th>
                                <th>Rating</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['reference_number']); ?></td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($booking['booking_datetime'])); ?><br>
                                        <span class="time"><?php echo date('h:i A', strtotime($booking['booking_datetime'])); ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($booking['visa_type']); ?></strong><br>
                                        <span><?php echo htmlspecialchars($booking['service_name']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($booking['consultation_mode']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['consultant_name']); ?></td>
                                    <td>
                                        <span class="status-badge" style="background-color: <?php echo $booking['status_color']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $booking['status_name'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($booking['rating'] > 0): ?>
                                            <div class="star-rating" title="<?php echo $booking['rating']; ?> out of 5">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <?php if ($i <= $booking['rating']): ?>
                                                        <i class="fas fa-star"></i>
                                                    <?php else: ?>
                                                        <i class="far fa-star"></i>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="no-rating">Not rated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="view_booking.php?id=<?php echo $booking['id']; ?>" class="btn-action btn-view" title="View Booking Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Applications Section -->
        <div class="client-details-section">
            <h3>Applications</h3>
            <?php if (empty($applications)): ?>
                <div class="empty-state small">
                    <i class="fas fa-file-alt"></i>
                    <p>No visa applications found for this client.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reference #</th>
                                <th>Date Created</th>
                                <th>Visa Type</th>
                                <th>Country</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($app['reference_number']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($app['visa_type']); ?></td>
                                    <td><?php echo htmlspecialchars($app['country_name']); ?></td>
                                    <td>
                                        <span class="status-badge" style="background-color: <?php echo $app['status_color']; ?>">
                                            <?php echo ucfirst($app['status_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view_application.php?id=<?php echo $app['id']; ?>" class="btn-action btn-view" title="View Application Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hidden forms for actions -->
<form id="deactivateForm" action="view_client.php?id=<?php echo $client_id; ?>" method="POST" style="display: none;">
    <input type="hidden" name="user_id" id="deactivate_user_id" value="<?php echo $client_id; ?>">
    <input type="hidden" name="deactivate_client" value="1">
</form>

<form id="activateForm" action="view_client.php?id=<?php echo $client_id; ?>" method="POST" style="display: none;">
    <input type="hidden" name="user_id" id="activate_user_id" value="<?php echo $client_id; ?>">
    <input type="hidden" name="reactivate_client" value="1">
</form>

<!-- Relationship Modal -->
<div class="modal" id="relationshipModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Client Relationship</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="update_client_relationship.php" method="POST" id="relationshipForm">
                    <input type="hidden" name="applicant_id" id="applicant_id" value="<?php echo $client_id; ?>">
                    <input type="hidden" name="redirect" value="view_client.php?id=<?php echo $client_id; ?>">
                    
                    <div class="form-group">
                        <label for="relationship_type">Relationship Type*</label>
                        <select name="relationship_type" id="relationship_type" class="form-control" required>
                            <option value="primary" <?php echo ($client['relationship_type'] == 'primary') ? 'selected' : ''; ?>>Primary</option>
                            <option value="secondary" <?php echo ($client['relationship_type'] == 'secondary') ? 'selected' : ''; ?>>Secondary</option>
                            <option value="referred" <?php echo ($client['relationship_type'] == 'referred') ? 'selected' : ''; ?>>Referred</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="relationship_notes">Notes</label>
                        <textarea name="notes" id="relationship_notes" class="form-control" rows="3"><?php echo htmlspecialchars($client['relationship_notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_relationship" class="btn submit-btn">Update Relationship</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Additional styles specific to client view page */
.client-profile-container {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    margin-bottom: 20px;
}

.client-profile-header {
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    border-bottom: 1px solid var(--border-color);
}

.client-avatar-large {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    overflow: hidden;
    background-color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
}

.client-avatar-large img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.initials-large {
    color: white;
    font-weight: 600;
    font-size: 36px;
}

.client-info {
    flex: 1;
}

.client-info h2 {
    margin: 0 0 10px 0;
    color: var(--dark-color);
    font-size: 24px;
}

.client-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 15px;
}

.client-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.secondary-btn {
    background-color: var(--light-color);
    color: var(--primary-color);
    border: 1px solid var(--border-color);
    padding: 8px 15px;
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

.danger-btn {
    background-color: var(--danger-color);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

.danger-btn:hover {
    background-color: #d44235;
}

.success-btn {
    background-color: var(--success-color);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

.success-btn:hover {
    background-color: #18b07b;
}

.info-btn {
    background-color: #36b9cc;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.info-btn:hover {
    background-color: #2ca8b9;
    color: white;
    text-decoration: none;
}

.client-details-section {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
}

.client-details-section:last-child {
    border-bottom: none;
}

.client-details-section h3 {
    margin: 0 0 15px 0;
    color: var(--primary-color);
    font-size: 18px;
    font-weight: 600;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
}

.detail-item {
    margin-bottom: 10px;
}

.detail-item.full-width {
    grid-column: 1 / -1;
}

.detail-label {
    font-size: 12px;
    color: var(--secondary-color);
    margin-bottom: 4px;
}

.detail-value {
    font-size: 14px;
    color: var(--dark-color);
}

.status-badge.email-verified {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge.email-pending {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.table-responsive {
    overflow-x: auto;
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
    font-size: 14px;
}

.data-table tbody tr:hover {
    background-color: rgba(4, 33, 103, 0.03);
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}

.time {
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.star-rating {
    color: #f6c23e;
    font-size: 12px;
}

.no-rating {
    color: var(--secondary-color);
    font-style: italic;
    font-size: 12px;
}

.empty-state.small {
    padding: 30px 20px;
}

.empty-state.small i {
    font-size: 36px;
    margin-bottom: 10px;
}

@media (max-width: 768px) {
    .client-profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .client-badges, .client-actions {
        justify-content: center;
    }
    
    .details-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Function to handle action confirmations (deactivate, activate)
function confirmAction(action, userId) {
    switch(action) {
        case 'deactivate':
            if (confirm('Are you sure you want to deactivate this client account?')) {
                document.getElementById('deactivateForm').submit();
            }
            break;
        case 'activate':
            document.getElementById('activateForm').submit();
            break;
    }
}

// Function to open relationship modal
function openRelationshipModal(applicantId, relationshipType) {
    document.getElementById('applicant_id').value = applicantId;
    
    // Set the selected relationship type if it exists
    if (relationshipType) {
        document.getElementById('relationship_type').value = relationshipType;
    } else {
        document.getElementById('relationship_type').value = 'primary'; // Default
    }
    
    // Show the modal
    document.getElementById('relationshipModal').style.display = 'block';
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
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
