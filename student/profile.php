<?php
// SPARK Platform - Student Profile
require_once __DIR__ . '/../includes/student_header.php';

// Get current user
$user = getCurrentUser();
$userId = $user['id'];

// Handle profile update
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'first_name' => sanitize($_POST['first_name']),
            'last_name' => sanitize($_POST['last_name']),
            'middle_name' => sanitize($_POST['middle_name'] ?? ''),
            'contact_no' => sanitize($_POST['contact_no']),
            'profile_image' => null
        ];

        // Validate required fields
        $required = ['first_name', 'last_name', 'contact_no'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }

        // Validate contact number
        if (!preg_match('/^[0-9]{10}$/', $data['contact_no'])) {
            throw new Exception("Please enter a valid 10-digit contact number");
        }

        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5242880; // 5MB

            if (!in_array($_FILES['profile_image']['type'], $allowedTypes)) {
                throw new Exception("Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed");
            }

            if ($_FILES['profile_image']['size'] > $maxSize) {
                throw new Exception("File size too large. Maximum size is 5MB");
            }

            // Generate unique filename
            $fileExtension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . time() . '_' . $userId . '.' . $fileExtension;
            $uploadPath = UPLOAD_PATH . 'profiles/' . $filename;

            // Create directory if not exists
            if (!file_exists(UPLOAD_PATH . 'profiles/')) {
                mkdir(UPLOAD_PATH . 'profiles/', 0755, true);
            }

            // Upload file
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath)) {
                $data['profile_image'] = $filename;

                // Delete old profile image if exists
                $oldImage = $user['profile_image'];
                if ($oldImage && file_exists(UPLOAD_PATH . 'profiles/' . $oldImage)) {
                    unlink(UPLOAD_PATH . 'profiles/' . $oldImage);
                }
            }
        }

        // Update user profile
        dbUpdate('students', $data, 'id = ?', [$userId]);

        // Log activity
        logActivity($_SESSION['user_id'], 'update', 'profile', null, 'Profile updated');

        $message = "Profile updated successfully!";

        // Refresh user data
        $user = getCurrentUser();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    try {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password_profile'];
        $confirmPassword = $_POST['confirm_new_password'];

        // Validate inputs
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            throw new Exception("All password fields are required");
        }

        if ($newPassword !== $confirmPassword) {
            throw new Exception("New passwords do not match");
        }

        if (strlen($newPassword) < 8) {
            throw new Exception("New password must be at least 8 characters long");
        }

        // Change password
        changePassword($userId, $currentPassword, $newPassword);

        // Log activity
        logActivity($_SESSION['user_id'], 'update', 'profile', null, 'Password changed');

        $message = "Password changed successfully!";

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$page_title = 'My Profile';
$page_subtitle = 'Manage Your Account Information';
$breadcrumb = [
    ['name' => 'Dashboard', 'link' => 'dashboard.php', 'active' => false],
    ['name' => 'Profile', 'link' => '', 'active' => true]
];
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="fas fa-user-circle me-2"></i>
                    My Profile
                </h2>
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

    <div class="row">
        <!-- Profile Information -->
        <div class="col-lg-8 mb-4">
            <div class="card admin-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user me-2"></i>
                        Profile Information
                    </h5>
                </div>
                <div class="card-body">
                    <form id="profileForm" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name"
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>"
                                       required maxlength="100">
                                <div class="invalid-feedback">Please provide your first name</div>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name"
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>"
                                       required maxlength="100">
                                <div class="invalid-feedback">Please provide your last name</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name"
                                       value="<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>"
                                       maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label for="contact_no" class="form-label">Contact Number</label>
                                <input type="tel" class="form-control" id="contact_no" name="contact_no"
                                       value="<?php echo htmlspecialchars($user['contact_no']); ?>"
                                       required maxlength="10" pattern="[0-9]{10}">
                                <div class="invalid-feedback">Please provide a valid 10-digit contact number</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label text-muted">PRN</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['prn']); ?>" disabled>
                                <small class="form-text">PRN cannot be changed</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                <small class="form-text">Email cannot be changed</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted">Department</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['department']); ?>" disabled>
                                <small class="form-text">Department cannot be changed</small>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="profile_image" class="form-label">Profile Picture</label>
                            <div class="profile-upload-container">
                                <div class="current-profile">
                                    <?php if ($user['profile_image']): ?>
                                        <img src="<?php echo SITE_URL; ?>/assets/images/profiles/<?php echo htmlspecialchars($user['profile_image']); ?>"
                                             alt="Profile" class="profile-image-large">
                                    <?php else: ?>
                                        <div class="profile-placeholder">
                                            <i class="fas fa-user-circle fa-4x"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="upload-controls">
                                    <input type="file" class="form-control" id="profile_image" name="profile_image"
                                           accept="image/jpeg,image/png,image/gif,image/webp">
                                    <div class="form-text text-muted">
                                        Allowed: JPG, PNG, GIF, WEBP (Max: 5MB)
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>
                                Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Password Change -->
        <div class="col-lg-4 mb-4">
            <div class="card admin-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-lock me-2"></i>
                        Change Password
                    </h5>
                </div>
                <div class="card-body">
                    <form id="passwordForm" method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-key"></i>
                                </span>
                                <input type="password" class="form-control" id="current_password" name="current_password"
                                       required minlength="8" maxlength="255">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                    <i class="fas fa-eye" id="current_password-toggle"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Please enter your current password</div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password_profile" class="form-label">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="new_password_profile" name="new_password_profile"
                                       required minlength="8" maxlength="255">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password_profile')">
                                    <i class="fas fa-eye" id="new_password_profile-toggle"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Password must be at least 8 characters</div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_new_password" class="form-label">Confirm New Password</label>
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
                            <button type="submit" name="change_password" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>
                                Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Account Statistics -->
        <div class="col-12 mb-4">
            <div class="card admin-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        Account Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3 mb-3">
                            <div class="stat-box">
                                <div class="stat-number"><?php
                                    echo dbCount('event_registrations', 'student_id = ?', [$userId]);
                                ?></div>
                                <div class="stat-label">Events Registered</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-box">
                                <div class="stat-number"><?php
                                    echo dbCount('attendance', 'student_id = ? AND status = ?', [$userId, 'present']);
                                ?></div>
                                <div class="stat-label">Events Attended</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-box">
                                <div class="stat-number"><?php
                                    echo dbCount('certificates', 'student_id = ?', [$userId]);
                                ?></div>
                                <div class="stat-label">Certificates Earned</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-box">
                                <div class="stat-number"><?php
                                    echo dbCount('project_members', 'student_id = ? AND status = ?', [$userId, 'accepted']);
                                ?></div>
                                <div class="stat-label">Projects Joined</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Profile Page Styles */
