<?php
// Set page title
$page_title = "Manage Consultants";

// Include header
include('includes/header.php');

// Process any actions
$action_message = '';
$action_error = '';

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $consultant_id = intval($_GET['id']);
    
    if ($action === 'suspend' && $consultant_id > 0) {
        // Suspend consultant
        $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND user_type = 'consultant'");
        $stmt->bind_param("i", $consultant_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $action_message = "Consultant has been suspended successfully.";
        } else {
            $action_error = "Failed to suspend consultant.";
        }
        $stmt->close();
    } elseif ($action === 'activate' && $consultant_id > 0) {
        // Activate consultant
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ? AND user_type = 'consultant'");
        $stmt->bind_param("i", $consultant_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $action_message = "Consultant has been activated successfully.";
        } else {
            $action_error = "Failed to activate consultant.";
        }
        $stmt->close();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$verification_filter = isset($_GET['verification']) ? $_GET['verification'] : 'all';
$view = (isset($_GET['view']) && $_GET['view'] === 'tile') ? 'tile' : 'list';

// Build the query based on filters
$query = "SELECT 
    u.id AS consultant_id,
    CONCAT(u.first_name, ' ', u.last_name) AS consultant_name,
    u.email,
    u.phone,
    u.status,
    u.created_at,
    u.profile_picture,
    c.company_name,
    COALESCE(cp.is_verified, 0) AS is_verified,
    cp.verified_at,
    mp.name AS membership_plan,
    mp.billing_cycle,
    s.end_date AS renewal_date,
    CONCAT(a.first_name, ' ', a.last_name) AS verified_by_name
FROM 
    users u
JOIN 
    consultants c ON u.id = c.user_id
LEFT JOIN 
    consultant_profiles cp ON u.id = cp.consultant_id
LEFT JOIN 
    users a ON cp.verified_by = a.id
LEFT JOIN 
    membership_plans mp ON c.membership_plan_id = mp.id
LEFT JOIN 
    subscriptions s ON u.id = s.user_id AND s.status = 'active'
WHERE 
    u.user_type = 'consultant' 
    AND u.deleted_at IS NULL";

// Add status filter
if ($status_filter !== 'all') {
    $query .= " AND u.status = '$status_filter'";
}

// Add verification filter
if ($verification_filter === 'verified') {
    $query .= " AND cp.is_verified = 1";
} elseif ($verification_filter === 'unverified') {
    $query .= " AND (cp.is_verified IS NULL OR cp.is_verified = 0)";
}

$query .= " ORDER BY u.created_at DESC";

$result = $conn->query($query);
$consultants = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $consultants[] = $row;
    }
}

// Get stats
$total_consultants = count($consultants);
$active_consultants = 0;
$suspended_consultants = 0;
$verified_consultants = 0;

foreach ($consultants as $consultant) {
    if ($consultant['status'] === 'active') $active_consultants++;
    if ($consultant['status'] === 'suspended') $suspended_consultants++;
    if ($consultant['is_verified'] === 1) $verified_consultants++;
}
?>

<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner">
        <div class="spinner"></div>
    </div>
</div>

