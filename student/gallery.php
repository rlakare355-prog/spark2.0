<?php
// SPARK Platform - Student Gallery Page
if (!function_exists('generateToken')) {
    require_once __DIR__ . '/../includes/config.php';
}
if (!function_exists('getPDO')) {
    require_once __DIR__ . '/../includes/database.php';
}
if (!function_exists('sendEmail')) {
    require_once __DIR__ . '/../includes/functions.php';
}

// Fetch gallery images
$category = sanitize($_GET['category'] ?? '');
$search = sanitize($_GET['search'] ?? '');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 12;

// Build where conditions
$whereConditions = [];
$params = [];

if (!empty($category)) {
    $whereConditions[] = 'g.category = ?';
    $params[] = $category;
}

if (!empty($search)) {
    $whereConditions[] = '(g.title LIKE ? OR g.description LIKE ? OR g.tags LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Count total images
$countSql = "SELECT COUNT(*) as total FROM gallery g $whereClause";
$totalResult = dbFetch($countSql, $params);
$totalImages = $totalResult['total'];

// Fetch images with pagination
$imagesSql = "
    SELECT g.*, u.first_name, u.last_name
    FROM gallery g
    LEFT JOIN students u ON g.uploaded_by = u.id
    $whereClause
    ORDER BY g.created_at DESC
    LIMIT ? OFFSET ?
";

$images = dbFetchAll($imagesSql, array_merge($params, [$perPage, ($page - 1) * $perPage]));

// Get categories for filter
$categories = dbFetchAll("
    SELECT category, COUNT(*) as count
    FROM gallery
    WHERE category IS NOT NULL AND category != ''
    GROUP BY category
    ORDER BY count DESC
");

$page_title = 'Gallery';
$page_subtitle = 'Explore SPARK Events and Activities';
$breadcrumb = [
    ['name' => 'Home', 'link' => SITE_URL . '/student/', 'active' => false],
    ['name' => 'Gallery', 'link' => '', 'active' => true]
];
$include_student_nav = true;

include __DIR__ . '/../templates/header.php';
?>

<!-- Gallery Header Section -->
<section class="gallery-header py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="gallery-hero" data-aos="fade-right">
                    <h2 class="section-title">
                        <i class="fas fa-images me-2"></i> SPARK Gallery
                    </h2>
                    <p class="section-description">
                        Relive memorable moments from our events, workshops, and activities
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="gallery-controls" data-aos="fade-left">
                    <div class="view-toggle btn-group" role="group">
                        <button class="btn btn-outline-primary active" onclick="setGalleryView('masonry')">
                            <i class="fas fa-th"></i> Masonry
                        </button>
                        <button class="btn btn-outline-primary" onclick="setGalleryView('grid')">
                            <i class="fas fa-th-large"></i> Grid
                        </button>
                        <button class="btn btn-outline-primary" onclick="setGalleryView('list')">
                            <i class="fas fa-list"></i> List
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Filters Section -->
<section class="gallery-filters py-3">
    <div class="container">
        <div class="filters-container" data-aos="fade-up">
            <div class="row">
                <div class="col-md-6">
                    <div class="search-bar">
                        <form method="GET" class="search-form">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search"
                                       placeholder="Search images..."
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="category-filter">
                        <select class="form-select" onchange="window.location.href='?category=' + this.value">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                    <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?> (<?php echo $cat['count']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <?php if (!empty($category) || !empty($search)): ?>
            <div class="clear-filters">
                <a href="gallery.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times-circle me-2"></i> Clear Filters
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Gallery Section -->
<section class="gallery-section py-5">
    <div class="container">
        <div class="gallery-count mb-4" data-aos="fade-up">
            <p class="mb-0">
                <span class="count-number"><?php echo $totalImages; ?></span>
                photos found
                <?php if (!empty($category) || !empty($search)): ?>
                in <span class="filter-info">
                    <?php if (!empty($category)): ?><?php echo htmlspecialchars($category); ?><?php endif; ?>
                    <?php if (!empty($category) && !empty($search)): ?> & <?php endif; ?>
                    <?php if (!empty($search)): ?>"<?php echo htmlspecialchars($search); ?>"<?php endif; ?>
                </span>
                <?php endif; ?>
            </p>
        </div>

        <?php if (empty($images)): ?>
        <div class="empty-state text-center py-5" data-aos="fade-up">
            <i class="fas fa-images fa-4x mb-4"></i>
            <h4>No images found</h4>
            <p>Try adjusting your search or filter</p>
            <a href="gallery.php" class="btn btn-primary">
                <i class="fas fa-sync-alt me-2"></i> Show All Photos
            </a>
        </div>
        <?php else: ?>
        <div id="gallery-container" class="gallery-masonry" data-aos="fade-up">
            <?php foreach ($images as $index => $image): ?>
            <div class="gallery-item" data-aos="zoom-in" data-aos-delay="<?php echo ($index % 12) * 50; ?>">
                <div class="image-card">
                    <div class="image-container" onclick="openLightbox(<?php echo $image['id']; ?>)">
                        <?php if ($image['thumbnail_path']): ?>
                        <img src="<?php echo SITE_URL; ?>/assets/images/gallery/<?php echo htmlspecialchars($image['thumbnail_path']); ?>"
                             alt="<?php echo htmlspecialchars($image['title']); ?>"
                             class="gallery-image"
                             loading="lazy">
                        <?php else: ?>
                        <img src="<?php echo SITE_URL; ?>/assets/images/gallery/<?php echo htmlspecialchars($image['image_path']); ?>"
                             alt="<?php echo htmlspecialchars($image['title']); ?>"
                             class="gallery-image"
                             loading="lazy">
                        <?php endif; ?>

                        <div class="image-overlay">
                            <div class="overlay-content">
                                <div class="overlay-icon">
                                    <i class="fas fa-search-plus"></i>
                                </div>
                                <div class="overlay-info">
                                    <h5 class="overlay-title"><?php echo htmlspecialchars($image['title']); ?></h5>
                                    <?php if (!empty($image['description'])): ?>
                                    <p class="overlay-description">
                                        <?php echo truncateText(htmlspecialchars($image['description']), 100); ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="image-details">
                        <h5 class="image-title">
                            <?php echo htmlspecialchars($image['title']); ?>
                        </h5>

                        <div class="image-meta">
                            <span class="image-date">
                                <i class="fas fa-calendar-alt me-1"></i>
                                <?php echo formatDate($image['created_at']); ?>
                            </span>

                            <?php if ($image['category']): ?>
                            <span class="image-category">
                                <i class="fas fa-tag me-1"></i>
                                <?php echo htmlspecialchars($image['category']); ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($image['description'])): ?>
                        <p class="image-description">
                            <?php echo truncateText(htmlspecialchars($image['description']), 150); ?>
                        </p>
                        <?php endif; ?>

                        <?php if (!empty($image['tags'])): ?>
                        <div class="image-tags">
                            <?php
                            $tags = json_decode($image['tags'], true) ?? [];
                            foreach ($tags as $tag):
                            ?>
                            <span class="tag">
                                <?php echo htmlspecialchars($tag); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <div class="image-author">
                            <small>
                                <i class="fas fa-camera me-1"></i>
                                <?php echo htmlspecialchars($image['first_name'] . ' ' . $image['last_name']); ?>
                            </small>
                        </div>
                    </div>

                    <div class="image-actions">
                        <button class="btn btn-outline-primary btn-sm" onclick="downloadImage('<?php echo htmlspecialchars($image['image_path']); ?>')">
                            <i class="fas fa-download me-1"></i> Download
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="shareImage(<?php echo $image['id']; ?>)">
                            <i class="fas fa-share-alt me-1"></i> Share
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalImages > $perPage): ?>
        <div class="pagination-container" data-aos="fade-up">
            <nav>
                <ul class="pagination justify-content-center">
                    <?php
                    $totalPages = ceil($totalImages / $perPage);
                    $currentUrl = $_SERVER['REQUEST_URI'];
                    $currentUrl = preg_replace('/[?&]page=\d+/', '', $currentUrl);
                    $separator = strpos($currentUrl, '?') !== false ? '&' : '?';
                    ?>

                    <!-- Previous Button -->
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo $currentUrl . $separator . 'page=' . ($page - 1); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                    </li>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);

                    if ($startPage > 1) {
                        echo '<li class="page-item"><a class="page-link" href="' . $currentUrl . $separator . 'page=1">1</a></li>';
                        if ($startPage > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }

                    for ($i = $startPage; $i <= $endPage; $i++) {
                        if ($i == $page) {
                            echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                        } else {
                            echo '<li class="page-item"><a class="page-link" href="' . $currentUrl . $separator . 'page=' . $i . '">' . $i . '</a></li>';
                        }
                    }

                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="' . $currentUrl . $separator . 'page=' . $totalPages . '">' . $totalPages . '</a></li>';
                    }
                    ?>

                    <!-- Next Button -->
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo $currentUrl . $separator . 'page=' . ($page + 1); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Lightbox Modal -->
<div class="lightbox-modal" id="lightboxModal" tabindex="-1">
    <div class="lightbox-dialog">
        <div class="lightbox-content">
            <div class="lightbox-header">
                <h4 id="lightbox-title"></h4>
                <button class="lightbox-close" onclick="closeLightbox()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="lightbox-body">
                <div class="lightbox-image-container">
                    <img id="lightbox-image" src="" alt="">
                    <div class="lightbox-loading">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                </div>
                <div class="lightbox-info">
                    <p id="lightbox-description"></p>
                    <div class="lightbox-meta">
                        <span id="lightbox-date"></span>
                        <span id="lightbox-category"></span>
                        <span id="lightbox-author"></span>
                    </div>
                </div>
            </div>
            <div class="lightbox-navigation">
                <button class="lightbox-nav-btn lightbox-prev" onclick="navigateLightbox(-1)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="lightbox-nav-btn lightbox-next" onclick="navigateLightbox(1)">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="lightbox-backdrop" onclick="closeLightbox()"></div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>

