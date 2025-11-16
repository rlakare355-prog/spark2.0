<?php
// SPARK Platform - Student Opportunities Hub Page
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

// Fetch opportunities
$search = sanitize($_GET['search'] ?? '');
$type = sanitize($_GET['type'] ?? '');
$location = sanitize($_GET['location'] ?? '');
$techStack = sanitize($_GET['tech_stack'] ?? '');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 9;

// Build where conditions
$whereConditions = [
    '(o.end_date IS NULL OR o.end_date >= CURDATE())'
];
$params = [];

if (!empty($search)) {
    $whereConditions[] = '(o.title LIKE ? OR o.description LIKE ? OR o.organizer LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($type)) {
    $whereConditions[] = 'o.type = ?';
    $params[] = $type;
}

if (!empty($location)) {
    $whereConditions[] = '(o.location LIKE ? OR o.organizer LIKE ?)';
    $params[] = "%$location%";
    $params[] = "%$location%";
}

if (!empty($techStack)) {
    $whereConditions[] = 'JSON_CONTAINS(o.tech_stack, ?)';
    $params[] = json_encode($techStack);
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Count total opportunities
$countSql = "SELECT COUNT(*) as total FROM opportunities o $whereClause";
$totalResult = dbFetch($countSql, $params);
$totalOpportunities = $totalResult['total'];

// Fetch opportunities with pagination
$opportunitiesSql = "
    SELECT o.*, u.first_name, u.last_name
    FROM opportunities o
    LEFT JOIN students u ON o.created_by = u.id
    $whereClause
    ORDER BY o.is_featured DESC, o.end_date ASC, o.created_at DESC
    LIMIT ? OFFSET ?
";

$opportunities = dbFetchAll($opportunitiesSql, array_merge($params, [$perPage, ($page - 1) * $perPage]));

// Get unique tech stacks and locations for filters
$techStacks = dbFetchAll("
    SELECT DISTINCT tech_stack
    FROM opportunities
    WHERE tech_stack IS NOT NULL AND tech_stack != '[]'
    ORDER BY created_at DESC
");

$locations = dbFetchAll("
    SELECT DISTINCT location, organizer
    FROM opportunities
    WHERE location IS NOT NULL AND location != ''
    ORDER BY location ASC
");

$page_title = 'Opportunities Hub';
$page_subtitle = 'Discover Career Growth Opportunities';
$breadcrumb = [
    ['name' => 'Home', 'link' => SITE_URL . '/student/', 'active' => false],
    ['name' => 'Opportunities', 'link' => '', 'active' => true]
];
$include_student_nav = true;

include __DIR__ . '/../templates/header.php';
?>

<!-- Opportunities Hero Section -->
<section class="opportunities-hero py-4">
    <div class="container">
        <div class="hero-content text-center" data-aos="fade-up">
            <h1 class="hero-title">
                <i class="fas fa-briefcase me-2"></i> Opportunities Hub
            </h1>
            <p class="hero-subtitle">
                Explore internships, hackathons, jobs, research, and events from top companies
            </p>
            <div class="hero-stats" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $totalOpportunities; ?></span>
                    <span class="stat-label">Active Opportunities</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo dbCount('opportunities', 'is_featured = 1'); ?></span>
                    <span class="stat-label">Featured</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Filters Section -->
<section class="opportunities-filters py-3">
    <div class="container">
        <div class="filters-container" data-aos="fade-up">
            <div class="row g-3">
                <!-- Search -->
                <div class="col-md-6">
                    <label class="filter-label">Search Opportunities</label>
                    <div class="search-bar">
                        <form method="GET" class="search-form">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search"
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Search opportunities...">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Type Filter -->
                <div class="col-md-6">
                    <label class="filter-label">Opportunity Type</label>
                    <select class="form-select" onchange="window.location.href='?type=' + this.value">
                        <option value="">All Types</option>
                        <option value="internship" <?php echo $type === 'internship' ? 'selected' : ''; ?>>
                            <i class="fas fa-briefcase me-1"></i> Internships
                        </option>
                        <option value="hackathon" <?php echo $type === 'hackathon' ? 'selected' : ''; ?>>
                            <i class="fas fa-code me-1"></i> Hackathons
                        </option>
                        <option value="event" <?php echo $type === 'event' ? 'selected' : ''; ?>>
                            <i class="fas fa-calendar-alt me-1"></i> Events
                        </option>
                        <option value="research" <?php echo $type === 'research' ? 'selected' : ''; ?>>
                            <i class="fas fa-flask me-1"></i> Research
                        </option>
                        <option value="job" <?php echo $type === 'job' ? 'selected' : ''; ?>>
                            <i class="fas fa-user-tie me-1"></i> Jobs
                        </option>
                    </select>
                </div>
            </div>

            <div class="row g-3">
                <!-- Location Filter -->
                <div class="col-md-6">
                    <label class="filter-label">Location</label>
                    <select class="form-select" onchange="window.location.href='?location=' + this.value">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo htmlspecialchars($loc['location']); ?>"
                                <?php echo $location === $loc['location'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc['location']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tech Stack Filter -->
                <div class="col-md-6">
                    <label class="filter-label">Technology Stack</label>
                    <select class="form-select" onchange="window.location.href='?tech_stack=' + this.value">
                        <option value="">All Technologies</option>
                        <?php foreach ($techStacks as $tech): ?>
                        <?php
                        $techArray = json_decode($tech['tech_stack'], true) ?? [];
                        foreach ($techArray as $tech): ?>
                        <option value="<?php echo htmlspecialchars($tech); ?>"
                                <?php echo $techStack === $tech ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tech); ?>
                        </option>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="active-filters">
                        <strong>Active Filters:</strong>
                        <?php $activeFilters = []; ?>
                        <?php if (!empty($search)): ?>
                            <?php $activeFilters[] = 'Search: ' . htmlspecialchars($search); ?>
                        <?php endif; ?>
                        <?php if (!empty($type)): ?>
                            <?php $activeFilters[] = 'Type: ' . ucfirst($type); ?>
                        <?php endif; ?>
                        <?php if (!empty($location)): ?>
                            <?php $activeFilters[] = 'Location: ' . htmlspecialchars($location); ?>
                        <?php endif; ?>
                        <?php if (!empty($techStack)): ?>
                            <?php $activeFilters[] = 'Tech: ' . htmlspecialchars($techStack); ?>
                        <?php endif; ?>

                        <?php if (!empty($activeFilters)): ?>
                            <span class="filter-tags">
                                <?php echo implode(' • ', $activeFilters); ?>
                            </span>
                        <?php else: ?>
                            <span class="no-filters">No active filters</span>
                        <?php endif; ?>

                        <?php if (!empty($activeFilters)): ?>
                            <a href="opportunities.php" class="clear-filters">
                                <i class="fas fa-times-circle me-1"></i> Clear All
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Opportunities Grid Section -->
<section class="opportunities-section py-4">
    <div class="container">
        <div class="opportunities-count mb-4" data-aos="fade-up">
            <p class="mb-0">
                <span class="count-number"><?php echo $totalOpportunities; ?></span>
                opportunities found
                <?php if (!empty($search) || !empty($type) || !empty($location) || !empty($techStack)): ?>
                <a href="opportunities.php" class="clear-filters">
                    <i class="fas fa-times-circle me-1"></i> Clear filters
                </a>
                <?php endif; ?>
            </p>
        </div>

        <?php if (empty($opportunities)): ?>
        <div class="empty-state text-center py-5" data-aos="fade-up">
            <i class="fas fa-search fa-4x mb-4"></i>
            <h4>No opportunities found</h4>
            <p>Try adjusting your search or filter criteria</p>
            <a href="opportunities.php" class="btn btn-primary">
                <i class="fas fa-sync-alt me-2"></i> Clear All Filters
            </a>
        </div>
        <?php else: ?>
        <div class="opportunities-grid">
            <?php foreach ($opportunities as $index => $opportunity): ?>
            <div class="opportunity-card" data-aos="zoom-in" data-aos-delay="<?php echo ($index % 9) * 50; ?>">
                <?php if ($opportunity['is_featured']): ?>
                <div class="featured-badge">
                    <i class="fas fa-star"></i> Featured
                </div>
                <?php endif; ?>

                <div class="opportunity-header">
                    <div class="opportunity-type <?php echo getTypeClass($opportunity['type']); ?>">
                        <i class="<?php echo getTypeIcon($opportunity['type']); ?> me-1"></i>
                        <?php echo ucfirst($opportunity['type']); ?>
                    </div>

                    <?php if ($opportunity['end_date']): ?>
                    <div class="deadline-alert <?php echo getDeadlineClass($opportunity['end_date']); ?>">
                        <i class="fas fa-clock me-1"></i>
                        <?php echo getTimeRemaining($opportunity['end_date']); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="opportunity-body">
                    <h4 class="opportunity-title">
                        <a href="<?php echo htmlspecialchars($opportunity['apply_link']); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo htmlspecialchars($opportunity['title']); ?>
                        </a>
                    </h4>

                    <div class="opportunity-meta">
                        <div class="meta-item">
                            <i class="fas fa-building me-1"></i>
                            <strong><?php echo htmlspecialchars($opportunity['organizer']); ?></strong>
                        </div>

                        <?php if ($opportunity['location']): ?>
                        <div class="meta-item">
                            <i class="fas fa-map-marker-alt me-1"></i>
                            <?php echo htmlspecialchars($opportunity['location']); ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($opportunity['start_date'] && $opportunity['end_date']): ?>
                        <div class="meta-item">
                            <i class="fas fa-calendar-alt me-1"></i>
                            <?php echo formatDate($opportunity['start_date']); ?> - <?php echo formatDate($opportunity['end_date']); ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($opportunity['description'])): ?>
                    <p class="opportunity-description">
                        <?php echo truncateText(htmlspecialchars($opportunity['description']), 200); ?>
                    </p>
                    <?php endif; ?>

                    <?php if (!empty($opportunity['tech_stack'])): ?>
                    <div class="tech-stack">
                        <strong>Technologies:</strong>
                        <div class="tech-tags">
                            <?php
                            $techs = json_decode($opportunity['tech_stack'], true) ?? [];
                            foreach ($techs as $tech):
                            ?>
                            <span class="tech-tag"><?php echo htmlspecialchars($tech); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="opportunity-footer">
                    <a href="<?php echo htmlspecialchars($opportunity['apply_link']); ?>"
                       class="btn btn-primary"
                       target="_blank"
                       rel="noopener noreferrer">
                        <i class="fas fa-external-link-alt me-1"></i>
                        Apply Now
                    </a>

                    <div class="opportunity-actions">
                        <button class="btn btn-outline-secondary btn-sm" onclick="saveOpportunity(<?php echo $opportunity['id']; ?>)">
                            <i class="fas fa-bookmark"></i>
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="shareOpportunity(<?php echo $opportunity['id']; ?>)">
                            <i class="fas fa-share-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalOpportunities > $perPage): ?>
        <div class="pagination-container" data-aos="fade-up">
            <nav>
                <ul class="pagination justify-content-center">
                    <?php
                    $totalPages = ceil($totalOpportunities / $perPage);
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

<!-- Notification Toast -->
<div class="notification-toast" id="notificationToast">
    <div class="toast-content">
        <div class="toast-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="toast-message"></div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>

<?php
// Helper functions
function getTypeClass($type) {
    $classes = [
        'internship' => 'internship',
        'hackathon' => 'hackathon',
        'event' => 'event',
        'research' => 'research',
        'job' => 'job'
    ];
    return $classes[$type] ?? 'opportunity';
}

function getTypeIcon($type) {
    $icons = [
        'internship' => 'fas fa-briefcase',
        'hackathon' => 'fas fa-code',
        'event' => 'fas fa-calendar-alt',
        'research' => 'fas fa-flask',
        'job' => 'fas fa-user-tie'
    ];
    return $icons[$type] ?? 'fas fa-external-link-alt';
}

function getDeadlineClass($endDate) {
    $days = floor((strtotime($endDate) - time()) / (60 * 60 * 24));
    if ($days < 3) return 'urgent';
    if ($days < 7) return 'warning';
    return 'normal';
}

function getTimeRemaining($endDate) {
    $days = floor((strtotime($endDate) - time()) / (60 * 60 * 24));
    if ($days <= 0) return 'Deadline Passed';
    if ($days === 1) return '1 Day Left';
    return $days . ' Days Left';
}
?>

<style>
/* Opportunities Page Styles */
.opportunities-hero {
    background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
    border-bottom: 1px solid var(--border-color);
}

.hero-content {
    max-width: 800px;
    margin: 0 auto;
    padding: 3rem 0;
}

.hero-title {
    color: var(--text-primary);
    font-size: 3rem;
    font-weight: bold;
    margin-bottom: 1rem;
    background: linear-gradient(45deg, var(--accent-color), var(--neon-blue));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-subtitle {
    color: var(--text-secondary);
    font-size: 1.2rem;
    margin-bottom: 2rem;
}

.hero-stats {
    display: flex;
    gap: 3rem;
    justify-content: center;
    flex-wrap: wrap;
}

.stat-item {
    text-align: center;
    padding: 1.5rem 2rem;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    backdrop-filter: blur(10px);
}

.stat-number {
    display: block;
    font-size: 2.5rem;
    font-weight: bold;
    color: var(--accent-color);
    margin-bottom: 0.5rem;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.opportunities-filters {
    background: var(--card-bg);
    border-bottom: 1px solid var(--border-color);
}

.filters-container {
    padding: 1.5rem;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    backdrop-filter: blur(10px);
}

.filter-label {
    color: var(--text-primary);
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
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
    padding: 0.75rem 1rem 0.75rem 3rem;
    background: var(--primary-color);
    color: var(--text-primary);
}

.search-form .btn {
    border-radius: 0 25px 25px 0;
    border: none;
    background: var(--accent-color);
    color: var(--primary-color);
}

.form-select {
    background: var(--primary-color);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 10px;
    padding: 0.75rem;
}

.form-select:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 20px rgba(0, 255, 136, 0.3);
}

.active-filters {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    margin-top: 1rem;
}

.active-filters strong {
    color: var(--text-primary);
    margin-right: 1rem;
}

.filter-tags {
    color: var(--accent-color);
    font-weight: 600;
}

.no-filters {
    color: var(--text-secondary);
}

.clear-filters {
    color: var(--neon-pink);
    text-decoration: none;
    transition: all 0.3s ease;
}

.clear-filters:hover {
    color: var(--error-color);
}

.opportunities-section {
    min-height: 50vh;
}

.opportunities-count {
    color: var(--text-secondary);
    text-align: center;
}

.count-number {
    color: var(--accent-color);
    font-weight: bold;
    font-size: 1.3rem;
}

.opportunities-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 2rem;
}

.opportunity-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
    backdrop-filter: blur(10px);
}

.opportunity-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(0, 255, 136, 0.1), transparent);
    transition: left 0.6s ease;
}

