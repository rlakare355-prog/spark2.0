<div class="card admin-card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-<?php echo $action === 'create_announcement' ? 'plus' : 'edit'; ?> me-2"></i>
            <?php echo $action === 'create_announcement' ? 'Create Announcement' : 'Edit Announcement'; ?>
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <?php if ($action === 'edit_announcement'): ?>
                <?php
                $announcement_id = (int)$_GET['id'];
                $announcement_data = fetchRow("SELECT * FROM announcements WHERE id = ?", [$announcement_id]);
                if (!$announcement_data) {
                    $_SESSION['errors'][] = "Announcement not found";
                    header('Location: homepage.php?action=announcements');
                    exit();
                }
                ?>
                <input type="hidden" name="announcement_id" value="<?php echo $announcement_data['id']; ?>">
            <?php else: ?>
                <?php $announcement_data = $_SESSION['form_data'] ?? []; ?>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label for="title" class="form-label">Announcement Title *</label>
                        <input type="text" class="form-control" id="title" name="title"
                               value="<?php echo htmlspecialchars($announcement_data['title'] ?? ''); ?>"
                               required maxlength="255" placeholder="Enter announcement title">
                        <div class="invalid-feedback">Please provide an announcement title</div>
                    </div>

                    <div class="mb-3">
                        <label for="content" class="form-label">Announcement Content *</label>
                        <textarea class="form-control editor" id="content" name="content"
                                  rows="8" required placeholder="Write your announcement content..."><?php echo htmlspecialchars($announcement_data['content'] ?? ''); ?></textarea>
                        <div class="invalid-feedback">Please provide announcement content</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type" class="form-label">Announcement Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="info" <?php echo ($announcement_data['type'] ?? 'info') === 'info' ? 'selected' : ''; ?>>Information</option>
                                    <option value="warning" <?php echo ($announcement_data['type'] ?? '') === 'warning' ? 'selected' : ''; ?>>Warning</option>
                                    <option value="success" <?php echo ($announcement_data['type'] ?? '') === 'success' ? 'selected' : ''; ?>>Success</option>
                                    <option value="danger" <?php echo ($announcement_data['type'] ?? '') === 'danger' ? 'selected' : ''; ?>>Important</option>
                                    <option value="update" <?php echo ($announcement_data['type'] ?? '') === 'update' ? 'selected' : ''; ?>>Update</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority Level</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="low" <?php echo ($announcement_data['priority'] ?? 'medium') === 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo ($announcement_data['priority'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo ($announcement_data['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="urgent" <?php echo ($announcement_data['priority'] ?? '') === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="datetime-local" class="form-control" id="start_date" name="start_date"
                                       value="<?php echo htmlspecialchars($announcement_data['start_date'] ?? date('Y-m-d\TH:i')); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="datetime-local" class="form-control" id="end_date" name="end_date"
                                       value="<?php echo htmlspecialchars($announcement_data['end_date'] ?? ''); ?>">
                                <div class="form-text">Leave empty for no end date</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo ($announcement_data['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($announcement_data['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="scheduled" <?php echo ($announcement_data['status'] ?? '') === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="link" class="form-label">Associated Link (Optional)</label>
                        <input type="url" class="form-control" id="link" name="link"
                               value="<?php echo htmlspecialchars($announcement_data['link'] ?? ''); ?>"
                               placeholder="https://example.com/more-info">
                    </div>

                    <div class="mb-3">
                        <label for="link_text" class="form-label">Link Text</label>
                        <input type="text" class="form-control" id="link_text" name="link_text"
                               value="<?php echo htmlspecialchars($announcement_data['link_text'] ?? 'Learn More'); ?>"
                               placeholder="Learn More">
                    </div>

                    <div class="mb-3">
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i> Announcement Guidelines</h6>
                            <ul class="mb-0 small">
                                <li>Keep content clear and concise</li>
                                <li>Use appropriate priority levels</li>
                                <li>Set start/end dates for timed announcements</li>
                                <li>Include relevant links when necessary</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Preview Card -->
                    <div class="mb-3">
                        <h6 class="text-primary">Preview</h6>
                        <div class="alert alert-<?php echo getAnnouncementTypeColor($announcement_data['type'] ?? 'info'); ?>" id="announcementPreview">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="alert-heading mb-1" id="previewTitle">
                                        <?php echo htmlspecialchars($announcement_data['title'] ?? 'Announcement Title'); ?>
                                    </h6>
                                    <p class="mb-2 small" id="previewContent">
                                        <?php echo htmlspecialchars(substr($announcement_data['content'] ?? 'Announcement content preview...', 0, 150)) . '...'; ?>
                                    </p>
                                    <div>
                                        <span class="badge bg-<?php echo getAnnouncementPriorityColor($announcement_data['priority'] ?? 'medium'); ?>" id="previewPriority">
                                            <?php echo ucfirst($announcement_data['priority'] ?? 'medium'); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if (!empty($announcement_data['link'])): ?>
                                <a href="<?php echo htmlspecialchars($announcement_data['link']); ?>" class="btn btn-sm btn-outline-light">
                                    <?php echo htmlspecialchars($announcement_data['link_text'] ?? 'Learn More'); ?>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <a href="homepage.php?action=announcements" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i> Cancel
                </a>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary" onclick="previewAnnouncement()">
                        <i class="fas fa-eye me-2"></i> Preview
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>
                        <?php echo $action === 'create_announcement' ? 'Create Announcement' : 'Update Announcement'; ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// Real-time preview
function updatePreview() {
    const title = document.getElementById('title').value;
    const content = document.getElementById('content').value;
    const type = document.getElementById('type').value;
    const priority = document.getElementById('priority').value;

    const previewTitle = document.getElementById('previewTitle');
    const previewContent = document.getElementById('previewContent');
    const previewPriority = document.getElementById('previewPriority');
    const previewContainer = document.getElementById('announcementPreview');

    if (previewTitle) previewTitle.textContent = title || 'Announcement Title';
    if (previewContent) previewContent.textContent = content ? content.substring(0, 150) + '...' : 'Announcement content preview...';
    if (previewPriority) {
        previewPriority.textContent = priority.charAt(0).toUpperCase() + priority.slice(1);
        previewPriority.className = `badge bg-${getAnnouncementPriorityColor(priority)}`;
    }

    // Update alert class based on type
    previewContainer.className = `alert alert-${getAnnouncementTypeColor(type)}`;
}

// Add event listeners for real-time preview
document.getElementById('title')?.addEventListener('input', updatePreview);
document.getElementById('content')?.addEventListener('input', updatePreview);
document.getElementById('type')?.addEventListener('change', updatePreview);
document.getElementById('priority')?.addEventListener('change', updatePreview);

// Preview function
function previewAnnouncement() {
    const title = document.getElementById('title').value;
    const content = document.getElementById('content').value;

    if (!title || !content) {
        alert('Please fill in title and content before previewing');
        return;
    }

    // In a real implementation, this would show a modal or new tab
    alert(`Preview:\n\nTitle: ${title}\n\nContent: ${content.substring(0, 200)}...`);
}

// Helper functions (same as in homepage.php)
function getAnnouncementTypeColor(type) {
    const colors = {
        'info': 'info',
        'warning': 'warning',
        'success': 'success',
        'danger': 'danger',
        'update': 'primary'
    };
    return colors[type] || 'secondary';
}

function getAnnouncementPriorityColor(priority) {
    const colors = {
        'low': 'secondary',
        'medium': 'info',
        'high': 'warning',
        'urgent': 'danger'
    };
    return colors[priority] || 'secondary';
}
</script>