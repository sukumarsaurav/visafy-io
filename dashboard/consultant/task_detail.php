<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Task Details";
$page_specific_css = "assets/css/tasks.css";
require_once 'includes/header.php';

// Get user ID and organization ID
$user_id = $_SESSION["id"];
$user_type = $_SESSION["user_type"];
$organization_id = isset($user['organization_id']) ? $user['organization_id'] : null;

// Check if task ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: tasks.php?error=invalid_task");
    exit;
}

$task_id = (int)$_GET['id'];

// Get task details
$query = "SELECT t.id, t.name, t.description, t.priority, t.status, 
          t.due_date, t.completed_at, t.created_at,
          u.id as creator_id, u.first_name as creator_first_name, u.last_name as creator_last_name
          FROM tasks t
          JOIN users u ON t.creator_id = u.id
          WHERE t.id = ? AND t.organization_id = ? AND t.deleted_at IS NULL";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $task_id, $organization_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: tasks.php?error=task_not_found");
    exit;
}

$task = $result->fetch_assoc();
$stmt->close();

// Get assignees for the task
$assignee_query = "SELECT ta.id as assignment_id, ta.status as assignee_status, ta.started_at, ta.completed_at,
                  u.id as user_id, u.first_name, u.last_name, u.email, u.profile_picture, u.user_type
                  FROM task_assignments ta
                  JOIN users u ON ta.assignee_id = u.id
                  WHERE ta.task_id = ? AND ta.deleted_at IS NULL
                  ORDER BY u.first_name, u.last_name";

$assignee_stmt = $conn->prepare($assignee_query);
$assignee_stmt->bind_param("i", $task_id);
$assignee_stmt->execute();
$assignee_result = $assignee_stmt->get_result();
$assignees = [];

while ($row = $assignee_result->fetch_assoc()) {
    $assignees[] = $row;
}
$assignee_stmt->close();

// Get task comments
$comments_query = "SELECT tc.id, tc.comment, tc.created_at,
                 u.id as user_id, u.first_name, u.last_name, u.profile_picture, u.user_type
                 FROM task_comments tc
                 JOIN users u ON tc.user_id = u.id
                 WHERE tc.task_id = ? AND tc.deleted_at IS NULL
                 ORDER BY tc.created_at DESC";

$comments_stmt = $conn->prepare($comments_query);
$comments_stmt->bind_param("i", $task_id);
$comments_stmt->execute();
$comments_result = $comments_stmt->get_result();
$comments = [];

while ($row = $comments_result->fetch_assoc()) {
    $comments[] = $row;
}
$comments_stmt->close();

// Get task attachments
$attachments_query = "SELECT ta.id, ta.file_name, ta.file_path, ta.file_type, ta.file_size, ta.created_at,
                    u.id as user_id, u.first_name, u.last_name
                    FROM task_attachments ta
                    JOIN users u ON ta.user_id = u.id
                    WHERE ta.task_id = ?
                    ORDER BY ta.created_at DESC";

$attachments_stmt = $conn->prepare($attachments_query);
$attachments_stmt->bind_param("i", $task_id);
$attachments_stmt->execute();
$attachments_result = $attachments_stmt->get_result();
$attachments = [];

while ($row = $attachments_result->fetch_assoc()) {
    $attachments[] = $row;
}
$attachments_stmt->close();

// Get task activity logs
$activity_query = "SELECT tal.id, tal.activity_type, tal.description, tal.created_at,
                 u.id as user_id, u.first_name, u.last_name,
                 affected.id as affected_user_id, affected.first_name as affected_first_name, affected.last_name as affected_last_name
                 FROM task_activity_logs tal
                 JOIN users u ON tal.user_id = u.id
                 LEFT JOIN users affected ON tal.affected_user_id = affected.id
                 WHERE tal.task_id = ?
                 ORDER BY tal.created_at DESC";

$activity_stmt = $conn->prepare($activity_query);
$activity_stmt->bind_param("i", $task_id);
$activity_stmt->execute();
$activity_result = $activity_stmt->get_result();
$activities = [];

while ($row = $activity_result->fetch_assoc()) {
    $activities[] = $row;
}
$activity_stmt->close();

// Get all team members for assignment
$team_query = "SELECT tm.id, tm.member_type, 
               u.id as user_id, u.first_name, u.last_name, u.email, u.status, u.profile_picture, u.user_type
               FROM team_members tm
               JOIN users u ON tm.member_user_id = u.id
               WHERE tm.consultant_id = ? AND u.status = 'active' AND u.deleted_at IS NULL
               ORDER BY u.first_name, u.last_name";
$team_stmt = $conn->prepare($team_query);
$team_stmt->bind_param("i", $user_id);
$team_stmt->execute();
$team_result = $team_stmt->get_result();
$team_members = [];

while ($row = $team_result->fetch_assoc()) {
    $team_members[] = $row;
}
$team_stmt->close();

// Get all clients for assignment
$clients_query = "SELECT u.id as user_id, u.first_name, u.last_name, u.email, u.status, u.profile_picture
               FROM users u
               WHERE u.user_type = 'applicant' AND u.status = 'active' AND u.deleted_at IS NULL
               AND u.organization_id = ?
               ORDER BY u.first_name, u.last_name";
