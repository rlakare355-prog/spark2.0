<?php
// SPARK Platform - Admin Certificate Management
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireAdmin();

// Handle certificate operations
$action = $_GET['action'] ?? 'list';

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $type = sanitize($_POST['type']);
        $student_id = (int)$_POST['student_id'];
        $event_id = (int)($_POST['event_id'] ?? 0);
        $issue_date = $_POST['issue_date'];
        $template_id = (int)($_POST['template_id'] ?? 1);
        $status = sanitize($_POST['status'] ?? 'active');
        $signature_1 = sanitize($_POST['signature_1'] ?? '');
        $signature_2 = sanitize($_POST['signature_2'] ?? '');
        $additional_info = sanitize($_POST['additional_info'] ?? '');

        // Validation
        if (empty($title) || empty($type) || empty($student_id) || empty($issue_date)) {
            throw new Exception("Title, type, student, and issue date are required");
        }

        // Check student exists
        $student = fetchRow("SELECT first_name, last_name, email FROM students WHERE id = ?", [$student_id]);
        if (!$student) {
            throw new Exception("Student not found");
        }

        // Generate unique certificate number
        $certificate_number = generateCertificateNumber();

        // Insert certificate
        $sql = "INSERT INTO certificates (
            certificate_number, title, description, type, student_id, event_id,
            issue_date, template_id, status, signature_1, signature_2,
            additional_info, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $params = [
            $certificate_number, $title, $description, $type, $student_id, $event_id,
            $issue_date, $template_id, $status, $signature_1, $signature_2,
            $additional_info, $_SESSION['user_id']
        ];

        $certificate_id = executeInsert($sql, $params);

        // Generate PDF certificate
        $pdf_path = generateCertificatePDF($certificate_id);

        if ($pdf_path) {
            executeUpdate("UPDATE certificates SET pdf_path = ? WHERE id = ?", [$pdf_path, $certificate_id]);
        }

        // Send email notification (in real implementation)
        $to = $student['email'];
        $subject = "Certificate Issued - SPARK Platform";
        $message = "
            <h2>Certificate Issued</h2>
            <p>Dear {$student['first_name']} {$student['last_name']},</p>
            <p>A certificate '{$title}' has been issued to you.</p>
            <p><strong>Certificate Details:</strong></p>
            <ul>
                <li>Certificate Number: {$certificate_number}</li>
                <li>Title: {$title}</li>
                <li>Issue Date: {$issue_date}</li>
                <li>Type: {$type}</li>
            </ul>
            <p>You can download your certificate from your student dashboard.</p>
            <p>Best regards,<br>SPARK Team</p>
        ";

        // sendEmail($to, $subject, $message); // Uncomment when email service is ready

        // Log activity
        logActivity('certificate_created', "Certificate '{$title}' issued to {$student['first_name']} {$student['last_name']}", $_SESSION['user_id'], $certificate_id);

        $_SESSION['success'][] = "Certificate created and issued successfully!";
        header('Location: certificates.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        $_SESSION['form_data'] = $_POST;
        header('Location: certificates.php?action=create');
        exit();
    }
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $certificate_id = (int)$_POST['certificate_id'];
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $type = sanitize($_POST['type']);
        $student_id = (int)$_POST['student_id'];
        $event_id = (int)($_POST['event_id'] ?? 0);
        $issue_date = $_POST['issue_date'];
        $template_id = (int)($_POST['template_id'] ?? 1);
        $status = sanitize($_POST['status'] ?? 'active');
        $signature_1 = sanitize($_POST['signature_1'] ?? '');
        $signature_2 = sanitize($_POST['signature_2'] ?? '');
        $additional_info = sanitize($_POST['additional_info'] ?? '');

        // Validation
        if (empty($title) || empty($type) || empty($student_id) || empty($issue_date)) {
            throw new Exception("Title, type, student, and issue date are required");
        }

        // Update certificate
        $sql = "UPDATE certificates SET
            title = ?, description = ?, type = ?, student_id = ?, event_id = ?,
            issue_date = ?, template_id = ?, status = ?, signature_1 = ?, signature_2 = ?,
            additional_info = ?, updated_at = NOW()
        WHERE id = ?";

        $params = [
            $title, $description, $type, $student_id, $event_id,
            $issue_date, $template_id, $status, $signature_1, $signature_2,
            $additional_info, $certificate_id
        ];

        executeUpdate($sql, $params);

        // Regenerate PDF if requested
        if (isset($_POST['regenerate_pdf']) && $_POST['regenerate_pdf'] === '1') {
            $pdf_path = generateCertificatePDF($certificate_id);
            if ($pdf_path) {
                executeUpdate("UPDATE certificates SET pdf_path = ? WHERE id = ?", [$pdf_path, $certificate_id]);
            }
        }

        // Log activity
        logActivity('certificate_updated', "Certificate '{$title}' updated", $_SESSION['user_id'], $certificate_id);

        $_SESSION['success'][] = "Certificate updated successfully!";
        header('Location: certificates.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: certificates.php?action=edit&id=' . $_POST['certificate_id']);
        exit();
    }
}

