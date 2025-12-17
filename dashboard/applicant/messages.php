<?php
// Set page title
$page_title = "Messages - Applicant";

// Include header
include('includes/header.php');

// Helper function to format time ago
function formatTimeAgo($timestamp) {
    $current_time = time();
    $time_difference = $current_time - strtotime($timestamp);
    
    if ($time_difference < 60) {
        return 'Just now';
    } elseif ($time_difference < 3600) {
        return floor($time_difference / 60) . ' min ago';
    } elseif ($time_difference < 86400) {
        return floor($time_difference / 3600) . ' hours ago';
    } elseif ($time_difference < 604800) {
        return floor($time_difference / 86400) . ' days ago';
    } else {
        return date('M j', strtotime($timestamp));
    }
}

// Get applicant ID
$applicant_id = $user_id;

// Get list of consultants the applicant has relationships with (only active ones)
$sql = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.profile_picture, 
               acr.relationship_type, acr.organization_id, o.name as organization_name,
               c.company_name, acr.status
        FROM applicant_consultant_relationships acr
        JOIN users u ON acr.consultant_id = u.id
        JOIN consultants c ON u.id = c.user_id
        JOIN organizations o ON acr.organization_id = o.id
        JOIN applicants a ON acr.applicant_id = a.user_id
        WHERE acr.applicant_id = ? AND acr.status = 'active'
        ORDER BY u.first_name, u.last_name";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$consultants_result = $stmt->get_result();
$consultants = [];

while ($row = $consultants_result->fetch_assoc()) {
    $consultants[] = $row;
}
$stmt->close();

// Get team members from the same organizations as the consultants
$team_members = [];
if (!empty($consultants)) {
    $organization_ids = array_unique(array_column($consultants, 'organization_id'));
    $org_ids_str = implode(',', $organization_ids);
    
    $sql = "SELECT DISTINCT tm.id as team_member_id, u.id, u.first_name, u.last_name, u.profile_picture, 
                   tm.member_type, tm.consultant_id, o.id as organization_id, o.name as organization_name
            FROM team_members tm
            JOIN users u ON tm.member_user_id = u.id
            JOIN consultants c ON tm.consultant_id = c.user_id
            JOIN organizations o ON u.organization_id = o.id
            WHERE o.id IN ($org_ids_str)
            AND tm.invitation_status = 'accepted'
            ORDER BY u.first_name, u.last_name";
    
    $team_result = $conn->query($sql);
    while ($row = $team_result->fetch_assoc()) {
        $team_members[] = $row;
    }
}

// Combine consultants and team members into one contacts array
$contacts = array_merge($consultants, $team_members);

// Get selected contact (default to first one if not specified)
$selected_contact_id = isset($_GET['contact']) ? intval($_GET['contact']) : 
                      (!empty($contacts) ? $contacts[0]['id'] : 0);
$selected_contact_type = 'consultant'; // Default type

// Check if selected contact is a team member
foreach ($team_members as $tm) {
    if ($tm['id'] == $selected_contact_id) {
        $selected_contact_type = 'team_member';
        break;
    }
}

// Create conversation if it doesn't exist
$conversation_id = null;

if ($selected_contact_id > 0) {
    // Check if conversation exists
    $sql = "SELECT c.id 
            FROM conversations c
            JOIN conversation_participants cp1 ON c.id = cp1.conversation_id
            JOIN conversation_participants cp2 ON c.id = cp2.conversation_id
            WHERE c.type = 'direct'
            AND cp1.user_id = ?
            AND cp2.user_id = ?
            AND c.deleted_at IS NULL
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $applicant_id, $selected_contact_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $conversation_id = $result->fetch_assoc()['id'];
    } else {
        // Create new conversation
        $sql = "INSERT INTO conversations (type, created_by, organization_id, created_at, updated_at, last_message_at) 
                VALUES ('direct', ?, (SELECT organization_id FROM users WHERE id = ?), NOW(), NOW(), NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $applicant_id, $selected_contact_id);
        
        if ($stmt->execute()) {
            $conversation_id = $conn->insert_id;
            
            // Add participants
            $sql = "INSERT INTO conversation_participants (conversation_id, user_id, role, joined_at) VALUES (?, ?, 'applicant', NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $conversation_id, $applicant_id);
            $stmt->execute();
            
            $role = $selected_contact_type == 'consultant' ? 'consultant' : 'team_member';
            $sql = "INSERT INTO conversation_participants (conversation_id, user_id, role, joined_at) VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $conversation_id, $selected_contact_id, $role);
            $stmt->execute();
        }
    }
}