.opportunity-card:hover::before {
    left: 100%;
}

.opportunity-card:hover {
    transform: translateY(-10px) scale(1.02);
    box-shadow: 0 30px 60px rgba(0, 255, 136, 0.3);
    border-color: var(--accent-color);
}

.featured-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: var(--accent-color);
    color: var(--primary-color);
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
    font-weight: bold;
    z-index: 10;
    clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%);
}

.opportunity-header {
    padding: 1.5rem 1.5rem 0 0;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.opportunity-type {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.opportunity-type.internship {
    background: var(--info-color);
    color: var(--text-primary);
}

.opportunity-type.hackathon {
    background: var(--warning-color);
    color: var(--text-primary);
}

.opportunity-type.event {
    background: var(--neon-blue);
    color: var(--text-primary);
}

.opportunity-type.research {
    background: var(--neon-pink);
    color: var(--text-primary);
}

.opportunity-type.job {
    background: var(--accent-color);
    color: var(--primary-color);
}

.deadline-alert {
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-weight: 600;
}

.deadline-alert.urgent {
    background: var(--error-color);
    color: var(--text-primary);
    animation: pulse 2s ease-in-out infinite;
}

.deadline-alert.warning {
    background: var(--warning-color);
    color: var(--text-primary);
}

.deadline-alert.normal {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    color: var(--text-primary);
}

.opportunity-body {
    padding: 0 1.5rem;
}

.opportunity-title {
    margin-bottom: 1rem;
    line-height: 1.3;
}

.opportunity-title a {
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 1.2rem;
    font-weight: bold;
}

.opportunity-title a:hover {
    color: var(--accent-color);
    text-decoration: underline;
}

.opportunity-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.meta-item {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.meta-item strong {
    color: var(--text-primary);
    margin-right: 0.5rem;
}

.opportunity-description {
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 1rem;
}

.tech-stack {
    margin-bottom: 1rem;
}

.tech-stack strong {
    color: var(--text-primary);
    font-size: 0.9rem;
    display: block;
    margin-bottom: 0.5rem;
}

.tech-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.tech-tag {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    padding: 0.25rem 0.75rem;
    font-size: 0.8rem;
    color: var(--text-secondary);
    transition: all 0.3s ease;
}

.tech-tag:hover {
    background: var(--accent-color);
    color: var(--primary-color);
    border-color: var(--accent-color);
}

.opportunity-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.opportunity-actions {
    display: flex;
    gap: 0.5rem;
}

/* Notification Toast */
.notification-toast {
    position: fixed;
    top: 2rem;
    right: 2rem;
    z-index: 9999;
    transform: translateX(400px);
    transition: transform 0.3s ease;
}

.notification-toast.show {
    transform: translateX(0);
}

.toast-content {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: var(--card-bg);
    border: 1px solid var(--accent-color);
    border-radius: 10px;
    padding: 1rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
}

.toast-icon {
    width: 24px;
    height: 24px;
    background: var(--accent-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
}

.toast-message {
    color: var(--text-primary);
    font-weight: 600;
}

/* Responsive Design */
@media (max-width: 992px) {
    .hero-title {
        font-size: 2.5rem;
    }

    .hero-stats {
        gap: 1.5rem;
    }

    .stat-item {
        padding: 1rem 1.5rem;
    }

    .stat-number {
        font-size: 2rem;
    }

    .opportunities-grid {
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .opportunity-header {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }

    .opportunity-actions {
        flex-direction: column;
        width: 100%;
    }
}

@media (max-width: 768px) {
    .hero-content {
        padding: 2rem 0;
    }

    .hero-title {
        font-size: 2rem;
    }

    .hero-subtitle {
        font-size: 1.1rem;
        margin-bottom: 1.5rem;
    }

    .hero-stats {
        flex-direction: column;
        gap: 1rem;
    }

    .stat-item {
        padding: 1rem;
    }

    .stat-number {
        font-size: 1.8rem;
    }

    .opportunities-grid {
        grid-template-columns: 1fr;
    }

    .opportunity-footer {
        flex-direction: column;
        gap: 1rem;
    }

    .opportunity-actions {
        width: 100%;
        justify-content: center;
    }

    .notification-toast {
        top: 1rem;
        right: 1rem;
        left: 1rem;
        right: auto;
        transform: translateY(-100px);
    }

    .notification-toast.show {
        transform: translateY(0);
    }
}

/* Animations */
.opportunity-card {
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

/* Button hover effects */
.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 255, 136, 0.3);
}

.btn-outline-secondary:hover {
    border-color: var(--accent-color);
    color: var(--accent-color);
    background: var(--accent-color);
}

.btn-outline-secondary:hover i {
    color: var(--primary-color);
}

/* Loading state */
.loading .opportunity-card {
    pointer-events: none;
    opacity: 0.6;
}

/* Empty state styling */
.empty-state {
    padding: 4rem 2rem;
    text-align: center;
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
</style>

<script>
// Opportunities data
let savedOpportunities = JSON.parse(localStorage.getItem('savedOpportunities')) || [];

// Save opportunity to bookmarks
function saveOpportunity(opportunityId) {
    if (!savedOpportunities.includes(opportunityId)) {
        savedOpportunities.push(opportunityId);
        localStorage.setItem('savedOpportunities', JSON.stringify(savedOpportunities));
        showNotification('Opportunity saved to bookmarks!', 'success');
    } else {
        showNotification('Opportunity already bookmarked!', 'info');
    }
}

// Share opportunity
function shareOpportunity(opportunityId) {
    const shareUrl = window.location.origin + window.location.pathname + '?id=' + opportunityId;

    if (navigator.share) {
        navigator.share({
            title: 'SPARK Opportunity',
            text: 'Check out this amazing opportunity from SPARK!',
            url: shareUrl
        });
    } else {
        // Fallback
        copyToClipboard(shareUrl);
        showNotification('Link copied to clipboard!', 'success');
    }
}

// Show notification
function showNotification(message, type = 'success') {
    const toast = document.getElementById('notificationToast');
    const messageElement = toast.querySelector('.toast-message');
    const iconElement = toast.querySelector('.toast-icon i');

    // Update content
    messageElement.textContent = message;

    // Update icon and colors based on type
    if (type === 'success') {
        iconElement.className = 'fas fa-check-circle';
        iconElement.style.color = 'var(--primary-color)';
        iconElement.style.background = 'var(--success-color)';
    } else if (type === 'error') {
        iconElement.className = 'fas fa-exclamation-circle';
        iconElement.style.color = 'var(--text-primary)';
        iconElement.style.background = 'var(--error-color)';
    } else if (type === 'info') {
        iconElement.className = 'fas fa-info-circle';
        iconElement.style.color = 'var(--text-primary)';
        iconElement.style.background = 'var(--info-color)';
    }

    // Show toast
    toast.classList.add('show');

    // Auto-hide after 3 seconds
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// Check for bookmarked opportunities
function checkBookmarkStatus() {
    const cards = document.querySelectorAll('.opportunity-card');
    cards.forEach(card => {
        const id = card.dataset.id;
        if (id && savedOpportunities.includes(parseInt(id))) {
            card.classList.add('bookmarked');
        }
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Check bookmark status
    checkBookmarkStatus();

    // Animate opportunity cards on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const opportunityObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animation = 'slideInUp 0.6s ease-out both';
                opportunityObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.opportunity-card').forEach(card => {
        opportunityObserver.observe(card);
    });

    // Type filter animations
    document.querySelectorAll('.form-select').forEach((select, index) => {
        select.style.animationDelay = `${index * 0.1}s`;
        select.style.animation = 'slideInUp 0.6s ease-out both';
    });

    // Search form validation
    const searchForm = document.querySelector('.search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            const searchInput = this.querySelector('input[name="search"]');
            if (searchInput && searchInput.value.trim().length < 3) {
                e.preventDefault();
                showNotification('Please enter at least 3 characters to search', 'warning');
            }
        });
    }

    // Clear filters on any filter change with empty value
    document.querySelectorAll('.form-select').forEach(select => {
        select.addEventListener('change', function() {
            if (this.value === '') {
                // Clear other filters when one is cleared
                document.querySelectorAll('.form-select').forEach(otherSelect => {
                    if (otherSelect !== this) {
                        otherSelect.value = '';
                    }
                });
            }
        });
    });

    // Pagination keyboard navigation
    document.addEventListener('keydown', function(e) {
        const links = document.querySelectorAll('.pagination .page-link:not(.disabled)');
        const currentIndex = Array.from(links).findIndex(link => link.getBoundingClientRect().top > window.innerHeight / 2);

        if (e.key === 'ArrowLeft' && currentIndex > 0) {
            links[currentIndex - 1].click();
        } else if (e.key === 'ArrowRight' && currentIndex < links.length - 1) {
            links[currentIndex + 1].click();
        }
    });

    // Add lazy loading for opportunity images (if any)
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.classList.add('loaded');
                        imageObserver.unobserve(img);
                    }
                }
            });
        });

        document.querySelectorAll('img[loading="lazy"]').forEach(img => {
            imageObserver.observe(img);
        });
    }
});
</script>