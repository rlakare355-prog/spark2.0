<?php
// SPARK Platform - Admin Attendance Management
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireAdmin();

// Include QR code library
require_once __DIR__ . '/../vendor/autoload.php';
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\InvalidCharacterException;

// Handle attendance operations
$action = $_GET['action'] ?? 'dashboard';

if ($action === 'generate_qr' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $event_id = (int)$_POST['event_id'];
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $location = sanitize($_POST['location']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Validation
        if (empty($title) || empty($event_id)) {
            throw new Exception("Title and event are required");
        }

        // Generate unique QR code ID
        $qr_code_id = generateQRCodeId();

        // Insert QR code session
        $sql = "INSERT INTO attendance_qr_codes (
            qr_code_id, title, description, event_id, start_date, end_date,
            location, is_active, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $params = [
            $qr_code_id, $title, $description, $event_id, $start_date, $end_date,
            $location, $is_active, $_SESSION['user_id']
        ];

        $qr_id = executeInsert($sql, $params);

        // Generate QR code image
        $qr_data = json_encode([
            'type' => 'attendance',
            'id' => $qr_code_id,
            'event_id' => $event_id,
            'title' => $title,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'location' => $location
        ]);

        $qr_code = new QrCode($qr_data);
        $qr_code->setSize(300);
        $qr_code->setMargin(10);
        $qr_code->setErrorCorrectionLevel(ErrorCorrectionLevel::HIGH);

        $writer = new PngWriter();
        $qr_code_image = $qr_code->write($writer);

        // Save QR code image
        $qr_dir = __DIR__ . '/../uploads/qr_codes';
        if (!file_exists($qr_dir)) {
            mkdir($qr_dir, 0755, true);
        }

        $qr_filename = 'attendance_' . $qr_code_id . '.png';
        $qr_path = $qr_dir . '/' . $qr_filename;
        file_put_contents($qr_path, $qr_code_image);

        // Update QR code record with image path
        executeUpdate("UPDATE attendance_qr_codes SET qr_image_path = ? WHERE id = ?", ['uploads/qr_codes/' . $qr_filename, $qr_id]);

        // Log activity
        logActivity('qr_code_generated', "QR code generated for '{$title}'", $_SESSION['user_id'], $qr_id);

        $_SESSION['success'][] = "QR code generated successfully!";
        header('Location: attendance.php?action=qr_codes');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: attendance.php?action=generate_qr');
        exit();
    }
}

