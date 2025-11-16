<?php
require_once '../includes/admin_header.php';
require_once '../includes/MailjetService.php';

// Only allow admins and super_admins to access email settings
requireRole(['super_admin', 'admin']);

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_settings'])) {
            // Update Mailjet settings
            $settings = [
                'mailjet_api_key' => sanitize($_POST['mailjet_api_key'] ?? ''),
                'mailjet_api_secret' => sanitize($_POST['mailjet_api_secret'] ?? ''),
                'mailjet_test_mode' => isset($_POST['mailjet_test_mode']) ? '1' : '0'
            ];

            foreach ($settings as $key => $value) {
                updateSetting($key, $value);
            }

            logActivity($_SESSION['user_id'], 'update', 'email_settings', null, 'Updated Mailjet email settings');
            $message = 'Email settings updated successfully!';
        }

        if (isset($_POST['send_test_email'])) {
            // Send test email
            $mailjet = new MailjetService();
            $testEmail = sanitize($_POST['test_email'] ?? '');
            $testName = sanitize($_POST['test_name'] ?? 'Test User');

            if (empty($testEmail)) {
                throw new Exception("Test email address is required");
            }

            $result = $mailjet->sendEmail(
                $testEmail,
                'Test Email from SPARK Platform',
                '<h2>Test Email</h2><p>This is a test email from the SPARK platform to verify Mailjet configuration.</p><p>Time: ' . date('Y-m-d H:i:s') . '</p><p>Regards,<br>SPARK Team</p>'
            );

            if ($result['success']) {
                $message = 'Test email sent successfully!';
                logActivity($_SESSION['user_id'], 'test', 'email_settings', null, "Sent test email to $testEmail");
            } else {
                throw new Exception($result['error'] ?? 'Failed to send test email');
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current settings
$settings = loadSettings();
$currentStats = null;

// Try to get email statistics
try {
    $mailjet = new MailjetService();
    $currentStats = $mailjet->getEmailStatistics(date('Y-m-01'), date('Y-m-t'));
} catch (Exception $e) {
    // Stats might fail if API keys are not configured
    $currentStats = null;
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="fas fa-envelope me-2"></i>
                    Email Settings
                </h2>
                <div>
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

    <!-- Email Statistics -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card admin-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        Email Statistics (This Month)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($currentStats): ?>
                        <div class="row text-center">
                            <div class="col-md-3 mb-3">
                                <div class="stat-card bg-primary">
                                    <div class="stat-icon">
                                        <i class="fas fa-paper-plane"></i>
                                    </div>
                                    <div class="stat-value"><?php echo number_format($currentStats['total_sent']); ?></div>
                                    <div class="stat-label">Total Sent</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stat-card bg-success">
                                    <div class="stat-icon">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="stat-value"><?php echo number_format($currentStats['total_delivered']); ?></div>
                                    <div class="stat-label">Delivered</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stat-card bg-info">
                                    <div class="stat-icon">
                                        <i class="fas fa-envelope-open"></i>
                                    </div>
                                    <div class="stat-value"><?php echo number_format($currentStats['total_opened']); ?></div>
                                    <div class="stat-label">Opened</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stat-card bg-warning">
                                    <div class="stat-icon">
                                        <i class="fas fa-mouse-pointer"></i>
                                    </div>
                                    <div class="stat-value"><?php echo number_format($currentStats['total_clicked']); ?></div>
                                    <div class="stat-label">Clicked</div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-4 mb-3">
                                <div class="stat-card bg-secondary">
                                    <div class="stat-label">Delivery Rate</div>
                                    <div class="stat-value"><?php echo round($currentStats['delivery_rate'], 1); ?>%</div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="stat-card bg-secondary">
                                    <div class="stat-label">Open Rate</div>
                                    <div class="stat-value"><?php echo round($currentStats['open_rate'], 1); ?>%</div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="stat-card bg-secondary">
                                    <div class="stat-label">Click Rate</div>
                                    <div class="stat-value"><?php echo round($currentStats['click_rate'], 1); ?>%</div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Email statistics are not available. Please configure Mailjet API keys to view statistics.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Mailjet Settings -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card admin-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-cog me-2"></i>
                        Mailjet Configuration
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="mailjet_api_key" class="form-label">Mailjet API Key</label>
                            <input type="text" class="form-control" id="mailjet_api_key" name="mailjet_api_key"
                                   value="<?php echo htmlspecialchars($settings['mailjet_api_key'] ?? ''); ?>"
                                   placeholder="Enter your Mailjet API Key" required>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Get your API key from your <a href="https://app.mailjet.com/account/api_keys" target="_blank">Mailjet account</a>
                            </div>
                            <div class="invalid-feedback">API key is required</div>
                        </div>

                        <div class="mb-3">
                            <label for="mailjet_api_secret" class="form-label">Mailjet API Secret</label>
                            <input type="password" class="form-control" id="mailjet_api_secret" name="mailjet_api_secret"
                                   value="<?php echo htmlspecialchars($settings['mailjet_api_secret'] ?? ''); ?>"
                                   placeholder="Enter your Mailjet API Secret" required>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Keep your API secret secure and never share it publicly
                            </div>
                            <div class="invalid-feedback">API secret is required</div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="mailjet_test_mode" name="mailjet_test_mode"
                                       <?php echo (isset($settings['mailjet_test_mode']) && $settings['mailjet_test_mode']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mailjet_test_mode">
                                    <strong>Test Mode</strong>
                                    <div class="form-text">Enable test mode to log emails without actually sending them</div>
                                </label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" name="update_settings" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                            <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#testEmailModal">
                                <i class="fas fa-paper-plane me-2"></i>Send Test Email
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card admin-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-question-circle me-2"></i>
                        Mailjet Setup Guide
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                        <h6><i class="fas fa-lightbulb me-2"></i>Quick Setup</h6>
                        <ol class="mb-0 small">
                            <li>Create a <a href="https://www.mailjet.com/" target="_blank">Mailjet account</a></li>
                            <li>Go to Account Settings → API Keys</li>
                            <li>Create a new API key with full permissions</li>
                            <li>Copy API Key and Secret to the form</li>
                            <li>Save settings and send a test email</li>
                        </ol>
                    </div>

                    <h6 class="text-primary mb-2">Features Available:</h6>
                    <ul class="small mb-3">
                        <li><i class="fas fa-check text-success me-2"></i>Email verification</li>
                        <li><i class="fas fa-check text-success me-2"></i>Password reset emails</li>
                        <li><i class="fas fa-check text-success me-2"></i>Event notifications</li>
                        <li><i class="fas fa-check text-success me-2"></i>Payment confirmations</li>
                        <li><i class="fas fa-check text-success me-2"></i>Certificate emails</li>
                        <li><i class="fas fa-check text-success me-2"></i>Opportunity alerts</li>
                    </ul>

                    <div class="alert alert-warning mb-0">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Important</h6>
                        <p class="mb-0 small">
                            Make sure your sending domain is verified in Mailjet for better deliverability.
                            Test mode allows you to test functionality without sending emails.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Email Logs -->
    <div class="row">
        <div class="col-12">
            <div class="card admin-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history me-2"></i>
                        Recent Email Logs
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    $recentLogs = dbFetchAll("SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 10");
                    if ($recentLogs): ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>To</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Message ID</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentLogs as $log): ?>
                                        <tr>
                                            <td><?php echo formatDate($log['created_at'], true); ?></td>
                                            <td><?php echo htmlspecialchars(substr($log['to_email'], 0, 50) . (strlen($log['to_email']) > 50 ? '...' : '')); ?></td>
                                            <td><?php echo htmlspecialchars(substr($log['subject'], 0, 30) . (strlen($log['subject']) > 30 ? '...' : '')); ?></td>
                                            <td>
                                                <?php if ($log['status'] === 'success'): ?>
                                                    <span class="badge bg-success">Success</span>
                                                <?php elseif ($log['status'] === 'error'): ?>
                                                    <span class="badge bg-danger">Error</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['message_id'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="email_logs.php" class="btn btn-outline-primary">
                                <i class="fas fa-list me-2"></i>View All Email Logs
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No email logs found yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Test Email Modal -->
<div class="modal fade" id="testEmailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">
                    <i class="fas fa-paper-plane me-2"></i>
                    Send Test Email
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="test_email" class="form-label">Test Email Address</label>
                        <input type="email" class="form-control bg-secondary text-light border-secondary" id="test_email" name="test_email" required>
                    </div>
                    <div class="mb-3">
                        <label for="test_name" class="form-label">Recipient Name</label>
                        <input type="text" class="form-control bg-secondary text-light border-secondary" id="test_name" name="test_name" value="Test User">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="send_test_email" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Send Test Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Email Settings Styles */
.stat-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
}

.stat-icon {
    font-size: 2rem;
    margin-bottom: 1rem;
    opacity: 0.8;
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

.alert-info {
    background: rgba(23, 162, 184, 0.1);
    border: 1px solid rgba(23, 162, 184, 0.3);
    color: var(--text-primary);
}

.alert-warning {
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid rgba(255, 193, 7, 0.3);
    color: var(--text-primary);
}

/* Responsive Design */
@media (max-width: 768px) {
    .stat-card {
        margin-bottom: 1rem;
    }

    .col-md-3 {
        margin-bottom: 1rem;
    }
}
</style>

<script>
// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Show password on toggle
    const apiSecretInput = document.getElementById('mailjet_api_secret');
    if (apiSecretInput) {
        const toggleButton = document.createElement('button');
        toggleButton.type = 'button';
        toggleButton.className = 'btn btn-outline-secondary btn-sm mt-2';
        toggleButton.innerHTML = '<i class="fas fa-eye me-2"></i>Show';
        toggleButton.style.position = 'absolute';
        toggleButton.style.right = '10px';
        toggleButton.style.top = '38px';

        apiSecretInput.parentElement.style.position = 'relative';
        apiSecretInput.parentElement.appendChild(toggleButton);

        toggleButton.addEventListener('click', function() {
            if (apiSecretInput.type === 'password') {
                apiSecretInput.type = 'text';
                toggleButton.innerHTML = '<i class="fas fa-eye-slash me-2"></i>Hide';
            } else {
                apiSecretInput.type = 'password';
                toggleButton.innerHTML = '<i class="fas fa-eye me-2"></i>Show';
            }
        });
    }
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>