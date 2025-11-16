<?php
// SPARK Platform - Admin Opportunities Management
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireAdmin();

// Handle opportunity operations
$action = $_GET['action'] ?? 'list';

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $company = sanitize($_POST['company']);
        $location = sanitize($_POST['location']);
        $type = sanitize($_POST['type']);
        $category = sanitize($_POST['category']);
        $experience_level = sanitize($_POST['experience_level']);
        $salary = sanitize($_POST['salary']);
        $application_deadline = $_POST['application_deadline'];
        $external_link = sanitize($_POST['external_link']);
        $contact_email = sanitize($_POST['contact_email']);
        $status = sanitize($_POST['status'] ?? 'active');
        $featured = isset($_POST['featured']) ? 1 : 0;
        $remote_work = isset($_POST['remote_work']) ? 1 : 0;

        // Tech stack processing
        $tech_stack = sanitize($_POST['tech_stack']);

        // Validation
        if (empty($title) || empty($description) || empty($company)) {
            throw new Exception("Title, description, and company are required");
        }

        // Handle company logo upload
        $logo_url = '';
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logo_url = uploadFile($_FILES['logo'], 'companies');
        }

        // Insert opportunity
        $sql = "INSERT INTO opportunities (
            title, description, company, location, type, category, experience_level,
            salary, application_deadline, external_link, contact_email, status,
            featured, remote_work, tech_stack, logo_url, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $params = [
            $title, $description, $company, $location, $type, $category, $experience_level,
            $salary, $application_deadline, $external_link, $contact_email, $status,
            $featured, $remote_work, $tech_stack, $logo_url, $_SESSION['user_id']
        ];

        $opportunity_id = executeInsert($sql, $params);

        // Log activity
        logActivity('opportunity_created', "Opportunity '{$title}' created", $_SESSION['user_id'], $opportunity_id);

        $_SESSION['success'][] = "Opportunity created successfully!";
        header('Location: opportunities.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        $_SESSION['form_data'] = $_POST;
        header('Location: opportunities.php?action=create');
        exit();
    }
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $opportunity_id = (int)$_POST['opportunity_id'];
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $company = sanitize($_POST['company']);
        $location = sanitize($_POST['location']);
        $type = sanitize($_POST['type']);
        $category = sanitize($_POST['category']);
        $experience_level = sanitize($_POST['experience_level']);
        $salary = sanitize($_POST['salary']);
        $application_deadline = $_POST['application_deadline'];
        $external_link = sanitize($_POST['external_link']);
        $contact_email = sanitize($_POST['contact_email']);
        $status = sanitize($_POST['status'] ?? 'active');
        $featured = isset($_POST['featured']) ? 1 : 0;
        $remote_work = isset($_POST['remote_work']) ? 1 : 0;

        // Tech stack processing
        $tech_stack = sanitize($_POST['tech_stack']);

        // Validation
        if (empty($title) || empty($description) || empty($company)) {
            throw new Exception("Title, description, and company are required");
        }

        // Handle company logo upload
        $logo_url = $_POST['existing_logo'] ?? '';
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logo_url = uploadFile($_FILES['logo'], 'companies');
        }

        // Update opportunity
        $sql = "UPDATE opportunities SET
            title = ?, description = ?, company = ?, location = ?, type = ?, category = ?,
            experience_level = ?, salary = ?, application_deadline = ?, external_link = ?,
            contact_email = ?, status = ?, featured = ?, remote_work = ?, tech_stack = ?,
            logo_url = ?, updated_at = NOW()
        WHERE id = ?";

        $params = [
            $title, $description, $company, $location, $type, $category, $experience_level,
            $salary, $application_deadline, $external_link, $contact_email, $status,
            $featured, $remote_work, $tech_stack, $logo_url, $opportunity_id
        ];

        executeUpdate($sql, $params);

        // Log activity
        logActivity('opportunity_updated', "Opportunity '{$title}' updated", $_SESSION['user_id'], $opportunity_id);

        $_SESSION['success'][] = "Opportunity updated successfully!";
        header('Location: opportunities.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: opportunities.php?action=edit&id=' . $_POST['opportunity_id']);
        exit();
    }
}

