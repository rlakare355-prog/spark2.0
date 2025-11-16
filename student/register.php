<?php
// SPARK Platform - Student Registration
if (!function_exists('generateToken')) {
    require_once __DIR__ . '/../includes/config.php';
}
if (!function_exists('getPDO')) {
    require_once __DIR__ . '/../includes/database.php';
}
if (!function_exists('registerStudent')) {
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

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate and sanitize input
        $data = [
            'first_name' => sanitize($_POST['first_name']),
            'middle_name' => sanitize($_POST['middle_name'] ?? ''),
            'last_name' => sanitize($_POST['last_name']),
            'prn' => sanitize($_POST['prn']),
            'email' => sanitize($_POST['email']),
            'contact_no' => sanitize($_POST['contact_no']),
            'department' => sanitize($_POST['department']),
            'year' => sanitize($_POST['year']),
            'password' => $_POST['password'],
            'confirm_password' => $_POST['confirm_password']
        ];

        // Validate required fields
        $required = ['first_name', 'last_name', 'prn', 'email', 'contact_no', 'department', 'year', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("All required fields must be filled");
            }
        }

        // Validate email
        if (!isValidEmail($data['email'])) {
            throw new Exception("Please enter a valid email address");
        }

        // Validate password match
        if ($data['password'] !== $data['confirm_password']) {
            throw new Exception("Passwords do not match");
        }

        // Validate password strength
        if (strlen($data['password']) < 8) {
            throw new Exception("Password must be at least 8 characters long");
        }

        // Validate PRN format (basic validation)
        if (!preg_match('/^[A-Z0-9]{10,20}$/', $data['prn'])) {
            throw new Exception("Please enter a valid PRN number");
        }

        // Validate contact number
        if (!preg_match('/^[0-9]{10}$/', $data['contact_no'])) {
            throw new Exception("Please enter a valid 10-digit contact number");
        }

        // Register student
        $studentId = registerStudent($data);

        // Set success message
        $_SESSION['success'][] = "Registration successful! You can now login to your account.";

        // Redirect to login page
        header('Location: login.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        $_SESSION['form_data'] = $_POST;
    }
}

$page_title = 'Student Registration';
$page_subtitle = 'Join SPARK Community';
$breadcrumb = [
    ['name' => 'Home', 'link' => SITE_URL . '/student/', 'active' => false],
    ['name' => 'Registration', 'link' => '', 'active' => true]
];

include __DIR__ . '/../templates/header.php';
?>

