<div class="card admin-card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-<?php echo $action === 'create_banner' ? 'plus' : 'edit'; ?> me-2"></i>
            <?php echo $action === 'create_banner' ? 'Create Banner' : 'Edit Banner'; ?>
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <?php if ($action === 'edit_banner'): ?>
                <?php
                $banner_id = (int)$_GET['id'];
                $banner_data = fetchRow("SELECT * FROM banners WHERE id = ?", [$banner_id]);
                if (!$banner_data) {
                    $_SESSION['errors'][] = "Banner not found";
                    header('Location: homepage.php?action=banners');
                    exit();
                }
                ?>
                <input type="hidden" name="banner_id" value="<?php echo $banner_data['id']; ?>">
            <?php else: ?>
                <?php $banner_data = $_SESSION['form_data'] ?? []; ?>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label for="title" class="form-label">Banner Title *</label>
                        <input type="text" class="form-control" id="title" name="title"
                               value="<?php echo htmlspecialchars($banner_data['title'] ?? ''); ?>"
                               required maxlength="255" placeholder="Enter banner title">
                        <div class="invalid-feedback">Please provide a banner title</div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description"
                                  rows="3" placeholder="Banner description or caption..."><?php echo htmlspecialchars($banner_data['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="link" class="form-label">Link URL</label>
                                <input type="url" class="form-control" id="link" name="link"
                                       value="<?php echo htmlspecialchars($banner_data['link'] ?? ''); ?>"
                                       placeholder="https://example.com/page">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="link_text" class="form-label">Link Text</label>
                                <input type="text" class="form-control" id="link_text" name="link_text"
                                       value="<?php echo htmlspecialchars($banner_data['link_text'] ?? 'Learn More'); ?>"
                                       placeholder="Learn More">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?php echo ($banner_data['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($banner_data['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date"
                                       value="<?php echo htmlspecialchars($banner_data['start_date'] ?? date('Y-m-d')); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date"
                                       value="<?php echo htmlspecialchars($banner_data['end_date'] ?? ''); ?>">
                                <div class="form-text">Leave empty for no end date</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="image" class="form-label">Banner Image *</label>
                        <input type="file" class="form-control" id="image" name="image"
                               accept="image/*" onchange="previewBannerImage(event)" required>
                        <div class="form-text">Recommended size: 1200x400px</div>
                        <?php if (!empty($banner_data['image_url'])): ?>
                            <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($banner_data['image_url']); ?>">
                            <div class="mt-2">
                                <small class="text-muted">Current image:</small><br>
                                <img src="<?php echo SITE_URL . '/' . $banner_data['image_url']; ?>"
                                     alt="Current image" style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 8px;">
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="image_required" value="1">
                        <?php endif; ?>
                        <div id="bannerImagePreview" class="mt-2"></div>
                    </div>

                    <div class="mb-3">
                        <label for="order_index" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="order_index" name="order_index"
                               value="<?php echo htmlspecialchars($banner_data['order_index'] ?? 0); ?>"
                               min="0" max="99">
                        <div class="form-text">Lower numbers appear first</div>
                    </div>

                    <div class="mb-3">
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i> Banner Guidelines</h6>
                            <ul class="mb-0 small">
                                <li>Recommended size: 1200x400px</li>
                                <li>Maximum file size: 2MB</li>
                                <li>Supported formats: JPG, PNG, GIF</li>
                                <li>Keep text readable and concise</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <a href="homepage.php?action=banners" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>
                    <?php echo $action === 'create_banner' ? 'Create Banner' : 'Update Banner'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Banner image preview
function previewBannerImage(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('bannerImagePreview');

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 8px;">`;
        }
        reader.readAsDataURL(file);
    }
}
</script>