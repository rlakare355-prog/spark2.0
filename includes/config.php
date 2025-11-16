<?php
// SPARK Platform - Configuration File

// Database settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'spark_platform');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site settings
define('SITE_URL', 'http://localhost/spark');
define('SITE_NAME', 'SPARK - Sanjivani Platform for AI, Research & Knowledge');
define('ADMIN_EMAIL', 'admin@sanjivani.edu');

// Security settings
define('HASH_COST', 12);
define('SESSION_LIFETIME', 86400); // 24 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// File upload settings
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('UPLOAD_PATH', __DIR__ . '/../assets/images/');

// Email settings
define('FROM_EMAIL', 'noreply@sanjivani.edu');
define('FROM_NAME', 'SPARK Platform');
define('EMAIL_FROM', 'noreply@sanjivani.edu');
define('EMAIL_FROM_NAME', 'SPARK Platform');

// Razorpay settings (will be updated from database)
define('RAZORPAY_KEY_ID', '');
define('RAZORPAY_KEY_SECRET', '');

// Mailjet settings (will be updated from database)
if (!defined('MAILJET_API_KEY')) {
    define('MAILJET_API_KEY', '');
}
if (!defined('MAILJET_API_SECRET')) {
    define('MAILJET_API_SECRET', '');
}
if (!defined('MAILJET_TEST_MODE')) {
    define('MAILJET_TEST_MODE', false); // Set to true for testing without sending emails
}

// Email settings are already defined above

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load settings from database
function loadSettings() {
    static $settings = null;

    if ($settings === null) {
        // In a real implementation, these would be loaded from database
        // For now, using defaults
        $settings = [
            'razorpay_key_id' => RAZORPAY_KEY_ID,
            'razorpay_key_secret' => RAZORPAY_KEY_SECRET,
            'mailjet_api_key' => MAILJET_API_KEY,
            'mailjet_api_secret' => MAILJET_API_SECRET,
            'mailjet_test_mode' => MAILJET_TEST_MODE,
            'registration_enabled' => true,
            'email_verification_required' => false
        ];
    }

    return $settings;
}

// Get setting value
function getSetting($key) {
    $settings = loadSettings();
    return isset($settings[$key]) ? $settings[$key] : null;
}

// Update setting in database (placeholder function)
function updateSetting($key, $value) {
    // Implementation would update database
    return true;
}

// Generate random token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
}

// Validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current user role
function getUserRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

// Check if user is admin
function isAdmin() {
    return getUserRole() === 'super_admin' || getUserRole() === 'admin';
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: ../student/login.php');
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('HTTP/1.0 403 Forbidden');
        echo 'Access denied';
        exit();
    }
}

// Check if specific role is allowed
function requireRole($allowedRoles) {
    requireLogin();

    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }

    if (!in_array(getUserRole(), $allowedRoles)) {
        header('HTTP/1.0 403 Forbidden');
        echo 'Access denied';
        exit();
    }
}

// Log activity
function logActivity($adminId, $action, $module, $recordId = null, $description = '') {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (admin_id, action_type, module, record_id, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $adminId,
            $action,
            $module,
            $recordId,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Activity logging failed: " . $e->getMessage());
    }
}

// Note: Settings will be loaded from database in production
// For now, using the constants defined above
?>