<?php
// SPARK Platform - Admin Team Management
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireAdmin();

// Handle team operations
$action = $_GET['action'] ?? 'list';

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = sanitize($_POST['name']);
        $role = sanitize($_POST['role']);
        $position = sanitize($_POST['position']);
        $department = sanitize($_POST['department']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $bio = sanitize($_POST['bio']);
        $order_index = (int)($_POST['order_index'] ?? 0);
        $status = sanitize($_POST['status'] ?? 'active');
        $featured = isset($_POST['featured']) ? 1 : 0;

        // Social media links
        $linkedin = sanitize($_POST['linkedin'] ?? '');
        $github = sanitize($_POST['github'] ?? '');
        $twitter = sanitize($_POST['twitter'] ?? '');
        $instagram = sanitize($_POST['instagram'] ?? '');
        $website = sanitize($_POST['website'] ?? '');

        // Validation
        if (empty($name) || empty($role) || empty($position)) {
            throw new Exception("Name, role, and position are required");
        }

        if (!empty($email) && !isValidEmail($email)) {
            throw new Exception("Please provide a valid email address");
        }

        // Handle photo upload
        $photo_url = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $photo_url = uploadFile($_FILES['photo'], 'team');
        }

        // Insert team member
        $sql = "INSERT INTO team_members (
            name, role, position, department, email, phone, bio, order_index, status, featured,
            photo_url, linkedin, github, twitter, instagram, website, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $params = [
            $name, $role, $position, $department, $email, $phone, $bio, $order_index, $status, $featured,
            $photo_url, $linkedin, $github, $twitter, $instagram, $website
        ];

        $member_id = executeInsert($sql, $params);

        // Log activity
        logActivity('team_member_created', "Team member '{$name}' added", $_SESSION['user_id'], $member_id);

        $_SESSION['success'][] = "Team member added successfully!";
        header('Location: team.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        $_SESSION['form_data'] = $_POST;
        header('Location: team.php?action=create');
        exit();
    }
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $member_id = (int)$_POST['member_id'];
        $name = sanitize($_POST['name']);
        $role = sanitize($_POST['role']);
        $position = sanitize($_POST['position']);
        $department = sanitize($_POST['department']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $bio = sanitize($_POST['bio']);
        $order_index = (int)($_POST['order_index'] ?? 0);
        $status = sanitize($_POST['status'] ?? 'active');
        $featured = isset($_POST['featured']) ? 1 : 0;

        // Social media links
        $linkedin = sanitize($_POST['linkedin'] ?? '');
        $github = sanitize($_POST['github'] ?? '');
        $twitter = sanitize($_POST['twitter'] ?? '');
        $instagram = sanitize($_POST['instagram'] ?? '');
        $website = sanitize($_POST['website'] ?? '');

        // Validation
        if (empty($name) || empty($role) || empty($position)) {
            throw new Exception("Name, role, and position are required");
        }

        if (!empty($email) && !isValidEmail($email)) {
            throw new Exception("Please provide a valid email address");
        }

        // Handle photo upload
        $photo_url = $_POST['existing_photo'] ?? '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $photo_url = uploadFile($_FILES['photo'], 'team');
        }

        // Update team member
        $sql = "UPDATE team_members SET
            name = ?, role = ?, position = ?, department = ?, email = ?, phone = ?, bio = ?,
            order_index = ?, status = ?, featured = ?, photo_url = ?,
            linkedin = ?, github = ?, twitter = ?, instagram = ?, website = ?, updated_at = NOW()
        WHERE id = ?";

        $params = [
            $name, $role, $position, $department, $email, $phone, $bio, $order_index, $status, $featured, $photo_url,
            $linkedin, $github, $twitter, $instagram, $website, $member_id
        ];

        executeUpdate($sql, $params);

        // Log activity
        logActivity('team_member_updated', "Team member '{$name}' updated", $_SESSION['user_id'], $member_id);

        $_SESSION['success'][] = "Team member updated successfully!";
        header('Location: team.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: team.php?action=edit&id=' . $_POST['member_id']);
        exit();
    }
}

