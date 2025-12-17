<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Organization Management";
$page_specific_css = "assets/css/team.css";
require_once 'includes/header.php';

// Get the consultant ID from the session (assuming the logged-in user is a consultant)
$consultant_id = $_SESSION["id"];
$organization_id = isset($user['organization_id']) ? $user['organization_id'] : null;

// Get organization details
$org_query = "SELECT o.*, c.company_name, mp.name as plan_name, mp.max_team_members, mp.price
              FROM organizations o 
              LEFT JOIN consultants c ON c.user_id = ?
              LEFT JOIN membership_plans mp ON c.membership_plan_id = mp.id
              WHERE o.id = ?";
$org_stmt = $conn->prepare($org_query);
$org_stmt->bind_param("ii", $consultant_id, $organization_id);
$org_stmt->execute();
$org_result = $org_stmt->get_result();
$organization = $org_result->fetch_assoc();
$org_stmt->close();

// Get all team members for this consultant's organization
$query = "SELECT tm.id, tm.consultant_id, tm.member_user_id, tm.member_type, tm.invitation_status, 
          tm.invited_at, tm.accepted_at, tm.created_at,
          u.id as user_id, u.first_name, u.last_name, u.email, u.phone, u.status, u.profile_picture, 
          u.email_verified, u.user_type
          FROM team_members tm
          JOIN users u ON tm.member_user_id = u.id
          WHERE tm.consultant_id = ? AND u.deleted_at IS NULL
          ORDER BY u.first_name, u.last_name";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $consultant_id);
$stmt->execute();
$result = $stmt->get_result();
$team_members = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $team_members[] = $row;
    }
}
$stmt->close();

// Generate random invite token
function generateInviteToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Handle invite form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_member'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $member_type = $_POST['member_type'] ?? 'custom'; // Default to custom if not provided
    
    // Validate inputs
    $errors = [];
    if (empty($first_name)) {
        $errors[] = "First name is required";
    }
    if (empty($last_name)) {
        $errors[] = "Last name is required";
    }
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email already exists
    $check_query = "SELECT id FROM users WHERE email = ? AND deleted_at IS NULL";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('s', $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $errors[] = "Email already exists";
    }
    $check_stmt->close();
    
    if (empty($errors)) {
        // Create invite token
        $token = generateInviteToken();
        $expires = date('Y-m-d H:i:s', strtotime('+48 hours'));
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create user with pending status and associate with organization
            $user_insert = "INSERT INTO users (first_name, last_name, email, phone, password, user_type, email_verified, 
                          email_verification_token, email_verification_expires, status, organization_id) 
                          VALUES (?, ?, ?, ?, '', 'member', 0, ?, ?, 'suspended', ?)";
            $stmt = $conn->prepare($user_insert);
            $stmt->bind_param('ssssssi', $first_name, $last_name, $email, $phone, $token, $expires, $organization_id);
            $stmt->execute();
            
            $user_id = $conn->insert_id;
            $stmt->close();
            
            // Create team member record
            $member_insert = "INSERT INTO team_members (consultant_id, member_user_id, member_type) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($member_insert);
            $stmt->bind_param('iis', $consultant_id, $user_id, $member_type);
            $stmt->execute();
            $stmt->close();
            
            // Send invitation email
            $invite_link = "https://" . $_SERVER['HTTP_HOST'] . "/activate.php?token=" . $token;
            $subject = "Invitation to join the team at Visafy";
            
            $message = "
            <html>
            <head>
                <title>Team Invitation</title>
            </head>
            <body>
                <p>Hello {$first_name} {$last_name},</p>
                <p>You have been invited to join the team at Visafy.</p>
                <p>Please click the link below to activate your account and set your password:</p>
                <p><a href='{$invite_link}'>{$invite_link}</a></p>
                <p>This link will expire in 48 hours.</p>
                <p>If you did not expect this invitation, please ignore this email.</p>
                <p>Regards,<br>The Visafy Team</p>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: Visafy <noreply@visafy.com>' . "\r\n";
            
            mail($email, $subject, $message, $headers);
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Invitation sent to {$email}";
            
            // Refresh the page to show updated list
            header("Location: team.php?success=1");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error sending invitation: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle member deletion/deactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_member'])) {
    $member_id = $_POST['member_id'];
    $user_id = $_POST['user_id'];
    
    // Soft delete - update status to suspended
    $update_query = "UPDATE users SET status = 'suspended' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('i', $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Team member deactivated successfully";
        $stmt->close();
        header("Location: team.php?success=2");
        exit;
    } else {
        $error_message = "Error deactivating team member: " . $conn->error;
        $stmt->close();
    }
}

