<?php
// SPARK Platform - Admin Contact Management
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireAdmin();

// Handle contact operations
$action = $_GET['action'] ?? 'list';

if ($action === 'reply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $message_id = (int)$_POST['message_id'];
        $reply_content = sanitize($_POST['reply_content']);
        $reply_method = sanitize($_POST['reply_method'] ?? 'email');
        $status = sanitize($_POST['status'] ?? 'replied');

        // Validation
        if (empty($reply_content)) {
            throw new Exception("Reply content is required");
        }

        // Get message details
        $message = fetchRow("SELECT * FROM contact_messages WHERE id = ?", [$message_id]);
        if (!$message) {
            throw new Exception("Message not found");
        }

        // Update message status and reply
        executeUpdate("
            UPDATE contact_messages SET
            status = ?, reply_content = ?, reply_method = ?, replied_by = ?,
            replied_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ", [$status, $reply_content, $reply_method, $_SESSION['user_id'], $message_id]);

        // Send reply email (in real implementation)
        if ($reply_method === 'email') {
            $to = $message['email'];
            $subject = "Re: " . $message['subject'];
            $email_content = "
                <h2>Reply to Your Message</h2>
                <p>Dear {$message['name']},</p>
                <p>Thank you for contacting us. Here is our response:</p>
                <div style='background: #f5f5f5; padding: 15px; border-left: 4px solid #00ff88; margin: 20px 0;'>
                    <strong>Original Message:</strong><br>
                    <em>\"{$message['message']}\"</em>
                </div>
                <div style='background: #e8f5e8; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0;'>
                    <strong>Our Response:</strong><br>
                    {$reply_content}
                </div>
                <p>Best regards,<br>SPARK Team<br>Sanjivani College of Engineering</p>
            ";

            // sendEmail($to, $subject, $email_content); // Uncomment when email service is ready
        }

        // Log activity
        logActivity('contact_message_replied', "Replied to message from {$message['name']}", $_SESSION['user_id'], $message_id);

        $_SESSION['success'][] = "Reply sent successfully!";
        header('Location: contact.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: contact.php?action=reply&id=' . $_POST['message_id']);
        exit();
    }
}