if ($action === 'scan_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $qr_code_id = sanitize($_POST['qr_code_id']);
        $student_id = (int)$_POST['student_id'];
        $manual_overrides = $_POST['manual_overrides'] ?? [];

        // Validation
        if (empty($qr_code_id) || empty($student_id)) {
            throw new Exception("QR code and student are required");
        }

        // Get QR code session info
        $qr_session = fetchRow("
            SELECT qrc.*, e.title as event_title, e.start_date as event_start_date, e.end_date as event_end_date
            FROM attendance_qr_codes qrc
            LEFT JOIN events e ON qrc.event_id = e.id
            WHERE qrc.qr_code_id = ? AND qrc.is_active = 1
        ", [$qr_code_id]);

        if (!$qr_session) {
            throw new Exception("Invalid or inactive QR code");
        }

        // Check if already marked present
        $existing_attendance = fetchRow("
            SELECT * FROM attendance_records
            WHERE qr_code_id = ? AND student_id = ?
        ", [$qr_code_id, $student_id]);

        if ($existing_attendance) {
            throw new Exception("Attendance already recorded for this session");
        }

        // Check if attendance is valid (time range, location, etc.)
        $current_time = date('Y-m-d H:i:s');
        $current_date = date('Y-m-d');

        $is_valid = true;
        $status = 'present';
        $notes = '';

        // Time validation
        if ($current_date < $qr_session['start_date'] || $current_date > $qr_session['end_date']) {
            $is_valid = false;
            $status = 'invalid_time';
            $notes = 'Attendance time is outside valid range';
        }

        // Manual override validation
        if (isset($manual_overrides['time_valid']) && $manual_overrides['time_valid'] === 'true') {
            $is_valid = true;
            $status = 'present';
        }

        // Insert attendance record
        $sql = "INSERT INTO attendance_records (
            qr_code_id, student_id, event_id, student_name, student_email, student_phone,
            scan_time, scan_location, scan_method, status, notes, is_valid,
            manual_overrides, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $student = fetchRow("SELECT first_name, last_name, email, phone FROM students WHERE id = ?", [$student_id]);

        $scan_method = $_POST['scan_method'] ?? 'qr_scan';
        $scan_location = sanitize($_POST['scan_location'] ?? 'Admin Panel');
        $manual_overrides_json = json_encode($manual_overrides);

        executeUpdate($sql, [
            $qr_code_id, $student_id, $qr_session['event_id'],
            $student['first_name'] . ' ' . $student['last_name'],
            $student['email'], $student['phone'], $current_time,
            $scan_location, $scan_method, $status, $notes, $is_valid,
            $manual_overrides_json
        ]);

        // Log activity
        logActivity('attendance_scanned', "Attendance scanned for {$student['first_name']} {$student['last_name']}", $_SESSION['user_id']);

        $_SESSION['success'][] = "Attendance recorded successfully!";
        header('Location: attendance.php?action=scan');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: attendance.php?action=scan');
        exit();
    }
}

if ($action === 'manual_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $event_id = (int)$_POST['event_id'];
        $student_id = (int)$_POST['student_id'];
        $status = sanitize($_POST['attendance_status']);
        $notes = sanitize($_POST['notes'] ?? '');
        $check_in_time = $_POST['check_in_time'];
        $check_out_time = $_POST['check_out_time'];

        // Validation
        if (empty($event_id) || empty($student_id) || empty($status)) {
            throw new Exception("Event, student, and status are required");
        }

        $student = fetchRow("SELECT first_name, last_name, email FROM students WHERE id = ?", [$student_id]);
        if (!$student) {
            throw new Exception("Student not found");
        }

        // Insert manual attendance record
        $sql = "INSERT INTO attendance_records (
            event_id, student_id, student_name, student_email, status,
            check_in_time, check_out_time, notes, scan_method, is_valid,
            manual_overrides, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'manual_entry', 1, ?, NOW())";

        executeUpdate($sql, [
            $event_id, $student_id, $student['first_name'] . ' ' . $student['last_name'],
            $student['email'], $status, $check_in_time, $check_out_time, $notes,
            json_encode(['manual_entry' => true, 'status' => $status])
        ]);

        // Log activity
        logActivity('attendance_manual', "Manual attendance added for {$student['first_name']} {$student['last_name']}", $_SESSION['user_id']);

        $_SESSION['success'][] = "Manual attendance recorded successfully!";
        header('Location: attendance.php?action=manual');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: attendance.php?action=manual');
        exit();
    }
}

if ($action === 'delete_qr') {
    try {
        $qr_id = (int)$_GET['id'];

        // Get QR info for logging
        $qr_info = fetchRow("SELECT title, qr_image_path FROM attendance_qr_codes WHERE id = ?", [$qr_id]);
        if (!$qr_info) {
            throw new Exception("QR code not found");
        }

        // Delete QR image
        if (!empty($qr_info['qr_image_path']) && file_exists(__DIR__ . '/../' . $qr_info['qr_image_path'])) {
            unlink(__DIR__ . '/../' . $qr_info['qr_image_path']);
        }

        // Delete QR code
        executeUpdate("DELETE FROM attendance_qr_codes WHERE id = ?", [$qr_id]);

        // Log activity
        logActivity('qr_code_deleted', "QR code '{$qr_info['title']}' deleted", $_SESSION['user_id'], $qr_id);

        $_SESSION['success'][] = "QR code deleted successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: attendance.php?action=qr_codes');
    exit();
}

if ($action === 'export_attendance') {
    try {
        $format = $_GET['format'] ?? 'csv';
        $event_id = (int)($_GET['event_id'] ?? 0);
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');

        // Build query
        $sql = "
            SELECT ar.*, s.student_id, s.email, e.title as event_title
            FROM attendance_records ar
            JOIN students s ON ar.student_id = s.id
            LEFT JOIN events e ON ar.event_id = e.id
            WHERE DATE(ar.created_at) BETWEEN ? AND ?
        ";
        $params = [$start_date, $end_date];

        if ($event_id) {
            $sql .= " AND ar.event_id = ?";
            $params[] = $event_id;
        }

        $sql .= " ORDER BY ar.created_at DESC";

        $attendance_records = fetchAll($sql, $params);

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="attendance_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Student Name', 'Email', 'Event', 'Status', 'Scan Time', 'Notes']);

            foreach ($attendance_records as $record) {
                fputcsv($output, [
                    $record['id'],
                    $record['student_name'],
                    $record['student_email'],
                    $record['event_title'],
                    $record['status'],
                    $record['scan_time'],
                    $record['notes']
                ]);
            }

            fclose($output);
            exit();
        }

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: attendance.php');
        exit();
    }
}

