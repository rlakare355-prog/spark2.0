<?php
// SPARK Platform - Student Login
if (!function_exists('generateToken')) {
    require_once __DIR__ . '/../includes/config.php';
}
if (!function_exists('getPDO')) {
    require_once __DIR__ . '/../includes/database.php';
}
if (!function_exists('loginStudent')) {
    require_once __DIR__ . '/../includes/auth.php';
}
if (!function_exists('sendEmail')) {
    require_once __DIR__ . '/../includes/functions.php';
}

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);

        // Validate input
        if (empty($email) || empty($password)) {
            throw new Exception("Please fill in all fields");
        }

        if (!isValidEmail($email)) {
            throw new Exception("Please enter a valid email address");
        }

        // Attempt login
        $student = loginStudent($email, $password);

        // Set remember me cookie if requested
        if ($remember) {
            setcookie('remember_email', $email, time() + (86400 * 30), '/'); // 30 days
        }

        // Redirect to intended page or dashboard
        $redirectUrl = $_SESSION['redirect_url'] ?? 'dashboard.php';
        unset($_SESSION['redirect_url']);

        $_SESSION['success'][] = "Welcome back, " . $student['first_name'] . "!";
        header('Location: ' . $redirectUrl);
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        $_SESSION['form_data'] = $_POST;
    }
}

$page_title = 'Student Login';
$page_subtitle = 'Welcome Back to SPARK';
$breadcrumb = [
    ['name' => 'Home', 'link' => SITE_URL . '/student/', 'active' => false],
    ['name' => 'Login', 'link' => '', 'active' => true]
];

include __DIR__ . '/../templates/header.php';
?>

<!-- Login Section -->
<section class="login-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card login-card" data-aos="fade-up">
                    <div class="card-header">
                        <h3 class="card-title text-center mb-0">
                            <i class="fas fa-sign-in-alt me-2"></i> Student Login
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php include __DIR__ . '/../includes/alerts.php'; ?>

                        <form id="loginForm" class="needs-validation" method="POST" novalidate>
                            <div class="mb-4">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo htmlspecialchars($_SESSION['form_data']['email'] ?? ($_COOKIE['remember_email'] ?? '')); ?>"
                                           required maxlength="255" placeholder="student@sanjivani.edu">
                                </div>
                                <div class="invalid-feedback">Please provide a valid email address</div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password"
                                           required maxlength="255" placeholder="Enter your password">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                        <i class="fas fa-eye" id="password-toggle"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Please provide your password</div>
                            </div>

                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember" name="remember"
                                           <?php echo isset($_COOKIE['remember_email']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="remember">
                                        Remember me for 30 days
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg" id="loginBtn">
                                    <i class="fas fa-sign-in-alt me-2"></i> Login
                                </button>
                            </div>
                        </form>

                        <!-- Password Reset Link -->
                        <div class="text-center mt-4">
                            <a href="reset-password.php" class="text-decoration-none">
                                <i class="fas fa-key me-1"></i> Forgot Password?
                            </a>
                        </div>

                        <!-- Register Link -->
                        <div class="text-center mt-3">
                            <p class="text-muted mb-2">Don't have an account?</p>
                            <a href="<?php echo SITE_URL; ?>/student/register.php" class="btn btn-outline-primary">
                                <i class="fas fa-user-plus me-2"></i> Create Account
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Access Section -->
                <div class="card mt-4 quick-access-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-bolt me-2"></i> Quick Access
                        </h5>
                        <div class="row g-3">
                            <div class="col-6">
                                <a href="<?php echo SITE_URL; ?>/student/events.php" class="quick-access-btn">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Events</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?php echo SITE_URL; ?>/student/opportunities.php" class="quick-access-btn">
                                    <i class="fas fa-briefcase"></i>
                                    <span>Opportunities</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?php echo SITE_URL; ?>/student/team.php" class="quick-access-btn">
                                    <i class="fas fa-users"></i>
                                    <span>Team</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?php echo SITE_URL; ?>/student/gallery.php" class="quick-access-btn">
                                    <i class="fas fa-images"></i>
                                    <span>Gallery</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Admin Access Section -->
<section class="admin-access-section py-4">
    <div class="container">
        <div class="text-center">
            <p class="text-muted mb-2">Administrator Access</p>
            <a href="<?php echo SITE_URL; ?>/admin/login.php" class="btn btn-outline-secondary">
                <i class="fas fa-shield-alt me-2"></i> Admin Panel
            </a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../templates/footer.php'; ?>

<style>
/* Login Page Styles */
.login-section {
    min-height: calc(100vh - 76px);
    display: flex;
    align-items: center;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
}

.login-card {
    border: 1px solid var(--border-color);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.login-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 30px 60px rgba(0, 255, 136, 0.2);
}

.login-card .card-header {
    background: var(--secondary-color);
    border-bottom: 1px solid var(--border-color);
    padding: 2rem;
}

.input-group-text {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--accent-color);
    border-right: none;
}

