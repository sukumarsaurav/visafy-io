<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Edit Task";
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

// Get current assignees for the task
$assignee_query = "SELECT ta.id as assignment_id, ta.status as assignee_status, 
                  u.id as user_id, u.first_name, u.last_name, u.email, u.profile_picture, u.user_type
                  FROM task_assignments ta
                  JOIN users u ON ta.assignee_id = u.id
                  WHERE ta.task_id = ? AND ta.deleted_at IS NULL
                  ORDER BY u.first_name, u.last_name";

$assignee_stmt = $conn->prepare($assignee_query);
$assignee_stmt->bind_param("i", $task_id);
$assignee_stmt->execute();
$assignee_result = $assignee_stmt->get_result();
$current_assignees = [];

while ($row = $assignee_result->fetch_assoc()) {
    $current_assignees[$row['user_id']] = $row;
}
$assignee_stmt->close();

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

// Handle task update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
    $name = isset($_POST['task_name']) ? trim($_POST['task_name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $priority = isset($_POST['priority']) ? $_POST['priority'] : 'normal';
    $status = isset($_POST['status']) ? $_POST['status'] : 'pending';
    $due_date = isset($_POST['due_date']) && !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $team_assignees = isset($_POST['team_assignees']) ? $_POST['team_assignees'] : [];
    $client_assignees = isset($_POST['client_assignees']) ? $_POST['client_assignees'] : [];
    
    // Validate inputs
    $errors = [];
    if (empty($name)) {
        $errors[] = "Task name is required";
    }
    
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update task record
            $task_update = "UPDATE tasks SET name = ?, description = ?, priority = ?, status = ?, due_date = ? WHERE id = ?";
            $stmt = $conn->prepare($task_update);
            $stmt->bind_param('ssssi', $name, $description, $priority, $status, $due_date, $task_id);
            $stmt->execute();
            $stmt->close();
            
            // Create activity log for task update
            $log_insert = "INSERT INTO task_activity_logs (task_id, user_id, activity_type, description) 
                         VALUES (?, ?, 'updated', 'Task details updated')";
            $log_stmt = $conn->prepare($log_insert);
            $log_stmt->bind_param('ii', $task_id, $user_id);
            $log_stmt->execute();
            $log_stmt->close();
            
            // Combine all assignees
            $all_assignees = array_merge($team_assignees, $client_assignees);
            
            // Handle assignee changes
            if (!empty($all_assignees)) {
                // First, mark all current assignments as deleted if they're not in the new list
                $delete_query = "UPDATE task_assignments SET deleted_at = NOW() 
                               WHERE task_id = ? AND assignee_id NOT IN (" . implode(',', array_fill(0, count($all_assignees), '?')) . ")
                               AND deleted_at IS NULL";
                
                $delete_params = array_merge([$task_id], $all_assignees);
                $delete_types = str_repeat('i', count($delete_params));
                
                $delete_stmt = $conn->prepare($delete_query);
                $delete_stmt->bind_param($delete_types, ...$delete_params);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                // Then, add new assignments for assignees that don't already exist
                foreach ($all_assignees as $assignee_id) {
                    // Check if assignment already exists
                    $check_query = "SELECT id FROM task_assignments 
                                  WHERE task_id = ? AND assignee_id = ? AND deleted_at IS NULL";
                    $check_stmt = $conn->prepare($check_query);
                    $check_stmt->bind_param('ii', $task_id, $assignee_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows === 0) {
                        // Insert new assignment
                        $insert_query = "INSERT INTO task_assignments (task_id, assignee_id, assigned_by) 
                                       VALUES (?, ?, ?)";
                        $insert_stmt = $conn->prepare($insert_query);
                        $insert_stmt->bind_param('iii', $task_id, $assignee_id, $user_id);
                        $insert_stmt->execute();
                        $insert_stmt->close();
                        
                        // Create activity log for assignment
                        $log_insert = "INSERT INTO task_activity_logs (task_id, user_id, affected_user_id, activity_type, description) 
                                     VALUES (?, ?, ?, 'assigned', 'User assigned to task')";
                        $log_stmt = $conn->prepare($log_insert);
                        $log_stmt->bind_param('iii', $task_id, $user_id, $assignee_id);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                    $check_stmt->close();
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Task updated successfully";
            header("Location: task_detail.php?id=$task_id&success=task_updated");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error updating task: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Edit Task</h1>
            <p><a href="task_detail.php?id=<?php echo $task_id; ?>">Back to Task Details</a></p>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <div class="edit-task-container">
        <form action="edit_task.php?id=<?php echo $task_id; ?>" method="POST" id="editTaskForm">
            <div class="form-section">
                <h2>Task Details</h2>
                
                <div class="form-group">
                    <label for="task_name">Task Name*</label>
                    <input type="text" name="task_name" id="task_name" class="form-control" 
                           value="<?php echo htmlspecialchars($task['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" class="form-control" rows="4"><?php echo htmlspecialchars($task['description']); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="priority">Priority*</label>
                        <select name="priority" id="priority" class="form-control" required>
                            <option value="low" <?php echo ($task['priority'] === 'low') ? 'selected' : ''; ?>>Low</option>
                            <option value="normal" <?php echo ($task['priority'] === 'normal') ? 'selected' : ''; ?>>Normal</option>
                            <option value="high" <?php echo ($task['priority'] === 'high') ? 'selected' : ''; ?>>High</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status*</label>
                        <select name="status" id="status" class="form-control" required>
                            <option value="pending" <?php echo ($task['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo ($task['status'] === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo ($task['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo ($task['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="due_date">Due Date</label>
                        <input type="date" name="due_date" id="due_date" class="form-control" 
                               value="<?php echo !empty($task['due_date']) ? date('Y-m-d', strtotime($task['due_date'])) : ''; ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h2>Assignees</h2>
                
                <div class="form-tabs">
                    <div class="tab-buttons">
                        <button type="button" class="tab-btn active" data-tab="team-tab">Team Members</button>
                        <button type="button" class="tab-btn" data-tab="client-tab">Clients</button>
                    </div>
                    
                    <div class="tab-content" id="team-tab">
                        <div class="assignee-grid">
                            <?php foreach ($team_members as $member): ?>
                                <label class="assignee-check-container">
                                    <input type="checkbox" name="team_assignees[]" value="<?php echo $member['user_id']; ?>"
                                           <?php echo isset($current_assignees[$member['user_id']]) ? 'checked' : ''; ?>>
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
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="tab-content" id="client-tab" style="display: none;">
                        <div class="assignee-grid">
                            <?php foreach ($clients as $client): ?>
                                <label class="assignee-check-container">
                                    <input type="checkbox" name="client_assignees[]" value="<?php echo $client['user_id']; ?>"
                                           <?php echo isset($current_assignees[$client['user_id']]) ? 'checked' : ''; ?>>
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
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-buttons">
                <a href="task_detail.php?id=<?php echo $task_id; ?>" class="btn cancel-btn">Cancel</a>
                <button type="submit" name="update_task" class="btn submit-btn">Update Task</button>
            </div>
        </form>
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

.edit-task-container {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    padding: 20px;
}

.form-section {
    margin-bottom: 30px;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 20px;
}

.form-section:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.form-section h2 {
    margin: 0 0 20px 0;
    color: var(--primary-color);
    font-size: 1.3rem;
}

.form-group {
    margin-bottom: 20px;
}

.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.form-row .form-group {
    flex: 1;
    margin-bottom: 0;
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
    font-size: 1rem;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

.form-tabs {
    margin-bottom: 20px;
}

.tab-buttons {
    display: flex;
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 15px;
}

.tab-btn {
    padding: 10px 15px;
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
    padding-left: 35px;
}

.assignee-check-container input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}

.checkmark {
    position: absolute;
    left: 10px;
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

.assignee-info {
    flex: 1;
}

.assignee-name {
    font-weight: 500;
    color: var(--dark-color);
}

.assignee-role {
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.form-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.cancel-btn {
    background-color: var(--light-color);
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.cancel-btn:hover {
    background-color: #f0f2f8;
    text-decoration: none;
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
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .form-row .form-group {
        margin-bottom: 20px;
    }
    
    .assignee-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching functionality
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
    
    // Status change handling
    const statusSelect = document.getElementById('status');
    const dueDateInput = document.getElementById('due_date');
    
    statusSelect.addEventListener('change', function() {
        if (this.value === 'completed') {
            // If status is completed and no due date is set, suggest setting today as due date
            if (!dueDateInput.value) {
                if (confirm('Set due date to today for completed task?')) {
                    const today = new Date();
                    const formattedDate = today.toISOString().split('T')[0];
                    dueDateInput.value = formattedDate;
                }
            }
        }
    });
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php include 'includes/footer.php'; ?>
