<?php
require_once '../../../config/db_connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $organization_id = isset($user['organization_id']) ? $user['organization_id'] : null;
    $consultant_id = isset($_SESSION['id']) ? $_SESSION['id'] : null;

    $date = $_POST['special_date'];
    $description = $_POST['description'];
    $is_closed = isset($_POST['is_closed']) ? 1 : 0;
    $alternative_open_time = !$is_closed ? $_POST['alternative_open_time'] : null;
    $alternative_close_time = !$is_closed ? $_POST['alternative_close_time'] : null;

    $query = "INSERT INTO special_days (date, description, is_closed, alternative_open_time, alternative_close_time, organization_id, consultant_id)
              VALUES (?, ?, ?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE
              description = VALUES(description),
              is_closed = VALUES(is_closed),
              alternative_open_time = VALUES(alternative_open_time),
              alternative_close_time = VALUES(alternative_close_time)";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssisssi', $date, $description, $is_closed, $alternative_open_time, $alternative_close_time, $organization_id, $consultant_id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Special day saved successfully';
    } else {
        throw new Exception($conn->error);
    }

} catch (Exception $e) {
    $response['message'] = 'Error saving special day: ' . $e->getMessage();
}

echo json_encode($response);
