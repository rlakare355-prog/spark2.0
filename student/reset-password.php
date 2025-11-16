<?php
// SPARK Platform - Password Reset
if (!function_exists('generateToken')) {
    require_once __DIR__ . '/../includes/config.php';
}
if (!function_exists('getPDO')) {
    require_once __DIR__ . '/../includes/database.php';
}
if (!function_exists('resetPassword')) {
    require_once __DIR__ . '/../includes/auth.php';
}

$message = '';
$error = '';

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    try {
        $email = sanitize($_POST['email']);

        if (empty($email)) {
            throw new Exception("Please enter your email address");
        }

        if (!isValidEmail($email)) {
            throw new Exception("Please enter a valid email address");
        }

        // Request password reset
        requestPasswordReset($email);

        // Set success message
        $_SESSION['success'][] = "Password reset link has been sent to your email address.";

        // Redirect to login page
        header('Location: login.php');
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
        $_SESSION['form_data'] = $_POST;
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    try {
        $token = sanitize($_POST['token']);
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_new_password'];

        // Validate inputs
        if (empty($token) || empty($newPassword) || empty($confirmPassword)) {
            throw new Exception("All fields are required");
        }

        if ($newPassword !== $confirmPassword) {
            throw new Exception("Passwords do not match");
        }

        if (strlen($newPassword) < 8) {
            throw new Exception("Password must be at least 8 characters long");
        }

        // Reset password
        resetPassword($token, $newPassword);

        // Set success message
        $_SESSION['success'][] = "Password has been reset successfully. You can now login with your new password.";

        // Redirect to login page
        header('Location: login.php');
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
        $_SESSION['form_data'] = $_POST;
    }
}

// Handle token validation for reset form
$isValidToken = false;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $token = sanitize($_GET['token']);

    try {
        // Check if token is valid (basic validation)
        if (!empty($token)) {
            $student = dbFetch("
                SELECT id FROM students
                WHERE reset_token = ? AND reset_token_expiry > NOW()
                LIMIT 1
            ", [$token]);

            $isValidToken = !empty($student);
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }

    if (!$isValidToken) {
        $error = "Invalid or expired reset token. Please request a new password reset.";
        $_GET['token'] = null; // Clear invalid token
    }
}

$page_title = 'Password Reset';
$page_subtitle = 'Reset Your Password';
$breadcrumb = [
    ['name' => 'Home', 'link' => SITE_URL . '/student/', 'active' => false],
    ['name' => 'Password Reset', 'link' => '', 'active' => true]
];

include __DIR__ . '/../templates/header.php';
?>

<!-- Password Reset Section -->
<section class="reset-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card reset-card" data-aos="fade-up">
                    <div class="card-header">
                        <h3 class="card-title text-center mb-0">
                            <i class="fas fa-key me-2"></i>
                            <?php echo $isValidToken ? 'Set New Password' : 'Password Reset Request'; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php include __DIR__ . '/../includes/alerts.php'; ?>

                        <?php if ($isValidToken && !$error): ?>
                            <!-- Reset Password Form -->
                            <form id="resetPasswordForm" class="needs-validation" method="POST">
                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>" required>

                                <div class="mb-4">
                                    <label for="new_password" class="form-label">New Password *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="new_password" name="new_password"
                                               required minlength="8" maxlength="255">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                            <i class="fas fa-eye" id="new_password-toggle"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">Password must be at least 8 characters long</div>
                                    <small class="form-text text-muted">Use strong password with letters, numbers, and symbols</small>
                                </div>

                                <div class="mb-4">
                                    <label for="confirm_new_password" class="form-label">Confirm New Password *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password"
                                               required minlength="8" maxlength="255">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_new_password')">
                                            <i class="fas fa-eye" id="confirm_new_password-toggle"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">Passwords must match</div>
                                </div>

                                <div class="text-center">
                                    <button type="submit" name="reset_password" class="btn btn-primary btn-lg">
                                        <i class="fas fa-save me-2"></i> Reset Password
                                    </button>
                                </div>
                            </form>

                        <?php else: ?>
                            <!-- Request Reset Form -->
                            <form id="requestResetForm" class="needs-validation" method="POST">
                                <div class="mb-4">
                                    <label for="email" class="form-label">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-envelope"></i>
                                        </span>
                                        <input type="email" class="form-control" id="email" name="email"
                                               value="<?php echo htmlspecialchars($_SESSION['form_data']['email'] ?? ''); ?>"
                                               required maxlength="255" placeholder="student@sanjivani.edu">
                                    </div>
                                    <div class="invalid-feedback">Please provide a valid email address</div>
                                </div>

                                <div class="text-center">
                                    <button type="submit" name="request_reset" class="btn btn-primary btn-lg">
                                        <i class="fas fa-paper-plane me-2"></i> Send Reset Link
                                    </button>
                                </div>
                            </form>

                            <div class="text-center mt-4">
                                <a href="<?php echo SITE_URL; ?>/student/login.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Back to Login
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Password Reset Page Styles */
.reset-section {
    min-height: 100vh;
    display: flex;
    align-items: center;
}

.reset-card {
    border: 1px solid var(--border-color);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(10px);
}

.reset-card .card-header {
    background: var(--secondary-color);
    border-bottom: 1px solid var(--border-color);
    padding: 2rem;
}

.form-control:focus,
.form-select:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 20px rgba(0, 255, 136, 0.2);
}

