<?php
// Include session management
require_once __DIR__ . '/session.php';

/**
 * Handle user file upload and store in user-specific directory
 * 
 * @param int $user_id The user ID
 * @param array $file The $_FILES array element
 * @param string $type The type of upload (profile, document, banner)
 * @param array $options Optional settings like max_size, allowed_types
 * @return array Result with status, message and file_path if successful
 */
function handle_user_file_upload($user_id, $file, $type = 'document', $options = []) {
    // Set default options
    $defaults = [
        'max_size' => 2 * 1024 * 1024, // 2MB default
        'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'],
        'filename_prefix' => $type . '_'
    ];
    
    // Merge options with defaults
    $options = array_merge($defaults, $options);
    
    // Initialize result
    $result = [
        'status' => false,
        'message' => '',
        'file_path' => ''
    ];
    
    // Validate file
    if (!isset($file) || $file['error'] != 0) {
        $result['message'] = "Error uploading file. Please try again.";
        return $result;
    }
    
    if (!in_array($file['type'], $options['allowed_types'])) {
        $result['message'] = "Invalid file type. Allowed types: " . implode(', ', array_map(function($type) {
            return str_replace('image/', '', str_replace('application/', '', $type));
        }, $options['allowed_types']));
        return $result;
    }
    
    if ($file['size'] > $options['max_size']) {
        $result['message'] = "File size should be less than " . formatBytes($options['max_size']);
        return $result;
    }
    
    // Determine user directory structure
    $base_upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads';
    $user_dir = $base_upload_dir . '/users/' . $user_id;
    $type_dir = $user_dir . '/' . $type;
    
    // Create directories if they don't exist
    if (!is_dir($user_dir)) {
        mkdir($user_dir, 0755, true);
    }
    
    if (!is_dir($type_dir)) {
        mkdir($type_dir, 0755, true);
    }
    
    // Create unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $options['filename_prefix'] . time() . '.' . $file_extension;
    $target_file = $type_dir . '/' . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Return relative path for database storage
        $relative_path = 'users/' . $user_id . '/' . $type . '/' . $filename;
        
        $result['status'] = true;
        $result['message'] = "File uploaded successfully";
        $result['file_path'] = $relative_path;
    } else {
        $result['message'] = "Failed to upload file";
    }
    
    return $result;
}

/**
 * Get the full server path for a user file
 * 
 * @param string $relative_path The relative path stored in the database
 * @return string The full server path
 */
function get_user_file_path($relative_path) {
    return $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $relative_path;
}

/**
 * Get the URL for a user file
 * 
 * @param string $relative_path The relative path stored in the database
 * @return string The URL path
 */
function get_user_file_url($relative_path) {
    // Handle empty paths
    if (empty($relative_path)) {
        return '/assets/images/default-profile.svg';
    }
    
    // If the path exists, return its URL
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $relative_path)) {
        return '/uploads/' . $relative_path;
    }
    
    // Legacy path handling
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/profiles/' . $relative_path)) {
        return '/uploads/profiles/' . $relative_path;
    }
    
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/profile/' . $relative_path)) {
        return '/uploads/profile/' . $relative_path;
    }
    
    // Return default if file not found
    return '/assets/images/default-profile.svg';
}

/**
 * Format bytes to human readable format
 * 
 * @param int $bytes The size in bytes
 * @param int $precision The number of decimal places
 * @return string Formatted size with unit
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