if ($action === 'delete') {
    try {
        $certificate_id = (int)$_GET['id'];

        // Get certificate info for logging and cleanup
        $certificate = fetchRow("
            SELECT c.*, s.first_name, s.last_name, c.pdf_path
            FROM certificates c
            JOIN students s ON c.student_id = s.id
            WHERE c.id = ?
        ", [$certificate_id]);

        if (!$certificate) {
            throw new Exception("Certificate not found");
        }

        // Delete PDF file if exists
        if (!empty($certificate['pdf_path']) && file_exists(__DIR__ . '/../' . $certificate['pdf_path'])) {
            unlink(__DIR__ . '/../' . $certificate['pdf_path']);
        }

        // Delete certificate
        executeUpdate("DELETE FROM certificates WHERE id = ?", [$certificate_id]);

        // Log activity
        logActivity('certificate_deleted', "Certificate '{$certificate['title']}' deleted (Student: {$certificate['first_name']} {$certificate['last_name']})", $_SESSION['user_id'], $certificate_id);

        $_SESSION['success'][] = "Certificate deleted successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: certificates.php');
    exit();
}

if ($action === 'bulk_issue' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $event_id = (int)$_POST['event_id'];
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $type = sanitize($_POST['type']);
        $issue_date = $_POST['issue_date'];
        $template_id = (int)($_POST['template_id'] ?? 1);
        $student_ids = array_map('intval', $_POST['student_ids'] ?? []);

        if (empty($event_id) || empty($title) || empty($type) || empty($student_ids)) {
            throw new Exception("Event, title, type, and at least one student are required");
        }

        $success_count = 0;
        $error_count = 0;

        foreach ($student_ids as $student_id) {
            try {
                // Check student exists
                $student = fetchRow("SELECT first_name, last_name FROM students WHERE id = ?", [$student_id]);
                if (!$student) continue;

                // Generate unique certificate number
                $certificate_number = generateCertificateNumber();

                // Insert certificate
                $sql = "INSERT INTO certificates (
                    certificate_number, title, description, type, student_id, event_id,
                    issue_date, template_id, status, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                executeInsert($sql, [
                    $certificate_number, $title, $description, $type, $student_id, $event_id,
                    $issue_date, $template_id, 'active', $_SESSION['user_id']
                ]);

                // Get certificate ID for PDF generation
                $cert_id = fetchColumn("SELECT LAST_INSERT_ID()", []);

                // Generate PDF
                generateCertificatePDF($cert_id);

                $success_count++;

            } catch (Exception $e) {
                $error_count++;
            }
        }

        $_SESSION['success'][] = "Bulk certificate issuance completed: {$success_count} successful, {$error_count} failed";
        header('Location: certificates.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: certificates.php?action=bulk_issue');
        exit();
    }
}

if ($action === 'verify_certificate') {
    try {
        $certificate_number = sanitize($_GET['certificate_number']);

        if (empty($certificate_number)) {
            throw new Exception("Certificate number is required");
        }

        $certificate = fetchRow("
            SELECT c.*, s.first_name, s.last_name, s.email, e.title as event_title
            FROM certificates c
            JOIN students s ON c.student_id = s.id
            LEFT JOIN events e ON c.event_id = e.id
            WHERE c.certificate_number = ? AND c.status = 'active'
        ", [$certificate_number]);

        if (!$certificate) {
            $_SESSION['errors'][] = "Certificate not found or invalid";
        } else {
            $_SESSION['success'][] = "Certificate verified: Issued to {$certificate['first_name']} {$certificate['last_name']}";
        }

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: certificates.php');
    exit();
}

$page_title = 'Certificate Management';
$breadcrumb = [
    ['name' => 'Dashboard', 'link' => 'index.php', 'active' => false],
    ['name' => 'Certificates', 'link' => '', 'active' => true]
];

include __DIR__ . '/../templates/admin_header.php';
?>

<!-- Certificate Management Section -->
<section class="admin-section py-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="section-title">
                            <i class="fas fa-certificate me-2"></i> Certificate Management
                        </h2>
                        <p class="text-muted">Issue, manage, and verify certificates</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-warning" onclick="showVerificationModal()">
                            <i class="fas fa-check-circle me-2"></i> Verify Certificate
                        </button>
                        <button class="btn btn-outline-info" onclick="showBulkIssueModal()">
                            <i class="fas fa-users me-2"></i> Bulk Issue
                        </button>
                        <a href="certificates.php?action=create" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Issue Certificate
                        </a>
                    </div>
                </div>

                <?php include __DIR__ . '/../includes/alerts.php'; ?>

                <?php if ($action === 'create' || $action === 'edit'): ?>
                    <!-- Create/Edit Certificate Form -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-<?php echo $action === 'create' ? 'plus' : 'edit'; ?> me-2"></i>
                                <?php echo $action === 'create' ? 'Issue New Certificate' : 'Edit Certificate'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $certificate_data = $_SESSION['form_data'] ?? [];
                            if ($action === 'edit') {
                                $certificate_id = (int)$_GET['id'];
                                $certificate_data = fetchRow("
                                    SELECT c.*, s.first_name, s.last_name, s.email
                                    FROM certificates c
                                    JOIN students s ON c.student_id = s.id
                                    WHERE c.id = ?
                                ", [$certificate_id]);
                                if (!$certificate_data) {
                                    $_SESSION['errors'][] = "Certificate not found";
                                    header('Location: certificates.php');
                                    exit();
                                }
                            }
                            ?>

                            <form method="POST" class="needs-validation" novalidate>
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="certificate_id" value="<?php echo $certificate_data['id']; ?>">
                                <?php endif; ?>

                                <div class="row">
                                    <!-- Basic Information -->
                                    <div class="col-md-8">
                                        <h6 class="text-primary mb-3">Certificate Information</h6>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="title" class="form-label">Certificate Title *</label>
                                                    <input type="text" class="form-control" id="title" name="title"
                                                           value="<?php echo htmlspecialchars($certificate_data['title'] ?? ''); ?>"
                                                           required maxlength="255" placeholder="e.g., Workshop Participation, Competition Winner">
                                                    <div class="invalid-feedback">Please provide a certificate title</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="type" class="form-label">Certificate Type *</label>
                                                    <select class="form-select" id="type" name="type" required>
                                                        <option value="">Select Type</option>
                                                        <option value="participation" <?php echo ($certificate_data['type'] ?? '') === 'participation' ? 'selected' : ''; ?>>Participation</option>
                                                        <option value="achievement" <?php echo ($certificate_data['type'] ?? '') === 'achievement' ? 'selected' : ''; ?>>Achievement</option>
                                                        <option value="excellence" <?php echo ($certificate_data['type'] ?? '') === 'excellence' ? 'selected' : ''; ?>>Excellence</option>
                                                        <option value="completion" <?php echo ($certificate_data['type'] ?? '') === 'completion' ? 'selected' : ''; ?>>Completion</option>
                                                        <option value="merit" <?php echo ($certificate_data['type'] ?? '') === 'merit' ? 'selected' : ''; ?>>Merit</option>
                                                        <option value="appreciation" <?php echo ($certificate_data['type'] ?? '') === 'appreciation' ? 'selected' : ''; ?>>Appreciation</option>
                                                        <option value="leadership" <?php echo ($certificate_data['type'] ?? '') === 'leadership' ? 'selected' : ''; ?>>Leadership</option>
                                                        <option value="volunteer" <?php echo ($certificate_data['type'] ?? '') === 'volunteer' ? 'selected' : ''; ?>>Volunteer</option>
                                                    </select>
                                                    <div class="invalid-feedback">Please select a certificate type</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control" id="description" name="description"
                                                      rows="3" placeholder="Certificate description or purpose..."><?php echo htmlspecialchars($certificate_data['description'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="student_id" class="form-label">Student *</label>
                                                    <select class="form-select" id="student_id" name="student_id" required>
                                                        <option value="">Select Student</option>
                                                        <?php
                                                        $students = fetchAll("SELECT id, first_name, last_name, email FROM students WHERE status = 'active' ORDER BY first_name, last_name");
                                                        foreach ($students as $student):
                                                            $selected = ($certificate_data['student_id'] ?? '') == $student['id'] ? 'selected' : '';
                                                        ?>
                                                        <option value="<?php echo $student['id']; ?>" <?php echo $selected; ?>>
                                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['email'] . ')'); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div class="invalid-feedback">Please select a student</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="event_id" class="form-label">Related Event (Optional)</label>
                                                    <select class="form-select" id="event_id" name="event_id">
                                                        <option value="">Select Event</option>
                                                        <?php
                                                        $events = fetchAll("SELECT id, title, start_date FROM events WHERE status IN ('completed', 'ongoing') ORDER BY start_date DESC LIMIT 50");
                                                        foreach ($events as $event):
                                                            $selected = ($certificate_data['event_id'] ?? '') == $event['id'] ? 'selected' : '';
                                                        ?>
                                                        <option value="<?php echo $event['id']; ?>" <?php echo $selected; ?>>
                                                            <?php echo htmlspecialchars($event['title'] . ' (' . formatDate($event['start_date']) . ')'); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="issue_date" class="form-label">Issue Date *</label>
                                                    <input type="date" class="form-control" id="issue_date" name="issue_date"
                                                           value="<?php echo htmlspecialchars($certificate_data['issue_date'] ?? date('Y-m-d')); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="template_id" class="form-label">Certificate Template</label>
                                                    <select class="form-select" id="template_id" name="template_id">
                                                        <option value="1" <?php echo ($certificate_data['template_id'] ?? 1) == 1 ? 'selected' : ''; ?>>Professional Template</option>
                                                        <option value="2" <?php echo ($certificate_data['template_id'] ?? 1) == 2 ? 'selected' : ''; ?>>Modern Template</option>
                                                        <option value="3" <?php echo ($certificate_data['template_id'] ?? 1) == 3 ? 'selected' : ''; ?>>Classic Template</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="status" class="form-label">Status</label>
                                                    <select class="form-select" id="status" name="status">
                                                        <option value="active" <?php echo ($certificate_data['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo ($certificate_data['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                        <option value="revoked" <?php echo ($certificate_data['status'] ?? '') === 'revoked' ? 'selected' : ''; ?>>Revoked</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Template and Signature -->
                                    <div class="col-md-4">
                                        <h6 class="text-primary mb-3">Template & Signatures</h6>

                                        <div class="mb-3">
                                            <label for="signature_1" class="form-label">First Signature</label>
                                            <input type="text" class="form-control" id="signature_1" name="signature_1"
                                                   value="<?php echo htmlspecialchars($certificate_data['signature_1'] ?? ''); ?>"
                                                   placeholder="e.g., Principal, HOD">
                                        </div>

                                        <div class="mb-3">
                                            <label for="signature_2" class="form-label">Second Signature</label>
                                            <input type="text" class="form-control" id="signature_2" name="signature_2"
                                                   value="<?php echo htmlspecialchars($certificate_data['signature_2'] ?? ''); ?>"
                                                   placeholder="e.g., Coordinator, Director">
                                        </div>

                                        <div class="mb-3">
                                            <label for="additional_info" class="form-label">Additional Information</label>
                                            <textarea class="form-control" id="additional_info" name="additional_info"
                                                      rows="4" placeholder="Additional details, achievements, etc."><?php echo htmlspecialchars($certificate_data['additional_info'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="regenerate_pdf" name="regenerate_pdf" value="1">
                                                <label class="form-check-label" for="regenerate_pdf">
                                                    Regenerate PDF after update
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Preview Button -->
                                        <button type="button" class="btn btn-outline-info w-100" onclick="previewCertificate()">
                                            <i class="fas fa-eye me-2"></i> Preview Certificate
                                        </button>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <a href="certificates.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        <?php echo $action === 'create' ? 'Issue Certificate' : 'Update Certificate'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($action === 'bulk_issue'): ?>
                    <!-- Bulk Issue Certificate Form -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-users me-2"></i> Bulk Issue Certificates
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="needs-validation" novalidate>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="event_id" class="form-label">Select Event *</label>
                                            <select class="form-select" id="event_id" name="event_id" required onchange="loadEventParticipants()">
                                                <option value="">Select Event</option>
                                                <?php
                                                $events = fetchAll("SELECT id, title, start_date FROM events WHERE status = 'completed' ORDER BY start_date DESC LIMIT 20");
                                                foreach ($events as $event):
                                                ?>
                                                <option value="<?php echo $event['id']; ?>">
                                                    <?php echo htmlspecialchars($event['title'] . ' (' . formatDate($event['start_date']) . ')'); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">Select an event to load registered participants</div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="title" class="form-label">Certificate Title *</label>
                                            <input type="text" class="form-control" id="title" name="title"
                                                   required maxlength="255" placeholder="e.g., Event Participation">
                                            <div class="invalid-feedback">Please provide a certificate title</div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="type" class="form-label">Certificate Type *</label>
                                            <select class="form-select" id="type" name="type" required>
                                                <option value="">Select Type</option>
                                                <option value="participation">Participation</option>
                                                <option value="achievement">Achievement</option>
                                                <option value="completion">Completion</option>
                                                <option value="appreciation">Appreciation</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control" id="description" name="description"
                                                      rows="3" placeholder="Certificate description..."></textarea>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="issue_date" class="form-label">Issue Date *</label>
                                            <input type="date" class="form-control" id="issue_date" name="issue_date"
                                                   value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="template_id" class="form-label">Certificate Template</label>
                                            <select class="form-select" id="template_id" name="template_id">
                                                <option value="1">Professional Template</option>
                                                <option value="2">Modern Template</option>
                                                <option value="3">Classic Template</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="students" class="form-label">Select Students *</label>
                                            <div id="studentsList" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                                <div class="text-muted text-center py-4">Select an event to load participants</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <a href="certificates.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-users me-2"></i> Issue Bulk Certificates
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Certificates List -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-list me-2"></i> All Certificates
                                    </h5>
                                </div>
                                <div class="col-auto">
                                    <div class="d-flex gap-2 flex-wrap">
                                        <!-- Search -->
                                        <div class="input-group" style="width: 250px;">
                                            <input type="text" class="form-control" id="searchCertificates" placeholder="Search certificates...">
                                            <button class="btn btn-outline-secondary" type="button">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>

                                        <!-- Filter by Type -->
                                        <select class="form-select" id="filterType" style="width: 150px;">
                                            <option value="">All Types</option>
                                            <option value="participation">Participation</option>
                                            <option value="achievement">Achievement</option>
                                            <option value="excellence">Excellence</option>
                                            <option value="completion">Completion</option>
                                            <option value="merit">Merit</option>
                                        </select>

                                        <!-- Filter by Status -->
                                        <select class="form-select" id="filterStatus" style="width: 120px;">
                                            <option value="">All Status</option>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                            <option value="revoked">Revoked</option>
                                        </select>

                                        <!-- Export -->
                                        <button class="btn btn-outline-success" onclick="exportCertificates()">
                                            <i class="fas fa-download me-1"></i> Export
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Statistics -->
                            <div class="row mb-4">
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-primary">
                                            <i class="fas fa-certificate"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM certificates", []); ?></h3>
                                            <p>Total Issued</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-success">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM certificates WHERE status = 'active'", []); ?></h3>
                                            <p>Active</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-info">
                                            <i class="fas fa-trophy"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM certificates WHERE type = 'achievement'", []); ?></h3>
                                            <p>Achievements</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-warning">
                                            <i class="fas fa-calendar-check"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM certificates WHERE DATE(issue_date) = CURDATE()", []); ?></h3>
                                            <p>Today</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-danger">
                                            <i class="fas fa-times-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM certificates WHERE status = 'revoked'", []); ?></h3>
                                            <p>Revoked</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-secondary">
                                            <i class="fas fa-file-pdf"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM certificates WHERE pdf_path IS NOT NULL AND pdf_path != ''", []); ?></h3>
                                            <p>PDF Generated</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Certificates Table -->
                            <div class="table-responsive">
                                <table class="table table-hover" id="certificatesTable">
                                    <thead>
                                        <tr>
                                            <th>Certificate #</th>
                                            <th>Title</th>
                                            <th>Student</th>
                                            <th>Type</th>
                                            <th>Event</th>
                                            <th>Issue Date</th>
                                            <th>Status</th>
                                            <th>PDF</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sql = "
                                            SELECT c.*, s.first_name, s.last_name, s.email, e.title as event_title
                                            FROM certificates c
                                            JOIN students s ON c.student_id = s.id
                                            LEFT JOIN events e ON c.event_id = e.id
                                            ORDER BY c.created_at DESC
                                            LIMIT 100
                                        ";
                                        $certificates = fetchAll($sql);

                                        foreach ($certificates as $cert):
                                        ?>
                                        <tr data-certificate-id="<?php echo $cert['id']; ?>">
                                            <td>
                                                <span class="badge bg-primary font-monospace"><?php echo htmlspecialchars($cert['certificate_number']); ?></span>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($cert['title']); ?></strong>
                                                    <?php if (!empty($cert['description'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($cert['description'], 0, 80)) . '...'; ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($cert['first_name'] . ' ' . $cert['last_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($cert['email']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getCertificateTypeColor($cert['type']); ?>">
                                                    <?php echo ucfirst($cert['type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($cert['event_title']): ?>
                                                    <span class="text-muted small"><?php echo htmlspecialchars($cert['event_title']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div>
                                                    <?php echo formatDate($cert['issue_date']); ?>
                                                    <?php if ($cert['template_id']): ?>
                                                        <br><small class="text-muted">Template <?php echo $cert['template_id']; ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getCertificateStatusColor($cert['status']); ?>">
                                                    <?php echo ucfirst($cert['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($cert['pdf_path'])): ?>
                                                    <a href="<?php echo SITE_URL . '/' . $cert['pdf_path']; ?>" target="_blank"
                                                       class="btn btn-sm btn-outline-success" title="View PDF">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="generatePDF(<?php echo $cert['id']; ?>)" title="Generate PDF">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-info" onclick="viewCertificate(<?php echo $cert['id']; ?>)" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="editCertificate(<?php echo $cert['id']; ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="downloadCertificate(<?php echo $cert['id']; ?>)" title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteCertificate(<?php echo $cert['id']; ?>)" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <nav class="mt-3">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#" tabindex="-1">Previous</a>
                                    </li>
                                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                                    <li class="page-item">
                                        <a class="page-link" href="#">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Certificate Verification Modal -->
<div class="modal fade" id="certificateVerificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Verify Certificate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="GET" action="certificates.php">
                    <input type="hidden" name="action" value="verify_certificate">
                    <div class="mb-3">
                        <label for="certificate_number" class="form-label">Certificate Number</label>
                        <input type="text" class="form-control" id="certificate_number" name="certificate_number"
                               placeholder="Enter certificate number to verify" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-check-circle me-2"></i> Verify Certificate
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Certificate Details Modal -->
<div class="modal fade" id="certificateDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Certificate Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="certificateDetailsContent">
                <!-- Certificate details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<style>
/* Admin Certificate Management Styles */
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

.table-responsive {
    background: var(--card-bg);
    border-radius: 12px;
    overflow: hidden;
}

.table {
    margin-bottom: 0;
}

.table thead th {
    background: var(--secondary-color);
    border-bottom: 2px solid var(--border-color);
    font-weight: 600;
    color: var(--text-primary);
}

.table tbody tr:hover {
    background: var(--glass-bg);
}

.font-monospace {
    font-family: 'Courier New', monospace;
}

/* Badge colors */
.badge.bg-primary { background: var(--primary-color) !important; }
.badge.bg-success { background: var(--success-color) !important; }
.badge.bg-warning { background: var(--warning-color) !important; }
.badge.bg-danger { background: var(--error-color) !important; }
.badge.bg-info { background: var(--info-color) !important; }
.badge.bg-secondary { background: var(--text-muted) !important; }

/* Form enhancements */
.form-control:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 15px rgba(0, 255, 136, 0.2);
}

/* Student selection for bulk issue */
#studentsList {
    background: var(--glass-bg);
}

.student-checkbox {
    padding: 0.5rem;
    border-radius: 8px;
    transition: background 0.3s ease;
}

.student-checkbox:hover {
    background: var(--glass-bg);
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

// Search functionality
document.getElementById('searchCertificates')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#certificatesTable tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Filter functionality
document.getElementById('filterType')?.addEventListener('change', filterCertificates);
document.getElementById('filterStatus')?.addEventListener('change', filterCertificates);

function filterCertificates() {
    const typeFilter = document.getElementById('filterType').value;
    const statusFilter = document.getElementById('filterStatus').value;
    const rows = document.querySelectorAll('#certificatesTable tbody tr');

    rows.forEach(row => {
        const typeCell = row.querySelector('td:nth-child(4) .badge');
        const statusCell = row.querySelector('td:nth-child(7) .badge');

        const typeMatch = !typeFilter || typeCell.textContent.toLowerCase() === typeFilter;
        const statusMatch = !statusFilter || statusCell.textContent.toLowerCase() === statusFilter;

        row.style.display = typeMatch && statusMatch ? '' : 'none';
    });
}

// CRUD operations
function viewCertificate(certificateId) {
    const modal = new bootstrap.Modal(document.getElementById('certificateDetailsModal'));
    const content = document.getElementById('certificateDetailsContent');

    // Show loading
    content.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    modal.show();

    // Fetch certificate details
    fetch(`../api/certificates.php?action=get&id=${certificateId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const cert = data.data;
                let eventInfo = '';
                if (cert.event_title) {
                    eventInfo = `
                        <div class="mb-3">
                            <h6>Related Event</h6>
                            <p class="mb-0">${cert.event_title}</p>
                        </div>
                    `;
                }

                let signatures = '';
                if (cert.signature_1 || cert.signature_2) {
                    signatures = `
                        <div class="mb-3">
                            <h6>Signatures</h6>
                            ${cert.signature_1 ? `<p><strong>Signature 1:</strong> ${cert.signature_1}</p>` : ''}
                            ${cert.signature_2 ? `<p><strong>Signature 2:</strong> ${cert.signature_2}</p>` : ''}
                        </div>
                    `;
                }

                content.innerHTML = `
                    <div class="certificate-details">
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <h6 class="text-primary">Certificate Information</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Certificate #:</strong></td><td><span class="font-monospace">${cert.certificate_number}</span></td></tr>
                                    <tr><td><strong>Title:</strong></td><td>${cert.title}</td></tr>
                                    <tr><td><strong>Type:</strong></td><td><span class="badge bg-${getCertificateTypeColor(cert.type)}">${cert.type}</span></td></tr>
                                    <tr><td><strong>Status:</strong></td><td><span class="badge bg-${getCertificateStatusColor(cert.status)}">${cert.status}</span></td></tr>
                                    <tr><td><strong>Issue Date:</strong></td><td>${formatDate(cert.issue_date)}</td></tr>
                                    <tr><td><strong>Template:</strong></td><td>Template ${cert.template_id}</td></tr>
                                    <tr><td><strong>Created:</strong></td><td>${formatDate(cert.created_at)}</td></tr>
                                </table>

                                ${cert.description ? `
                                    <div class="mb-3">
                                        <h6>Description</h6>
                                        <p>${cert.description}</p>
                                    </div>
                                ` : ''}

                                ${eventInfo}

                                ${signatures}

                                ${cert.additional_info ? `
                                    <div class="mb-3">
                                        <h6>Additional Information</h6>
                                        <p>${cert.additional_info}</p>
                                    </div>
                                ` : ''}
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-primary">Student Information</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Name:</strong></td><td>${cert.first_name} ${cert.last_name}</td></tr>
                                    <tr><td><strong>Email:</strong></td><td>${cert.email}</td></tr>
                                </table>

                                ${cert.pdf_path ? `
                                    <div class="mt-3">
                                        <h6>Certificate PDF</h6>
                                        <a href="${SITE_URL}/${cert.pdf_path}" target="_blank" class="btn btn-sm btn-success">
                                            <i class="fas fa-file-pdf me-2"></i> View PDF
                                        </a>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Error loading certificate details</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error loading certificate details</div>';
        });
}

function editCertificate(certificateId) {
    window.location.href = `certificates.php?action=edit&id=${certificateId}`;
}

function deleteCertificate(certificateId) {
    if (confirm('Are you sure you want to delete this certificate? This action cannot be undone.')) {
        window.location.href = `certificates.php?action=delete&id=${certificateId}`;
    }
}

function downloadCertificate(certificateId) {
    window.open(`../api/certificate-pdf.php?id=${certificateId}`, '_blank');
}

function generatePDF(certificateId) {
    if (confirm('Generate PDF for this certificate?')) {
        fetch(`../api/certificate-pdf.php?action=generate&id=${certificateId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error generating PDF: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error generating PDF');
            });
    }
}

// Modal functions
function showVerificationModal() {
    new bootstrap.Modal(document.getElementById('certificateVerificationModal')).show();
}

function showBulkIssueModal() {
    window.location.href = 'certificates.php?action=bulk_issue';
}

function previewCertificate() {
    alert('Certificate preview will show the certificate template with the entered data. This would open a new window with the preview.');
}

// Bulk issue functions
function loadEventParticipants() {
    const eventId = document.getElementById('event_id').value;
    const studentsList = document.getElementById('studentsList');

    if (!eventId) {
        studentsList.innerHTML = '<div class="text-muted text-center py-4">Select an event to load participants</div>';
        return;
    }

    studentsList.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Loading participants...</div>';

    fetch(`../api/events.php?action=get_participants&id=${eventId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                let html = '<div class="form-check"><input type="checkbox" id="selectAll" onchange="toggleAllStudents(this)"><label for="selectAll">Select All</label></div>';
                html += '<div class="row">';

                data.data.forEach(student => {
                    html += `
                        <div class="col-md-6 mb-2">
                            <div class="student-checkbox">
                                <input type="checkbox" name="student_ids[]" value="${student.id}" id="student_${student.id}">
                                <label for="student_${student.id}" class="ms-2">
                                    ${student.first_name} ${student.last_name} (${student.email})
                                </label>
                            </div>
                        </div>
                    `;
                });

                html += '</div>';
                studentsList.innerHTML = html;
            } else {
                studentsList.innerHTML = '<div class="text-muted text-center py-4">No participants found for this event</div>';
            }
        })
        .catch(error => {
            studentsList.innerHTML = '<div class="text-danger text-center py-4">Error loading participants</div>';
        });
}

function toggleAllStudents(checkbox) {
    const studentCheckboxes = document.querySelectorAll('input[name="student_ids[]"]');
    studentCheckboxes.forEach(cb => cb.checked = checkbox.checked);
}

// Export function
function exportCertificates() {
    const typeFilter = document.getElementById('filterType')?.value || '';
    const statusFilter = document.getElementById('filterStatus')?.value || '';

    let url = `../api/export.php?type=certificates`;
    if (typeFilter) url += `&certificate_type=${typeFilter}`;
    if (statusFilter) url += `&status=${statusFilter}`;

    window.location.href = url;
}

// Helper functions
function getCertificateTypeColor(type) {
    const colors = {
        'participation' => 'info',
        'achievement' => 'warning',
        'excellence' => 'success',
        'completion' => 'primary',
        'merit' => 'danger',
        'appreciation' => 'secondary',
        'leadership' => 'primary',
        'volunteer' => 'success'
    };
    return colors[type] || 'secondary';
}

function getCertificateStatusColor(status) {
    const colors = {
        'active' => 'success',
        'inactive' => 'secondary',
        'revoked' => 'danger'
    };
    return colors[status] || 'secondary';
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

<?php
// Helper functions already defined in includes/functions.php

function generateCertificatePDF($certificate_id) {
    // In a real implementation, this would use a library like TCPDF or FPDF
    // For now, return a placeholder path
    $cert = fetchRow("SELECT * FROM certificates WHERE id = ?", [$certificate_id]);
    if (!$cert) return false;

    $pdf_path = 'uploads/certificates/certificate_' . $cert['certificate_number'] . '.pdf';

    // Create the directory if it doesn't exist
    $upload_dir = __DIR__ . '/../uploads/certificates';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Placeholder - in real implementation, generate actual PDF
    file_put_contents(__DIR__ . '/../' . $pdf_path, 'PDF content for certificate: ' . $cert['certificate_number']);

    return $pdf_path;
}

include __DIR__ . '/../templates/admin_footer.php';
?>