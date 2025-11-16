<?php
// SPARK Platform - Admin Homepage Management
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireAdmin();

// Handle homepage operations
$action = $_GET['action'] ?? 'dashboard';

if ($action === 'save_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $hero_title = sanitize($_POST['hero_title']);
        $hero_subtitle = sanitize($_POST['hero_subtitle']);
        $hero_description = sanitize($_POST['hero_description']);
        $hero_button_text = sanitize($_POST['hero_button_text']);
        $hero_button_link = sanitize($_POST['hero_button_link']);
        $welcome_title = sanitize($_POST['welcome_title']);
        $welcome_content = sanitize($_POST['welcome_content']);
        $stats_students = (int)($_POST['stats_students'] ?? 0);
        $stats_events = (int)($_POST['stats_events'] ?? 0);
        $stats_projects = (int)($_POST['stats_projects'] ?? 0);
        $stats_certificates = (int)($_POST['stats_certificates'] ?? 0);
        $theme_color = sanitize($_POST['theme_color']);
        $font_family = sanitize($_POST['font_family']);

        // Handle hero background upload
        $hero_background = $_POST['existing_hero_background'] ?? '';
        if (isset($_FILES['hero_background']) && $_FILES['hero_background']['error'] === UPLOAD_ERR_OK) {
            $hero_background = uploadFile($_FILES['hero_background'], 'homepage');
        }

        // Update or insert homepage settings
        $sql = "INSERT INTO homepage_settings (
            hero_title, hero_subtitle, hero_description, hero_button_text, hero_button_link,
            hero_background, welcome_title, welcome_content, stats_students, stats_events,
            stats_projects, stats_certificates, theme_color, font_family, updated_by, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
        hero_title = VALUES(hero_title), hero_subtitle = VALUES(hero_subtitle),
        hero_description = VALUES(hero_description), hero_button_text = VALUES(hero_button_text),
        hero_button_link = VALUES(hero_button_link), hero_background = VALUES(hero_background),
        welcome_title = VALUES(welcome_title), welcome_content = VALUES(welcome_content),
        stats_students = VALUES(stats_students), stats_events = VALUES(stats_events),
        stats_projects = VALUES(stats_projects), stats_certificates = VALUES(stats_certificates),
        theme_color = VALUES(theme_color), font_family = VALUES(font_family),
        updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)";

        $params = [
            $hero_title, $hero_subtitle, $hero_description, $hero_button_text, $hero_button_link,
            $hero_background, $welcome_title, $welcome_content, $stats_students, $stats_events,
            $stats_projects, $stats_certificates, $theme_color, $font_family, $_SESSION['user_id']
        ];

        executeUpdate($sql, $params);

        // Log activity
        logActivity('homepage_settings_updated', "Homepage settings updated", $_SESSION['user_id']);

        $_SESSION['success'][] = "Homepage settings saved successfully!";
        header('Location: homepage.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: homepage.php?action=settings');
        exit();
    }
}

if ($action === 'create_banner' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $link = sanitize($_POST['link']);
        $link_text = sanitize($_POST['link_text']);
        $status = sanitize($_POST['status'] ?? 'active');
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $order_index = (int)($_POST['order_index'] ?? 0);

        // Validation
        if (empty($title)) {
            throw new Exception("Banner title is required");
        }

        // Handle banner image upload
        $image_url = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image_url = uploadFile($_FILES['image'], 'banners');
        }

        // Insert banner
        $sql = "INSERT INTO banners (
            title, description, image_url, link, link_text, status,
            start_date, end_date, order_index, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $params = [
            $title, $description, $image_url, $link, $link_text, $status,
            $start_date, $end_date, $order_index, $_SESSION['user_id']
        ];

        $banner_id = executeInsert($sql, $params);

        // Log activity
        logActivity('banner_created', "Banner '{$title}' created", $_SESSION['user_id'], $banner_id);

        $_SESSION['success'][] = "Banner created successfully!";
        header('Location: homepage.php?action=banners');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        $_SESSION['form_data'] = $_POST;
        header('Location: homepage.php?action=create_banner');
        exit();
    }
}