$clients_stmt = $conn->prepare($clients_query);
$clients_stmt->bind_param("i", $organization_id);
$clients_stmt->execute();
$clients_result = $clients_stmt->get_result();
$clients = [];

while ($row = $clients_result->fetch_assoc()) {
    $clients[] = $row;
}
$clients_stmt->close();

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task_status'])) {
    $new_status = $_POST['new_status'];
    
    // Update task status
    $update_query = "UPDATE tasks SET status = ?, completed_at = ? WHERE id = ?";
    $completed_at = ($new_status === 'completed') ? date('Y-m-d H:i:s') : null;
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('ssi', $new_status, $completed_at, $task_id);
    
    if ($stmt->execute()) {
        // Create activity log
        $log_insert = "INSERT INTO task_activity_logs (task_id, user_id, activity_type, description) 
                     VALUES (?, ?, 'status_changed', ?)";
        $description = "Task status changed to " . $new_status;
        
        $log_stmt = $conn->prepare($log_insert);
        $log_stmt->bind_param('iis', $task_id, $user_id, $description);
        $log_stmt->execute();
        $log_stmt->close();
        
        $success_message = "Task status updated successfully";
        $stmt->close();
        header("Location: task_detail.php?id=$task_id&success=status_updated");
        exit;
    } else {
        $error_message = "Error updating task status: " . $conn->error;
        $stmt->close();
    }
}

// Handle adding assignees
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assignees'])) {
    $assignees = isset($_POST['assignees']) ? $_POST['assignees'] : [];
    $client_assignees = isset($_POST['client_assignees']) ? $_POST['client_assignees'] : [];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Insert team member task assignments
        if (!empty($assignees)) {
            $assignment_insert = "INSERT INTO task_assignments (task_id, assignee_id, assigned_by) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($assignment_insert);
            
            foreach ($assignees as $assignee_id) {
                // Check if assignment already exists
                $check_query = "SELECT id FROM task_assignments WHERE task_id = ? AND assignee_id = ? AND deleted_at IS NULL";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param('ii', $task_id, $assignee_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows === 0) {
                    $stmt->bind_param('iii', $task_id, $assignee_id, $user_id);
                    $stmt->execute();
                    
                    // Create activity log for assignment
                    $log_insert = "INSERT INTO task_activity_logs (task_id, user_id, affected_user_id, activity_type, description) 
                                 VALUES (?, ?, ?, 'assigned', 'Task assigned to team member')";
                    $log_stmt = $conn->prepare($log_insert);
                    $log_stmt->bind_param('iii', $task_id, $user_id, $assignee_id);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                $check_stmt->close();
            }
            $stmt->close();
        }
        
        // Insert client task assignments
        if (!empty($client_assignees)) {
            $assignment_insert = "INSERT INTO task_assignments (task_id, assignee_id, assigned_by) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($assignment_insert);
            
            foreach ($client_assignees as $client_id) {
                // Check if assignment already exists
                $check_query = "SELECT id FROM task_assignments WHERE task_id = ? AND assignee_id = ? AND deleted_at IS NULL";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param('ii', $task_id, $client_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows === 0) {
                    $stmt->bind_param('iii', $task_id, $client_id, $user_id);
                    $stmt->execute();
                    
                    // Create activity log for client assignment
                    $log_insert = "INSERT INTO task_activity_logs (task_id, user_id, affected_user_id, activity_type, description) 
                                 VALUES (?, ?, ?, 'assigned', 'Task assigned to client')";
                    $log_stmt = $conn->prepare($log_insert);
                    $log_stmt->bind_param('iii', $task_id, $user_id, $client_id);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                $check_stmt->close();
            }
            $stmt->close();
        }
        
        // Commit transaction
        $conn->commit();
        
        $success_message = "Assignees added successfully";
        header("Location: task_detail.php?id=$task_id&success=assignees_added");
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error adding assignees: " . $e->getMessage();
    }
}

// Handle removing assignee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_assignee'])) {
    $assignment_id = $_POST['assignment_id'];
    $assignee_id = $_POST['assignee_id'];
    
    // Soft delete the assignment
    $delete_query = "UPDATE task_assignments SET deleted_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param('i', $assignment_id);
    
    if ($stmt->execute()) {
        // Create activity log
        $log_insert = "INSERT INTO task_activity_logs (task_id, user_id, affected_user_id, activity_type, description) 
                     VALUES (?, ?, ?, 'unassigned', 'User removed from task')";
        
        $log_stmt = $conn->prepare($log_insert);
        $log_stmt->bind_param('iii', $task_id, $user_id, $assignee_id);
        $log_stmt->execute();
        $log_stmt->close();
        
        $success_message = "Assignee removed successfully";
        $stmt->close();
        header("Location: task_detail.php?id=$task_id&success=assignee_removed");
        exit;
    } else {
        $error_message = "Error removing assignee: " . $conn->error;
        $stmt->close();
    }
}

