<?php
require_once '../includes/student_header.php';
requireRole(['student', 'event_coordinator', 'research_coordinator', 'domain_lead', 'management_head', 'accountant', 'super_admin', 'admin']);

// Get current user
$user = getCurrentUser();
$userId = $user['id'];

// Handle attendance actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['mark_manual_attendance'])) {
            $eventId = intval($_POST['event_id']);
            $accessCode = sanitize($_POST['access_code']);

            // Verify event and access code
            $event = dbFetch("
                SELECT id, title, location, manual_access_code
                FROM events
                WHERE id = ?
            ", [$eventId]);

            if (!$event) {
                throw new Exception("Event not found");
            }

            if ($event['manual_access_code'] !== $accessCode) {
                throw new Exception("Invalid access code");
            }

            // Check if already marked attendance
            $existing = dbFetch("
                SELECT id FROM attendance
                WHERE event_id = ? AND student_id = ?
            ", [$eventId, $userId]);

            if ($existing) {
                throw new Exception("Attendance already marked for this event");
            }

            // Mark attendance manually
            $attendanceData = [
                'event_id' => $eventId,
                'student_id' => $userId,
                'qr_token' => 'MANUAL_' . generateToken(10),
                'status' => 'present',
                'marked_by' => 'manual'
            ];

            dbInsert('attendance', $attendanceData);

            // Log activity
            logActivity($_SESSION['user_id'], 'create', 'attendance', null, "Manual attendance marked for event: {$event['title']}");

            $message = "Attendance marked successfully!";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get upcoming events for attendance
$upcomingEvents = dbFetchAll("
    SELECT e.*,
           er.id as registration_id,
           er.payment_status,
           er.attendance_status,
           a.id as attendance_id,
           a.scan_time as attendance_time,
           a.qr_token,
           a.marked_by
    FROM events e
    LEFT JOIN event_registrations er ON e.id = er.event_id AND er.student_id = ?
    LEFT JOIN attendance a ON e.id = a.event_id AND a.student_id = ?
    WHERE e.event_date > DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY e.event_date ASC
", [$userId, $userId]);

// Get recent attendance history
$attendanceHistory = dbFetchAll("
    SELECT e.title, e.event_date, e.location,
           a.scan_time, a.status, a.qr_token, a.marked_by
    FROM attendance a
    JOIN events e ON a.event_id = e.id
    WHERE a.student_id = ?
    ORDER BY a.scan_time DESC
    LIMIT 10
", [$userId]);

// Get attendance statistics
$stats = [
    'total_events' => dbCount('event_registrations', 'student_id = ?', [$userId]),
    'attended_events' => dbCount('attendance', 'student_id = ? AND status = ?', [$userId, 'present']),
    'upcoming_events' => count(array_filter($upcomingEvents, function($event) {
        return empty($event['attendance_id']);
    }))
];
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="fas fa-qrcode me-2"></i>
                    QR Attendance
                </h2>
                <button class="btn btn-outline-secondary" onclick="window.location.href='dashboard.php'">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </button>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Attendance Statistics -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card admin-card h-100">
                <div class="card-body text-center">
                    <div class="stat-icon bg-info mb-3">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $stats['total_events']; ?></h3>
                    <p class="stat-label">Total Events</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card admin-card h-100">
                <div class="card-body text-center">
                    <div class="stat-icon bg-success mb-3">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $stats['attended_events']; ?></h3>
                    <p class="stat-label">Attended</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card admin-card h-100">
                <div class="card-body text-center">
                    <div class="stat-icon bg-warning mb-3">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $stats['upcoming_events']; ?></h3>
                    <p class="stat-label">Pending</p>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Scanner Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card admin-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-camera me-2"></i>
                        QR Code Scanner
                    </h5>
                </div>
                <div class="card-body">
                    <div class="qr-scanner-container text-center">
                        <div id="qr-scanner" class="qr-scanner">
                            <div class="scanner-overlay">
                                <div class="scanner-border">
                                    <div class="scanner-corner top-left"></div>
                                    <div class="scanner-corner top-right"></div>
                                    <div class="scanner-corner bottom-left"></div>
                                    <div class="scanner-corner bottom-right"></div>
                                </div>
                                <div class="scanner-line"></div>
                            </div>
                        </div>
                        <div class="scanner-controls mt-3">
                            <button id="startScanner" class="btn btn-primary me-2">
                                <i class="fas fa-play me-2"></i>Start Scanner
                            </button>
                            <button id="stopScanner" class="btn btn-danger" style="display: none;">
                                <i class="fas fa-stop me-2"></i>Stop Scanner
                            </button>
                        </div>
                        <div id="scannerResult" class="scanner-result mt-3"></div>
                    </div>

                    <div class="alert alert-info mt-3">
                        <h6><i class="fas fa-info-circle me-2"></i>How to use QR Attendance:</h6>
                        <ol class="mb-0">
                            <li>Click "Start Scanner" to activate your camera</li>
                            <li>Point camera at the event QR code displayed by the organizer</li>
                            <li>Wait for automatic scan and verification</li>
                            <li>Your attendance will be marked instantly</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Events -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card admin-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Upcoming Events
                        <span class="badge bg-primary ms-2"><?php echo count($upcomingEvents); ?> events</span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($upcomingEvents): ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Date & Time</th>
                                        <th>Location</th>
                                        <th>Registration Status</th>
                                        <th>Attendance Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcomingEvents as $event): ?>
                                        <tr>
                                            <td>
                                                <div class="event-info">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h6>
                                                    <?php if ($event['fee'] > 0): ?>
                                                        <span class="badge bg-warning">₹<?php echo number_format($event['fee'], 2); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo formatDate($event['event_date'], true); ?></td>
                                            <td><?php echo htmlspecialchars($event['location'] ?? 'TBD'); ?></td>
                                            <td>
                                                <span class="badge <?php echo $event['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning'; ?>">
                                                    <?php echo ucfirst($event['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($event['attendance_id']): ?>
                                                    <span class="badge bg-success">Present</span>
                                                    <small class="text-muted d-block"><?php echo formatDate($event['attendance_time'], true); ?></small>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!$event['attendance_id'] && $event['payment_status'] === 'paid'): ?>
                                                    <button class="btn btn-sm btn-primary" onclick="startQRScanner()">
                                                        <i class="fas fa-qrcode me-2"></i>Scan QR
                                                    </button>
                                                <?php elseif ($event['manual_access_code']): ?>
                                                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#manualAttendanceModal" data-event-id="<?php echo $event['id']; ?>">
                                                        <i class="fas fa-key me-2"></i>Manual Check-in
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted small">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No upcoming events</h5>
                            <p class="text-muted">Register for events to see them here</p>
                            <a href="events.php" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Browse Events
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance History -->
    <div class="row">
        <div class="col-12">
            <div class="card admin-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history me-2"></i>
                        Recent Attendance History
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($attendanceHistory): ?>
                        <div class="timeline">
                            <?php foreach ($attendanceHistory as $attendance): ?>
                                <div class="timeline-item">
                                    <div class="timeline-icon bg-success">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6 class="timeline-title"><?php echo htmlspecialchars($attendance['title']); ?></h6>
                                        <p class="timeline-action">
                                            Attended on <?php echo formatDate($attendance['scan_time'], true); ?>
                                        </p>
                                        <div class="timeline-meta">
                                            <span class="badge bg-<?php echo $attendance['marked_by'] === 'manual' ? 'info' : 'success'; ?>">
                                                <?php echo ucfirst($attendance['marked_by']); ?>
                                            </span>
                                            <span class="text-muted ms-2">
                                                Token: <?php echo htmlspecialchars($attendance['qr_token']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-clock fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No attendance history yet</h5>
                            <p class="text-muted">Your attendance records will appear here after attending events</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Manual Attendance Modal -->
<div class="modal fade" id="manualAttendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">
                    <i class="fas fa-key me-2"></i>
                    Manual Check-in
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="event_id" id="manualEventId">

                    <div class="mb-3">
                        <label for="access_code" class="form-label">Access Code</label>
                        <input type="text" class="form-control bg-secondary text-light border-secondary"
                               id="access_code" name="access_code" required
                               placeholder="Enter the access code provided by event organizer">
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            The event organizer will provide a special access code for manual check-in
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Note:</h6>
                        <p class="mb-0">This is an alternative attendance method when QR scanning is not available.</p>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="mark_manual_attendance" class="btn btn-primary">
                        <i class="fas fa-check me-2"></i>Mark Attendance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Attendance Styles */
.qr-scanner {
    position: relative;
    width: 100%;
    max-width: 400px;
    height: 300px;
    background: #000;
    border-radius: 15px;
    margin: 0 auto;
    overflow: hidden;
}

.scanner-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
}

.scanner-border {
    position: absolute;
    top: 20px;
    left: 20px;
    right: 20px;
    bottom: 20px;
    border: 2px solid rgba(0, 255, 136, 0.5);
    border-radius: 10px;
}

.scanner-corner {
    position: absolute;
    width: 20px;
    height: 20px;
    border-color: var(--accent-color);
    border-style: solid;
    border-width: 4px;
}

.scanner-corner.top-left {
    top: -4px;
    left: -4px;
    border-right: none;
    border-bottom: none;
}

.scanner-corner.top-right {
    top: -4px;
    right: -4px;
    border-left: none;
    border-bottom: none;
}

.scanner-corner.bottom-left {
    bottom: -4px;
    left: -4px;
    border-right: none;
    border-top: none;
}

.scanner-corner.bottom-right {
    bottom: -4px;
    right: -4px;
    border-left: none;
    border-top: none;
}

.scanner-line {
    position: absolute;
    top: 20px;
    left: 20px;
    right: 20px;
    height: 2px;
    background: var(--accent-color);
    animation: scan 2s linear infinite;
}

@keyframes scan {
    0% {
        top: 20px;
        opacity: 1;
    }
    50% {
        top: calc(100% - 20px);
        opacity: 1;
    }
    51% {
        opacity: 0;
    }
    100% {
        top: 20px;
        opacity: 0;
    }
}

.scanner-result {
    padding: 1rem;
    background: var(--card-bg);
    border-radius: 10px;
    border: 1px solid var(--border-color);
    min-height: 50px;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    font-size: 1.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
    color: var(--accent-color);
}

.stat-label {
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: 0.7;
}

/* Timeline Styles */
.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0.5rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--border-color);
}

.timeline-item {
    position: relative;
    margin-bottom: 2rem;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-icon {
    position: absolute;
    left: -2rem;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 0.8rem;
    z-index: 1;
}

.timeline-content {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    padding: 1rem;
    backdrop-filter: blur(10px);
}

.timeline-title {
    color: var(--text-primary);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.timeline-action {
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.timeline-meta {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    font-size: 0.9rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .qr-scanner {
        height: 250px;
    }

    .stat-card {
        margin-bottom: 1rem;
    }

    .timeline {
        padding-left: 1.5rem;
    }

    .timeline::before {
        left: 0.25rem;
    }

    .timeline-icon {
        left: -1.5rem;
        width: 1.5rem;
        height: 1.5rem;
    }
}
</style>

<script>
// Manual Attendance Modal
document.getElementById('manualAttendanceModal')?.addEventListener('show.bs.modal', function(event) {
    const eventId = event.relatedTarget.getAttribute('data-event-id');
    document.getElementById('manualEventId').value = eventId;
});

// QR Scanner functionality
let scannerStream = null;
let scannerActive = false;

function startQRScanner() {
    const video = document.getElementById('qr-video');
    if (!video) {
        createVideoElement();
    }

    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' }
        })
        .then(function(stream) {
            scannerStream = stream;
            video.srcObject = stream;
            video.style.display = 'block';
            scannerActive = true;
            updateScannerControls();

            // Start QR code detection
            startQRCodeDetection();
        })
        .catch(function(error) {
            console.error('Camera access denied:', error);
            showScannerMessage('Camera access denied. Please allow camera permissions.', 'error');
        });
    } else {
        showScannerMessage('Camera API not supported in this browser.', 'error');
    }
}

function stopQRScanner() {
    if (scannerStream) {
        scannerStream.getTracks().forEach(track => track.stop());
        scannerStream = null;
    }

    const video = document.getElementById('qr-video');
    if (video) {
        video.style.display = 'none';
    }

    scannerActive = false;
    updateScannerControls();
}

function createVideoElement() {
    const video = document.createElement('video');
    video.id = 'qr-video';
    video.style.width = '100%';
    video.style.height = '100%';
    video.style.objectFit = 'cover';
    video.autoplay = true;
    document.getElementById('qr-scanner').appendChild(video);
}

function updateScannerControls() {
    const startBtn = document.getElementById('startScanner');
    const stopBtn = document.getElementById('stopScanner');

    if (scannerActive) {
        startBtn.style.display = 'none';
        stopBtn.style.display = 'inline-block';
    } else {
        startBtn.style.display = 'inline-block';
        stopBtn.style.display = 'none';
    }
}

function showScannerMessage(message, type = 'info') {
    const resultDiv = document.getElementById('scannerResult');
    resultDiv.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show">
            <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
            ${message}
        </div>
    `;
}

function startQRCodeDetection() {
    // Placeholder for QR code detection
    // In a real implementation, you would use a library like:
    // - qr-scanner (JavaScript)
    // - ZXing-js
    // - Or send video frames to a backend for processing

    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');

    function scanFrame() {
        if (!scannerActive) return;

        const video = document.getElementById('qr-video');
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            // QR code detection would happen here
            // For demo, we'll simulate finding a QR code after 3 seconds
            setTimeout(() => {
                if (scannerActive) {
                    simulateQRCodeFound();
                }
            }, 3000);
        }

        requestAnimationFrame(scanFrame);
    }

    scanFrame();
}

function simulateQRCodeFound() {
    // Simulate finding a QR code
    const qrData = {
        type: 'attendance',
        id: 'ATT_' + Date.now(),
        event_id: 123,
        timestamp: new Date().toISOString()
    };

    showScannerMessage('QR Code detected! Processing...', 'info');

    // Send QR data to server for verification
    fetch('api/attendance.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'verify_qr',
            qr_data: qrData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showScannerMessage('Attendance marked successfully!', 'success');
            stopQRScanner();
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showScannerMessage(data.message || 'Invalid QR code', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showScannerMessage('Failed to verify QR code', 'error');
    });
}

// Scanner control event listeners
document.getElementById('startScanner')?.addEventListener('click', startQRScanner);
document.getElementById('stopScanner')?.addEventListener('click', stopQRScanner);

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include __DIR__ . '/../includes/student_footer.php'; ?>