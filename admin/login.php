<?php
// SPARK Platform - Admin Login
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    // Check if user is admin
    if (isAdmin()) {
        header('Location: index.php');
    } else {
        header('Location: ../student/dashboard.php');
    }
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

        // Attempt admin login
        $admin = loginAdmin($email, $password);

        // Log login attempt
        logActivity($admin['id'], 'login', 'admin', null, 'Admin login successful');

        // Set remember me cookie if requested
        if ($remember) {
            setcookie('admin_email', $email, time() + (86400 * 7), '/'); // 7 days
        }

        $_SESSION['success'][] = "Welcome to Admin Panel, " . $admin['first_name'] . "!";
        header('Location: index.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        $_SESSION['form_data'] = $_POST;

        // Log failed login attempt
        logActivity(0, 'login_failed', 'admin', null, 'Failed admin login attempt for: ' . $email);
    }
}

$page_title = 'Admin Login';
$page_subtitle = 'SPARK Administration Panel';
$hide_page_header = true;

include __DIR__ . '/../templates/header.php';
?>

<!-- Admin Login Section -->
<section class="admin-login-section">
    <div class="container-fluid">
        <div class="row min-vh-100">
            <!-- Left Side - Branding -->
            <div class="col-lg-6 d-none d-lg-flex admin-login-left">
                <div class="admin-login-left-content">
                    <div class="admin-logo-section">
                        <div class="spark-logo">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <h1 class="admin-brand">SPARK</h1>
                        <p class="admin-tagline">Administration Panel</p>
                    </div>

                    <div class="admin-features">
                        <div class="feature-item" data-aos="fade-right" data-aos-delay="100">
                            <div class="feature-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="feature-text">
                                <h4>Analytics Dashboard</h4>
                                <p>Real-time insights and reporting</p>
                            </div>
                        </div>

                        <div class="feature-item" data-aos="fade-right" data-aos-delay="200">
                            <div class="feature-icon">
                                <i class="fas fa-users-cog"></i>
                            </div>
                            <div class="feature-text">
                                <h4>User Management</h4>
                                <p>Comprehensive control and permissions</p>
                            </div>
                        </div>

                        <div class="feature-item" data-aos="fade-right" data-aos-delay="300">
                            <div class="feature-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="feature-text">
                                <h4>Secure Access</h4>
                                <p>Role-based authentication system</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Login Form -->
            <div class="col-lg-6">
                <div class="admin-login-right">
                    <div class="login-form-container" data-aos="fade-left">
                        <div class="login-form-header">
                            <h2 class="form-title">Welcome Back</h2>
                            <p class="form-subtitle">Sign in to access the admin panel</p>
                        </div>

                        <?php include __DIR__ . '/../includes/alerts.php'; ?>

                        <form id="adminLoginForm" class="admin-login-form" method="POST" novalidate>
                            <div class="form-group mb-4">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-2"></i>Email Address
                                </label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($_SESSION['form_data']['email'] ?? ($_COOKIE['admin_email'] ?? '')); ?>"
                                       required maxlength="255" placeholder="admin@sanjivani.edu">
                                <div class="invalid-feedback">Please provide a valid email address</div>
                            </div>

                            <div class="form-group mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Password
                                </label>
                                <div class="password-input-group">
                                    <input type="password" class="form-control" id="password" name="password"
                                           required maxlength="255" placeholder="Enter your admin password">
                                    <button class="btn password-toggle" type="button" onclick="togglePassword('password')">
                                        <i class="fas fa-eye" id="password-toggle"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Please provide your password</div>
                            </div>

                            <div class="form-group mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember" name="remember"
                                           <?php echo isset($_COOKIE['admin_email']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="remember">
                                        <i class="fas fa-clock me-1"></i>
                                        Remember me for 7 days
                                    </label>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn admin-login-btn" id="loginBtn">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    <span class="btn-text">Sign In</span>
                                    <span class="btn-loading" style="display: none;">
                                        <i class="fas fa-spinner fa-spin me-2"></i>
                                        Authenticating...
                                    </span>
                                </button>
                            </div>

                            <div class="login-footer">
                                <div class="forgot-password">
                                    <a href="forgot-password.php">
                                        <i class="fas fa-key me-1"></i> Forgot Password?
                                    </a>
                                </div>

                                <div class="back-to-student">
                                    <a href="../student/login.php">
                                        <i class="fas fa-arrow-left me-1"></i> Back to Student Login
                                    </a>
                                </div>
                            </div>
                        </form>

                        <!-- Security Notice -->
                        <div class="security-notice">
                            <i class="fas fa-shield-alt me-2"></i>
                            <small>This is a secure administrative area. Unauthorized access is prohibited and will be logged.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Background Animation -->
<div class="login-bg-animation">
    <div class="floating-element float-1">
        <i class="fas fa-shield-alt"></i>
    </div>
    <div class="floating-element float-2">
        <i class="fas fa-lock"></i>
    </div>
    <div class="floating-element float-3">
        <i class="fas fa-user-shield"></i>
    </div>
    <div class="floating-element float-4">
        <i class="fas fa-key"></i>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>

<style>
/* Admin Login Specific Styles */
.admin-login-section {
    min-height: 100vh;
    padding: 0;
    position: relative;
    overflow: hidden;
}

.admin-login-left {
    background: linear-gradient(135deg, var(--accent-color) 0%, var(--neon-blue) 100%);
    position: relative;
    overflow: hidden;
}

.admin-login-left::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse"><rect width="20" height="20" fill="none" stroke="rgba(0,0,0,0.1)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
    opacity: 0.3;
}

