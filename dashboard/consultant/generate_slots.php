<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Get service ID and date range from POST data
$service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : null;
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : null;

if (!$service_id || !$start_date || !$end_date) {
    die(json_encode(['error' => 'Missing required parameters']));
}

// Get consultant ID and organization ID from session
$consultant_id = isset($_SESSION['id']) ? $_SESSION['id'] : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
$organization_id = isset($user['organization_id']) ? $user['organization_id'] : null;

// Verify organization ID is set
if (!$organization_id) {
    die(json_encode(['error' => 'Organization ID not set']));
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
    die(json_encode(['error' => 'Service not found or you don\'t have permission to manage it']));
}

if (!$service['is_bookable']) {
    die(json_encode(['error' => 'This service is not bookable']));
}

// Get booking settings
$query = "SELECT * FROM service_booking_settings WHERE visa_service_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $service_id);
$stmt->execute();
$booking_settings = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking_settings) {
    die(json_encode(['error' => 'Booking settings not found for this service']));
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
          WHERE visa_service_id = ? AND exception_date BETWEEN ? AND ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('iss', $service_id, $start_date, $end_date);
$stmt->execute();
$exceptions_result = $stmt->get_result();
$exceptions = [];

if ($exceptions_result && $exceptions_result->num_rows > 0) {
    while ($row = $exceptions_result->fetch_assoc()) {
        $exceptions[$row['exception_date']] = $row;
    }
}
$stmt->close();

// Get existing bookings
$query = "SELECT booking_datetime, duration_minutes 
          FROM bookings b
          JOIN service_consultation_modes scm ON b.consultation_mode_id = scm.consultation_mode_id
          WHERE b.visa_service_id = ? AND b.booking_datetime BETWEEN ? AND ?
          AND b.deleted_at IS NULL";
$stmt = $conn->prepare($query);
$stmt->bind_param('iss', $service_id, $start_date, $end_date);
$stmt->execute();
$bookings_result = $stmt->get_result();
$bookings = [];

if ($bookings_result && $bookings_result->num_rows > 0) {
    while ($row = $bookings_result->fetch_assoc()) {
        $bookings[] = $row;
    }
}
$stmt->close();

try {
    $conn->begin_transaction();

    // Delete existing slots in the date range
    $query = "DELETE FROM service_time_slots 
              WHERE visa_service_id = ? AND slot_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iss', $service_id, $start_date, $end_date);
    $stmt->execute();
    $stmt->close();

    // Generate new slots
    $current_date = new DateTime($start_date);
    $end_datetime = new DateTime($end_date);
    $end_datetime->setTime(23, 59, 59);

    while ($current_date <= $end_datetime) {
        $date_str = $current_date->format('Y-m-d');
        $day_of_week = $current_date->format('N'); // 1 (Monday) to 7 (Sunday)

        // Check if there's an exception for this date
        if (isset($exceptions[$date_str])) {
            $exception = $exceptions[$date_str];
            if ($exception['is_available']) {
                // Generate slots for the exception date using default working hours
                generateSlotsForDate($conn, $service_id, $date_str, $working_hours[$day_of_week], 
                                   $booking_settings, $bookings);
            }
        } else {
            // Check if it's a working day
            if (isset($working_hours[$day_of_week]) && $working_hours[$day_of_week]['is_working_day']) {
                generateSlotsForDate($conn, $service_id, $date_str, $working_hours[$day_of_week], 
                                   $booking_settings, $bookings);
            }
        }

        $current_date->modify('+1 day');
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => 'Failed to generate slots: ' . $e->getMessage()]);
}

// Helper function to generate slots for a specific date
function generateSlotsForDate($conn, $service_id, $date, $working_hours, $booking_settings, $bookings) {
    // Get consultation modes for the service
    $query = "SELECT scm.*, cm.mode_name 
              FROM service_consultation_modes scm
              JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
              WHERE scm.visa_service_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $service_id);
    $stmt->execute();
    $modes_result = $stmt->get_result();
    $modes = [];

    if ($modes_result && $modes_result->num_rows > 0) {
        while ($row = $modes_result->fetch_assoc()) {
            $modes[] = $row;
        }
    }
    $stmt->close();

    if (empty($modes)) {
        return;
    }

    // Generate slots for each consultation mode
    foreach ($modes as $mode) {
        $start_time = new DateTime($date . ' ' . $working_hours['start_time']);
        $end_time = new DateTime($date . ' ' . $working_hours['end_time']);
        $duration = $mode['duration_minutes'];
        $buffer_before = $booking_settings['buffer_before_minutes'];
        $buffer_after = $booking_settings['buffer_after_minutes'];

        // Check for break time
        $break_start = null;
        $break_end = null;
        if ($working_hours['break_start_time'] && $working_hours['break_end_time']) {
            $break_start = new DateTime($date . ' ' . $working_hours['break_start_time']);
            $break_end = new DateTime($date . ' ' . $working_hours['break_end_time']);
        }

        while ($start_time < $end_time) {
            $slot_end = clone $start_time;
            $slot_end->modify("+{$duration} minutes");

            // Skip if slot overlaps with break time
            if ($break_start && $break_end) {
                if ($start_time < $break_end && $slot_end > $break_start) {
                    $start_time = clone $break_end;
                    continue;
                }
            }

            // Skip if slot overlaps with existing booking
            $is_available = true;
            foreach ($bookings as $booking) {
                $booking_start = new DateTime($booking['booking_datetime']);
                $booking_end = clone $booking_start;
                $booking_end->modify("+{$booking['duration_minutes']} minutes");

                if ($start_time < $booking_end && $slot_end > $booking_start) {
                    $is_available = false;
                    break;
                }
            }

            if ($is_available) {
                // Insert the slot
                $query = "INSERT INTO service_time_slots 
                         (visa_service_id, consultation_mode_id, slot_date, start_time, end_time, 
                          is_available, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('iisss', 
                    $service_id,
                    $mode['consultation_mode_id'],
                    $date,
                    $start_time->format('H:i:s'),
                    $slot_end->format('H:i:s')
                );
                $stmt->execute();
                $stmt->close();
            }

            // Move to next slot
            $start_time->modify("+{$duration} minutes");
            if ($buffer_before > 0) {
                $start_time->modify("+{$buffer_before} minutes");
            }
            if ($buffer_after > 0) {
                $start_time->modify("+{$buffer_after} minutes");
            }
        }
    }
}

// End output buffering and send content to browser
ob_end_flush();
?>
