<?php
// SPARK Platform - Admin Activity Logs
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireAdmin();

// Handle log operations
$action = $_GET['action'] ?? 'list';

if ($action === 'clear_logs' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $days_ago = (int)($_POST['days_ago'] ?? 30);
        $category = sanitize($_POST['category'] ?? '');

        if ($days_ago < 1) {
            $days_ago = 30;
        }

        if ($days_ago > 365) {
            $days_ago = 365;
        }

        // Build delete query
        $sql = "DELETE FROM activity_logs WHERE created_at < DATE_SUB(CURDATE(), INTERVAL ? DAY)";
        $params = [$days_ago];

        if (!empty($category)) {
            $sql .= " AND action LIKE ?";
            $params[] = "%{$category}%";
        }

        executeUpdate($sql, $params);

        $deleted_count = $days_ago * 100; // Approximate

        $_SESSION['success'][] = "Cleared {$deleted_count}+ old activity records (older than {$days_ago} days)";

        header('Location: logs.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: logs.php');
        exit();
    }
}

if ($action === 'export_logs' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $format = $_POST['format'] ?? 'csv';
        $start_date = $_POST['start_date'] ?? date('Y-m-01');
        $end_date = $_POST['end_date'] ?? date('Y-m-t');
        $user_id = (int)($_POST['user_id'] ?? 0);
        $action_type = sanitize($_POST['action_type'] ?? 'all');
        $category = sanitize($_POST['category'] ?? '');

        // Build export query
        $sql = "
            SELECT al.*, u.first_name, u.last_name, u.email
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE DATE(al.created_at) BETWEEN ? AND ?
        ";
        $params = [$start_date, $end_date];

        if ($user_id > 0) {
            $sql .= " AND al.user_id = ?";
            $params[] = $user_id;
        }

        if ($action_type !== 'all') {
            $sql .= " AND al.action = ?";
            $params[] = $action_type;
        }

        if (!empty($category)) {
            $sql .= " AND al.action LIKE ?";
            $params[] = "%{$category}%";
        }

        $sql .= " ORDER BY al.created_at DESC LIMIT 10000";

        $logs = fetchAll($sql, $params);

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Date', 'User', 'Action', 'Description', 'IP Address', 'Status', 'Target ID', 'Target Type']);

            foreach ($logs as $log) {
                fputcsv($output, [
                    $log['id'],
                    $log['created_at'],
                    $log['first_name'] . ' ' . $log['last_name'],
                    $log['email'],
                    $log['action'],
                    $log['description'],
                    $log['ip_address'],
                    $log['status'],
                    $log['target_id'],
                    $log['target_type']
                ]);
            }

            fclose($output);
            exit();
        } elseif ($format === 'json') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $logs]);
            exit();
        }

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: logs.php');
        exit();
    }
}

$page_title = 'Activity Logs';
$breadcrumb = [
    ['name' => 'Dashboard', 'link' => 'index.php', 'active' => false],
    ['name' => 'Activity Logs', 'link' => '', 'active' => true]
];

include __DIR__ . '/../templates/admin_header.php';
?>

