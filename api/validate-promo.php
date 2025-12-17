<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../config/db_connect.php';

// Set headers
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['code']) || !isset($input['plan_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$code = trim($input['code']);
$plan_id = intval($input['plan_id']);
$user_id = isset($_SESSION['id']) ? $_SESSION['id'] : null;

// Initialize output variables
$is_valid = false;
$discount_type = '';
$discount_value = 0;
$error_message = '';

// Call the stored procedure to validate promo code
$stmt = $conn->prepare("CALL validate_promo_code(?, ?, ?, @is_valid, @discount_type, @discount_value, @error_message)");
$stmt->bind_param("sii", $code, $plan_id, $user_id);
$stmt->execute();
$stmt->close();

// Get the results
$result = $conn->query("SELECT @is_valid as is_valid, @discount_type as discount_type, 
                              @discount_value as discount_value, @error_message as error_message");
$validation = $result->fetch_assoc();

if ($validation['is_valid']) {
    // Get plan price
    $stmt = $conn->prepare("SELECT price FROM membership_plans WHERE id = ?");
    $stmt->bind_param("i", $plan_id);
    $stmt->execute();
    $plan_result = $stmt->get_result();
    $plan = $plan_result->fetch_assoc();
    $stmt->close();
    
    $original_price = $plan['price'];
    
    // Calculate discount
    if ($validation['discount_type'] === 'percentage') {
        $discount_amount = $original_price * ($validation['discount_value'] / 100);
    } else {
        $discount_amount = $validation['discount_value'];
    }
    
    // Ensure discount doesn't exceed original price
    $discount_amount = min($discount_amount, $original_price);
    $final_price = $original_price - $discount_amount;
    
    // Get promo code ID
    $stmt = $conn->prepare("SELECT id FROM promo_codes WHERE code = ? AND is_active = 1");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $promo_result = $stmt->get_result();
    $promo = $promo_result->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'promo_code_id' => $promo['id'],
        'original_price' => $original_price,
        'discount_amount' => $discount_amount,
        'final_price' => $final_price,
        'discount_type' => $validation['discount_type'],
        'discount_value' => $validation['discount_value']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $validation['error_message'] ?? 'Invalid promo code'
    ]);
} 