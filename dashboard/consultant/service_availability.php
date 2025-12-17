<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Service Availability";
$page_specific_css = "assets/css/services.css";
require_once 'includes/header.php';

// Create consultant_working_hours table if it doesn't exist
$create_table_query = "CREATE TABLE IF NOT EXISTS consultant_working_hours (
    id INT PRIMARY KEY AUTO_INCREMENT,
    consultant_id INT NOT NULL,
    day_of_week TINYINT NOT NULL,
    is_working_day BOOLEAN DEFAULT true,
    start_time TIME,
    end_time TIME,
    break_start_time TIME,
    break_end_time TIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (consultant_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_consultant_day (consultant_id, day_of_week)
)";

$conn->query($create_table_query);

// Create service_availability_exceptions table if it doesn't exist
$create_exceptions_table_query = "CREATE TABLE IF NOT EXISTS service_availability_exceptions (
    exception_id INT PRIMARY KEY AUTO_INCREMENT,
    visa_service_id INT NOT NULL,
    exception_date DATE NOT NULL,
    is_available BOOLEAN DEFAULT true,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (visa_service_id) REFERENCES visa_services(visa_service_id) ON DELETE CASCADE,
    UNIQUE KEY unique_service_date (visa_service_id, exception_date)
)";

$conn->query($create_exceptions_table_query);

// Get service ID from URL
$service_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$service_id) {
    die("Service ID not provided");
}

// Get consultant ID and organization ID from session
$consultant_id = isset($_SESSION['id']) ? $_SESSION['id'] : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
$organization_id = isset($user['organization_id']) ? $user['organization_id'] : null;

// Verify organization ID is set
if (!$organization_id) {
    die("Organization ID not set. Please log in again.");
}

// Get service details
$query = "SELECT vs.*, v.visa_type, c.country_name, st.service_name
          FROM visa_services vs
          JOIN visas v ON vs.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          JOIN service_types st ON vs.service_type_id = st.service_type_id
          WHERE vs.visa_service_id = ? AND vs.organization_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $service_id, $organization_id);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$service) {
    die("Service not found or you don't have permission to manage it");
}

if (!$service['is_bookable']) {
    die("This service is not bookable");
}

// Get booking settings
$query = "SELECT * FROM service_booking_settings WHERE visa_service_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $service_id);
$stmt->execute();
$booking_settings = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking_settings) {
    die("Booking settings not found for this service");
}

// Get consultant's working hours
$query = "SELECT * FROM consultant_working_hours WHERE consultant_id = ? ORDER BY day_of_week";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $consultant_id);
$stmt->execute();
$working_hours_result = $stmt->get_result();
$working_hours = [];

if ($working_hours_result && $working_hours_result->num_rows > 0) {
    while ($row = $working_hours_result->fetch_assoc()) {
        $working_hours[$row['day_of_week']] = $row;
    }
}
$stmt->close();

// Get service availability exceptions
$query = "SELECT * FROM service_availability_exceptions 
          WHERE visa_service_id = ? AND exception_date >= CURDATE()
          ORDER BY exception_date";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $service_id);
$stmt->execute();
$exceptions_result = $stmt->get_result();
$exceptions = [];

