<?php
// SPARK Platform - Admin Event Management
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin login
requireAdmin();

// Handle event operations
$action = $_GET['action'] ?? 'list';

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $short_description = sanitize($_POST['short_description']);
        $event_type = sanitize($_POST['event_type']);
        $category = sanitize($_POST['category']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $venue = sanitize($_POST['venue']);
        $max_participants = (int)($_POST['max_participants'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $external_link = sanitize($_POST['external_link'] ?? '');
        $status = sanitize($_POST['status'] ?? 'upcoming');
        $featured = isset($_POST['featured']) ? 1 : 0;
        $requirements = sanitize($_POST['requirements'] ?? '');
        $perks = sanitize($_POST['perks'] ?? '');

        // Validation
        if (empty($title) || empty($description) || empty($start_date) || empty($venue)) {
            throw new Exception("Title, description, start date, and venue are required");
        }

        // Handle image upload
        $image_url = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image_url = uploadFile($_FILES['image'], 'events');
        }

        // Insert event
        $sql = "INSERT INTO events (
            title, description, short_description, event_type, category,
            start_date, end_date, start_time, end_time, venue,
            max_participants, price, external_link, status, featured,
            image_url, requirements, perks, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $params = [
            $title, $description, $short_description, $event_type, $category,
            $start_date, $end_date, $start_time, $end_time, $venue,
            $max_participants, $price, $external_link, $status, $featured,
            $image_url, $requirements, $perks, $_SESSION['user_id']
        ];

        $event_id = executeInsert($sql, $params);

        // Log activity
        logActivity('event_created', "Event '{$title}' created", $_SESSION['user_id'], $event_id);

        $_SESSION['success'][] = "Event created successfully!";
        header('Location: events.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        $_SESSION['form_data'] = $_POST;
        header('Location: events.php?action=create');
        exit();
    }
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $event_id = (int)$_POST['event_id'];
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $short_description = sanitize($_POST['short_description']);
        $event_type = sanitize($_POST['event_type']);
        $category = sanitize($_POST['category']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $venue = sanitize($_POST['venue']);
        $max_participants = (int)($_POST['max_participants'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $external_link = sanitize($_POST['external_link'] ?? '');
        $status = sanitize($_POST['status'] ?? 'upcoming');
        $featured = isset($_POST['featured']) ? 1 : 0;
        $requirements = sanitize($_POST['requirements'] ?? '');
        $perks = sanitize($_POST['perks'] ?? '');

        // Validation
        if (empty($title) || empty($description) || empty($start_date) || empty($venue)) {
            throw new Exception("Title, description, start date, and venue are required");
        }

        // Handle image upload
        $image_url = $_POST['existing_image'] ?? '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image_url = uploadFile($_FILES['image'], 'events');
        }

        // Update event
        $sql = "UPDATE events SET
            title = ?, description = ?, short_description = ?, event_type = ?, category = ?,
            start_date = ?, end_date = ?, start_time = ?, end_time = ?, venue = ?,
            max_participants = ?, price = ?, external_link = ?, status = ?, featured = ?,
            image_url = ?, requirements = ?, perks = ?, updated_at = NOW()
        WHERE id = ?";

        $params = [
            $title, $description, $short_description, $event_type, $category,
            $start_date, $end_date, $start_time, $end_time, $venue,
            $max_participants, $price, $external_link, $status, $featured,
            $image_url, $requirements, $perks, $event_id
        ];

        executeUpdate($sql, $params);

        // Log activity
        logActivity('event_updated', "Event '{$title}' updated", $_SESSION['user_id'], $event_id);

        $_SESSION['success'][] = "Event updated successfully!";
        header('Location: events.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        header('Location: events.php?action=edit&id=' . $_POST['event_id']);
        exit();
    }
}

if ($action === 'delete') {
    try {
        $event_id = (int)$_GET['id'];

        // Get event info for logging
        $event = fetchRow("SELECT title FROM events WHERE id = ?", [$event_id]);
        if (!$event) {
            throw new Exception("Event not found");
        }

        // Check if event has registrations
        $registrations = fetchColumn("SELECT COUNT(*) FROM event_registrations WHERE event_id = ?", [$event_id]);
        if ($registrations > 0) {
            throw new Exception("Cannot delete event with existing registrations");
        }

        // Delete event
        executeUpdate("DELETE FROM events WHERE id = ?", [$event_id]);

        // Log activity
        logActivity('event_deleted', "Event '{$event['title']}' deleted", $_SESSION['user_id'], $event_id);

        $_SESSION['success'][] = "Event deleted successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: events.php');
    exit();
}

if ($action === 'toggle_featured') {
    try {
        $event_id = (int)$_GET['id'];
        $featured = (int)$_GET['featured'];

        executeUpdate("UPDATE events SET featured = ? WHERE id = ?", [$featured, $event_id]);

        $action_text = $featured ? 'featured' : 'unfeatured';
        $_SESSION['success'][] = "Event {$action_text} successfully!";

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
    }

    header('Location: events.php');
    exit();
}

$page_title = 'Event Management';
$breadcrumb = [
    ['name' => 'Dashboard', 'link' => 'index.php', 'active' => false],
    ['name' => 'Events', 'link' => '', 'active' => true]
];

include __DIR__ . '/../templates/admin_header.php';
?>

<!-- Event Management Section -->
<section class="admin-section py-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="section-title">
                            <i class="fas fa-calendar-alt me-2"></i> Event Management
                        </h2>
                        <p class="text-muted">Manage all events, workshops, and competitions</p>
                    </div>
                    <div>
                        <a href="events.php?action=create" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Create Event
                        </a>
                    </div>
                </div>

                <?php include __DIR__ . '/../includes/alerts.php'; ?>

                <?php if ($action === 'create' || $action === 'edit'): ?>
                    <!-- Create/Edit Event Form -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-<?php echo $action === 'create' ? 'plus' : 'edit'; ?> me-2"></i>
                                <?php echo $action === 'create' ? 'Create New Event' : 'Edit Event'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $event_data = $_SESSION['form_data'] ?? [];
                            if ($action === 'edit') {
                                $event_id = (int)$_GET['id'];
                                $event_data = fetchRow("SELECT * FROM events WHERE id = ?", [$event_id]);
                                if (!$event_data) {
                                    $_SESSION['errors'][] = "Event not found";
                                    header('Location: events.php');
                                    exit();
                                }
                            }
                            ?>

                            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="event_id" value="<?php echo $event_data['id']; ?>">
                                <?php endif; ?>

                                <div class="row">
                                    <!-- Basic Information -->
                                    <div class="col-md-8">
                                        <h6 class="text-primary mb-3">Basic Information</h6>

                                        <div class="mb-3">
                                            <label for="title" class="form-label">Event Title *</label>
                                            <input type="text" class="form-control" id="title" name="title"
                                                   value="<?php echo htmlspecialchars($event_data['title'] ?? ''); ?>"
                                                   required maxlength="255">
                                            <div class="invalid-feedback">Please provide an event title</div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="short_description" class="form-label">Short Description</label>
                                            <textarea class="form-control" id="short_description" name="short_description"
                                                      rows="2" maxlength="500"><?php echo htmlspecialchars($event_data['short_description'] ?? ''); ?></textarea>
                                            <small class="text-muted">Brief description for listings (max 500 chars)</small>
                                        </div>

                                        <div class="mb-3">
                                            <label for="description" class="form-label">Full Description *</label>
                                            <textarea class="form-control editor" id="description" name="description"
                                                      rows="8" required><?php echo htmlspecialchars($event_data['description'] ?? ''); ?></textarea>
                                            <div class="invalid-feedback">Please provide a full description</div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="event_type" class="form-label">Event Type</label>
                                                    <select class="form-select" id="event_type" name="event_type">
                                                        <option value="">Select Type</option>
                                                        <option value="workshop" <?php echo ($event_data['event_type'] ?? '') === 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                                                        <option value="competition" <?php echo ($event_data['event_type'] ?? '') === 'competition' ? 'selected' : ''; ?>>Competition</option>
                                                        <option value="seminar" <?php echo ($event_data['event_type'] ?? '') === 'seminar' ? 'selected' : ''; ?>>Seminar</option>
                                                        <option value="hackathon" <?php echo ($event_data['event_type'] ?? '') === 'hackathon' ? 'selected' : ''; ?>>Hackathon</option>
                                                        <option value="conference" <?php echo ($event_data['event_type'] ?? '') === 'conference' ? 'selected' : ''; ?>>Conference</option>
                                                        <option value="cultural" <?php echo ($event_data['event_type'] ?? '') === 'cultural' ? 'selected' : ''; ?>>Cultural</option>
                                                        <option value="sports" <?php echo ($event_data['event_type'] ?? '') === 'sports' ? 'selected' : ''; ?>>Sports</option>
                                                        <option value="other" <?php echo ($event_data['event_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="category" class="form-label">Category</label>
                                                    <select class="form-select" id="category" name="category">
                                                        <option value="">Select Category</option>
                                                        <option value="technical" <?php echo ($event_data['category'] ?? '') === 'technical' ? 'selected' : ''; ?>>Technical</option>
                                                        <option value="non_technical" <?php echo ($event_data['category'] ?? '') === 'non_technical' ? 'selected' : ''; ?>>Non-Technical</option>
                                                        <option value="management" <?php echo ($event_data['category'] ?? '') === 'management' ? 'selected' : ''; ?>>Management</option>
                                                        <option value="design" <?php echo ($event_data['category'] ?? '') === 'design' ? 'selected' : ''; ?>>Design</option>
                                                        <option value="research" <?php echo ($event_data['category'] ?? '') === 'research' ? 'selected' : ''; ?>>Research</option>
                                                        <option value="entrepreneurship" <?php echo ($event_data['category'] ?? '') === 'entrepreneurship' ? 'selected' : ''; ?>>Entrepreneurship</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Event Details -->
                                    <div class="col-md-4">
                                        <h6 class="text-primary mb-3">Event Details</h6>

                                        <div class="mb-3">
                                            <label for="start_date" class="form-label">Start Date *</label>
                                            <input type="date" class="form-control" id="start_date" name="start_date"
                                                   value="<?php echo htmlspecialchars($event_data['start_date'] ?? ''); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="end_date" class="form-label">End Date</label>
                                            <input type="date" class="form-control" id="end_date" name="end_date"
                                                   value="<?php echo htmlspecialchars($event_data['end_date'] ?? ''); ?>">
                                        </div>

                                        <div class="row">
                                            <div class="col-6">
                                                <div class="mb-3">
                                                    <label for="start_time" class="form-label">Start Time</label>
                                                    <input type="time" class="form-control" id="start_time" name="start_time"
                                                           value="<?php echo htmlspecialchars($event_data['start_time'] ?? ''); ?>">
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="mb-3">
                                                    <label for="end_time" class="form-label">End Time</label>
                                                    <input type="time" class="form-control" id="end_time" name="end_time"
                                                           value="<?php echo htmlspecialchars($event_data['end_time'] ?? ''); ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="venue" class="form-label">Venue *</label>
                                            <input type="text" class="form-control" id="venue" name="venue"
                                                   value="<?php echo htmlspecialchars($event_data['venue'] ?? ''); ?>"
                                                   required maxlength="255">
                                        </div>

                                        <div class="row">
                                            <div class="col-6">
                                                <div class="mb-3">
                                                    <label for="max_participants" class="form-label">Max Participants</label>
                                                    <input type="number" class="form-control" id="max_participants" name="max_participants"
                                                           value="<?php echo htmlspecialchars($event_data['max_participants'] ?? ''); ?>"
                                                           min="0" max="10000">
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="mb-3">
                                                    <label for="price" class="form-label">Price (₹)</label>
                                                    <input type="number" class="form-control" id="price" name="price"
                                                           value="<?php echo htmlspecialchars($event_data['price'] ?? ''); ?>"
                                                           min="0" step="0.01">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="external_link" class="form-label">External Registration Link</label>
                                            <input type="url" class="form-control" id="external_link" name="external_link"
                                                   value="<?php echo htmlspecialchars($event_data['external_link'] ?? ''); ?>"
                                                   placeholder="https://example.com/register">
                                        </div>

                                        <div class="mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <select class="form-select" id="status" name="status">
                                                <option value="upcoming" <?php echo ($event_data['status'] ?? 'upcoming') === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                                <option value="ongoing" <?php echo ($event_data['status'] ?? '') === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                                <option value="completed" <?php echo ($event_data['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo ($event_data['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="featured" name="featured"
                                                       <?php echo ($event_data['featured'] ?? 0) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="featured">
                                                    Featured Event
                                                </label>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="image" class="form-label">Event Image</label>
                                            <input type="file" class="form-control" id="image" name="image"
                                                   accept="image/*" onchange="previewImage(event)">
                                            <?php if (!empty($event_data['image_url'])): ?>
                                                <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($event_data['image_url']); ?>">
                                                <div class="mt-2">
                                                    <small class="text-muted">Current image:</small><br>
                                                    <img src="<?php echo SITE_URL . '/' . $event_data['image_url']; ?>"
                                                         alt="Current image" style="max-width: 100px; max-height: 100px; object-fit: cover;">
                                                </div>
                                            <?php endif; ?>
                                            <div id="imagePreview" class="mt-2"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Additional Information -->
                                <div class="row mt-4">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="requirements" class="form-label">Requirements</label>
                                            <textarea class="form-control" id="requirements" name="requirements"
                                                      rows="4" placeholder="Prerequisites, materials needed, etc."><?php echo htmlspecialchars($event_data['requirements'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="perks" class="form-label">Perks & Benefits</label>
                                            <textarea class="form-control" id="perks" name="perks"
                                                      rows="4" placeholder="Certificates, prizes, learning outcomes, etc."><?php echo htmlspecialchars($event_data['perks'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Quick Actions</label>
                                            <div class="d-grid gap-2">
                                                <button type="button" class="btn btn-outline-secondary" onclick="generateDescription()">
                                                    <i class="fas fa-magic me-2"></i> Generate Description
                                                </button>
                                                <button type="button" class="btn btn-outline-info" onclick="previewEvent()">
                                                    <i class="fas fa-eye me-2"></i> Preview Event
                                                </button>
                                                <button type="button" class="btn btn-outline-warning" onclick="duplicateEvent()">
                                                    <i class="fas fa-copy me-2"></i> Duplicate Event
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <a href="events.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        <?php echo $action === 'create' ? 'Create Event' : 'Update Event'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Events List -->
                    <div class="card admin-card">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-list me-2"></i> All Events
                                    </h5>
                                </div>
                                <div class="col-auto">
                                    <div class="d-flex gap-2">
                                        <!-- Search -->
                                        <div class="input-group" style="width: 250px;">
                                            <input type="text" class="form-control" id="searchEvents" placeholder="Search events...">
                                            <button class="btn btn-outline-secondary" type="button">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>

                                        <!-- Filter -->
                                        <select class="form-select" id="filterStatus" style="width: 150px;">
                                            <option value="">All Status</option>
                                            <option value="upcoming">Upcoming</option>
                                            <option value="ongoing">Ongoing</option>
                                            <option value="completed">Completed</option>
                                            <option value="cancelled">Cancelled</option>
                                        </select>

                                        <!-- Export -->
                                        <button class="btn btn-outline-success" onclick="exportEvents()">
                                            <i class="fas fa-download me-1"></i> Export
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Statistics -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-primary">
                                            <i class="fas fa-calendar"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM events", []); ?></h3>
                                            <p>Total Events</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-success">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM events WHERE status = 'upcoming'", []); ?></h3>
                                            <p>Upcoming</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-warning">
                                            <i class="fas fa-play-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM events WHERE status = 'ongoing'", []); ?></h3>
                                            <p>Ongoing</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-icon bg-info">
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3><?php echo fetchColumn("SELECT COUNT(*) FROM events WHERE featured = 1", []); ?></h3>
                                            <p>Featured</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Events Table -->
                            <div class="table-responsive">
                                <table class="table table-hover" id="eventsTable">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Event</th>
                                            <th>Type</th>
                                            <th>Date</th>
                                            <th>Venue</th>
                                            <th>Participants</th>
                                            <th>Price</th>
                                            <th>Status</th>
                                            <th>Featured</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sql = "SELECT e.*,
                                               (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.id) as participant_count
                                               FROM events e
                                               ORDER BY e.start_date DESC, e.created_at DESC";
                                        $events = fetchAll($sql);

                                        foreach ($events as $event):
                                        ?>
                                        <tr data-event-id="<?php echo $event['id']; ?>">
                                            <td>
                                                <?php if (!empty($event['image_url'])): ?>
                                                    <img src="<?php echo SITE_URL . '/' . $event['image_url']; ?>"
                                                         alt="<?php echo htmlspecialchars($event['title']); ?>"
                                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                                <?php else: ?>
                                                    <div class="event-placeholder">
                                                        <i class="fas fa-calendar"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                                    <?php if (!empty($event['short_description'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($event['short_description'], 0, 100)) . '...'; ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getEventTypeColor($event['event_type']); ?>">
                                                    <?php echo ucfirst($event['event_type'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo formatDate($event['start_date']); ?>
                                                <?php if ($event['start_time']): ?>
                                                    <br><small class="text-muted"><?php echo $event['start_time']; ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo $event['participant_count']; ?>
                                                    <?php if ($event['max_participants'] > 0): ?>
                                                        / <?php echo $event['max_participants']; ?>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($event['price'] > 0): ?>
                                                    ₹<?php echo number_format($event['price'], 2); ?>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Free</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getStatusColor($event['status']); ?>">
                                                    <?php echo ucfirst($event['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox"
                                                           <?php echo $event['featured'] ? 'checked' : ''; ?>
                                                           onchange="toggleFeatured(<?php echo $event['id']; ?>, <?php echo $event['featured'] ? 0 : 1; ?>)">
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewEvent(<?php echo $event['id']; ?>)" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="editEvent(<?php echo $event['id']; ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-success" onclick="viewRegistrations(<?php echo $event['id']; ?>)" title="Registrations">
                                                        <i class="fas fa-users"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteEvent(<?php echo $event['id']; ?>)" title="Delete">
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

<!-- Event Preview Modal -->
<div class="modal fade" id="eventPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Event Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="eventPreviewContent">
                <!-- Preview content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<style>
/* Admin Event Management Styles */
.event-placeholder {
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

.table td {
    vertical-align: middle;
}

.btn-group .btn {
    padding: 0.25rem 0.5rem;
}

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
.needs-validation .form-control:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 15px rgba(0, 255, 136, 0.2);
}

.editor {
    min-height: 200px;
}

#imagePreview img {
    max-width: 200px;
    max-height: 200px;
    border-radius: 8px;
    border: 2px solid var(--border-color);
}

/* Table responsive improvements */
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
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="max-width: 200px; border-radius: 8px;">`;
        }
        reader.readAsDataURL(file);
    }
}

// Search functionality
document.getElementById('searchEvents')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#eventsTable tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Filter functionality
document.getElementById('filterStatus')?.addEventListener('change', function() {
    const filterValue = this.value;
    const rows = document.querySelectorAll('#eventsTable tbody tr');

    rows.forEach(row => {
        if (!filterValue) {
            row.style.display = '';
        } else {
            const statusBadge = row.querySelector('td:nth-child(8) .badge');
            const status = statusBadge.textContent.toLowerCase();
            row.style.display = status === filterValue ? '' : 'none';
        }
    });
});

// CRUD operations
function viewEvent(eventId) {
    const modal = new bootstrap.Modal(document.getElementById('eventPreviewModal'));
    const content = document.getElementById('eventPreviewContent');

    // Show loading
    content.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    modal.show();

    // Fetch event data
    fetch(`../api/events.php?action=get&id=${eventId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const event = data.data;
                content.innerHTML = `
                    <div class="event-preview">
                        ${event.image_url ? `<img src="${SITE_URL}/${event.image_url}" class="img-fluid mb-3 rounded" alt="${event.title}">` : ''}
                        <h4>${event.title}</h4>
                        <div class="mb-3">
                            <span class="badge bg-primary">${event.event_type}</span>
                            <span class="badge bg-info ms-2">${event.category}</span>
                            <span class="badge bg-success ms-2">${event.status}</span>
                        </div>
                        <p><strong>Date:</strong> ${formatDate(event.start_date)} ${event.start_time || ''}</p>
                        <p><strong>Venue:</strong> ${event.venue}</p>
                        <p><strong>Price:</strong> ${event.price > 0 ? '₹' + event.price : 'Free'}</p>
                        <p><strong>Max Participants:</strong> ${event.max_participants || 'Unlimited'}</p>
                        ${event.description ? `<div><strong>Description:</strong><br>${event.description}</div>` : ''}
                        ${event.requirements ? `<div class="mt-3"><strong>Requirements:</strong><br>${event.requirements}</div>` : ''}
                        ${event.perks ? `<div class="mt-3"><strong>Perks:</strong><br>${event.perks}</div>` : ''}
                    </div>
                `;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Error loading event details</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error loading event details</div>';
        });
}

function editEvent(eventId) {
    window.location.href = `events.php?action=edit&id=${eventId}`;
}

function deleteEvent(eventId) {
    if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
        window.location.href = `events.php?action=delete&id=${eventId}`;
    }
}

function toggleFeatured(eventId, featured) {
    window.location.href = `events.php?action=toggle_featured&id=${eventId}&featured=${featured}`;
}

function viewRegistrations(eventId) {
    window.location.href = `event-registrations.php?event_id=${eventId}`;
}

// Utility functions
function exportEvents() {
    window.location.href = '../api/export.php?type=events';
}

function generateDescription() {
    const title = document.getElementById('title').value;
    const eventType = document.getElementById('event_type').value;

    if (!title || !eventType) {
        alert('Please fill in the event title and type first');
        return;
    }

    const templates = {
        workshop: `Join us for an engaging workshop on ${title}. This hands-on session will provide practical skills and knowledge that you can apply immediately. Perfect for students looking to enhance their expertise in this area.`,
        competition: `Showcase your skills and compete with the best in ${title}. This exciting competition offers prizes, recognition, and a platform to demonstrate your talents. Don't miss this opportunity to shine!`,
        seminar: `Expand your knowledge with our informative seminar on ${title}. Industry experts will share insights, trends, and best practices. Ideal for anyone interested in learning from experienced professionals.`,
        hackathon: `Innovate, create, and compete in our ${title} hackathon. Work in teams to develop solutions, win prizes, and network with fellow innovators. A perfect platform for tech enthusiasts!`
    };

    const description = templates[eventType] || `An exciting event focused on ${title}. Join us for an enriching experience that combines learning, networking, and engagement. Don't miss this opportunity to be part of something special!`;

    document.getElementById('description').value = description;
}

function previewEvent() {
    const form = document.querySelector('.needs-validation');
    const formData = new FormData(form);

    // Create preview HTML
    let preview = '<div class="event-preview">';
    preview += `<h4>${formData.get('title') || 'Untitled Event'}</h4>`;
    preview += `<div class="mb-3">`;
    preview += `<span class="badge bg-primary">${formData.get('event_type') || 'General'}</span>`;
    preview += `<span class="badge bg-info ms-2">${formData.get('category') || 'General'}</span>`;
    preview += `<span class="badge bg-success ms-2">${formData.get('status') || 'Upcoming'}</span>`;
    preview += `</div>`;
    preview += `<p><strong>Date:</strong> ${formData.get('start_date') || 'TBD'}</p>`;
    preview += `<p><strong>Venue:</strong> ${formData.get('venue') || 'TBD'}</p>`;
    preview += `<p><strong>Price:</strong> ${formData.get('price') > 0 ? '₹' + formData.get('price') : 'Free'}</p>`;
    preview += `<p><strong>Description:</strong><br>${formData.get('description') || 'No description provided'}</p>`;
    preview += '</div>';

    document.getElementById('eventPreviewContent').innerHTML = preview;
    new bootstrap.Modal(document.getElementById('eventPreviewModal')).show();
}

function duplicateEvent() {
    const form = document.querySelector('.needs-validation');
    const formData = new FormData(form);

    // Clear ID field for duplication
    const eventIdInput = form.querySelector('input[name="event_id"]');
    if (eventIdInput) {
        eventIdInput.remove();
    }

    // Modify title to indicate copy
    const titleInput = document.getElementById('title');
    titleInput.value = titleInput.value + ' (Copy)';

    alert('Event form duplicated. Please update the title and any other details as needed.');
}

// Helper function for status colors (mirrors PHP function)
function getStatusColor(status) {
    const colors = {
        'upcoming': 'primary',
        'ongoing': 'success',
        'completed': 'secondary',
        'cancelled': 'danger'
    };
    return colors[status] || 'secondary';
}

// Helper function for event type colors
function getEventTypeColor(type) {
    const colors = {
        'workshop': 'info',
        'competition': 'warning',
        'seminar': 'success',
        'hackathon': 'danger',
        'conference': 'primary',
        'cultural': 'success',
        'sports': 'warning',
        'other': 'secondary'
    };
    return colors[type] || 'secondary';
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
function getEventTypeColor($type) {
    $colors = [
        'workshop' => 'info',
        'competition' => 'warning',
        'seminar' => 'success',
        'hackathon' => 'danger',
        'conference' => 'primary',
        'cultural' => 'success',
        'sports' => 'warning',
        'other' => 'secondary'
    ];
    return $colors[$type] ?? 'secondary';
}

function getStatusColor($status) {
    $colors = [
        'upcoming' => 'primary',
        'ongoing' => 'success',
        'completed' => 'secondary',
        'cancelled' => 'danger'
    ];
    return $colors[$status] ?? 'secondary';
}

include __DIR__ . '/../templates/admin_footer.php';
?>