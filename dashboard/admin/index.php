<?php
// Set page title
$page_title = "Admin Dashboard";

// Include header
include('includes/header.php');

// Fetch basic stats
// Consultants count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'consultant' AND deleted_at IS NULL");
$stmt->execute();
$consultants_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Pending verification count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users u 
                       JOIN consultants c ON u.id = c.user_id 
                       LEFT JOIN consultant_profiles cp ON u.id = cp.consultant_id 
                       WHERE u.user_type = 'consultant' 
                       AND u.deleted_at IS NULL 
                       AND (cp.is_verified IS NULL OR cp.is_verified = 0)");
$stmt->execute();
$pending_verification = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Eligibility questions count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM decision_tree_questions");
$stmt->execute();
$questions_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// User assessments count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_assessments");
$stmt->execute();
$assessments_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();
?>

<div class="content">
    <div class="dashboard-header">
        <h1>Admin Dashboard</h1>
        <p>Welcome back, <?php echo htmlspecialchars($_SESSION["first_name"]); ?></p>
    </div>
    
    <!-- Stats Cards -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon booking-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3>Total Consultants</h3>
                <div class="stat-number"><?php echo number_format($consultants_count); ?></div>
                <div class="stat-detail">
                    <a href="consultants.php" class="stat-link">View All Consultants</a>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon client-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-info">
                <h3>Pending Verification</h3>
                <div class="stat-number"><?php echo number_format($pending_verification); ?></div>
                <div class="stat-detail">
                    <a href="verify-consultants.php" class="stat-link">View Pending</a>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon message-icon">
                <i class="fas fa-question-circle"></i>
            </div>
            <div class="stat-info">
                <h3>Eligibility Questions</h3>
                <div class="stat-number"><?php echo number_format($questions_count); ?></div>
                <div class="stat-detail">
                    <a href="manage-questions.php" class="stat-link">Manage Questions</a>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon notification-icon">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <div class="stat-info">
                <h3>User Assessments</h3>
                <div class="stat-number"><?php echo number_format($assessments_count); ?></div>
                <div class="stat-detail">
                    <a href="assessment-results.php" class="stat-link">View Results</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-grid">
        <!-- Quick Actions Section -->
        <div class="dashboard-section quick-actions">
            <div class="section-header">
                <h2>Quick Actions</h2>
            </div>
            <div class="actions-grid">
                <a href="eligibility-calculator.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="action-title">Manage Eligibility Calculator</div>
                    <div class="action-description">Configure and update eligibility criteria</div>
                </a>
                <a href="verify-consultants.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="action-title">Verify Consultants</div>
                    <div class="action-description">Review and verify consultant applications</div>
                </a>
                <a href="manage-questions.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <div class="action-title">Manage Questions</div>
                    <div class="action-description">Update eligibility assessment questions</div>
                </a>
                <a href="assessment-results.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="action-title">Assessment Results</div>
                    <div class="action-description">View and analyze user assessment data</div>
                </a>
            </div>
        </div>
        
        <!-- Recent Verifications Section -->
        <div class="dashboard-section recent-verifications">
            <div class="section-header">
                <h2>Recent Verifications</h2>
                <a href="verify-consultants.php" class="btn-link">View All</a>
            </div>
            <div class="table-responsive">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Consultant</th>
                            <th>Verified By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                                <?php
                                // Get recent verifications
                                $stmt = $conn->prepare("SELECT cp.consultant_id, cp.verified_at, 
                                                     CONCAT(c.first_name, ' ', c.last_name) AS consultant_name,
                                                     CONCAT(a.first_name, ' ', a.last_name) AS admin_name
                                                     FROM consultant_profiles cp
                                                     JOIN users c ON cp.consultant_id = c.id
                                                     JOIN users a ON cp.verified_by = a.id
                                                     WHERE cp.is_verified = 1
                                                     ORDER BY cp.verified_at DESC LIMIT 5");
                                $stmt->execute();
                                $verifications = $stmt->get_result();
                                
                                if ($verifications->num_rows > 0) {
                                    while ($row = $verifications->fetch_assoc()) {
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($row['consultant_name']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['admin_name']) . '</td>';
                                        echo '<td>' . date('M d, Y', strtotime($row['verified_at'])) . '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="3" class="text-center">No recent verifications</td></tr>';
                                }
                                $stmt->close();
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-grid">
        <!-- Recent Assessments Section -->
        <div class="dashboard-section recent-assessments">
            <div class="section-header">
                <h2>Recent Eligibility Assessments</h2>
                <a href="assessment-results.php" class="btn-link">View All</a>
            </div>
            <div class="table-responsive">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Date</th>
                            <th>Result</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                                <?php
                                // Get recent assessments
                                $stmt = $conn->prepare("SELECT ua.id, ua.start_time, ua.end_time, ua.is_complete, 
                                                     ua.result_eligible, CONCAT(u.first_name, ' ', u.last_name) AS user_name
                                                     FROM user_assessments ua
                                                     JOIN users u ON ua.user_id = u.id
                                                     ORDER BY ua.start_time DESC LIMIT 10");
                                $stmt->execute();
                                $assessments = $stmt->get_result();
                                
                                if ($assessments->num_rows > 0) {
                                    while ($row = $assessments->fetch_assoc()) {
                                        $status = $row['is_complete'] ? 'Completed' : 'In Progress';
                                        $status_class = $row['is_complete'] ? 'success' : 'warning';
                                        
                                        $result = 'N/A';
                                        $result_class = 'secondary';
                                        if ($row['is_complete']) {
                                            if ($row['result_eligible'] === 1) {
                                                $result = 'Eligible';
                                                $result_class = 'success';
                                            } elseif ($row['result_eligible'] === 0) {
                                                $result = 'Not Eligible';
                                                $result_class = 'danger';
                                            }
                                        }
                                        
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($row['user_name']) . '</td>';
                                        echo '<td>' . date('M d, Y H:i', strtotime($row['start_time'])) . '</td>';
                                        echo '<td><span class="badge bg-' . $result_class . '">' . $result . '</span></td>';
                                        echo '<td><span class="badge bg-' . $status_class . '">' . $status . '</span></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="4" class="text-center">No recent assessments</td></tr>';
                                }
                                $stmt->close();
                                ?>
                            </tbody>
                        </table>
                    </div>
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

.dashboard-header h1 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.8rem;
    font-weight: 700;
}

.dashboard-header p {
    margin: 5px 0 0;
    color: var(--secondary-color);
    font-size: 1rem;
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

.stat-info {
    flex: 1;
}

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
    margin-bottom: 5px;
}

.stat-detail {
    font-size: 0.8rem;
}

.stat-link {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
}

.stat-link:hover {
    text-decoration: underline;
}

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

/* Dashboard Sections */
.dashboard-section {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
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
    font-weight: 600;
}

.btn-link {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
}

.btn-link:hover {
    text-decoration: underline;
}

/* Quick Actions */
.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.action-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    text-decoration: none;
    transition: transform 0.2s;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.action-card:hover {
    transform: translateY(-5px);
}

.action-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: var(--light-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 24px;
    margin-bottom: 15px;
}

.action-title {
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 5px;
    font-size: 1rem;
}

.action-description {
    color: var(--secondary-color);
    font-size: 0.85rem;
}

/* Tables */
.table-responsive {
    overflow-x: auto;
}

.dashboard-table {
    width: 100%;
    border-collapse: collapse;
}

.dashboard-table th {
    text-align: left;
    padding: 12px;
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    font-size: 0.85rem;
}

.dashboard-table td {
    padding: 12px;
    border-top: 1px solid var(--border-color);
    font-size: 0.9rem;
    color: var(--dark-color);
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    color: white;
}

/* Responsive Design */
@media (max-width: 992px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-container {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
}

@media (max-width: 576px) {
    .stat-card {
        flex-direction: column;
        text-align: center;
    }
    
    .actions-grid {
        grid-template-columns: 1fr;
    }
    
    .dashboard-header h1 {
        font-size: 1.5rem;
    }
}
</style>

<?php
// Include footer
include('includes/footer.php');
?> 