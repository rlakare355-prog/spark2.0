<?php
// SPARK Platform - Email Verification
if (!function_exists('generateToken')) {
    require_once __DIR__ . '/../includes/config.php';
}
if (!function_exists('getPDO')) {
    require_once __DIR__ . '/../includes/database.php';
}
if (!function_exists('verifyEmail')) {
    require_once __DIR__ . '/../includes/auth.php';
}

$message = '';
$error = '';

// Handle verification
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    try {
        $token = sanitize($_GET['token']);

        if (empty($token)) {
            throw new Exception("Invalid verification token");
        }

        // Verify email
        $student = verifyEmail($token);

        // Log activity
        logActivity($student['id'], 'verify', 'system', null, 'Email verified successfully');

        // Set success message
        $_SESSION['success'][] = "Email verified successfully! Your account is now active.";

        // Redirect to login page
        header('Location: login.php');
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$page_title = 'Email Verification';
$page_subtitle = 'Verify Your Email Address';
$breadcrumb = [
    ['name' => 'Home', 'link' => SITE_URL . '/student/', 'active' => false],
    ['name' => 'Email Verification', 'link' => '', 'active' => true]
];

include __DIR__ . '/../templates/header.php';
?>

<!-- Verification Section -->
<section class="verification-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card verification-card" data-aos="fade-up">
                    <div class="card-header">
                        <h3 class="card-title text-center mb-0">
                            <i class="fas fa-envelope-check me-2"></i> Email Verification
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center">
                                <div class="verification-icon mb-4">
                                    <i class="fas fa-envelope-open-text"></i>
                                </div>
                                <h4 class="mb-3">Check Your Email</h4>
                                <p class="text-muted mb-4">
                                    We've sent a verification link to your registered email address. Please click the link in that email to verify your account.
                                </p>

                                <div class="verification-steps mb-4">
                                    <div class="step">
                                        <div class="step-number">1</div>
                                        <div class="step-text">Check your email inbox</div>
                                    </div>
                                    <div class="step">
                                        <div class="step-number">2</div>
                                        <div class="step-text">Find the verification email from SPARK</div>
                                    </div>
                                    <div class="step">
                                        <div class="step-number">3</div>
                                        <div class="step-text">Click the verification link</div>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle me-2"></i>Important Notes:</h6>
                                    <ul class="mb-0">
                                        <li>Check your spam/junk folder if you don't see the email</li>
                                        <li>The verification link expires in 24 hours</li>
                                        <li>Make sure to use the correct email address</li>
                                    </ul>
                                </div>

                                <div class="resend-section">
                                    <p class="text-muted mb-3">Didn't receive the email?</p>
                                    <button class="btn btn-outline-primary" onclick="resendVerification()">
                                        <i class="fas fa-redo me-2"></i>Resend Verification Email
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Login Link -->
<section class="login-link-section py-4">
    <div class="container">
        <div class="text-center">
            <p class="text-muted mb-2">Already verified?</p>
            <a href="<?php echo SITE_URL; ?>/student/login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt me-2"></i> Login to Your Account
            </a>
        </div>
    </div>
</section>

<style>
/* Verification Page Styles */
.verification-section {
    min-height: 100vh;
    display: flex;
    align-items: center;
}

.verification-card {
    border: 1px solid var(--border-color);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(10px);
}

.verification-icon {
    font-size: 4rem;
    color: var(--accent-color);
    margin-bottom: 1.5rem;
}

.verification-steps {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    margin: 2rem 0;
}

.step {
    text-align: center;
    flex: 1;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--accent-color);
    color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin: 0 auto 1rem;
}

.step-text {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.resend-section {
    padding: 2rem 0;
    border-top: 1px solid var(--border-color);
    margin-top: 2rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .verification-steps {
        flex-direction: column;
        gap: 2rem;
    }

    .verification-card {
        margin: 1rem;
    }
}

/* Animation */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.verification-icon {
    animation: pulse 2s infinite;
}
</style>

<script>
// Resend verification email function
function resendVerification() {
    // This would typically make an AJAX call to resend the email
    // For now, we'll show a message
    const btn = event.target;
    const originalText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';

    // Simulate API call
    setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        showNotification('Verification email has been resent!', 'success');
    }, 2000);
}

// Auto-check if token exists in URL (for manual entry)
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');

    if (token && document.querySelector('.text-center')) {
        // Show that we're processing the verification
        const cardBody = document.querySelector('.card-body');
        cardBody.innerHTML = `
            <div class="text-center">
                <div class="verification-processing mb-4">
                    <i class="fas fa-spinner fa-spin fa-3x text-primary"></i>
                </div>
                <h4>Verifying Your Email...</h4>
                <p class="text-muted">Please wait while we verify your email address.</p>
            </div>
        `;

        // Redirect to process verification
        setTimeout(() => {
            window.location.href = 'verify.php?token=' + encodeURIComponent(token);
        }, 2000);
    }
});

// Form field animations
document.addEventListener('DOMContentLoaded', function() {
    // Add subtle animations to the verification steps
    const steps = document.querySelectorAll('.step');
    steps.forEach((step, index) => {
        setTimeout(() => {
            step.style.opacity = '0';
            step.style.transform = 'translateY(20px)';

            setTimeout(() => {
                step.style.transition = 'all 0.5s ease';
                step.style.opacity = '1';
                step.style.transform = 'translateY(0)';
            }, 100);
        }, index * 200);
    });
});

// Enhanced button interactions
document.querySelectorAll('button').forEach(button => {
    button.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px)';
        this.style.boxShadow = '0 5px 15px rgba(0, 255, 136, 0.3)';
    });

    button.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = '0 2px 10px rgba(0, 255, 136, 0.2)';
    });
});

// Auto-focus prevention for mobile
document.addEventListener('DOMContentLoaded', function() {
    if (/iPhone|iPad|iPod|Android/i.test(navigator.userAgent)) {
        document.querySelector('meta[name="viewport"]').content = 'width=device-width, initial-scale=1, maximum-scale=1';
    }
});
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>