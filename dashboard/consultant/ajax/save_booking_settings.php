<?php
require_once '../../../config/db_connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $organization_id = isset($user['organization_id']) ? $user['organization_id'] : null;
    $consultant_id = isset($_SESSION['id']) ? $_SESSION['id'] : null;

    $min_notice_hours = (int)$_POST['min_notice_hours'];
    $max_advance_days = (int)$_POST['max_advance_days'];
    $buffer_before_minutes = (int)$_POST['buffer_before_minutes'];
    $buffer_after_minutes = (int)$_POST['buffer_after_minutes'];
    $cancellation_policy = $_POST['cancellation_policy'];
    $reschedule_policy = $_POST['reschedule_policy'];
    $payment_required = isset($_POST['payment_required']) ? 1 : 0;
    $deposit_amount = $payment_required ? (float)$_POST['deposit_amount'] : 0;
    $deposit_percentage = $payment_required ? (int)$_POST['deposit_percentage'] : 0;

    $query = "INSERT INTO service_booking_settings 
              (min_notice_hours, max_advance_days, buffer_before_minutes, buffer_after_minutes,
               cancellation_policy, reschedule_policy, payment_required, deposit_amount,
               deposit_percentage, organization_id, consultant_id)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE
              min_notice_hours = VALUES(min_notice_hours),
              max_advance_days = VALUES(max_advance_days),
              buffer_before_minutes = VALUES(buffer_before_minutes),
              buffer_after_minutes = VALUES(buffer_after_minutes),
              cancellation_policy = VALUES(cancellation_policy),
              reschedule_policy = VALUES(reschedule_policy),
              payment_required = VALUES(payment_required),
              deposit_amount = VALUES(deposit_amount),
              deposit_percentage = VALUES(deposit_percentage)";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('iiiissddiii', 
        $min_notice_hours, $max_advance_days, $buffer_before_minutes, $buffer_after_minutes,
        $cancellation_policy, $reschedule_policy, $payment_required, $deposit_amount,
        $deposit_percentage, $organization_id, $consultant_id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Booking settings saved successfully';
    } else {
        throw new Exception($conn->error);
    }

} catch (Exception $e) {
    $response['message'] = 'Error saving booking settings: ' . $e->getMessage();
}

echo json_encode($response);