<div class="content" id="pageContent" style="display: none;">
    <?php if (!empty($action_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $action_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($action_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $action_error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="header-content">
            <h1>Consultants Management</h1>
            <p class="text-muted">View and manage all consultants on the platform</p>
        </div>
    </div>

    <!-- Stats Container -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon booking-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3>Total Consultants</h3>
                <div class="stat-number"><?php echo $total_consultants; ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon client-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-info">
                <h3>Active Consultants</h3>
                <div class="stat-number"><?php echo $active_consultants; ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon message-icon">
                <i class="fas fa-user-slash"></i>
            </div>
            <div class="stat-info">
                <h3>Suspended Consultants</h3>
                <div class="stat-number"><?php echo $suspended_consultants; ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon notification-icon">
                <i class="fas fa-certificate"></i>
            </div>
            <div class="stat-info">
                <h3>Verified Consultants</h3>
                <div class="stat-number"><?php echo $verified_consultants; ?></div>
            </div>
        </div>
    </div>

    <!-- Main Content Section -->
    <div class="dashboard-section">
        <div class="section-header">
            <h2>Consultants List</h2>
            <div class="section-actions">
                <div class="filter-controls">
                    <form action="" method="get" class="d-flex gap-2">
                        <select name="status" class="form-select form-select-sm">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                        <select name="verification" class="form-select form-select-sm">
                            <option value="all" <?php echo $verification_filter === 'all' ? 'selected' : ''; ?>>All Verification</option>
                            <option value="verified" <?php echo $verification_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                            <option value="unverified" <?php echo $verification_filter === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                        </select>
                        <input type="hidden" name="view" value="<?php echo $view; ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    </form>
                </div>
                <?php
                    $filter_params = [
                        'status' => $status_filter,
                        'verification' => $verification_filter
                    ];
                    $list_view_url = 'consultants.php?' . http_build_query(array_merge($filter_params, ['view' => 'list']));
                    $tile_view_url = 'consultants.php?' . http_build_query(array_merge($filter_params, ['view' => 'tile']));
                ?>
                <div class="view-toggle btn-group">
                    <a href="<?php echo $list_view_url; ?>" class="btn btn-outline-primary btn-sm <?php echo $view === 'list' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> List
                    </a>
                    <a href="<?php echo $tile_view_url; ?>" class="btn btn-outline-primary btn-sm <?php echo $view === 'tile' ? 'active' : ''; ?>">
                        <i class="fas fa-th-large"></i> Tile
                    </a>
                </div>
            </div>
        </div>
        <?php if (empty($consultants)): ?>
            <div class="empty-state text-center py-4">
                No consultants found.
            </div>
        <?php elseif ($view === 'list'): ?>
            <div class="table-responsive">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Profile</th>
                            <th>Name</th>
                            <th>Company</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Verification</th>
                            <th>Membership</th>
                            <th>Renewal Date</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consultants as $consultant): ?>
                            <tr>
                                <td>
                                    <?php
                                        $profile_img = '../assets/images/default-consultant.svg';
                                        if (!empty($consultant['profile_picture'])) {
                                            $relative_path = strpos($consultant['profile_picture'], 'uploads/') === 0
                                                ? '../../' . $consultant['profile_picture']
                                                : '../../uploads/users/' . $consultant['consultant_id'] . '/profile/' . $consultant['profile_picture'];
                                            if (file_exists($relative_path)) {
                                                $profile_img = $relative_path;
                                            }
                                        }
                                    ?>
                                    <img src="<?php echo $profile_img; ?>" alt="Profile" class="avatar-sm rounded-circle">
                                </td>
                                <td><?php echo htmlspecialchars($consultant['consultant_name']); ?></td>
                                <td><?php echo htmlspecialchars($consultant['company_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($consultant['email']); ?><br>
                                    <small><?php echo htmlspecialchars($consultant['phone']); ?></small>
                                </td>
                                <td>
                                    <?php if ($consultant['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($consultant['is_verified'] == 1): ?>
                                        <span class="badge bg-info">Verified</span>
                                        <br>
                                        <small>by <?php echo htmlspecialchars($consultant['verified_by_name'] ?? 'System'); ?></small>
                                        <br>
                                        <small><?php echo date('M d, Y', strtotime($consultant['verified_at'])); ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Unverified</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($consultant['membership_plan'])): ?>
                                        <span class="badge bg-primary">
                                            <?php echo htmlspecialchars($consultant['membership_plan']); ?>
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo ucfirst($consultant['billing_cycle']); ?> Plan
                                        </small>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No Plan</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($consultant['renewal_date'])): ?>
                                        <?php 
                                            $renewal_date = strtotime($consultant['renewal_date']);
                                            $today = time();
                                            $days_until_renewal = ceil(($renewal_date - $today) / (60 * 60 * 24));
                                            $badge_class = $days_until_renewal <= 7 ? 'bg-warning' : 'bg-info';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo date('M d, Y', $renewal_date); ?>
                                        </span>
                                        <?php if ($days_until_renewal > 0): ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo $days_until_renewal; ?> days left
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not Available</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($consultant['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view-consultant.php?id=<?php echo $consultant['consultant_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($consultant['status'] === 'active'): ?>
                                            <a href="consultants.php?action=suspend&id=<?php echo $consultant['consultant_id']; ?>" class="btn btn-sm btn-warning" onclick="return confirm('Are you sure you want to suspend this consultant?')">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="consultants.php?action=activate&id=<?php echo $consultant['consultant_id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to activate this consultant?')">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($consultant['is_verified'] == 0): ?>
                                            <a href="verify-consultants.php?id=<?php echo $consultant['consultant_id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-user-check"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="tile-grid">
                <?php foreach ($consultants as $consultant): ?>
                    <div class="consultant-card">
                        <div class="card-header">
                            <div>
                                <div class="consultant-name"><?php echo htmlspecialchars($consultant['consultant_name']); ?></div>
                                <div class="consultant-company"><?php echo htmlspecialchars($consultant['company_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="badge-group">
                                <span class="badge <?php echo $consultant['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo ucfirst($consultant['status']); ?>
                                </span>
                                <span class="badge <?php echo $consultant['is_verified'] == 1 ? 'bg-info' : 'bg-warning'; ?>">
                                    <?php echo $consultant['is_verified'] == 1 ? 'Verified' : 'Unverified'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="card-row">
                                <i class="fas fa-envelope text-primary me-2"></i>
                                <span><?php echo htmlspecialchars($consultant['email']); ?></span>
                            </div>
                            <div class="card-row">
                                <i class="fas fa-phone text-primary me-2"></i>
                                <span><?php echo htmlspecialchars($consultant['phone']); ?></span>
                            </div>
                            <div class="card-row">
                                <i class="fas fa-id-badge text-primary me-2"></i>
                                <?php if (!empty($consultant['membership_plan'])): ?>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($consultant['membership_plan']); ?></span>
                                    <small class="text-muted ms-2"><?php echo ucfirst($consultant['billing_cycle']); ?> plan</small>
                                <?php else: ?>
                                    <span class="badge bg-secondary">No Plan</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-row">
                                <i class="fas fa-redo text-primary me-2"></i>
                                <?php if (!empty($consultant['renewal_date'])): ?>
                                    <?php 
                                        $renewal_date = strtotime($consultant['renewal_date']);
                                        $today = time();
                                        $days_until_renewal = ceil(($renewal_date - $today) / (60 * 60 * 24));
                                        $badge_class = $days_until_renewal <= 7 ? 'bg-warning' : 'bg-info';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo date('M d, Y', $renewal_date); ?></span>
                                    <?php if ($days_until_renewal > 0): ?>
                                        <small class="text-muted ms-2"><?php echo $days_until_renewal; ?> days left</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-secondary">No renewal</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-row">
                                <i class="fas fa-calendar-alt text-primary me-2"></i>
                                <span>Joined <?php echo date('M d, Y', strtotime($consultant['created_at'])); ?></span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="btn-group">
                                <a href="view-consultant.php?id=<?php echo $consultant['consultant_id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <?php if ($consultant['status'] === 'active'): ?>
                                    <a href="consultants.php?action=suspend&id=<?php echo $consultant['consultant_id']; ?>" class="btn btn-sm btn-warning" onclick="return confirm('Are you sure you want to suspend this consultant?')">
                                        <i class="fas fa-ban"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="consultants.php?action=activate&id=<?php echo $consultant['consultant_id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to activate this consultant?')">
                                        <i class="fas fa-check"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($consultant['is_verified'] == 0): ?>
                                    <a href="verify-consultants.php?id=<?php echo $consultant['consultant_id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-user-check"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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

/* Content Container */
.content {
    padding: 20px;
    margin: 0 auto;
}

/* Dashboard Header */
.dashboard-header {
    margin-bottom: 20px;
}

.header-content {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.dashboard-header h1 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.8rem;
    font-weight: 700;
}

/* Stats Container */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.booking-icon { background-color: var(--primary-color); }
.client-icon { background-color: var(--info-color); }
.message-icon { background-color: var(--message-color); }
.notification-icon { background-color: var(--notification-color); }

.stat-info h3 {
    margin: 0 0 5px 0;
    color: var(--secondary-color);
    font-size: 0.85rem;
    font-weight: 600;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--dark-color);
}

/* Dashboard Section */
.dashboard-section {
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(4, 33, 103, 0.05);
    padding: 20px;
    margin-bottom: 30px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color);
}

.section-header h2 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.2rem;
    font-weight: 600;
}

/* Filter Controls */
.filter-controls {
    display: flex;
    gap: 10px;
}

.section-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.view-toggle .btn {
    min-width: 90px;
}

.form-select {
    width: 150px;
    padding: 6px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 0.85rem;
    color: var(--dark-color);
}

/* Table Styles */
.dashboard-table {
    width: 100%;
    border-collapse: collapse;
}

.dashboard-table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    font-size: 0.85rem;
    padding: 12px;
    text-align: left;
}

.dashboard-table td {
    padding: 12px;
    border-top: 1px solid var(--border-color);
    font-size: 0.9rem;
    color: var(--dark-color);
}

.avatar-sm {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 50%;
    border: 1px solid var(--border-color);
    background-color: var(--light-color);
}

.empty-state {
    background-color: #fff;
    border: 1px dashed var(--border-color);
    border-radius: 8px;
    color: var(--secondary-color);
    font-weight: 600;
}

.tile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
}

.consultant-card {
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(4, 33, 103, 0.05);
    border: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    height: 100%;
}

.consultant-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 16px;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--light-color);
}

.consultant-name {
    font-weight: 700;
    color: var(--primary-color);
}

.consultant-company {
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.badge-group {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.consultant-card .card-body {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.card-row {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--dark-color);
    font-size: 0.9rem;
}

.consultant-card .card-footer {
    padding: 12px 16px 16px;
    margin-top: auto;
    border-top: 1px solid var(--border-color);
}

@media (max-width: 768px) {
    .section-actions {
        width: 100%;
        justify-content: space-between;
    }
}

/* Badge Styles */
.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

/* Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 500;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: background-color 0.2s;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 0.8rem;
}

/* Responsive Design */
@media (max-width: 992px) {
    .stats-container {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-container {
        grid-template-columns: 1fr;
    }
    
    .filter-controls {
        flex-direction: column;
    }
    
    .section-header {
        flex-direction: column;
        gap: 15px;
    }
}

@media (max-width: 576px) {
    .stat-card {
        flex-direction: column;
        text-align: center;
    }
    
    .btn-group {
        flex-direction: column;
        gap: 5px;
    }
}

/* Loading Animation Styles */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.9);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.loading-spinner {
    text-align: center;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 4px solid var(--light-color);
    border-top: 4px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 15px;
}

.loading-spinner p {
    color: var(--primary-color);
    font-size: 16px;
    font-weight: 500;
    margin: 0;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Fade In Animation */
.fade-in {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show loading overlay
    const loadingOverlay = document.getElementById('loadingOverlay');
    const pageContent = document.getElementById('pageContent');
    const viewType = '<?php echo $view; ?>';
    
    // Function to check if all images are loaded
    function areImagesLoaded() {
        const images = document.getElementsByTagName('img');
        for (let img of images) {
            if (!img.complete) {
                return false;
            }
        }
        return true;
    }
    
    // Function to show the page content
    function showContent() {
        loadingOverlay.style.display = 'none';
        pageContent.style.display = 'block';
        pageContent.classList.add('fade-in');
        
        // Initialize DataTable after content is shown
        if (viewType === 'list' && $.fn.DataTable) {
            $('.dashboard-table').DataTable({
                "order": [[8, "desc"]], // Sort by joined date by default (column shifted due to profile)
                "pageLength": 25,
                "language": {
                    "emptyTable": "No consultants found"
                },
                "dom": '<"table-responsive"t>p',
                "drawCallback": function() {
                    // Add any custom styling after table draw
                }
            });
        }
    }
    
    // Check if all assets are loaded
    window.onload = function() {
        if (areImagesLoaded()) {
            // Add a small delay for smoother transition
            setTimeout(showContent, 500);
        } else {
            // If images are not loaded, wait for them
            const images = document.getElementsByTagName('img');
            let loadedImages = 0;
            
            function imageLoaded() {
                loadedImages++;
                if (loadedImages === images.length) {
                    setTimeout(showContent, 500);
                }
            }
            
            for (let img of images) {
                if (img.complete) {
                    imageLoaded();
                } else {
                    img.addEventListener('load', imageLoaded);
                    img.addEventListener('error', imageLoaded); // Handle error cases
                }
            }
        }
    };
    
    // Fallback: Show content if loading takes too long
    setTimeout(showContent, 3000);
});
</script>

<?php
// Include footer
include('includes/footer.php');
?> 