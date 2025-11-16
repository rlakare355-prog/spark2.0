<?php
// SPARK Platform - Admin Payment Management
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireAdmin();

// Handle payment operations
$action = $_GET['action'] ?? 'list';

if ($action === 'refund' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $payment_id = (int)$_POST['payment_id'];
        $refund_amount = (float)$_POST['refund_amount'];
        $refund_reason = sanitize($_POST['refund_reason']);

        if (empty($refund_reason)) {
            throw new Exception("Refund reason is required");
        }

        if ($refund_amount <= 0) {
            throw new Exception("Refund amount must be greater than 0");
        }

        // Get payment details
        $payment = fetchRow("
            SELECT p.*, e.title as event_title, s.first_name, s.last_name, s.email
            FROM payments p
            JOIN events e ON p.event_id = e.id
            JOIN students s ON p.student_id = s.id
            WHERE p.id = ?
        ", [$payment_id]);

        if (!$payment) {
            throw new Exception("Payment not found");
        }

        if ($payment['status'] !== 'completed') {
            throw new Exception("Only completed payments can be refunded");
        }

        if ($refund_amount > $payment['amount']) {
            throw new Exception("Refund amount cannot exceed payment amount");
        }

        // Process refund through Razorpay (in real implementation)
        $refund_id = 'refund_' . uniqid();

        // Update payment status
        if ($refund_amount >= $payment['amount']) {
            // Full refund
            executeUpdate("
                UPDATE payments SET
                status = 'refunded',
                refund_id = ?,
                refund_amount = ?,
                refund_reason = ?,
                refund_date = NOW()
                WHERE id = ?
            ", [$refund_id, $refund_amount, $refund_reason, $payment_id]);

            // Cancel event registration
            executeUpdate("
                UPDATE event_registrations
                SET status = 'cancelled', cancelled_at = NOW()
                WHERE payment_id = ?
            ", [$payment_id]);
        } else {
            // Partial refund
            executeUpdate("
                UPDATE payments SET
                status = 'partially_refunded',
                refund_id = ?,
                refund_amount = ?,
                refund_reason = ?,
                refund_date = NOW()
                WHERE id = ?
            ", [$refund_id, $refund_amount, $refund_reason, $payment_id]);
        }

        // Send refund email (in real implementation)
        $to = $payment['email'];
        $subject = "Refund Processed - SPARK Platform";
        $message = "
            <h2>Refund Processed</h2>
            <p>Dear {$payment['first_name']} {$payment['last_name']},</p>
            <p>A refund of ₹{$refund_amount} has been processed for your payment regarding '{$payment['event_title']}'.</p>
            <p><strong>Refund Details:</strong></p>
            <ul>
                <li>Refund ID: {$refund_id}</li>
                <li>Amount: ₹{$refund_amount}</li>
                <li>Reason: {$refund_reason}</li>
                <li>Payment ID: {$payment['razorpay_payment_id']}</li>
            </ul>
            <p>The refund will be credited to your original payment method within 5-7 business days.</p>
            <p>Best regards,<br>SPARK Team</p>
        ";

        // sendEmail($to, $subject, $message); // Uncomment when email service is ready

        // Log activity
        logActivity('payment_refunded', "Refund of ₹{$refund_amount} processed for payment ID {$payment_id}", $_SESSION['user_id'], $payment_id);

        $_SESSION['success'][] = "Refund processed successfully!";
        header('Location: payments.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: payments.php?action=refund&id=' . $_POST['payment_id']);
        exit();
    }
}

if ($action === 'verify_payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $payment_id = (int)$_POST['payment_id'];

        // Get payment details
        $payment = fetchRow("
            SELECT p.*, e.title as event_title
            FROM payments p
            JOIN events e ON p.event_id = e.id
            WHERE p.id = ?
        ", [$payment_id]);

        if (!$payment) {
            throw new Exception("Payment not found");
        }

        // Verify payment with Razorpay (in real implementation)
        $verified = true; // Simulate verification

        if ($verified) {
            executeUpdate("
                UPDATE payments SET
                status = 'verified',
                verified_at = NOW(),
                verified_by = ?
                WHERE id = ?
            ", [$_SESSION['user_id'], $payment_id]);

            // Confirm event registration
            executeUpdate("
                UPDATE event_registrations
                SET status = 'confirmed'
                WHERE payment_id = ?
            ", [$payment_id]);

            logActivity('payment_verified', "Payment ID {$payment_id} verified manually", $_SESSION['user_id'], $payment_id);
            $_SESSION['success'][] = "Payment verified successfully!";
        } else {
            throw new Exception("Payment verification failed");
        }

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: payments.php');
    exit();
}

if ($action === 'export') {
    try {
        $format = $_GET['format'] ?? 'csv';
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');
        $status = $_GET['status'] ?? '';

        // Build query
        $sql = "
            SELECT p.*, e.title as event_title, s.first_name, s.last_name, s.email, s.phone
            FROM payments p
            JOIN events e ON p.event_id = e.id
            JOIN students s ON p.student_id = s.id
            WHERE p.created_at BETWEEN ? AND ?
        ";
        $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];

        if ($status) {
            $sql .= " AND p.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY p.created_at DESC";
        $payments = fetchAll($sql, $params);

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="payments_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Event', 'Student', 'Email', 'Amount', 'Status', 'Payment ID', 'Date']);

            foreach ($payments as $payment) {
                fputcsv($output, [
                    $payment['id'],
                    $payment['event_title'],
                    $payment['first_name'] . ' ' . $payment['last_name'],
                    $payment['email'],
                    $payment['amount'],
                    $payment['status'],
                    $payment['razorpay_payment_id'],
                    $payment['created_at']
                ]);
            }

            fclose($output);
            exit();
        }

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: payments.php');
        exit();
    }
}

$page_title = 'Payment Management';
$breadcrumb = [
    ['name' => 'Dashboard', 'link' => 'index.php', 'active' => false],
    ['name' => 'Payments', 'link' => '', 'active' => true]
];

include __DIR__ . '/../templates/admin_header.php';
?>

<!-- Payment Management Section -->
<section class="admin-section py-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="section-title">
                            <i class="fas fa-credit-card me-2"></i> Payment Management
                        </h2>
                        <p class="text-muted">Manage all transactions, refunds, and payment verification</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-success" onclick="exportPayments()">
                            <i class="fas fa-download me-2"></i> Export
                        </button>
                        <button class="btn btn-outline-info" onclick="showAnalytics()">
                            <i class="fas fa-chart-line me-2"></i> Analytics
                        </button>
                    </div>
                </div>

                <?php include __DIR__ . '/../includes/alerts.php'; ?>

                <?php if ($action === 'refund'): ?>
                    <!-- Refund Form -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-undo me-2"></i> Process Refund
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $payment_id = (int)$_GET['id'];
                            $payment = fetchRow("
                                SELECT p.*, e.title as event_title, s.first_name, s.last_name, s.email
                                FROM payments p
                                JOIN events e ON p.event_id = e.id
                                JOIN students s ON p.student_id = s.id
                                WHERE p.id = ?
                            ", [$payment_id]);

                            if (!$payment):
                                $_SESSION['errors'][] = "Payment not found";
                                header('Location: payments.php');
                                exit();
                            endif;
                            ?>

                            <form method="POST" class="needs-validation" novalidate>
                                <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">

                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">Payment Information</h6>
                                        <div class="bg-light p-3 rounded">
                                            <p><strong>Event:</strong> <?php echo htmlspecialchars($payment['event_title']); ?></p>
                                            <p><strong>Student:</strong> <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></p>
                                            <p><strong>Email:</strong> <?php echo htmlspecialchars($payment['email']); ?></p>
                                            <p><strong>Payment ID:</strong> <?php echo htmlspecialchars($payment['razorpay_payment_id']); ?></p>
                                            <p><strong>Amount Paid:</strong> ₹<?php echo number_format($payment['amount'], 2); ?></p>
                                            <p><strong>Payment Date:</strong> <?php echo formatDate($payment['created_at']); ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">Refund Details</h6>

                                        <div class="mb-3">
                                            <label for="refund_amount" class="form-label">Refund Amount (₹) *</label>
                                            <input type="number" class="form-control" id="refund_amount" name="refund_amount"
                                                   value="<?php echo $payment['amount']; ?>"
                                                   min="0.01" max="<?php echo $payment['amount']; ?>" step="0.01" required>
                                            <div class="form-text">Maximum refundable: ₹<?php echo number_format($payment['amount'], 2); ?></div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="refund_reason" class="form-label">Refund Reason *</label>
                                            <textarea class="form-control" id="refund_reason" name="refund_reason"
                                                      rows="4" required placeholder="Please provide a reason for this refund..."></textarea>
                                            <div class="invalid-feedback">Refund reason is required</div>
                                        </div>

                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong>Important:</strong> Processing a refund will update the payment status and may affect event registration.
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <a href="payments.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-undo me-2"></i> Process Refund
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Payments List -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-list me-2"></i> All Transactions
                                    </h5>
                                </div>
                                <div class="col-auto">
                                    <div class="d-flex gap-2 flex-wrap">
                                        <!-- Search -->
                                        <div class="input-group" style="width: 250px;">
                                            <input type="text" class="form-control" id="searchPayments" placeholder="Search payments...">
                                            <button class="btn btn-outline-secondary" type="button">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>

                                        <!-- Date Range -->
                                        <div class="input-group" style="width: 200px;">
                                            <input type="date" class="form-control" id="startDate" value="<?php echo date('Y-m-01'); ?>">
                                        </div>
                                        <div class="input-group" style="width: 200px;">
                                            <input type="date" class="form-control" id="endDate" value="<?php echo date('Y-m-t'); ?>">
                                        </div>

                                        <!-- Status Filter -->
                                        <select class="form-select" id="filterStatus" style="width: 150px;">
                                            <option value="">All Status</option>
                                            <option value="pending">Pending</option>
                                            <option value="completed">Completed</option>
                                            <option value="failed">Failed</option>
                                            <option value="refunded">Refunded</option>
                                            <option value="partially_refunded">Partially Refunded</option>
                                            <option value="verified">Verified</option>
                                        </select>

                                        <!-- Refresh -->
                                        <button class="btn btn-outline-primary" onclick="refreshPayments()">
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
                                            <i class="fas fa-money-bill-wave"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM payments", []); ?></h3>
                                            <p>Total Txns</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-success">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM payments WHERE status = 'completed'", []); ?></h3>
                                            <p>Completed</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-info">
                                            <i class="fas fa-rupee-sign"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3>₹<?php echo number_format(fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'", []), 0); ?></h3>
                                            <p>Revenue</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-warning">
                                            <i class="fas fa-undo"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM payments WHERE status LIKE '%refunded%'", []); ?></h3>
                                            <p>Refunds</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-danger">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3>₹<?php echo number_format(fetchColumn("SELECT COALESCE(SUM(refund_amount), 0) FROM payments WHERE status LIKE '%refunded%'", []), 0); ?></h3>
                                            <p>Refunded</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Payments Table -->
                            <div class="table-responsive">
                                <table class="table table-hover" id="paymentsTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Event</th>
                                            <th>Student</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                            <th>Payment ID</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sql = "
                                            SELECT p.*, e.title as event_title, s.first_name, s.last_name, s.email
                                            FROM payments p
                                            JOIN events e ON p.event_id = e.id
                                            JOIN students s ON p.student_id = s.id
                                            ORDER BY p.created_at DESC
                                            LIMIT 100
                                        ";
                                        $payments = fetchAll($sql);

                                        foreach ($payments as $payment):
                                        ?>
                                        <tr data-payment-id="<?php echo $payment['id']; ?>">
                                            <td>
                                                <span class="badge bg-secondary">#<?php echo $payment['id']; ?></span>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($payment['event_title']); ?></strong>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($payment['email']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong>₹<?php echo number_format($payment['amount'], 2); ?></strong>
                                                    <?php if (!empty($payment['refund_amount'])): ?>
                                                        <br><small class="text-warning">
                                                            Refunded: ₹<?php echo number_format($payment['refund_amount'], 2); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo ucfirst($payment['payment_method'] ?? 'Online'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getPaymentStatusColor($payment['status']); ?>">
                                                    <?php echo formatPaymentStatus($payment['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="font-monospace">
                                                    <?php echo htmlspecialchars(substr($payment['razorpay_payment_id'], 0, 15)) . '...'; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div>
                                                    <?php echo formatDate($payment['created_at']); ?>
                                                    <br><small class="text-muted"><?php echo formatTime($payment['created_at']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-info" onclick="viewPayment(<?php echo $payment['id']; ?>)" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($payment['status'] === 'completed'): ?>
                                                        <button class="btn btn-sm btn-outline-warning" onclick="refundPayment(<?php echo $payment['id']; ?>)" title="Refund">
                                                            <i class="fas fa-undo"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($payment['status'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-outline-success" onclick="verifyPayment(<?php echo $payment['id']; ?>)" title="Verify">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="downloadReceipt(<?php echo $payment['id']; ?>)" title="Download Receipt">
                                                        <i class="fas fa-download"></i>
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

<!-- Payment Details Modal -->
<div class="modal fade" id="paymentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="paymentDetailsContent">
                <!-- Payment details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Payment Analytics Modal -->
<div class="modal fade" id="paymentAnalyticsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Analytics</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <canvas id="revenueChart"></canvas>
                    </div>
                    <div class="col-md-6">
                        <canvas id="paymentStatusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Admin Payment Management Styles */
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
.badge.bg-success { background: var(--success-color) !important; }
.badge.bg-warning { background: var(--warning-color) !important; }
.badge.bg-danger { background: var(--error-color) !important; }
.badge.bg-info { background: var(--info-color) !important; }
.badge.bg-primary { background: var(--primary-color) !important; }
.badge.bg-secondary { background: var(--text-muted) !important; }

/* Alert styling */
.alert {
    background: var(--glass-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
}

.alert-warning {
    border-color: var(--warning-color);
    background: rgba(255, 193, 7, 0.1);
}

/* Form enhancements */
.form-control:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 15px rgba(0, 255, 136, 0.2);
}

.bg-light {
    background: var(--glass-bg) !important;
    border: 1px solid var(--border-color);
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
document.getElementById('searchPayments')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#paymentsTable tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Filter functionality
document.getElementById('filterStatus')?.addEventListener('change', function() {
    const filterValue = this.value;
    const rows = document.querySelectorAll('#paymentsTable tbody tr');

    rows.forEach(row => {
        if (!filterValue) {
            row.style.display = '';
        } else {
            const statusBadge = row.querySelector('td:nth-child(6) .badge');
            const status = statusBadge.textContent.toLowerCase().replace(' ', '_');
            row.style.display = status === filterValue ? '' : 'none';
        }
    });
});

// Date range filtering
function filterByDateRange() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const rows = document.querySelectorAll('#paymentsTable tbody tr');

    rows.forEach(row => {
        const dateText = row.querySelector('td:nth-child(8) div').textContent.trim();
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

document.getElementById('startDate')?.addEventListener('change', filterByDateRange);
document.getElementById('endDate')?.addEventListener('change', filterByDateRange);

// Payment operations
function viewPayment(paymentId) {
    const modal = new bootstrap.Modal(document.getElementById('paymentDetailsModal'));
    const content = document.getElementById('paymentDetailsContent');

    // Show loading
    content.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    modal.show();

    // Fetch payment details
    fetch(`../api/payments.php?action=get&id=${paymentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const payment = data.data;
                content.innerHTML = `
                    <div class="payment-details">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="text-primary">Transaction Information</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Payment ID:</strong></td><td>#${payment.id}</td></tr>
                                    <tr><td><strong>Razorpay ID:</strong></td><td><span class="font-monospace">${payment.razorpay_payment_id}</span></td></tr>
                                    <tr><td><strong>Order ID:</strong></td><td><span class="font-monospace">${payment.razorpay_order_id || 'N/A'}</span></td></tr>
                                    <tr><td><strong>Amount:</strong></td><td>₹${payment.amount}</td></tr>
                                    <tr><td><strong>Method:</strong></td><td>${payment.payment_method || 'Online'}</td></tr>
                                    <tr><td><strong>Status:</strong></td><td><span class="badge bg-${getPaymentStatusColor(payment.status)}">${formatPaymentStatus(payment.status)}</span></td></tr>
                                    <tr><td><strong>Created:</strong></td><td>${formatDateTime(payment.created_at)}</td></tr>
                                    ${payment.refund_amount ? `<tr><td><strong>Refund Amount:</strong></td><td>₹${payment.refund_amount}</td></tr>` : ''}
                                    ${payment.refund_date ? `<tr><td><strong>Refund Date:</strong></td><td>${formatDateTime(payment.refund_date)}</td></tr>` : ''}
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-primary">Event & Student Information</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Event:</strong></td><td>${payment.event_title}</td></tr>
                                    <tr><td><strong>Student:</strong></td><td>${payment.first_name} ${payment.last_name}</td></tr>
                                    <tr><td><strong>Email:</strong></td><td>${payment.email}</td></tr>
                                    <tr><td><strong>Phone:</strong></td><td>${payment.phone || 'N/A'}</td></tr>
                                </table>
                            </div>
                        </div>
                        ${payment.refund_reason ? `
                            <div class="mb-4">
                                <h6 class="text-primary">Refund Reason</h6>
                                <div class="bg-light p-3 rounded">${payment.refund_reason}</div>
                            </div>
                        ` : ''}
                    </div>
                `;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Error loading payment details</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error loading payment details</div>';
        });
}

function refundPayment(paymentId) {
    window.location.href = `payments.php?action=refund&id=${paymentId}`;
}

function verifyPayment(paymentId) {
    if (confirm('Are you sure you want to verify this payment?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'payments.php?action=verify_payment';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'payment_id';
        input.value = paymentId;

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

function downloadReceipt(paymentId) {
    window.open(`../api/receipt.php?id=${paymentId}`, '_blank');
}

function exportPayments() {
    const startDate = document.getElementById('startDate')?.value || '';
    const endDate = document.getElementById('endDate')?.value || '';
    const status = document.getElementById('filterStatus')?.value || '';

    let url = `payments.php?action=export&format=csv`;
    if (startDate) url += `&start_date=${startDate}`;
    if (endDate) url += `&end_date=${endDate}`;
    if (status) url += `&status=${status}`;

    window.location.href = url;
}

function showAnalytics() {
    const modal = new bootstrap.Modal(document.getElementById('paymentAnalyticsModal'));
    modal.show();

    // Initialize charts
    setTimeout(() => {
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Revenue',
                    data: [12000, 19000, 15000, 25000, 22000, 30000],
                    borderColor: '#00ff88',
                    backgroundColor: 'rgba(0, 255, 136, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Monthly Revenue'
                    }
                }
            }
        });

        // Payment Status Chart
        const statusCtx = document.getElementById('paymentStatusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Pending', 'Failed', 'Refunded'],
                datasets: [{
                    data: [150, 25, 10, 15],
                    backgroundColor: [
                        '#00ff88',
                        '#ffc107',
                        '#dc3545',
                        '#17a2b8'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Payment Status Distribution'
                    }
                }
            }
        });
    }, 500);
}

function refreshPayments() {
    window.location.reload();
}

// Helper functions
function getPaymentStatusColor(status) {
    const colors = {
        'completed': 'success',
        'pending': 'warning',
        'failed': 'danger',
        'refunded': 'info',
        'partially_refunded': 'warning',
        'verified': 'primary'
    };
    return colors[status] || 'secondary';
}

function formatPaymentStatus(status) {
    return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function formatDateTime(dateTime) {
    const date = new Date(dateTime);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}
</script>

<?php
// Helper functions
function getPaymentStatusColor($status) {
    $colors = [
        'completed' => 'success',
        'pending' => 'warning',
        'failed' => 'danger',
        'refunded' => 'info',
        'partially_refunded' => 'warning',
        'verified' => 'primary'
    ];
    return $colors[$status] ?? 'secondary';
}

function formatPaymentStatus($status) {
    return ucwords(str_replace('_', ' ', $status));
}

include __DIR__ . '/../templates/admin_footer.php';
?>