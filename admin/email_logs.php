<?php
require_once '../includes/admin_header.php';
require_once '../includes/MailjetService.php';

// Only allow admins and super_admins to access email logs
requireRole(['super_admin', 'admin']);

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['export_logs'])) {
            $format = sanitize($_POST['export_format'] ?? 'csv');
            $dateFrom = sanitize($_POST['date_from'] ?? date('Y-m-01'));
            $dateTo = sanitize($_POST['date_to'] ?? date('Y-m-d'));

            $query = "SELECT * FROM email_logs WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC";
            $logs = dbFetchAll($query, [$dateFrom, $dateTo]);

            if ($format === 'csv') {
                $filename = 'email_logs_' . date('Y-m-d') . '.csv';
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');

                $output = fopen('php://output', 'w');
                fputcsv($output, ['Date', 'To Email', 'Subject', 'Status', 'Message ID', 'Error Message']);

                foreach ($logs as $log) {
                    fputcsv($output, [
                        $log['created_at'],
                        $log['to_email'],
                        $log['subject'],
                        $log['status'],
                        $log['message_id'] ?? '',
                        $log['error_message'] ?? ''
                    ]);
                }

                fclose($output);
                exit;
            } else {
                // JSON export
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="email_logs_' . date('Y-m-d') . '.json"');
                echo json_encode($logs, JSON_PRETTY_PRINT);
                exit;
            }
        }

        if (isset($_POST['clear_logs'])) {
            $dateFrom = sanitize($_POST['clear_date_from'] ?? date('Y-m-d', strtotime('-30 days')));
            $dateTo = sanitize($_POST['clear_date_to'] ?? date('Y-m-d'));

            $query = "DELETE FROM email_logs WHERE DATE(created_at) BETWEEN ? AND ?";
            $result = dbExecute($query, [$dateFrom, $dateTo]);

            logActivity($_SESSION['user_id'], 'delete', 'email_logs', null, "Cleared email logs from $dateFrom to $dateTo");
            $message = "Email logs cleared successfully! $result records deleted.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get filter parameters
$search = sanitize($_GET['search'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$dateFrom = sanitize($_GET['date_from'] ?? date('Y-m-01'));
$dateTo = sanitize($_GET['date_to'] ?? date('Y-m-d'));
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(to_email LIKE ? OR subject LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $whereConditions[] = "status = ?";
    $params[] = $status;
}

if (!empty($dateFrom) && !empty($dateTo)) {
    $whereConditions[] = "DATE(created_at) BETWEEN ? AND ?";
    $params[] = $dateFrom;
    $params[] = $dateTo;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM email_logs $whereClause";
$totalResult = dbFetch($countQuery, $params);
$totalLogs = $totalResult['total'];
$totalPages = ceil($totalLogs / $perPage);

// Get logs with pagination
$query = "SELECT * FROM email_logs $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
$logs = dbFetchAll($query, $params);
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="fas fa-envelope-open-text me-2"></i>
                    Email Logs
                </h2>
                <div>
                    <button class="btn btn-outline-secondary" onclick="window.location.href='email_settings.php'">
                        <i class="fas fa-cog me-2"></i>Email Settings
                    </button>
                    <button class="btn btn-outline-secondary" onclick="window.location.href='index.php'">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </button>
                </div>
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

    <!-- Statistics Summary -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card admin-card h-100">
                <div class="card-body text-center">
                    <div class="stat-icon bg-primary mb-3">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3 class="stat-value"><?php echo number_format($totalLogs); ?></h3>
                    <p class="stat-label">Total Emails</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card admin-card h-100">
                <div class="card-body text-center">
                    <div class="stat-icon bg-success mb-3">
                        <i class="fas fa-check"></i>
                    </div>
                    <h3 class="stat-value">
                        <?php
                        $successCount = dbFetch("SELECT COUNT(*) as count FROM email_logs WHERE status = 'success' AND DATE(created_at) BETWEEN ? AND ?", [$dateFrom, $dateTo]);
                        echo number_format($successCount['count']);
                        ?>
                    </h3>
                    <p class="stat-label">Successful</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card admin-card h-100">
                <div class="card-body text-center">
                    <div class="stat-icon bg-danger mb-3">
                        <i class="fas fa-times"></i>
                    </div>
                    <h3 class="stat-value">
                        <?php
                        $errorCount = dbFetch("SELECT COUNT(*) as count FROM email_logs WHERE status = 'error' AND DATE(created_at) BETWEEN ? AND ?", [$dateFrom, $dateTo]);
                        echo number_format($errorCount['count']);
                        ?>
                    </h3>
                    <p class="stat-label">Failed</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card admin-card h-100">
                <div class="card-body text-center">
                    <div class="stat-icon bg-warning mb-3">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <h3 class="stat-value">
                        <?php
                        if ($errorCount['count'] > 0) {
                            $successRate = ($successCount['count'] / ($successCount['count'] + $errorCount['count'])) * 100;
                        } else {
                            $successRate = 100;
                        }
                        echo round($successRate, 1) . '%';
                        ?>
                    </h3>
                    <p class="stat-label">Success Rate</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Actions -->
    <div class="card admin-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                           value="<?php echo htmlspecialchars($search); ?>" placeholder="Email or Subject">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="success" <?php echo $status === 'success' ? 'selected' : ''; ?>>Success</option>
                        <option value="error" <?php echo $status === 'error' ? 'selected' : ''; ?>>Error</option>
                        <option value="send" <?php echo $status === 'send' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from"
                           value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to"
                           value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-2"></i>Filter
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                        <i class="fas fa-undo me-2"></i>Clear
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Email Logs Table -->
    <div class="card admin-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="fas fa-list me-2"></i>
                Email Logs
                <span class="badge bg-primary ms-2"><?php echo number_format($totalLogs); ?> records</span>
            </h5>
            <div>
                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#exportModal">
                    <i class="fas fa-download me-2"></i>Export
                </button>
                <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#clearModal">
                    <i class="fas fa-trash me-2"></i>Clear Logs
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if ($logs): ?>
                <div class="table-responsive">
                    <table class="table table-dark table-striped">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>To Email</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Message ID</th>
                                <th>Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo formatDate($log['created_at'], true); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($log['to_email']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($log['subject']); ?>
                                    </td>
                                    <td>
                                        <?php if ($log['status'] === 'success'): ?>
                                            <span class="badge bg-success">Success</span>
                                        <?php elseif ($log['status'] === 'error'): ?>
                                            <span class="badge bg-danger">Error</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="text-muted small"><?php echo htmlspecialchars($log['message_id'] ?? ''); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($log['error_message']): ?>
                                            <span class="text-danger small" title="<?php echo htmlspecialchars($log['error_message']); ?>">
                                                <?php echo htmlspecialchars(substr($log['error_message'], 0, 30)) . '...'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="d-flex justify-content-center mt-4">
                        <nav>
                            <ul class="pagination pagination-dark">
                                <?php
                                $prevPage = $page - 1;
                                $nextPage = $page + 1;
                                ?>

                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $prevPage; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $nextPage; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No email logs found matching your criteria.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">
                    <i class="fas fa-download me-2"></i>
                    Export Email Logs
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">

                    <div class="mb-3">
                        <label class="form-label">Export Format</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="export_format" id="export_csv" value="csv" checked>
                            <label class="form-check-label" for="export_csv">
                                CSV (Excel Compatible)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="export_format" id="export_json" value="json">
                            <label class="form-check-label" for="export_json">
                                JSON (Data Analysis)
                            </label>
                        </div>
                    </div>

                    <div class="alert alert-info small">
                        <i class="fas fa-info-circle me-2"></i>
                        Exporting logs from <?php echo formatDate($dateFrom); ?> to <?php echo formatDate($dateTo); ?>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="export_logs" class="btn btn-primary">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Clear Logs Modal -->
<div class="modal fade" id="clearModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">
                    <i class="fas fa-trash me-2"></i>
                    Clear Email Logs
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="clear_date_from" class="form-label">From Date</label>
                        <input type="date" class="form-control bg-secondary text-light border-secondary" id="clear_date_from" name="clear_date_from" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="clear_date_to" class="form-label">To Date</label>
                        <input type="date" class="form-control bg-secondary text-light border-secondary" id="clear_date_to" name="clear_date_to" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone. All email logs within the selected date range will be permanently deleted.
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="clear_logs" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Clear Logs
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Email Logs Styles */
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

.pagination-dark .page-link {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
}

.pagination-dark .page-link:hover {
    background: var(--accent-color);
    border-color: var(--accent-color);
    color: var(--dark-bg);
}

.pagination-dark .page-item.active .page-link {
    background: var(--accent-color);
    border-color: var(--accent-color);
    color: var(--dark-bg);
}

.table-dark {
    background: var(--card-bg);
}

.table-dark th {
    background: var(--dark-bg);
    border-color: var(--border-color);
}

.table-dark td {
    border-color: var(--border-color);
}
</style>

<script>
function clearFilters() {
    window.location.href = 'email_logs.php';
}

// Auto-refresh logs every 30 seconds for real-time monitoring
setInterval(function() {
    const url = new URL(window.location);
    url.searchParams.set('refresh', Date.now());
    window.location.href = url.toString();
}, 30000);
</script>

<?php require_once '../includes/admin_footer.php'; ?>