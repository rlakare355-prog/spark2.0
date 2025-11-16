<?php
// SPARK Platform - Admin Research Management
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireAdmin();

// Handle research operations
$action = $_GET['action'] ?? 'list';

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $category = sanitize($_POST['category']);
        $tech_stack = sanitize($_POST['tech_stack']);
        $requirements = sanitize($_POST['requirements']);
        $max_members = (int)($_POST['max_members'] ?? 0);
        $status = sanitize($_POST['status'] ?? 'ongoing');
        $featured = isset($_POST['featured']) ? 1 : 0;
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];

        // Validation
        if (empty($title) || empty($description)) {
            throw new Exception("Title and description are required");
        }

        // Handle image upload
        $image_url = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image_url = uploadFile($_FILES['image'], 'research');
        }

        // Insert research project
        $sql = "INSERT INTO research_projects (
            title, description, category, tech_stack, requirements, max_members,
            status, featured, image_url, start_date, end_date, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $params = [
            $title, $description, $category, $tech_stack, $requirements, $max_members,
            $status, $featured, $image_url, $start_date, $end_date, $_SESSION['user_id']
        ];

        $project_id = executeInsert($sql, $params);

        // Log activity
        logActivity('research_project_created', "Research project '{$title}' created", $_SESSION['user_id'], $project_id);

        $_SESSION['success'][] = "Research project created successfully!";
        header('Location: research.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        $_SESSION['form_data'] = $_POST;
        header('Location: research.php?action=create');
        exit();
    }
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $project_id = (int)$_POST['project_id'];
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $category = sanitize($_POST['category']);
        $tech_stack = sanitize($_POST['tech_stack']);
        $requirements = sanitize($_POST['requirements']);
        $max_members = (int)($_POST['max_members'] ?? 0);
        $status = sanitize($_POST['status'] ?? 'ongoing');
        $featured = isset($_POST['featured']) ? 1 : 0;
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];

        // Validation
        if (empty($title) || empty($description)) {
            throw new Exception("Title and description are required");
        }

        // Handle image upload
        $image_url = $_POST['existing_image'] ?? '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image_url = uploadFile($_FILES['image'], 'research');
        }

        // Update research project
        $sql = "UPDATE research_projects SET
            title = ?, description = ?, category = ?, tech_stack = ?, requirements = ?,
            max_members = ?, status = ?, featured = ?, image_url = ?,
            start_date = ?, end_date = ?, updated_at = NOW()
        WHERE id = ?";

        $params = [
            $title, $description, $category, $tech_stack, $requirements, $max_members,
            $status, $featured, $image_url, $start_date, $end_date, $project_id
        ];

        executeUpdate($sql, $params);

        // Log activity
        logActivity('research_project_updated', "Research project '{$title}' updated", $_SESSION['user_id'], $project_id);

        $_SESSION['success'][] = "Research project updated successfully!";
        header('Location: research.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: research.php?action=edit&id=' . $_POST['project_id']);
        exit();
    }
}

