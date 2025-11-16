<?php
// SPARK Platform - Attendance API
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Require login for all attendance operations
requireLogin();

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'verify_qr':
            handleQRVerification();
            break;

        case 'get_attendance_stats':
            handleGetAttendanceStats();
            break;

        default:
            apiError('Invalid action', 400);
    }
} catch (Exception $e) {
    apiError($e->getMessage(), 500);
}

function handleQRVerification() {
    $qrData = $_POST['qr_data'] ?? null;

    if (!$qrData || !is_array($qrData)) {
        apiError('Invalid QR data', 400);
    }

    // Validate QR data format
    if (!isset($qrData['type']) || $qrData['type'] !== 'attendance') {
        apiError('Invalid QR code type', 400);
    }

    $eventId = intval($qrData['event_id'] ?? 0);
    $userId = $_SESSION['user_id'];

    if (!$eventId) {
        apiError('Invalid event ID', 400);
    }

    // Verify event exists and is active
    $event = dbFetch("
        SELECT e.*, er.student_id as registration_id, er.payment_status
        FROM events e
        LEFT JOIN event_registrations er ON e.id = er.event_id AND er.student_id = ?
        WHERE e.id = ? AND e.is_active = 1
    ", [$eventId, $userId]);

    if (!$event) {
        apiError('Event not found or registration required', 404);
    }

    // Check if event is currently valid for attendance
    $eventTime = strtotime($event['event_date']);
    $currentTime = time();
    $timeDiff = $eventTime - $currentTime;

    // Allow attendance 2 hours before event until 2 hours after event
    if ($timeDiff > 7200 || $timeDiff < -7200) {
        apiError('Event is not currently active for attendance', 400);
    }

    // Check if already marked attendance
    $existing = dbFetch("
        SELECT id, status FROM attendance
        WHERE event_id = ? AND student_id = ?
    ", [$eventId, $userId]);

    if ($existing) {
        if ($existing['status'] === 'present') {
            apiError('Attendance already marked', 409);
        } else {
            apiError('Attendance already processed', 409);
        }
    }

    // Validate registration payment
    if ($event['fee'] > 0 && $event['payment_status'] !== 'paid') {
        apiError('Payment required before marking attendance', 400);
    }

    // Check QR code validity (token-based)
    $qrToken = $qrData['id'] ?? '';
    $validToken = false;

    // For this demo, accept tokens that start with specific patterns
    if (strpos($qrToken, 'ATT_') === 0) {
        $validToken = true;
    } elseif (strpos($qrToken, 'QR_') === 0) {
        $validToken = true;
    }

    if (!$validToken) {
        apiError('Invalid QR code', 400);
    }

    // Generate unique attendance token
    $attendanceToken = 'ATT_' . generateToken(8) . '_' . time();

    dbTransaction(function($pdo) use ($eventId, $userId, $attendanceToken, $event) {
        // Insert attendance record
        $attendanceData = [
            'event_id' => $eventId,
            'student_id' => $userId,
            'qr_token' => $attendanceToken,
            'status' => 'present',
            'marked_by' => 'qr_scan',
            'scan_time' => date('Y-m-d H:i:s')
        ];

        dbInsert('attendance', $attendanceData);

        // Log activity
        logActivity($userId, 'create', 'attendance', null, "QR attendance marked for event: {$event['title']}");

        return true;
    });

    // Send confirmation notification
    try {
        require_once __DIR__ . '/../includes/MailjetService.php';
        $mailjet = new MailjetService();
        $student = dbFetch("SELECT first_name, email FROM students WHERE id = ?", [$userId]);

        $result = $mailjet->sendEmail(
            $student['email'],
            "Attendance Confirmation - {$event['title']}",
            "<h2>Attendance Confirmed!</h2>
            <p>Dear {$student['first_name']},</p>
            <p>Your attendance has been successfully marked for the following event:</p>
            <div style='background: var(--card-bg); padding: 20px; border-radius: 10px; margin: 20px 0;'>
                <h3>{$event['title']}</h3>
                <p><strong>Date & Time:</strong> " . formatDate($event['event_date'], true) . "</p>
                <p><strong>Location:</strong> " . ($event['location'] ?? 'TBD') . "</p>
                <p><strong>Attendance ID:</strong> $attendanceToken</p>
                <p><strong>Marked at:</strong> " . date('Y-m-d H:i:s') . "</p>
            </div>
            <p>This confirms your presence at the event.</p>
            <p>Best regards,<br>SPARK Team</p>"
        );

        if (!$result['success']) {
            error_log("Failed to send attendance confirmation: " . ($result['error'] ?? 'Unknown error'));
        }
    } catch (Exception $e) {
        error_log("Failed to send attendance confirmation: " . $e->getMessage());
    }

    apiSuccess('Attendance marked successfully', [
        'event_title' => $event['title'],
        'event_date' => $event['event_date'],
        'location' => $event['location'],
        'attendance_token' => $attendanceToken
    ]);
}

function handleGetAttendanceStats() {
    $userId = $_SESSION['user_id'];
    $startDate = sanitize($_GET['start_date'] ?? '') ?: date('Y-m-01');
    $endDate = sanitize($_GET['end_date'] ?? '') ?: date('Y-m-d');

    // Get attendance statistics
    $stats = dbFetch("
        SELECT
            COUNT(DISTINCT e.id) as total_events,
            COUNT(DISTINCT CASE WHEN a.status = 'present' THEN e.id END) as attended_events,
            COUNT(DISTINCT CASE WHEN a.status = 'present' THEN e.id END) * 100.0 / COUNT(DISTINCT e.id) as attendance_rate
        FROM events e
        LEFT JOIN event_registrations er ON e.id = er.event_id AND er.student_id = ?
        LEFT JOIN attendance a ON e.id = a.event_id AND a.student_id = ?
        WHERE e.is_active = 1 AND e.event_date BETWEEN ? AND ?
        GROUP BY e.id
    ", [$userId, $userId, $startDate, $endDate]);

    // Get monthly breakdown
    $monthlyStats = dbFetchAll("
        SELECT
            DATE_FORMAT(e.event_date, '%Y-%m') as month,
            COUNT(DISTINCT e.id) as total_events,
            COUNT(DISTINCT CASE WHEN a.status = 'present' THEN e.id END) as attended_events
        FROM events e
        LEFT JOIN event_registrations er ON e.id = er.event_id AND er.student_id = ?
        LEFT JOIN attendance a ON e.id = a.event_id AND a.student_id = ?
        WHERE e.is_active = 1 AND e.event_date BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(e.event_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ", [$userId, $userId, $startDate, $endDate]);

    // Get recent attendance
    $recentAttendance = dbFetchAll("
        SELECT
            e.title,
            e.event_date,
            e.location,
            a.scan_time,
            a.qr_token,
            a.marked_by
        FROM attendance a
        JOIN events e ON a.event_id = e.id
        WHERE a.student_id = ? AND a.status = 'present'
        ORDER BY a.scan_time DESC
        LIMIT 10
    ", [$userId]);

    apiSuccess('Attendance statistics retrieved', [
        'total_events' => $stats['total_events'] ?? 0,
        'attended_events' => $stats['attended_events'] ?? 0,
        'attendance_rate' => $stats['attendance_rate'] ?? 0,
        'monthly_breakdown' => $monthlyStats,
        'recent_attendance' => $recentAttendance
    ]);
}

// Helper function for API responses
function apiSuccess($message, $data = []) {
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function apiError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'code' => $code
    ]);
    exit;
}
?>