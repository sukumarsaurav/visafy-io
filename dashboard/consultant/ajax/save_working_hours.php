<?php
require_once '../../../config/db_connect.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $organization_id = isset($user['organization_id']) ? $user['organization_id'] : null;
    $consultant_id = isset($_SESSION['id']) ? $_SESSION['id'] : null;

    // Begin transaction
    $conn->begin_transaction();

    // Delete existing hours
    $delete_query = "DELETE FROM business_hours WHERE organization_id = ? AND consultant_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param('ii', $organization_id, $consultant_id);
    $stmt->execute();

    // Insert new hours
    $insert_query = "INSERT INTO business_hours (day_of_week, is_open, open_time, close_time, organization_id, consultant_id) 
                    VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);

    for ($day = 0; $day < 7; $day++) {
        $is_open = isset($_POST['is_open'][$day]) ? 1 : 0;
        $open_time = $is_open ? $_POST['open_time'][$day] : '00:00:00';
        $close_time = $is_open ? $_POST['close_time'][$day] : '00:00:00';

        $stmt->bind_param('iissii', $day, $is_open, $open_time, $close_time, $organization_id, $consultant_id);
        $stmt->execute();
    }

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Working hours saved successfully';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Error saving working hours: ' . $e->getMessage();
}

echo json_encode($response);