.form-control {
    border-left: none;
}

.form-control:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 20px rgba(0, 255, 136, 0.2);
}

.form-control:focus ~ .input-group-text {
    border-color: var(--accent-color);
}

.quick-access-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    backdrop-filter: blur(10px);
}

.quick-access-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1.5rem 1rem;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.3s ease;
    height: 100%;
}

.quick-access-btn:hover {
    background: var(--accent-color);
    color: var(--primary-color);
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0, 255, 136, 0.3);
    text-decoration: none;
}

.quick-access-btn i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.quick-access-btn span {
    font-weight: 500;
    font-size: 0.9rem;
}

.admin-access-section {
    background: var(--secondary-color);
    border-top: 1px solid var(--border-color);
}

/* Loading Animation */
#loginBtn.loading {
    pointer-events: none;
    opacity: 0.7;
}

#loginBtn.loading::after {
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

/* Form Validation Styles */
.was-validated .form-control:invalid {
    border-color: var(--error-color);
}

.was-validated .form-control:invalid:focus {
    box-shadow: 0 0 20px rgba(255, 71, 87, 0.2);
}

.was-validated .form-control:valid {
    border-color: var(--success-color);
}

/* Responsive Design */
@media (max-width: 768px) {
    .login-card {
        margin: 1rem;
    }

    .btn-lg {
        padding: 12px 20px;
        font-size: 1rem;
    }

    .quick-access-btn {
        padding: 1rem 0.5rem;
    }

    .quick-access-btn i {
        font-size: 1.5rem;
    }
}

/* Animation for login card */
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

.login-card {
    animation: slideInUp 0.6s ease-out;
}

/* Input focus effects */
.form-control:focus,
.input-group-text {
    transition: all 0.3s ease;
}

.input-group:focus-within .input-group-text {
    border-color: var(--accent-color);
    background: var(--accent-color);
    color: var(--primary-color);
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

// Form validation
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const form = this;
    const submitBtn = document.getElementById('loginBtn');

    if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    } else {
        // Show loading state
        submitBtn.classList.add('loading');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Logging in...';
    }

    form.classList.add('was-validated');
});

// Real-time email validation
document.getElementById('email').addEventListener('blur', function() {
    const email = this.value;
    if (email && !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
        this.setCustomValidity('Please enter a valid email address');
    } else {
        this.setCustomValidity('');
    }
});

// Form field animations
document.querySelectorAll('.form-control').forEach(field => {
    field.addEventListener('focus', function() {
        this.parentElement.style.transform = 'scale(1.02)';
    });

    field.addEventListener('blur', function() {
        this.parentElement.style.transform = 'scale(1)';
    });
});

// Auto-fill detection for styling
document.getElementById('email').addEventListener('animationstart', function(e) {
    if (e.animationName === 'onAutoFillStart') {
        this.classList.add('autofilled');
    }
});

document.getElementById('password').addEventListener('animationstart', function(e) {
    if (e.animationName === 'onAutoFillStart') {
        this.classList.add('autofilled');
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + Enter to submit form
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('loginForm').dispatchEvent(new Event('submit'));
    }
});

// Enhanced input focus effect
document.querySelectorAll('.input-group').forEach(group => {
    const input = group.querySelector('.form-control');
    const icon = group.querySelector('.input-group-text');

    if (input && icon) {
        input.addEventListener('focus', function() {
            icon.style.background = 'var(--accent-color)';
            icon.style.color = 'var(--primary-color)';
            icon.style.transform = 'scale(1.1)';
        });

        input.addEventListener('blur', function() {
            icon.style.background = 'var(--card-bg)';
            icon.style.color = 'var(--accent-color)';
            icon.style.transform = 'scale(1)';
        });
    }
});

// Add CSS for autofill detection
const style = document.createElement('style');
style.textContent = `
    @keyframes onAutoFillStart {
        from { /**/ }
        to { /**/ }
    }

    input:-webkit-autofill {
        background: var(--card-bg) !important;
        color: var(--text-primary) !important;
    }

    input:-webkit-autofill ~ .input-group-text {
        background: var(--accent-color) !important;
        color: var(--primary-color) !important;
    }
`;
document.head.appendChild(style);

// Clear session form data
<?php if (isset($_SESSION['form_data'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    <?php unset($_SESSION['form_data']); ?>
});
<?php endif; ?>
</script>