<style>
/* Gallery Page Styles */
.gallery-header {
    background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
    border-bottom: 1px solid var(--border-color);
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

.gallery-controls {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 1rem;
}

.view-toggle .btn {
    border-radius: 25px;
    transition: all 0.3s ease;
}

.view-toggle .btn.active {
    background: var(--accent-color);
    color: var(--primary-color);
    border-color: var(--accent-color);
}

.gallery-filters {
    background: var(--card-bg);
    border-bottom: 1px solid var(--border-color);
}

.filters-container {
    padding: 1.5rem;
}

.search-bar {
    position: relative;
}

.search-form .input-group {
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    border-radius: 25px;
    overflow: hidden;
}

.search-form .form-control {
    border: none;
    border-radius: 25px;
    padding-left: 1.5rem;
}

.search-form .btn {
    border-radius: 0 25px 25px 0;
    border: none;
}

.category-filter .form-select {
    border-radius: 25px;
    border: 1px solid var(--border-color);
    background: var(--card-bg);
    color: var(--text-primary);
}

.clear-filters {
    text-align: center;
    margin-top: 1rem;
}

.gallery-count {
    color: var(--text-secondary);
    text-align: center;
}

.count-number {
    color: var(--accent-color);
    font-weight: bold;
    font-size: 1.2rem;
}

.filter-info {
    color: var(--neon-blue);
    font-weight: 600;
}

/* Gallery Layout */
.gallery-section {
    min-height: 60vh;
}

.gallery-masonry {
    column-count: 4;
    column-gap: 1.5rem;
    column-fill: balance;
}

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.gallery-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.gallery-item {
    break-inside: avoid;
    margin-bottom: 1.5rem;
}

.gallery-grid .gallery-item {
    break-inside: auto;
}

.gallery-list .gallery-item {
    display: flex;
    align-items: center;
    break-inside: auto;
}

.gallery-list .image-card {
    width: 300px;
    margin-right: 2rem;
}

.gallery-list .image-details {
    flex: 1;
}

.image-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    overflow: hidden;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.image-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 255, 136, 0.2);
    border-color: var(--accent-color);
}