.admin-login-left-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 3rem;
    height: 100%;
    position: relative;
    z-index: 1;
}

.admin-logo-section {
    text-align: center;
    margin-bottom: 4rem;
}

.spark-logo {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: white;
    margin: 0 auto 1rem;
    animation: pulse 2s ease-in-out infinite;
    box-shadow: 0 0 40px rgba(255, 255, 255, 0.3);
}

.admin-brand {
    color: white;
    font-size: 3rem;
    font-weight: bold;
    margin: 0;
    text-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}

.admin-tagline {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.2rem;
    margin: 1rem 0 0;
}

.admin-features {
    max-width: 400px;
    width: 100%;
}

.feature-item {
    display: flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.feature-item:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateX(10px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
}

.feature-icon {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    margin-right: 1.5rem;
    flex-shrink: 0;
}

.feature-text h4 {
    color: white;
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 0.5rem;
}

.feature-text p {
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
    font-size: 0.9rem;
}

.admin-login-right {
    background: var(--secondary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.login-form-container {
    max-width: 450px;
    width: 100%;
}

.login-form-header {
    text-align: center;
    margin-bottom: 3rem;
}

.form-title {
    color: var(--text-primary);
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.form-subtitle {
    color: var(--text-secondary);
    margin: 0;
}

.admin-login-form {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
}

.form-group {
    position: relative;
}

.form-label {
    color: var(--text-primary);
    font-weight: 600;
    margin-bottom: 0.75rem;
    display: block;
}

.form-control {
    background: var(--primary-color);
    border: 2px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 10px;
    padding: 1rem 1.25rem;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 30px rgba(0, 255, 136, 0.3);
    background: var(--primary-color);
}

.password-input-group {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-secondary);
    z-index: 10;
    transition: all 0.3s ease;
}

.password-toggle:hover {
    color: var(--accent-color);
}

.form-check {
    background: rgba(0, 255, 136, 0.1);
    border: 1px solid rgba(0, 255, 136, 0.2);
    border-radius: 8px;
    padding: 1rem;
    transition: all 0.3s ease;
}

.form-check:hover {
    background: rgba(0, 255, 136, 0.2);
    border-color: var(--accent-color);
}

.form-check-input:checked ~ .form-check-label {
    color: var(--accent-color);
}

.admin-login-btn {
    width: 100%;
    padding: 1.25rem;
    background: linear-gradient(45deg, var(--accent-color), var(--neon-blue));
    color: var(--primary-color);
    border: none;
    border-radius: 10px;
    font-size: 1.1rem;
    font-weight: 600;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.admin-login-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.6s ease;
}

.admin-login-btn:hover::before {
    left: 100%;
}

.admin-login-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 30px rgba(0, 255, 136, 0.4);
}

.login-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border-color);
}