.profile-upload-container {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.current-profile {
    flex-shrink: 0;
}

.profile-image-large {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--accent-color);
}

.profile-placeholder {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: var(--card-bg);
    border: 3px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
}

.upload-controls {
    flex: 1;
}

.stat-box {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    padding: 1.5rem;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.stat-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 255, 136, 0.3);
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: var(--accent-color);
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 1px;
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

/* Responsive Design */
@media (max-width: 768px) {
    .profile-upload-container {
        flex-direction: column;
        gap: 1rem;
    }

    .stat-box {
        margin-bottom: 1rem;
    }
}

/* Form Validation */
.was-validated .form-control:invalid {
    border-color: var(--error-color);
}

.was-validated .form-control:valid {
    border-color: var(--success-color);
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
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const form = this;
    const submitBtn = form.querySelector('button[type="submit"]');

    if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    } else {
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating Profile...';
    }

    form.classList.add('was-validated');
});

document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const form = this;
    const submitBtn = form.querySelector('button[type="submit"]');
    const currentPassword = document.getElementById('current_password').value;
    const newPassword = document.getElementById('new_password_profile').value;
    const confirmPassword = document.getElementById('confirm_new_password').value;

    if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    } else if (newPassword !== confirmPassword) {
        e.preventDefault();
        e.stopPropagation();
        showNotification('New passwords do not match!', 'error');
        document.getElementById('confirm_new_password').focus();
    } else {
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Changing Password...';
    }

    form.classList.add('was-validated');
});

// Profile image preview
document.getElementById('profile_image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const currentProfile = document.querySelector('.current-profile');
            const image = document.createElement('img');
            image.src = e.target.result;
            image.className = 'profile-image-large';
            currentProfile.innerHTML = '';
            currentProfile.appendChild(image);
        };
        reader.readAsDataURL(file);
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

<?php include __DIR__ . '/../includes/student_footer.php'; ?>