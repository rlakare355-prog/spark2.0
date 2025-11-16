<?php
// SPARK Platform - Admin Gallery Management
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireAdmin();

// Handle gallery operations
$action = $_GET['action'] ?? 'list';

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $category = sanitize($_POST['category']);
        $tags = sanitize($_POST['tags']);
        $status = sanitize($_POST['status'] ?? 'active');
        $featured = isset($_POST['featured']) ? 1 : 0;
        $order_index = (int)($_POST['order_index'] ?? 0);

        // Validation
        if (empty($title)) {
            throw new Exception("Gallery title is required");
        }

        // Handle image upload
        $image_url = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image_url = uploadFile($_FILES['image'], 'gallery');
        } else {
            throw new Exception("Image upload is required");
        }

        // Insert gallery item
        $sql = "INSERT INTO gallery (
            title, description, category, tags, image_url, status, featured,
            order_index, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $params = [
            $title, $description, $category, $tags, $image_url, $status, $featured,
            $order_index, $_SESSION['user_id']
        ];

        $gallery_id = executeInsert($sql, $params);

        // Generate thumbnail
        if ($image_url) {
            generateThumbnail($image_url, 'gallery');
        }

        // Log activity
        logActivity('gallery_item_created', "Gallery item '{$title}' added", $_SESSION['user_id'], $gallery_id);

        $_SESSION['success'][] = "Gallery item added successfully!";
        header('Location: gallery.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        $_SESSION['form_data'] = $_POST;
        header('Location: gallery.php?action=create');
        exit();
    }
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $gallery_id = (int)$_POST['gallery_id'];
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $category = sanitize($_POST['category']);
        $tags = sanitize($_POST['tags']);
        $status = sanitize($_POST['status'] ?? 'active');
        $featured = isset($_POST['featured']) ? 1 : 0;
        $order_index = (int)($_POST['order_index'] ?? 0);

        // Validation
        if (empty($title)) {
            throw new Exception("Gallery title is required");
        }

        // Handle image upload
        $image_url = $_POST['existing_image'] ?? '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image_url = uploadFile($_FILES['image'], 'gallery');
        }

        // Update gallery item
        $sql = "UPDATE gallery SET
            title = ?, description = ?, category = ?, tags = ?, image_url = ?,
            status = ?, featured = ?, order_index = ?, updated_at = NOW()
        WHERE id = ?";

        $params = [
            $title, $description, $category, $tags, $image_url, $status, $featured,
            $order_index, $gallery_id
        ];

        executeUpdate($sql, $params);

        // Generate thumbnail if new image
        if ($image_url && $image_url !== $_POST['existing_image']) {
            generateThumbnail($image_url, 'gallery');
        }

        // Log activity
        logActivity('gallery_item_updated', "Gallery item '{$title}' updated", $_SESSION['user_id'], $gallery_id);

        $_SESSION['success'][] = "Gallery item updated successfully!";
        header('Location: gallery.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: gallery.php?action=edit&id=' . $_POST['gallery_id']);
        exit();
    }
}

if ($action === 'delete') {
    try {
        $gallery_id = (int)$_GET['id'];

        // Get gallery info for logging and cleanup
        $item = fetchRow("SELECT title, image_url FROM gallery WHERE id = ?", [$gallery_id]);
        if (!$item) {
            throw new Exception("Gallery item not found");
        }

        // Delete image and thumbnail if exist
        if (!empty($item['image_url'])) {
            $image_path = __DIR__ . '/../' . $item['image_url'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }

            // Delete thumbnail
            $thumbnail_path = str_replace('/gallery/', '/gallery/thumbnails/', $image_path);
            if (file_exists($thumbnail_path)) {
                unlink($thumbnail_path);
            }
        }

        // Delete gallery item
        executeUpdate("DELETE FROM gallery WHERE id = ?", [$gallery_id]);

        // Log activity
        logActivity('gallery_item_deleted', "Gallery item '{$item['title']}' deleted", $_SESSION['user_id'], $gallery_id);

        $_SESSION['success'][] = "Gallery item deleted successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: gallery.php');
    exit();
}

if ($action === 'bulk_upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $category = sanitize($_POST['category']);
        $status = sanitize($_POST['status'] ?? 'active');
        $featured = isset($_POST['featured']) ? 1 : 0;

        if (empty($category)) {
            throw new Exception("Category is required");
        }

        $success_count = 0;
        $error_count = 0;

        // Handle multiple file uploads
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $files = $_FILES['images'];

            foreach ($files['name'] as $key => $name) {
                if ($files['error'][$key] === UPLOAD_ERR_OK) {
                    try {
                        // Create file array for single upload
                        $file = [
                            'name' => $files['name'][$key],
                            'type' => $files['type'][$key],
                            'tmp_name' => $files['tmp_name'][$key],
                            'error' => $files['error'][$key],
                            'size' => $files['size'][$key]
                        ];

                        // Upload file
                        $image_url = uploadFile($file, 'gallery');

                        // Generate title from filename
                        $title = pathinfo($name, PATHINFO_FILENAME);
                        $title = ucwords(str_replace(['_', '-'], ' ', $title));

                        // Insert gallery item
                        $sql = "INSERT INTO gallery (
                            title, description, category, image_url, status, featured,
                            created_by, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

                        executeInsert($sql, [
                            $title, '', $category, $image_url, $status, $featured,
                            $_SESSION['user_id']
                        ]);

                        // Generate thumbnail
                        generateThumbnail($image_url, 'gallery');

                        $success_count++;

                    } catch (Exception $e) {
                        $error_count++;
                    }
                }
            }
        } else {
            throw new Exception("No files were uploaded");
        }

        $_SESSION['success'][] = "Bulk upload completed: {$success_count} successful, {$error_count} failed";
        header('Location: gallery.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: gallery.php?action=bulk_upload');
        exit();
    }
}

