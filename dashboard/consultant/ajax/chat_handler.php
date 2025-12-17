<?php
require_once '../../../config/db_connect.php';
session_start();
header('Content-Type: application/json');

// Get user_id from session
$user_id = $_SESSION['id'] ?? null;
$user_type = $_SESSION['user_type'] ?? null;

if (!$user_id || !$user_type) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// For team members, get their team_member_id
$team_member_id = null;
if ($user_type == 'member') {
    $stmt = $conn->prepare("SELECT id FROM team_members WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $team_member = $result->fetch_assoc();
    $team_member_id = $team_member['id'] ?? null;
}

// Load environment variables if not already loaded from db_connect.php
function loadEnv($path) {
    if (file_exists($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

// Load the .env file with explicit error checking if API key not found
$api_key = getenv('OPENAI_API_KEY');
if (!$api_key) {
    $envPath = __DIR__ . '/../../../../config/.env';
    if (!file_exists($envPath)) {
        error_log("Error: .env file not found at: " . $envPath);
        echo json_encode(['success' => false, 'error' => 'Configuration file not found']);
        exit;
    }
    
    loadEnv($envPath);
    $api_key = getenv('OPENAI_API_KEY');
    
    if (!$api_key) {
        error_log("Error: OPENAI_API_KEY not found in environment variables");
        echo json_encode(['success' => false, 'error' => 'API key not configured']);
        exit;
    }
}

// Get monthly chat limit based on membership plan
function getMonthlyLimit($conn, $user_id) {
    // Default limits by plan
    $plan_limits = [
        'Bronze' => 40,
        'Silver' => 80,
        'Gold' => 150
    ];
    
    // Get user's membership plan
    $sql = "SELECT mp.name FROM membership_plans mp 
            JOIN consultants c ON c.membership_plan_id = mp.id 
            WHERE c.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $plan_name = $row['name'];
        return $plan_limits[$plan_name] ?? 40; // Default to Bronze limit if plan not found
    }
    
    return 40; // Default to Bronze if no plan found
}

// Check if user has reached message limit
function hasReachedMessageLimit($conn, $user_id) {
    if ($user_id == 'admin') {
        return false; // Admins have unlimited usage
    }
    
    $month = date('Y-m');
    $sql = "SELECT chat_count FROM ai_chat_usage 
            WHERE consultant_id = ? AND month = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    $usage = $result->fetch_assoc();
    $messages_used = $usage ? $usage['chat_count'] : 0;
    
    // Get monthly limit based on plan
    $monthly_limit = getMonthlyLimit($conn, $user_id);
    
    return $messages_used >= $monthly_limit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_conversation':
                $conversation_id = (int)$_POST['conversation_id'];
                $sql = "UPDATE ai_chat_conversations SET deleted_at = NOW() 
                        WHERE id = ? AND consultant_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $conversation_id, $user_id);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => $conn->error]);
                }
                break;

            case 'get_usage':
                if ($user_type == 'admin') {
                    // Admins have unlimited usage
                    echo json_encode(['success' => true, 'usage' => 0, 'limit' => 'Unlimited']);
                } else {
                    $month = date('Y-m');
                    $sql = "SELECT chat_count FROM ai_chat_usage 
                            WHERE consultant_id = ? AND month = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("is", $user_id, $month);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $usage = $result->fetch_assoc();
                    $messages_used = $usage ? $usage['chat_count'] : 0;
                    
                    // Get monthly limit based on plan
                    $monthly_limit = getMonthlyLimit($conn, $user_id);
                    
                    echo json_encode([
                        'success' => true, 
                        'usage' => $messages_used,
                        'limit' => $monthly_limit
                    ]);
                }
                break;

            case 'create_conversation':
                // No need to check limits for creating conversation, only when sending messages
                $title = "New Chat";
                $chat_type = isset($_POST['chat_type']) ? $_POST['chat_type'] : 'ircc';
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    $sql = "INSERT INTO ai_chat_conversations (consultant_id, title, chat_type) 
                            VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iss", $user_id, $title, $chat_type);
                    
                    if (!$stmt->execute()) {
                        throw new Exception($conn->error);
                    }
                    
                    $conversation_id = $conn->insert_id;
                    
                    // Add initial system message (this doesn't count against the limit)
                    $welcome_msg = "Hello! I'm your visa and immigration consultant assistant. How can I help you today?";
                    $sql = "INSERT INTO ai_chat_messages (conversation_id, consultant_id, role, content) 
                            VALUES (?, ?, 'assistant', ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iis", $conversation_id, $user_id, $welcome_msg);
                    
                    if (!$stmt->execute()) {
                        throw new Exception($conn->error);
                    }
                    
                    // We don't increment chat_count here anymore, only when sending messages
                    
                    $conn->commit();
                    
                    echo json_encode([
                        'success' => true, 
                        'conversation_id' => $conversation_id,
                        'welcome_message' => $welcome_msg
                    ]);
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                break;

            case 'get_conversation':
                $conversation_id = (int)$_POST['conversation_id'];
                
                // Get conversation details
                $sql = "SELECT * FROM ai_chat_conversations 
                        WHERE id = ? AND consultant_id = ? AND deleted_at IS NULL";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $conversation_id, $user_id);
                $stmt->execute();
                $conversation = $stmt->get_result()->fetch_assoc();
                
                if (!$conversation) {
                    echo json_encode(['success' => false, 'error' => 'Conversation not found']);
                    break;
                }
                
                // Get messages
                $sql = "SELECT id, role, content, created_at FROM ai_chat_messages 
                        WHERE conversation_id = ? AND deleted_at IS NULL 
                        ORDER BY created_at ASC";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $conversation_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $messages = [];
                while ($row = $result->fetch_assoc()) {
                    $content = $row['content'];
                    // If it's an AI message, format the content for display
                    if ($row['role'] === 'assistant') {
                        $content = nl2br($content);
                    }
                    
                    $messages[] = [
                        'id' => $row['id'],
                        'role' => $row['role'],
                        'content' => $content,
                        'created_at' => $row['created_at']
                    ];
                }
                
                echo json_encode([
                    'success' => true, 
                    'conversation' => $conversation,
                    'messages' => $messages
                ]);
                break;

            case 'send_message':
                $message = $_POST['message'] ?? '';
                $conversation_id = (int)$_POST['conversation_id'];

                if (!empty($message)) {
                    // Check if user has reached their monthly message limit
                    if ($user_type != 'admin') {
                        if (hasReachedMessageLimit($conn, $user_id)) {
                            echo json_encode([
                                'success' => false, 
                                'error' => "You've reached your monthly message limit. Please upgrade your plan for more messages."
                            ]);
                            break;
                        }
                    }
                    
                    // Verify conversation exists and belongs to user
                    $sql = "SELECT id, chat_type FROM ai_chat_conversations 
                            WHERE id = ? AND consultant_id = ? AND deleted_at IS NULL";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $conversation_id, $user_id);
                    $stmt->execute();
                    $conversation = $stmt->get_result()->fetch_assoc();
                    
                    if (!$conversation) {
                        echo json_encode(['success' => false, 'error' => 'Invalid conversation']);
                        break;
                    }

                    // Start a transaction
                    $conn->begin_transaction();
                    
                    try {
                        // Save user message
                        $sql = "INSERT INTO ai_chat_messages (conversation_id, consultant_id, role, content) 
                                VALUES (?, ?, 'user', ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("iis", $conversation_id, $user_id, $message);
                        if (!$stmt->execute()) {
                            throw new Exception('Failed to save message: ' . $conn->error);
                        }

                        // Update conversation title with first message
                        $sql = "UPDATE ai_chat_conversations 
                                SET title = ? 
                                WHERE id = ? AND consultant_id = ? 
                                AND (title = 'New Chat' OR title LIKE 'New Chat%')";
                        $stmt = $conn->prepare($sql);
                        $short_title = substr($message, 0, 50) . (strlen($message) > 50 ? "..." : "");
                        $stmt->bind_param("sii", $short_title, $conversation_id, $user_id);
                        $stmt->execute();

                        // Get conversation history
                        $sql = "SELECT role, content FROM ai_chat_messages 
                                WHERE conversation_id = ? AND deleted_at IS NULL
                                ORDER BY created_at DESC LIMIT 10";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $conversation_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $history = [];
                        while ($row = $result->fetch_assoc()) {
                            array_unshift($history, [
                                "role" => $row['role'],
                                "content" => $row['content']
                            ]);
                        }

                        // Update usage count for non-admin users - now we count each message
                        if ($user_type != 'admin') {
                            $month = date('Y-m');
                            $sql = "INSERT INTO ai_chat_usage (consultant_id, month, chat_count) 
                                    VALUES (?, ?, 1)
                                    ON DUPLICATE KEY UPDATE chat_count = chat_count + 1";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("is", $user_id, $month);
                            
                            if (!$stmt->execute()) {
                                throw new Exception('Failed to update usage: ' . $conn->error);
                            }
                        }

                        // Call OpenAI API with error handling
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Content-Type: application/json',
                            'Authorization: Bearer ' . $api_key
                        ]);

                        // Enable verbose error reporting
                        curl_setopt($ch, CURLOPT_VERBOSE, true);
                        $verbose = fopen('php://temp', 'w+');
                        curl_setopt($ch, CURLOPT_STDERR, $verbose);

                        // Prepare messages array with system message and history
                        $system_message = $conversation['chat_type'] === 'cases' 
                            ? "You are a legal assistant specializing in immigration case law. Provide accurate information about immigration cases, precedents, and court decisions. Be precise and professional."
                            : "You are a friendly visa and immigration consultant assistant. Provide accurate, helpful information about visa processes, IRCC procedures, and immigration requirements. Be clear and professional.";

                        $messages = [["role" => "system", "content" => $system_message]];
                        $messages = array_merge($messages, $history);

                        $data = [
                            'model' => 'gpt-3.5-turbo',
                            'messages' => $messages,
                            'temperature' => 0.7,
                            'max_tokens' => 500
                        ];

                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                        $response = curl_exec($ch);
                        
                        if ($response === false) {
                            rewind($verbose);
                            $verboseLog = stream_get_contents($verbose);
                            error_log("cURL Error: " . curl_error($ch) . "\n" . $verboseLog);
                            throw new Exception('Could not connect to the OpenAI API: ' . curl_error($ch));
                        }

                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        if ($http_code >= 400) {
                            error_log("OpenAI API HTTP Error: " . $http_code . " - " . $response);
                            throw new Exception('API returned error code: ' . $http_code);
                        }

                        $result = json_decode($response, true);
                        curl_close($ch);

                        if (isset($result['error'])) {
                            error_log("OpenAI API Error: " . json_encode($result['error']));
                            throw new Exception($result['error']['message'] ?? 'An unknown error occurred');
                        }

                        if (!isset($result['choices'][0]['message']['content'])) {
                            error_log("OpenAI API Invalid Response: " . json_encode($result));
                            throw new Exception('Invalid response format from AI service');
                        }

                        $ai_response = $result['choices'][0]['message']['content'];
                        
                        // Format the response for display - preserve line breaks
                        $ai_response = nl2br($ai_response);
                        
                        // Save the original response to the database (without HTML)
                        $sql = "INSERT INTO ai_chat_messages (conversation_id, consultant_id, role, content) 
                                VALUES (?, ?, 'assistant', ?)";
                        $stmt = $conn->prepare($sql);
                        $raw_response = $result['choices'][0]['message']['content']; // Original without formatting
                        $stmt->bind_param("iis", $conversation_id, $user_id, $raw_response);
                        if (!$stmt->execute()) {
                            throw new Exception('Failed to save AI response: ' . $conn->error);
                        }
                        
                        // Count AI message response separately for token counting
                        if ($user_type != 'admin') {
                            // Calculate tokens for usage tracking
                            $prompt_tokens = isset($result['usage']['prompt_tokens']) ? $result['usage']['prompt_tokens'] : 0;
                            $completion_tokens = isset($result['usage']['completion_tokens']) ? $result['usage']['completion_tokens'] : 0;
                            $total_tokens = $prompt_tokens + $completion_tokens;
                            
                            $month = date('Y-m');
                            
                            $sql = "UPDATE ai_chat_usage 
                                    SET token_count = token_count + ?, 
                                        message_count = message_count + 1
                                    WHERE consultant_id = ? AND month = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("iis", $total_tokens, $user_id, $month);
                            if (!$stmt->execute()) {
                                throw new Exception('Failed to update token usage: ' . $conn->error);
                            }
                        }

                        $conn->commit();
                        echo json_encode([
                            'success' => true, 
                            'message' => $ai_response,
                            'usage_updated' => true
                        ]);
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        error_log("Error in send_message: " . $e->getMessage());
                        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
                }
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
                break;
        }
    }
}
?> 