<?php
// Include session management
require_once "includes/session.php";

// Database connection
require_once "config/db_connect.php";

// Get consultant ID from URL
$consultant_id = isset($_GET['consultant_id']) ? (int)$_GET['consultant_id'] : 0;

// Fetch consultant data
$query = "SELECT 
    u.id,
    u.first_name,
    u.last_name,
    u.email,
    u.phone,
    c.company_name,
    cp.profile_image,
    cp.banner_image,
    o.name as organization_name,
    o.id as organization_id
FROM users u
JOIN consultants c ON u.id = c.user_id
LEFT JOIN consultant_profiles cp ON u.id = cp.consultant_id
JOIN organizations o ON u.organization_id = o.id
WHERE u.id = ? AND u.user_type = 'consultant' AND u.status = 'active'";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $consultant_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: /404.php");
    exit();
}

$consultant = $result->fetch_assoc();

// Fetch available services for this consultant
$services_query = "SELECT 
    vs.visa_service_id,
    v.visa_type,
    st.service_name,
    vs.base_price,
    vs.description,
    GROUP_CONCAT(DISTINCT cm.mode_name) as consultation_modes
FROM visa_services vs
JOIN visas v ON vs.visa_id = v.visa_id
JOIN service_types st ON vs.service_type_id = st.service_type_id
LEFT JOIN service_consultation_modes scm ON vs.visa_service_id = scm.visa_service_id
LEFT JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
WHERE vs.consultant_id = ? AND vs.is_active = 1
GROUP BY vs.visa_service_id";

$stmt = $conn->prepare($services_query);
$stmt->bind_param("i", $consultant_id);
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$page_title = "Book Consultation with " . $consultant['first_name'] . " " . $consultant['last_name'];
include('includes/header.php');
?>

<!-- Booking Form Section -->
<section class="booking-section">
    <div class="container">
        <div class="booking-grid">
            <!-- Consultant Info -->
            <div class="consultant-info">
                <div class="profile-header">
                    <img src="<?php echo !empty($consultant['profile_image']) ? '/uploads/' . $consultant['profile_image'] : '/assets/images/default-profile.svg'; ?>" 
                         alt="<?php echo htmlspecialchars($consultant['first_name'] . ' ' . $consultant['last_name']); ?>"
                         class="profile-image">
                    <div class="profile-details">
                        <h2><?php echo htmlspecialchars($consultant['first_name'] . ' ' . $consultant['last_name']); ?></h2>
                        <p class="company"><?php echo htmlspecialchars($consultant['company_name']); ?></p>
                        <p class="organization"><?php echo htmlspecialchars($consultant['organization_name']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Booking Form -->
            <div class="booking-form">
                <form id="bookingForm" action="process-booking.php" method="POST">
                    <input type="hidden" name="consultant_id" value="<?php echo $consultant_id; ?>">
                    <input type="hidden" name="organization_id" value="<?php echo $consultant['organization_id']; ?>">

                    <!-- Service Selection -->
                    <div class="form-group">
                        <label for="service">Select Service</label>
                        <select name="visa_service_id" id="service" required>
                            <option value="">Choose a service...</option>
                            <?php foreach ($services as $service): ?>
                            <option value="<?php echo $service['visa_service_id']; ?>" 
                                    data-price="<?php echo $service['base_price']; ?>"
                                    data-modes="<?php echo htmlspecialchars($service['consultation_modes']); ?>">
                                <?php echo htmlspecialchars($service['visa_type'] . ' - ' . $service['service_name']); ?>
                                (<?php echo number_format($service['base_price'], 2); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Consultation Mode -->
                    <div class="form-group">
                        <label for="consultation_mode">Consultation Mode</label>
                        <select name="consultation_mode" id="consultation_mode" required>
                            <option value="">Select mode...</option>
                        </select>
                    </div>

                    <!-- Date Selection -->
                    <div class="form-group">
                        <label for="booking_date">Select Date</label>
                        <input type="date" id="booking_date" name="booking_date" required 
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <!-- Time Selection -->
                    <div class="form-group">
                        <label for="booking_time">Select Time</label>
                        <select name="booking_time" id="booking_time" required>
                            <option value="">Select time...</option>
                        </select>
                    </div>

                    <!-- Duration -->
                    <div class="form-group">
                        <label for="duration">Duration</label>
                        <select name="duration_minutes" id="duration" required>
                            <option value="30">30 minutes</option>
                            <option value="60" selected>1 hour</option>
                            <option value="90">1.5 hours</option>
                            <option value="120">2 hours</option>
                        </select>
                    </div>

                    <!-- Notes -->
                    <div class="form-group">
                        <label for="notes">Additional Notes</label>
                        <textarea name="client_notes" id="notes" rows="4" 
                                  placeholder="Any specific questions or topics you'd like to discuss?"></textarea>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary">Book Consultation</button>
                </form>
            </div>
        </div>
    </div>
</section>



<script>
document.addEventListener('DOMContentLoaded', function() {
    const serviceSelect = document.getElementById('service');
    const modeSelect = document.getElementById('consultation_mode');
    const dateInput = document.getElementById('booking_date');
    const timeSelect = document.getElementById('booking_time');

    // Update consultation modes when service is selected
    serviceSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const modes = selectedOption.dataset.modes.split(',');
        
        modeSelect.innerHTML = '<option value="">Select mode...</option>';
        modes.forEach(mode => {
            const option = document.createElement('option');
            option.value = mode.trim();
            option.textContent = mode.trim();
            modeSelect.appendChild(option);
        });
    });

    // Fetch available time slots when date is selected
    dateInput.addEventListener('change', function() {
        const date = this.value;
        const serviceId = serviceSelect.value;
        const consultantId = <?php echo $consultant_id; ?>;

        if (date && serviceId) {
            // Fetch available time slots from the server
            fetch(`get-available-slots.php?date=${date}&service_id=${serviceId}&consultant_id=${consultantId}`)
                .then(response => response.json())
                .then(slots => {
                    timeSelect.innerHTML = '<option value="">Select time...</option>';
                    slots.forEach(slot => {
                        const option = document.createElement('option');
                        option.value = slot.time;
                        option.textContent = slot.time;
                        timeSelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Error fetching time slots:', error));
        }
    });

    // Form submission handling
    document.getElementById('bookingForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate form
        if (!this.checkValidity()) {
            e.stopPropagation();
            this.classList.add('was-validated');
            return;
        }

        // Submit form
        const formData = new FormData(this);
        fetch('process-booking.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = `booking-confirmation.php?reference=${data.reference}`;
            } else {
                alert(data.message || 'An error occurred while processing your booking.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing your booking.');
        });
    });
});
</script>

<?php include('includes/footer.php'); ?>