if ($action === 'delete') {
    try {
        $project_id = (int)$_GET['id'];

        // Get project info for logging
        $project = fetchRow("SELECT title FROM research_projects WHERE id = ?", [$project_id]);
        if (!$project) {
            throw new Exception("Research project not found");
        }

        // Check if project has members
        $members = fetchColumn("SELECT COUNT(*) FROM research_project_members WHERE project_id = ?", [$project_id]);
        if ($members > 0) {
            throw new Exception("Cannot delete project with existing members. Remove members first.");
        }

        // Delete project
        executeUpdate("DELETE FROM research_projects WHERE id = ?", [$project_id]);

        // Log activity
        logActivity('research_project_deleted', "Research project '{$project['title']}' deleted", $_SESSION['user_id'], $project_id);

        $_SESSION['success'][] = "Research project deleted successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: research.php');
    exit();
}

if ($action === 'toggle_featured') {
    try {
        $project_id = (int)$_GET['id'];
        $featured = (int)$_GET['featured'];

        executeUpdate("UPDATE research_projects SET featured = ? WHERE id = ?", [$featured, $project_id]);

        $action_text = $featured ? 'featured' : 'unfeatured';
        $_SESSION['success'][] = "Research project {$action_text} successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: research.php');
    exit();
}

if ($action === 'manage_members' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $project_id = (int)$_POST['project_id'];
        $action_type = sanitize($_POST['member_action']);

        if ($action_type === 'add_member') {
            $student_id = (int)$_POST['student_id'];
            $role = sanitize($_POST['role']);
            $status = sanitize($_POST['status'] ?? 'active');

            // Check if student already exists
            $exists = fetchColumn("SELECT COUNT(*) FROM research_project_members WHERE project_id = ? AND student_id = ?", [$project_id, $student_id]);
            if ($exists) {
                throw new Exception("Student is already a member of this project");
            }

            // Add member
            executeUpdate("
                INSERT INTO research_project_members (project_id, student_id, role, status, joined_at, added_by)
                VALUES (?, ?, ?, ?, NOW(), ?)
            ", [$project_id, $student_id, $role, $status, $_SESSION['user_id']]);

            logActivity('research_member_added', "Student added to project", $_SESSION['user_id'], $project_id);
            $_SESSION['success'][] = "Member added successfully!";

        } elseif ($action_type === 'remove_member') {
            $member_id = (int)$_POST['member_id'];

            executeUpdate("DELETE FROM research_project_members WHERE id = ?", [$member_id]);
            logActivity('research_member_removed', "Member removed from project", $_SESSION['user_id'], $project_id);
            $_SESSION['success'][] = "Member removed successfully!";

        } elseif ($action_type === 'update_member') {
            $member_id = (int)$_POST['member_id'];
            $role = sanitize($_POST['role']);
            $status = sanitize($_POST['status']);

            executeUpdate("
                UPDATE research_project_members SET role = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ", [$role, $status, $member_id]);

            logActivity('research_member_updated', "Member role/status updated", $_SESSION['user_id'], $project_id);
            $_SESSION['success'][] = "Member updated successfully!";
        }

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header("Location: research.php?action=members&id=$project_id");
    exit();
}

$page_title = 'Research Management';
$breadcrumb = [
    ['name' => 'Dashboard', 'link' => 'index.php', 'active' => false],
    ['name' => 'Research', 'link' => '', 'active' => true]
];

include __DIR__ . '/../templates/admin_header.php';
?>

<!-- Research Management Section -->
<section class="admin-section py-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="section-title">
                            <i class="fas fa-flask me-2"></i> Research Management
                        </h2>
                        <p class="text-muted">Manage research projects and collaborations</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-info" onclick="exportResearch()">
                            <i class="fas fa-download me-2"></i> Export
                        </button>
                        <a href="research.php?action=create" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Create Project
                        </a>
                    </div>
                </div>

                <?php include __DIR__ . '/../includes/alerts.php'; ?>

                <?php if ($action === 'create' || $action === 'edit'): ?>
                    <!-- Create/Edit Research Project Form -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-<?php echo $action === 'create' ? 'plus' : 'edit'; ?> me-2"></i>
                                <?php echo $action === 'create' ? 'Create Research Project' : 'Edit Research Project'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $project_data = $_SESSION['form_data'] ?? [];
                            if ($action === 'edit') {
                                $project_id = (int)$_GET['id'];
                                $project_data = fetchRow("SELECT * FROM research_projects WHERE id = ?", [$project_id]);
                                if (!$project_data) {
                                    $_SESSION['errors'][] = "Research project not found";
                                    header('Location: research.php');
                                    exit();
                                }
                            }
                            ?>

                            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="project_id" value="<?php echo $project_data['id']; ?>">
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="title" class="form-label">Project Title *</label>
                                            <input type="text" class="form-control" id="title" name="title"
                                                   value="<?php echo htmlspecialchars($project_data['title'] ?? ''); ?>"
                                                   required maxlength="255" placeholder="Enter project title">
                                            <div class="invalid-feedback">Please provide a project title</div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description *</label>
                                            <textarea class="form-control" id="description" name="description"
                                                      rows="6" required placeholder="Describe the research project..."><?php echo htmlspecialchars($project_data['description'] ?? ''); ?></textarea>
                                            <div class="invalid-feedback">Please provide a description</div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="category" class="form-label">Category</label>
                                                    <select class="form-select" id="category" name="category">
                                                        <option value="">Select Category</option>
                                                        <option value="ai_ml" <?php echo ($project_data['category'] ?? '') === 'ai_ml' ? 'selected' : ''; ?>>AI & Machine Learning</option>
                                                        <option value="web_dev" <?php echo ($project_data['category'] ?? '') === 'web_dev' ? 'selected' : ''; ?>>Web Development</option>
                                                        <option value="mobile_dev" <?php echo ($project_data['category'] ?? '') === 'mobile_dev' ? 'selected' : ''; ?>>Mobile Development</option>
                                                        <option value="data_science" <?php echo ($project_data['category'] ?? '') === 'data_science' ? 'selected' : ''; ?>>Data Science</option>
                                                        <option value="iot" <?php echo ($project_data['category'] ?? '') === 'iot' ? 'selected' : ''; ?>>IoT</option>
                                                        <option value="blockchain" <?php echo ($project_data['category'] ?? '') === 'blockchain' ? 'selected' : ''; ?>>Blockchain</option>
                                                        <option value="cybersecurity" <?php echo ($project_data['category'] ?? '') === 'cybersecurity' ? 'selected' : ''; ?>>Cybersecurity</option>
                                                        <option value="cloud" <?php echo ($project_data['category'] ?? '') === 'cloud' ? 'selected' : ''; ?>>Cloud Computing</option>
                                                        <option value="other" <?php echo ($project_data['category'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="max_members" class="form-label">Maximum Members</label>
                                                    <input type="number" class="form-control" id="max_members" name="max_members"
                                                           value="<?php echo htmlspecialchars($project_data['max_members'] ?? 5); ?>"
                                                           min="1" max="50">
                                                    <div class="form-text">Leave 0 for unlimited</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="tech_stack" class="form-label">Technology Stack</label>
                                            <input type="text" class="form-control" id="tech_stack" name="tech_stack"
                                                   value="<?php echo htmlspecialchars($project_data['tech_stack'] ?? ''); ?>"
                                                   placeholder="React, Node.js, Python, TensorFlow">
                                            <div class="form-text">Comma-separated list of technologies</div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="requirements" class="form-label">Requirements</label>
                                            <textarea class="form-control" id="requirements" name="requirements"
                                                      rows="3" placeholder="Skills and knowledge required..."><?php echo htmlspecialchars($project_data['requirements'] ?? ''); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="mb-3">
                                                    <label for="start_date" class="form-label">Start Date</label>
                                                    <input type="date" class="form-control" id="start_date" name="start_date"
                                                           value="<?php echo htmlspecialchars($project_data['start_date'] ?? date('Y-m-d')); ?>">
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="mb-3">
                                                    <label for="end_date" class="form-label">End Date</label>
                                                    <input type="date" class="form-control" id="end_date" name="end_date"
                                                           value="<?php echo htmlspecialchars($project_data['end_date'] ?? ''); ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <select class="form-select" id="status" name="status">
                                                <option value="planning" <?php echo ($project_data['status'] ?? '') === 'planning' ? 'selected' : ''; ?>>Planning</option>
                                                <option value="ongoing" <?php echo ($project_data['status'] ?? 'ongoing') === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                                <option value="completed" <?php echo ($project_data['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="paused" <?php echo ($project_data['status'] ?? '') === 'paused' ? 'selected' : ''; ?>>Paused</option>
                                                <option value="cancelled" <?php echo ($project_data['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="featured" name="featured"
                                                       <?php echo ($project_data['featured'] ?? 0) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="featured">
                                                    Featured Project
                                                </label>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="image" class="form-label">Project Image</label>
                                            <input type="file" class="form-control" id="image" name="image"
                                                   accept="image/*" onchange="previewImage(event)">
                                            <?php if (!empty($project_data['image_url'])): ?>
                                                <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($project_data['image_url']); ?>">
                                                <div class="mt-2">
                                                    <small class="text-muted">Current image:</small><br>
                                                    <img src="<?php echo SITE_URL . '/' . $project_data['image_url']; ?>"
                                                         alt="Current image" style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 8px;">
                                                </div>
                                            <?php endif; ?>
                                            <div id="imagePreview" class="mt-2"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <a href="research.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        <?php echo $action === 'create' ? 'Create Project' : 'Update Project'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($action === 'members'): ?>
                    <!-- Manage Project Members -->
                    <?php
                    $project_id = (int)$_GET['id'];
                    $project = fetchRow("SELECT * FROM research_projects WHERE id = ?", [$project_id]);
                    if (!$project) {
                        $_SESSION['errors'][] = "Research project not found";
                        header('Location: research.php');
                        exit();
                    }

                    $members = fetchAll("
                        SELECT rpm.*, s.first_name, s.last_name, s.email, s.department
                        FROM research_project_members rpm
                        JOIN students s ON rpm.student_id = s.id
                        WHERE rpm.project_id = ?
                        ORDER BY rpm.joined_at DESC
                    ", [$project_id]);
                    ?>

                    <div class="card admin-card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-users me-2"></i> Manage Members - <?php echo htmlspecialchars($project['title']); ?>
                                </h5>
                                <a href="research.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Back to Projects
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Add Member Form -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <form method="POST" action="research.php?action=manage_members">
                                        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                                        <input type="hidden" name="member_action" value="add_member">

                                        <div class="row align-items-end">
                                            <div class="col-md-4">
                                                <label class="form-label">Select Student</label>
                                                <select class="form-select" name="student_id" required>
                                                    <option value="">Select student to add...</option>
                                                    <?php
                                                    $available_students = fetchAll("
                                                        SELECT s.id, s.first_name, s.last_name, s.email
                                                        FROM students s
                                                        WHERE s.id NOT IN (
                                                            SELECT student_id FROM research_project_members WHERE project_id = ?
                                                        ) AND s.status = 'active'
                                                        ORDER BY s.first_name, s.last_name
                                                    ", [$project_id]);

                                                    foreach ($available_students as $student):
                                                    ?>
                                                    <option value="<?php echo $student['id']; ?>">
                                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['email'] . ')'); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Role</label>
                                                <select class="form-select" name="role">
                                                    <option value="member">Member</option>
                                                    <option value="coordinator">Coordinator</option>
                                                    <option value="domain_lead">Domain Lead</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Status</label>
                                                <select class="form-select" name="status">
                                                    <option value="active">Active</option>
                                                    <option value="inactive">Inactive</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <button type="submit" class="btn btn-primary w-100">
                                                    <i class="fas fa-plus me-2"></i> Add Member
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Members List -->
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Joined Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($members): ?>
                                            <?php foreach ($members as $member): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($member['email']); ?></small>
                                                        <?php if ($member['department']): ?>
                                                            <br><span class="badge bg-info"><?php echo htmlspecialchars($member['department']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getMemberRoleColor($member['role']); ?>">
                                                        <?php echo ucwords($member['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $member['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($member['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($member['joined_at']); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-warning" onclick="editMember(<?php echo $member['id']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="removeMember(<?php echo $member['id']; ?>)">
                                                            <i class="fas fa-user-minus"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">
                                                    No members added to this project yet.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Research Projects List -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-list me-2"></i> All Research Projects
                                    </h5>
                                </div>
                                <div class="col-auto">
                                    <div class="d-flex gap-2 flex-wrap">
                                        <!-- Search -->
                                        <div class="input-group" style="width: 250px;">
                                            <input type="text" class="form-control" id="searchProjects" placeholder="Search projects...">
                                            <button class="btn btn-outline-secondary" type="button">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>

                                        <!-- Filter by Category -->
                                        <select class="form-select" id="filterCategory" style="width: 150px;">
                                            <option value="">All Categories</option>
                                            <option value="ai_ml">AI & Machine Learning</option>
                                            <option value="web_dev">Web Development</option>
                                            <option value="mobile_dev">Mobile Development</option>
                                            <option value="data_science">Data Science</option>
                                            <option value="iot">IoT</option>
                                            <option value="blockchain">Blockchain</option>
                                            <option value="cybersecurity">Cybersecurity</option>
                                            <option value="cloud">Cloud Computing</option>
                                            <option value="other">Other</option>
                                        </select>

                                        <!-- Filter by Status -->
                                        <select class="form-select" id="filterStatus" style="width: 120px;">
                                            <option value="">All Status</option>
                                            <option value="planning">Planning</option>
                                            <option value="ongoing">Ongoing</option>
                                            <option value="completed">Completed</option>
                                            <option value="paused">Paused</option>
                                            <option value="cancelled">Cancelled</option>
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
                                            <i class="fas fa-flask"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM research_projects", []); ?></h3>
                                            <p>Total Projects</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-success">
                                            <i class="fas fa-play-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM research_projects WHERE status = 'ongoing'", []); ?></h3>
                                            <p>Ongoing</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-info">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(DISTINCT student_id) FROM research_project_members", []); ?></h3>
                                            <p>Participants</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-warning">
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM research_projects WHERE featured = 1", []); ?></h3>
                                            <p>Featured</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-secondary">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM research_projects WHERE status = 'completed'", []); ?></h3>
                                            <p>Completed</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-danger">
                                            <i class="fas fa-folder"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(DISTINCT category) FROM research_projects", []); ?></h3>
                                            <p>Categories</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Projects Table -->
                            <div class="table-responsive">
                                <table class="table table-hover" id="projectsTable">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Project</th>
                                            <th>Category</th>
                                            <th>Members</th>
                                            <th>Status</th>
                                            <th>Featured</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sql = "
                                            SELECT p.*,
                                                   (SELECT COUNT(*) FROM research_project_members rpm WHERE rpm.project_id = p.id) as member_count
                                            FROM research_projects p
                                            ORDER BY p.featured DESC, p.created_at DESC
                                        ";
                                        $projects = fetchAll($sql);

                                        foreach ($projects as $project):
                                        ?>
                                        <tr data-project-id="<?php echo $project['id']; ?>">
                                            <td>
                                                <?php if (!empty($project['image_url'])): ?>
                                                    <img src="<?php echo SITE_URL . '/' . $project['image_url']; ?>"
                                                         alt="<?php echo htmlspecialchars($project['title']); ?>"
                                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                                <?php else: ?>
                                                    <div class="project-placeholder">
                                                        <i class="fas fa-flask"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($project['title']); ?></strong>
                                                    <?php if (!empty($project['tech_stack'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($project['tech_stack'], 0, 80)) . '...'; ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getProjectCategoryColor($project['category']); ?>">
                                                    <?php echo getProjectCategoryName($project['category']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo $project['member_count']; ?>
                                                    <?php if ($project['max_members'] > 0): ?>
                                                        / <?php echo $project['max_members']; ?>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getProjectStatusColor($project['status']); ?>">
                                                    <?php echo ucfirst($project['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox"
                                                           <?php echo $project['featured'] ? 'checked' : ''; ?>
                                                           onchange="toggleFeatured(<?php echo $project['id']; ?>, <?php echo $project['featured'] ? 0 : 1; ?>)">
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-info" onclick="viewProject(<?php echo $project['id']; ?>)" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="manageMembers(<?php echo $project['id']; ?>)" title="Manage Members">
                                                        <i class="fas fa-users"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="editProject(<?php echo $project['id']; ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteProject(<?php echo $project['id']; ?>)" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Member Edit Modal -->
<div class="modal fade" id="memberModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="memberForm">
                    <input type="hidden" name="member_id" id="memberIdInput">
                    <input type="hidden" name="project_id" value="<?php echo $project_id ?? ''; ?>">
                    <input type="hidden" name="member_action" value="update_member">

                    <div class="mb-3">
                        <label for="memberRole" class="form-label">Role</label>
                        <select class="form-select" id="memberRole" name="role">
                            <option value="member">Member</option>
                            <option value="coordinator">Coordinator</option>
                            <option value="domain_lead">Domain Lead</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="memberStatus" class="form-label">Status</label>
                        <select class="form-select" id="memberStatus" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Member</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Admin Research Management Styles */
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

.project-placeholder {
    width: 50px;
    height: 50px;
    background: var(--card-bg);
    border: 2px dashed var(--border-color);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
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

#imagePreview img {
    width: 100%;
    max-height: 200px;
    border-radius: 8px;
    border: 2px solid var(--border-color);
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

// Image preview
function previewImage(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('imagePreview');

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="max-height: 200px;">`;
        }
        reader.readAsDataURL(file);
    }
}

// Search functionality
document.getElementById('searchProjects')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#projectsTable tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Filter functionality
document.getElementById('filterCategory')?.addEventListener('change', function() {
    filterProjects();
});

document.getElementById('filterStatus')?.addEventListener('change', function() {
    filterProjects();
});

function filterProjects() {
    const categoryFilter = document.getElementById('filterCategory').value;
    const statusFilter = document.getElementById('filterStatus').value;
    const rows = document.querySelectorAll('#projectsTable tbody tr');

    rows.forEach(row => {
        const category = row.querySelector('td:nth-child(3) .badge').textContent.toLowerCase();
        const status = row.querySelector('td:nth-child(5) .badge').textContent.toLowerCase();

        const categoryMatch = !categoryFilter || category.includes(categoryFilter.toLowerCase().replace('_', ' '));
        const statusMatch = !statusFilter || status === statusFilter;

        row.style.display = categoryMatch && statusMatch ? '' : 'none';
    });
}

// Project operations
function viewProject(projectId) {
    // In a real implementation, this would open a modal with project details
    alert(`Viewing project ${projectId} details`);
}

function manageMembers(projectId) {
    window.location.href = `research.php?action=members&id=${projectId}`;
}

function editProject(projectId) {
    window.location.href = `research.php?action=edit&id=${projectId}`;
}

function deleteProject(projectId) {
    if (confirm('Are you sure you want to delete this research project? This action cannot be undone.')) {
        window.location.href = `research.php?action=delete&id=${projectId}`;
    }
}

function toggleFeatured(projectId, featured) {
    window.location.href = `research.php?action=toggle_featured&id=${projectId}&featured=${featured}`;
}

// Member operations
function editMember(memberId) {
    const modal = new bootstrap.Modal(document.getElementById('memberModal'));
    document.getElementById('memberIdInput').value = memberId;
    modal.show();
}

function removeMember(memberId) {
    if (confirm('Are you sure you want to remove this member from the project?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'research.php?action=manage_members';

        const input1 = document.createElement('input');
        input1.type = 'hidden';
        input1.name = 'project_id';
        input1.value = <?php echo $project_id ?? 0; ?>;

        const input2 = document.createElement('input');
        input2.type = 'hidden';
        input2.name = 'member_action';
        input2.value = 'remove_member';

        const input3 = document.createElement('input');
        input3.type = 'hidden';
        input3.name = 'member_id';
        input3.value = memberId;

        form.appendChild(input1);
        form.appendChild(input2);
        form.appendChild(input3);
        document.body.appendChild(form);
        form.submit();
    }
}

// Member form submission
document.getElementById('memberForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    this.submit();
});

// Export function
function exportResearch() {
    const categoryFilter = document.getElementById('filterCategory')?.value || '';
    const statusFilter = document.getElementById('filterStatus')?.value || '';

    let url = `../api/export.php?type=research_projects`;
    if (categoryFilter) url += `&category=${categoryFilter}`;
    if (statusFilter) url += `&status=${statusFilter}`;

    window.location.href = url;
}

// Helper functions
function getProjectCategoryColor(category) {
    const colors = {
        'ai_ml' => 'primary',
        'web_dev' => 'info',
        'mobile_dev' => 'success',
        'data_science' => 'warning',
        'iot' => 'danger',
        'blockchain' => 'primary',
        'cybersecurity' => 'warning',
        'cloud' => 'info',
        'other' => 'secondary'
    };
    return colors[category] || 'secondary';
}

function getProjectStatusColor(status) {
    const colors = {
        'planning' => 'info',
        'ongoing' => 'success',
        'completed' => 'primary',
        'paused' => 'warning',
        'cancelled' => 'danger'
    };
    return colors[status] || 'secondary';
}

function getMemberRoleColor(role) {
    const colors = {
        'member' => 'info',
        'coordinator' => 'warning',
        'domain_lead' => 'success'
    };
    return colors[role] || 'secondary';
}

function getProjectCategoryName(category) {
    const names = {
        'ai_ml' => 'AI & Machine Learning',
        'web_dev' => 'Web Development',
        'mobile_dev' => 'Mobile Development',
        'data_science' => 'Data Science',
        'iot' => 'IoT',
        'blockchain' => 'Blockchain',
        'cybersecurity' => 'Cybersecurity',
        'cloud' => 'Cloud Computing',
        'other' => 'Other'
    };
    return names[category] || category;
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
function getProjectCategoryColor($category) {
    $colors = [
        'ai_ml' => 'primary',
        'web_dev' => 'info',
        'mobile_dev' => 'success',
        'data_science' => 'warning',
        'iot' => 'danger',
        'blockchain' => 'primary',
        'cybersecurity' => 'warning',
        'cloud' => 'info',
        'other' => 'secondary'
    ];
    return $colors[$category] ?? 'secondary';
}

function getProjectStatusColor($status) {
    $colors = [
        'planning' => 'info',
        'ongoing' => 'success',
        'completed' => 'primary',
        'paused' => 'warning',
        'cancelled' => 'danger'
    ];
    return $colors[$status] ?? 'secondary';
}

function getMemberRoleColor($role) {
    $colors = [
        'member' => 'info',
        'coordinator' => 'warning',
        'domain_lead' => 'success'
    ];
    return $colors[$role] ?? 'secondary';
}

function getProjectCategoryName($category) {
    $names = [
        'ai_ml' => 'AI & Machine Learning',
        'web_dev' => 'Web Development',
        'mobile_dev' => 'Mobile Development',
        'data_science' => 'Data Science',
        'iot' => 'IoT',
        'blockchain' => 'Blockchain',
        'cybersecurity' => 'Cybersecurity',
        'cloud' => 'Cloud Computing',
        'other' => 'Other'
    ];
    return $names[$category] ?? $category;
}

include __DIR__ . '/../templates/admin_footer.php';
?>