.forgot-password a,
.back-to-student a {
    color: var(--text-secondary);
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.forgot-password a:hover,
.back-to-student a:hover {
    color: var(--accent-color);
}

.security-notice {
    background: rgba(255, 152, 0, 0.1);
    border: 1px solid rgba(255, 152, 0, 0.2);
    border-radius: 10px;
    padding: 1rem;
    margin-top: 2rem;
    text-align: center;
}

.security-notice i {
    color: var(--warning-color);
    margin-right: 0.5rem;
}

.security-notice small {
    color: var(--text-secondary);
}

/* Background Animation */
.login-bg-animation {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 0;
}

.floating-element {
    position: absolute;
    background: rgba(0, 255, 136, 0.1);
    border: 2px solid rgba(0, 255, 136, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-color);
    font-size: 1.5rem;
    backdrop-filter: blur(10px);
}

.float-1 {
    width: 80px;
    height: 80px;
    top: 10%;
    left: 10%;
    animation: float 6s ease-in-out infinite;
}

.float-2 {
    width: 60px;
    height: 60px;
    top: 20%;
    right: 15%;
    animation: float 8s ease-in-out infinite 1s;
}

.float-3 {
    width: 100px;
    height: 100px;
    bottom: 15%;
    left: 20%;
    animation: float 10s ease-in-out infinite 2s;
}

.float-4 {
    width: 70px;
    height: 70px;
    bottom: 25%;
    right: 10%;
    animation: float 7s ease-in-out infinite 1.5s;
}

/* Loading State */
#loginBtn.loading {
    pointer-events: none;
    opacity: 0.8;
}

#loginBtn.loading .btn-text {
    display: none;
}

#loginBtn.loading .btn-loading {
    display: inline;
}

/* Responsive Design */
@media (max-width: 992px) {
    .admin-login-left {
        display: none !important;
    }

    .admin-login-right {
        min-height: 100vh;
    }
}

@media (max-width: 768px) {
    .admin-login-form {
        padding: 2rem 1.5rem;
        margin: 1rem;
    }

    .login-footer {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }

    .floating-element {
        display: none;
    }
}

/* Form Validation */
.was-validated .form-control:invalid {
    border-color: var(--error-color);
}

.was-validated .form-control:invalid:focus {
    box-shadow: 0 0 30px rgba(255, 71, 87, 0.3);
}

.was-validated .form-control:valid {
    border-color: var(--success-color);
}

/* Additional Animations */
@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
}

@keyframes float {
    0%, 100% {
        transform: translateY(0px) rotate(0deg);
    }
    33% {
        transform: translateY(-20px) rotate(5deg);
    }
    66% {
        transform: translateY(-10px) rotate(-5deg);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password toggle
    window.togglePassword = function(fieldId) {
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
    };

    // Form validation
    document.getElementById('adminLoginForm').addEventListener('submit', function(e) {
        const form = this;
        const submitBtn = document.getElementById('loginBtn');

        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        } else {
            // Show loading state
            submitBtn.classList.add('loading');
            form.classList.add('was-validated');
        }
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

    // Input focus effects
    const formControls = document.querySelectorAll('.form-control');
    formControls.forEach(control => {
        control.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
        });

        control.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
    });

    // Form field animations
    const formGroups = document.querySelectorAll('.form-group');
    formGroups.forEach((group, index) => {
        group.style.animationDelay = `${index * 0.1}s`;
        group.style.animation = 'slideInUp 0.6s ease-out both';
    });

    // Add CSS animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    `;
    document.head.appendChild(style);

    // Security: Check for multiple login attempts
    let loginAttempts = parseInt(localStorage.getItem('adminLoginAttempts') || '0');
    const lastAttemptTime = parseInt(localStorage.getItem('lastAdminAttemptTime') || '0');
    const now = Date.now();

    // Lockout after 5 failed attempts for 15 minutes
    if (loginAttempts >= 5 && (now - lastAttemptTime) < 900000) {
        document.getElementById('loginBtn').disabled = true;
        const lockoutTime = Math.ceil((900000 - (now - lastAttemptTime)) / 60000);
        document.querySelector('.form-subtitle').innerHTML =
            `<span style="color: var(--error-color)">Too many failed attempts. Please try again in ${lockoutTime} minutes.</span>`;
    }

    // Track login attempts
    document.getElementById('adminLoginForm').addEventListener('submit', function() {
        if (this.classList.contains('was-validated')) {
            loginAttempts++;
            localStorage.setItem('adminLoginAttempts', loginAttempts.toString());
            localStorage.setItem('lastAdminAttemptTime', now.toString());
        }
    });

    // Clear form data from session
    <?php if (isset($_SESSION['form_data'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        <?php unset($_SESSION['form_data']); ?>
    });
    <?php endif; ?>
});

// Reset login attempts on successful login
window.addEventListener('beforeunload', function() {
    // Only clear if we're not on the login page (successful login)
    if (!window.location.pathname.includes('/admin/login.php')) {
        localStorage.removeItem('adminLoginAttempts');
        localStorage.removeItem('lastAdminAttemptTime');
    }
});
</script>