.image-container {
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.gallery-image {
    width: 100%;
    height: auto;
    display: block;
    transition: transform 0.3s ease;
}

.image-card:hover .gallery-image {
    transform: scale(1.05);
}

.image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to bottom, transparent 0%, transparent 50%, rgba(0, 0, 0, 0.8) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.image-card:hover .image-overlay {
    opacity: 1;
}

.overlay-content {
    text-align: center;
    color: white;
}

.overlay-icon {
    font-size: 2rem;
    margin-bottom: 1rem;
    animation: bounce 1s ease-in-out infinite;
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(-10px);
    }
    60% {
        transform: translateY(-5px);
    }
}

.overlay-title {
    font-size: 1.2rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.overlay-description {
    margin: 0;
    opacity: 0.9;
}

.image-details {
    padding: 1.5rem;
}

.image-title {
    color: var(--text-primary);
    font-size: 1.1rem;
    font-weight: bold;
    margin-bottom: 1rem;
    line-height: 1.3;
}

.image-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
}

.image-date,
.image-category {
    color: var(--text-secondary);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
}

.image-date i,
.image-category i {
    color: var(--accent-color);
    margin-right: 0.5rem;
}

.image-description {
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 1rem;
}

.image-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.tag {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    padding: 0.25rem 0.75rem;
    font-size: 0.8rem;
    color: var(--text-secondary);
    transition: all 0.3s ease;
}