<!-- Registration Section -->
<section class="registration-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card registration-card" data-aos="fade-up">
                    <div class="card-header">
                        <h3 class="card-title text-center mb-0">
                            <i class="fas fa-user-plus me-2"></i> Student Registration
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php include __DIR__ . '/../includes/alerts.php'; ?>

                        <form id="registrationForm" class="needs-validation" method="POST" novalidate>
                            <!-- Personal Information -->
                            <div class="registration-section">
                                <h4 class="section-subtitle">
                                    <i class="fas fa-user me-2"></i> Personal Information
                                </h4>

                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="first_name" class="form-label">First Name *</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name"
                                               value="<?php echo htmlspecialchars($_SESSION['form_data']['first_name'] ?? ''); ?>"
                                               required maxlength="100">
                                        <div class="invalid-feedback">Please provide your first name</div>
                                    </div>

                                    <div class="col-md-4">
                                        <label for="middle_name" class="form-label">Middle Name</label>
                                        <input type="text" class="form-control" id="middle_name" name="middle_name"
                                               value="<?php echo htmlspecialchars($_SESSION['form_data']['middle_name'] ?? ''); ?>"
                                               maxlength="100">
                                    </div>

                                    <div class="col-md-4">
                                        <label for="last_name" class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name"
                                               value="<?php echo htmlspecialchars($_SESSION['form_data']['last_name'] ?? ''); ?>"
                                               required maxlength="100">
                                        <div class="invalid-feedback">Please provide your last name</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Academic Information -->
                            <div class="registration-section">
                                <h4 class="section-subtitle">
                                    <i class="fas fa-graduation-cap me-2"></i> Academic Information
                                </h4>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="prn" class="form-label">PRN Number *</label>
                                        <input type="text" class="form-control" id="prn" name="prn"
                                               value="<?php echo htmlspecialchars($_SESSION['form_data']['prn'] ?? ''); ?>"
                                               required maxlength="20" pattern="[A-Z0-9]{10,20}" title="PRN should be 10-20 alphanumeric characters">
                                        <div class="invalid-feedback">Please provide a valid PRN number</div>
                                        <small class="form-text text-muted">Format: Example: PRN2024CS001</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="contact_no" class="form-label">Contact Number *</label>
                                        <input type="tel" class="form-control" id="contact_no" name="contact_no"
                                               value="<?php echo htmlspecialchars($_SESSION['form_data']['contact_no'] ?? ''); ?>"
                                               required maxlength="10" pattern="[0-9]{10}" title="Please enter 10 digits">
                                        <div class="invalid-feedback">Please provide a valid 10-digit contact number</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="department" class="form-label">Department *</label>
                                        <select class="form-select" id="department" name="department" required>
                                            <option value="">Select Department</option>
                                            <option value="CSE" <?php echo (($_SESSION['form_data']['department'] ?? '') === 'CSE') ? 'selected' : ''; ?>>
                                                Computer Science Engineering (CSE)
                                            </option>
                                            <option value="CY" <?php echo (($_SESSION['form_data']['department'] ?? '') === 'CY') ? 'selected' : ''; ?>>
                                                Computer Engineering (CY)
                                            </option>
                                            <option value="AIML" <?php echo (($_SESSION['form_data']['department'] ?? '') === 'AIML') ? 'selected' : ''; ?>>
                                                Artificial Intelligence & Machine Learning (AIML)
                                            </option>
                                            <option value="ALDS" <?php echo (($_SESSION['form_data']['department'] ?? '') === 'ALDS') ? 'selected' : ''; ?>>
                                                Artificial Learning & Data Science (ALDS)
                                            </option>
                                            <option value="Integrated-B.tech" <?php echo (($_SESSION['form_data']['department'] ?? '') === 'Integrated-B.tech') ? 'selected' : ''; ?>>
                                                Integrated B.Tech
                                            </option>
                                        </select>
                                        <div class="invalid-feedback">Please select your department</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="year" class="form-label">Academic Year *</label>
                                        <select class="form-select" id="year" name="year" required>
                                            <option value="">Select Year</option>
                                            <option value="FY" <?php echo (($_SESSION['form_data']['year'] ?? '') === 'FY') ? 'selected' : ''; ?>>
                                                First Year (FY)
                                            </option>
                                            <option value="SY" <?php echo (($_SESSION['form_data']['year'] ?? '') === 'SY') ? 'selected' : ''; ?>>
                                                Second Year (SY)
                                            </option>
                                            <option value="TY" <?php echo (($_SESSION['form_data']['year'] ?? '') === 'TY') ? 'selected' : ''; ?>>
                                                Third Year (TY)
                                            </option>
                                            <option value="FINAL YEAR" <?php echo (($_SESSION['form_data']['year'] ?? '') === 'FINAL YEAR') ? 'selected' : ''; ?>>
                                                Final Year
                                            </option>
                                        </select>
                                        <div class="invalid-feedback">Please select your academic year</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact Information -->
                            <div class="registration-section">
                                <h4 class="section-subtitle">
                                    <i class="fas fa-envelope me-2"></i> Contact Information
                                </h4>

                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label for="email" class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" id="email" name="email"
                                               value="<?php echo htmlspecialchars($_SESSION['form_data']['email'] ?? ''); ?>"
                                               required maxlength="255">
                                        <div class="invalid-feedback">Please provide a valid email address</div>
                                        <small class="form-text text-muted">We'll send verification link to this email</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Password Information -->
                            <div class="registration-section">
                                <h4 class="section-subtitle">
                                    <i class="fas fa-lock me-2"></i> Password
                                </h4>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="password" class="form-label">Password *</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password" name="password"
                                                   required minlength="8" maxlength="255">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                                <i class="fas fa-eye" id="password-toggle"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback">Password must be at least 8 characters long</div>
                                        <small class="form-text text-muted">Use strong password with letters, numbers, and symbols</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                                   required minlength="8" maxlength="255">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                                <i class="fas fa-eye" id="confirm_password-toggle"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback">Passwords must match</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Terms and Conditions -->
                            <div class="registration-section">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I agree to <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>
                                        and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a> *
                                    </label>
                                    <div class="invalid-feedback">You must agree to the terms and conditions</div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg px-5" id="registerBtn">
                                    <i class="fas fa-user-plus me-2"></i> Create Account
                                </button>
                            </div>
                        </form>

                        <!-- Already have account -->
                        <div class="text-center mt-4">
                            <p class="text-muted">Already have an account?</p>
                            <a href="<?php echo SITE_URL; ?>/student/login.php" class="btn btn-outline-primary">
                                <i class="fas fa-sign-in-alt me-2"></i> Login Here
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Terms Modal -->
<div class="modal fade" id="termsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Terms and Conditions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>1. Acceptance of Terms</h6>
                <p>By registering with SPARK platform, you agree to these terms and conditions.</p>

                <h6>2. Use of Platform</h6>
                <p>You agree to use platform for educational and professional purposes only.</p>

                <h6>3. Privacy</h6>
                <p>Your personal information will be handled according to our privacy policy.</p>

                <h6>4. Account Security</h6>
                <p>You are responsible for maintaining the confidentiality of your account credentials.</p>

                <h6>5. Code of Conduct</h6>
                <p>You agree to maintain professional behavior and respect other users.</p>

                <h6>6. Intellectual Property</h6>
                <p>All content on the platform remains the property of SPARK Club.</p>

                <h6>7. Termination</h6>
                <p>SPARK reserves the right to terminate accounts that violate these terms.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
            </div>
        </div>
    </div>