if ($action === 'update_banner' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $banner_id = (int)$_POST['banner_id'];
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $link = sanitize($_POST['link']);
        $link_text = sanitize($_POST['link_text']);
        $status = sanitize($_POST['status'] ?? 'active');
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $order_index = (int)($_POST['order_index'] ?? 0);

        // Validation
        if (empty($title)) {
            throw new Exception("Banner title is required");
        }

        // Handle banner image upload
        $image_url = $_POST['existing_image'] ?? '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image_url = uploadFile($_FILES['image'], 'banners');
        }

        // Update banner
        $sql = "UPDATE banners SET
            title = ?, description = ?, image_url = ?, link = ?, link_text = ?, status = ?,
            start_date = ?, end_date = ?, order_index = ?, updated_at = NOW()
        WHERE id = ?";

        $params = [
            $title, $description, $image_url, $link, $link_text, $status,
            $start_date, $end_date, $order_index, $banner_id
        ];

        executeUpdate($sql, $params);

        // Log activity
        logActivity('banner_updated', "Banner '{$title}' updated", $_SESSION['user_id'], $banner_id);

        $_SESSION['success'][] = "Banner updated successfully!";
        header('Location: homepage.php?action=banners');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: homepage.php?action=edit_banner&id=' . $_POST['banner_id']);
        exit();
    }
}

if ($action === 'delete_banner') {
    try {
        $banner_id = (int)$_GET['id'];

        // Get banner info for logging and cleanup
        $banner = fetchRow("SELECT title, image_url FROM banners WHERE id = ?", [$banner_id]);
        if (!$banner) {
            throw new Exception("Banner not found");
        }

        // Delete banner image if exists
        if (!empty($banner['image_url']) && file_exists(__DIR__ . '/../' . $banner['image_url'])) {
            unlink(__DIR__ . '/../' . $banner['image_url']);
        }

        // Delete banner
        executeUpdate("DELETE FROM banners WHERE id = ?", [$banner_id]);

        // Log activity
        logActivity('banner_deleted', "Banner '{$banner['title']}' deleted", $_SESSION['user_id'], $banner_id);

        $_SESSION['success'][] = "Banner deleted successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: homepage.php?action=banners');
    exit();
}

if ($action === 'create_announcement' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = sanitize($_POST['title']);
        $content = sanitize($_POST['content']);
        $type = sanitize($_POST['type']);
        $priority = sanitize($_POST['priority']);
        $link = sanitize($_POST['link'] ?? '');
        $link_text = sanitize($_POST['link_text'] ?? '');
        $status = sanitize($_POST['status'] ?? 'active');
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];

        // Validation
        if (empty($title) || empty($content)) {
            throw new Exception("Title and content are required");
        }

        // Insert announcement
        $sql = "INSERT INTO announcements (
            title, content, type, priority, link, link_text, status,
            start_date, end_date, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $params = [
            $title, $content, $type, $priority, $link, $link_text, $status,
            $start_date, $end_date, $_SESSION['user_id']
        ];

        $announcement_id = executeInsert($sql, $params);

        // Log activity
        logActivity('announcement_created', "Announcement '{$title}' created", $_SESSION['user_id'], $announcement_id);

        $_SESSION['success'][] = "Announcement created successfully!";
        header('Location: homepage.php?action=announcements');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        $_SESSION['form_data'] = $_POST;
        header('Location: homepage.php?action=create_announcement');
        exit();
    }
}

if ($action === 'update_announcement' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $announcement_id = (int)$_POST['announcement_id'];
        $title = sanitize($_POST['title']);
        $content = sanitize($_POST['content']);
        $type = sanitize($_POST['type']);
        $priority = sanitize($_POST['priority']);
        $link = sanitize($_POST['link'] ?? '');
        $link_text = sanitize($_POST['link_text'] ?? '');
        $status = sanitize($_POST['status'] ?? 'active');
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];

        // Validation
        if (empty($title) || empty($content)) {
            throw new Exception("Title and content are required");
        }

        // Update announcement
        $sql = "UPDATE announcements SET
            title = ?, content = ?, type = ?, priority = ?, link = ?, link_text = ?, status = ?,
            start_date = ?, end_date = ?, updated_at = NOW()
        WHERE id = ?";

        $params = [
            $title, $content, $type, $priority, $link, $link_text, $status,
            $start_date, $end_date, $announcement_id
        ];

        executeUpdate($sql, $params);

        // Log activity
        logActivity('announcement_updated', "Announcement '{$title}' updated", $_SESSION['user_id'], $announcement_id);

        $_SESSION['success'][] = "Announcement updated successfully!";
        header('Location: homepage.php?action=announcements');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: homepage.php?action=edit_announcement&id=' . $_POST['announcement_id']);
        exit();
    }
}