$page_title = 'Attendance Management';
$breadcrumb = [
    ['name' => 'Dashboard', 'link' => 'index.php', 'active' => false],
    ['name' => 'Attendance', 'link' => '', 'active' => true]
];

include __DIR__ . '/../templates/admin_header.php';
?>

<!-- Attendance Management Section -->
<section class="admin-section py-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="section-title">
                            <i class="fas fa-qrcode me-2"></i> Attendance Management
                        </h2>
                        <p class="text-muted">QR code generation, scanning, and attendance tracking</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-info" onclick="showStatistics()">
                            <i class="fas fa-chart-bar me-2"></i> Statistics
                        </button>
                        <button class="btn btn-outline-success" onclick="exportAttendance()">
                            <i class="fas fa-download me-2"></i> Export
                        </button>
                    </div>
                </div>

                <?php include __DIR__ . '/../includes/alerts.php'; ?>

                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs mb-4" id="attendanceTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $action === 'dashboard' ? 'active' : ''; ?>" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $action === 'qr_codes' ? 'active' : ''; ?>" id="qr-codes-tab" data-bs-toggle="tab" data-bs-target="#qr-codes" type="button" role="tab">
                            <i class="fas fa-qrcode me-2"></i> QR Codes
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $action === 'scan' ? 'active' : ''; ?>" id="scan-tab" data-bs-toggle="tab" data-bs-target="#scan" type="button" role="tab">
                            <i class="fas fa-camera me-2"></i> Scan Attendance
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $action === 'manual' ? 'active' : ''; ?>" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual" type="button" role="tab">
                            <i class="fas fa-user-check me-2"></i> Manual Entry
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="attendanceTabContent">
                    <!-- Dashboard Tab -->
                    <div class="tab-pane fade <?php echo $action === 'dashboard' ? 'show active' : ''; ?>" id="dashboard" role="tabpanel">
                        <div class="card admin-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-line me-2"></i> Attendance Overview
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Statistics -->
                                <div class="row mb-4">
                                    <div class="col-md-2">
                                        <div class="stat-card">
                                            <div class="stat-icon bg-primary">
                                                <i class="fas fa-qrcode"></i>
                                            </div>
                                            <div class="stat-content">
                                                <h3><?php echo fetchColumn("SELECT COUNT(*) FROM attendance_qr_codes", []); ?></h3>
                                                <p>QR Codes</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="stat-card">
                                            <div class="stat-icon bg-success">
                                                <i class="fas fa-user-check"></i>
                                            </div>
                                            <div class="stat-content">
                                                <h3><?php echo fetchColumn("SELECT COUNT(*) FROM attendance_records", []); ?></h3>
                                                <p>Total Attendance</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="stat-card">
                                            <div class="stat-icon bg-info">
                                                <i class="fas fa-calendar-check"></i>
                                            </div>
                                            <div class="stat-content">
                                                <h3><?php echo fetchColumn("SELECT COUNT(*) FROM attendance_records WHERE DATE(created_at) = CURDATE()", []); ?></h3>
                                                <p>Today</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="stat-card">
                                            <div class="stat-icon bg-warning">
                                                <i class="fas fa-clock"></i>
                                            </div>
                                            <div class="stat-content">
                                                <h3><?php echo fetchColumn("SELECT COUNT(*) FROM attendance_records WHERE status = 'present'", []); ?></h3>
                                                <p>Present</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="stat-card">
                                            <div class="stat-icon bg-secondary">
                                                <i class="fas fa-calendar-day"></i>
                                            </div>
                                            <div class="stat-content">
                                                <h3><?php echo fetchColumn("SELECT COUNT(DISTINCT DATE(scan_time)) FROM attendance_records", []); ?></h3>
                                                <p>Active Days</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="stat-card">
                                            <div class="stat-icon bg-danger">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </div>
                                            <div class="stat-content">
                                                <h3><?php echo fetchColumn("SELECT COUNT(*) FROM attendance_records WHERE is_valid = 0", []); ?></h3>
                                                <p>Invalid</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Recent Activity -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <h6 class="text-primary mb-3">Recent Attendance Activity</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Time</th>
                                                        <th>Student</th>
                                                        <th>Event</th>
                                                        <th>Status</th>
                                                        <th>Method</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $recent_attendance = fetchAll("
                                                        SELECT ar.*, s.first_name, s.last_name, e.title as event_title
                                                        FROM attendance_records ar
                                                        JOIN students s ON ar.student_id = s.id
                                                        LEFT JOIN events e ON ar.event_id = e.id
                                                        ORDER BY ar.created_at DESC
                                                        LIMIT 10
                                                    ");

                                                    foreach ($recent_attendance as $attendance):
                                                    ?>
                                                    <tr>
                                                        <td><?php echo formatDateTime($attendance['created_at']); ?></td>
                                                        <td><?php echo htmlspecialchars($attendance['student_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($attendance['event_title'] ?? 'N/A'); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $attendance['is_valid'] ? 'success' : 'danger'; ?>">
                                                                <?php echo ucfirst($attendance['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info"><?php echo $attendance['scan_method']; ?></span>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- QR Codes Tab -->
                    <div class="tab-pane fade <?php echo $action === 'qr_codes' ? 'show active' : ''; ?>" id="qr-codes" role="tabpanel">
                        <?php if ($action === 'generate_qr'): ?>
                            <!-- Generate QR Code Form -->
                            <?php include __DIR__ . '/../includes/admin_qr_generate_form.php'; ?>
                        <?php else: ?>
                            <!-- QR Codes List -->
                            <div class="card admin-card">
                                <div class="card-header">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <h5 class="card-title mb-0">
                                                <i class="fas fa-list me-2"></i> Generated QR Codes
                                            </h5>
                                        </div>
                                        <div class="col-auto">
                                            <a href="attendance.php?action=generate_qr" class="btn btn-primary">
                                                <i class="fas fa-plus me-2"></i> Generate QR Code
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>QR Image</th>
                                                    <th>Title</th>
                                                    <th>Event</th>
                                                    <th>Location</th>
                                                    <th>Date Range</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $qr_codes = fetchAll("
                                                    SELECT qrc.*, e.title as event_title
                                                    FROM attendance_qr_codes qrc
                                                    LEFT JOIN events e ON qrc.event_id = e.id
                                                    ORDER BY qrc.created_at DESC
                                                ");

                                                foreach ($qr_codes as $qr):
                                                ?>
                                                <tr>
                                                    <td>
                                                        <?php if (!empty($qr['qr_image_path'])): ?>
                                                            <img src="<?php echo SITE_URL . '/' . $qr['qr_image_path']; ?>"
                                                                 alt="QR Code" style="width: 50px; height: 50px; border-radius: 8px;">
                                                        <?php else: ?>
                                                            <div class="qr-placeholder">
                                                                <i class="fas fa-qrcode fa-2x"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($qr['title']); ?></strong>
                                                            <br><small class="text-muted">ID: <?php echo $qr['qr_code_id']; ?></small>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($qr['event_title'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($qr['location']); ?></td>
                                                    <td>
                                                        <div>
                                                            <?php echo formatDate($qr['start_date']); ?>
                                                            <?php if ($qr['end_date']): ?>
                                                                <br>to <?php echo formatDate($qr['end_date']); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $qr['is_active'] ? 'success' : 'secondary'; ?>">
                                                            <?php echo $qr['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-sm btn-outline-info" onclick="viewQRCode(<?php echo $qr['id']; ?>)" title="View Details">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-primary" onclick="downloadQRCode(<?php echo $qr['id']; ?>)" title="Download QR">
                                                                <i class="fas fa-download"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteQRCode(<?php echo $qr['id']; ?>)" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Scan Attendance Tab -->
                    <div class="tab-pane fade <?php echo $action === 'scan' ? 'show active' : ''; ?>" id="scan" role="tabpanel">
                        <div class="card admin-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-camera me-2"></i> Scan Attendance
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="attendance.php?action=scan_attendance" class="needs-validation" novalidate>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="qr_code_id" class="form-label">QR Code ID / Scan QR</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" id="qr_code_id" name="qr_code_id"
                                                           required placeholder="Scan QR or enter ID manually">
                                                    <button class="btn btn-outline-secondary" type="button" onclick="startQRScanner()">
                                                        <i class="fas fa-camera"></i>
                                                    </button>
                                                </div>
                                                <div id="qrScanner" style="display: none;" class="mt-3">
                                                    <div id="qr-video-container" style="width: 100%; max-width: 400px; margin: 0 auto;">
                                                        <video id="qr-video" style="width: 100%;"></video>
                                                        <canvas id="qr-canvas" style="display: none;"></canvas>
                                                    </div>
                                                    <div class="text-center mt-2">
                                                        <button class="btn btn-secondary" onclick="stopQRScanner()">
                                                            <i class="fas fa-stop"></i> Stop Scanning
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="student_id" class="form-label">Select Student</label>
                                                <select class="form-select" id="student_id" name="student_id" required>
                                                    <option value="">Select student...</option>
                                                    <?php
                                                    $students = fetchAll("SELECT id, first_name, last_name, email FROM students WHERE status = 'active' ORDER BY first_name, last_name");
                                                    foreach ($students as $student):
                                                    ?>
                                                    <option value="<?php echo $student['id']; ?>">
                                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['email'] . ')'); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="scan_method" class="form-label">Scan Method</label>
                                                <select class="form-select" id="scan_method" name="scan_method">
                                                    <option value="qr_scan">QR Code Scan</option>
                                                    <option value="manual_entry">Manual Entry</option>
                                                    <option value="biometric">Biometric</option>
                                                    <option value="rfid">RFID Card</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="scan_location" class="form-label">Scan Location</label>
                                                <input type="text" class="form-control" id="scan_location" name="scan_location"
                                                       value="Admin Panel" placeholder="e.g., Main Gate, Room 101">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Manual Overrides -->
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <h6 class="text-primary">Manual Overrides</h6>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="time_valid" name="manual_overrides[time_valid]" value="true">
                                                        <label class="form-check-label" for="time_valid">
                                                            Override Time Validation
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="location_valid" name="manual_overrides[location_valid]" value="true">
                                                        <label class="form-check-label" for="location_valid">
                                                            Override Location Check
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="force_present" name="manual_overrides[force_present]" value="true">
                                                        <label class="form-check-label" for="force_present">
                                                            Force Present Status
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-center">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-check-circle me-2"></i> Mark Attendance
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Manual Entry Tab -->
                    <div class="tab-pane fade <?php echo $action === 'manual' ? 'show active' : ''; ?>" id="manual" role="tabpanel">
                        <div class="card admin-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-user-check me-2"></i> Manual Attendance Entry
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="attendance.php?action=manual_attendance" class="needs-validation" novalidate>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="event_id" class="form-label">Event</label>
                                                <select class="form-select" id="event_id" name="event_id" required>
                                                    <option value="">Select event...</option>
                                                    <?php
                                                    $events = fetchAll("SELECT id, title, start_date FROM events WHERE status IN ('ongoing', 'completed') ORDER BY start_date DESC");
                                                    foreach ($events as $event):
                                                    ?>
                                                    <option value="<?php echo $event['id']; ?>">
                                                        <?php echo htmlspecialchars($event['title'] . ' (' . formatDate($event['start_date']) . ')'); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="student_id" class="form-label">Student</label>
                                                <select class="form-select" id="student_id" name="student_id" required>
                                                    <option value="">Select student...</option>
                                                    <?php
                                                    $students = fetchAll("SELECT id, first_name, last_name, email FROM students WHERE status = 'active' ORDER BY first_name, last_name");
                                                    foreach ($students as $student):
                                                    ?>
                                                    <option value="<?php echo $student['id']; ?>">
                                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="attendance_status" class="form-label">Attendance Status</label>
                                                <select class="form-select" id="attendance_status" name="attendance_status" required>
                                                    <option value="present">Present</option>
                                                    <option value="absent">Absent</option>
                                                    <option value="late">Late</option>
                                                    <option value="excused">Excused</option>
                                                    <option value="on_leave">On Leave</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="check_in_time" class="form-label">Check-in Time</label>
                                                <input type="datetime-local" class="form-control" id="check_in_time" name="check_in_time">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="check_out_time" class="form-label">Check-out Time</label>
                                                <input type="datetime-local" class="form-control" id="check_out_time" name="check_out_time">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3"
                                                  placeholder="Additional notes about attendance..."></textarea>
                                    </div>

                                    <div class="d-flex justify-content-center">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save me-2"></i> Save Attendance
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- QR Code Details Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">QR Code Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="qrModalContent">
                <!-- QR code details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Statistics Modal -->
<div class="modal fade" id="statisticsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Attendance Statistics</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Daily Attendance</h6>
                        <canvas id="dailyChart"></canvas>
                    </div>
                    <div class="col-md-6">
                        <h6>Attendance by Event</h6>
                        <canvas id="eventChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Admin Attendance Management Styles */
.stat-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 255, 136, 0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.5rem;
}

.stat-content h3 {
    margin: 0;
    font-size: 2rem;
    font-weight: bold;
    color: var(--accent-color);
}

.stat-content p {
    margin: 0;
    color: var(--text-muted);
}

.qr-placeholder {
    width: 50px;
    height: 50px;
    background: var(--glass-bg);
    border: 2px dashed var(--border-color);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
}

/* QR Scanner Styles */
#qr-video-container {
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    overflow: hidden;
}

#qr-video {
    background: var(--card-bg);
    border-radius: 10px;
}

/* Tab styling */
.nav-tabs .nav-link {
    color: var(--text-muted);
    border-color: var(--border-color);
}

.nav-tabs .nav-link.active {
    color: var(--accent-color);
    background-color: var(--card-bg);
    border-color: var(--accent-color);
}

.nav-tabs .nav-link:hover {
    color: var(--accent-color);
    border-color: var(--accent-color);
}

.tab-content {
    background: var(--card-bg);
    border-radius: 0 12px 12px 12px;
    padding: 1.5rem;
}

/* Badge colors */
.badge.bg-primary { background: var(--primary-color) !important; }
.badge.bg-success { background: var(--success-color) !important; }
.badge.bg-warning { background: var(--warning-color) !important; }
.badge.bg-info { background: var(--info-color) !important; }
.badge.bg-danger { background: var(--error-color) !important; }
.badge.bg-secondary { background: var(--text-muted) !important; }

/* Form enhancements */
.form-control:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 15px rgba(0, 255, 136, 0.2);
}

.btn-lg {
    padding: 0.75rem 1.5rem;
    font-size: 1.125rem;
}

/* Loading state */
.loading {
    pointer-events: none;
    opacity: 0.6;
}

.loading::after {
    content: '';
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-left: 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
// Form validation
document.querySelector('.needs-validation')?.addEventListener('submit', function(e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});

// QR Scanner functionality
let qrScannerActive = false;
let qrScannerInterval;

function startQRScanner() {
    const scannerDiv = document.getElementById('qrScanner');
    const video = document.getElementById('qr-video');

    scannerDiv.style.display = 'block';
    qrScannerActive = true;

    // Get user media
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(function(stream) {
            video.srcObject = stream;
            video.play();

            // Start QR code scanning (simplified implementation)
            setTimeout(() => {
                // In a real implementation, this would use a QR scanning library
                alert('QR code scanner started. Point camera at QR code.');
            }, 1000);
        })
        .catch(function(err) {
            alert('Camera access denied or not available: ' + err.message);
        });
}

function stopQRScanner() {
    const video = document.getElementById('qr-video');
    const scannerDiv = document.getElementById('qrScanner');

    if (video.srcObject) {
        video.srcObject.getTracks().forEach(track => track.stop());
    }

    scannerDiv.style.display = 'none';
    qrScannerActive = false;
}

// Modal functions
function viewQRCode(qrId) {
    const modal = new bootstrap.Modal(document.getElementById('qrModal'));
    const content = document.getElementById('qrModalContent');

    // Show loading
    content.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    modal.show();

    // Fetch QR code details
    fetch(`../api/attendance.php?action=get_qr&id=${qrId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const qr = data.data;
                content.innerHTML = `
                    <div class="qr-details">
                        <div class="text-center mb-3">
                            ${qr.qr_image_path ? `<img src="${SITE_URL}/${qr.qr_image_path}" alt="QR Code" style="max-width: 200px;">` : ''}
                        </div>
                        <table class="table">
                            <tr><th>QR Code ID:</th><td>${qr.qr_code_id}</td></tr>
                            <tr><th>Title:</th><td>${qr.title}</td></tr>
                            <tr><th>Description:</th><td>${qr.description || 'N/A'}</td></tr>
                            <tr><th>Event:</th><td>${qr.event_title || 'N/A'}</td></tr>
                            <tr><th>Location:</th><td>${qr.location || 'N/A'}</td></tr>
                            <tr><th>Start Date:</th><td>${qr.start_date || 'N/A'}</td></tr>
                            <tr><th>End Date:</th><td>${qr.end_date || 'N/A'}</td></tr>
                            <tr><th>Status:</th><td><span class="badge bg-${qr.is_active ? 'success' : 'secondary'}">${qr.is_active ? 'Active' : 'Inactive'}</span></td></tr>
                            <tr><th>Created:</th><td>${formatDateTime(qr.created_at)}</td></tr>
                        </table>
                    </div>
                `;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Error loading QR code details</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error loading QR code details</div>';
        });
}

function downloadQRCode(qrId) {
    window.open(`../api/attendance.php?action=download_qr&id=${qrId}`, '_blank');
}

function deleteQRCode(qrId) {
    if (confirm('Are you sure you want to delete this QR code? This action cannot be undone.')) {
        window.location.href = `attendance.php?action=delete_qr&id=${qrId}`;
    }
}

function showStatistics() {
    const modal = new bootstrap.Modal(document.getElementById('statisticsModal'));
    modal.show();

    // Initialize charts
    setTimeout(() => {
        // Daily attendance chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Attendance',
                    data: [85, 92, 78, 88, 95, 45, 30],
                    borderColor: '#00ff88',
                    backgroundColor: 'rgba(0, 255, 136, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Event attendance chart
        const eventCtx = document.getElementById('eventChart').getContext('2d');
        new Chart(eventCtx, {
            type: 'doughnut',
            data: {
                labels: ['Workshop A', 'Seminar B', 'Competition C', 'Training D'],
                datasets: [{
                    data: [45, 30, 25, 50],
                    backgroundColor: [
                        '#00ff88',
                        '#ffc107',
                        '#17a2b8',
                        '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }, 500);
}

function exportAttendance() {
    const format = 'csv';
    const startDate = prompt('Start date (YYYY-MM-DD):', date('Y-m-01'));
    const endDate = prompt('End date (YYYY-MM-DD):', date('Y-m-d'));

    if (startDate && endDate) {
        window.location.href = `attendance.php?action=export_attendance&format=${format}&start_date=${startDate}&end_date=${endDate}`;
    }
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

// Clear form data
<?php if (isset($_SESSION['form_data'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    <?php unset($_SESSION['form_data']); ?>
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