if ($exceptions_result && $exceptions_result->num_rows > 0) {
    while ($row = $exceptions_result->fetch_assoc()) {
        $exceptions[] = $row;
    }
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_exception':
                    $query = "INSERT INTO service_availability_exceptions 
                             (visa_service_id, exception_date, is_available, reason, created_at, updated_at)
                             VALUES (?, ?, ?, ?, NOW(), NOW())";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('isis', 
                        $service_id,
                        $_POST['exception_date'],
                        $_POST['is_available'],
                        $_POST['reason']
                    );
                    $stmt->execute();
                    $stmt->close();
                    break;

                case 'delete_exception':
                    $query = "DELETE FROM service_availability_exceptions 
                             WHERE exception_id = ? AND visa_service_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('ii', 
                        $_POST['exception_id'],
                        $service_id
                    );
                    $stmt->execute();
                    $stmt->close();
                    break;

                case 'generate_slots':
                    // Call the generate_slots.php script to generate slots
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/generate_slots.php");
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, [
                        'service_id' => $service_id,
                        'start_date' => $_POST['start_date'],
                        'end_date' => $_POST['end_date']
                    ]);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $response = curl_exec($ch);
                    curl_close($ch);

                    if ($response === false) {
                        throw new Exception("Failed to generate slots");
                    }
                    break;
            }
        }

        $conn->commit();
        header("Location: service_availability.php?id=" . $service_id . "&success=1");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error = "An error occurred while updating availability. Please try again.";
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Service Availability</h1>
            <p>Manage service availability and time slots</p>
        </div>
        <div class="header-actions">
            <a href="service_details.php?id=<?php echo $service_id; ?>" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Details
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        Changes saved successfully
    </div>
    <?php endif; ?>

    <div class="availability-container">
        <!-- Service Information -->
        <div class="info-section">
            <h2>Service Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <label>Country</label>
                    <span><?php echo htmlspecialchars($service['country_name']); ?></span>
                </div>
                <div class="info-item">
                    <label>Visa Type</label>
                    <span><?php echo htmlspecialchars($service['visa_type']); ?></span>
                </div>
                <div class="info-item">
                    <label>Service Type</label>
                    <span><?php echo htmlspecialchars($service['service_name']); ?></span>
                </div>
            </div>
        </div>

        <!-- Working Hours -->
        <div class="info-section">
            <h2>Working Hours</h2>
            <div class="working-hours-grid">
                <?php
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                foreach ($days as $index => $day):
                    $day_data = $working_hours[$index + 1] ?? null;
                ?>
                <div class="working-hour-item">
                    <div class="day-header">
                        <h4><?php echo $day; ?></h4>
                        <span class="status-badge <?php echo ($day_data && $day_data['is_working_day']) ? 'active' : 'inactive'; ?>">
                            <?php echo ($day_data && $day_data['is_working_day']) ? 'Working Day' : 'Off Day'; ?>
                        </span>
                    </div>
                    <?php if ($day_data && $day_data['is_working_day']): ?>
                    <div class="time-slots">
                        <div class="time-slot">
                            <i class="fas fa-clock"></i>
                            <span><?php echo date('h:i A', strtotime($day_data['start_time'])); ?> - 
                                  <?php echo date('h:i A', strtotime($day_data['end_time'])); ?></span>
                        </div>
                        <?php if ($day_data['break_start_time'] && $day_data['break_end_time']): ?>
                        <div class="time-slot break">
                            <i class="fas fa-coffee"></i>
                            <span>Break: <?php echo date('h:i A', strtotime($day_data['break_start_time'])); ?> - 
                                      <?php echo date('h:i A', strtotime($day_data['break_end_time'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Generate Slots -->
        <div class="info-section">
            <h2>Generate Time Slots</h2>
            <form method="POST" class="generate-slots-form">
                <input type="hidden" name="action" value="generate_slots">
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" required 
                           min="<?php echo date('Y-m-d'); ?>"
                           max="<?php echo date('Y-m-d', strtotime('+' . $booking_settings['max_advance_days'] . ' days')); ?>">
                </div>
                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" required
                           min="<?php echo date('Y-m-d'); ?>"
                           max="<?php echo date('Y-m-d', strtotime('+' . $booking_settings['max_advance_days'] . ' days')); ?>">
                </div>
                <button type="submit" class="btn primary-btn">
                    <i class="fas fa-calendar-plus"></i> Generate Slots
                </button>
            </form>
        </div>

        <!-- Availability Exceptions -->
        <div class="info-section">
            <h2>Availability Exceptions</h2>
            <form method="POST" class="add-exception-form">
                <input type="hidden" name="action" value="add_exception">
                <div class="form-group">
                    <label for="exception_date">Date</label>
                    <input type="date" id="exception_date" name="exception_date" required
                           min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Availability</label>
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="radio" name="is_available" value="1" checked>
                            Available
                        </label>
                        <label class="checkbox-label">
                            <input type="radio" name="is_available" value="0">
                            Not Available
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="reason">Reason</label>
                    <textarea id="reason" name="reason" rows="2" required></textarea>
                </div>
                <button type="submit" class="btn primary-btn">
                    <i class="fas fa-plus"></i> Add Exception
                </button>
            </form>

            <?php if (!empty($exceptions)): ?>
            <div class="exceptions-list">
                <?php foreach ($exceptions as $exception): ?>
                <div class="exception-item">
                    <div class="exception-header">
                        <div class="exception-date">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo date('F d, Y', strtotime($exception['exception_date'])); ?></span>
                        </div>
                        <span class="status-badge <?php echo $exception['is_available'] ? 'active' : 'inactive'; ?>">
                            <?php echo $exception['is_available'] ? 'Available' : 'Not Available'; ?>
                        </span>
                    </div>
                    <div class="exception-content">
                        <p><?php echo nl2br(htmlspecialchars($exception['reason'])); ?></p>
                    </div>
                    <form method="POST" class="delete-exception-form">
                        <input type="hidden" name="action" value="delete_exception">
                        <input type="hidden" name="exception_id" value="<?php echo $exception['exception_id']; ?>">
                        <button type="submit" class="btn danger-btn">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-muted">No exceptions added yet</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.availability-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.info-section {
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid var(--border-color);
}

.info-section:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.info-section h2 {
    color: var(--primary-color);
    font-size: 1.4rem;
    margin-bottom: 20px;
}

.working-hours-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.working-hour-item {
    background-color: var(--light-color);
    padding: 15px;
    border-radius: 4px;
}

.day-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.day-header h4 {
    margin: 0;
    color: var(--primary-color);
}

.time-slots {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.time-slot {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--dark-color);
}

.time-slot.break {
    color: var(--secondary-color);
}

.generate-slots-form,
.add-exception-form {
    background-color: var(--light-color);
    padding: 20px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    color: var(--secondary-color);
    margin-bottom: 5px;
}

.form-group input[type="date"],
.form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 1rem;
}

.form-group textarea {
    resize: vertical;
}

.checkbox-group {
    display: flex;
    gap: 20px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.exceptions-list {
    display: grid;
    gap: 15px;
    margin-top: 20px;
}

.exception-item {
    background-color: var(--light-color);
    padding: 15px;
    border-radius: 4px;
}

.exception-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.exception-date {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--primary-color);
}

.exception-content {
    color: var(--dark-color);
    margin-bottom: 15px;
}

.delete-exception-form {
    display: flex;
    justify-content: flex-end;
}

.alert {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
    border: 1px solid var(--danger-color);
}

.alert-success {
    background-color: rgba(40, 167, 69, 0.1);
    color: var(--success-color);
    border: 1px solid var(--success-color);
}

.text-muted {
    color: var(--secondary-color);
    font-style: italic;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set max date for end date based on start date
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');

    startDateInput.addEventListener('change', function() {
        endDateInput.min = this.value;
    });

    // Confirm before deleting exception
    const deleteForms = document.querySelectorAll('.delete-exception-form');
    deleteForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to delete this exception?')) {
                e.preventDefault();
            }
        });
    });
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>
<?php require_once 'includes/footer.php'; ?>