if ($action === 'delete_announcement') {
    try {
        $announcement_id = (int)$_GET['id'];

        // Get announcement info for logging
        $announcement = fetchRow("SELECT title FROM announcements WHERE id = ?", [$announcement_id]);
        if (!$announcement) {
            throw new Exception("Announcement not found");
        }

        // Delete announcement
        executeUpdate("DELETE FROM announcements WHERE id = ?", [$announcement_id]);

        // Log activity
        logActivity('announcement_deleted', "Announcement '{$announcement['title']}' deleted", $_SESSION['user_id'], $announcement_id);

        $_SESSION['success'][] = "Announcement deleted successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: homepage.php?action=announcements');
    exit();
}

$page_title = 'Homepage Management';
$breadcrumb = [
    ['name' => 'Dashboard', 'link' => 'index.php', 'active' => false],
    ['name' => 'Homepage', 'link' => '', 'active' => true]
];

// Get current homepage settings
$homepage_settings = fetchRow("SELECT * FROM homepage_settings ORDER BY id DESC LIMIT 1");

include __DIR__ . '/../templates/admin_header.php';
?>

<!-- Homepage Management Section -->
<section class="admin-section py-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="section-title">
                            <i class="fas fa-home me-2"></i> Homepage Management
                        </h2>
                        <p class="text-muted">Manage homepage content, banners, and announcements</p>
                    </div>
                </div>

                <?php include __DIR__ . '/../includes/alerts.php'; ?>

                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs mb-4" id="homepageTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $action === 'dashboard' ? 'active' : ''; ?>" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab">
                            <i class="fas fa-tachometer-alt me-2"></i> Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $action === 'settings' ? 'active' : ''; ?>" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">
                            <i class="fas fa-cog me-2"></i> Settings
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo in_array($action, ['banners', 'create_banner', 'edit_banner']) ? 'active' : ''; ?>" id="banners-tab" data-bs-toggle="tab" data-bs-target="#banners" type="button" role="tab">
                            <i class="fas fa-image me-2"></i> Banners
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo in_array($action, ['announcements', 'create_announcement', 'edit_announcement']) ? 'active' : ''; ?>" id="announcements-tab" data-bs-toggle="tab" data-bs-target="#announcements" type="button" role="tab">
                            <i class="fas fa-bullhorn me-2"></i> Announcements
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="homepageTabContent">
                    <!-- Dashboard Tab -->
                    <div class="tab-pane fade <?php echo $action === 'dashboard' ? 'show active' : ''; ?>" id="dashboard" role="tabpanel">
                        <div class="card admin-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-line me-2"></i> Homepage Overview
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Statistics -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="stat-card">
                                            <div class="stat-icon bg-primary">
                                                <i class="fas fa-image"></i>
                                            </div>
                                            <div class="stat-content">
                                                <h3><?php echo fetchColumn("SELECT COUNT(*) FROM banners WHERE status = 'active'", []); ?></h3>
                                                <p>Active Banners</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card">
                                            <div class="stat-icon bg-info">
                                                <i class="fas fa-bullhorn"></i>
                                            </div>
                                            <div class="stat-content">
                                                <h3><?php echo fetchColumn("SELECT COUNT(*) FROM announcements WHERE status = 'active'", []); ?></h3>
                                                <p>Active Announcements</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card">
                                            <div class="stat-icon bg-success">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <div class="stat-content">
                                                <h3><?php echo fetchColumn("SELECT COUNT(*) FROM banners WHERE start_date <= CURDATE() AND end_date >= CURDATE()", []); ?></h3>
                                                <p>Current Banners</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stat-card">
                                            <div class="stat-icon bg-warning">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </div>
                                            <div class="stat-content">
                                                <h3><?php echo fetchColumn("SELECT COUNT(*) FROM announcements WHERE priority = 'high' AND status = 'active'", []); ?></h3>
                                                <p>High Priority</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quick Actions -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="text-primary mb-3">Quick Actions</h6>
                                                <div class="d-grid gap-2">
                                                    <a href="homepage.php?action=settings" class="btn btn-outline-primary">
                                                        <i class="fas fa-cog me-2"></i> Update Homepage Settings
                                                    </a>
                                                    <a href="homepage.php?action=create_banner" class="btn btn-outline-success">
                                                        <i class="fas fa-plus me-2"></i> Add New Banner
                                                    </a>
                                                    <a href="homepage.php?action=create_announcement" class="btn btn-outline-warning">
                                                        <i class="fas fa-bullhorn me-2"></i> Create Announcement
                                                    </a>
                                                    <button class="btn btn-outline-info" onclick="previewHomepage()">
                                                        <i class="fas fa-eye me-2"></i> Preview Homepage
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="text-primary mb-3">Recent Updates</h6>
                                                <div class="recent-updates">
                                                    <?php
                                                    $recent_activity = fetchAll("
                                                        SELECT * FROM activity_logs
                                                        WHERE action IN ('homepage_settings_updated', 'banner_created', 'banner_updated', 'announcement_created', 'announcement_updated')
                                                        ORDER BY created_at DESC LIMIT 5
                                                    ");

                                                    if ($recent_activity):
                                                        foreach ($recent_activity as $activity):
                                                    ?>
                                                    <div class="update-item">
                                                        <div class="update-text"><?php echo htmlspecialchars($activity['description']); ?></div>
                                                        <div class="update-time small text-muted"><?php echo formatTimeAgo($activity['created_at']); ?></div>
                                                    </div>
                                                    <?php endforeach;
                                                    else:
                                                    ?>
                                                    <p class="text-muted">No recent updates</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Settings Tab -->
                    <div class="tab-pane fade <?php echo $action === 'settings' ? 'show active' : ''; ?>" id="settings" role="tabpanel">
                        <div class="card admin-card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-cog me-2"></i> Homepage Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="homepage.php?action=save_settings" enctype="multipart/form-data" class="needs-validation" novalidate>
                                    <div class="row">
                                        <!-- Hero Section -->
                                        <div class="col-md-6">
                                            <h6 class="text-primary mb-3">Hero Section</h6>

                                            <div class="mb-3">
                                                <label for="hero_title" class="form-label">Hero Title</label>
                                                <input type="text" class="form-control" id="hero_title" name="hero_title"
                                                       value="<?php echo htmlspecialchars($homepage_settings['hero_title'] ?? 'SPARK Platform'); ?>"
                                                       maxlength="255" placeholder="Welcome to SPARK Platform">
                                            </div>

                                            <div class="mb-3">
                                                <label for="hero_subtitle" class="form-label">Hero Subtitle</label>
                                                <input type="text" class="form-control" id="hero_subtitle" name="hero_subtitle"
                                                       value="<?php echo htmlspecialchars($homepage_settings['hero_subtitle'] ?? 'Sanjivani Platform for AI, Research & Knowledge'); ?>"
                                                       maxlength="255" placeholder="Platform subtitle">
                                            </div>

                                            <div class="mb-3">
                                                <label for="hero_description" class="form-label">Hero Description</label>
                                                <textarea class="form-control" id="hero_description" name="hero_description"
                                                          rows="3"><?php echo htmlspecialchars($homepage_settings['hero_description'] ?? 'Empowering innovation through technology and research'); ?></textarea>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="hero_button_text" class="form-label">Button Text</label>
                                                        <input type="text" class="form-control" id="hero_button_text" name="hero_button_text"
                                                               value="<?php echo htmlspecialchars($homepage_settings['hero_button_text'] ?? 'Get Started'); ?>"
                                                               maxlength="100">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="hero_button_link" class="form-label">Button Link</label>
                                                        <input type="url" class="form-control" id="hero_button_link" name="hero_button_link"
                                                               value="<?php echo htmlspecialchars($homepage_settings['hero_button_link'] ?? '/student/register.php'); ?>"
                                                               placeholder="/student/register.php">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label for="hero_background" class="form-label">Hero Background</label>
                                                <input type="file" class="form-control" id="hero_background" name="hero_background"
                                                       accept="image/*" onchange="previewHeroBackground(event)">
                                                <?php if (!empty($homepage_settings['hero_background'])): ?>
                                                    <input type="hidden" name="existing_hero_background" value="<?php echo htmlspecialchars($homepage_settings['hero_background']); ?>">
                                                    <div class="mt-2">
                                                        <small class="text-muted">Current background:</small><br>
                                                        <img src="<?php echo SITE_URL . '/' . $homepage_settings['hero_background']; ?>"
                                                             alt="Current background" style="max-width: 200px; max-height: 100px; object-fit: cover; border-radius: 8px;">
                                                    </div>
                                                <?php endif; ?>
                                                <div id="heroBackgroundPreview" class="mt-2"></div>
                                            </div>
                                        </div>

                                        <!-- Statistics and Welcome -->
                                        <div class="col-md-6">
                                            <h6 class="text-primary mb-3">Statistics Section</h6>

                                            <div class="row">
                                                <div class="col-6">
                                                    <div class="mb-3">
                                                        <label for="stats_students" class="form-label">Students Count</label>
                                                        <input type="number" class="form-control" id="stats_students" name="stats_students"
                                                               value="<?php echo htmlspecialchars($homepage_settings['stats_students'] ?? 500); ?>" min="0">
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="mb-3">
                                                        <label for="stats_events" class="form-label">Events Count</label>
                                                        <input type="number" class="form-control" id="stats_events" name="stats_events"
                                                               value="<?php echo htmlspecialchars($homepage_settings['stats_events'] ?? 50); ?>" min="0">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-6">
                                                    <div class="mb-3">
                                                        <label for="stats_projects" class="form-label">Projects Count</label>
                                                        <input type="number" class="form-control" id="stats_projects" name="stats_projects"
                                                               value="<?php echo htmlspecialchars($homepage_settings['stats_projects'] ?? 100); ?>" min="0">
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="mb-3">
                                                        <label for="stats_certificates" class="form-label">Certificates Count</label>
                                                        <input type="number" class="form-control" id="stats_certificates" name="stats_certificates"
                                                               value="<?php echo htmlspecialchars($homepage_settings['stats_certificates'] ?? 200); ?>" min="0">
                                                    </div>
                                                </div>
                                            </div>

                                            <h6 class="text-primary mb-3 mt-4">Welcome Section</h6>

                                            <div class="mb-3">
                                                <label for="welcome_title" class="form-label">Welcome Title</label>
                                                <input type="text" class="form-control" id="welcome_title" name="welcome_title"
                                                       value="<?php echo htmlspecialchars($homepage_settings['welcome_title'] ?? 'Welcome to SPARK'); ?>"
                                                       maxlength="255">
                                            </div>

                                            <div class="mb-3">
                                                <label for="welcome_content" class="form-label">Welcome Content</label>
                                                <textarea class="form-control" id="welcome_content" name="welcome_content"
                                                          rows="4"><?php echo htmlspecialchars($homepage_settings['welcome_content'] ?? 'Discover a world of innovation, learning, and growth opportunities.'); ?></textarea>
                                            </div>

                                            <h6 class="text-primary mb-3 mt-4">Theme Settings</h6>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="theme_color" class="form-label">Theme Color</label>
                                                        <select class="form-select" id="theme_color" name="theme_color">
                                                            <option value="#00ff88" <?php echo ($homepage_settings['theme_color'] ?? '#00ff88') === '#00ff88' ? 'selected' : ''; ?>>Neon Green</option>
                                                            <option value="#0080ff" <?php echo ($homepage_settings['theme_color'] ?? '') === '#0080ff' ? 'selected' : ''; ?>>Neon Blue</option>
                                                            <option value="#ff0080" <?php echo ($homepage_settings['theme_color'] ?? '') === '#ff0080' ? 'selected' : ''; ?>>Neon Pink</option>
                                                            <option value="#ff8000" <?php echo ($homepage_settings['theme_color'] ?? '') === '#ff8000' ? 'selected' : ''; ?>>Neon Orange</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="font_family" class="form-label">Font Family</label>
                                                        <select class="form-select" id="font_family" name="font_family">
                                                            <option value="Inter" <?php echo ($homepage_settings['font_family'] ?? 'Inter') === 'Inter' ? 'selected' : ''; ?>>Inter</option>
                                                            <option value="Poppins" <?php echo ($homepage_settings['font_family'] ?? '') === 'Poppins' ? 'selected' : ''; ?>>Poppins</option>
                                                            <option value="Roboto" <?php echo ($homepage_settings['font_family'] ?? '') === 'Roboto' ? 'selected' : ''; ?>>Roboto</option>
                                                            <option value="Open Sans" <?php echo ($homepage_settings['font_family'] ?? '') === 'Open Sans' ? 'selected' : ''; ?>>Open Sans</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between mt-4">
                                        <button type="button" class="btn btn-outline-info" onclick="previewHomepage()">
                                            <i class="fas fa-eye me-2"></i> Preview Changes
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Save Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Banners Tab -->
                    <div class="tab-pane fade <?php echo in_array($action, ['banners', 'create_banner', 'edit_banner']) ? 'show active' : ''; ?>" id="banners" role="tabpanel">
                        <?php if (in_array($action, ['create_banner', 'edit_banner'])): ?>
                            <!-- Create/Edit Banner Form -->
                            <?php include __DIR__ . '/../includes/admin_banner_form.php'; ?>
                        <?php else: ?>
                            <!-- Banners List -->
                            <div class="card admin-card">
                                <div class="card-header">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <h5 class="card-title mb-0">
                                                <i class="fas fa-list me-2"></i> Homepage Banners
                                            </h5>
                                        </div>
                                        <div class="col-auto">
                                            <a href="homepage.php?action=create_banner" class="btn btn-primary">
                                                <i class="fas fa-plus me-2"></i> Add Banner
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Preview</th>
                                                    <th>Title</th>
                                                    <th>Link</th>
                                                    <th>Dates</th>
                                                    <th>Order</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $banners = fetchAll("SELECT * FROM banners ORDER BY order_index ASC, created_at DESC");
                                                foreach ($banners as $banner):
                                                ?>
                                                <tr>
                                                    <td>
                                                        <?php if (!empty($banner['image_url'])): ?>
                                                            <img src="<?php echo SITE_URL . '/' . $banner['image_url']; ?>"
                                                                 alt="<?php echo htmlspecialchars($banner['title']); ?>"
                                                                 style="width: 80px; height: 50px; object-fit: cover; border-radius: 4px;">
                                                        <?php else: ?>
                                                            <div style="width: 80px; height: 50px; background: var(--glass-bg); border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                                                <i class="fas fa-image text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($banner['title']); ?></strong>
                                                            <?php if (!empty($banner['description'])): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($banner['description'], 0, 100)) . '...'; ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($banner['link'])): ?>
                                                            <a href="<?php echo htmlspecialchars($banner['link']); ?>" target="_blank" class="text-primary small">
                                                                <?php echo htmlspecialchars($banner['link_text'] ?? 'Visit'); ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted small">No link</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <?php echo formatDate($banner['start_date']); ?>
                                                            <?php if ($banner['end_date']): ?>
                                                                - <?php echo formatDate($banner['end_date']); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td><?php echo $banner['order_index']; ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $banner['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                            <?php echo ucfirst($banner['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-sm btn-outline-primary" onclick="editBanner(<?php echo $banner['id']; ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteBanner(<?php echo $banner['id']; ?>)">
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

                    <!-- Announcements Tab -->
                    <div class="tab-pane fade <?php echo in_array($action, ['announcements', 'create_announcement', 'edit_announcement']) ? 'show active' : ''; ?>" id="announcements" role="tabpanel">
                        <?php if (in_array($action, ['create_announcement', 'edit_announcement'])): ?>
                            <!-- Create/Edit Announcement Form -->
                            <?php include __DIR__ . '/../includes/admin_announcement_form.php'; ?>
                        <?php else: ?>
                            <!-- Announcements List -->
                            <div class="card admin-card">
                                <div class="card-header">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <h5 class="card-title mb-0">
                                                <i class="fas fa-list me-2"></i> Announcements
                                            </h5>
                                        </div>
                                        <div class="col-auto">
                                            <a href="homepage.php?action=create_announcement" class="btn btn-primary">
                                                <i class="fas fa-plus me-2"></i> Create Announcement
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Title</th>
                                                    <th>Type</th>
                                                    <th>Priority</th>
                                                    <th>Dates</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $announcements = fetchAll("SELECT * FROM announcements ORDER BY priority DESC, created_at DESC");
                                                foreach ($announcements as $announcement):
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($announcement['title']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($announcement['content'], 0, 120)) . '...'; ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getAnnouncementTypeColor($announcement['type']); ?>">
                                                            <?php echo ucfirst($announcement['type']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getAnnouncementPriorityColor($announcement['priority']); ?>">
                                                            <?php echo ucfirst($announcement['priority']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <?php echo formatDate($announcement['start_date']); ?>
                                                            <?php if ($announcement['end_date']): ?>
                                                                - <?php echo formatDate($announcement['end_date']); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $announcement['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                            <?php echo ucfirst($announcement['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-sm btn-outline-primary" onclick="editAnnouncement(<?php echo $announcement['id']; ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteAnnouncement(<?php echo $announcement['id']; ?>)">
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
        </div>
    </div>
</section>

<style>
/* Admin Homepage Management Styles */
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

.recent-updates {
    max-height: 200px;
    overflow-y: auto;
}

.update-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border-color);
}

.update-item:last-child {
    border-bottom: none;
}

.update-text {
    font-size: 0.875rem;
    color: var(--text-primary);
}

.update-time {
    font-size: 0.75rem;
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

#heroBackgroundPreview img {
    max-width: 300px;
    max-height: 150px;
    border-radius: 8px;
    border: 2px solid var(--border-color);
}

/* Tab styling */
.nav-tabs .nav-link {
    color: var(--text-muted);
    border-color: var(--border-color);
}

.nav-tabs .nav-link.active {
    color: var(--accent-color);
    background-color: var(--card-bg);
    border-color: var(--accent-color);
}

.nav-tabs .nav-link:hover {
    color: var(--accent-color);
    border-color: var(--accent-color);
}

.tab-content {
    background: var(--card-bg);
    border-radius: 0 12px 12px 12px;
    padding: 1.5rem;
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

// Hero background preview
function previewHeroBackground(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('heroBackgroundPreview');

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="max-width: 300px; border-radius: 8px;">`;
        }
        reader.readAsDataURL(file);
    }
}

// CRUD operations
function editBanner(bannerId) {
    window.location.href = `homepage.php?action=edit_banner&id=${bannerId}`;
}

function deleteBanner(bannerId) {
    if (confirm('Are you sure you want to delete this banner? This action cannot be undone.')) {
        window.location.href = `homepage.php?action=delete_banner&id=${bannerId}`;
    }
}

function editAnnouncement(announcementId) {
    window.location.href = `homepage.php?action=edit_announcement&id=${announcementId}`;
}

function deleteAnnouncement(announcementId) {
    if (confirm('Are you sure you want to delete this announcement? This action cannot be undone.')) {
        window.location.href = `homepage.php?action=delete_announcement&id=${announcementId}`;
    }
}

function previewHomepage() {
    window.open('<?php echo SITE_URL; ?>/student/index.php', '_blank');
}

// Helper functions
function getAnnouncementTypeColor(type) {
    const colors = {
        'info' => 'info',
        'warning' => 'warning',
        'success' => 'success',
        'danger' => 'danger',
        'update' => 'primary'
    };
    return colors[type] || 'secondary';
}

function getAnnouncementPriorityColor(priority) {
    const colors = {
        'low' => 'secondary',
        'medium' => 'info',
        'high' => 'warning',
        'urgent' => 'danger'
    };
    return colors[priority] || 'secondary';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

function formatTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 60) return `${diffMins} minutes ago`;
    if (diffHours < 24) return `${diffHours} hours ago`;
    return `${diffDays} days ago`;
}
</script>

<?php
// Helper functions
function getAnnouncementTypeColor($type) {
    $colors = [
        'info' => 'info',
        'warning' => 'warning',
        'success' => 'success',
        'danger' => 'danger',
        'update' => 'primary'
    ];
    return $colors[$type] ?? 'secondary';
}

function getAnnouncementPriorityColor($priority) {
    $colors = [
        'low' => 'secondary',
        'medium' => 'info',
        'high' => 'warning',
        'urgent' => 'danger'
    ];
    return $colors[$priority] ?? 'secondary';
}

function formatTimeAgo($dateString) {
    $date = new DateTime($dateString);
    $now = new DateTime();
    $diff = $now->getTimestamp() - $date->getTimestamp();
    $diffMins = floor($diff / 60);
    $diffHours = floor($diff / 3600);
    $diffDays = floor($diff / 86400);

    if ($diffMins < 60) return "$diffMins minutes ago";
    if ($diffHours < 24) return "$diffHours hours ago";
    return "$diffDays days ago";
}

include __DIR__ . '/../templates/admin_footer.php';
?>