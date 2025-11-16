<?php
// SPARK Platform - Common Functions

if (!function_exists('generateToken')) {
    require_once __DIR__ . '/config.php';
}
if (!function_exists('getPDO')) {
    require_once __DIR__ . '/database.php';
}

// Send email using Mailjet API
function sendEmail($to, $subject, $body, $attachments = []) {
    require_once __DIR__ . '/MailjetService.php';
    
    try {
        $mailjet = new MailjetService();
        $html_content = $body;
        $result = $mailjet->sendEmail($to, $subject, $html_content, '', $attachments);
        return $result['success'] ?? false;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        
        // Fallback to PHP mail if Mailjet fails
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        if (defined('EMAIL_FROM') && defined('EMAIL_FROM_NAME')) {
            $headers .= "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">" . "\r\n";
        }
        
        return mail($to, $subject, $body, $headers);
    }
}

// Queue email for sending
function queueEmail($to, $subject, $body, $priority = 'normal') {
    $emailData = [
        'to_email' => $to,
        'subject' => $subject,
        'body' => $body,
        'status' => 'pending'
    ];

    return dbInsert('email_queue', $emailData);
}

// Process email queue
function processEmailQueue() {
    $emails = dbFetchAll("
        SELECT * FROM email_queue
        WHERE status = 'pending' AND attempts < 3
        ORDER BY created_at ASC
        LIMIT 10
    ");

    foreach ($emails as $email) {
        $success = sendEmail($email['to_email'], $email['subject'], $email['body']);

        if ($success) {
            dbUpdate('email_queue', [
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$email['id']]);
        } else {
            dbUpdate('email_queue', [
                'attempts' => $email['attempts'] + 1,
                'status' => $email['attempts'] + 1 >= 3 ? 'failed' : 'pending'
            ], 'id = ?', [$email['id']]);
        }
    }
}

// Upload file
function uploadFile($file, $destinationFolder, $allowedTypes = null) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new Exception("Invalid file upload");
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception("File size exceeds maximum limit");
    }

    // Get file extension
    $fileName = $file['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Check allowed types
    if ($allowedTypes === null) {
        $allowedTypes = ALLOWED_IMAGE_TYPES;
    }

    if (!in_array($fileExtension, $allowedTypes)) {
        throw new Exception("File type not allowed");
    }

    // Generate unique filename
    $uniqueFileName = uniqid('spark_', true) . '.' . $fileExtension;
    $destinationPath = rtrim($destinationFolder, '/') . '/' . $uniqueFileName;

    // Create directory if it doesn't exist
    if (!is_dir($destinationFolder)) {
        mkdir($destinationFolder, 0755, true);
    }

    // Move file
    if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
        throw new Exception("Failed to move uploaded file");
    }

    return $uniqueFileName;
}

// Delete file
function deleteFile($filePath) {
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return true;
}

// Format date
function formatDate($date, $format = 'd M Y h:i A') {
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

// Format currency
function formatCurrency($amount, $currency = '₹') {
    return $currency . number_format($amount, 2);
}

// Generate slug
function generateSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

// Truncate text
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length - strlen($suffix)) . $suffix;
}

// Generate random string
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
}

// Get file size in human readable format
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, 2) . ' ' . $units[$pow];
}

// Sanitize filename
function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9.-]/', '_', $filename);
    return $filename;
}

// Get MIME type
function getMimeType($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mimeTypes = [
        'txt' => 'text/plain',
        'htm' => 'text/html',
        'html' => 'text/html',
        'php' => 'application/x-httpd-php',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'swf' => 'application/x-shockwave-flash',
        'flv' => 'video/x-flv',
        'png' => 'image/png',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'exe' => 'application/x-msdownload',
        'msi' => 'application/x-msi',
        'cab' => 'application/vnd.ms-cab-compressed',
        'mp3' => 'audio/mpeg',
        'qt' => 'video/quicktime',
        'mov' => 'video/quicktime',
        'pdf' => 'application/pdf',
        'psd' => 'image/vnd.adobe.photoshop',
        'ai' => 'application/postscript',
        'eps' => 'application/postscript',
        'ps' => 'application/postscript',
        'doc' => 'application/msword',
        'rtf' => 'application/rtf',
        'xls' => 'application/vnd.ms-excel',
        'ppt' => 'application/vnd.ms-powerpoint',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
    ];

    return $mimeTypes[$extension] ?? 'application/octet-stream';
}