if ($action === 'toggle_featured') {
    try {
        $gallery_id = (int)$_GET['id'];
        $featured = (int)$_GET['featured'];

        executeUpdate("UPDATE gallery SET featured = ? WHERE id = ?", [$featured, $gallery_id]);

        $action_text = $featured ? 'featured' : 'unfeatured';
        $_SESSION['success'][] = "Gallery item {$action_text} successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: gallery.php');
    exit();
}

$page_title = 'Gallery Management';
$breadcrumb = [
    ['name' => 'Dashboard', 'link' => 'index.php', 'active' => false],
    ['name' => 'Gallery', 'link' => '', 'active' => true]
];

include __DIR__ . '/../templates/admin_header.php';
?>

<!-- Gallery Management Section -->
<section class="admin-section py-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="section-title">
                            <i class="fas fa-images me-2"></i> Gallery Management
                        </h2>
                        <p class="text-muted">Manage photo gallery with categories and featured items</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-warning" onclick="showBulkUpload()">
                            <i class="fas fa-cloud-upload-alt me-2"></i> Bulk Upload
                        </button>
                        <a href="gallery.php?action=create" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Add Image
                        </a>
                    </div>
                </div>

                <?php include __DIR__ . '/../includes/alerts.php'; ?>

                <?php if ($action === 'create' || $action === 'edit'): ?>
                    <!-- Create/Edit Gallery Item Form -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-<?php echo $action === 'create' ? 'plus' : 'edit'; ?> me-2"></i>
                                <?php echo $action === 'create' ? 'Add Gallery Image' : 'Edit Gallery Image'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $gallery_data = $_SESSION['form_data'] ?? [];
                            if ($action === 'edit') {
                                $gallery_id = (int)$_GET['id'];
                                $gallery_data = fetchRow("SELECT * FROM gallery WHERE id = ?", [$gallery_id]);
                                if (!$gallery_data) {
                                    $_SESSION['errors'][] = "Gallery item not found";
                                    header('Location: gallery.php');
                                    exit();
                                }
                            }
                            ?>

                            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="gallery_id" value="<?php echo $gallery_data['id']; ?>">
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="title" class="form-label">Title *</label>
                                                    <input type="text" class="form-control" id="title" name="title"
                                                           value="<?php echo htmlspecialchars($gallery_data['title'] ?? ''); ?>"
                                                           required maxlength="255" placeholder="Enter image title">
                                                    <div class="invalid-feedback">Please provide a title</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="category" class="form-label">Category *</label>
                                                    <select class="form-select" id="category" name="category" required>
                                                        <option value="">Select Category</option>
                                                        <option value="events" <?php echo ($gallery_data['category'] ?? '') === 'events' ? 'selected' : ''; ?>>Events</option>
                                                        <option value="workshops" <?php echo ($gallery_data['category'] ?? '') === 'workshops' ? 'selected' : ''; ?>>Workshops</option>
                                                        <option value="competitions" <?php echo ($gallery_data['category'] ?? '') === 'competitions' ? 'selected' : ''; ?>>Competitions</option>
                                                        <option value="seminars" <?php echo ($gallery_data['category'] ?? '') === 'seminars' ? 'selected' : ''; ?>>Seminars</option>
                                                        <option value="infrastructure" <?php echo ($gallery_data['category'] ?? '') === 'infrastructure' ? 'selected' : ''; ?>>Infrastructure</option>
                                                        <option value="achievements" <?php echo ($gallery_data['category'] ?? '') === 'achievements' ? 'selected' : ''; ?>>Achievements</option>
                                                        <option value="activities" <?php echo ($gallery_data['category'] ?? '') === 'activities' ? 'selected' : ''; ?>>Activities</option>
                                                        <option value="cultural" <?php echo ($gallery_data['category'] ?? '') === 'cultural' ? 'selected' : ''; ?>>Cultural</option>
                                                        <option value="sports" <?php echo ($gallery_data['category'] ?? '') === 'sports' ? 'selected' : ''; ?>>Sports</option>
                                                        <option value="other" <?php echo ($gallery_data['category'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                                    </select>
                                                    <div class="invalid-feedback">Please select a category</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control" id="description" name="description"
                                                      rows="4" placeholder="Image description..."><?php echo htmlspecialchars($gallery_data['description'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label for="tags" class="form-label">Tags</label>
                                            <input type="text" class="form-control" id="tags" name="tags"
                                                   value="<?php echo htmlspecialchars($gallery_data['tags'] ?? ''); ?>"
                                                   placeholder="event, workshop, 2024, technology">
                                            <div class="form-text">Separate multiple tags with commas</div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="status" class="form-label">Status</label>
                                                    <select class="form-select" id="status" name="status">
                                                        <option value="active" <?php echo ($gallery_data['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo ($gallery_data['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="order_index" class="form-label">Display Order</label>
                                                    <input type="number" class="form-control" id="order_index" name="order_index"
                                                           value="<?php echo htmlspecialchars($gallery_data['order_index'] ?? 0); ?>"
                                                           min="0" max="999">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <div class="form-check mt-4">
                                                        <input class="form-check-input" type="checkbox" id="featured" name="featured"
                                                               <?php echo ($gallery_data['featured'] ?? 0) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="featured">
                                                            Featured Image
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="image" class="form-label">Image *</label>
                                            <input type="file" class="form-control" id="image" name="image"
                                                   accept="image/*" onchange="previewGalleryImage(event)"
                                                   <?php echo $action === 'create' ? 'required' : ''; ?>>
                                            <div class="form-text">Recommended size: 1200x800px (3:2 ratio)</div>
                                            <?php if (!empty($gallery_data['image_url'])): ?>
                                                <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($gallery_data['image_url']); ?>">
                                                <div class="mt-2">
                                                    <small class="text-muted">Current image:</small><br>
                                                    <img src="<?php echo SITE_URL . '/' . $gallery_data['image_url']; ?>"
                                                         alt="Current image" style="width: 100%; max-height: 300px; object-fit: cover; border-radius: 8px;">
                                                </div>
                                            <?php endif; ?>
                                            <div id="galleryImagePreview" class="mt-2"></div>
                                        </div>

                                        <div class="alert alert-info">
                                            <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i> Image Guidelines</h6>
                                            <ul class="mb-0 small">
                                                <li>Recommended size: 1200x800px (3:2 ratio)</li>
                                                <li>Maximum file size: 5MB</li>
                                                <li>Supported formats: JPG, PNG, GIF, WebP</li>
                                                <li>High-quality images recommended for best display</li>
                                                <li>Auto-thumbnails will be generated</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <a href="gallery.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        <?php echo $action === 'create' ? 'Add Image' : 'Update Image'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($action === 'bulk_upload'): ?>
                    <!-- Bulk Upload Form -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-cloud-upload-alt me-2"></i> Bulk Upload Images
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="category" class="form-label">Category *</label>
                                            <select class="form-select" id="category" name="category" required>
                                                <option value="">Select Category</option>
                                                <option value="events">Events</option>
                                                <option value="workshops">Workshops</option>
                                                <option value="competitions">Competitions</option>
                                                <option value="seminars">Seminars</option>
                                                <option value="infrastructure">Infrastructure</option>
                                                <option value="achievements">Achievements</option>
                                                <option value="activities">Activities</option>
                                                <option value="cultural">Cultural</option>
                                                <option value="sports">Sports</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <select class="form-select" id="status" name="status">
                                                <option value="active">Active</option>
                                                <option value="inactive">Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="featured" name="featured">
                                        <label class="form-check-label" for="featured">
                                            Mark all as featured
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="images" class="form-label">Select Images *</label>
                                    <input type="file" class="form-control" id="images" name="images[]"
                                           accept="image/*" multiple required onchange="previewBulkImages(event)">
                                    <div class="form-text">You can select multiple images at once</div>
                                </div>

                                <div id="bulkImagesPreview" class="row"></div>

                                <div class="d-flex justify-content-between mt-4">
                                    <a href="gallery.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-cloud-upload-alt me-2"></i> Upload Images
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Gallery Items List -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-list me-2"></i> Gallery Images
                                    </h5>
                                </div>
                                <div class="col-auto">
                                    <div class="d-flex gap-2 flex-wrap">
                                        <!-- Search -->
                                        <div class="input-group" style="width: 250px;">
                                            <input type="text" class="form-control" id="searchGallery" placeholder="Search images...">
                                            <button class="btn btn-outline-secondary" type="button">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>

                                        <!-- Filter by Category -->
                                        <select class="form-select" id="filterCategory" style="width: 150px;">
                                            <option value="">All Categories</option>
                                            <option value="events">Events</option>
                                            <option value="workshops">Workshops</option>
                                            <option value="competitions">Competitions</option>
                                            <option value="seminars">Seminars</option>
                                            <option value="infrastructure">Infrastructure</option>
                                            <option value="achievements">Achievements</option>
                                            <option value="activities">Activities</option>
                                            <option value="cultural">Cultural</option>
                                            <option value="sports">Sports</option>
                                            <option value="other">Other</option>
                                        </select>

                                        <!-- Filter by Status -->
                                        <select class="form-select" id="filterStatus" style="width: 120px;">
                                            <option value="">All Status</option>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>

                                        <!-- View Toggle -->
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-outline-secondary active" id="gridView" onclick="setView('grid')">
                                                <i class="fas fa-th"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" id="listView" onclick="setView('list')">
                                                <i class="fas fa-list"></i>
                                            </button>
                                        </div>
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
                                            <i class="fas fa-images"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM gallery", []); ?></h3>
                                            <p>Total Images</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-success">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM gallery WHERE status = 'active'", []); ?></h3>
                                            <p>Active</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-warning">
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM gallery WHERE featured = 1", []); ?></h3>
                                            <p>Featured</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-info">
                                            <i class="fas fa-folder"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(DISTINCT category) FROM gallery", []); ?></h3>
                                            <p>Categories</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-secondary">
                                            <i class="fas fa-calendar"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM gallery WHERE DATE(created_at) = CURDATE()", []); ?></h3>
                                            <p>Today</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-danger">
                                            <i class="fas fa-database"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo getTotalGallerySize(); ?></h3>
                                            <p>Total Size</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Gallery Grid/List View -->
                            <div id="galleryContainer">
                                <!-- Grid View (Default) -->
                                <div id="galleryGrid" class="gallery-grid">
                                    <?php
                                    $gallery_items = fetchAll("SELECT * FROM gallery ORDER BY order_index ASC, created_at DESC");
                                    foreach ($gallery_items as $item):
                                    ?>
                                    <div class="gallery-item" data-category="<?php echo $item['category']; ?>" data-status="<?php echo $item['status']; ?>">
                                        <div class="gallery-item-card">
                                            <div class="gallery-item-image">
                                                <?php if (!empty($item['image_url'])): ?>
                                                    <img src="<?php echo SITE_URL . '/' . $item['image_url']; ?>"
                                                         alt="<?php echo htmlspecialchars($item['title']); ?>"
                                                         loading="lazy">
                                                <?php else: ?>
                                                    <div class="no-image">
                                                        <i class="fas fa-image fa-3x"></i>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="gallery-item-overlay">
                                                    <div class="overlay-content">
                                                        <span class="badge bg-<?php echo getGalleryCategoryColor($item['category']); ?>">
                                                            <?php echo ucfirst($item['category']); ?>
                                                        </span>
                                                        <?php if ($item['featured']): ?>
                                                            <span class="badge bg-warning ms-1">
                                                                <i class="fas fa-star"></i>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="gallery-item-info">
                                                <h6 class="item-title"><?php echo htmlspecialchars($item['title']); ?></h6>
                                                <?php if (!empty($item['description'])): ?>
                                                    <p class="item-description small text-muted">
                                                        <?php echo htmlspecialchars(substr($item['description'], 0, 80)) . '...'; ?>
                                                    </p>
                                                <?php endif; ?>

                                                <div class="item-meta">
                                                    <span class="badge bg-<?php echo $item['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($item['status']); ?>
                                                    </span>
                                                    <span class="text-muted small ms-2">
                                                        <?php echo formatDate($item['created_at']); ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="gallery-item-actions">
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-info" onclick="viewGalleryItem(<?php echo $item['id']; ?>)" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="editGalleryItem(<?php echo $item['id']; ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="toggleFeatured(<?php echo $item['id']; ?>, <?php echo $item['featured'] ? 0 : 1; ?>)" title="Toggle Featured">
                                                        <i class="fas fa-star"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteGalleryItem(<?php echo $item['id']; ?>)" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- List View (Hidden by default) -->
                                <div id="galleryList" class="gallery-list" style="display: none;">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Preview</th>
                                                    <th>Title</th>
                                                    <th>Category</th>
                                                    <th>Tags</th>
                                                    <th>Featured</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($gallery_items as $item): ?>
                                                <tr data-category="<?php echo $item['category']; ?>" data-status="<?php echo $item['status']; ?>">
                                                    <td>
                                                        <?php if (!empty($item['image_url'])): ?>
                                                            <img src="<?php echo SITE_URL . '/' . str_replace('/gallery/', '/gallery/thumbnails/', $item['image_url']); ?>"
                                                                 alt="<?php echo htmlspecialchars($item['title']); ?>"
                                                                 style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px;">
                                                        <?php else: ?>
                                                            <div style="width: 60px; height: 40px; background: var(--glass-bg); border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                                                <i class="fas fa-image text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                                            <?php if (!empty($item['description'])): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($item['description'], 0, 60)) . '...'; ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getGalleryCategoryColor($item['category']); ?>">
                                                            <?php echo ucfirst($item['category']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($item['tags'])): ?>
                                                            <div class="tags-container">
                                                                <?php
                                                                $tags = array_map('trim', explode(',', $item['tags']));
                                                                foreach (array_slice($tags, 0, 3) as $tag):
                                                                ?>
                                                                <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars($tag); ?></span>
                                                                <?php endforeach; ?>
                                                                <?php if (count($tags) > 3): ?>
                                                                    <span class="badge bg-secondary">+<?php echo count($tags) - 3; ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">No tags</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <i class="fas fa-star <?php echo $item['featured'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $item['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                            <?php echo ucfirst($item['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <?php echo formatDate($item['created_at']); ?>
                                                            <br><small class="text-muted"><?php echo formatTime($item['created_at']); ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-sm btn-outline-info" onclick="viewGalleryItem(<?php echo $item['id']; ?>)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-warning" onclick="editGalleryItem(<?php echo $item['id']; ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteGalleryItem(<?php echo $item['id']; ?>)">
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
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<style>
/* Admin Gallery Management Styles */
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

/* Gallery Grid View */
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.gallery-item {
    transition: all 0.3s ease;
}

.gallery-item-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.gallery-item-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 255, 136, 0.1);
}

.gallery-item-image {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.gallery-item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.gallery-item-card:hover .gallery-item-image img {
    transform: scale(1.05);
}

.gallery-item-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    padding: 1rem;
    background: linear-gradient(to bottom, transparent, rgba(0,0,0,0.7));
}

.no-image {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--glass-bg);
    color: var(--text-muted);
}

.gallery-item-info {
    padding: 1rem;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.item-title {
    margin: 0 0 0.5rem 0;
    font-size: 1rem;
    color: var(--text-primary);
}

.item-description {
    margin: 0 0 1rem 0;
    flex-grow: 1;
}

.item-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.gallery-item-actions {
    padding: 0.5rem 1rem;
    border-top: 1px solid var(--border-color);
}

/* Badge colors */
.badge.bg-primary { background: var(--primary-color) !important; }
.badge.bg-success { background: var(--success-color) !important; }
.badge.bg-warning { background: var(--warning-color) !important; }
.badge.bg-danger { background: var(--error-color) !important; }
.badge.bg-info { background: var(--info-color) !important; }
.badge.bg-secondary { background: var(--text-muted) !important; }
.badge.bg-light { background: var(--glass-bg) !important; color: var(--text-primary) !important; }

/* Form enhancements */
.form-control:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 15px rgba(0, 255, 136, 0.2);
}

#galleryImagePreview img,
#bulkImagesPreview img {
    width: 100%;
    max-height: 200px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid var(--border-color);
    margin-bottom: 0.5rem;
}

/* Tags container */
.tags-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
}

/* Responsive design */
@media (max-width: 768px) {
    .gallery-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
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

// Image preview functions
function previewGalleryImage(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('galleryImagePreview');

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="max-height: 300px;">`;
        }
        reader.readAsDataURL(file);
    }
}

function previewBulkImages(event) {
    const files = event.target.files;
    const preview = document.getElementById('bulkImagesPreview');

    if (files && files.length > 0) {
        let html = '';
        for (let i = 0; i < Math.min(files.length, 6); i++) {
            const file = files[i];
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'col-md-4 mb-3';
                div.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 100%; height: 150px; object-fit: cover; border-radius: 8px;">`;
                preview.appendChild(div);
            }
            reader.readAsDataURL(file);
        }

        if (files.length > 6) {
            preview.innerHTML += `<div class="col-12"><p class="text-muted">And ${files.length - 6} more files...</p></div>`;
        }
    }
}

// Search and filter functionality
document.getElementById('searchGallery')?.addEventListener('input', function() {
    filterGalleryItems();
});

document.getElementById('filterCategory')?.addEventListener('change', function() {
    filterGalleryItems();
});

document.getElementById('filterStatus')?.addEventListener('change', function() {
    filterGalleryItems();
});

function filterGalleryItems() {
    const searchTerm = document.getElementById('searchGallery').value.toLowerCase();
    const categoryFilter = document.getElementById('filterCategory').value;
    const statusFilter = document.getElementById('filterStatus').value;

    const items = document.querySelectorAll('.gallery-item');

    items.forEach(item => {
        const category = item.dataset.category;
        const status = item.dataset.status;
        const text = item.textContent.toLowerCase();

        const categoryMatch = !categoryFilter || category === categoryFilter;
        const statusMatch = !statusFilter || status === statusFilter;
        const textMatch = !searchTerm || text.includes(searchTerm);

        item.style.display = categoryMatch && statusMatch && textMatch ? '' : 'none';
    });
}

// View toggle
function setView(view) {
    const gridView = document.getElementById('galleryGrid');
    const listView = document.getElementById('galleryList');
    const gridBtn = document.getElementById('gridView');
    const listBtn = document.getElementById('listView');

    if (view === 'grid') {
        gridView.style.display = 'grid';
        listView.style.display = 'none';
        gridBtn.classList.add('active');
        listBtn.classList.remove('active');
    } else {
        gridView.style.display = 'none';
        listView.style.display = 'block';
        gridBtn.classList.remove('active');
        listBtn.classList.add('active');
    }
}

// CRUD operations
function viewGalleryItem(itemId) {
    const item = fetchGalleryItemData(itemId);
    if (item && item.image_url) {
        window.open(`${SITE_URL}/${item.image_url}`, '_blank');
    }
}

function editGalleryItem(itemId) {
    window.location.href = `gallery.php?action=edit&id=${itemId}`;
}

function deleteGalleryItem(itemId) {
    if (confirm('Are you sure you want to delete this gallery item? This action cannot be undone.')) {
        window.location.href = `gallery.php?action=delete&id=${itemId}`;
    }
}

function toggleFeatured(itemId, featured) {
    window.location.href = `gallery.php?action=toggle_featured&id=${itemId}&featured=${featured}`;
}

function showBulkUpload() {
    window.location.href = 'gallery.php?action=bulk_upload';
}

// Helper function to get item data (simplified)
function fetchGalleryItemData(itemId) {
    // In a real implementation, this would fetch from API
    return { image_url: 'path/to/image.jpg' };
}

// Helper functions
function getGalleryCategoryColor(category) {
    const colors = {
        'events' => 'primary',
        'workshops' => 'success',
        'competitions' => 'warning',
        'seminars' => 'info',
        'infrastructure' => 'secondary',
        'achievements' => 'warning',
        'activities' => 'primary',
        'cultural' => 'success',
        'sports' => 'info',
        'other' => 'secondary'
    };
    return colors[category] ?? 'secondary';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString();
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
function getGalleryCategoryColor($category) {
    $colors = [
        'events' => 'primary',
        'workshops' => 'success',
        'competitions' => 'warning',
        'seminars' => 'info',
        'infrastructure' => 'secondary',
        'achievements' => 'warning',
        'activities' => 'primary',
        'cultural' => 'success',
        'sports' => 'info',
        'other' => 'secondary'
    ];
    return $colors[$category] ?? 'secondary';
}

function getTotalGallerySize() {
    // Simplified - in real implementation would calculate actual file sizes
    return '~45MB';
}

include __DIR__ . '/../templates/admin_footer.php';
?>