.input-group .btn-outline-secondary:hover {
    border-color: var(--accent-color);
    background: var(--accent-color);
    color: var(--primary-color);
}

/* Password Strength Indicator */
.password-strength {
    margin-top: 0.5rem;
    height: 5px;
    border-radius: 5px;
    background: var(--border-color);
    overflow: hidden;
}

.password-strength-bar {
    height: 100%;
    transition: all 0.3s ease;
    border-radius: 5px;
}

.strength-weak { width: 33%; background: #ff4757; }
.strength-medium { width: 66%; background: #ff9800; }
.strength-strong { width: 100%; background: var(--success-color); }

/* Loading Animation */
#resetPasswordForm button[type="submit"].loading,
#requestResetForm button[type="submit"].loading {
    pointer-events: none;
    opacity: 0.7;
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
    vertical-align: middle;
}

/* Responsive Design */
@media (max-width: 768px) {
    .reset-card {
        margin: 1rem;
    }

    .btn-lg {
        padding: 12px 20px;
        font-size: 1rem;
    }
}

/* Form field animations */
.form-control:focus,
.form-select:focus {
    transform: scale(1.02);
    transition: all 0.3s ease;
}

.input-group-text {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--accent-color);
    border-right: none;
}

/* Animation for card */
@keyframes slideInUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.reset-card {
    animation: slideInUp 0.6s ease-out;
}
</style>

<script>
// Password toggle function
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const toggle = document.getElementById(fieldId + '-toggle');

    if (field.type === 'password') {
        field.type = 'text';
        toggle.classList.remove('fa-eye');
        toggle.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        toggle.classList.remove('fa-eye-slash');
        toggle.classList.add('fa-eye');
    }
}

// Password strength checker
if (document.getElementById('new_password')) {
    document.getElementById('new_password').addEventListener('input', function() {
        const password = this.value;
        let strength = 0;

        if (password.length >= 8) strength++;
        if (password.match(/[a-z]+/)) strength++;
        if (password.match(/[A-Z]+/)) strength++;
        if (password.match(/[0-9]+/)) strength++;
        if (password.match(/[$@#&!]+/)) strength++;

        const strengthBar = document.querySelector('.password-strength-bar');
        if (strengthBar) {
            strengthBar.className = 'password-strength-bar';
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        }
    });
}

// Form validation
const resetPasswordForm = document.getElementById('resetPasswordForm');
const requestResetForm = document.getElementById('requestResetForm');

if (resetPasswordForm) {
    resetPasswordForm.addEventListener('submit', function(e) {
        const form = this;
        const submitBtn = form.querySelector('button[type="submit"]');

        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        } else {
            // Check password match
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_new_password').value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                showNotification('Passwords do not match!', 'error');
                document.getElementById('confirm_new_password').focus();
                return;
            }

            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Resetting Password...';
        }

        form.classList.add('was-validated');
    });
}

if (requestResetForm) {
    requestResetForm.addEventListener('submit', function(e) {
        const form = this;
        const submitBtn = form.querySelector('button[type="submit"]');

        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        } else {
            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending Reset Link...';
        }

        form.classList.add('was-validated');
    });
}

// Real-time email validation
if (document.getElementById('email')) {
    document.getElementById('email').addEventListener('blur', function() {
        const email = this.value;
        if (email && !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
            this.setCustomValidity('Please enter a valid email address');
        } else {
            this.setCustomValidity('');
        }
    });
}

// Form field animations
document.querySelectorAll('.form-control, .form-select').forEach(field => {
    field.addEventListener('focus', function() {
        this.parentElement.style.transform = 'scale(1.02)';
    });

    field.addEventListener('blur', function() {
        this.parentElement.style.transform = 'scale(1)';
    });
});

// Auto-focus first input
document.addEventListener('DOMContentLoaded', function() {
    const firstInput = document.querySelector('input[type="email"], input[type="password"]');
    if (firstInput) {
        setTimeout(() => firstInput.focus(), 100);
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + Enter to submit form
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        const activeForm = document.querySelector('form:visible');
        if (activeForm) {
            e.preventDefault();
            activeForm.dispatchEvent(new Event('submit'));
        }
    }
});
</script>

<?php
// Clear form data from session
unset($_SESSION['form_data']);
?>
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>