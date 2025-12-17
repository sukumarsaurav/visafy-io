<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent PHP from outputting HTML errors
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set content type to JSON for all responses
header('Content-Type: application/json');

// Debug: Log all POST data
file_put_contents('booking_debug.log', date('Y-m-d H:i:s') . " - POST Data: " . print_r($_POST, true) . "\n", FILE_APPEND);

try {
    // Include database connection
    require_once 'config/db_connect.php';
    
    // Redirect if not logged in
    if (!isset($_SESSION['id'])) {
        file_put_contents('booking_debug.log', date('Y-m-d H:i:s') . " - Error: User not logged in\n", FILE_APPEND);
        echo json_encode([
            'success' => false,
            'message' => 'User not logged in'
        ]);
        exit;
    }

    // Check if form was submitted
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        file_put_contents('booking_debug.log', date('Y-m-d H:i:s') . " - Error: Not a POST request\n", FILE_APPEND);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request method'
        ]);
        exit;
    }

    // Validate required fields
    $required_fields = [
        'consultant_id', 
        'organization_id', 
        'visa_service_id', 
        'consultation_mode_id', 
        'booking_date', 
        'booking_time'
    ];

    $missing_fields = [];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {
        $error_message = "Missing required fields: " . implode(', ', $missing_fields);
        file_put_contents('booking_debug.log', date('Y-m-d H:i:s') . " - Error: {$error_message}\n", FILE_APPEND);
        
        echo json_encode([
            'success' => false,
            'message' => $error_message
        ]);
        exit;
    }

    // Validate terms agreement
    if (!isset($_POST['terms']) || $_POST['terms'] !== 'on') {
        file_put_contents('booking_debug.log', date('Y-m-d H:i:s') . " - Error: Terms not agreed\n", FILE_APPEND);
        echo json_encode([
            'success' => false,
            'message' => 'You must agree to the terms and conditions'
        ]);
        exit;
    }

    // Get form data
    $consultant_id = intval($_POST['consultant_id']);
    $user_id = $_SESSION['id']; // Use logged in user's ID from session
    $organization_id = intval($_POST['organization_id']);
    $visa_service_id = intval($_POST['visa_service_id']);
    $consultation_mode_id = intval($_POST['consultation_mode_id']);

    // Combine date and time
    $booking_date = $_POST['booking_date'];
    $booking_time = $_POST['booking_time'];
    $booking_datetime = $booking_date . ' ' . $booking_time;

    // Optional fields
    $client_notes = isset($_POST['client_notes']) ? trim($_POST['client_notes']) : '';
    $language_preference = isset($_POST['language_preference']) ? trim($_POST['language_preference']) : 'English';
    $duration_minutes = isset($_POST['duration_minutes']) ? intval($_POST['duration_minutes']) : 60; // Default to 1 hour

    file_put_contents('booking_debug.log', date('Y-m-d H:i:s') . " - Processing booking for consultant_id: {$consultant_id}, user_id: {$user_id}\n", FILE_APPEND);

    // Check if service consultation mode exists - Ensure consistent collation
    $scm_query = "SELECT service_consultation_id FROM service_consultation_modes 
                  WHERE visa_service_id = ? AND consultation_mode_id = ? AND is_available = 1";
    $scm_stmt = $conn->prepare($scm_query);
    $scm_stmt->bind_param('ii', $visa_service_id, $consultation_mode_id);
    $scm_stmt->execute();
    $scm_result = $scm_stmt->get_result();

    if ($scm_result->num_rows === 0) {
        file_put_contents('booking_debug.log', date('Y-m-d H:i:s') . " - Error: Service consultation mode not available\n", FILE_APPEND);
        $scm_stmt->close();
        echo json_encode([
            'success' => false,
            'message' => 'Selected consultation mode is not available for this service'
        ]);
        exit;
    }

    $scm_row = $scm_result->fetch_assoc();
    $service_consultation_id = $scm_row['service_consultation_id'];
    $scm_stmt->close();

    // Get visa service details to calculate duration - Ensure consistent collation
    $service_query = "SELECT base_price FROM visa_services WHERE visa_service_id = ?";
    $service_stmt = $conn->prepare($service_query);
    $service_stmt->bind_param('i', $visa_service_id);
    $service_stmt->execute();
    $service_result = $service_stmt->get_result();

    if ($service_result->num_rows === 0) {
        file_put_contents('booking_debug.log', date('Y-m-d H:i:s') . " - Error: Service not available\n", FILE_APPEND);
        $service_stmt->close();
        echo json_encode([
            'success' => false,
            'message' => 'Selected service is not available'
        ]);
        exit;
    }

    $service_row = $service_result->fetch_assoc();
    $base_price = $service_row['base_price'];
    $service_stmt->close();

    // Get consultation mode fee - Ensure consistent collation
    $mode_query = "SELECT additional_fee FROM service_consultation_modes WHERE service_consultation_id = ?";
    $mode_stmt = $conn->prepare($mode_query);
    $mode_stmt->bind_param('i', $service_consultation_id);
    $mode_stmt->execute();
    $mode_result = $mode_stmt->get_result();
    $mode_row = $mode_result->fetch_assoc();
    $additional_fee = $mode_row['additional_fee'];
    $mode_stmt->close();

    // Default duration and other parameters
    // Default duration and other parameters
    // $duration_minutes = 60; // Default to 1 hour - REMOVE THIS LINE
    $status_id = 1; // Pending status
    $reference_number = null; // This will be generated by the trigger

    file_put_contents('booking_debug.log', date('Y-m-d H:i:s') . " - Starting transaction\n", FILE_APPEND);

    // Begin transaction
    $conn->begin_transaction();

    // Calculate end_datetime based on duration
    $end_datetime = date('Y-m-d H:i:s', strtotime($booking_datetime . ' +' . $duration_minutes . ' minutes'));

    // Prepare and execute the INSERT statement for bookings - Ensure consistent collation
    $stmt = $conn->prepare("INSERT INTO bookings (
        reference_number, user_id, visa_service_id, service_consultation_id, consultant_id,
        organization_id, status_id, booking_datetime, end_datetime, duration_minutes,
        client_notes, language_preference, time_zone
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $time_zone = 'UTC'; // Default, could be customized
    
    $stmt->bind_param('siiiiiissssss', 
        $reference_number, $user_id, $visa_service_id, $service_consultation_id,
        $consultant_id, $organization_id, $status_id, $booking_datetime, $end_datetime,
        $duration_minutes, $client_notes, $language_preference, $time_zone
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create booking: " . $conn->error);
    }
    
    $booking_id = $conn->insert_id;
    $stmt->close();
    
    file_put_contents('booking_debug.log', date('Y-m-d H:i:s') . " - Booking created with ID: {$booking_id}\n", FILE_APPEND);
    
    // Create payment record
    $total_price = $base_price + $additional_fee;
    $payment_method = 'credit_card'; // Default
    $payment_status = 'pending'; // Default
    
    $payment_query = "INSERT INTO booking_payments (
        booking_id, amount, currency, payment_method, payment_status
    ) VALUES (?, ?, ?, ?, ?)";
    
    $currency = 'USD'; // Default
    
    $payment_stmt = $conn->prepare($payment_query);
    $payment_stmt->bind_param('idsss', 
        $booking_id, $total_price, $currency, $payment_method, $payment_status
    );
    
    if (!$payment_stmt->execute()) {
        throw new Exception("Failed to create payment record: " . $conn->error);
    }
    
    $payment_stmt->close();
    
    file_put_contents('booking_debug.log', date('Y-m-d H:i:s') . " - Payment record created\n", FILE_APPEND);
    
    // Create activity log
    $activity_query = "INSERT INTO booking_activity_logs (
        booking_id, user_id, activity_type, description
    ) VALUES (?, ?, ?, ?)";
    
    $activity_type = 'created';
    $description = 'Booking created through online booking system';
    
    $activity_stmt = $conn->prepare($activity_query);
    $activity_stmt->bind_param('iiss', 
        $booking_id, $user_id, $activity_type, $description
    );
    
    if (!$activity_stmt->execute()) {
        throw new Exception("Failed to create activity log: " . $conn->error);
    }
    
    $activity_stmt->close();
    
    // Create reminder
    $reminder_query = "INSERT INTO booking_reminders (
        booking_id, reminder_type, scheduled_time
    ) VALUES (?, ?, DATE_SUB(?, INTERVAL 24 HOUR))";
    
    $reminder_type = 'email';
    
    $reminder_stmt = $conn->prepare($reminder_query);
    $reminder_stmt->bind_param('iss', 
        $booking_id, $reminder_type, $booking_datetime
    );
    
    if (!$reminder_stmt->execute()) {
        throw new Exception("Failed to create reminder: " . $conn->error);
    }
    
    $reminder_stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    file_put_contents('booking_debug.log', date('Y-m-d H:i:s') . " - Transaction committed successfully\n", FILE_APPEND);
    
    // Get the generated reference number - Use correct column name
    $ref_query = "SELECT reference_number FROM bookings WHERE id = ?";
    $ref_stmt = $conn->prepare($ref_query);
    $ref_stmt->bind_param('i', $booking_id);
    $ref_stmt->execute();
    $ref_result = $ref_stmt->get_result();
    $ref_row = $ref_result->fetch_assoc();
    $reference_number = $ref_row['reference_number'];
    $ref_stmt->close();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Your booking has been successfully created',
        'data' => [
            'booking_id' => $booking_id,
            'reference_number' => $reference_number,
            'total_amount' => $total_price,
            'booking_datetime' => $booking_datetime
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Log the error
    file_put_contents('booking_debug.log', date('Y-m-d H:i:s') . " - Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    
    // Return JSON error response
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    exit;
}
?>