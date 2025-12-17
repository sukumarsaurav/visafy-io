<?php
include_once 'includes/header.php';

// Get filter parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$eligibility = isset($_GET['eligibility']) ? $_GET['eligibility'] : 'all';
$completion = isset($_GET['completion']) ? $_GET['completion'] : 'all';

// Build the query
$query = "SELECT 
    ua.id,
    ua.start_time,
    ua.end_time,
    ua.is_complete,
    ua.result_eligible,
    ua.result_text,
    CONCAT(u.first_name, ' ', u.last_name) as user_name,
    u.email,
    COUNT(uaa.id) as questions_answered,
    (SELECT COUNT(*) FROM user_assessment_answers WHERE assessment_id = ua.id) as total_answers
FROM 
    user_assessments ua
JOIN 
    users u ON ua.user_id = u.id
LEFT JOIN 
    user_assessment_answers uaa ON ua.id = uaa.assessment_id
WHERE 1=1";

$params = [];
$types = "";

// Add date filters
if (!empty($date_from)) {
    $query .= " AND DATE(ua.start_time) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND DATE(ua.start_time) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Add eligibility filter
if ($eligibility !== 'all') {
    $query .= " AND ua.result_eligible = ?";
    $params[] = ($eligibility === 'eligible' ? 1 : 0);
    $types .= "i";
}

// Add completion filter
if ($completion !== 'all') {
    $query .= " AND ua.is_complete = ?";
    $params[] = ($completion === 'completed' ? 1 : 0);
    $types .= "i";
}

$query .= " GROUP BY ua.id ORDER BY ua.start_time DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get basic stats
$stats_query = "SELECT 
    COALESCE(COUNT(*), 0) as total_assessments,
    COALESCE(SUM(is_complete), 0) as completed_assessments,
    COALESCE(SUM(CASE WHEN result_eligible = 1 THEN 1 ELSE 0 END), 0) as eligible_results,
    COALESCE(SUM(CASE WHEN result_eligible = 0 THEN 1 ELSE 0 END), 0) as ineligible_results
FROM user_assessments";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Ensure all stats values are integers
$stats['total_assessments'] = (int)$stats['total_assessments'];
$stats['completed_assessments'] = (int)$stats['completed_assessments'];
$stats['eligible_results'] = (int)$stats['eligible_results'];
$stats['ineligible_results'] = (int)$stats['ineligible_results'];
?>

<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner">
        <div class="spinner"></div>
    </div>
</div>

<div class="content" id="pageContent" style="display: none;">
    <div class="header-container">
        <div>
            <h1><i class="fas fa-clipboard-list"></i> Assessment Results</h1>
            <p>View and analyze user assessment results from the eligibility checker</p>
        </div>
        <div>
            <a href="eligibility-calculator.php" class="btn cancel-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-container">
        <div class="stats-card">
            <div class="stats-card-body">
                <div class="stats-card-icon bg-primary">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stats-card-content">
                    <h3><?php echo number_format($stats['total_assessments']); ?></h3>
                    <p>Total Assessments</p>
                </div>
            </div>
        </div>
        <div class="stats-card">
            <div class="stats-card-body">
                <div class="stats-card-icon bg-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stats-card-content">
                    <h3><?php echo number_format($stats['completed_assessments']); ?></h3>
                    <p>Completed Assessments</p>
                </div>
            </div>
        </div>
        <div class="stats-card">
            <div class="stats-card-body">
                <div class="stats-card-icon bg-info">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stats-card-content">
                    <h3><?php echo number_format($stats['eligible_results']); ?></h3>
                    <p>Eligible Results</p>
                </div>
            </div>
        </div>
        <div class="stats-card">
            <div class="stats-card-body">
                <div class="stats-card-icon bg-warning">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stats-card-content">
                    <h3><?php echo number_format($stats['ineligible_results']); ?></h3>
                    <p>Ineligible Results</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="dashboard-section">
        <div class="section-header">
            <h2>Filter Results</h2>
            <div class="filter-controls">
                <form action="" method="GET" class="d-flex gap-2">
                    <div class="form-group">
                        <input type="date" id="date_from" name="date_from" class="form-select" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="form-group">
                        <input type="date" id="date_to" name="date_to" class="form-select" value="<?php echo $date_to; ?>">
                    </div>
                    <select name="eligibility" class="form-select">
                        <option value="all" <?php echo $eligibility === 'all' ? 'selected' : ''; ?>>All Results</option>
                        <option value="eligible" <?php echo $eligibility === 'eligible' ? 'selected' : ''; ?>>Eligible</option>
                        <option value="ineligible" <?php echo $eligibility === 'ineligible' ? 'selected' : ''; ?>>Ineligible</option>
                    </select>
                    <select name="completion" class="form-select">
                        <option value="all" <?php echo $completion === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="completed" <?php echo $completion === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="incomplete" <?php echo $completion === 'incomplete' ? 'selected' : ''; ?>>Incomplete</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Results Table -->
    <div class="dashboard-section">
        <div class="section-header">
            <h2>Assessment Results</h2>
        </div>
        <div class="table-responsive">
            <table class="dashboard-table" id="assessmentsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Start Time</th>
                        <th>Completion</th>
                        <th>Questions</th>
                        <th>Result</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                    <tr>
                        <td colspan="7" class="text-center">No assessment results found.</td>
                    </tr>
                    <?php else: ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td>
                                <div class="user-info">
                                    <span class="user-name"><?php echo htmlspecialchars($row['user_name']); ?></span>
                                    <span class="user-email"><?php echo htmlspecialchars($row['email']); ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="time-info">
                                    <span class="time-main"><?php echo date('M d, Y H:i', strtotime($row['start_time'])); ?></span>
                                    <?php if ($row['end_time']): ?>
                                        <span class="time-secondary">
                                            Completed: <?php echo date('M d, Y H:i', strtotime($row['end_time'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($row['is_complete']): ?>
                                    <span class="badge bg-success">Completed</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">In Progress</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="questions-count">
                                    <?php echo $row['questions_answered']; ?> answered
                                </span>
                            </td>
                            <td>
                                <?php if ($row['is_complete']): ?>
                                    <?php if ($row['result_eligible']): ?>
                                        <span class="badge bg-success">Eligible</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Ineligible</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-warning">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-primary view-details" data-id="<?php echo $row['id']; ?>" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Assessment Details Modal -->
<div class="modal" id="assessmentModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Assessment Details</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="assessment-details">
                    <div class="loader">Loading...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Inherit existing styles from manage_questions.php and add specific styles */
.content {
    padding: 20px;
    margin: 0 auto;
}

.filter-form {
    background-color: var(--light-color);
    border-radius: 8px;
    padding: 20px;
}

.filter-form .form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    align-items: flex-end;
}

.filter-form .form-group {
    margin: 0;
}

.filter-form label {
    display: block;
    margin-bottom: 8px;
    color: var(--dark-color);
    font-weight: 500;
    font-size: 14px;
}

.filter-form .form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
    color: var(--dark-color);
    background-color: white;
    transition: all 0.2s;
}

.filter-form .form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(4, 33, 103, 0.1);
    outline: none;
}

.filter-form select.form-control {
    padding-right: 30px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8' viewBox='0 0 8 8'%3E%3Cpath fill='%235a5c69' d='M0 2l4 4 4-4z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 8px;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

.filter-form .btn {
    height: 42px;
    padding: 0 20px;
    font-weight: 600;
}

/* Assessment Results Section */
.assessment-results {
    margin-top: 30px;
}

.table-wrapper {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.table-header {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-header h2 {
    margin: 0;
    color: var(--primary-color);
    font-size: 18px;
    font-weight: 600;
}

.table-container {
    padding: 0;
    overflow-x: auto;
}

.table {
    margin: 0;
}

.table th {
    white-space: nowrap;
    padding: 15px 20px;
    font-size: 14px;
    font-weight: 600;
    color: var(--primary-color);
    background-color: var(--light-color);
    border-bottom: 2px solid var(--border-color);
}

.table td {
    padding: 15px 20px;
    vertical-align: middle;
    font-size: 14px;
    color: var(--dark-color);
    border-bottom: 1px solid var(--border-color);
}

.table tbody tr:hover {
    background-color: rgba(4, 33, 103, 0.02);
}

/* Status Indicators */
.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
}

.status-indicator::before {
    content: '';
    display: block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.status-indicator.completed::before {
    background-color: var(--success-color);
}

.status-indicator.pending::before {
    background-color: var(--warning-color);
}

.status-indicator.cancelled::before {
    background-color: var(--danger-color);
}

/* User Info in Table */
.user-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.user-name {
    font-weight: 500;
    color: var(--dark-color);
}

.user-email {
    font-size: 13px;
    color: var(--secondary-color);
}

/* Time Info in Table */
.time-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.time-main {
    font-weight: 500;
}

.time-secondary {
    font-size: 13px;
    color: var(--secondary-color);
}

/* Questions Count */
.questions-count {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    background-color: rgba(54, 185, 204, 0.1);
    border-radius: 4px;
    color: var(--info-color);
    font-size: 13px;
    font-weight: 500;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-action {
    width: 32px;
    height: 32px;
    padding: 0;
    border-radius: 6px;
    transition: all 0.2s;
}

.btn-action:hover {
    transform: translateY(-1px);
}

.btn-action i {
    font-size: 14px;
}

/* DataTables Customization */
.dataTables_wrapper .dataTables_length select {
    padding: 6px 30px 6px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
    background-position: right 8px center;
}

.dataTables_wrapper .dataTables_filter input {
    padding: 6px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: 6px 12px;
    margin: 0 2px;
    border-radius: 4px;
    border: none !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: var(--primary-color) !important;
    color: white !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: var(--light-color) !important;
    color: var(--primary-color) !important;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .filter-form .form-row {
        grid-template-columns: 1fr;
    }
    
    .table-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    
    .table td {
        padding: 12px 15px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}

/* Stats Container */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stats-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: transform 0.2s;
}

.stats-card:hover {
    transform: translateY(-2px);
}

.stats-card-body {
    display: flex;
    align-items: center;
    gap: 20px;
    width: 100%;
}

.stats-card-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    flex-shrink: 0;
}

.stats-card-content {
    flex-grow: 1;
}

.stats-card-content h3 {
    margin: 0 0 5px 0;
    font-size: 24px;
    font-weight: 700;
    color: var(--dark-color);
}

.stats-card-content p {
    margin: 0;
    color: var(--secondary-color);
    font-size: 14px;
    font-weight: 500;
}

.bg-primary { background-color: var(--primary-color); }
.bg-success { background-color: var(--success-color); }
.bg-info { background-color: var(--info-color); }
.bg-warning { background-color: var(--warning-color); }

/* Responsive adjustments */
@media (max-width: 992px) {
    .stats-container {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-container {
        grid-template-columns: 1fr;
    }
    
    .stats-card {
        padding: 15px;
    }
    
    .stats-card-icon {
        width: 50px;
        height: 50px;
        font-size: 20px;
    }
    
    .stats-card-content h3 {
        font-size: 20px;
    }
}

/* Update existing styles */
.header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.header-container h1 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.8rem;
    font-weight: 700;
}

.header-container p {
    margin: 5px 0 0;
    color: var(--secondary-color);
}

/* Card styles */
.card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    margin-bottom: 20px;
}

.card-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--light-color);
}

.card-header h5 {
    margin: 0;
    color: var(--primary-color);
    font-size: 16px;
    font-weight: 600;
}

.card-body {
    padding: 20px;
}

/* Status badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.completed {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge.pending {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.status-badge.cancelled {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

/* Button styles */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-view {
    background-color: var(--primary-color);
    color: white;
}

.btn-view:hover {
    background-color: #031c56;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
}

.primary-btn:hover {
    background-color: #031c56;
}

.cancel-btn {
    background-color: white;
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
}

.cancel-btn:hover {
    background-color: var(--light-color);
}

/* Table styles */
.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    text-align: left;
    padding: 12px;
    border-bottom: 2px solid var(--border-color);
}