if ($action === 'delete') {
    try {
        $member_id = (int)$_GET['id'];

        // Get member info for logging
        $member = fetchRow("SELECT name FROM team_members WHERE id = ?", [$member_id]);
        if (!$member) {
            throw new Exception("Team member not found");
        }

        // Delete team member
        executeUpdate("DELETE FROM team_members WHERE id = ?", [$member_id]);

        // Log activity
        logActivity('team_member_deleted', "Team member '{$member['name']}' deleted", $_SESSION['user_id'], $member_id);

        $_SESSION['success'][] = "Team member deleted successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: team.php');
    exit();
}

if ($action === 'toggle_featured') {
    try {
        $member_id = (int)$_GET['id'];
        $featured = (int)$_GET['featured'];

        executeUpdate("UPDATE team_members SET featured = ? WHERE id = ?", [$featured, $member_id]);

        $action_text = $featured ? 'featured' : 'unfeatured';
        $_SESSION['success'][] = "Team member {$action_text} successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: team.php');
    exit();
}

if ($action === 'reorder') {
    try {
        $order_data = json_decode($_POST['order_data'], true);

        foreach ($order_data as $index => $member_id) {
            executeUpdate("UPDATE team_members SET order_index = ? WHERE id = ?", [$index + 1, (int)$member_id]);
        }

        $_SESSION['success'][] = "Team members reordered successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: team.php');
    exit();
}

$page_title = 'Team Management';
$breadcrumb = [
    ['name' => 'Dashboard', 'link' => 'index.php', 'active' => false],
    ['name' => 'Team', 'link' => '', 'active' => true]
];

include __DIR__ . '/../templates/admin_header.php';
?>

<!-- Team Management Section -->
<section class="admin-section py-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="section-title">
                            <i class="fas fa-users me-2"></i> Team Management
                        </h2>
                        <p class="text-muted">Manage team members, faculty coordinators, and student leaders</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-info" onclick="reorderMembers()">
                            <i class="fas fa-sort me-2"></i> Reorder
                        </button>
                        <a href="team.php?action=create" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Add Member
                        </a>
                    </div>
                </div>

                <?php include __DIR__ . '/../includes/alerts.php'; ?>

                <?php if ($action === 'create' || $action === 'edit'): ?>
                    <!-- Create/Edit Team Member Form -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-<?php echo $action === 'create' ? 'user-plus' : 'user-edit'; ?> me-2"></i>
                                <?php echo $action === 'create' ? 'Add Team Member' : 'Edit Team Member'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $member_data = $_SESSION['form_data'] ?? [];
                            if ($action === 'edit') {
                                $member_id = (int)$_GET['id'];
                                $member_data = fetchRow("SELECT * FROM team_members WHERE id = ?", [$member_id]);
                                if (!$member_data) {
                                    $_SESSION['errors'][] = "Team member not found";
                                    header('Location: team.php');
                                    exit();
                                }
                            }
                            ?>

                            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="member_id" value="<?php echo $member_data['id']; ?>">
                                <?php endif; ?>

                                <div class="row">
                                    <!-- Basic Information -->
                                    <div class="col-md-8">
                                        <h6 class="text-primary mb-3">Basic Information</h6>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="name" class="form-label">Full Name *</label>
                                                    <input type="text" class="form-control" id="name" name="name"
                                                           value="<?php echo htmlspecialchars($member_data['name'] ?? ''); ?>"
                                                           required maxlength="255">
                                                    <div class="invalid-feedback">Please provide the full name</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="role" class="form-label">Role *</label>
                                                    <select class="form-select" id="role" name="role" required>
                                                        <option value="">Select Role</option>
                                                        <option value="faculty" <?php echo ($member_data['role'] ?? '') === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                                                        <option value="staff" <?php echo ($member_data['role'] ?? '') === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                                        <option value="student" <?php echo ($member_data['role'] ?? '') === 'student' ? 'selected' : ''; ?>>Student Leader</option>
                                                        <option value="alumni" <?php echo ($member_data['role'] ?? '') === 'alumni' ? 'selected' : ''; ?>>Alumni</option>
                                                        <option value="industry" <?php echo ($member_data['role'] ?? '') === 'industry' ? 'selected' : ''; ?>>Industry Expert</option>
                                                    </select>
                                                    <div class="invalid-feedback">Please select a role</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="position" class="form-label">Position/Title *</label>
                                                    <input type="text" class="form-control" id="position" name="position"
                                                           value="<?php echo htmlspecialchars($member_data['position'] ?? ''); ?>"
                                                           required maxlength="255" placeholder="e.g., Professor, Developer, Coordinator">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="department" class="form-label">Department</label>
                                                    <select class="form-select" id="department" name="department">
                                                        <option value="">Select Department</option>
                                                        <option value="computer" <?php echo ($member_data['department'] ?? '') === 'computer' ? 'selected' : ''; ?>>Computer Engineering</option>
                                                        <option value="it" <?php echo ($member_data['department'] ?? '') === 'it' ? 'selected' : ''; ?>>Information Technology</option>
                                                        <option value="electronics" <?php echo ($member_data['department'] ?? '') === 'electronics' ? 'selected' : ''; ?>>Electronics</option>
                                                        <option value="mechanical" <?php echo ($member_data['department'] ?? '') === 'mechanical' ? 'selected' : ''; ?>>Mechanical</option>
                                                        <option value="civil" <?php echo ($member_data['department'] ?? '') === 'civil' ? 'selected' : ''; ?>>Civil</option>
                                                        <option value="management" <?php echo ($member_data['department'] ?? '') === 'management' ? 'selected' : ''; ?>>Management</option>
                                                        <option value="other" <?php echo ($member_data['department'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="email" class="form-label">Email</label>
                                                    <input type="email" class="form-control" id="email" name="email"
                                                           value="<?php echo htmlspecialchars($member_data['email'] ?? ''); ?>"
                                                           maxlength="255">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="phone" class="form-label">Phone</label>
                                                    <input type="tel" class="form-control" id="phone" name="phone"
                                                           value="<?php echo htmlspecialchars($member_data['phone'] ?? ''); ?>"
                                                           maxlength="20" placeholder="+91 98765 43210">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="bio" class="form-label">Biography</label>
                                            <textarea class="form-control" id="bio" name="bio" rows="4"
                                                      placeholder="Brief introduction and background..."><?php echo htmlspecialchars($member_data['bio'] ?? ''); ?></textarea>
                                        </div>

                                        <!-- Social Media Links -->
                                        <h6 class="text-primary mb-3 mt-4">Social Media Links</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="linkedin" class="form-label">
                                                        <i class="fab fa-linkedin me-1"></i> LinkedIn
                                                    </label>
                                                    <input type="url" class="form-control" id="linkedin" name="linkedin"
                                                           value="<?php echo htmlspecialchars($member_data['linkedin'] ?? ''); ?>"
                                                           placeholder="https://linkedin.com/in/username">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="github" class="form-label">
                                                        <i class="fab fa-github me-1"></i> GitHub
                                                    </label>
                                                    <input type="url" class="form-control" id="github" name="github"
                                                           value="<?php echo htmlspecialchars($member_data['github'] ?? ''); ?>"
                                                           placeholder="https://github.com/username">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="twitter" class="form-label">
                                                        <i class="fab fa-twitter me-1"></i> Twitter
                                                    </label>
                                                    <input type="url" class="form-control" id="twitter" name="twitter"
                                                           value="<?php echo htmlspecialchars($member_data['twitter'] ?? ''); ?>"
                                                           placeholder="https://twitter.com/username">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="instagram" class="form-label">
                                                        <i class="fab fa-instagram me-1"></i> Instagram
                                                    </label>
                                                    <input type="url" class="form-control" id="instagram" name="instagram"
                                                           value="<?php echo htmlspecialchars($member_data['instagram'] ?? ''); ?>"
                                                           placeholder="https://instagram.com/username">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="website" class="form-label">
                                                <i class="fas fa-globe me-1"></i> Personal Website
                                            </label>
                                            <input type="url" class="form-control" id="website" name="website"
                                                   value="<?php echo htmlspecialchars($member_data['website'] ?? ''); ?>"
                                                   placeholder="https://personalwebsite.com">
                                        </div>
                                    </div>

                                    <!-- Photo and Settings -->
                                    <div class="col-md-4">
                                        <h6 class="text-primary mb-3">Photo & Settings</h6>

                                        <div class="mb-3">
                                            <label for="photo" class="form-label">Photo</label>
                                            <input type="file" class="form-control" id="photo" name="photo"
                                                   accept="image/*" onchange="previewPhoto(event)">
                                            <?php if (!empty($member_data['photo_url'])): ?>
                                                <input type="hidden" name="existing_photo" value="<?php echo htmlspecialchars($member_data['photo_url']); ?>">
                                                <div class="mt-2">
                                                    <small class="text-muted">Current photo:</small><br>
                                                    <img src="<?php echo SITE_URL . '/' . $member_data['photo_url']; ?>"
                                                         alt="Current photo" style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px;">
                                                </div>
                                            <?php endif; ?>
                                            <div id="photoPreview" class="mt-2"></div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="order_index" class="form-label">Display Order</label>
                                            <input type="number" class="form-control" id="order_index" name="order_index"
                                                   value="<?php echo htmlspecialchars($member_data['order_index'] ?? 0); ?>"
                                                   min="0" max="999">
                                            <div class="form-text">Lower numbers appear first</div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <select class="form-select" id="status" name="status">
                                                <option value="active" <?php echo ($member_data['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo ($member_data['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                <option value="on_leave" <?php echo ($member_data['status'] ?? '') === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="featured" name="featured"
                                                       <?php echo ($member_data['featured'] ?? 0) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="featured">
                                                    Featured Member
                                                </label>
                                                <div class="form-text">Featured members appear prominently on the team page</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <a href="team.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        <?php echo $action === 'create' ? 'Add Member' : 'Update Member'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Team Members List -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-list me-2"></i> All Team Members
                                    </h5>
                                </div>
                                <div class="col-auto">
                                    <div class="d-flex gap-2 flex-wrap">
                                        <!-- Search -->
                                        <div class="input-group" style="width: 250px;">
                                            <input type="text" class="form-control" id="searchTeam" placeholder="Search team members...">
                                            <button class="btn btn-outline-secondary" type="button">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>

                                        <!-- Filter by Role -->
                                        <select class="form-select" id="filterRole" style="width: 150px;">
                                            <option value="">All Roles</option>
                                            <option value="faculty">Faculty</option>
                                            <option value="staff">Staff</option>
                                            <option value="student">Student Leaders</option>
                                            <option value="alumni">Alumni</option>
                                            <option value="industry">Industry Experts</option>
                                        </select>

                                        <!-- Filter by Department -->
                                        <select class="form-select" id="filterDepartment" style="width: 150px;">
                                            <option value="">All Departments</option>
                                            <option value="computer">Computer Engineering</option>
                                            <option value="it">Information Technology</option>
                                            <option value="electronics">Electronics</option>
                                            <option value="mechanical">Mechanical</option>
                                            <option value="civil">Civil</option>
                                            <option value="management">Management</option>
                                        </select>
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
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM team_members", []); ?></h3>
                                            <p>Total Members</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-success">
                                            <i class="fas fa-chalkboard-teacher"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM team_members WHERE role = 'faculty'", []); ?></h3>
                                            <p>Faculty</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-info">
                                            <i class="fas fa-graduation-cap"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM team_members WHERE role = 'student'", []); ?></h3>
                                            <p>Students</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-warning">
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM team_members WHERE featured = 1", []); ?></h3>
                                            <p>Featured</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-success">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM team_members WHERE status = 'active'", []); ?></h3>
                                            <p>Active</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-secondary">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(DISTINCT department) FROM team_members WHERE department IS NOT NULL", []); ?></h3>
                                            <p>Departments</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Team Members Grid -->
                            <div class="row" id="teamGrid">
                                <?php
                                $sql = "SELECT * FROM team_members ORDER BY order_index ASC, name ASC";
                                $members = fetchAll($sql);

                                foreach ($members as $member):
                                ?>
                                <div class="col-md-4 col-lg-3 mb-4 team-member-card" data-member-id="<?php echo $member['id']; ?>"
                                     data-role="<?php echo $member['role']; ?>" data-department="<?php echo $member['department'] ?? ''; ?>">
                                    <div class="card team-card h-100" draggable="true">
                                        <div class="card-body text-center">
                                            <?php if (!empty($member['photo_url'])): ?>
                                                <img src="<?php echo SITE_URL . '/' . $member['photo_url']; ?>"
                                                     alt="<?php echo htmlspecialchars($member['name']); ?>"
                                                     class="team-member-photo mb-3">
                                            <?php else: ?>
                                                <div class="team-member-placeholder mb-3">
                                                    <i class="fas fa-user fa-3x"></i>
                                                </div>
                                            <?php endif; ?>

                                            <h6 class="member-name"><?php echo htmlspecialchars($member['name']); ?></h6>
                                            <p class="member-position text-muted small"><?php echo htmlspecialchars($member['position']); ?></p>

                                            <div class="member-badges mb-2">
                                                <span class="badge bg-<?php echo getRoleColor($member['role']); ?> me-1">
                                                    <?php echo ucfirst($member['role']); ?>
                                                </span>
                                                <?php if ($member['featured']): ?>
                                                    <span class="badge bg-warning me-1">
                                                        <i class="fas fa-star"></i>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="badge bg-<?php echo getStatusColor($member['status']); ?>">
                                                    <?php echo ucfirst($member['status']); ?>
                                                </span>
                                            </div>

                                            <?php if ($member['department']): ?>
                                                <p class="member-department small text-muted mb-2">
                                                    <i class="fas fa-building me-1"></i>
                                                    <?php echo getDepartmentName($member['department']); ?>
                                                </p>
                                            <?php endif; ?>

                                            <?php if (!empty($member['email']) || !empty($member['phone'])): ?>
                                                <div class="member-contact small mb-3">
                                                    <?php if (!empty($member['email'])): ?>
                                                        <div><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($member['email']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($member['phone'])): ?>
                                                        <div><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($member['phone']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <div class="member-social mb-3">
                                                <?php if (!empty($member['linkedin'])): ?>
                                                    <a href="<?php echo htmlspecialchars($member['linkedin']); ?>" target="_blank" class="social-link">
                                                        <i class="fab fa-linkedin"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!empty($member['github'])): ?>
                                                    <a href="<?php echo htmlspecialchars($member['github']); ?>" target="_blank" class="social-link">
                                                        <i class="fab fa-github"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!empty($member['twitter'])): ?>
                                                    <a href="<?php echo htmlspecialchars($member['twitter']); ?>" target="_blank" class="social-link">
                                                        <i class="fab fa-twitter"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>

                                            <div class="member-actions">
                                                <div class="btn-group btn-group-sm w-100" role="group">
                                                    <button class="btn btn-outline-info" onclick="viewMember(<?php echo $member['id']; ?>)" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-warning" onclick="editMember(<?php echo $member['id']; ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-outline-warning" onclick="toggleFeatured(<?php echo $member['id']; ?>, <?php echo $member['featured'] ? 0 : 1; ?>)" title="Toggle Featured">
                                                        <i class="fas fa-star"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="deleteMember(<?php echo $member['id']; ?>)" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Team Member Details Modal -->
<div class="modal fade" id="memberDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Team Member Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="memberDetailsContent">
                <!-- Member details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<style>
/* Admin Team Management Styles */
.team-member-card {
    transition: all 0.3s ease;
}

.team-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    transition: all 0.3s ease;
    cursor: move;
}

.team-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 255, 136, 0.1);
}

.team-member-photo {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--accent-color);
}

.team-member-placeholder {
    width: 80px;
    height: 80px;
    background: var(--glass-bg);
    border: 2px dashed var(--border-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    color: var(--text-muted);
}

.member-name {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.member-position {
    margin: 0 0 0.5rem 0;
    font-size: 0.875rem;
}

.member-badges {
    min-height: 24px;
}

.member-contact {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.member-social {
    min-height: 30px;
}

.social-link {
    display: inline-block;
    width: 30px;
    height: 30px;
    line-height: 30px;
    text-align: center;
    background: var(--glass-bg);
    border: 1px solid var(--border-color);
    border-radius: 50%;
    color: var(--text-secondary);
    margin: 0 2px;
    transition: all 0.3s ease;
}

.social-link:hover {
    background: var(--accent-color);
    color: var(--primary-color);
    transform: scale(1.1);
}

/* Drag and drop styles */
.team-card.dragging {
    opacity: 0.5;
    transform: rotate(5deg);
}

.team-member-card.drag-over {
    transform: scale(1.05);
}

/* Statistics cards */
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

/* Form enhancements */
.form-control:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 15px rgba(0, 255, 136, 0.2);
}

#photoPreview img {
    width: 120px;
    height: 120px;
    border-radius: 8px;
    border: 2px solid var(--border-color);
}

/* Badge colors */
.badge.bg-primary { background: var(--primary-color) !important; }
.badge.bg-success { background: var(--success-color) !important; }
.badge.bg-warning { background: var(--warning-color) !important; }
.badge.bg-info { background: var(--info-color) !important; }
.badge.bg-danger { background: var(--error-color) !important; }
.badge.bg-secondary { background: var(--text-muted) !important; }

/* Responsive design */
@media (max-width: 768px) {
    .team-member-card {
        margin-bottom: 1.5rem;
    }

    .stat-card {
        margin-bottom: 1rem;
    }
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

// Photo preview
function previewPhoto(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('photoPreview');

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 120px; border-radius: 8px;">`;
        }
        reader.readAsDataURL(file);
    }
}

// Search functionality
document.getElementById('searchTeam')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const cards = document.querySelectorAll('.team-member-card');

    cards.forEach(card => {
        const text = card.textContent.toLowerCase();
        card.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Filter functionality
document.getElementById('filterRole')?.addEventListener('change', function() {
    filterTeamMembers();
});

document.getElementById('filterDepartment')?.addEventListener('change', function() {
    filterTeamMembers();
});

function filterTeamMembers() {
    const roleFilter = document.getElementById('filterRole').value;
    const deptFilter = document.getElementById('filterDepartment').value;
    const cards = document.querySelectorAll('.team-member-card');

    cards.forEach(card => {
        const role = card.dataset.role;
        const dept = card.dataset.department;

        const roleMatch = !roleFilter || role === roleFilter;
        const deptMatch = !deptFilter || dept === deptFilter;

        card.style.display = roleMatch && deptMatch ? '' : 'none';
    });
}

// CRUD operations
function viewMember(memberId) {
    const modal = new bootstrap.Modal(document.getElementById('memberDetailsModal'));
    const content = document.getElementById('memberDetailsContent');

    // Show loading
    content.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    modal.show();

    // Fetch member details
    fetch(`../api/team.php?action=get&id=${memberId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const member = data.data;
                let socialLinks = '';
                if (member.linkedin || member.github || member.twitter || member.instagram) {
                    socialLinks = `
                        <div class="mt-3">
                            <h6>Social Media</h6>
                            <div class="d-flex gap-2">
                                ${member.linkedin ? `<a href="${member.linkedin}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fab fa-linkedin"></i></a>` : ''}
                                ${member.github ? `<a href="${member.github}" target="_blank" class="btn btn-sm btn-outline-dark"><i class="fab fa-github"></i></a>` : ''}
                                ${member.twitter ? `<a href="${member.twitter}" target="_blank" class="btn btn-sm btn-outline-info"><i class="fab fa-twitter"></i></a>` : ''}
                                ${member.instagram ? `<a href="${member.instagram}" target="_blank" class="btn btn-sm btn-outline-danger"><i class="fab fa-instagram"></i></a>` : ''}
                            </div>
                        </div>
                    `;
                }

                content.innerHTML = `
                    <div class="row">
                        <div class="col-md-4 text-center">
                            ${member.photo_url ?
                                `<img src="${SITE_URL}/${member.photo_url}" class="img-fluid rounded-circle mb-3" style="max-width: 200px;" alt="${member.name}">` :
                                `<div class="member-placeholder-large mb-3"><i class="fas fa-user fa-5x"></i></div>`
                            }
                            <h5>${member.name}</h5>
                            <p class="text-muted">${member.position}</p>
                            <div class="mb-3">
                                <span class="badge bg-${getRoleColor(member.role)}">${member.role}</span>
                                ${member.featured ? '<span class="badge bg-warning"><i class="fas fa-star"></i> Featured</span>' : ''}
                                <span class="badge bg-${getStatusColor(member.status)}">${member.status}</span>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h6>Contact Information</h6>
                            <table class="table table-sm">
                                ${member.email ? `<tr><td><strong>Email:</strong></td><td>${member.email}</td></tr>` : ''}
                                ${member.phone ? `<tr><td><strong>Phone:</strong></td><td>${member.phone}</td></tr>` : ''}
                                ${member.department ? `<tr><td><strong>Department:</strong></td><td>${getDepartmentName(member.department)}</td></tr>` : ''}
                                <tr><td><strong>Order:</strong></td><td>${member.order_index || 0}</td></tr>
                                <tr><td><strong>Joined:</strong></td><td>${formatDate(member.created_at)}</td></tr>
                            </table>

                            ${member.bio ? `
                                <div class="mt-3">
                                    <h6>Biography</h6>
                                    <p>${member.bio}</p>
                                </div>
                            ` : ''}

                            ${socialLinks}
                        </div>
                    </div>
                `;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Error loading member details</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error loading member details</div>';
        });
}

function editMember(memberId) {
    window.location.href = `team.php?action=edit&id=${memberId}`;
}

function deleteMember(memberId) {
    if (confirm('Are you sure you want to delete this team member? This action cannot be undone.')) {
        window.location.href = `team.php?action=delete&id=${memberId}`;
    }
}

function toggleFeatured(memberId, featured) {
    window.location.href = `team.php?action=toggle_featured&id=${memberId}&featured=${featured}`;
}

// Reorder functionality
let draggedElement = null;

function reorderMembers() {
    const cards = document.querySelectorAll('.team-member-card');

    cards.forEach(card => {
        card.draggable = true;
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragover', handleDragOver);
        card.addEventListener('drop', handleDrop);
        card.addEventListener('dragend', handleDragEnd);
    });

    // Add visual indicator
    cards.forEach(card => {
        card.style.border = '2px dashed var(--accent-color)';
    });

    // Show reorder button
    setTimeout(() => {
        if (confirm('Drag and drop members to reorder, then click OK to save changes.')) {
            saveOrder();
        } else {
            location.reload();
        }
    }, 100);
}

function handleDragStart(e) {
    draggedElement = this;
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
}

function handleDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    e.dataTransfer.dropEffect = 'move';

    const afterElement = getDragAfterElement(e.currentTarget.parentElement, e.clientY);
    if (afterElement == null) {
        e.currentTarget.parentElement.appendChild(draggedElement);
    } else {
        e.currentTarget.parentElement.insertBefore(draggedElement, afterElement);
    }

    return false;
}

function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }
    return false;
}

function handleDragEnd(e) {
    this.classList.remove('dragging');
    draggedElement = null;
}

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.team-member-card:not(.dragging)')];

    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;

        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

function saveOrder() {
    const cards = document.querySelectorAll('.team-member-card');
    const orderData = Array.from(cards).map(card => card.dataset.memberId);

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'team.php?action=reorder';

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'order_data';
    input.value = JSON.stringify(orderData);

    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

// Helper functions
function getRoleColor(role) {
    const colors = {
        'faculty' => 'primary',
        'staff' => 'info',
        'student' => 'success',
        'alumni' => 'warning',
        'industry' => 'danger'
    };
    return colors[role] || 'secondary';
}

function getStatusColor(status) {
    const colors = {
        'active' => 'success',
        'inactive' => 'secondary',
        'on_leave' => 'warning'
    };
    return colors[status] || 'secondary';
}

function getDepartmentName(dept) {
    const names = {
        'computer' => 'Computer Engineering',
        'it' => 'Information Technology',
        'electronics' => 'Electronics',
        'mechanical' => 'Mechanical',
        'civil' => 'Civil',
        'management' => 'Management',
        'other' => 'Other'
    };
    return names[dept] || dept;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

// Clear form data
<?php if (isset($_SESSION['form_data'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    <?php unset($_SESSION['form_data']); ?>
});
<?php endif; ?>
</script>

<?php
// Helper functions
function getRoleColor($role) {
    $colors = [
        'faculty' => 'primary',
        'staff' => 'info',
        'student' => 'success',
        'alumni' => 'warning',
        'industry' => 'danger'
    ];
    return $colors[$role] ?? 'secondary';
}

function getStatusColor($status) {
    $colors = [
        'active' => 'success',
        'inactive' => 'secondary',
        'on_leave' => 'warning'
    ];
    return $colors[$status] ?? 'secondary';
}

function getDepartmentName($dept) {
    $names = [
        'computer' => 'Computer Engineering',
        'it' => 'Information Technology',
        'electronics' => 'Electronics',
        'mechanical' => 'Mechanical',
        'civil' => 'Civil',
        'management' => 'Management',
        'other' => 'Other'
    ];
    return $names[$dept] ?? $dept;
}

include __DIR__ . '/../templates/admin_footer.php';
?>