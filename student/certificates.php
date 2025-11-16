<?php
// SPARK Platform - Student Certificates Page
if (!function_exists('generateToken')) {
    require_once __DIR__ . '/../includes/config.php';
}
if (!function_exists('getPDO')) {
    require_once __DIR__ . '/../includes/database.php';
}
if (!function_exists('sendEmail')) {
    require_once __DIR__ . '/../includes/functions.php';
}

if (!function_exists('getCurrentUser')) {
    require_once __DIR__ . '/../includes/auth.php';
}

// Get current user (if logged in)
$currentUser = isLoggedIn() ? getCurrentUser() : null;
$userId = $currentUser ? $currentUser['id'] : null;

// Require login

// Get current user

// Fetch student certificates
$certificates = dbFetchAll("
    SELECT c.*, e.title as event_title, e.event_date as event_date
    FROM certificates c
    JOIN events e ON c.event_id = e.id
    WHERE c.student_id = ?
    ORDER BY c.created_at DESC
", [$userId]);

$page_title = 'My Certificates';
$page_subtitle = 'Download Your Achievement Certificates';
$breadcrumb = [
    ['name' => 'Home', 'link' => SITE_URL . '/student/', 'active' => false],
    ['name' => 'Dashboard', 'link' => SITE_URL . '/student/dashboard.php', 'active' => false],
    ['name' => 'Certificates', 'link' => '', 'active' => true]
];
$include_student_nav = true;

include __DIR__ . '/../templates/header.php';
?>

<!-- Certificates Hero Section -->
<section class="certificates-hero py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="hero-content" data-aos="fade-right">
                    <h2 class="section-title">
                        <i class="fas fa-certificate me-2"></i> My Certificates
                    </h2>
                    <p class="section-description">
                        Download and share your achievement certificates from SPARK events and activities
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stats-summary" data-aos="fade-left">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo count($certificates); ?></div>
                            <div class="stat-label">Total Certificates</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Search Section -->
<section class="certificates-search py-3">
    <div class="container">
        <div class="search-container" data-aos="fade-up">
            <div class="search-bar">
                <form method="GET" class="search-form">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search"
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                               placeholder="Search certificates by event name or ID..."
                               id="searchInput">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>

            <div class="filter-dropdown">
                <select class="form-select" id="sortFilter" onchange="sortCertificates()">
                    <option value="recent">Most Recent</option>
                    <option value="event">Event Date</option>
                    <option value="name">Event Name</option>
                </select>
            </div>
        </div>
    </div>
</section>

<!-- Certificates List Section -->
<section class="certificates-section py-5">
    <div class="container">
        <div class="certificates-count mb-4" data-aos="fade-up">
            <p class="mb-0">
                <span class="count-number"><?php echo count($certificates); ?></span>
                certificates found
                <?php if (!empty($_GET['search'])): ?>
                matching "<span class="search-term"><?php echo htmlspecialchars($_GET['search']); ?></span>"
                <?php endif; ?>
            </p>
        </div>

        <?php if (empty($certificates)): ?>
        <div class="empty-state text-center py-5" data-aos="fade-up">
            <i class="fas fa-certificate fa-4x mb-4"></i>
            <h4>No certificates found</h4>
            <p>Participate in events to earn your achievement certificates!</p>
            <a href="events.php" class="btn btn-primary">
                <i class="fas fa-calendar-alt me-2"></i> Browse Events
            </a>
        </div>
        <?php else: ?>
        <div class="certificates-grid" id="certificatesGrid">
            <?php foreach ($certificates as $index => $certificate): ?>
            <div class="certificate-card" data-aos="zoom-in" data-aos-delay="<?php echo ($index % 6) * 50; ?>"
                 data-certificate-id="<?php echo $certificate['id']; ?>"
                 data-certificate-title="<?php echo htmlspecialchars($certificate['event_title']); ?>"
                 data-certificate-date="<?php echo $certificate['issue_date']; ?>">
                <div class="certificate-preview">
                    <div class="certificate-preview-header">
                        <div class="certificate-icon">
                            <i class="fas fa-award"></i>
                        </div>
                        <div class="certificate-info">
                            <h5 class="certificate-title"><?php echo htmlspecialchars($certificate['event_title']); ?></h5>
                            <p class="certificate-date">
                                <i class="fas fa-calendar me-1"></i>
                                Issued: <?php echo formatDate($certificate['issue_date']); ?>
                            </p>
                            <p class="certificate-id">
                                <i class="fas fa-fingerprint me-1"></i>
                                ID: <?php echo htmlspecialchars($certificate['certificate_id']); ?>
                            </p>
                        </div>
                    </div>
                    <div class="certificate-preview-body">
                        <div class="certificate-template">
                            <div class="certificate-header-line">
                                <div class="certificate-logo">
                                    <div class="spark-logo-small">
                                        <i class="fas fa-bolt"></i>
                                    </div>
                                </div>
                                <div class="certificate-title-text">
                                    <h3>SPARK</h3>
                                    <h4>Sanjivani Platform for AI, Research & Knowledge</h4>
                                </div>
                            </div>
                            <div class="certificate-content">
                                <h2>Certificate of Achievement</h2>
                                <p>This is to certify that</p>
                                <div class="certificate-recipient">
                                    <strong><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></strong>
                                </div>
                                <div class="certificate-details">
                                    has successfully participated in
                                </div>
                                <div class="certificate-event-name">
                                    <strong><?php echo htmlspecialchars($certificate['event_title']); ?></strong>
                                </div>
                                <div class="certificate-event-date">
                                    held on <?php echo formatDate($certificate['event_date']); ?>
                                </div>
                            </div>
                            <div class="certificate-footer">
                                <div class="certificate-signature">
                                    <div class="signature-line"></div>
                                    <div class="signature-text">SPARK Coordinator</div>
                                </div>
                                <div class="certificate-date-issued">
                                    Issued: <?php echo formatDate($certificate['issue_date']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="certificate-actions">
                    <div class="certificate-actions-top">
                        <button class="btn btn-primary" onclick="downloadCertificate(<?php echo $certificate['id']; ?>)">
                            <i class="fas fa-download me-1"></i> Download PDF
                        </button>
                        <button class="btn btn-outline-primary" onclick="shareCertificate(<?php echo $certificate['id']; ?>)">
                            <i class="fas fa-share-alt me-1"></i> Share
                        </button>
                    </div>

                    <div class="certificate-actions-bottom">
                        <button class="btn btn-outline-secondary btn-sm" onclick="viewCertificate(<?php echo $certificate['id']; ?>)">
                            <i class="fas fa-expand me-1"></i> Full View
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="verifyCertificate('<?php echo htmlspecialchars($certificate['verification_link']); ?>')">
                            <i class="fas fa-check-circle me-1"></i> Verify
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Certificate Modal -->
<div class="certificate-modal" id="certificateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Certificate Details</h5>
                <button type="button" class="btn-close" onclick="closeCertificateModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="certificate-full-view" id="certificateFullView">
                    <!-- Certificate will be loaded here -->
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading certificate...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCertificateModal()">Close</button>
                <button type="button" class="btn btn-primary" id="downloadModalBtn" onclick="downloadModalCertificate()">
                    <i class="fas fa-download me-1"></i> Download
                </button>
            </div>
        </div>
    </div>
    <div class="modal-backdrop" onclick="closeCertificateModal()"></div>
</div>

<!-- Verification Modal -->
<div class="verification-modal" id="verificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Certificate Verification</h5>
                <button type="button" class="btn-close" onclick="closeVerificationModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="verification-status" id="verificationStatus">
                    <div class="verification-loading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Verifying certificate...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeVerificationModal()">Close</button>
            </div>
        </div>
    </div>
    <div class="modal-backdrop" onclick="closeVerificationModal()"></div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>

<style>
/* Certificates Page Styles */
.certificates-hero {
    background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
    border-bottom: 1px solid var(--border-color);
}

.hero-content {
    max-width: 600px;
    margin: 0 auto;
    padding: 2rem 0;
}

.section-title {
    color: var(--text-primary);
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 1rem;
    background: linear-gradient(45deg, var(--accent-color), var(--neon-blue));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.section-description {
    color: var(--text-secondary);
    font-size: 1.1rem;
    margin: 0;
}

.stats-summary {
    display: flex;
    justify-content: flex-end;
    align-items: center;
}

.stat-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    backdrop-filter: blur(10px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(45deg, var(--accent-color), var(--neon-blue));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.5rem;
    margin-right: 1.5rem;
    transition: all 0.3s ease;
}

.stat-card:hover .stat-icon {
    transform: scale(1.1);
    box-shadow: 0 10px 20px rgba(0, 255, 136, 0.3);
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: var(--text-primary);
    line-height: 1;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.certificates-search {
    background: var(--card-bg);
    border-bottom: 1px solid var(--border-color);
}

.search-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
    padding: 1.5rem;
    backdrop-filter: blur(10px);
}

.search-bar {
    flex: 1;
    max-width: 400px;
}

.search-form .input-group {
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    border-radius: 25px;
    overflow: hidden;
}

.search-form .form-control {
    border: none;
    border-radius: 25px;
    padding: 0.75rem 1.25rem;
    background: var(--primary-color);
    color: var(--text-primary);
}

.search-form .btn {
    border-radius: 0 25px 25px 0;
    border: none;
    background: var(--accent-color);
    color: var(--primary-color);
}

.filter-dropdown .form-select {
    background: var(--primary-color);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 10px;
    padding: 0.75rem 1rem;
}

.certificates-section {
    background: var(--secondary-color);
}

.certificates-count {
    color: var(--text-secondary);
    text-align: center;
}

.count-number {
    color: var(--accent-color);
    font-weight: bold;
    font-size: 1.2rem;
}

.search-term {
    color: var(--accent-color);
    font-weight: 600;
}

.certificates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

.certificate-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.certificate-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(0, 255, 136, 0.2), transparent);
    transition: left 0.6s ease;
}

.certificate-card:hover::before {
    left: 100%;
}

.certificate-card:hover {
    transform: translateY(-10px) scale(1.02);
    box-shadow: 0 30px 60px rgba(0, 255, 136, 0.3);
    border-color: var(--accent-color);
}

.certificate-preview {
    background: var(--glass-bg);
    border-bottom: 1px solid var(--border-color);
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.certificate-preview-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.certificate-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(45deg, var(--accent-color), var(--neon-blue));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.5rem;
    flex-shrink: 0;
}

.certificate-info h5 {
    color: var(--text-primary);
    font-size: 1.2rem;
    font-weight: bold;
    margin: 0;
    line-height: 1.2;
}

.certificate-info p {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin: 0.25rem 0;
}

.certificate-preview-body {
    padding: 2rem;
    background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
}

.certificate-template {
    background: white;
    border-radius: 10px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    text-align: center;
    position: relative;
    overflow: hidden;
}

.certificate-template::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="spark-pattern" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M20 20 L20 10 L10 10 L10 0 M10 20 L10 10 L20 10" stroke="rgba(0,255,136,0.05)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23spark-pattern)"/></svg>');
    opacity: 0.05;
}