// Send message if form submitted
$message_sent = false;
$message_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message']) && $conversation_id) {
    $message_content = trim($_POST['message_content']);
    
    if (empty($message_content)) {
        $message_error = "Please enter a message.";
    } else {
        // Insert message
        $stmt = $conn->prepare("INSERT INTO messages (conversation_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $conversation_id, $applicant_id, $message_content);
        
        if ($stmt->execute()) {
            $message_sent = true;
        } else {
            $message_error = "Failed to send message. Please try again.";
        }
        $stmt->close();
    }
}

// Mark messages as read for this conversation
if ($conversation_id) {
    $sql = "INSERT INTO message_read_status (message_id, user_id, read_at)
            SELECT m.id, ?, NOW()
            FROM messages m
            LEFT JOIN message_read_status mrs ON m.id = mrs.message_id AND mrs.user_id = ?
            WHERE m.conversation_id = ?
            AND m.user_id != ?
            AND mrs.id IS NULL";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $applicant_id, $applicant_id, $conversation_id, $applicant_id);
    $stmt->execute();
    $stmt->close();
}
?>

<div class="content">
    <div class="dashboard-header">
        <h1>Messages</h1>
        <p>Communicate with your consultants and their team</p>
    </div>

    <?php if ($message_sent): ?>
    <div class="alert alert-success">
        Message sent successfully.
    </div>
    <?php endif; ?>

    <?php if (!empty($message_error)): ?>
    <div class="alert alert-danger">
        <?php echo $message_error; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($contacts)): ?>
    <div class="empty-state">
        <i class="fas fa-comment-slash"></i>
        <p>You don't have any active relationships with consultants yet. Book a consultation to start messaging with a consultant.</p>
    </div>
    <?php else: ?>
    <div class="messaging-container">
        <div class="messaging-grid">
            <!-- Contacts List -->
            <div class="contacts-sidebar">
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Contacts</h2>
                    </div>
                    <ul class="contacts-list">
                        <?php foreach ($contacts as $contact):
                            $contact_id = $contact['id'];
                            $is_team_member = isset($contact['team_member_id']);
                            $contact_type = $is_team_member ? 'team_member' : 'consultant';
                            
                            // Find conversation
                            $conv_id = null;
                            $sql = "SELECT c.id 
                                    FROM conversations c
                                    JOIN conversation_participants cp1 ON c.id = cp1.conversation_id
                                    JOIN conversation_participants cp2 ON c.id = cp2.conversation_id
                                    WHERE c.type = 'direct'
                                    AND cp1.user_id = ?
                                    AND cp2.user_id = ?
                                    AND c.deleted_at IS NULL
                                    LIMIT 1";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("ii", $applicant_id, $contact_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($result->num_rows > 0) {
                                $conv_id = $result->fetch_assoc()['id'];
                            }
                            $stmt->close();
                            
                            // Check for unread messages
                            $unread_count = 0;
                            if ($conv_id) {
                                $sql = "SELECT COUNT(*) as count 
                                        FROM messages m
                                        LEFT JOIN message_read_status mrs ON m.id = mrs.message_id AND mrs.user_id = ?
                                        WHERE m.conversation_id = ?
                                        AND m.user_id = ?
                                        AND mrs.id IS NULL";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("iii", $applicant_id, $conv_id, $contact_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $unread_count = $result->fetch_assoc()['count'];
                                $stmt->close();
                            }
                            
                            // Prepare profile image
                            $profile_img = '../../assets/images/default-profile.jpg';
                            if (!empty($contact['profile_picture'])) {
                                if (file_exists('../../uploads/profiles/' . $contact['profile_picture'])) {
                                    $profile_img = '../../uploads/profiles/' . $contact['profile_picture'];
                                }
                            }
                            
                            $active_class = ($contact_id == $selected_contact_id) ? 'active' : '';
                        ?>
                        <li class="contact-item <?php echo $active_class; ?>">
                            <a href="?contact=<?php echo $contact_id; ?>" class="contact-link">
                                <div class="contact-avatar">
                                    <img src="<?php echo $profile_img; ?>" alt="Profile" class="contact-img">
                                </div>
                                <div class="contact-info">
                                    <div class="contact-name"><?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?></div>
                                    <div class="contact-status">
                                        <?php if ($is_team_member): ?>
                                            <?php echo ucfirst($contact['member_type']); ?> 
                                            <span>(<?php echo htmlspecialchars($contact['organization_name']); ?>)</span>
                                        <?php else: ?>
                                            Consultant 
                                            <span>(<?php echo htmlspecialchars($contact['organization_name']); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($unread_count > 0): ?>
                                <div class="unread-badge"><?php echo $unread_count; ?></div>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Messages Area -->
            <div class="messages-content">
                <?php if ($selected_contact_id > 0 && $conversation_id): 
                    // Get selected contact details
                    $sql = "SELECT u.first_name, u.last_name, u.profile_picture, 
                                  CASE 
                                      WHEN cp.role = 'consultant' THEN 'Consultant'
                                      WHEN cp.role = 'team_member' THEN (SELECT member_type FROM team_members WHERE member_user_id = u.id LIMIT 1)
                                      ELSE cp.role
                                  END as role,
                                  o.name as organization_name
                           FROM users u
                           JOIN conversation_participants cp ON u.id = cp.user_id
                           JOIN organizations o ON u.organization_id = o.id
                           WHERE u.id = ? AND cp.conversation_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $selected_contact_id, $conversation_id);
                    $stmt->execute();
                    $contact_data = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    // Get conversation messages
                    $sql = "SELECT m.id, m.user_id, m.message, m.created_at, 
                                  u.first_name, u.last_name, u.profile_picture
                           FROM messages m
                           JOIN users u ON m.user_id = u.id
                           WHERE m.conversation_id = ?
                           AND m.deleted_at IS NULL
                           ORDER BY m.created_at ASC";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $conversation_id);
                    $stmt->execute();
                    $messages_result = $stmt->get_result();
                    $messages = [];
                    
                    while ($row = $messages_result->fetch_assoc()) {
                        $messages[] = $row;
                    }
                    $stmt->close();
                    
                    // Prepare profile image
                    $contact_profile_img = '../../assets/images/default-profile.jpg';
                    if (!empty($contact_data['profile_picture'])) {
                        if (file_exists('../../uploads/profiles/' . $contact_data['profile_picture'])) {
                            $contact_profile_img = '../../uploads/profiles/' . $contact_data['profile_picture'];
                        }
                    }
                ?>
                <div class="dashboard-section">
                    <div class="section-header">
                        <div class="header-profile">
                            <img src="<?php echo $contact_profile_img; ?>" alt="Profile" class="header-profile-img">
                            <div>
                                <h2>
                                    <?php echo htmlspecialchars($contact_data['first_name'] . ' ' . $contact_data['last_name']); ?>
                                </h2>
                                <div class="header-profile-meta">
                                    <?php echo htmlspecialchars(ucfirst($contact_data['role'])); ?> -
                                    <?php echo htmlspecialchars($contact_data['organization_name']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="messages-container" id="messagesContainer">
                        <?php if (empty($messages)): ?>
                        <div class="empty-state">
                            <i class="fas fa-comments"></i>
                            <p>No messages yet. Start the conversation!</p>
                        </div>
                        <?php else: ?>
                            <?php 
                            $last_date = '';
                            foreach ($messages as $message): 
                                $message_date = date('Y-m-d', strtotime($message['created_at']));
                                $show_date = false;
                                
                                if ($message_date != $last_date) {
                                    $show_date = true;
                                    $last_date = $message_date;
                                }
                                
                                $is_outgoing = ($message['user_id'] == $applicant_id);
                                $message_class = $is_outgoing ? 'outgoing' : 'incoming';
                                
                                // Format time
                                $message_time = date('h:i A', strtotime($message['created_at']));
                            ?>
                                <?php if ($show_date): ?>
                                <div class="message-date-divider">
                                    <span><?php echo date('F j, Y', strtotime($message['created_at'])); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="message <?php echo $message_class; ?>">
                                    <?php if (!$is_outgoing): ?>
                                    <div class="message-sender">
                                        <?php echo htmlspecialchars($message['first_name']); ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="message-content">
                                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                    </div>
                                    <div class="message-time">
                                        <?php echo $message_time; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <form id="messageForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?contact=' . $selected_contact_id); ?>">
                        <div class="message-input-container">
                            <textarea class="form-control" name="message_content" id="messageContent" placeholder="Type your message..." required></textarea>
                            <button type="submit" name="send_message" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="dashboard-section">
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <p>Select a contact to start messaging.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
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

.alert {
    padding: 12px 16px;
    margin-bottom: 20px;
    border-radius: 8px;
    border-left: 4px solid;
}

.alert-success {
    background-color: rgba(28, 200, 138, 0.1);
    border-color: var(--success-color);
    color: var(--success-color);
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.1);
    border-color: var(--danger-color);
    color: var(--danger-color);
}

