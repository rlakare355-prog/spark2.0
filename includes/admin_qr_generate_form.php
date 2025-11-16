<div class="card admin-card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-plus me-2"></i> Generate QR Code
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" action="attendance.php?action=generate_qr" enctype="multipart/form-data" class="needs-validation" novalidate>
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary mb-3">Basic Information</h6>

                    <div class="mb-3">
                        <label for="title" class="form-label">QR Title *</label>
                        <input type="text" class="form-control" id="title" name="title"
                               value="<?php echo htmlspecialchars($_SESSION['form_data']['title'] ?? ''); ?>"
                               required maxlength="255" placeholder="e.g., Workshop Registration, Event Check-in">
                        <div class="invalid-feedback">Please provide a QR title</div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description"
                                  rows="3" placeholder="Describe what this QR code is for..."><?php echo htmlspecialchars($_SESSION['form_data']['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="event_id" class="form-label">Associated Event</label>
                        <select class="form-select" id="event_id" name="event_id">
                            <option value="">Select event (optional)</option>
                            <?php
                            $events = fetchAll("SELECT id, title, start_date FROM events WHERE status IN ('upcoming', 'ongoing', 'completed') ORDER BY start_date DESC LIMIT 20");
                            foreach ($events as $event):
                            ?>
                            <option value="<?php echo $event['id']; ?>" <?php echo (isset($_SESSION['form_data']['event_id']) && $_SESSION['form_data']['event_id'] == $event['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($event['title'] . ' (' . formatDate($event['start_date']) . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Associate with specific event for better tracking</div>
                    </div>
                </div>

                <div class="col-md-6">
                    <h6 class="text-primary mb-3">Schedule & Location</h6>

                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date"
                                       value="<?php echo htmlspecialchars($_SESSION['form_data']['start_date'] ?? date('Y-m-d')); ?>">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date"
                                       value="<?php echo htmlspecialchars($_SESSION['form_data']['end_date'] ?? ''); ?>">
                                <div class="form-text">Leave empty for no expiry</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location" name="location"
                               value="<?php echo htmlspecialchars($_SESSION['form_data']['location'] ?? ''); ?>"
                               placeholder="e.g., Main Auditorium, Room 101, Online">
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                   <?php echo (isset($_SESSION['form_data']['is_active']) && $_SESSION['form_data']['is_active']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">
                                <strong>Active QR Code</strong>
                                <div class="form-text">QR code can be immediately used for attendance</div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <h6 class="text-primary mb-3">QR Code Settings</h6>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">QR Code Size</label>
                                <select class="form-select">
                                    <option value="small">Small (200x200)</option>
                                    <option value="medium" selected>Medium (300x300)</option>
                                    <option value="large">Large (400x400)</option>
                                </select>
                                <div class="form-text">Standard size is Medium (300x300)</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Error Correction</label>
                                <select class="form-select">
                                    <option value="low">Low (7% redundancy)</option>
                                    <option value="medium" selected>Medium (15% redundancy)</option>
                                    <option value="high">High (25% redundancy)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Border Style</label>
                                <select class="form-select">
                                    <option value="square" selected>Square</option>
                                    <option value="rounded">Rounded</option>
                                    <option value="circle">Circle</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Custom Logo (Optional)</label>
                        <input type="file" class="form-control" accept="image/*"
                               placeholder="Upload a logo to center in the QR code">
                        <div class="form-text">Recommended: PNG with transparent background</div>
                    </div>

                    <div class="alert alert-info">
                        <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i> QR Code Information</h6>
                        <ul class="mb-0 small">
                            <li>QR codes will contain session information for attendance tracking</li>
                            <li>Each QR code generates a unique ID for security</li>
                            <li>QR codes can be scanned with mobile devices or dedicated scanners</li>
                            <li>Attendance records will include scan time, location, and validation status</li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Preview QR Code Data</label>
                        <div class="preview-box bg-light p-3 rounded">
                            <small class="text-muted">QR code will contain:</small>
                            <pre class="mb-0 mt-2"><code>{
    "type": "attendance",
    "id": "[AUTO-GENERATED]",
    "title": "[Your Title]",
    "event_id": "[Event ID]",
    "start_date": "[Start Date]",
    "end_date": "[End Date]",
    "location": "[Location]",
    "timestamp": "[Scan Timestamp]"
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <a href="attendance.php?action=qr_codes" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-qrcode me-2"></i> Generate QR Code
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* QR Generate Form Styles */
.preview-box {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
}

.preview-box code {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 1rem;
    font-size: 0.875rem;
    color: var(--text-primary);
    white-space: pre-wrap;
    word-break: break-all;
}

.form-text {
    color: var(--text-muted);
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

.alert-info {
    background: rgba(23, 162, 184, 0.1);
    border: 1px solid rgba(23, 162, 184, 0.3);
    color: var(--text-primary);
}

.alert-heading {
    color: var(--info-color);
    margin-bottom: 1rem;
}

.form-check {
    margin-bottom: 1rem;
}

.form-check-input:checked + .form-check-label {
    color: var(--accent-color);
}

.form-check-label strong {
    display: block;
    margin-bottom: 0.25rem;
}

/* Responsive design */
@media (max-width: 768px) {
    .col-md-4, .col-md-6 {
        margin-bottom: 1rem;
    }
}
</style>

<script>
// QR code settings preview
document.addEventListener('DOMContentLoaded', function() {
    // Update preview based on form changes
    const formInputs = ['title', 'description', 'event_id', 'start_date', 'end_date', 'location'];

    formInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', updatePreview);
            input.addEventListener('change', updatePreview);
        }
    });
});

function updatePreview() {
    const title = document.getElementById('title')?.value || 'Sample Title';
    const event_id = document.getElementById('event_id')?.value || '123';
    const start_date = document.getElementById('start_date')?.value || '2024-01-01';
    const end_date = document.getElementById('end_date')?.value || '2024-01-01';
    const location = document.getElementById('location')?.value || 'Main Auditorium';

    const previewData = {
        type: "attendance",
        id: "[AUTO-GENERATED]",
        title: title,
        event_id: event_id,
        start_date: start_date,
        end_date: end_date,
        location: location,
        timestamp: "[Scan Timestamp]"
    };

    const previewCode = document.querySelector('.preview-box code');
    if (previewCode) {
        previewCode.textContent = JSON.stringify(previewData, null, 2);
    }
}

// QR code visual preview (simplified)
function generateVisualPreview() {
    // In a real implementation, this would generate an actual QR code
    // For now, just show a placeholder
    const previewContainer = document.getElementById('visualPreview');
    if (previewContainer) {
        previewContainer.innerHTML = '<div class="qr-visual-preview"><i class="fas fa-qrcode fa-4x text-muted"></i><br><small class="text-muted">QR code preview will appear here</small></div>';
    }
}

// Clear form data
<?php if (isset($_SESSION['form_data'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    <?php unset($_SESSION['form_data']); ?>
});
<?php endif; ?>
</script>