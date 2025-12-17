<?php
require_once '../../includes/session.php';
require_once '../../config/db_connect.php';

// Check if user is logged in and is a consultant
if (!isset($_SESSION['id']) || $_SESSION['user_type'] !== 'consultant') {
    header('Location: ../../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_business_hours'])) {
    $consultant_id = $_SESSION['id'];
    $organization_id = $_SESSION['organization_id'] ?? 1;
    
    try {
        $conn->begin_transaction();
        
        // Delete existing business hours for this consultant
        $delete_query = "DELETE FROM business_hours WHERE consultant_id = ? AND organization_id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("ii", $consultant_id, $organization_id);
        $stmt->execute();
        
        // Insert new business hours
        $insert_query = "INSERT INTO business_hours (day_of_week, is_open, open_time, close_time, organization_id, consultant_id) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        
        for ($day = 0; $day < 7; $day++) {
            $is_open = isset($_POST['is_open'][$day]) ? 1 : 0;
            $open_time = $is_open && !empty($_POST['open_time'][$day]) ? $_POST['open_time'][$day] . ':00' : '00:00:00';
            $close_time = $is_open && !empty($_POST['close_time'][$day]) ? $_POST['close_time'][$day] . ':00' : '00:00:00';
            
            $stmt->bind_param("iissii", $day, $is_open, $open_time, $close_time, $organization_id, $consultant_id);
            $stmt->execute();
        }
        
        $conn->commit();
        $_SESSION['success_message'] = 'Business hours updated successfully!';
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = 'Error updating business hours: ' . $e->getMessage();
    }
}

header('Location: bookings.php?tab=business-hours');
exit();
?>