// Create thumbnail
function createThumbnail($sourcePath, $destPath, $width = 300, $height = 200) {
    if (!extension_loaded('gd')) {
        return false;
    }

    // Get image info
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return false;
    }

    $mimeType = $imageInfo['mime'];
    list($origWidth, $origHeight) = $imageInfo;

    // Calculate dimensions
    $ratio = min($width / $origWidth, $height / $origHeight);
    $newWidth = (int)($origWidth * $ratio);
    $newHeight = (int)($origHeight * $ratio);

    // Create image resource
    switch ($mimeType) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $source = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }

    if (!$source) {
        return false;
    }

    // Create thumbnail
    $thumb = imagecreatetruecolor($newWidth, $newHeight);

    // Preserve transparency for PNG and GIF
    if ($mimeType == 'image/png' || $mimeType == 'image/gif') {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
        imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $transparent);
    }

    // Resize image
    if (!imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight)) {
        imagedestroy($source);
        imagedestroy($thumb);
        return false;
    }

    // Save thumbnail
    $success = false;
    switch ($mimeType) {
        case 'image/jpeg':
            $success = imagejpeg($thumb, $destPath, 85);
            break;
        case 'image/png':
            $success = imagepng($thumb, $destPath, 6);
            break;
        case 'image/gif':
            $success = imagegif($thumb, $destPath);
            break;
    }

    // Clean up
    imagedestroy($source);
    imagedestroy($thumb);

    return $success;
}

// Get client IP address
function getClientIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '';
}

// Get browser information
function getBrowser() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $browser = "Unknown";
    $platform = "Unknown";

    // Get browser
    if (preg_match('/MSIE|Trident/i', $userAgent)) {
        $browser = "Internet Explorer";
    } elseif (preg_match('/Firefox/i', $userAgent)) {
        $browser = "Firefox";
    } elseif (preg_match('/Chrome/i', $userAgent)) {
        $browser = "Chrome";
    } elseif (preg_match('/Safari/i', $userAgent)) {
        $browser = "Safari";
    } elseif (preg_match('/Edge/i', $userAgent)) {
        $browser = "Edge";
    } elseif (preg_match('/Opera|OPR/i', $userAgent)) {
        $browser = "Opera";
    }

    // Get platform
    if (preg_match('/Windows/i', $userAgent)) {
        $platform = "Windows";
    } elseif (preg_match('/Mac/i', $userAgent)) {
        $platform = "Mac";
    } elseif (preg_match('/Linux/i', $userAgent)) {
        $platform = "Linux";
    } elseif (preg_match('/Android/i', $userAgent)) {
        $platform = "Android";
    } elseif (preg_match('/iOS|iPhone|iPad/i', $userAgent)) {
        $platform = "iOS";
    }

    return [
        'browser' => $browser,
        'platform' => $platform,
        'user_agent' => $userAgent
    ];
}

// Log debug information
function debugLog($message, $context = []) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        error_log("[$timestamp] SPARK DEBUG: $message$contextStr");
    }
}

// API response helper
function apiResponse($success, $message = '', $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');

    $response = [
        'success' => $success,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response);
    exit;
}

// Success API response
function apiSuccess($message = '', $data = null) {
    apiResponse(true, $message, $data);
}

// Error API response
function apiError($message, $statusCode = 400, $data = null) {
    apiResponse(false, $message, $data, $statusCode);
}

// Format time for display
function formatTime($dateString) {
    if (empty($dateString)) {
        return '';
    }
    $timestamp = is_numeric($dateString) ? $dateString : strtotime($dateString);
    return date('h:i A', $timestamp);
}

// Generate unique QR code ID
function generateQRCodeId() {
    return 'QR' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

// Generate unique certificate number
function generateCertificateNumber() {
    return 'CERT' . date('Y') . strtoupper(substr(uniqid(), -8));
}

// Execute INSERT query with raw SQL (for compatibility with admin pages)
function executeInsert($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return getPDO()->lastInsertId();
}

// Generate thumbnail - simplified wrapper
function generateThumbnail($imagePath, $folder, $width = 300, $height = 200) {
    // Construct full source path if relative
    $sourcePath = file_exists($imagePath) ? $imagePath : UPLOAD_PATH . $folder . '/' . $imagePath;
    
    if (!file_exists($sourcePath)) {
        error_log("Thumbnail source not found: $sourcePath");
        return false;
    }
    
    // Create thumbnails directory
    $thumbDir = UPLOAD_PATH . $folder . '/thumbnails/';
    if (!is_dir($thumbDir)) {
        mkdir($thumbDir, 0755, true);
    }
    
    $fileName = basename($imagePath);
    $destPath = $thumbDir . 'thumb_' . $fileName;
    
    return createThumbnail($sourcePath, $destPath, $width, $height);
}
?>