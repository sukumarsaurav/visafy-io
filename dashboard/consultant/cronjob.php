<?php
// Include your database connection
require_once 'includes/db.php';

// Process booking action queue
$stmt = $conn->prepare("SELECT * FROM booking_action_queue WHERE processed = 0 LIMIT 100");
$stmt->execute();
$actions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($actions as $action) {
    if ($action['action_type'] == 'create_conversation') {
        // Call your procedure without being in a trigger
        $stmt = $conn->prepare("CALL create_booking_conversation(?, @conversation_id)");
        $stmt->bind_param('i', $action['booking_id']);
        $stmt->execute();
        $stmt->close();
    }
    
    // Mark as processed
    $stmt = $conn->prepare("UPDATE booking_action_queue SET processed = 1, processed_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $action['id']);
    $stmt->execute();
    $stmt->close();
}

// Process notification queue
$stmt = $conn->prepare("SELECT * FROM notification_queue WHERE processed = 0 LIMIT 100");
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($notifications as $notif) {
    // Get booking details
    $stmt = $conn->prepare("SELECT b.*, 
                          CONCAT(u.first_name, ' ', u.last_name) AS client_name,
                          CONCAT(cu.first_name, ' ', cu.last_name) AS consultant_name,
                          st.service_name
                          FROM bookings b
                          JOIN users u ON b.user_id = u.id
                          JOIN users cu ON b.consultant_id = cu.id
                          JOIN visa_services vs ON b.visa_service_id = vs.visa_service_id
                          JOIN service_types st ON vs.service_type_id = st.service_type_id
                          WHERE b.id = ?");
    $stmt->bind_param('i', $notif['related_booking_id']);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Call send_notification without being in a trigger
    $title = '';
    $message = '';
    
    // Prepare notification content
    if ($notif['status_name'] == 'confirmed') {
        $title = "Booking Confirmed";
        $message = "Your booking on " . date('Y-m-d H:i', strtotime($booking['booking_datetime'])) . " has been confirmed.";
    } elseif ($notif['status_name'] == 'cancelled_by_user') {
        $title = "Booking Cancelled";
        $message = "Booking has been cancelled by the client.";
    } // Add other status types
    
    // Send notification
    $stmt = $conn->prepare("CALL send_notification(?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL, NULL, ?, NULL, @notification_id)");
    $notif_type = $notif['notification_type'];
    $link = "/dashboard/bookings/" . $notif['related_booking_id'];
    $stmt->bind_param('sissiii', $notif_type, $notif['user_id'], $title, $message, $link, $notif['related_booking_id'], $booking['organization_id']);
    $stmt->execute();
    $stmt->close();
    
    // Mark as processed
    $stmt = $conn->prepare("UPDATE notification_queue SET processed = 1, processed_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $notif['id']);
    $stmt->execute();
    $stmt->close();
}

echo "Processed " . count($actions) . " booking actions and " . count($notifications) . " notifications.";
?>