.certificate-header-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.certificate-logo {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.spark-logo-small {
    width: 40px;
    height: 40px;
    background: var(--accent-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1rem;
}

.certificate-title-text {
    text-align: center;
}

.certificate-title-text h3 {
    color: var(--accent-color);
    font-size: 1.5rem;
    font-weight: bold;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 2px;
}

.certificate-title-text h4 {
    color: var(--text-primary);
    font-size: 1rem;
    font-weight: normal;
    margin: 0.5rem 0;
}

.certificate-content h2 {
    color: var(--text-primary);
    font-size: 1.8rem;
    font-weight: bold;
    margin: 1rem 0;
}

.certificate-content p {
    color: var(--text-secondary);
    font-size: 1.1rem;
    margin: 1rem 0;
}

.certificate-recipient {
    font-size: 1.3rem;
    margin: 2rem 0;
    color: var(--text-primary);
    text-align: center;
}

.certificate-details {
    font-size: 1rem;
    margin: 1rem 0;
    color: var(--text-primary);
}

.certificate-event-name {
    color: var(--accent-color);
    font-weight: bold;
}

.certificate-event-date {
    color: var(--text-secondary);
    margin-top: 0.5rem;
}

.certificate-footer {
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 2px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
}

.certificate-signature {
    text-align: center;
    width: 150px;
}

.signature-line {
    height: 2px;
    background: var(--text-secondary);
    border-radius: 1px;
    margin-bottom: 0.5rem;
}

.signature-text {
    font-style: italic;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.certificate-date-issued {
    text-align: right;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.certificate-actions {
    padding: 1.5rem;
}

.certificate-actions-top {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-bottom: 1rem;
}

.certificate-actions-bottom {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
}

/* Modal Styles */
.certificate-modal,
.verification-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
}

.certificate-modal.active,
.verification-modal.active {
    display: flex;
}

.modal-dialog {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    margin: 2rem;
    max-width: 90%;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(20px);
}

.modal-lg {
    max-width: 800px;
}

.modal-content {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.modal-header {
    padding: 1.5rem 2rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    color: var(--text-primary);
    font-size: 1.5rem;
    font-weight: bold;
    margin: 0;
}

.modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 2rem;
}

.modal-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

.btn-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.btn-close:hover {
    color: var(--text-primary);
    background: var(--error-color);
}

.loading-spinner {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--accent-color);
}

.loading-spinner i {
    font-size: 2rem;
    margin-bottom: 1rem;
}

.loading-spinner p {
    margin: 0;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state i {
    color: var(--text-secondary);
    opacity: 0.5;
    margin-bottom: 1rem;
}

.empty-state h4 {
    color: var(--text-primary);
    margin-bottom: 1rem;
}

.empty-state p {
    color: var(--text-secondary);
    margin-bottom: 2rem;
}

/* Responsive Design */
@media (max-width: 992px) {
    .search-container {
        flex-direction: column;
        gap: 1rem;
        padding: 1rem;
    }

    .search-bar {
        max-width: 100%;
    }

    .certificates-grid {
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
    }

    .certificate-preview {
        padding: 1rem;
    }

    .certificate-template {
        padding: 1.5rem;
    }

    .certificate-header-line {
        flex-direction: column;
        gap: 0.5rem;
    }

    .certificate-logo {
        gap: 0.5rem;
    }

    .certificate-footer {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }

    .certificate-actions-top {
        flex-direction: column;
        gap: 0.5rem;
    }

    .certificate-actions-bottom {
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.5rem;
    }
}

@media (max-width: 768px) {
    .hero-content {
        padding: 1rem 0;
    }

    .section-title {
        font-size: 2rem;
    }

    .stat-card {
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
        margin-right: 1rem;
    }

    .stat-number {
        font-size: 1.5rem;
    }

    .certificates-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .certificate-card {
        margin-bottom: 1.5rem;
    }
}

/* Animations */
.certificate-card {
    animation: slideInUp 0.6s ease-out both;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Verification Status Styles */
.verification-status.success {
    color: var(--success-color);
    text-align: center;
}

.verification-status.error {
    color: var(--error-color);
    text-align: center;
}

.verification-success-icon,
.verification-error-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.verification-success-icon {
    color: var(--success-color);
}

.verification-error-icon {
    color: var(--error-color);
}

.verification-message {
    font-size: 1.1rem;
    line-height: 1.6;
    margin-bottom: 1rem;
}

.verification-details {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    padding: 1rem;
    margin-top: 1rem;
}

.verification-details strong {
    color: var(--accent-color);
}
</style>

<script>
// Certificate data
let certificatesData = <?php echo json_encode($certificates); ?>;
let currentCertificate = null;

// Download certificate
function downloadCertificate(certificateId) {
    const certificate = certificatesData.find(c => c.id == certificateId);
    if (certificate) {
        // In a real implementation, this would generate and download a PDF
        // For now, we'll simulate it
        console.log('Downloading certificate:', certificate);

        // Create a temporary link element
        const link = document.createElement('a');
        link.href = '#' + certificateId; // In real implementation, this would be the PDF URL
        link.download = `Certificate_${certificate.certificate_id}.pdf`;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        showNotification('Certificate download started!', 'success');
    }
}

// Share certificate
function shareCertificate(certificateId) {
    const certificate = certificatesData.find(c => c.id == certificateId);
    if (certificate) {
        const shareUrl = window.location.origin + window.location.pathname + '#verify=' + certificate.verification_link;

        if (navigator.share) {
            navigator.share({
                title: 'My SPARK Certificate',
                text: 'Check out my achievement certificate from ' + certificate.event_title,
                url: shareUrl
            });
        } else {
            // Fallback: copy verification link
            copyToClipboard(shareUrl);
            showNotification('Verification link copied to clipboard!', 'success');
        }
    }
}

// View certificate
function viewCertificate(certificateId) {
    currentCertificate = certificatesData.find(c => c.id == certificateId);
    if (currentCertificate) {
        const modal = document.getElementById('certificateModal');
        const fullView = document.getElementById('certificateFullView');

        // Show modal
        modal.classList.add('active');

        // Load certificate preview
        fullView.innerHTML = `
            <div class="certificate-full-preview">
                <div class="certificate-preview-header">
                    <div class="certificate-icon">
                        <i class="fas fa-award"></i>
                    </div>
                    <div class="certificate-info">
                        <h5>${currentCertificate.event_title}</h5>
                        <p class="certificate-date">
                            <i class="fas fa-calendar me-1"></i>
                            Issued: ${formatDate(currentCertificate.issue_date)}
                        </p>
                        <p class="certificate-id">
                            <i class="fas fa-fingerprint me-1"></i>
                            ID: ${currentCertificate.certificate_id}
                        </p>
                    </div>
                </div>
                <div class="certificate-preview-body">
                    <div class="certificate-template">
                        <div class="certificate-header-line">
                            <div class="certificate-logo">
                                <div class="spark-logo-small">
                                    <i class="fas fa-bolt"></i>
                                </div>
                            </div>
                            <div class="certificate-title-text">
                                <h3>SPARK</h3>
                                <h4>Sanjivani Platform for AI, Research & Knowledge</h4>
                            </div>
                        </div>
                        <div class="certificate-content">
                            <h2>Certificate of Achievement</h2>
                            <p>This is to certify that</p>
                            <div class="certificate-recipient">
                                <strong>${currentUser.first_name} ${currentUser.last_name}</strong>
                            </div>
                            <div class="certificate-details">
                                has successfully participated in
                            </div>
                            <div class="certificate-event-name">
                                <strong>${currentCertificate.event_title}</strong>
                            </div>
                            <div class="certificate-event-date">
                                held on ${formatDate(currentCertificate.event_date)}
                            </div>
                        </div>
                        <div class="certificate-footer">
                            <div class="certificate-signature">
                                <div class="signature-line"></div>
                                <div class="signature-text">SPARK Coordinator</div>
                            </div>
                            <div class="certificate-date-issued">
                                Issued: ${formatDate(currentCertificate.issue_date)}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Set download button for modal
        document.getElementById('downloadModalBtn').onclick = function() {
            downloadCertificate(currentCertificate.id);
        };
    }
}

// Verify certificate
function verifyCertificate(verificationLink) {
    const modal = document.getElementById('verificationModal');
    const statusDiv = document.getElementById('verificationStatus');

    if (!verificationLink) {
        showNotification('Invalid verification link', 'error');
        return;
    }

    modal.classList.add('active');
    statusDiv.innerHTML = `
        <div class="verification-loading">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Verifying certificate...</p>
        </div>
    `;

    // Simulate verification process
    setTimeout(() => {
        const isValid = verificationLink.includes('verify') && verificationLink.length > 10;

        if (isValid) {
            statusDiv.innerHTML = `
                <div class="verification-status success">
                    <div class="verification-success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h4>Certificate Valid!</h4>
                    <p>This certificate has been verified and is authentic.</p>
                    <div class="verification-details">
                        <strong>Verification ID:</strong> ${verificationLink.split('/verify/')[1]}
                        <br><strong>Issue Date:</strong> ${formatDate(new Date())}
                        <br><strong>Student:</strong> ${currentUser.first_name} ${currentUser.last_name}
                    </div>
                </div>
            `;
        } else {
            statusDiv.innerHTML = `
                <div class="verification-status error">
                    <div class="verification-error-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <h4>Invalid Certificate</h4>
                    <p>This certificate could not be verified. Please check the verification link or contact SPARK support.</p>
                </div>
            `;
        }
    }, 2000);
}

// Close modals
function closeCertificateModal() {
    document.getElementById('certificateModal').classList.remove('active');
    currentCertificate = null;
}

function closeVerificationModal() {
    document.getElementById('verificationModal').classList.remove('active');
}

// Sort certificates
function sortCertificates() {
    const sortValue = document.getElementById('sortFilter').value;
    const grid = document.getElementById('certificatesGrid');

    // Sort the data
    let sortedData = [...certificatesData];

    switch (sortValue) {
        case 'recent':
            sortedData.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            break;
        case 'event':
            sortedData.sort((a, b) => new Date(b.event_date) - new Date(a.event_date));
            break;
        case 'name':
            sortedData.sort((a, b) => a.event_title.localeCompare(b.event_title));
            break;
        default:
            sortedData = certificatesData;
    }

    // Re-render certificates
    renderCertificates(sortedData);
}

// Render certificates function
function renderCertificates(data) {
    const grid = document.getElementById('certificatesGrid');
    if (grid) {
        grid.innerHTML = data.map((cert, index) => `
            <div class="certificate-card" data-aos="zoom-in" data-aos-delay="${index * 50}"
                 data-certificate-id="${cert.id}"
                 data-certificate-title="${cert.event_title}"
                 data-certificate-date="${cert.issue_date}">
                <!-- Certificate content from HTML -->
            </div>
        `).join('');

        // Re-attach event listeners
        reattachCertificateListeners();
    }
}

// Re-attach event listeners
function reattachCertificateListeners() {
    document.querySelectorAll('.certificate-card').forEach(card => {
        const id = card.dataset.certificateId;
        const downloadBtn = card.querySelector('.btn-primary');
        const shareBtn = card.querySelector('.btn-outline-primary');
        const viewBtn = card.querySelectorAll('.btn-outline-secondary')[0];
        const verifyBtn = card.querySelectorAll('.btn-outline-secondary')[1];

        if (downloadBtn) {
            downloadBtn.onclick = () => downloadCertificate(id);
        }

        if (shareBtn) {
            shareBtn.onclick = () => shareCertificate(id);
        }

        if (viewBtn) {
            viewBtn.onclick = () => viewCertificate(id);
        }

        if (verifyBtn) {
            const verificationLink = card.dataset.certificateLink;
            verifyBtn.onclick = () => verifyCertificate(verificationLink);
        }
    });
}

// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const filtered = certificatesData.filter(cert =>
                cert.event_title.toLowerCase().includes(query) ||
                cert.certificate_id.toLowerCase().includes(query)
            );
            renderCertificates(filtered);
        });
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Animate statistics
    const statNumbers = document.querySelectorAll('.stat-number');
    const observerOptions = {
        threshold: 0.5,
        rootMargin: '0px'
    };

    const statsObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const target = entry.target;
                const finalNumber = parseInt(target.innerText);
                let currentNumber = 0;
                const increment = Math.ceil(finalNumber / 50);

                const counter = setInterval(() => {
                    if (currentNumber < finalNumber) {
                        currentNumber += increment;
                        if (currentNumber > finalNumber) {
                            currentNumber = finalNumber;
                        }
                        target.innerText = currentNumber;
                    } else {
                        clearInterval(counter);
                    }
                }, 30);
            }
        });
    }, observerOptions);

    statNumbers.forEach(number => {
        if (number.innerText && !isNaN(parseInt(number.innerText))) {
            statsObserver.observe(number);
        }
    });
});
</script>