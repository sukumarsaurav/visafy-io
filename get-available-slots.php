<?php
require_once 'config/db_connect.php';
header('Content-Type: application/json');

$consultant_id = isset($_GET['consultant_id']) ? (int)$_GET['consultant_id'] : 0;
$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';

if (!$consultant_id || !$service_id || !$date) {
    echo json_encode([]);
    exit();
}

try {
    // Get day of week (0 = Sunday, 1 = Monday, etc.)
    $day_of_week = date('w', strtotime($date));
    
    // Get business hours for this consultant and day
    $hours_query = "SELECT open_time, close_time, is_open FROM business_hours 
                   WHERE consultant_id = ? AND day_of_week = ? AND is_open = 1";
    $stmt = $conn->prepare($hours_query);
    $stmt->bind_param("ii", $consultant_id, $day_of_week);
    $stmt->execute();
    $hours_result = $stmt->get_result();
    
    if ($hours_result->num_rows === 0) {
        echo json_encode([]);
        exit();
    }
    
    $hours = $hours_result->fetch_assoc();
    $open_time = $hours['open_time'];
    $close_time = $hours['close_time'];
    
    // Generate time slots (30-minute intervals)
    $slots = [];
    $current_time = strtotime($open_time);
    $end_time = strtotime($close_time);
    
    while ($current_time < $end_time) {
        $slot_time = date('H:i', $current_time);
        
        // Check if this slot is already booked
        $booking_query = "SELECT COUNT(*) as count FROM bookings 
                         WHERE consultant_id = ? 
                         AND DATE(booking_datetime) = ? 
                         AND TIME(booking_datetime) = ? 
                         AND status_id NOT IN (SELECT id FROM booking_statuses WHERE name IN ('cancelled_by_user', 'cancelled_by_admin', 'cancelled_by_consultant'))";
        $stmt = $conn->prepare($booking_query);
        $stmt->bind_param("iss", $consultant_id, $date, $slot_time);
        $stmt->execute();
        $booking_result = $stmt->get_result();
        $booking_count = $booking_result->fetch_assoc()['count'];
        
        // Only add slot if it's not booked and is in the future
        $slot_datetime = $date . ' ' . $slot_time;
        if ($booking_count == 0 && strtotime($slot_datetime) > time()) {
            $slots[] = ['time' => $slot_time];
        }
        
        $current_time += 1800; // Add 30 minutes
    }
    
    echo json_encode($slots);
    
} catch (Exception $e) {
    echo json_encode([]);
}
?>