if ($action === 'update_status') {
    try {
        $message_id = (int)$_GET['id'];
        $status = sanitize($_GET['status']);

        // Validate status
        $valid_statuses = ['new', 'read', 'in_progress', 'replied', 'closed'];
        if (!in_array($status, $valid_statuses)) {
            throw new Exception("Invalid status");
        }

        // Update message status
        executeUpdate("UPDATE contact_messages SET status = ?, updated_at = NOW() WHERE id = ?", [$status, $message_id]);

        // Get message for logging
        $message = fetchRow("SELECT name, subject FROM contact_messages WHERE id = ?", [$message_id]);
        if ($message) {
            logActivity('contact_status_updated', "Updated message status to '{$status}' for '{$message['subject']}'", $_SESSION['user_id'], $message_id);
        }

        $_SESSION['success'][] = "Message status updated successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: contact.php');
    exit();
}

if ($action === 'delete') {
    try {
        $message_id = (int)$_GET['id'];

        // Get message info for logging
        $message = fetchRow("SELECT name, subject FROM contact_messages WHERE id = ?", [$message_id]);
        if (!$message) {
            throw new Exception("Message not found");
        }

        // Delete message
        executeUpdate("DELETE FROM contact_messages WHERE id = ?", [$message_id]);

        // Log activity
        logActivity('contact_message_deleted', "Deleted message '{$message['subject']}' from {$message['name']}", $_SESSION['user_id'], $message_id);

        $_SESSION['success'][] = "Message deleted successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: contact.php');
    exit();
}

if ($action === 'bulk_update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $message_ids = array_map('intval', $_POST['message_ids'] ?? []);
        $action = sanitize($_POST['bulk_action']);

        if (empty($message_ids) || empty($action)) {
            throw new Exception("Please select messages and an action");
        }

        $success_count = 0;

        foreach ($message_ids as $message_id) {
            try {
                if ($action === 'mark_read') {
                    executeUpdate("UPDATE contact_messages SET status = 'read', updated_at = NOW() WHERE id = ?", [$message_id]);
                    $success_count++;
                } elseif ($action === 'mark_closed') {
                    executeUpdate("UPDATE contact_messages SET status = 'closed', updated_at = NOW() WHERE id = ?", [$message_id]);
                    $success_count++;
                } elseif ($action === 'delete') {
                    executeUpdate("DELETE FROM contact_messages WHERE id = ?", [$message_id]);
                    $success_count++;
                }
            } catch (Exception $e) {
                // Continue with next message
            }
        }

        $_SESSION['success'][] = "Bulk action completed: {$success_count} messages processed";
        header('Location: contact.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: contact.php');
        exit();
    }
}

$page_title = 'Contact Management';
$breadcrumb = [
    ['name' => 'Dashboard', 'link' => 'index.php', 'active' => false],
    ['name' => 'Contact', 'link' => '', 'active' => true]
];

include __DIR__ . '/../templates/admin_header.php';
?>

<!-- Contact Management Section -->
<section class="admin-section py-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="section-title">
                            <i class="fas fa-envelope me-2"></i> Contact Management
                        </h2>
                        <p class="text-muted">Manage contact messages and inquiries</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-info" onclick="showStatistics()">
                            <i class="fas fa-chart-bar me-2"></i> Statistics
                        </button>
                        <button class="btn btn-outline-success" onclick="exportMessages()">
                            <i class="fas fa-download me-2"></i> Export
                        </button>
                    </div>
                </div>

                <?php include __DIR__ . '/../includes/alerts.php'; ?>

                <?php if ($action === 'reply'): ?>
                    <!-- Reply Form -->
                    <?php
                    $message_id = (int)$_GET['id'];
                    $message = fetchRow("SELECT * FROM contact_messages WHERE id = ?", [$message_id]);
                    if (!$message) {
                        $_SESSION['errors'][] = "Message not found";
                        header('Location: contact.php');
                        exit();
                    }
                    ?>

                    <div class="card admin-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-reply me-2"></i> Reply to Message
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Original Message -->
                            <div class="mb-4">
                                <h6 class="text-primary mb-3">Original Message</h6>
                                <div class="original-message">
                                    <div class="message-header">
                                        <span class="badge bg-primary"><?php echo ucfirst($message['status']); ?></span>
                                        <span class="ms-2"><strong>From:</strong> <?php echo htmlspecialchars($message['name'] . ' (' . $message['email'] . ')'); ?></span>
                                        <span class="ms-2"><strong>Date:</strong> <?php echo formatDateTime($message['created_at']); ?></span>
                                    </div>
                                    <div class="message-subject mt-2">
                                        <strong>Subject:</strong> <?php echo htmlspecialchars($message['subject']); ?>
                                    </div>
                                    <div class="message-content mt-3">
                                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                    </div>
                                    <?php if (!empty($message['phone'])): ?>
                                        <div class="mt-2">
                                            <strong>Phone:</strong> <?php echo htmlspecialchars($message['phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Reply Form -->
                            <form method="POST" class="needs-validation" novalidate>
                                <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="reply_method" class="form-label">Reply Method</label>
                                            <select class="form-select" id="reply_method" name="reply_method">
                                                <option value="email">Email Reply</option>
                                                <option value="phone">Phone Call</option>
                                                <option value="visit">In-Person Visit</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="status" class="form-label">Status After Reply</label>
                                            <select class="form-select" id="status" name="status">
                                                <option value="replied">Replied</option>
                                                <option value="in_progress">In Progress</option>
                                                <option value="closed">Closed</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="reply_content" class="form-label">Reply Content *</label>
                                    <textarea class="form-control" id="reply_content" name="reply_content"
                                              rows="8" required placeholder="Type your reply here..."></textarea>
                                    <div class="invalid-feedback">Please provide reply content</div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="contact.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i> Send Reply
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Messages List -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-list me-2"></i> Contact Messages
                                    </h5>
                                </div>
                                <div class="col-auto">
                                    <div class="d-flex gap-2 flex-wrap">
                                        <!-- Search -->
                                        <div class="input-group" style="width: 250px;">
                                            <input type="text" class="form-control" id="searchMessages" placeholder="Search messages...">
                                            <button class="btn btn-outline-secondary" type="button">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>

                                        <!-- Filter by Status -->
                                        <select class="form-select" id="filterStatus" style="width: 150px;">
                                            <option value="">All Status</option>
                                            <option value="new">New</option>
                                            <option value="read">Read</option>
                                            <option value="in_progress">In Progress</option>
                                            <option value="replied">Replied</option>
                                            <option value="closed">Closed</option>
                                        </select>

                                        <!-- Date Range -->
                                        <div class="input-group" style="width: 200px;">
                                            <input type="date" class="form-control" id="startDate" value="<?php echo date('Y-m-01'); ?>">
                                        </div>
                                        <div class="input-group" style="width: 200px;">
                                            <input type="date" class="form-control" id="endDate" value="<?php echo date('Y-m-t'); ?>">
                                        </div>

                                        <!-- Refresh -->
                                        <button class="btn btn-outline-primary" onclick="refreshMessages()">
                                            <i class="fas fa-sync-alt"></i>
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
                                            <i class="fas fa-envelope"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM contact_messages", []); ?></h3>
                                            <p>Total Messages</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-danger">
                                            <i class="fas fa-exclamation-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'", []); ?></h3>
                                            <p>New Messages</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-warning">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM contact_messages WHERE status = 'in_progress'", []); ?></h3>
                                            <p>In Progress</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-success">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM contact_messages WHERE status = 'replied'", []); ?></h3>
                                            <p>Replied</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-secondary">
                                            <i class="fas fa-archive"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM contact_messages WHERE status = 'closed'", []); ?></h3>
                                            <p>Closed</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-info">
                                            <i class="fas fa-calendar-day"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM contact_messages WHERE DATE(created_at) = CURDATE()", []); ?></h3>
                                            <p>Today</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bulk Actions -->
                            <div class="row mb-3">
                                <div class="col">
                                    <form method="POST" action="contact.php?action=bulk_update">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleAllMessages(this)">
                                                <label class="form-check-label" for="selectAll">Select All</label>
                                            </div>
                                            <div>
                                                <select class="form-select" name="bulk_action" style="width: 150px; display: inline-block;">
                                                    <option value="">Bulk Actions</option>
                                                    <option value="mark_read">Mark as Read</option>
                                                    <option value="mark_closed">Mark as Closed</option>
                                                    <option value="delete">Delete</option>
                                                </select>
                                                <button type="submit" class="btn btn-outline-primary">
                                                    Apply
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Messages Table -->
                            <div class="table-responsive">
                                <table class="table table-hover" id="messagesTable">
                                    <thead>
                                        <tr>
                                            <th width="40">
                                                <input type="checkbox" id="selectAllTable" onchange="toggleAllMessages(this)">
                                            </th>
                                            <th>Status</th>
                                            <th>Subject</th>
                                            <th>From</th>
                                            <th>Message</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sql = "
                                            SELECT * FROM contact_messages
                                            ORDER BY
                                                CASE WHEN status = 'new' THEN 1 ELSE 2 END,
                                                created_at DESC
                                            LIMIT 100
                                        ";
                                        $messages = fetchAll($sql);

                                        foreach ($messages as $msg):
                                        ?>
                                        <tr data-message-id="<?php echo $msg['id']; ?>" data-status="<?php echo $msg['status']; ?>">
                                            <td>
                                                <input type="checkbox" name="message_ids[]" value="<?php echo $msg['id']; ?>"
                                                       class="message-checkbox">
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getMessageStatusColor($msg['status']); ?>">
                                                    <?php echo formatMessageStatus($msg['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($msg['subject']); ?></strong>
                                                    <?php if (!empty($msg['replied_at'])): ?>
                                                        <br><small class="text-success">Replied on <?php echo formatDate($msg['replied_at']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($msg['name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($msg['email']); ?></small>
                                                    <?php if (!empty($msg['phone'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($msg['phone']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="message-preview" title="<?php echo htmlspecialchars($msg['message']); ?>">
                                                    <?php echo htmlspecialchars(substr($msg['message'], 0, 100)) . (strlen($msg['message']) > 100 ? '...' : ''); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <?php echo formatDate($msg['created_at']); ?>
                                                    <br><small class="text-muted"><?php echo formatTime($msg['created_at']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-info" onclick="viewMessage(<?php echo $msg['id']; ?>)" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($msg['status'] !== 'replied'): ?>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="replyToMessage(<?php echo $msg['id']; ?>)" title="Reply">
                                                            <i class="fas fa-reply"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="updateMessageStatus(<?php echo $msg['id']; ?>)" title="Update Status">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteMessage(<?php echo $msg['id']; ?>)" title="Delete">
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

<!-- Message Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Message Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="statusForm">
                    <input type="hidden" name="message_id" id="statusMessageId">
                    <div class="mb-3">
                        <label for="new_status" class="form-label">New Status</label>
                        <select class="form-select" id="new_status" name="status">
                            <option value="new">New</option>
                            <option value="read">Read</option>
                            <option value="in_progress">In Progress</option>
                            <option value="replied">Replied</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Modal -->
<div class="modal fade" id="statisticsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Contact Statistics</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Messages by Status</h6>
                        <canvas id="statusChart"></canvas>
                    </div>
                    <div class="col-md-6">
                        <h6>Messages by Month</h6>
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-12">
                        <h6>Recent Activity</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Message</th>
                                        <th>Action</th>
                                        <th>By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $recent_activity = fetchAll("
                                        SELECT * FROM activity_logs
                                        WHERE action LIKE '%contact%'
                                        ORDER BY created_at DESC LIMIT 10
                                    ");
                                    foreach ($recent_activity as $activity):
                                    ?>
                                    <tr>
                                        <td><?php echo formatDateTime($activity['created_at']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                        <td><?php echo str_replace('_', ' ', $activity['action']); ?></td>
                                        <td>Admin</td>
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
</div>

<style>
/* Admin Contact Management Styles */
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

.original-message {
    background: var(--glass-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
}

.message-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.message-subject {
    font-weight: bold;
    color: var(--primary-color);
}

.message-content {
    background: white;
    border-left: 4px solid var(--accent-color);
    padding: 1rem;
    border-radius: 0 8px 8px 0;
    white-space: pre-wrap;
}

.message-preview {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
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
document.getElementById('searchMessages')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#messagesTable tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Filter functionality
document.getElementById('filterStatus')?.addEventListener('change', function() {
    const filterValue = this.value;
    const rows = document.querySelectorAll('#messagesTable tbody tr');

    rows.forEach(row => {
        if (!filterValue) {
            row.style.display = '';
        } else {
            const statusBadge = row.querySelector('td:nth-child(2) .badge');
            const status = statusBadge.textContent.toLowerCase().replace(' ', '_');
            row.style.display = status === filterValue ? '' : 'none';
        }
    });
});

// Date range filtering
document.getElementById('startDate')?.addEventListener('change', filterByDateRange);
document.getElementById('endDate')?.addEventListener('change', filterByDateRange);

function filterByDateRange() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const rows = document.querySelectorAll('#messagesTable tbody tr');

    rows.forEach(row => {
        const dateText = row.querySelector('td:nth-child(6) div').textContent.trim();
        const date = new Date(dateText);
        const start = new Date(startDate);
        const end = new Date(endDate);

        if (date >= start && date <= end) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Checkbox functionality
function toggleAllMessages(checkbox) {
    const checkboxes = document.querySelectorAll('.message-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

document.getElementById('selectAll')?.addEventListener('change', function() {
    toggleAllMessages(this);
});

document.getElementById('selectAllTable')?.addEventListener('change', function() {
    toggleAllMessages(this);
});

// Message operations
function viewMessage(messageId) {
    // Mark as read if new
    fetch(`contact.php?action=update_status&id=${messageId}&status=read`)
        .then(() => {
            // Show message details in modal or expand row
            alert(`Viewing message ${messageId} - Would open in detail modal`);
        });
}

function replyToMessage(messageId) {
    window.location.href = `contact.php?action=reply&id=${messageId}`;
}

function deleteMessage(messageId) {
    if (confirm('Are you sure you want to delete this message? This action cannot be undone.')) {
        window.location.href = `contact.php?action=delete&id=${messageId}`;
    }
}

function updateMessageStatus(messageId) {
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    document.getElementById('statusMessageId').value = messageId;
    modal.show();
}

// Status form submission
document.getElementById('statusForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const messageId = document.getElementById('statusMessageId').value;
    const status = document.getElementById('new_status').value;

    window.location.href = `contact.php?action=update_status&id=${messageId}&status=${status}`;
});

// Modal functions
function showStatistics() {
    const modal = new bootstrap.Modal(document.getElementById('statisticsModal'));
    modal.show();

    // Initialize charts
    setTimeout(() => {
        // Status chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['New', 'Read', 'In Progress', 'Replied', 'Closed'],
                datasets: [{
                    data: [12, 25, 8, 45, 20],
                    backgroundColor: [
                        '#dc3545',
                        '#6c757d',
                        '#ffc107',
                        '#28a745',
                        '#17a2b8'
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

        // Monthly chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Messages',
                    data: [65, 75, 70, 80, 85, 90],
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
    }, 500);
}

function exportMessages() {
    const statusFilter = document.getElementById('filterStatus')?.value || '';
    const startDate = document.getElementById('startDate')?.value || '';
    const endDate = document.getElementById('endDate')?.value || '';

    let url = `../api/export.php?type=contact_messages`;
    if (statusFilter) url += `&status=${statusFilter}`;
    if (startDate) url += `&start_date=${startDate}`;
    if (endDate) url += `&end_date=${endDate}`;

    window.location.href = url;
}

function refreshMessages() {
    location.reload();
}

// Helper functions
function getMessageStatusColor(status) {
    const colors = {
        'new' => 'danger',
        'read' => 'secondary',
        'in_progress' => 'warning',
        'replied' => 'success',
        'closed' => 'info'
    };
    return colors[status] || 'secondary';
}

function formatMessageStatus(status) {
    return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString();
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}
</script>

<?php
// Helper functions
function getMessageStatusColor($status) {
    $colors = [
        'new' => 'danger',
        'read' => 'secondary',
        'in_progress' => 'warning',
        'replied' => 'success',
        'closed' => 'info'
    ];
    return $colors[$status] ?? 'secondary';
}

function formatMessageStatus($status) {
    return ucwords(str_replace('_', ' ', $status));
}

include __DIR__ . '/../templates/admin_footer.php';
?>