// Handle member reactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_member'])) {
    $member_id = $_POST['member_id'];
    $user_id = $_POST['user_id'];
    
    // Update status to active
    $update_query = "UPDATE users SET status = 'active' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('i', $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Team member reactivated successfully";
        $stmt->close();
        header("Location: team.php?success=3");
        exit;
    } else {
        $error_message = "Error reactivating team member: " . $conn->error;
        $stmt->close();
    }
}

// Handle resending invite
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_invite'])) {
    $member_id = $_POST['member_id'];
    $user_id = $_POST['user_id'];
    
    // Get user details
    $user_query = "SELECT first_name, last_name, email FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user = $user_result->fetch_assoc();
    $stmt->close();
    
    if ($user) {
        // Create new token
        $token = generateInviteToken();
        $expires = date('Y-m-d H:i:s', strtotime('+48 hours'));
        
        // Update token in database
        $update_query = "UPDATE users SET email_verification_token = ?, email_verification_expires = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ssi', $token, $expires, $user_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            // Send invitation email
            $invite_link = "https://" . $_SERVER['HTTP_HOST'] . "/activate.php?token=" . $token;
            $subject = "Invitation to join the team at Visafy";
            
            $message = "
            <html>
            <head>
                <title>Team Invitation</title>
            </head>
            <body>
                <p>Hello {$user['first_name']} {$user['last_name']},</p>
                <p>You have been invited to join the team at Visafy.</p>
                <p>Please click the link below to activate your account and set your password:</p>
                <p><a href='{$invite_link}'>{$invite_link}</a></p>
                <p>This link will expire in 48 hours.</p>
                <p>If you did not expect this invitation, please ignore this email.</p>
                <p>Regards,<br>The Visafy Team</p>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: Visafy <noreply@visafy.com>' . "\r\n";
            
            mail($user['email'], $subject, $message, $headers);
            
            $success_message = "Invitation resent to {$user['email']}";
            header("Location: team.php?success=4");
            exit;
        } else {
            $error_message = "Error resending invitation: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = "User not found";
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Invitation sent successfully";
            break;
        case 2:
            $success_message = "Team member deactivated successfully";
            break;
        case 3:
            $success_message = "Team member reactivated successfully";
            break;
        case 4:
            $success_message = "Invitation resent successfully";
            break;
    }
}
?>