.messaging-container {
    height: calc(100vh - 200px);
}

.messaging-grid {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 20px;
    height: 100%;
}

.dashboard-section {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    height: 100%;
    display: flex;
    flex-direction: column;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
}

.section-header h2 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.2rem;
}

.contacts-list {
    list-style: none;
    padding: 0;
    margin: 0;
    overflow-y: auto;
    max-height: calc(100vh - 270px);
    flex-grow: 1;
}

.contact-item {
    border-bottom: 1px solid var(--border-color);
    position: relative;
}

.contact-item.active {
    background-color: var(--light-color);
}

.contact-link {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    text-decoration: none;
    color: inherit;
    transition: background-color 0.2s;
}

.contact-link:hover {
    background-color: rgba(4, 33, 103, 0.05);
}

.contact-avatar {
    position: relative;
    margin-right: 12px;
}

.contact-img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.contact-info {
    flex: 1;
    min-width: 0;
}

.contact-name {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--dark-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.contact-status {
    font-size: 0.8rem;
    color: var(--secondary-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.contact-status span {
    opacity: 0.7;
}

.unread-badge {
    background-color: var(--primary-color);
    color: white;
    font-size: 0.75rem;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
}

.header-profile {
    display: flex;
    align-items: center;
    gap: 15px;
}

.header-profile-img {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    object-fit: cover;
}

.header-profile-meta {
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.messages-container {
    padding: 20px;
    overflow-y: auto;
    flex-grow: 1;
    max-height: calc(100vh - 350px);
}

.message {
    margin-bottom: 15px;
    max-width: 80%;
}

.message.incoming {
    margin-right: auto;
}

.message.outgoing {
    margin-left: auto;
}

.message-sender {
    font-size: 0.8rem;
    color: var(--secondary-color);
    margin-bottom: 3px;
}

.message-content {
    padding: 12px 16px;
    border-radius: 18px;
    background-color: var(--light-color);
    display: inline-block;
    word-break: break-word;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.message.outgoing .message-content {
    background-color: var(--primary-color);
    color: white;
}

.message-time {
    font-size: 0.7rem;
    color: var(--secondary-color);
    margin-top: 3px;
    text-align: right;
}

.message-date-divider {
    text-align: center;
    margin: 20px 0;
    position: relative;
}

.message-date-divider span {
    background-color: white;
    padding: 0 10px;
    position: relative;
    z-index: 1;
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.message-date-divider:before {
    content: "";
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background-color: var(--border-color);
    z-index: 0;
}

.message-input-container {
    display: flex;
    gap: 10px;
    padding: 15px 20px;
    border-top: 1px solid var(--border-color);
}

.message-input-container textarea {
    flex-grow: 1;
    resize: none;
    min-height: 50px;
    max-height: 150px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 10px 15px;
    outline: none;
    transition: border-color 0.2s;
}

.message-input-container textarea:focus {
    border-color: var(--primary-color);
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 16px;
    border-radius: 6px;
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

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--secondary-color);
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.empty-state i {
    font-size: 36px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0;
    font-size: 0.9rem;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .messaging-grid {
        grid-template-columns: 1fr;
        grid-template-rows: auto 1fr;
    }
    
    .contacts-sidebar {
        max-height: 300px;
    }
    
    .contacts-list {
        max-height: 200px;
    }
}

@media (max-width: 576px) {
    .message-input-container {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Scroll to bottom of messages container
    const messagesContainer = document.getElementById('messagesContainer');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // Auto-resize textarea
    const messageContent = document.getElementById('messageContent');
    if (messageContent) {
        messageContent.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    }
});
</script>

<?php
// Include footer
include('includes/footer.php');
?> 