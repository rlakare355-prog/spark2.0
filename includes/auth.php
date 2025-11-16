<?php
// SPARK Platform - Authentication System

if (!function_exists('generateToken')) {
    require_once __DIR__ . '/config.php';
}
if (!function_exists('getPDO')) {
    require_once __DIR__ . '/database.php';
}
if (!class_exists('MailjetService')) {
    require_once __DIR__ . '/MailjetService.php';
}

// Hash password
function hashPassword($password) {
    // Use PASSWORD_ARGON2ID if available (PHP 7.2+), otherwise fallback to PASSWORD_DEFAULT
    $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    return password_hash($password, $algorithm, ['cost' => HASH_COST]);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Generate verification token
function generateVerificationToken() {
    return generateToken(64);
}

// Send verification email
function sendVerificationEmail($email, $token) {
    try {
        $mailjet = new MailjetService();
        $verificationLink = SITE_URL . "/student/verify.php?token=" . urlencode($token);

        // Get student name for personalization
        $student = dbFetch("SELECT first_name FROM students WHERE email = ?", [$email]);
        $name = $student ? $student['first_name'] : 'Student';

        return $mailjet->sendVerificationEmail($email, $name, $verificationLink);
    } catch (Exception $e) {
        error_log("Verification email failed: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Send password reset email
function sendPasswordResetEmail($email, $token) {
    try {
        $mailjet = new MailjetService();
        $resetLink = SITE_URL . "/student/reset-password.php?token=" . urlencode($token);

        // Get student name for personalization
        $student = dbFetch("SELECT first_name FROM students WHERE email = ?", [$email]);
        $name = $student ? $student['first_name'] : 'Student';

        return $mailjet->sendPasswordResetEmail($email, $name, $resetLink);
    } catch (Exception $e) {
        error_log("Password reset email failed: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Send welcome email
function sendWelcomeEmail($email, $firstName) {
    try {
        $mailjet = new MailjetService();
        $subject = "Welcome to SPARK - " . SITE_NAME;

        $body = "
            <h2>Welcome to SPARK, $firstName!</h2>
            <p>Your account has been successfully created and verified.</p>
            <p>You can now:</p>
            <ul>
                <li>Register for events</li>
                <li>Join research projects</li>
                <li>Access certificates</li>
                <li>View opportunities</li>
                <li>Mark attendance with QR codes</li>
            </ul>
            <p><a href='" . SITE_URL . "/student/' style='background-color: #00ff88; color: #000; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Dashboard</a></p>
            <p>Best regards,<br>SPARK Team</p>
        ";

        return $mailjet->sendEmail($email, $subject, $body);
    } catch (Exception $e) {
        error_log("Welcome email failed: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Send event registration confirmation
function sendEventRegistrationConfirmation($email, $name, $eventTitle, $eventDate, $amountPaid = 0) {
    try {
        $mailjet = new MailjetService();
        return $mailjet->sendEventRegistrationEmail($email, $name, $eventTitle, $eventDate, $amountPaid);
    } catch (Exception $e) {
        error_log("Event registration email failed: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Send payment confirmation
function sendPaymentConfirmationEmail($email, $name, $eventTitle, $amount, $transactionId) {
    try {
        $mailjet = new MailjetService();
        return $mailjet->sendPaymentConfirmation($email, $name, $eventTitle, $amount, $transactionId);
    } catch (Exception $e) {
        error_log("Payment confirmation email failed: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Send certificate notification
function sendCertificateNotification($email, $name, $certificateTitle, $certificateLink) {
    try {
        $mailjet = new MailjetService();
        return $mailjet->sendCertificateEmail($email, $name, $certificateTitle, $certificateLink);
    } catch (Exception $e) {
        error_log("Certificate email failed: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Send opportunity notification
function sendOpportunityNotification($email, $name, $opportunityTitle, $opportunityType) {
    try {
        $mailjet = new MailjetService();
        return $mailjet->sendOpportunityNotification($email, $name, $opportunityTitle, $opportunityType);
    } catch (Exception $e) {
        error_log("Opportunity email failed: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Register new student
function registerStudent($data) {
    // Validate required fields
    $required = ['first_name', 'last_name', 'prn', 'email', 'contact_no', 'department', 'year', 'password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }

    // Validate department
    $validDepartments = ['CSE', 'CY', 'AIML', 'ALDS', 'Integrated-B.tech'];
    if (!in_array($data['department'], $validDepartments)) {
        throw new Exception("Invalid department");
    }

    // Validate year
    $validYears = ['FY', 'SY', 'TY', 'FINAL YEAR'];
    if (!in_array($data['year'], $validYears)) {
        throw new Exception("Invalid year");
    }

    // Check if email already exists
    if (dbCount('students', 'email = ?', [$data['email']]) > 0) {
        throw new Exception("Email already registered");
    }

    // Check if PRN already exists
    if (dbCount('students', 'prn = ?', [$data['prn']]) > 0) {
        throw new Exception("PRN already registered");
    }

    // Hash password
    $hashedPassword = hashPassword($data['password']);

    // Generate verification token
    $verificationToken = generateVerificationToken();

    // Insert student
    $studentData = [
        'first_name' => $data['first_name'],
        'middle_name' => $data['middle_name'] ?? null,
        'last_name' => $data['last_name'],
        'prn' => $data['prn'],
        'email' => $data['email'],
        'contact_no' => $data['contact_no'],
        'department' => $data['department'],
        'year' => $data['year'],
        'password_hash' => $hashedPassword,
        'role' => 'student',
        'is_email_verified' => 1
    ];

    try {
        $studentId = dbInsert('students', $studentData);

        // Email verification removed - student can login immediately
        // Log registration activity
        logActivity($studentId, 'register', 'system', null, 'Student registered successfully');

        return $studentId;
    } catch (Exception $e) {
        throw new Exception("Registration failed: " . $e->getMessage());
    }
}

// Login student
function loginStudent($email, $password) {
    $student = dbFetch("
        SELECT id, first_name, last_name, email, password_hash, role
        FROM students
        WHERE email = ?
    ", [$email]);

    if (!$student) {
        throw new Exception("Invalid email or password");
    }

    // Email verification removed - students can login immediately after registration

    if (!verifyPassword($password, $student['password_hash'])) {
        throw new Exception("Invalid email or password");
    }

    // Set session
    $_SESSION['user_id'] = $student['id'];
    $_SESSION['first_name'] = $student['first_name'];
    $_SESSION['last_name'] = $student['last_name'];
    $_SESSION['email'] = $student['email'];
    $_SESSION['role'] = $student['role'];

    // Log activity
    logActivity($student['id'], 'login', 'system', null, 'User logged in');

    return $student;
}

// Login admin
function loginAdmin($email, $password) {
    $admin = dbFetch("
        SELECT id, first_name, last_name, email, password_hash, role
        FROM students
        WHERE email = ? AND role IN ('super_admin', 'admin', 'event_coordinator', 'research_coordinator', 'domain_lead', 'management_head', 'accountant')
    ", [$email]);

    if (!$admin) {
        throw new Exception("Invalid email or password");
    }

    if (!verifyPassword($password, $admin['password_hash'])) {
        throw new Exception("Invalid email or password");
    }

    // Set session
    $_SESSION['user_id'] = $admin['id'];
    $_SESSION['first_name'] = $admin['first_name'];
    $_SESSION['last_name'] = $admin['last_name'];
    $_SESSION['email'] = $admin['email'];
    $_SESSION['role'] = $admin['role'];

    return $admin;
}

// Logout
function logout() {
    // Destroy session
    session_destroy();

    // Clear session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    return true;
}

// Verify email
function verifyEmail($token) {
    $student = dbFetch("
        SELECT id, email, first_name
        FROM students
        WHERE email_verification_token = ?
    ", [$token]);

    if (!$student) {
        throw new Exception("Invalid verification token");
    }

    // Update student
    dbUpdate('students', [
        'is_email_verified' => true,
        'email_verification_token' => null
    ], 'id = ?', [$student['id']]);

    // Send welcome email
    sendWelcomeEmail($student['email'], $student['first_name']);

    return $student;
}

// Request password reset
function requestPasswordReset($email) {
    $student = dbFetch("
        SELECT id, first_name, email
        FROM students
        WHERE email = ? AND is_email_verified = 1
    ", [$email]);

    if (!$student) {
        throw new Exception("Email not found or not verified");
    }

    // Generate reset token
    $resetToken = generateVerificationToken();
    $expiryTime = date('Y-m-d H:i:s', time() + 3600); // 1 hour from now

    // Update student
    dbUpdate('students', [
        'reset_token' => $resetToken,
        'reset_token_expiry' => $expiryTime
    ], 'id = ?', [$student['id']]);

    // Send reset email
    sendPasswordResetEmail($student['email'], $resetToken);

    return true;
}

// Reset password
function resetPassword($token, $newPassword) {
    $student = dbFetch("
        SELECT id, reset_token_expiry
        FROM students
        WHERE reset_token = ?
    ", [$token]);

    if (!$student) {
        throw new Exception("Invalid reset token");
    }

    if (strtotime($student['reset_token_expiry']) < time()) {
        throw new Exception("Reset token has expired");
    }

    // Hash new password
    $hashedPassword = hashPassword($newPassword);

    // Update student
    dbUpdate('students', [
        'password_hash' => $hashedPassword,
        'reset_token' => null,
        'reset_token_expiry' => null
    ], 'id = ?', [$student['id']]);

    return true;
}

// Change password
function changePassword($userId, $currentPassword, $newPassword) {
    $student = dbFetch("
        SELECT password_hash
        FROM students
        WHERE id = ?
    ", [$userId]);

    if (!$student) {
        throw new Exception("User not found");
    }

    if (!verifyPassword($currentPassword, $student['password_hash'])) {
        throw new Exception("Current password is incorrect");
    }

    // Hash new password
    $hashedPassword = hashPassword($newPassword);

    // Update student
    dbUpdate('students', [
        'password_hash' => $hashedPassword
    ], 'id = ?', [$userId]);

    return true;
}

// Check current user permissions
function hasPermission($permission) {
    $role = getUserRole();

    $permissions = [
        'super_admin' => ['*'],
        'admin' => ['*'],
        'event_coordinator' => ['manage_events', 'manage_certificates', 'manage_attendance'],
        'research_coordinator' => ['manage_research', 'view_analytics'],
        'domain_lead' => ['manage_research', 'view_analytics'],
        'management_head' => ['manage_team', 'manage_homepage', 'manage_gallery'],
        'accountant' => ['manage_payments', 'view_reports'],
        'student' => ['view_events', 'register_events', 'view_dashboard']
    ];

    $userPermissions = $permissions[$role] ?? [];

    return in_array('*', $userPermissions) || in_array($permission, $userPermissions);
}

// Get current user data
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    return dbFetch("
        SELECT id, first_name, middle_name, last_name, prn, email, contact_no,
               department, year, profile_image, role, is_email_verified
        FROM students
        WHERE id = ?
    ", [$_SESSION['user_id']]);
}

// Check if registration is enabled
function isRegistrationEnabled() {
    return getSetting('registration_enabled') === true || getSetting('registration_enabled') === '1';
}
?>