</div>

<!-- Privacy Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Privacy Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>Information We Collect</h6>
                <p>We collect personal information including name, email, PRN, contact details, and academic information.</p>

                <h6>How We Use Information</h6>
                <p>Information is used for event registration, certificate generation, and platform management.</p>

                <h6>Data Security</h6>
                <p>We implement appropriate security measures to protect your personal information.</p>

                <h6>Third-Party Services</h6>
                <p>We use third-party services for payment processing and email communications.</p>

                <h6>Your Rights</h6>
                <p>You have the right to access, modify, or delete your personal information.</p>

                <h6>Cookies</h6>
                <p>We use cookies to enhance your experience on the platform.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>

<style>
/* Registration Page Styles */
.registration-section {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color);
}

.registration-section:last-child {
    border-bottom: none;
}

.section-subtitle {
    color: var(--accent-color);
    font-weight: 600;
    margin-bottom: 1.5rem;
    font-size: 1.1rem;
}

.registration-card {
    border: 1px solid var(--border-color);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.registration-card .card-header {
    background: var(--secondary-color);
    border-bottom: 1px solid var(--border-color);
    padding: 1.5rem;
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

.modal-content {
    background: var(--secondary-color);
    border: 1px solid var(--border-color);
}

.modal-header {
    border-bottom-color: var(--border-color);
}

.modal-footer {
    border-top-color: var(--border-color);
}

.modal h6 {
    color: var(--accent-color);
    font-weight: 600;
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
.strength-strong { width: 100%; background: #00ff88; }

/* Loading Animation */
#registerBtn.loading {
    pointer-events: none;
    opacity: 0.7;
}

#registerBtn.loading::after {
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
    .registration-card {
        margin: 1rem;
    }

    .btn-lg {
        padding: 12px 20px;
        font-size: 1rem;
    }
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
document.getElementById('password').addEventListener('input', function() {
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

// Form validation
document.getElementById('registrationForm').addEventListener('submit', function(e) {
    const form = this;
    const submitBtn = document.getElementById('registerBtn');

    if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    } else {
        // Check password match
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        if (password !== confirmPassword) {
            e.preventDefault();
            showNotification('Passwords do not match!', 'error');
            document.getElementById('confirm_password').focus();
            return;
        }

        // Check PRN format
        const prn = document.getElementById('prn').value;
        if (!/^[A-Z0-9]{10,20}$/.test(prn)) {
            e.preventDefault();
            showNotification('PRN should be 10-20 alphanumeric characters in uppercase!', 'error');
            document.getElementById('prn').focus();
            return;
        }

        // Show loading state
        submitBtn.classList.add('loading');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Creating Account...';
    }

    form.classList.add('was-validated');
});

// PRN formatting
document.getElementById('prn').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});

// Contact number formatting
document.getElementById('contact_no').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 10);
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
document.querySelectorAll('.form-control, .form-select').forEach(field => {
    field.addEventListener('focus', function() {
        this.parentElement.style.transform = 'scale(1.02)';
    });

    field.addEventListener('blur', function() {
        this.parentElement.style.transform = 'scale(1)';
    });
});
</script>

<?php
// Clear form data from session
unset($_SESSION['form_data']);
?>