.tag:hover {
    background: var(--accent-color);
    color: var(--primary-color);
}

.image-author {
    color: var(--text-secondary);
    font-size: 0.85rem;
}

.image-actions {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: 0.5rem;
}

/* Lightbox */
.lightbox-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: none;
}

.lightbox-modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.lightbox-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: -1;
}

.lightbox-dialog {
    position: relative;
    z-index: 1;
    max-width: 90%;
    max-height: 90%;
}

.lightbox-content {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(20px);
}

.lightbox-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem 1rem 2rem;
    border-bottom: 1px solid var(--border-color);
}

.lightbox-title {
    color: var(--text-primary);
    font-size: 1.5rem;
    font-weight: bold;
    margin: 0;
}

.lightbox-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 0.5rem;
    border-radius: 50%;
}

.lightbox-close:hover {
    color: var(--error-color);
    background: rgba(255, 71, 87, 0.1);
}

.lightbox-body {
    display: flex;
    align-items: flex-start;
    padding: 0;
    max-height: 70vh;
}

.lightbox-image-container {
    flex: 1;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 400px;
    background: var(--primary-color);
}

#lightbox-image {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    transition: opacity 0.3s ease;
}

.lightbox-loading {
    position: absolute;
    font-size: 3rem;
    color: var(--accent-color);
    animation: spin 1s linear infinite;
}

.lightbox-info {
    flex: 0 0 400px;
    padding: 2rem;
    max-height: 70vh;
    overflow-y: auto;
}

.lightbox-info p {
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 1rem;
}

.lightbox-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.lightbox-meta span {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.lightbox-navigation {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 100%;
    display: flex;
    justify-content: space-between;
    pointer-events: none;
    padding: 0 2rem;
}

.lightbox-modal.active .lightbox-navigation {
    pointer-events: all;
}

.lightbox-nav-btn {
    width: 50px;
    height: 50px;
    background: rgba(0, 0, 0, 0.7);
    border: none;
    border-radius: 50%;
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s ease;
    pointer-events: all;
}

.lightbox-nav-btn:hover {
    background: var(--accent-color);
    transform: scale(1.1);
}

.lightbox-nav-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .gallery-masonry {
        column-count: 3;
    }
}

@media (max-width: 900px) {
    .gallery-masonry {
        column-count: 2;
    }

    .lightbox-body {
        flex-direction: column;
    }

    .lightbox-info {
        flex: 1;
        max-width: none;
        max-height: 300px;
    }

    .lightbox-image-container {
        min-height: 300px;
    }
}

@media (max-width: 600px) {
    .gallery-masonry,
    .gallery-grid {
        column-count: 1;
        grid-template-columns: 1fr;
    }

    .gallery-list .image-card {
        width: 100%;
        margin-right: 0;
        margin-bottom: 1rem;
    }

    .gallery-list .image-details {
        padding: 1rem;
    }

    .lightbox-dialog {
        max-width: 95%;
        max-height: 95%;
    }

    .image-actions {
        flex-direction: column;
    }
}

/* Loading state */
.gallery-image[loading="lazy"] {
    opacity: 0;
    transition: opacity 0.3s ease;
}

.gallery-image.loaded {
    opacity: 1;
}