<div class="content">
    <!-- Organization Details Section -->
    <div class="org-details-container">
        <div class="org-info">
            <h1>Organization Details</h1>
            <div class="org-details">
                <div class="org-detail-item">
                    <span class="label">Organization Name:</span>
                    <span class="value"><?php echo !empty($user['organization_name']) ? htmlspecialchars($user['organization_name']) : (!empty($organization['company_name']) ? htmlspecialchars($organization['company_name']) : 'Not set'); ?></span>
                </div>
                <div class="org-detail-item">
                    <span class="label">Current Plan:</span>
                    <span class="value plan-badge"><?php echo !empty($organization['plan_name']) ? htmlspecialchars($organization['plan_name']) : 'Free'; ?></span>
                </div>
                <div class="org-detail-item">
                    <span class="label">Team Members:</span>
                    <span class="value"><?php echo count($team_members); ?> / <?php echo !empty($organization['max_team_members']) ? $organization['max_team_members'] : 0; ?></span>
                </div>
                <?php if (!empty($organization['price'])): ?>
                <div class="org-detail-item">
                    <span class="label">Monthly Fee:</span>
                    <span class="value">$<?php echo number_format($organization['price'], 2); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="org-actions">
            <a href="subscription.php" class="btn primary-btn upgrade-btn">
                <i class="fas fa-arrow-circle-up"></i> Upgrade Plan
            </a>
        </div>
    </div>

    <div class="header-container">
        <div>
            <h2>Team Management</h2>
            <p>Manage your team members and send invitations to new team members.</p>
        </div>
        <div>
            <button type="button" class="btn primary-btn" id="addTeamMemberBtn">
                <i class="fas fa-plus"></i> Add Team Member
            </button>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Team Members Table Section -->
    <div class="team-table-container">
        <?php if (empty($team_members)): ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <p>No team members yet. Invite someone to get started!</p>
            </div>
        <?php else: ?>
            <table class="team-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Member Type</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($team_members as $member): ?>
                        <tr>
                            <td class="name-cell">
                                <div class="member-avatar">
                                    <?php if (!empty($member['profile_picture']) && file_exists('../../uploads/profiles/' . $member['profile_picture'])): ?>
                                        <img src="../../uploads/profiles/<?php echo $member['profile_picture']; ?>" alt="Profile picture">
                                    <?php else: ?>
                                        <div class="initials">
                                            <?php echo substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($member['member_type']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($member['email']); ?>
                                <?php if (!$member['email_verified']): ?>
                                    <span class="pending-badge" title="Pending activation"><i class="fas fa-clock"></i></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($member['phone']) ? htmlspecialchars($member['phone']) : '-'; ?></td>
                            <td>
                                <?php if ($member['status'] === 'active'): ?>
                                    <span class="status-badge active"><i class="fas fa-circle"></i> Active</span>
                                <?php else: ?>
                                    <span class="status-badge inactive"><i class="fas fa-circle"></i> Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <?php if ($member['status'] === 'active'): ?>
                                    <button type="button" class="btn-action btn-deactivate" 
                                            title="Deactivate" onclick="confirmAction('deactivate', <?php echo $member['id']; ?>, <?php echo $member['user_id']; ?>)">
                                        <i class="fas fa-user-slash"></i>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn-action btn-activate" 
                                            title="Activate" onclick="confirmAction('activate', <?php echo $member['id']; ?>, <?php echo $member['user_id']; ?>)">
                                        <i class="fas fa-user-check"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (!$member['email_verified']): ?>
                                    <button type="button" class="btn-action btn-resend" 
                                            title="Resend Invitation" onclick="confirmAction('resend', <?php echo $member['id']; ?>, <?php echo $member['user_id']; ?>)">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <a href="edit_member.php?id=<?php echo $member['id']; ?>" class="btn-action btn-edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Add Team Member Modal -->
<div class="modal" id="addTeamMemberModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Team Member</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="team.php" method="POST" id="addTeamMemberForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="modal_first_name">First Name*</label>
                            <input type="text" name="first_name" id="modal_first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="modal_last_name">Last Name*</label>
                            <input type="text" name="last_name" id="modal_last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="modal_email">Email Address*</label>
                        <input type="email" name="email" id="modal_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="modal_phone">Phone Number</label>
                        <input type="tel" name="phone" id="modal_phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="modal_member_type">Member Type*</label>
                        <select name="member_type" id="modal_member_type" class="form-control" required>
                            <option value="case_manager">Case Manager</option>
                            <option value="document_specialist">Document Specialist</option>
                            <option value="career_consultant">Career Consultant</option>
                            <option value="business_planner">Business Plan Creator</option>
                            <option value="immigration_assistant">Immigration Assistant</option>
                            <option value="social_media_manager">Social Media Manager</option>
                            <option value="crm_manager">Leads & CRM Manager</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="invite_member" class="btn submit-btn">Send Invitation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Hidden forms for actions -->
<form id="deactivateForm" action="team.php" method="POST" style="display: none;">
    <input type="hidden" name="member_id" id="deactivate_member_id">
    <input type="hidden" name="user_id" id="deactivate_user_id">
    <input type="hidden" name="deactivate_member" value="1">
</form>

<form id="activateForm" action="team.php" method="POST" style="display: none;">
    <input type="hidden" name="member_id" id="activate_member_id">
    <input type="hidden" name="user_id" id="activate_user_id">
    <input type="hidden" name="reactivate_member" value="1">
</form>

<form id="resendForm" action="team.php" method="POST" style="display: none;">
    <input type="hidden" name="member_id" id="resend_member_id">
    <input type="hidden" name="user_id" id="resend_user_id">
    <input type="hidden" name="resend_invite" value="1">
</form>

<style>
:root {
    --primary-color: #042167;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --danger-color: #e74a3b;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-color: #e3e6f0;
    --warning-color: #f6c23e;
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
}

.primary-btn:hover {
    background-color: #031c56;
}

.team-table-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.team-table {
    width: 100%;
    border-collapse: collapse;
}

.team-table th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    text-align: left;
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
}

