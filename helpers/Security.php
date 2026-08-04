<?php
/**
 * Security.php - Security utility functions
 * 
 * Provides security-related functions for input validation, sanitization,
 * file validation, and secure hash generation.
 */

/**
 * Sanitize user input to prevent XSS attacks
 * 
 * Removes potentially dangerous characters and encodes special characters
 * to safely display user input in HTML context.
 * 
 * @param string $input The input string to sanitize
 * @return string The sanitized input string
 */
function sanitizeInput($input)
{
    if (!is_string($input)) {
        return '';
    }
    
    // Remove leading/trailing whitespace
    $input = trim($input);
    
    // Remove script tags and other dangerous content
    $input = preg_replace('/<script[^>]*>.*?<\/script>/i', '', $input);
    $input = preg_replace('/<iframe[^>]*>.*?<\/iframe>/i', '', $input);
    $input = preg_replace('/<object[^>]*>.*?<\/object>/i', '', $input);
    $input = preg_replace('/<embed[^>]*>/i', '', $input);
    
    // Encode special HTML characters
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

/**
 * Validate email address format
 * 
 * Checks if the provided email address is in valid format.
 * Uses PHP's built-in filter for RFC 5321 compliant validation.
 * 
 * @param string $email The email address to validate
 * @return bool True if email is valid, false otherwise
 */
function validateEmail($email)
{
    if (!is_string($email)) {
        return false;
    }
    
    $email = trim($email);
    
    // Check length
    if (strlen($email) > 255 || strlen($email) < 5) {
        return false;
    }
    
    // Use filter_var for RFC 5321 validation
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate uploaded file
 * 
 * Checks if the uploaded file meets the specified requirements:
 * - File type/MIME type is allowed
 * - File size does not exceed maximum
 * 
 * @param array $file The $_FILES array element (e.g., $_FILES['upload'])
 * @param array $allowed_types Array of allowed MIME types (e.g., ['image/jpeg', 'image/png'])
 * @param int $max_size Maximum file size in bytes
 * @return array ['valid' => bool, 'message' => string]
 */
function validateFile($file, $allowed_types = [], $max_size = 5242880)
{
    // Check if file was uploaded without errors
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return [
            'valid' => false,
            'message' => 'File upload error: ' . ($file['error'] ?? 'Unknown')
        ];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return [
            'valid' => false,
            'message' => 'File size exceeds maximum allowed size (' . round($max_size / 1024 / 1024, 2) . 'MB)'
        ];
    }
    
    // Check file type if allowed types specified
    if (!empty($allowed_types)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            return [
                'valid' => false,
                'message' => 'File type not allowed. Allowed types: ' . implode(', ', $allowed_types)
            ];
        }
    }
    
    return [
        'valid' => true,
        'message' => 'File is valid'
    ];
}

/**
 * Generate a random hash string
 * 
 * Creates a cryptographically secure random hash string of specified length.
 * Useful for generating tokens, verification codes, etc.
 * 
 * @param int $length The desired length of the hash (default: 32)
 * @return string Random hash string of specified length
 */
function generateHash($length = 32)
{
    if ($length <= 0) {
        $length = 32;
    }
    
    try {
        // Generate cryptographically secure random bytes
        $random_bytes = random_bytes(ceil($length / 2));
        $hash = substr(bin2hex($random_bytes), 0, $length);
        return $hash;
    } catch (Exception $e) {
        // Fallback if random_bytes is not available
        $hash = '';
        for ($i = 0; $i < $length; $i++) {
            $hash .= dechex(mt_rand(0, 15));
        }
        return substr($hash, 0, $length);
    }
}

/**
 * Validate a filename to prevent path traversal and unsafe characters.
 * Allows only alphanumeric, dot, underscore and dash characters.
 *
 * @param string $filename
 * @return bool
 */
function isSafeFilename($filename)
{
    if (!is_string($filename)) return false;
    if (strpos($filename, '..') !== false) return false;
    // Limit length to reasonable max (255)
    if (strlen($filename) < 1 || strlen($filename) > 255) return false;
    return preg_match('/^[A-Za-z0-9._-]+$/', $filename) === 1;
}

/**
 * Safely unlink a file inside a directory ensuring no path traversal.
 *
 * @param string $dir
 * @param string $filename
 * @return bool
 */
function safeUnlink($dir, $filename)
{
    if (!isSafeFilename($filename)) return false;
    $target_dir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
    $file_path = $target_dir . $filename;

    $realDir = realpath($target_dir);
    $realFile = realpath($file_path);
    if ($realDir === false || $realFile === false) return false;

    // Ensure the real file path begins with the real directory path
    if (strpos($realFile, $realDir) !== 0) return false;

    if (is_file($realFile)) {
        return @unlink($realFile);
    }
    return false;
}

/**
 * Generate and store CSRF token in session.
 * Call once per session to initialize CSRF protection.
 *
 * @param string $tokenName Session key name for the token (default: '_csrf_token')
 * @return string The CSRF token
 */
function generateCSRFToken($tokenName = '_csrf_token') {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (!headers_sent()) {
            session_start();
        }
        // If headers already sent, do not attempt to start session to avoid warnings
    }

    if (!isset($_SESSION[$tokenName])) {
        $_SESSION[$tokenName] = bin2hex(random_bytes(32));
    }

    return $_SESSION[$tokenName];
}

/**
 * Verify CSRF token from POST/GET request.
 *
 * @param string $token The token to verify (from request)
 * @param string $tokenName Session key name for the token (default: '_csrf_token')
 * @return bool True if token is valid, false otherwise
 */
function verifyCSRFToken($token, $tokenName = '_csrf_token') {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (!headers_sent()) {
            session_start();
        }
        // If headers already sent, do not attempt to start session to avoid warnings
    }

    if (!isset($_SESSION[$tokenName]) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION[$tokenName], $token);
}