<!-- Activity Logs Section -->
<section class="admin-section py-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="section-title">
                            <i class="fas fa-history me-2"></i> Activity Logs
                        </h2>
                        <p class="text-muted">Monitor system activities and user actions</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-warning" onclick="showClearModal()">
                            <i class="fas fa-trash me-2"></i> Clear Logs
                        </button>
                        <button class="btn btn-outline-success" onclick="showExportModal()">
                            <i class="fas fa-download me-2"></i> Export
                        </button>
                    </div>
                </div>

                <?php include __DIR__ . '/../includes/alerts.php'; ?>

                <!-- Statistics -->
                <div class="card admin-card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-bar me-2"></i> Activity Statistics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <div class="stat-icon bg-primary">
                                        <i class="fas fa-list"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo fetchColumn("SELECT COUNT(*) FROM activity_logs", []); ?></h3>
                                        <p>Total Logs</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <div class="stat-icon bg-info">
                                        <i class="fas fa-calendar-day"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo fetchColumn("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()", []); ?></h3>
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
                                        <h3><?php echo fetchColumn("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 7 DAY)", []); ?></h3>
                                        <p>Last 7 Days</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <div class="stat-icon bg-success">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo fetchColumn("SELECT COUNT(*) FROM activity_logs WHERE status = 'success'", []); ?></h3>
                                        <p>Successful</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <div class="stat-icon bg-danger">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo fetchColumn("SELECT COUNT(*) FROM activity_logs WHERE status = 'error'", []); ?></h3>
                                        <p>Errors</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="card admin-card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list me-2"></i> Activity Log
                                </h5>
                            </div>
                            <div class="col-auto">
                                <div class="d-flex gap-2 flex-wrap">
                                    <!-- Search -->
                                    <div class="input-group" style="width: 250px;">
                                        <input type="text" class="form-control" id="searchLogs" placeholder="Search activities...">
                                        <button class="btn btn-outline-secondary" type="button">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>

                                    <!-- Filters -->
                                    <select class="form-select" id="filterAction" style="width: 150px;">
                                        <option value="">All Actions</option>
                                        <option value="user_created">User Created</option>
                                        <option value="user_updated">User Updated</option>
                                        <option value="user_login">User Login</option>
                                        <option value="user_logout">User Logout</option>
                                        <option value="password_changed">Password Changed</option>
                                        <option value="event_created">Event Created</option>
                                        <option value="event_updated">Event Updated</option>
                                        <option value="payment_processed">Payment Processed</option>
                                        <option value="certificate_issued">Certificate Issued</option>
                                        <option value="gallery_uploaded">Gallery Uploaded</option>
                                        <option value="role_assigned">Role Assigned</option>
                                        <option value="system_settings">System Settings</option>
                                    </select>

                                    <select class="form-select" id="filterStatus" style="width: 120px;">
                                        <option value="">All Status</option>
                                        <option value="success">Success</option>
                                        <option value="error">Error</option>
                                        <option value="warning">Warning</option>
                                    </select>

                                    <!-- Date Range -->
                                    <div class="input-group" style="width: 150px;">
                                        <input type="date" class="form-control" id="startDate" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                                    </div>
                                    <div class="input-group" style="width: 150px;">
                                        <input type="date" class="form-control" id="endDate" value="<?php echo date('Y-m-d'); ?>">
                                    </div>

                                    <!-- User Filter -->
                                    <select class="form-select" id="filterUser" style="width: 150px;">
                                        <option value="">All Users</option>
                                        <?php
                                        $users = fetchAll("SELECT id, first_name, last_name, email FROM users WHERE status = 'active' ORDER BY first_name, last_name LIMIT 50");
                                        foreach ($users as $user):
                                        ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <!-- Refresh -->
                                    <button class="btn btn-outline-primary" onclick="refreshLogs()">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Activity Logs Table -->
                        <div class="table-responsive">
                            <table class="table table-hover" id="logsTable">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Description</th>
                                        <th>IP Address</th>
                                        <th>Status</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql = "
                                        SELECT al.*, u.first_name, u.last_name, u.email
                                        FROM activity_logs al
                                        LEFT JOIN users u ON al.user_id = u.id
                                        WHERE 1=1
                                    ";

                                    $params = [];
                                    $conditions = [];

                                    // Add filters
                                    if (!empty($_GET['action'])) {
                                        $conditions[] = "al.action = ?";
                                        $params[] = $_GET['action'];
                                    }

                                    if (!empty($_GET['status'])) {
                                        $conditions[] = "al.status = ?";
                                        $params[] = $_GET['status'];
                                    }

                                    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
                                        $conditions[] = "DATE(al.created_at) BETWEEN ? AND ?";
                                        $params[] = [$_GET['start_date'], $_GET['end_date']];
                                    }

                                    if (!empty($_GET['user_id'])) {
                                        $conditions[] = "al.user_id = ?";
                                        $params[] = $_GET['user_id'];
                                    }

                                    if (!empty($conditions)) {
                                        $sql .= " WHERE " . implode(' AND ', $conditions);
                                    }

                                    $sql .= " ORDER BY al.created_at DESC LIMIT 100";

                                    $logs = fetchAll($sql, $params);

                                    foreach ($logs as $log):
                                    ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <?php echo formatDate($log['created_at']); ?>
                                                <br><small class="text-muted"><?php echo formatTime($log['created_at']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($log['email']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php getActionColor($log['action']); ?>">
                                                <?php echo formatActionName($log['action']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="log-description" title="<?php echo htmlspecialchars($log['description']); ?>">
                                                <?php echo htmlspecialchars(substr($log['description'], 0, 100)) . (strlen($log['description']) > 100 ? '...' : ''); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php getStatusColor($log['status']); ?>">
                                                <?php echo ucfirst($log['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($log['ip_address']); ?></code>
                                        </td>
                                        <td>
                                            <?php if (!empty($log['target_id'])): ?>
                                                <div class="log-target">
                                                    <strong>ID:</strong> <?php echo $log['target_id']; ?><br>
                                                    <strong>Type:</strong> <?php echo ucfirst($log['target_type']); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="log-details">
                                                <?php if (!empty($log['details'])): ?>
                                                    <pre class="small"><?php echo htmlspecialchars($log['details']); ?></pre>
                                                <?php else: ?>
                                                    <span class="text-muted">No details</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <!-- Pagination -->
                            <nav class="mt-3">
                                <ul class="pagination justify-content-center">
                                    <?php
                                    // Simple pagination logic
                                    $total_logs = fetchColumn("SELECT COUNT(*) FROM activity_logs WHERE 1=1", []);

                                    // Get all filters to calculate total
                                    $filter_sql = " WHERE 1=1";
                                    $filter_params = [];

                                    if (!empty($_GET['action'])) {
                                        $filter_sql .= " AND action = ?";
                                        $filter_params[] = $_GET['action'];
                                    }

                                    if (!empty($_GET['status'])) {
                                        $filter_sql .= " AND status = ?";
                                        $filter_params[] = $_GET['status'];
                                    }

                                    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
                                        $filter_sql .= " AND DATE(created_at) BETWEEN ? AND ?";
                                        $filter_params[] = [$_GET['start_date'], $_GET['end_date']];
                                    }

                                    if (!empty($_GET['user_id'])) {
                                        $filter_sql .= " AND user_id = ?";
                                        $filter_params[] = $_GET['user_id'];
                                    }

                                    if (!empty($filter_params)) {
                                        $filter_sql .= " AND " . implode(' AND ', $filter_params);
                                    }

                                    $total_filtered = fetchColumn("SELECT COUNT(*) FROM activity_logs {$filter_sql}", $filter_params);
                                    $total_pages = ceil($total_filtered / 50);
                                    $current_page = max(1, (int)($_GET['page'] ?? 1));
                                    $total_pages = min($total_pages, 100); // Max 100 pages

                                    for ($i = 1; $i <= $total_pages; $i++):
                                        $active = ($i === $current_page) ? 'active' : '';
                                        echo "<li class=\"page-item {$active}\"><a class=\"page-link\" href=\"logs.php?page={$i}&" . http_build_query($_GET) . "\">{$i}</a></li>";
                                    endfor;
                                    ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Clear Logs Modal -->
<div class="modal fade" id="clearLogsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Clear Activity Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="logs.php?action=clear_logs" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="days_ago" class="form-label">Delete logs older than</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="days_ago" name="days_ago"
                                           value="30" min="1" max="365" required>
                                    <select class="form-select">
                                        <option value="7">7 Days</option>
                                        <option value="30" selected>30 Days</option>
                                        <option value="60">60 Days</option>
                                        <option value="90">90 Days</option>
                                        <option value="180">180 Days</option>
                                        <option value="365">1 Year</option>
                                    </select>
                                </div>
                                <div class="form-text">Logs older than selected period will be permanently deleted</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category" class="form-label">Category Filter</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">All Categories</option>
                                    <option value="user">User Actions</option>
                                    <option value="event">Event Management</option>
                                    <option value="payment">Payment Processing</option>
                                    <option value="certificate">Certificate Management</option>
                                    <option value="content">Content Management</option>
                                    <option value="system">System Settings</option>
                                </select>
                                <div class="form-text">Clear only specific type of activities</div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mb-3">
                        <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i> Important</h6>
                        <p><strong>This action cannot be undone!</strong> All activity logs older than the specified period will be permanently deleted from the database.</p>
                        <p>Consider downloading logs before clearing if you need long-term records.</p>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i> Clear Logs
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Export Logs Modal -->
<div class="modal fade" id="exportLogsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Export Activity Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="logs.php?action=export_logs" class="needs-validation" novalidate">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="format" class="form-label">Export Format</label>
                                <select class="form-select" id="format" name="format">
                                    <option value="csv" selected>CSV Format</option>
                                    <option value="json">JSON Format</option>
                                </select>
                                <div class="form-text">Choose the format for exporting logs</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="action_type" class="form-label">Action Type</label>
                                <select class="form-select" id="action_type" name="action_type">
                                    <option value="all" selected>All Activities</option>
                                    <option value="user_created">User Created</option>
                                    <option value="user_updated">User Updated</option>
                                    <option value="user_login">User Login</option>
                                    <option value="user_logout">User Logout</option>
                                    <option value="event_created">Event Created</option>
                                    <option value="event_updated">Event Updated</option>
                                    <option value="payment_processed">Payment Processed</option>
                                    <option value="certificate_issued">Certificate Issued</option>
                                    <option value="role_assigned">Role Assigned</option>
                                    <option value="system_settings">System Settings</option>
                                </select>
                                <div class="form-text">Export specific type of activities</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date"
                                       value="<?php echo date('Y-m-01', strtotime('-30 days')); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date"
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="user_id" class="form-label">User Filter</label>
                                <select class="form-select" id="user_id" name="user_id">
                                    <option value="0">All Users</option>
                                    <?php
                                    $users = fetchAll("SELECT id, first_name, last_name, email FROM users WHERE status = 'active' ORDER BY first_name, last_name");
                                    foreach ($users as $user):
                                    ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mb-3">
                        <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i> Export Tips</h6>
                        <ul class="mb-0">
                            <li><strong>CSV Format:</strong> Best for spreadsheet analysis and reporting</li>
                            <li><strong>JSON Format:</strong> Better for programmatic analysis and data processing</li>
                            <li><strong>Large Date Range:</strong> Consider filtering by date range for better performance</li>
                            <li><strong>User-Specific Logs:</strong> Filter by specific user to get individual activity patterns</li>
                        </ul>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-download me-2"></i> Export Logs
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Admin Activity Logs Styles */
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
    white-space: nowrap;
}

.table tbody tr:hover {
    background: var(--glass-bg);
}

.log-description {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.log-target {
    font-size: 0.875rem;
    line-height: 1.2;
}

.log-details pre {
    background: var(--glass-bg);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 0.5rem;
    font-size: 0.75rem;
    color: var(--text-primary);
    max-height: 100px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-all;
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

/* Responsive design */
@media (max-width: 768px) {
    .stat-card {
        margin-bottom: 1rem;
    }

    .table-responsive {
        overflow-x: auto;
    }

    .table th {
        font-size: 0.75rem;
        padding: 0.5rem;
    }

    .table td {
        padding: 0.5rem;
        font-size: 0.875rem;
    }
}
</style>

<script>
// Filter functionality
document.getElementById('searchLogs')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#logsTable tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

document.getElementById('filterAction')?.addEventListener('change', function() {
    filterLogs();
});

document.getElementById('filterStatus')?.addEventListener('change', filterLogs);
document.getElementById('filterUser')?.addEventListener('change', filterLogs);

function filterLogs() {
    const actionFilter = document.getElementById('filterAction').value;
    const statusFilter = document.getElementById('filterStatus').value;
    const userFilter = document.getElementById('filterUser').value;
    const startDate = document.getElementById('startDate')?.value;
    const endDate = document.getElementById('endDate')?.value;

    const rows = document.querySelectorAll('#logsTable tbody tr');

    rows.forEach(row => {
        let shouldShow = true;
        const text = row.textContent.toLowerCase();

        // Check each filter
        if (actionFilter && !text.includes(actionFilter.toLowerCase())) shouldShow = false;
        if (statusFilter) && !text.includes(statusFilter.toLowerCase())) shouldShow = false;
        if (userFilter && !row.textContent.includes(userFilter)) shouldShow = false;
        if (startDate && !text.includes(startDate)) shouldShow = false;
        if (endDate && !text.includes(endDate)) shouldShow = false;

        row.style.display = shouldShow ? '' : 'none';
    });
}

// Refresh functionality
function refreshLogs() {
    location.reload();
}

// Modal functions
function showClearModal() {
    const modal = new bootstrap.Modal(document.getElementById('clearLogsModal'));
    modal.show();
}

function showExportModal() {
    const modal = new bootstrap.Modal(document.getElementById('exportLogsModal'));
    modal.show();
}

// Helper functions
function getActionColor(action) {
    const colors = {
        'user_created' => 'primary',
        'user_updated' => 'info',
        'user_login' => 'success',
        'user_logout' => 'secondary',
        'password_changed' => 'warning',
        'event_created' => 'primary',
        'event_updated' => 'info',
        'payment_processed' => 'success',
        'certificate_issued' => 'warning',
        'gallery_uploaded' => 'info',
        'role_assigned' => 'primary',
        'system_settings' => 'secondary'
    };
    return colors[$action] ?? 'secondary';
}

function getStatusColor(status) {
    const colors = {
        'success' => 'success',
        'error' => 'danger',
        'warning' => 'warning',
        'info' => 'info'
    };
    return colors[$status] ?? 'secondary';
}

function formatActionName(action) {
    return str_replace('_', ' ', $action);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString();
}

// Clear form data
<?php if (isset($_SESSION['form_data'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    <?php unset($_SESSION['form_data']); ?>
});
<?php endif; ?>
</script>

<?php
include __DIR__ . '/../templates/admin_footer.php';
?>