if ($action === 'delete') {
    try {
        $opportunity_id = (int)$_GET['id'];

        // Get opportunity info for logging
        $opportunity = fetchRow("SELECT title, logo_url FROM opportunities WHERE id = ?", [$opportunity_id]);
        if (!$opportunity) {
            throw new Exception("Opportunity not found");
        }

        // Delete logo if exists
        if (!empty($opportunity['logo_url']) && file_exists(__DIR__ . '/../' . $opportunity['logo_url'])) {
            unlink(__DIR__ . '/../' . $opportunity['logo_url']);
        }

        // Delete opportunity
        executeUpdate("DELETE FROM opportunities WHERE id = ?", [$opportunity_id]);

        // Log activity
        logActivity('opportunity_deleted', "Opportunity '{$opportunity['title']}' deleted", $_SESSION['user_id'], $opportunity_id);

        $_SESSION['success'][] = "Opportunity deleted successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: opportunities.php');
    exit();
}

if ($action === 'toggle_featured') {
    try {
        $opportunity_id = (int)$_GET['id'];
        $featured = (int)$_GET['featured'];

        executeUpdate("UPDATE opportunities SET featured = ? WHERE id = ?", [$featured, $opportunity_id]);

        $action_text = $featured ? 'featured' : 'unfeatured';
        $_SESSION['success'][] = "Opportunity {$action_text} successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: opportunities.php');
    exit();
}

if ($action === 'bulk_import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $csv_file = $_FILES['csv_file']['tmp_name'];
            $csv_content = file_get_contents($csv_file);

            $lines = explode("\n", $csv_content);
            $header = str_getcsv(array_shift($lines));

            $imported_count = 0;
            $error_count = 0;

            foreach ($lines as $line) {
                if (empty(trim($line))) continue;

                $data = str_getcsv($line);

                if (count($data) >= 5) {
                    try {
                        $sql = "INSERT INTO opportunities (
                            title, description, company, location, type, experience_level,
                            salary, external_link, status, featured, remote_work,
                            created_by, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                        executeInsert($sql, [
                            $data[0] ?? '', $data[1] ?? '', $data[2] ?? '', $data[3] ?? '',
                            $data[4] ?? 'other', 'entry_level', $data[5] ?? '',
                            $data[6] ?? '', $data[7] ?? '', 'active', 0, 0,
                            $_SESSION['user_id']
                        ]);

                        $imported_count++;
                    } catch (Exception $e) {
                        $error_count++;
                    }
                }
            }

            $_SESSION['success'][] = "Bulk import completed: {$imported_count} imported, {$error_count} errors";
        } else {
            throw new Exception("Please select a CSV file to import");
        }

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: opportunities.php');
    exit();
}

$page_title = 'Opportunities Management';
$breadcrumb = [
    ['name' => 'Dashboard', 'link' => 'index.php', 'active' => false],
    ['name' => 'Opportunities', 'link' => '', 'active' => true]
];

include __DIR__ . '/../templates/admin_header.php';
?>

<!-- Opportunities Management Section -->
<section class="admin-section py-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="section-title">
                            <i class="fas fa-briefcase me-2"></i> Opportunities Management
                        </h2>
                        <p class="text-muted">Manage job opportunities, internships, and career resources</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-warning" onclick="showBulkImport()">
                            <i class="fas fa-file-import me-2"></i> Bulk Import
                        </button>
                        <button class="btn btn-outline-info" onclick="exportOpportunities()">
                            <i class="fas fa-download me-2"></i> Export
                        </button>
                        <a href="opportunities.php?action=create" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Add Opportunity
                        </a>
                    </div>
                </div>

                <?php include __DIR__ . '/../includes/alerts.php'; ?>

                <?php if ($action === 'create' || $action === 'edit'): ?>
                    <!-- Create/Edit Opportunity Form -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-<?php echo $action === 'create' ? 'plus' : 'edit'; ?> me-2"></i>
                                <?php echo $action === 'create' ? 'Create Opportunity' : 'Edit Opportunity'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $opportunity_data = $_SESSION['form_data'] ?? [];
                            if ($action === 'edit') {
                                $opportunity_id = (int)$_GET['id'];
                                $opportunity_data = fetchRow("SELECT * FROM opportunities WHERE id = ?", [$opportunity_id]);
                                if (!$opportunity_data) {
                                    $_SESSION['errors'][] = "Opportunity not found";
                                    header('Location: opportunities.php');
                                    exit();
                                }
                            }
                            ?>

                            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="opportunity_id" value="<?php echo $opportunity_data['id']; ?>">
                                <?php endif; ?>

                                <div class="row">
                                    <!-- Basic Information -->
                                    <div class="col-md-8">
                                        <h6 class="text-primary mb-3">Basic Information</h6>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="title" class="form-label">Opportunity Title *</label>
                                                    <input type="text" class="form-control" id="title" name="title"
                                                           value="<?php echo htmlspecialchars($opportunity_data['title'] ?? ''); ?>"
                                                           required maxlength="255" placeholder="e.g., Frontend Developer Intern">
                                                    <div class="invalid-feedback">Please provide an opportunity title</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="company" class="form-label">Company/Organization *</label>
                                                    <input type="text" class="form-control" id="company" name="company"
                                                           value="<?php echo htmlspecialchars($opportunity_data['company'] ?? ''); ?>"
                                                           required maxlength="255" placeholder="e.g., Tech Corp India">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description *</label>
                                            <textarea class="form-control editor" id="description" name="description"
                                                      rows="6" required placeholder="Describe the opportunity, responsibilities, and requirements..."><?php echo htmlspecialchars($opportunity_data['description'] ?? ''); ?></textarea>
                                            <div class="invalid-feedback">Please provide a description</div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="type" class="form-label">Opportunity Type</label>
                                                    <select class="form-select" id="type" name="type">
                                                        <option value="job" <?php echo ($opportunity_data['type'] ?? 'job') === 'job' ? 'selected' : ''; ?>>Full-time Job</option>
                                                        <option value="internship" <?php echo ($opportunity_data['type'] ?? '') === 'internship' ? 'selected' : ''; ?>>Internship</option>
                                                        <option value="training" <?php echo ($opportunity_data['type'] ?? '') === 'training' ? 'selected' : ''; ?>>Training</option>
                                                        <option value="workshop" <?php echo ($opportunity_data['type'] ?? '') === 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                                                        <option value="competition" <?php echo ($opportunity_data['type'] ?? '') === 'competition' ? 'selected' : ''; ?>>Competition</option>
                                                        <option value="freelance" <?php echo ($opportunity_data['type'] ?? '') === 'freelance' ? 'selected' : ''; ?>>Freelance</option>
                                                        <option value="other" <?php echo ($opportunity_data['type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="category" class="form-label">Category</label>
                                                    <select class="form-select" id="category" name="category">
                                                        <option value="technology" <?php echo ($opportunity_data['category'] ?? '') === 'technology' ? 'selected' : ''; ?>>Technology</option>
                                                        <option value="design" <?php echo ($opportunity_data['category'] ?? '') === 'design' ? 'selected' : ''; ?>>Design</option>
                                                        <option value="marketing" <?php echo ($opportunity_data['category'] ?? '') === 'marketing' ? 'selected' : ''; ?>>Marketing</option>
                                                        <option value="management" <?php echo ($opportunity_data['category'] ?? '') === 'management' ? 'selected' : ''; ?>>Management</option>
                                                        <option value="research" <?php echo ($opportunity_data['category'] ?? '') === 'research' ? 'selected' : ''; ?>>Research</option>
                                                        <option value="education" <?php echo ($opportunity_data['category'] ?? '') === 'education' ? 'selected' : ''; ?>>Education</option>
                                                        <option value="other" <?php echo ($opportunity_data['category'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="experience_level" class="form-label">Experience Level</label>
                                                    <select class="form-select" id="experience_level" name="experience_level">
                                                        <option value="entry_level" <?php echo ($opportunity_data['experience_level'] ?? '') === 'entry_level' ? 'selected' : ''; ?>>Entry Level</option>
                                                        <option value="junior" <?php echo ($opportunity_data['experience_level'] ?? 'junior') === 'junior' ? 'selected' : ''; ?>>Junior</option>
                                                        <option value="mid_level" <?php echo ($opportunity_data['experience_level'] ?? 'mid_level') === 'mid_level' ? 'selected' : ''; ?>>Mid Level</option>
                                                        <option value="senior" <?php echo ($opportunity_data['experience_level'] ?? '') === 'senior' ? 'selected' : ''; ?>>Senior</option>
                                                        <option value="lead" <?php echo ($opportunity_data['experience_level'] ?? '') === 'lead' ? 'selected' : ''; ?>>Lead</option>
                                                        <option value="manager" <?php echo ($opportunity_data['experience_level'] ?? '') === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                                        <option value="executive" <?php echo ($opportunity_data['experience_level'] ?? '') === 'executive' ? 'selected' : ''; ?>>Executive</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="location" class="form-label">Location</label>
                                                    <input type="text" class="form-control" id="location" name="location"
                                                           value="<?php echo htmlspecialchars($opportunity_data['location'] ?? ''); ?>"
                                                           maxlength="255" placeholder="e.g., Pune, Maharashtra, India">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="salary" class="form-label">Salary/Stipend</label>
                                                    <input type="text" class="form-control" id="salary" name="salary"
                                                           value="<?php echo htmlspecialchars($opportunity_data['salary'] ?? ''); ?>"
                                                           maxlength="100" placeholder="e.g., ₹50,000/month, Stipend: ₹10,000/month">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="tech_stack" class="form-label">Technology Stack</label>
                                            <input type="text" class="form-control" id="tech_stack" name="tech_stack"
                                                   value="<?php echo htmlspecialchars($opportunity_data['tech_stack'] ?? ''); ?>"
                                                   placeholder="React, Node.js, Python, AWS, Docker">
                                            <div class="form-text">Comma-separated list of required technologies</div>
                                        </div>
                                    </div>

                                    <!-- Additional Information -->
                                    <div class="col-md-4">
                                        <h6 class="text-primary mb-3">Additional Details</h6>

                                        <div class="mb-3">
                                            <label for="application_deadline" class="form-label">Application Deadline</label>
                                            <input type="date" class="form-control" id="application_deadline" name="application_deadline"
                                                   value="<?php echo htmlspecialchars($opportunity_data['application_deadline'] ?? ''); ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label for="external_link" class="form-label">External Application Link</label>
                                            <input type="url" class="form-control" id="external_link" name="external_link"
                                                   value="<?php echo htmlspecialchars($opportunity_data['external_link'] ?? ''); ?>"
                                                   placeholder="https://careers.company.com/job/123">
                                        </div>

                                        <div class="mb-3">
                                            <label for="contact_email" class="form-label">Contact Email</label>
                                            <input type="email" class="form-control" id="contact_email" name="contact_email"
                                                   value="<?php echo htmlspecialchars($opportunity_data['contact_email'] ?? ''); ?>"
                                                   placeholder="hr@company.com">
                                        </div>

                                        <div class="row">
                                            <div class="col-6">
                                                <div class="mb-3">
                                                    <label for="status" class="form-label">Status</label>
                                                    <select class="form-select" id="status" name="status">
                                                        <option value="active" <?php echo ($opportunity_data['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo ($opportunity_data['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                        <option value="expired" <?php echo ($opportunity_data['status'] ?? '') === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                                        <option value="closed" <?php echo ($opportunity_data['status'] ?? '') === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Features</label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="featured" name="featured"
                                                               <?php echo ($opportunity_data['featured'] ?? 0) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="featured">
                                                            Featured
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="remote_work" name="remote_work"
                                                               <?php echo ($opportunity_data['remote_work'] ?? 0) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="remote_work">
                                                            Remote Work
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="logo" class="form-label">Company Logo</label>
                                            <input type="file" class="form-control" id="logo" name="logo"
                                                   accept="image/*" onchange="previewLogo(event)">
                                            <?php if (!empty($opportunity_data['logo_url'])): ?>
                                                <input type="hidden" name="existing_logo" value="<?php echo htmlspecialchars($opportunity_data['logo_url']); ?>">
                                                <div class="mt-2">
                                                    <small class="text-muted">Current logo:</small><br>
                                                    <img src="<?php echo SITE_URL . '/' . $opportunity_data['logo_url']; ?>"
                                                         alt="Current logo" style="width: 100%; max-height: 100px; object-fit: contain; border-radius: 8px;">
                                                </div>
                                            <?php endif; ?>
                                            <div id="logoPreview" class="mt-2"></div>
                                        </div>

                                        <div class="alert alert-info">
                                            <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i> Guidelines</h6>
                                            <ul class="mb-0 small">
                                                <li>Be specific about role requirements</li>
                                                <li>Include clear application instructions</li>
                                                <li>Set realistic deadlines</li>
                                                <li>Provide accurate contact information</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <a href="opportunities.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        <?php echo $action === 'create' ? 'Create Opportunity' : 'Update Opportunity'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($action === 'bulk_import'): ?>
                    <!-- Bulk Import Form -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-file-import me-2"></i> Bulk Import Opportunities
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <h6 class="text-primary mb-3">Import Instructions</h6>
                                        <p>Import opportunities from a CSV file with the following columns:</p>
                                        <ul>
                                            <li>Title</li>
                                            <li>Description</li>
                                            <li>Company</li>
                                            <li>Location</li>
                                            <li>Type</li>
                                            <li>Experience Level</li>
                                            <li>Salary</li>
                                            <li>External Link</li>
                                        </ul>

                                        <div class="alert alert-warning">
                                            <strong>Note:</strong> The first row should contain headers. Each opportunity should be on a separate row.
                                        </div>

                                        <div class="text-center mb-3">
                                            <a href="#" class="btn btn-outline-primary" onclick="downloadSampleCSV()">
                                                <i class="fas fa-download me-2"></i> Download Sample CSV
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <form method="POST" action="opportunities.php?action=bulk_import" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label for="csv_file" class="form-label">Select CSV File *</label>
                                            <input type="file" class="form-control" id="csv_file" name="csv_file"
                                                   accept=".csv" required>
                                        </div>

                                        <div class="d-flex justify-content-between">
                                            <a href="opportunities.php" class="btn btn-secondary">
                                                <i class="fas fa-times me-2"></i> Cancel
                                            </a>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-upload me-2"></i> Import Opportunities
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Opportunities List -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-list me-2"></i> All Opportunities
                                    </h5>
                                </div>
                                <div class="col-auto">
                                    <div class="d-flex gap-2 flex-wrap">
                                        <!-- Search -->
                                        <div class="input-group" style="width: 250px;">
                                            <input type="text" class="form-control" id="searchOpportunities" placeholder="Search opportunities...">
                                            <button class="btn btn-outline-secondary" type="button">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>

                                        <!-- Filter by Type -->
                                        <select class="form-select" id="filterType" style="width: 150px;">
                                            <option value="">All Types</option>
                                            <option value="job">Full-time Job</option>
                                            <option value="internship">Internship</option>
                                            <option value="training">Training</option>
                                            <option value="workshop">Workshop</option>
                                            <option value="competition">Competition</option>
                                            <option value="freelance">Freelance</option>
                                            <option value="other">Other</option>
                                        </select>

                                        <!-- Filter by Category -->
                                        <select class="form-select" id="filterCategory" style="width: 150px;">
                                            <option value="">All Categories</option>
                                            <option value="technology">Technology</option>
                                            <option value="design">Design</option>
                                            <option value="marketing">Marketing</option>
                                            <option value="management">Management</option>
                                            <option value="research">Research</option>
                                            <option value="education">Education</option>
                                            <option value="other">Other</option>
                                        </select>

                                        <!-- Filter by Status -->
                                        <select class="form-select" id="filterStatus" style="width: 120px;">
                                            <option value="">All Status</option>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                            <option value="expired">Expired</option>
                                            <option value="closed">Closed</option>
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
                                            <i class="fas fa-briefcase"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM opportunities", []); ?></h3>
                                            <p>Total</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-success">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM opportunities WHERE status = 'active'", []); ?></h3>
                                            <p>Active</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-info">
                                            <i class="fas fa-laptop-code"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM opportunities WHERE type = 'internship'", []); ?></h3>
                                            <p>Internships</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-warning">
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM opportunities WHERE featured = 1", []); ?></h3>
                                            <p>Featured</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-secondary">
                                            <i class="fas fa-home"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM opportunities WHERE remote_work = 1", []); ?></h3>
                                            <p>Remote</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-danger">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM opportunities WHERE application_deadline < CURDATE()", []); ?></h3>
                                            <p>Expired</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Opportunities Table -->
                            <div class="table-responsive">
                                <table class="table table-hover" id="opportunitiesTable">
                                    <thead>
                                        <tr>
                                            <th>Company</th>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Location</th>
                                            <th>Experience</th>
                                            <th>Deadline</th>
                                            <th>Status</th>
                                            <th>Featured</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sql = "
                                            SELECT * FROM opportunities
                                            ORDER BY featured DESC, created_at DESC
                                            LIMIT 100
                                        ";
                                        $opportunities = fetchAll($sql);

                                        foreach ($opportunities as $opp):
                                        ?>
                                        <tr data-opportunity-id="<?php echo $opp['id']; ?>">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($opp['logo_url'])): ?>
                                                        <img src="<?php echo SITE_URL . '/' . $opp['logo_url']; ?>"
                                                             alt="<?php echo htmlspecialchars($opp['company']); ?>"
                                                             style="width: 30px; height: 30px; object-fit: contain; margin-right: 10px; border-radius: 4px;">
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($opp['company']); ?></strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($opp['title']); ?></strong>
                                                    <?php if (!empty($opp['salary'])): ?>
                                                        <br><small class="text-success"><?php echo htmlspecialchars($opp['salary']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getOpportunityTypeColor($opp['type']); ?>">
                                                    <?php echo getOpportunityTypeName($opp['type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div>
                                                    <?php echo htmlspecialchars($opp['location']); ?>
                                                    <?php if ($opp['remote_work']): ?>
                                                        <br><small class="text-info"><i class="fas fa-home"></i> Remote</small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getExperienceLevelColor($opp['experience_level']); ?>">
                                                    <?php echo getExperienceLevelName($opp['experience_level']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($opp['application_deadline'])): ?>
                                                    <div class="<?php echo (strtotime($opp['application_deadline']) < time()) ? 'text-danger' : 'text-muted'; ?>">
                                                        <?php echo formatDate($opp['application_deadline']); ?>
                                                        <?php if (strtotime($opp['application_deadline']) < time()): ?>
                                                            <br><small>Expired</small>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">No deadline</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getOpportunityStatusColor($opp['status']); ?>">
                                                    <?php echo ucfirst($opp['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox"
                                                           <?php echo $opp['featured'] ? 'checked' : ''; ?>
                                                           onchange="toggleFeatured(<?php echo $opp['id']; ?>, <?php echo $opp['featured'] ? 0 : 1; ?>)">
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-info" onclick="viewOpportunity(<?php echo $opp['id']; ?>)" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if (!empty($opp['external_link'])): ?>
                                                        <button class="btn btn-sm btn-outline-success" onclick="window.open('<?php echo htmlspecialchars($opp['external_link']); ?>', '_blank')" title="External Link">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="editOpportunity(<?php echo $opp['id']; ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteOpportunity(<?php echo $opp['id']; ?>)" title="Delete">
                                                        <i class="fas fa-trash"></i>
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

<style>
/* Admin Opportunities Management Styles */
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

#logoPreview img {
    width: 100%;
    max-height: 100px;
    object-fit: contain;
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

// Logo preview
function previewLogo(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('logoPreview');

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="max-height: 100px;">`;
        }
        reader.readAsDataURL(file);
    }
}

// Search functionality
document.getElementById('searchOpportunities')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#opportunitiesTable tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Filter functionality
document.getElementById('filterType')?.addEventListener('change', filterOpportunities);
document.getElementById('filterCategory')?.addEventListener('change', filterOpportunities);
document.getElementById('filterStatus')?.addEventListener('change', filterOpportunities);

function filterOpportunities() {
    const typeFilter = document.getElementById('filterType').value;
    const categoryFilter = document.getElementById('filterCategory').value;
    const statusFilter = document.getElementById('filterStatus').value;
    const rows = document.querySelectorAll('#opportunitiesTable tbody tr');

    rows.forEach(row => {
        const typeCell = row.querySelector('td:nth-child(3) .badge');
        const categoryCell = row.querySelector('td:nth-child(4)');
        const statusCell = row.querySelector('td:nth-child(8) .badge');

        const typeMatch = !typeFilter || typeCell.textContent.toLowerCase().includes(typeFilter.toLowerCase());
        const categoryMatch = !categoryFilter || categoryCell.textContent.toLowerCase().includes(categoryFilter.toLowerCase());
        const statusMatch = !statusFilter || statusCell.textContent.toLowerCase() === statusFilter;

        row.style.display = typeMatch && categoryMatch && statusMatch ? '' : 'none';
    });
}

// CRUD operations
function viewOpportunity(opportunityId) {
    // In a real implementation, this would open a modal with opportunity details
    alert(`Viewing opportunity ${opportunityId} details`);
}

function editOpportunity(opportunityId) {
    window.location.href = `opportunities.php?action=edit&id=${opportunityId}`;
}

function deleteOpportunity(opportunityId) {
    if (confirm('Are you sure you want to delete this opportunity? This action cannot be undone.')) {
        window.location.href = `opportunities.php?action=delete&id=${opportunityId}`;
    }
}

function toggleFeatured(opportunityId, featured) {
    window.location.href = `opportunities.php?action=toggle_featured&id=${opportunityId}&featured=${featured}`;
}

function showBulkImport() {
    window.location.href = 'opportunities.php?action=bulk_import';
}

function exportOpportunities() {
    const typeFilter = document.getElementById('filterType')?.value || '';
    const categoryFilter = document.getElementById('filterCategory')?.value || '';
    const statusFilter = document.getElementById('filterStatus')?.value || '';

    let url = `../api/export.php?type=opportunities`;
    if (typeFilter) url += `&type=${typeFilter}`;
    if (categoryFilter) url += `&category=${categoryFilter}`;
    if (statusFilter) url += `&status=${statusFilter}`;

    window.location.href = url;
}

function downloadSampleCSV() {
    const csvContent = "Title,Description,Company,Location,Type,Experience Level,Salary,External Link\n" +
        "Sample Title,Sample description of the opportunity,Sample Company,Pune,India,Internship,Entry Level,₹10,000/month,https://example.com/apply\n" +
        "Frontend Developer,Looking for frontend developer with React experience,Tech Corp,Mumbai,India,Job,Junior,₹80,000/month,https://techcorp.com/careers\n";

    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sample_opportunities.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Helper functions
function getOpportunityTypeColor(type) {
    const colors = {
        'job' => 'primary',
        'internship' => 'success',
        'training' => 'info',
        'workshop' => 'warning',
        'competition' => 'danger',
        'freelance' => 'secondary',
        'other' => 'secondary'
    };
    return colors[type] || 'secondary';
}

function getExperienceLevelColor(level) {
    const colors = {
        'entry_level' => 'info',
        'junior' => 'success',
        'mid_level' => 'primary',
        'senior' => 'warning',
        'lead' => 'danger',
        'manager' => 'secondary',
        'executive' => 'secondary'
    };
    return colors[level] || 'secondary';
}

function getOpportunityStatusColor(status) {
    const colors = {
        'active' => 'success',
        'inactive' => 'secondary',
        'expired' => 'danger',
        'closed' => 'warning'
    };
    return colors[status] || 'secondary';
}

function getOpportunityTypeName(type) {
    const names = {
        'job' => 'Full-time Job',
        'internship' => 'Internship',
        'training' => 'Training',
        'workshop' => 'Workshop',
        'competition' => 'Competition',
        'freelance' => 'Freelance',
        'other' => 'Other'
    };
    return names[type] || type;
}

function getExperienceLevelName(level) {
    const names = {
        'entry_level' => 'Entry Level',
        'junior' => 'Junior',
        'mid_level' => 'Mid Level',
        'senior' => 'Senior',
        'lead' => 'Lead',
        'manager' => 'Manager',
        'executive' => 'Executive'
    };
    return names[level] || level;
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
function getOpportunityTypeColor($type) {
    $colors = [
        'job' => 'primary',
        'internship' => 'success',
        'training' => 'info',
        'workshop' => 'warning',
        'competition' => 'danger',
        'freelance' => 'secondary',
        'other' => 'secondary'
    ];
    return $colors[$type] ?? 'secondary';
}

function getExperienceLevelColor($level) {
    $colors = [
        'entry_level' => 'info',
        'junior' => 'success',
        'mid_level' => 'primary',
        'senior' => 'warning',
        'lead' => 'danger',
        'manager' => 'secondary',
        'executive' => 'secondary'
    ];
    return $colors[$level] ?? 'secondary';
}

function getOpportunityStatusColor($status) {
    $colors = [
        'active' => 'success',
        'inactive' => 'secondary',
        'expired' => 'danger',
        'closed' => 'warning'
    ];
    return $colors[$status] ?? 'secondary';
}

function getOpportunityTypeName($type) {
    $names = [
        'job' => 'Full-time Job',
        'internship' => 'Internship',
        'training' => 'Training',
        'workshop' => 'Workshop',
        'competition' => 'Competition',
        'freelance' => 'Freelance',
        'other' => 'Other'
    ];
    return $names[$type] ?? $type;
}

function getExperienceLevelName($level) {
    $names = [
        'entry_level' => 'Entry Level',
        'junior' => 'Junior',
        'mid_level' => 'Mid Level',
        'senior' => 'Senior',
        'lead' => 'Lead',
        'manager' => 'Manager',
        'executive' => 'Executive'
    ];
    return $names[$level] ?? $level;
}

include __DIR__ . '/../templates/admin_footer.php';
?>