.table td {
    padding: 12px;
    border-bottom: 1px solid var(--border-color);
    color: var(--dark-color);
}

.table tr:hover {
    background-color: rgba(4, 33, 103, 0.03);
}

.text-muted {
    color: var(--secondary-color);
}

/* Filter Section */
.filter-controls {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 20px;
}

.form-select {
    width: 150px;
    padding: 6px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 0.85rem;
    color: var(--dark-color);
    background-color: white;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8' viewBox='0 0 8 8'%3E%3Cpath fill='%235a5c69' d='M0 2l4 4 4-4z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 8px;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

.form-select:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 0.2rem rgba(4, 33, 103, 0.25);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.85rem;
}

/* Table Section */
.dashboard-section {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--light-color);
}

.section-header h2 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.2rem;
    font-weight: 600;
}

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
    border-bottom: 2px solid var(--border-color);
}

.dashboard-table td {
    padding: 12px;
    border-top: 1px solid var(--border-color);
    font-size: 0.9rem;
    color: var(--dark-color);
    vertical-align: middle;
}

.dashboard-table tr:hover {
    background-color: rgba(4, 33, 103, 0.03);
}

/* Badge Styles */
.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.bg-success {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.bg-danger {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.bg-warning {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.bg-info {
    background-color: rgba(54, 185, 204, 0.1);
    color: var(--info-color);
}

.bg-secondary {
    background-color: rgba(133, 135, 150, 0.1);
    color: var(--secondary-color);
}

.bg-primary {
    background-color: rgba(78, 115, 223, 0.1);
    color: var(--primary-color);
}

/* Button Group */
.btn-group {
    display: flex;
    gap: 5px;
}

.btn-group .btn {
    padding: 4px 8px;
    font-size: 0.8rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .filter-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-select {
        width: 100%;
    }
    
    .btn-sm {
        width: 100%;
    }
    
    .section-header {
        flex-direction: column;
        gap: 10px;
    }
    
    .dashboard-table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
}

/* Update existing table styles */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.table td small {
    display: block;
    color: var(--secondary-color);
    font-size: 0.85rem;
    margin-top: 2px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show loading overlay
    const loadingOverlay = document.getElementById('loadingOverlay');
    const pageContent = document.getElementById('pageContent');
    
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
        
        // Initialize DataTable
        if ($.fn.DataTable) {
            $('#assessmentsTable').DataTable({
                "order": [[2, "desc"]], // Sort by start time by default
                "pageLength": 25,
                "language": {
                    "emptyTable": "No assessment results found"
                }
            });
        }
    }
    
    // Check if all assets are loaded
    window.onload = function() {
        if (areImagesLoaded()) {
            setTimeout(showContent, 500);
        } else {
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
                    img.addEventListener('error', imageLoaded);
                }
            }
        }
    };
    
    // Fallback: Show content if loading takes too long
    setTimeout(showContent, 3000);
    
    // Handle view details button clicks
    const viewButtons = document.querySelectorAll('.view-details');
    const assessmentModal = document.getElementById('assessmentModal');
    
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const assessmentId = this.getAttribute('data-id');
            const detailsContainer = document.getElementById('assessment-details');
            
            // Show modal with loading state
            assessmentModal.style.display = 'block';
            detailsContainer.innerHTML = '<div class="loader">Loading assessment details...</div>';
            
            // Fetch assessment details
            fetch(`ajax/get_assessment_details.php?id=${assessmentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = `
                            <div class="assessment-detail-section">
                                <h4>User Information</h4>
                                <p><strong>Name:</strong> ${data.user_name}</p>
                                <p><strong>Email:</strong> ${data.email}</p>
                            </div>
                            
                            <div class="assessment-detail-section">
                                <h4>Assessment Information</h4>
                                <p><strong>Started:</strong> ${data.start_time}</p>
                                <p><strong>Completed:</strong> ${data.end_time || 'In Progress'}</p>
                                <p><strong>Status:</strong> ${data.is_complete ? 'Completed' : 'In Progress'}</p>
                            </div>
                            
                            <div class="assessment-detail-section">
                                <h4>Answers</h4>
                                <ul class="answer-list">`;
                        
                        data.answers.forEach(answer => {
                            html += `
                                <li>
                                    <div><strong>Q:</strong> ${answer.question_text}</div>
                                    <div><strong>A:</strong> ${answer.option_text}</div>
                                    <div class="answer-time">${answer.answer_time}</div>
                                </li>`;
                        });
                        
                        html += `</ul>
                            </div>`;
                        
                        if (data.is_complete) {
                            html += `
                                <div class="result-section ${data.result_eligible ? 'eligible' : 'ineligible'}">
                                    <h4>Final Result</h4>
                                    <p><strong>Eligibility:</strong> ${data.result_eligible ? 'Eligible' : 'Ineligible'}</p>
                                    <p><strong>Result Message:</strong></p>
                                    <p>${data.result_text}</p>
                                </div>`;
                        }
                        
                        detailsContainer.innerHTML = html;
                    } else {
                        detailsContainer.innerHTML = `
                            <div class="alert alert-danger">
                                Error loading assessment details: ${data.message}
                            </div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    detailsContainer.innerHTML = `
                        <div class="alert alert-danger">
                            An error occurred while loading the assessment details.
                        </div>`;
                });
        });
    });
    
    // Close modal when X is clicked
    document.querySelectorAll('[data-dismiss="modal"]').forEach(button => {
        button.addEventListener('click', function() {
            assessmentModal.style.display = 'none';
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === assessmentModal) {
            assessmentModal.style.display = 'none';
        }
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>