// Handle adding comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $comment = trim($_POST['comment']);
    
    if (!empty($comment)) {
        $comment_insert = "INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($comment_insert);
        $stmt->bind_param('iis', $task_id, $user_id, $comment);
        
        if ($stmt->execute()) {
            // Create activity log
            $log_insert = "INSERT INTO task_activity_logs (task_id, user_id, activity_type, description) 
                         VALUES (?, ?, 'commented', 'Added a comment')";
            
            $log_stmt = $conn->prepare($log_insert);
            $log_stmt->bind_param('ii', $task_id, $user_id);
            $log_stmt->execute();
            $log_stmt->close();
            
            $success_message = "Comment added successfully";
            $stmt->close();
            header("Location: task_detail.php?id=$task_id&success=comment_added");
            exit;
        } else {
            $error_message = "Error adding comment: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = "Comment cannot be empty";
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_attachment'])) {
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['attachment']['name'];
        $file_tmp = $_FILES['attachment']['tmp_name'];
        $file_type = $_FILES['attachment']['type'];
        $file_size = $_FILES['attachment']['size'];
        
        // Create uploads directory if it doesn't exist
        $upload_dir = "../../uploads/tasks/";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $unique_name = uniqid() . '_' . time() . '.' . $file_ext;
        $file_path = "uploads/tasks/" . $unique_name;
        $full_path = $upload_dir . $unique_name;
        
        if (move_uploaded_file($file_tmp, $full_path)) {
            // Insert attachment record
            $attachment_insert = "INSERT INTO task_attachments (task_id, user_id, file_name, file_path, file_type, file_size) 
                               VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($attachment_insert);
            $stmt->bind_param('iisssi', $task_id, $user_id, $file_name, $file_path, $file_type, $file_size);
            
            if ($stmt->execute()) {
                // Create activity log
                $log_insert = "INSERT INTO task_activity_logs (task_id, user_id, activity_type, description) 
                             VALUES (?, ?, 'attachment_added', 'Added an attachment')";
                
                $log_stmt = $conn->prepare($log_insert);
                $log_stmt->bind_param('ii', $task_id, $user_id);
                $log_stmt->execute();
                $log_stmt->close();
                
                $success_message = "File uploaded successfully";
                $stmt->close();
                header("Location: task_detail.php?id=$task_id&success=file_uploaded");
                exit;
            } else {
                $error_message = "Error saving attachment record: " . $conn->error;
                $stmt->close();
            }
        } else {
            $error_message = "Error uploading file";
        }
    } else {
        $error_message = "Please select a file to upload";
    }
}