/* Animation */
.gallery-item {
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
</style>

<script>
// Gallery data
let galleryImages = <?php echo json_encode($images); ?>;
let currentImageIndex = -1;
let currentView = 'masonry';

// View management
function setGalleryView(view) {
    currentView = view;
    const container = document.getElementById('gallery-container');
    const buttons = document.querySelectorAll('.view-toggle .btn');

    // Update button states
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    // Update container class
    container.className = 'gallery-' + view;

    // Trigger reflow for masonry
    if (view === 'masonry') {
        setTimeout(() => {
            forceMasonryReflow();
        }, 100);
    }
}

// Lightbox functionality
function openLightbox(imageId) {
    const imageIndex = galleryImages.findIndex(img => img.id == imageId);
    if (imageIndex === -1) return;

    currentImageIndex = imageIndex;
    const image = galleryImages[imageIndex];

    // Populate lightbox
    document.getElementById('lightbox-title').textContent = image.title;
    document.getElementById('lightbox-description').textContent = image.description || '';
    document.getElementById('lightbox-image').src = '';
    document.getElementById('lightbox-image').onload = () => {
        document.querySelector('.lightbox-loading').style.display = 'none';
    };

    // Set image with delay for loading effect
    setTimeout(() => {
        document.querySelector('.lightbox-loading').style.display = 'block';
        const imagePath = image.thumbnail_path || image.image_path;
        document.getElementById('lightbox-image').src = '<?php echo SITE_URL; ?>/assets/images/gallery/' + imagePath;
    }, 100);

    // Set meta info
    const dateStr = new Date(image.created_at).toLocaleDateString('en-IN', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    document.getElementById('lightbox-date').innerHTML = `<i class="fas fa-calendar-alt me-1"></i> ${dateStr}`;
    document.getElementById('lightbox-category').innerHTML = `<i class="fas fa-tag me-1"></i> ${image.category || 'Uncategorized'}`;
    document.getElementById('lightbox-author').innerHTML = `<i class="fas fa-camera me-1"></i> ${image.first_name} ${image.last_name}`;

    // Show lightbox
    document.getElementById('lightboxModal').classList.add('active');
    updateNavigationButtons();
}

function closeLightbox() {
    document.getElementById('lightboxModal').classList.remove('active');
    currentImageIndex = -1;
}

function navigateLightbox(direction) {
    const newIndex = currentImageIndex + direction;
    if (newIndex >= 0 && newIndex < galleryImages.length) {
        openLightbox(galleryImages[newIndex].id);
    }
}

function updateNavigationButtons() {
    const prevBtn = document.querySelector('.lightbox-prev');
    const nextBtn = document.querySelector('.lightbox-next');

    if (prevBtn && nextBtn) {
        prevBtn.disabled = currentImageIndex <= 0;
        nextBtn.disabled = currentImageIndex >= galleryImages.length - 1;
    }
}

// Image download
function downloadImage(imagePath) {
    const link = document.createElement('a');
    link.href = '<?php echo SITE_URL; ?>/assets/images/gallery/' + imagePath;
    link.download = imagePath.split('/').pop();
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    showNotification('Image download started', 'success');
}

// Image share
function shareImage(imageId) {
    const shareUrl = window.location.origin + window.location.pathname + '?image=' + imageId;

    if (navigator.share) {
        navigator.share({
            title: 'SPARK Gallery Image',
            text: 'Check out this amazing photo from SPARK!',
            url: shareUrl
        });
    } else {
        // Fallback to copying link
        copyToClipboard(shareUrl);
        showNotification('Link copied to clipboard!', 'success');
    }
}

// Masonry reflow helper
function forceMasonryReflow() {
    const container = document.querySelector('.gallery-masonry');
    if (container) {
        const items = container.querySelectorAll('.gallery-item');
        items.forEach(item => {
            const rect = item.getBoundingClientRect();
            item.style.height = rect.height + 'px';
        });
    }
}

// Lazy loading
function initLazyLoading() {
    const images = document.querySelectorAll('.gallery-image[loading="lazy"]');
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src || img.src;
                img.onload = () => {
                    img.classList.add('loaded');
                };
                imageObserver.unobserve(img);
            }
        });
    });

    images.forEach(img => imageObserver.observe(img));
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (document.getElementById('lightboxModal').classList.contains('active')) {
        if (e.key === 'Escape') {
            closeLightbox();
        } else if (e.key === 'ArrowLeft') {
            navigateLightbox(-1);
        } else if (e.key === 'ArrowRight') {
            navigateLightbox(1);
        }
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    initLazyLoading();

    // Handle direct image URL
    const urlParams = new URLSearchParams(window.location.search);
    const imageId = urlParams.get('image');
    if (imageId) {
        setTimeout(() => {
            openLightbox(parseInt(imageId));
        }, 500);
    }

    // Initialize masonry after a short delay
    if (currentView === 'masonry') {
        setTimeout(forceMasonryReflow, 100);
    }

    // Gallery item hover effects
    document.querySelectorAll('.image-card').forEach((card, index) => {
        card.style.animationDelay = `${index * 0.05}s`;
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Window resize handler
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            if (currentView === 'masonry') {
                forceMasonryReflow();
            }
        }, 300);
    });
});
</script>