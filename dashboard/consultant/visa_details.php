<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Visa Details";
$page_specific_css = "assets/css/visa.css";
require_once 'includes/header.php';

// Get visa ID from URL
$visa_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($visa_id <= 0) {
    header("Location: visa.php");
    exit;
}

// Get visa details with country information
$query = "SELECT v.*, c.country_name, c.country_code 
          FROM visas v 
          JOIN countries c ON v.country_id = c.country_id 
          WHERE v.visa_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $visa_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: visa.php");
    exit;
}

$visa = $result->fetch_assoc();
$stmt->close();

// Get required documents for this visa
$documents_query = "SELECT * FROM service_documents 
                   WHERE visa_service_id IN (
                       SELECT visa_service_id FROM visa_services 
                       WHERE visa_id = ?
                   )";
$stmt = $conn->prepare($documents_query);
$stmt->bind_param('i', $visa_id);
$stmt->execute();
$documents_result = $stmt->get_result();
$documents = [];
while ($row = $documents_result->fetch_assoc()) {
    $documents[] = $row;
}
$stmt->close();

// Get services offered for this visa
$services_query = "SELECT vs.*, st.service_name, st.description as service_description,
                  CONCAT(u.first_name, ' ', u.last_name) as consultant_name
                  FROM visa_services vs
                  JOIN service_types st ON vs.service_type_id = st.service_type_id
                  JOIN users u ON vs.consultant_id = u.id
                  WHERE vs.visa_id = ? AND vs.is_active = 1";
$stmt = $conn->prepare($services_query);
$stmt->bind_param('i', $visa_id);
$stmt->execute();
$services_result = $stmt->get_result();
$services = [];
while ($row = $services_result->fetch_assoc()) {
    $services[] = $row;
}
$stmt->close();
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Visa Details</h1>
            <p>Detailed information about <?php echo htmlspecialchars($visa['visa_type']); ?> visa</p>
        </div>
        <div class="action-buttons">
            <a href="edit_visa.php?id=<?php echo $visa_id; ?>" class="btn primary-btn">
                <i class="fas fa-edit"></i> Edit Visa
            </a>
            <a href="visa.php" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <div class="visa-details-grid">
        <!-- Basic Information -->
        <div class="details-section">
            <h2>Basic Information</h2>
            <div class="details-content">
                <div class="detail-item">
                    <label>Country:</label>
                    <span><?php echo htmlspecialchars($visa['country_name']); ?> (<?php echo htmlspecialchars($visa['country_code']); ?>)</span>
                </div>
                <div class="detail-item">
                    <label>Visa Type:</label>
                    <span><?php echo htmlspecialchars($visa['visa_type']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Status:</label>
                    <span class="status-badge <?php echo $visa['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $visa['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
                <?php if (!$visa['is_active'] && !empty($visa['inactive_reason'])): ?>
                    <div class="detail-item">
                        <label>Inactive Reason:</label>
                        <span><?php echo htmlspecialchars($visa['inactive_reason']); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Inactive Since:</label>
                        <span><?php echo date('M d, Y', strtotime($visa['inactive_since'])); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Description -->
        <div class="details-section">
            <h2>Description</h2>
            <div class="details-content">
                <?php if (!empty($visa['description'])): ?>
                    <p><?php echo nl2br(htmlspecialchars($visa['description'])); ?></p>
                <?php else: ?>
                    <p class="text-muted">No description provided</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Requirements -->
        <div class="details-section">
            <h2>Requirements</h2>
            <div class="details-content">
                <?php if (!empty($visa['requirements'])): ?>
                    <p><?php echo nl2br(htmlspecialchars($visa['requirements'])); ?></p>
                <?php else: ?>
                    <p class="text-muted">No requirements specified</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Validity and Fee -->
        <div class="details-section">
            <h2>Validity and Fee</h2>
            <div class="details-content">
                <div class="detail-item">
                    <label>Validity Period:</label>
                    <span><?php echo $visa['validity_period'] ? $visa['validity_period'] . ' days' : 'Not specified'; ?></span>
                </div>
                <div class="detail-item">
                    <label>Base Fee:</label>
                    <span><?php echo $visa['fee'] ? '$' . number_format($visa['fee'], 2) : 'Not specified'; ?></span>
                </div>
            </div>
        </div>

        <!-- Required Documents -->
        <div class="details-section">
            <h2>Required Documents</h2>
            <div class="details-content">
                <?php if (!empty($documents)): ?>
                    <div class="documents-list">
                        <?php foreach ($documents as $doc): ?>
                            <div class="document-item">
                                <div class="document-name">
                                    <?php echo htmlspecialchars($doc['document_name']); ?>
                                    <?php if ($doc['is_required']): ?>
                                        <span class="required-badge">Required</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($doc['description'])): ?>
                                    <div class="document-description">
                                        <?php echo htmlspecialchars($doc['description']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No documents specified</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Available Services -->
        <div class="details-section">
            <h2>Available Services</h2>
            <div class="details-content">
                <?php if (!empty($services)): ?>
                    <div class="services-list">
                        <?php foreach ($services as $service): ?>
                            <div class="service-item">
                                <div class="service-header">
                                    <h3><?php echo htmlspecialchars($service['service_name']); ?></h3>
                                    <span class="price">$<?php echo number_format($service['base_price'], 2); ?></span>
                                </div>
                                <div class="service-details">
                                    <?php if (!empty($service['service_description'])): ?>
                                        <p><?php echo htmlspecialchars($service['service_description']); ?></p>
                                    <?php endif; ?>
                                    <div class="service-meta">
                                        <span class="consultant">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($service['consultant_name']); ?>
                                        </span>
                                        <?php if ($service['is_bookable']): ?>
                                            <span class="bookable">
                                                <i class="fas fa-calendar-check"></i> Bookable
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No services available</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.visa-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.details-section {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.details-section h2 {
    color: var(--primary-color);
    font-size: 1.2rem;
    margin: 0 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.details-content {
    color: var(--dark-color);
}

.detail-item {
    margin-bottom: 12px;
}

.detail-item label {
    font-weight: 600;
    color: var(--secondary-color);
    display: block;
    margin-bottom: 4px;
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

.documents-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.document-item {
    padding: 12px;
    background-color: var(--light-color);
    border-radius: 6px;
}

.document-name {
    font-weight: 600;
    margin-bottom: 4px;
}

.required-badge {
    background-color: var(--danger-color);
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
    margin-left: 8px;
}

.document-description {
    font-size: 0.9rem;
    color: var(--secondary-color);
}

.services-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.service-item {
    padding: 15px;
    background-color: var(--light-color);
    border-radius: 6px;
}

.service-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.service-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--primary-color);
}

.price {
    font-weight: 600;
    color: var(--success-color);
}

.service-meta {
    display: flex;
    gap: 15px;
    margin-top: 10px;
    font-size: 0.9rem;
    color: var(--secondary-color);
}

.service-meta i {
    margin-right: 4px;
}

.text-muted {
    color: var(--secondary-color);
    font-style: italic;
}

.secondary-btn {
    background-color: var(--secondary-color);
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

.secondary-btn:hover {
    background-color: #6c757d;
}

@media (max-width: 768px) {
    .visa-details-grid {
        grid-template-columns: 1fr;
    }
    
    .service-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .service-meta {
        flex-direction: column;
        gap: 8px;
    }
}
</style>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