// Handle update assignee status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_assignee_status'])) {
    $assignment_id = $_POST['assignment_id'];
    $assignee_id = $_POST['assignee_id'];
    $new_status = $_POST['assignee_status'];
    
    // Update assignment status
    $update_query = "UPDATE task_assignments SET status = ?, started_at = ?, completed_at = ? WHERE id = ?";
    $started_at = null;
    $completed_at = null;
    
    if ($new_status === 'in_progress' && empty($assignee['started_at'])) {
        $started_at = date('Y-m-d H:i:s');
    }
    
    if ($new_status === 'completed') {
        $completed_at = date('Y-m-d H:i:s');
    }
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('sssi', $new_status, $started_at, $completed_at, $assignment_id);
    
    if ($stmt->execute()) {
        // Create activity log
        $log_insert = "INSERT INTO task_activity_logs (task_id, user_id, affected_user_id, activity_type, description) 
                     VALUES (?, ?, ?, 'assignee_status_changed', ?)";
        $description = "Assignee status changed to " . $new_status;
        
        $log_stmt = $conn->prepare($log_insert);
        $log_stmt->bind_param('iiis', $task_id, $user_id, $assignee_id, $description);
        $log_stmt->execute();
        $log_stmt->close();
        
        $success_message = "Assignee status updated successfully";
        $stmt->close();
        header("Location: task_detail.php?id=$task_id&success=assignee_status_updated");
        exit;
    } else {
        $error_message = "Error updating assignee status: " . $conn->error;
        $stmt->close();
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'status_updated':
            $success_message = "Task status updated successfully";
            break;
        case 'assignees_added':
            $success_message = "Assignees added successfully";
            break;
        case 'assignee_removed':
            $success_message = "Assignee removed successfully";
            break;
        case 'comment_added':
            $success_message = "Comment added successfully";
            break;
        case 'file_uploaded':
            $success_message = "File uploaded successfully";
            break;
        case 'assignee_status_updated':
            $success_message = "Assignee status updated successfully";
            break;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Task Details</h1>
            <p><a href="tasks.php">Back to Tasks</a></p>
        </div>
        <div class="task-actions">
            <?php if ($task['status'] === 'pending'): ?>
                <button type="button" class="btn action-btn start-btn" 
                        onclick="updateTaskStatus('in_progress')">
                    <i class="fas fa-play"></i> Start Task
                </button>
            <?php elseif ($task['status'] === 'in_progress'): ?>
                <button type="button" class="btn action-btn complete-btn" 
                        onclick="updateTaskStatus('completed')">
                    <i class="fas fa-check"></i> Complete Task
                </button>
            <?php elseif ($task['status'] === 'completed'): ?>
                <button type="button" class="btn action-btn reopen-btn" 
                        onclick="updateTaskStatus('in_progress')">
                    <i class="fas fa-redo"></i> Reopen Task
                </button>
            <?php endif; ?>
            
            <button type="button" class="btn primary-btn" id="addAssigneesBtn">
                <i class="fas fa-user-plus"></i> Add Assignees
            </button>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="task-detail-container">
        <div class="task-detail-header">
            <div class="task-title-section">
                <h2><?php echo htmlspecialchars($task['name']); ?></h2>
                <div class="task-meta">
                    <span class="task-creator">
                        Created by <?php echo htmlspecialchars($task['creator_first_name'] . ' ' . $task['creator_last_name']); ?>
                    </span>
                    <span class="task-date">
                        on <?php echo date('M d, Y', strtotime($task['created_at'])); ?>
                    </span>
                </div>
            </div>
            <div class="task-status-section">
                <div class="status-priority-container">
                    <div class="status-badge-container">
                        <span class="status-label">Status:</span>
                        <span class="status-badge <?php echo $task['status']; ?>">
                            <?php echo ucwords(str_replace('_', ' ', $task['status'])); ?>
                        </span>
                    </div>
                    <div class="priority-badge-container">
                        <span class="priority-label">Priority:</span>
                        <span class="priority-badge <?php echo $task['priority']; ?>">
                            <?php echo ucfirst($task['priority']); ?>
                        </span>
                    </div>
                </div>
                <?php if (!empty($task['due_date'])): ?>
                    <?php 
                        $due_date = new DateTime($task['due_date']);
                        $today = new DateTime();
                        $interval = $today->diff($due_date);
                        $is_overdue = $due_date < $today && $task['status'] !== 'completed';
                        $date_class = $is_overdue ? 'overdue' : '';
                    ?>
                    <div class="due-date-container">
                        <span class="due-date-label">Due Date:</span>
                        <span class="due-date <?php echo $date_class; ?>">
                            <?php echo $due_date->format('M d, Y'); ?>
                            <?php if ($is_overdue): ?>
                                <span class="overdue-tag">Overdue</span>
                            <?php elseif ($interval->days == 0): ?>
                                <span class="today-tag">Today</span>
                            <?php elseif ($interval->days == 1 && $due_date > $today): ?>
                                <span class="tomorrow-tag">Tomorrow</span>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($task['description'])): ?>
            <div class="task-description">
                <h3>Description</h3>
                <div class="description-content">
                    <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="task-detail-tabs">
            <div class="tab-buttons">
                <button type="button" class="tab-btn active" data-tab="assignees-tab">Assignees</button>
                <button type="button" class="tab-btn" data-tab="comments-tab">Comments</button>
                <button type="button" class="tab-btn" data-tab="attachments-tab">Attachments</button>
                <button type="button" class="tab-btn" data-tab="activity-tab">Activity</button>
            </div>
            
            <div class="tab-content" id="assignees-tab">
                <?php if (empty($assignees)): ?>
                    <div class="empty-state">
                        <p>No assignees yet. Add assignees to track progress.</p>
                    </div>
                <?php else: ?>
                    <div class="assignees-list">
                        <?php foreach ($assignees as $assignee): ?>
                            <div class="assignee-card">
                                <div class="assignee-info">
                                    <div class="assignee-avatar">
                                        <?php if (!empty($assignee['profile_picture']) && file_exists('../../uploads/profiles/' . $assignee['profile_picture'])): ?>
                                            <img src="../../uploads/profiles/<?php echo $assignee['profile_picture']; ?>" alt="Profile picture">
                                        <?php else: ?>
                                            <div class="initials">
                                                <?php echo substr($assignee['first_name'], 0, 1) . substr($assignee['last_name'], 0, 1); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="assignee-details">
                                        <div class="assignee-name">
                                            <?php echo htmlspecialchars($assignee['first_name'] . ' ' . $assignee['last_name']); ?>
                                        </div>
                                        <div class="assignee-type">
                                            <?php echo ucfirst($assignee['user_type']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="assignee-status-section">
                                    <div class="assignee-status">
                                        <span class="status-badge <?php echo $assignee['assignee_status']; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $assignee['assignee_status'])); ?>
                                        </span>
                                    </div>
                                    <div class="assignee-actions">
                                        <button type="button" class="btn-action" 
                                                onclick="openUpdateStatusModal(<?php echo $assignee['assignment_id']; ?>, <?php echo $assignee['user_id']; ?>, '<?php echo $assignee['assignee_status']; ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn-action btn-remove" 
                                                onclick="confirmRemoveAssignee(<?php echo $assignee['assignment_id']; ?>, <?php echo $assignee['user_id']; ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="tab-content" id="comments-tab" style="display: none;">
                <div class="add-comment-section">
                    <form action="task_detail.php?id=<?php echo $task_id; ?>" method="POST">
                        <div class="form-group">
                            <textarea name="comment" class="form-control" placeholder="Add a comment..." rows="3" required></textarea>
                        </div>
                        <button type="submit" name="add_comment" class="btn primary-btn">
                            <i class="fas fa-comment"></i> Add Comment
                        </button>
                    </form>
                </div>
                
                <?php if (empty($comments)): ?>
                    <div class="empty-state">
                        <p>No comments yet. Be the first to comment!</p>
                    </div>
                <?php else: ?>
                    <div class="comments-list">
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment-card">
                                <div class="comment-header">
                                    <div class="commenter-info">
                                        <div class="commenter-avatar">
                                            <?php if (!empty($comment['profile_picture']) && file_exists('../../uploads/profiles/' . $comment['profile_picture'])): ?>
                                                <img src="../../uploads/profiles/<?php echo $comment['profile_picture']; ?>" alt="Profile picture">
                                            <?php else: ?>
                                                <div class="initials">
                                                    <?php echo substr($comment['first_name'], 0, 1) . substr($comment['last_name'], 0, 1); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="commenter-details">
                                            <div class="commenter-name">
                                                <?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?>
                                            </div>
                                            <div class="comment-date">
                                                <?php echo date('M d, Y h:i A', strtotime($comment['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="comment-content">
                                    <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="tab-content" id="attachments-tab" style="display: none;">
                <div class="add-attachment-section">
                    <form action="task_detail.php?id=<?php echo $task_id; ?>" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <div class="file-upload-container">
                                <input type="file" name="attachment" id="attachment" class="file-input" required>
                                <label for="attachment" class="file-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Choose a file</span>
                                </label>
                                <div class="selected-file"></div>
                            </div>
                        </div>
                        <button type="submit" name="upload_attachment" class="btn primary-btn">
                            <i class="fas fa-upload"></i> Upload File
                        </button>
                    </form>
                </div>
                
                <?php if (empty($attachments)): ?>
                    <div class="empty-state">
                        <p>No attachments yet. Upload files to share with assignees.</p>
                    </div>
                <?php else: ?>
                    <div class="attachments-list">
                        <?php foreach ($attachments as $attachment): ?>
                            <div class="attachment-card">
                                <div class="attachment-icon">
                                    <?php 
                                        $icon_class = 'fas fa-file';
                                        if (strpos($attachment['file_type'], 'image') !== false) {
                                            $icon_class = 'fas fa-file-image';
                                        } elseif (strpos($attachment['file_type'], 'pdf') !== false) {
                                            $icon_class = 'fas fa-file-pdf';
                                        } elseif (strpos($attachment['file_type'], 'word') !== false || strpos($attachment['file_type'], 'document') !== false) {
                                            $icon_class = 'fas fa-file-word';
                                        } elseif (strpos($attachment['file_type'], 'excel') !== false || strpos($attachment['file_type'], 'spreadsheet') !== false) {
                                            $icon_class = 'fas fa-file-excel';
                                        }
                                    ?>
                                    <i class="<?php echo $icon_class; ?>"></i>
                                </div>
                                <div class="attachment-details">
                                    <div class="attachment-name">
                                        <?php echo htmlspecialchars($attachment['file_name']); ?>
                                    </div>
                                    <div class="attachment-meta">
                                        <span class="attachment-size">
                                            <?php echo formatFileSize($attachment['file_size']); ?>
                                        </span>
                                        <span class="attachment-date">
                                            <?php echo date('M d, Y', strtotime($attachment['created_at'])); ?>
                                        </span>
                                        <span class="attachment-uploader">
                                            by <?php echo htmlspecialchars($attachment['first_name'] . ' ' . $attachment['last_name']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="attachment-actions">
                                    <a href="../../<?php echo $attachment['file_path']; ?>" class="btn-action" download>
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="tab-content" id="activity-tab" style="display: none;">
                <?php if (empty($activities)): ?>
                    <div class="empty-state">
                        <p>No activity recorded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="activity-timeline">
                        <?php foreach ($activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php 
                                        $icon_class = 'fas fa-info-circle';
                                        switch ($activity['activity_type']) {
                                            case 'created':
                                                $icon_class = 'fas fa-plus-circle';
                                                break;
                                            case 'updated':
                                                $icon_class = 'fas fa-edit';
                                                break;
                                            case 'status_changed':
                                                $icon_class = 'fas fa-exchange-alt';
                                                break;
                                            case 'assigned':
                                                $icon_class = 'fas fa-user-plus';
                                                break;
                                            case 'unassigned':
                                                $icon_class = 'fas fa-user-minus';
                                                break;
                                            case 'assignee_status_changed':
                                                $icon_class = 'fas fa-user-clock';
                                                break;
                                            case 'commented':
                                                $icon_class = 'fas fa-comment';
                                                break;
                                            case 'attachment_added':
                                                $icon_class = 'fas fa-paperclip';
                                                break;
                                        }
                                    ?>
                                    <i class="<?php echo $icon_class; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-header">
                                        <span class="activity-user">
                                            <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                        </span>
                                        <span class="activity-action">
                                            <?php echo formatActivityType($activity['activity_type']); ?>
                                        </span>
                                        <?php if (!empty($activity['affected_user_id'])): ?>
                                            <span class="activity-affected-user">
                                                <?php echo htmlspecialchars($activity['affected_first_name'] . ' ' . $activity['affected_last_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-description">
                                        <?php echo htmlspecialchars($activity['description']); ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo formatActivityTime($activity['created_at']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Assignees Modal -->
<div class="modal" id="addAssigneesModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Assignees</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="task_detail.php?id=<?php echo $task_id; ?>" method="POST" id="addAssigneesForm">
                    <div class="form-tabs">
                        <div class="tab-buttons">
                            <button type="button" class="tab-btn active" data-tab="team-tab">Team Members</button>
                            <button type="button" class="tab-btn" data-tab="client-tab">Clients</button>
                        </div>
                        
                        <div class="tab-content" id="team-tab">
                            <div class="assignee-grid">
                                <?php foreach ($team_members as $member): ?>
                                    <?php 
                                        // Check if already assigned
                                        $is_assigned = false;
                                        foreach ($assignees as $assignee) {
                                            if ($assignee['user_id'] == $member['user_id']) {
                                                $is_assigned = true;
                                                break;
                                            }
                                        }
                                    ?>
                                    <?php if (!$is_assigned): ?>
                                        <label class="assignee-check-container">
                                            <input type="checkbox" name="assignees[]" value="<?php echo $member['user_id']; ?>">
                                            <span class="checkmark"></span>
                                            <div class="assignee-info">
                                                <div class="assignee-name">
                                                    <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                                </div>
                                                <div class="assignee-role">
                                                    <?php echo htmlspecialchars($member['member_type']); ?>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="tab-content" id="client-tab" style="display: none;">
                            <div class="assignee-grid">
                                <?php foreach ($clients as $client): ?>
                                    <?php 
                                        // Check if already assigned
                                        $is_assigned = false;
                                        foreach ($assignees as $assignee) {
                                            if ($assignee['user_id'] == $client['user_id']) {
                                                $is_assigned = true;
                                                break;
                                            }
                                        }
                                    ?>
                                    <?php if (!$is_assigned): ?>
                                        <label class="assignee-check-container">
                                            <input type="checkbox" name="client_assignees[]" value="<?php echo $client['user_id']; ?>">
                                            <span class="checkmark"></span>
                                            <div class="assignee-info">
                                                <div class="assignee-name">
                                                    <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                                                </div>
                                                <div class="assignee-role">
                                                    Client
                                                </div>
                                            </div>
                                        </label>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_assignees" class="btn submit-btn">Add Assignees</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Update Assignee Status Modal -->
<div class="modal" id="updateStatusModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Assignee Status</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form action="task_detail.php?id=<?php echo $task_id; ?>" method="POST" id="updateStatusForm">
                    <input type="hidden" name="assignment_id" id="assignment_id">
                    <input type="hidden" name="assignee_id" id="assignee_id">
                    
                    <div class="form-group">
                        <label for="assignee_status">Status</label>
                        <select name="assignee_status" id="assignee_status" class="form-control" required>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_assignee_status" class="btn submit-btn">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Hidden forms for actions -->
<form id="statusUpdateForm" action="task_detail.php?id=<?php echo $task_id; ?>" method="POST" style="display: none;">
    <input type="hidden" name="new_status" id="new_task_status">
    <input type="hidden" name="update_task_status" value="1">
</form>

<form id="removeAssigneeForm" action="task_detail.php?id=<?php echo $task_id; ?>" method="POST" style="display: none;">
    <input type="hidden" name="assignment_id" id="remove_assignment_id">
    <input type="hidden" name="assignee_id" id="remove_assignee_id">
    <input type="hidden" name="remove_assignee" value="1">
</form>

<?php
// Helper functions
function formatFileSize($size) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

function formatActivityType($type) {
    switch ($type) {
        case 'created':
            return 'created this task';
        case 'updated':
            return 'updated this task';
        case 'status_changed':
            return 'changed the status';
        case 'assigned':
            return 'assigned';
        case 'unassigned':
            return 'unassigned';
        case 'assignee_status_changed':
            return 'updated status for';
        case 'commented':
            return 'commented';
        case 'attachment_added':
            return 'added an attachment';
        default:
            return $type;
    }
}

function formatActivityTime($timestamp) {
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}
?>

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
    align-items: flex-start;
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

.header-container p a {
    color: var(--primary-color);
    text-decoration: none;
}

.header-container p a:hover {
    text-decoration: underline;
}

.task-actions {
    display: flex;
    gap: 10px;
}

.action-btn {
    padding: 8px 15px;
    border-radius: 4px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    border: none;
}

.start-btn {
    background-color: var(--primary-color);
    color: white;
}

.complete-btn {
    background-color: var(--success-color);
    color: white;
}

.reopen-btn {
    background-color: var(--warning-color);
    color: white;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
}

.primary-btn:hover {
    background-color: #031c56;
}

.alert {
    padding: 12px 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-success {
    background-color: rgba(28, 200, 138, 0.1);
    border-left: 4px solid var(--success-color);
    color: #0e6848;
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.1);
    border-left: 4px solid var(--danger-color);
    color: #a52a21;
}

.task-detail-container {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.task-detail-header {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
}

.task-title-section h2 {
    margin: 0 0 10px;
    color: var(--dark-color);
    font-size: 1.5rem;
}

.task-meta {
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.task-creator {
    font-weight: 500;
}

.task-status-section {
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: flex-end;
}

.status-priority-container {
    display: flex;
    gap: 15px;
}

.status-badge-container,
.priority-badge-container {
    display: flex;
    align-items: center;
    gap: 5px;
}

.status-label,
.priority-label,
.due-date-label {
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-badge.pending {
    background-color: rgba(246, 194, 62, 0.1);
    color: #b88d2e;
}

.status-badge.in_progress {
    background-color: rgba(78, 115, 223, 0.1);
    color: #3a56a0;
}

.status-badge.completed {
    background-color: rgba(28, 200, 138, 0.1);
    color: #0e6848;
}

.status-badge.cancelled {
    background-color: rgba(231, 74, 59, 0.1);
    color: #a52a21;
}

.priority-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}

.priority-badge.high {
    background-color: rgba(231, 74, 59, 0.1);
    color: #a52a21;
}

.priority-badge.normal {
    background-color: rgba(78, 115, 223, 0.1);
    color: #3a56a0;
}

.priority-badge.low {
    background-color: rgba(28, 200, 138, 0.1);
    color: #0e6848;
}

.due-date-container {
    display: flex;
    align-items: center;
    gap: 5px;
}

.due-date {
    font-weight: 500;
}

.due-date.overdue {
    color: var(--danger-color);
}

.overdue-tag,
.today-tag,
.tomorrow-tag {
    font-size: 0.7rem;
    padding: 2px 5px;
    border-radius: 3px;
    margin-left: 5px;
}

.overdue-tag {
    background-color: var(--danger-color);
    color: white;
}

.today-tag {
    background-color: var(--warning-color);
    color: white;
}

.tomorrow-tag {
    background-color: var(--primary-color);
    color: white;
}

.task-description {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
}

.task-description h3 {
    margin: 0 0 10px;
    color: var(--dark-color);
    font-size: 1.2rem;
}

.description-content {
    color: var(--dark-color);
    line-height: 1.6;
}

.task-detail-tabs {
    padding: 0;
}

.tab-buttons {
    display: flex;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--light-color);
}

.tab-btn {
    padding: 15px 20px;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    font-weight: 500;
    color: var(--secondary-color);
}

.tab-btn.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
    background-color: white;
}

.tab-content {
    padding: 20px;
}

.empty-state {
    text-align: center;
    padding: 30px;
    color: var(--secondary-color);
}

/* Assignees Tab Styles */
.assignees-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.assignee-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-radius: 6px;
    background-color: var(--light-color);
    border: 1px solid var(--border-color);
}

.assignee-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.assignee-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
    background-color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
}

.assignee-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.assignee-avatar .initials {
    color: white;
    font-weight: 500;
    font-size: 16px;
}

.assignee-details {
    display: flex;
    flex-direction: column;
}

.assignee-name {
    font-weight: 500;
    color: var(--dark-color);
}

.assignee-type {
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.assignee-status-section {
    display: flex;
    align-items: center;
    gap: 15px;
}

.assignee-actions {
    display: flex;
    gap: 5px;
}

.btn-action {
    width: 30px;
    height: 30px;
    border-radius: 4px;
    border: 1px solid var(--border-color);
    background-color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: var(--secondary-color);
}

.btn-action:hover {
    background-color: var(--light-color);
}

.btn-action.btn-remove {
    color: var(--danger-color);
}

/* Comments Tab Styles */
.add-comment-section {
    margin-bottom: 20px;
}

.comments-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.comment-card {
    padding: 15px;
    border-radius: 6px;
    background-color: var(--light-color);
    border: 1px solid var(--border-color);
}

.comment-header {
    margin-bottom: 10px;
}

.commenter-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.commenter-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
    background-color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
}

.commenter-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.commenter-avatar .initials {
    color: white;
    font-weight: 500;
    font-size: 16px;
}

.commenter-details {
    display: flex;
    flex-direction: column;
}

.commenter-name {
    font-weight: 500;
    color: var(--dark-color);
}

.comment-date {
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.comment-content {
    color: var(--dark-color);
    line-height: 1.6;
}

/* Attachments Tab Styles */
.add-attachment-section {
    margin-bottom: 20px;
}

.file-upload-container {
    position: relative;
    margin-bottom: 15px;
}

.file-input {
    position: absolute;
    width: 0.1px;
    height: 0.1px;
    opacity: 0;
    overflow: hidden;
    z-index: -1;
}

.file-label {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 15px;
    background-color: var(--light-color);
    border: 1px dashed var(--border-color);
    border-radius: 4px;
    cursor: pointer;
    color: var(--secondary-color);
    transition: all 0.3s;
}

.file-label:hover {
    background-color: #f0f2f8;
}

.selected-file {
    margin-top: 10px;
    font-size: 0.9rem;
    color: var(--dark-color);
}

.attachments-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.attachment-card {
    display: flex;
    align-items: center;
    padding: 15px;
    border-radius: 6px;
    background-color: var(--light-color);
    border: 1px solid var(--border-color);
}

.attachment-icon {
    width: 40px;
    height: 40px;
    border-radius: 4px;
    background-color: rgba(4, 33, 103, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.2rem;
    margin-right: 15px;
}

.attachment-details {
    flex: 1;
}

.attachment-name {
    font-weight: 500;
    color: var(--dark-color);
    margin-bottom: 5px;
}

.attachment-meta {
    display: flex;
    gap: 15px;
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.attachment-actions {
    margin-left: 15px;
}

/* Activity Tab Styles */
.activity-timeline {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.activity-item {
    display: flex;
    gap: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background-color: rgba(4, 33, 103, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
}

.activity-content {
    flex: 1;
}

.activity-header {
    margin-bottom: 5px;
}

.activity-user {
    font-weight: 500;
    color: var(--dark-color);
}

.activity-action {
    color: var(--secondary-color);
}

.activity-affected-user {
    font-weight: 500;
    color: var(--dark-color);
}

.activity-description {
    color: var(--dark-color);
    margin-bottom: 5px;
}

.activity-time {
    color: var(--secondary-color);
    font-size: 0.9rem;
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
    max-width: 500px;
    margin: 50px auto;
}

.modal-content {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    margin: 0;
    color: var(--dark-color);
    font-size: 1.2rem;
}

.close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--secondary-color);
}

.modal-body {
    padding: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    color: var(--dark-color);
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 1rem;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
}

.form-tabs {
    margin-bottom: 20px;
}

.assignee-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    max-height: 300px;
    overflow-y: auto;
    padding: 10px 0;
}

.assignee-check-container {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border-radius: 4px;
    border: 1px solid var(--border-color);
    cursor: pointer;
    position: relative;
}

.assignee-check-container input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}

.checkmark {
    position: relative;
    height: 20px;
    width: 20px;
    background-color: white;
    border: 1px solid var(--border-color);
    border-radius: 4px;
}

.assignee-check-container:hover .checkmark {
    background-color: var(--light-color);
}

.assignee-check-container input:checked ~ .checkmark {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.checkmark:after {
    content: "";
    position: absolute;
    display: none;
}

.assignee-check-container input:checked ~ .checkmark:after {
    display: block;
}

.assignee-check-container .checkmark:after {
    left: 7px;
    top: 3px;
    width: 5px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.form-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.cancel-btn {
    background-color: var(--light-color);
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
}

.submit-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
}

/* Responsive Styles */
@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        gap: 15px;
    }
    
    .task-actions {
        width: 100%;
        justify-content: flex-start;
    }
    
    .task-detail-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .task-status-section {
        align-items: flex-start;
        width: 100%;
    }
    
    .status-priority-container {
        width: 100%;
        justify-content: space-between;
    }
    
    .assignee-grid {
        grid-template-columns: 1fr;
    }
    
    .attachment-card {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .attachment-icon {
        margin-bottom: 10px;
    }
    
    .attachment-meta {
        flex-direction: column;
        gap: 5px;
    }
    
    .attachment-actions {
        margin-left: 0;
        margin-top: 10px;
    }
}
</style>

<script>
// Tab switching functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            tabButtons.forEach(btn => btn.classList.remove('active'));
            
            // Hide all tab contents
            tabContents.forEach(content => content.style.display = 'none');
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Show corresponding tab content
            const tabId = this.getAttribute('data-tab');
            document.getElementById(tabId).style.display = 'block';
        });
    });
    
    // Modal functionality
    const addAssigneesBtn = document.getElementById('addAssigneesBtn');
    const addAssigneesModal = document.getElementById('addAssigneesModal');
    const updateStatusModal = document.getElementById('updateStatusModal');
    const closeButtons = document.querySelectorAll('.close, .cancel-btn');
    
    // Open Add Assignees Modal
    if (addAssigneesBtn) {
        addAssigneesBtn.addEventListener('click', function() {
            addAssigneesModal.style.display = 'block';
        });
    }
    
    // Close modals when clicking close button
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            addAssigneesModal.style.display = 'none';
            updateStatusModal.style.display = 'none';
        });
    });
    
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === addAssigneesModal) {
            addAssigneesModal.style.display = 'none';
        }
        if (event.target === updateStatusModal) {
            updateStatusModal.style.display = 'none';
        }
    });
    
    // Modal tab switching
    const modalTabButtons = document.querySelectorAll('.form-tabs .tab-btn');
    const modalTabContents = document.querySelectorAll('.form-tabs .tab-content');
    
    modalTabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            modalTabButtons.forEach(btn => btn.classList.remove('active'));
            
            // Hide all tab contents
            modalTabContents.forEach(content => content.style.display = 'none');
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Show corresponding tab content
            const tabId = this.getAttribute('data-tab');
            document.getElementById(tabId).style.display = 'block';
        });
    });
    
    // File upload handling
    const fileInput = document.getElementById('attachment');
    const selectedFileDiv = document.querySelector('.selected-file');
    
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                const fileName = this.files[0].name;
                selectedFileDiv.textContent = fileName;
            } else {
                selectedFileDiv.textContent = '';
            }
        });
    }
    
    // Set default active tab
    const defaultTabBtn = document.querySelector('.tab-btn.active') || document.querySelector('.tab-btn');
    if (defaultTabBtn) {
        defaultTabBtn.click();
    }
});

// Function to update task status
function updateTaskStatus(status) {
    document.getElementById('new_task_status').value = status;
    document.getElementById('statusUpdateForm').submit();
}

// Function to open update status modal
function openUpdateStatusModal(assignmentId, assigneeId, currentStatus) {
    document.getElementById('assignment_id').value = assignmentId;
    document.getElementById('assignee_id').value = assigneeId;
    
    // Set current status in dropdown
    const statusSelect = document.getElementById('assignee_status');
    for (let i = 0; i < statusSelect.options.length; i++) {
        if (statusSelect.options[i].value === currentStatus) {
            statusSelect.selectedIndex = i;
            break;
        }
    }
    
    document.getElementById('updateStatusModal').style.display = 'block';
}

// Function to confirm removing assignee
function confirmRemoveAssignee(assignmentId, assigneeId) {
    if (confirm('Are you sure you want to remove this assignee?')) {
        document.getElementById('remove_assignment_id').value = assignmentId;
        document.getElementById('remove_assignee_id').value = assigneeId;
        document.getElementById('removeAssigneeForm').submit();
    }
}
</script>
<?php
// End output buffering and send content to browser
ob_end_flush();
?>
<?php include 'includes/footer.php'; ?>