.team-table td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--dark-color);
}

.team-table tbody tr:hover {
    background-color: rgba(4, 33, 103, 0.03);
}

.team-table tbody tr:last-child td {
    border-bottom: none;
}

.name-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.member-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    overflow: hidden;
    background-color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
}

.member-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.initials {
    color: white;
    font-weight: 600;
    font-size: 14px;
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

.status-badge i {
    font-size: 8px;
}

.pending-badge {
    display: inline-flex;
    margin-left: 5px;
    color: #f6c23e;
}

.actions-cell {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
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

.btn-activate {
    background-color: var(--success-color);
}

.btn-activate:hover {
    background-color: #18b07b;
}

.btn-deactivate {
    background-color: var(--danger-color);
}

.btn-deactivate:hover {
    background-color: #d44235;
}

.btn-resend {
    background-color: #4e73df;
}

.btn-resend:hover {
    background-color: #4262c3;
}

.btn-edit-role {
    background-color: var(--primary-color);
    width: 20px;
    height: 20px;
    font-size: 10px;
    margin-left: 5px;
    vertical-align: middle;
}

.empty-state {
    text-align: center;
    padding: 50px 20px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
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

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    flex: 1;
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

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(4, 33, 103, 0.1);
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

.submit-btn:hover {
    background-color: #031c56;
}

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .team-table {
        display: block;
        overflow-x: auto;
    }
    
    .actions-cell {
        flex-direction: column;
    }
    
    .btn-action {
        width: 100%;
        justify-content: center;
    }
}

/* Add organization details styling */
.org-details-container {
    display: flex;
    justify-content: space-between;
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    margin-bottom: 30px;
}

.org-info h1 {
    color: var(--primary-color);
    font-size: 1.5rem;
    margin-bottom: 15px;
}

.org-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.org-detail-item {
    display: flex;
    flex-direction: column;
}

.org-detail-item .label {
    font-weight: 600;
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.org-detail-item .value {
    font-size: 1.1rem;
    color: var(--dark-color);
}

.plan-badge {
    display: inline-block;
    padding: 4px 10px;
    background-color: var(--primary-color);
    color: white !important;
    border-radius: 15px;
    font-size: 0.9rem !important;
}

.upgrade-btn {
    padding: 10px 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

@media (max-width: 768px) {
    .org-details-container {
        flex-direction: column;
        gap: 20px;
    }
    
    .org-details {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Modal functionality for add team member
document.getElementById('addTeamMemberBtn').addEventListener('click', function() {
    document.getElementById('addTeamMemberModal').style.display = 'block';
});

// Close modals when close button is clicked
document.querySelectorAll('[data-dismiss="modal"]').forEach(function(element) {
    element.addEventListener('click', function() {
        document.getElementById('addTeamMemberModal').style.display = 'none';
        document.getElementById('assignRoleModal').style.display = 'none';
    });
});

// Close modals when clicking outside of them
window.addEventListener('click', function(event) {
    if (event.target === document.getElementById('addTeamMemberModal')) {
        document.getElementById('addTeamMemberModal').style.display = 'none';
    }
    if (event.target === document.getElementById('assignRoleModal')) {
        document.getElementById('assignRoleModal').style.display = 'none';
    }
});

// Function to show the assign role modal
function showAssignRoleModal(userId, memberName) {
    document.getElementById('assign_user_id').value = userId;
    document.getElementById('member_name_display').textContent = memberName;
    document.getElementById('assignRoleModal').style.display = 'block';
}

// Function to handle action confirmations (deactivate, activate, resend)
function confirmAction(action, memberId, userId) {
    switch(action) {
        case 'deactivate':
            if (confirm('Are you sure you want to deactivate this team member?')) {
                document.getElementById('deactivate_member_id').value = memberId;
                document.getElementById('deactivate_user_id').value = userId;
                document.getElementById('deactivateForm').submit();
            }
            break;
        case 'activate':
            document.getElementById('activate_member_id').value = memberId;
            document.getElementById('activate_user_id').value = userId;
            document.getElementById('activateForm').submit();
            break;
        case 'resend':
            if (confirm('Resend invitation email to this team member?')) {
                document.getElementById('resend_member_id').value = memberId;
                document.getElementById('resend_user_id').value = userId;
                document.getElementById('resendForm').submit();
            }
            break;
    }
}
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>

