<?php
// SPARK Platform - Student Research Projects Page
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

// Get current user if logged in
$currentUser = isLoggedIn() ? getCurrentUser() : null;
$userId = $currentUser ? $currentUser['id'] : null;

// Helper function for status class
function getStatusClass($status) {
    switch ($status) {
        case 'active':
            return 'status-active';
        case 'completed':
            return 'status-completed';
        case 'on_hold':
            return 'status-hold';
        default:
            return 'status-default';
    }
}

// Fetch research projects
$search = sanitize($_GET['search'] ?? '');
$techStack = sanitize($_GET['tech_stack'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 6;

// Build where conditions
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = '(rp.title LIKE ? OR rp.description LIKE ? OR rp.tech_stack LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($techStack)) {
    $whereConditions[] = 'JSON_CONTAINS(rp.tech_stack, ?)';
    $params[] = json_encode($techStack);
}

if (!empty($status)) {
    $whereConditions[] = 'rp.status = ?';
    $params[] = $status;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Count total projects
$countSql = "SELECT COUNT(*) as total FROM research_projects rp $whereClause";
$totalResult = dbFetch($countSql, $params);
$totalProjects = $totalResult['total'];

// Fetch projects with pagination
$projectsSql = "
    SELECT rp.*,
           s.first_name as coordinator_first, s.last_name as coordinator_last,
           sl.first_name as lead_first, sl.last_name as lead_last,
           COUNT(pm.id) as member_count,
           GROUP_CONCAT(pm.status, ',') as status_distribution
    FROM research_projects rp
    LEFT JOIN project_members pm ON rp.id = pm.project_id
    LEFT JOIN students s ON rp.coordinator_id = s.id
    LEFT JOIN students sl ON rp.domain_lead_id = sl.id
    $whereClause
    GROUP BY rp.id
    ORDER BY rp.created_at DESC
    LIMIT ? OFFSET ?
";

$projects = dbFetchAll($projectsSql, array_merge($params, [$perPage, ($page - 1) * $perPage]));

$page_title = 'Research Projects';
$page_subtitle = 'Collaborate on Innovation and Research';
$breadcrumb = [
    ['name' => 'Home', 'link' => SITE_URL . '/student/', 'active' => false],
    ['name' => 'Research', 'link' => '', 'active' => true]
];
$include_student_nav = true;

include __DIR__ . '/../templates/header.php';
?>

<!-- Research Hero Section -->
<section class="research-hero py-4">
    <div class="container">
        <div class="hero-content text-center" data-aos="fade-up">
            <h1 class="hero-title">
                <i class="fas fa-flask me-2"></i> Research Projects
            </h1>
            <p class="hero-subtitle">
                Collaborate on cutting-edge research and innovation projects
            </p>
            <div class="hero-features" data-aos="fade-up" data-aos-delay="100">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-microscope"></i>
                    </div>
                    <div class="feature-text">
                        <h3>Live Projects</h3>
                        <p>Work on ongoing research with experienced mentors</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="feature-text">
                        <h3>Collaboration</h3>
                        <p>Team up with peers on exciting challenges</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="feature-text">
                        <h3>Publication</h3>
                        <p>Share your findings with the community</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Filters Section -->
<section class="research-filters py-3">
    <div class="container">
        <div class="filters-container" data-aos="fade-up">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="filter-label">Search Projects</label>
                    <div class="search-bar">
                        <form method="GET" class="search-form">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search"
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Search research projects...">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="filter-label">Technology Stack</label>
                    <select class="form-select" onchange="window.location.href='?tech_stack=' + this.value">
                        <option value="">All Technologies</option>
                        <?php
                        $allTechStacks = [];
                        foreach ($projects as $project) {
                            if (!empty($project['tech_stack'])) {
                                $techs = json_decode($project['tech_stack'], true) ?? [];
                                foreach ($techs as $tech) {
                                    $allTechStacks[] = $tech;
                                }
                            }
                        }
                        $uniqueTechs = array_unique($allTechStacks);
                        sort($uniqueTechs);
                        foreach ($uniqueTechs as $tech): ?>
                        <option value="<?php echo htmlspecialchars($tech); ?>"
                                <?php echo $techStack === $tech ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tech); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

                <div class="col-md-6">
                    <label class="filter-label">Project Status</label>
                    <select class="form-select" onchange="window.location.href='?status=' + this.value">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="on_hold" <?php echo $status === 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
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
                        <?php if (!empty($techStack)): ?>
                            <?php $activeFilters[] = 'Tech: ' . htmlspecialchars($techStack); ?>
                        <?php endif; ?>
                        <?php if (!empty($status)): ?>
                            <?php $activeFilters[] = 'Status: ' . ucfirst($status); ?>
                        <?php endif; ?>

                        <?php if (!empty($activeFilters)): ?>
                            <span class="filter-tags">
                                <?php echo implode(' • ', $activeFilters); ?>
                            </span>
                        <?php else: ?>
                            <span class="no-filters">No active filters</span>
                        <?php endif; ?>

                        <?php if (!empty($activeFilters)): ?>
                            <a href="research.php" class="clear-filters">
                                <i class="fas fa-times-circle me-1"></i> Clear All
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Projects Section -->
<section class="research-section py-4">
    <div class="container">
        <div class="projects-count mb-4" data-aos="fade-up">
            <p class="mb-0">
                <span class="count-number"><?php echo $totalProjects; ?></span>
                research projects found
                <?php if (!empty($search) || !empty($techStack) || !empty($status)): ?>
                <a href="research.php" class="clear-filters">
                    <i class="fas fa-times-circle me-1"></i> Clear filters
                </a>
                <?php endif; ?>
            </p>
        </div>

        <?php if (empty($projects)): ?>
        <div class="empty-state text-center py-5" data-aos="fade-up">
            <i class="fas fa-flask fa-4x mb-4"></i>
            <h4>No research projects found</h4>
            <p>Try adjusting your search or filter criteria</p>
            <a href="research.php" class="btn btn-primary">
                <i class="fas fa-sync-alt me-2"></i> Clear All Filters
            </a>
        </div>
        <?php else: ?>
        <div class="projects-grid">
            <?php foreach ($projects as $index => $project): ?>
            <div class="project-card" data-aos="zoom-in" data-aos-delay="<?php echo ($index % 6) * 100; ?>">
                <?php if (!empty($project['is_featured'])): ?>
                <div class="featured-badge">
                    <i class="fas fa-star"></i> Featured
                </div>
                <?php endif; ?>

                <div class="project-header">
                    <div class="project-title">
                        <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                        <div class="project-status <?php echo getStatusClass($project['status']); ?>">
                            <?php echo ucfirst($project['status']); ?>
                        </div>
                    </div>
                    <div class="project-meta">
                        <div class="meta-item">
                            <i class="fas fa-user-tie"></i>
                            <span class="meta-label">Coordinator:</span>
                            <?php if (!empty($project['coordinator_first'])): ?>
                            <span class="meta-value"><?php echo htmlspecialchars($project['coordinator_first'] . ' ' . $project['coordinator_last']); ?></span>
                            <?php else: ?>
                            <span class="meta-value">Not Assigned</span>
                            <?php endif; ?>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-user-graduate"></i>
                            <span class="meta-label">Domain Lead:</span>
                            <?php if (!empty($project['lead_first'])): ?>
                            <span class="meta-value"><?php echo htmlspecialchars($project['lead_first'] . ' ' . $project['lead_last']); ?></span>
                            <?php else: ?>
                            <span class="meta-value">Not Assigned</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="project-body">
                    <p class="project-description">
                        <?php echo truncateText(htmlspecialchars($project['description']), 200); ?>
                    </p>

                    <?php if (!empty($project['tech_stack'])): ?>
                    <div class="tech-stack">
                        <span class="tech-label">Technologies:</span>
                        <div class="tech-tags">
                            <?php
                            $techs = json_decode($project['tech_stack'], true) ?? [];
                            foreach ($techs as $tech):
                            ?>
                            <span class="tech-tag"><?php echo htmlspecialchars($tech); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="project-footer">
                    <div class="project-stats">
                        <div class="stat-item">
                            <i class="fas fa-users"></i>
                            <span class="stat-value"><?php echo $project['member_count']; ?></span>
                            <span class="stat-label">Members</span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-check-circle"></i>
                            <span class="stat-value">
                                <?php
                                if ($project['status_distribution']) {
                                    $statuses = explode(',', $project['status_distribution']);
                                    $acceptedCount = count(array_filter($statuses, fn($s) => $s === 'accepted'));
                                    echo $acceptedCount . '/' . $project['member_count'];
                                } else {
                                    echo '0/' . $project['member_count'];
                                }
                                ?>
                            </span>
                            <span class="stat-label">Accepted</span>
                        </div>
                    </div>

                    <div class="project-dates">
                        <div class="date-item">
                            <i class="fas fa-play-circle"></i>
                            <span class="date-label">Started:</span>
                            <span class="date-value"><?php echo formatDate($project['created_at']); ?></span>
                        </div>
                        <?php if (!empty($project['end_date']) && $project['status'] === 'completed'): ?>
                        <div class="date-item">
                            <i class="fas fa-check-circle"></i>
                            <span class="date-label">Completed:</span>
                            <span class="date-value"><?php echo formatDate($project['end_date']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="project-actions">
                    <?php if (isLoggedIn()): ?>
                        <?php
                        // Check if user is already a member
                        $isMember = dbFetch("
                            SELECT COUNT(*) as is_member
                            FROM project_members
                            WHERE project_id = ? AND student_id = ? AND status = 'accepted'
                        ", [$project['id'], $userId]);

                        if ($isMember['is_member'] > 0) {
                            $statusText = 'Already Joined';
                            $buttonClass = 'btn btn-secondary';
                            $buttonIcon = 'fa-check';
                            $buttonText = 'Joined';
                        } else {
                            // Check if there's a pending request
                            $hasPendingRequest = dbFetch("
                                SELECT COUNT(*) as has_pending
                                FROM project_members
                                WHERE project_id = ? AND student_id = ? AND status = 'pending'
                            ", [$project['id'], $userId]);

                            if ($hasPendingRequest['has_pending'] > 0) {
                                $statusText = 'Request Pending';
                                $buttonClass = 'btn btn-warning';
                                $buttonIcon = 'fa-clock';
                                $buttonText = 'Pending';
                            } else {
                                // Check if project is accepting members
                                $isAccepting = $project['member_count'] < $project['max_members'];
                                $statusText = 'Available to Join';
                                $buttonClass = 'btn btn-primary';
                                $buttonIcon = 'fa-plus';
                                $buttonText = 'Join Project';
                            }
                        }
                        ?>
                        <div class="join-status">
                            <span class="status-text"><?php echo $statusText; ?></span>
                        </div>
                    <?php endif; ?>

                    <button class="btn btn-outline-secondary" onclick="viewProjectDetails(<?php echo $project['id']; ?>)">
                        <i class="fas fa-info-circle me-1"></i> Details
                    </button>

                    <button class="btn btn-outline-secondary" onclick="shareProject(<?php echo $project['id']; ?>)">
                        <i class="fas fa-share-alt me-1"></i> Share
                    </button>
                </div>
            </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalProjects > $perPage): ?>
        <div class="pagination-container" data-aos="fade-up">
            <nav>
                <ul class="pagination justify-content-center">
                    <?php
                    $totalPages = ceil($totalProjects / $perPage);
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

<!-- Join Request Modal -->
<div class="join-modal" id="joinModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Join Research Project</h5>
                <button type="button" class="btn-close" onclick="closeJoinModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="joinForm" class="needs-validation">
                    <input type="hidden" name="project_id" id="projectId">
                    <div class="form-group mb-3">
                        <label for="student_message" class="form-label">Your Message</label>
                        <textarea class="form-control" id="student_message" rows="4"
                                  placeholder="Tell us why you're interested in this project..."
                                  maxlength="1000"></textarea>
                        <div class="form-help">
                            <small>Share your skills and experience that would be valuable to this project.</small>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="agree_terms" required>
                            <label class="form-check-label" for="agree_terms">
                                I agree to the project terms and conditions and commit to contributing regularly
                            </label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeJoinModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="joinBtn">Send Join Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal-backdrop" onclick="closeJoinModal()"></div>
</div>

<!-- Project Details Modal -->
<div class="details-modal" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Project Details</h5>
                <button type="button" class="btn-close" onclick="closeDetailsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="detailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDetailsModal()">Close</button>
                <button type="button" class="btn btn-primary" id="detailsJoinBtn" onclick="joinFromDetails()">Join Project</button>
            </div>
        </div>
    </div>
    <div class="modal-backdrop" onclick="closeDetailsModal()"></div>
</div>

<!-- Success Modal -->
<div class="success-modal" id="successModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content text-center">
            <div class="success-icon">
                <i class="fas fa-check-circle fa-3x"></i>
            </div>
            <h3>Request Sent!</h3>
            <p>Your join request has been submitted successfully. The project coordinator will review your application.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="closeSuccessModal()">Close</button>
        </div>
    </div>
    <div class="modal-backdrop" onclick="closeSuccessModal()"></div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>

<style>
/* Research Projects Page Styles */
.research-hero {
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
    font-size: 2.5rem;
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

.hero-features {
    display: flex;
    gap: 3rem;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 2rem;
}

.feature-item {
    text-align: center;
    max-width: 200px;
}

.feature-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(45deg, var(--accent-color), var(--neon-blue));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.feature-item:hover .feature-icon {
    transform: scale(1.1);
    box-shadow: 0 10px 20px rgba(0, 255, 136, 0.3);
}

.feature-text h3 {
    color: var(--text-primary);
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.feature-text p {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.research-filters {
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
    padding: 0.75rem 1rem;
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

.research-section {
    background: var(--secondary-color);
}

.projects-count {
    color: var(--text-secondary);
    text-align: center;
}

.count-number {
    color: var(--accent-color);
    font-weight: bold;
    font-size: 1.3rem;
}

.search-term {
    color: var(--neon-blue);
    font-weight: 600;
}

.projects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
}

.project-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
    backdrop-filter: blur(10px);
}

.project-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(0, 255, 136, 0.1), transparent);
    transition: left 0.6s ease;
}

.project-card:hover::before {
    left: 100%;
}

.project-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 25px 50px rgba(0, 255, 136, 0.3);
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
    clip-path: polygon(100% 0, 0 100% 100%, 0 100%);
}

.project-header {
    padding: 1.5rem 1.5rem 0 0;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.project-title {
    flex: 1;
    color: var(--text-primary);
    font-size: 1.3rem;
    font-weight: bold;
    line-height: 1.3;
}

.project-status {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.project-status.active {
    background: var(--success-color);
    color: var(--text-primary);
}

.project-status.completed {
    background: var(--accent-color);
    color: var(--primary-color);
}

.project-status.on_hold {
    background: var(--warning-color);
    color: var(--text-primary);
}

.project-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}

.meta-label {
    color: var(--text-secondary);
    font-size: 0.8rem;
    min-width: 80px;
    text-align: right;
}

.meta-value {
    color: var(--text-primary);
    font-weight: 600;
    margin-left: 0.5rem;
}

.project-body {
    padding: 0 1.5rem 0;
}

.project-description {
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 1rem;
}

.tech-stack {
    margin-bottom: 1rem;
}

.tech-label {
    color: var(--text-primary);
    font-size: 0.9rem;
    font-weight: 600;
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

.project-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.project-stats {
    display: flex;
    gap: 2rem;
    margin-bottom: 1rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-align: center;
    min-width: 100px;
}

.stat-item i {
    color: var(--text-secondary);
    font-size: 1rem;
    margin-right: 0.5rem;
}

.stat-value {
    color: var(--accent-color);
    font-weight: bold;
    font-size: 1.1rem;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
    text-align: center;
}

.project-dates {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}

.date-item {
    display: flex;
    align-items: center;
    flex: 1;
    gap: 0.5rem;
}

.date-label {
    color: var(--text-secondary);
    font-size: 0.8rem;
    text-align: center;
    min-width: 80px;
}

.date-value {
    color: var(--text-primary);
    font-weight: 600;
    margin-left: 0.5rem;
}

.project-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.join-status {
    text-align: center;
    margin-top: 1rem;
    padding: 0.5rem;
    border-radius: 10px;
    background: var(--glass-bg);
}

.status-text {
    color: var(--text-primary);
    font-weight: 600;
}

/* Modal Styles */
.modal {
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

.modal.active {
    display: flex;
}

.modal-dialog {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    max-width: 90%;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(20px);
}

.modal-lg {
    max-width: 95%;
}

.modal-content {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.modal-header {
    padding: 1.5rem 2rem 1rem 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
}

.modal-title {
    color: var(--text-primary);
    font-size: 1.3rem;
    font-weight: bold;
    margin: 0;
}

.modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
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
    color: var(--error-color);
    background: rgba(255, 71, 87, 0.1);
}

.success-icon {
    width: 80px;
    height: 80px;
    background: var(--success-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 3rem;
    margin: 0 auto;
}

.details-content {
    padding: 2rem;
    background: var(--primary-color);
    border-radius: 10px;
    color: var(--text-primary);
}

.details-content h3 {
    color: var(--text-primary);
    margin-bottom: 1rem;
}

.details-content p {
    line-height: 1.6;
    margin-bottom: 1.5rem;
}

/* Responsive Design */
@media (max-width: 992px) {
    .hero-content {
        padding: 2rem 0;
    }

    .hero-title {
        font-size: 2rem;
    }

    .hero-features {
        gap: 2rem;
        flex-wrap: wrap;
    }

    .projects-grid {
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 1.5rem;
    }

    .project-card {
        margin-bottom: 1.5rem;
    }

    .project-header {
        flex-direction: column;
        gap: 0.5rem;
    }

    .project-title {
        font-size: 1.2rem;
    }

    .project-meta {
        gap: 0.25rem;
    }

    .project-footer {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }

    .project-actions {
        flex-direction: column;
        gap: 0.5rem;
    }

    .modal-dialog {
        margin: 1rem;
        max-width: 95%;
        max-height: 95vh;
    }
}

    .modal-lg {
        max-width: 98%;
        max-height: 95vh;
    }
}
</style>

<script>
// Projects data
let projectsData = <?php echo json_encode($projects); ?>;
let currentProject = null;

// Join project
function joinProject(projectId) {
    document.getElementById('projectId').value = projectId;
    const modal = document.getElementById('joinModal');
    modal.classList.add('active');
}

function closeJoinModal() {
    const modal = document.getElementById('joinModal');
    modal.classList.remove('active');
    document.getElementById('joinForm').reset();
}

function closeDetailsModal() {
    const modal = document.getElementById('detailsModal');
    modal.classList.remove('active');
}

function closeSuccessModal() {
    const modal = document.getElementById('successModal');
    modal.classList.remove('active');
}

// View project details
function viewProjectDetails(projectId) {
    const project = projectsData.find(p => p.id == projectId);
    if (project) {
        const modal = document.getElementById('detailsModal');
        const content = document.getElementById('detailsContent');
        const joinBtn = document.getElementById('detailsJoinBtn');

        currentProject = project;

        // Populate content
        content.innerHTML = `
            <h3>${project.title}</h3>
            <div class="details-stats">
                <div class="stat-item">
                    <i class="fas fa-users"></i>
                    <span>Members: ${project.member_count}</span>
                </div>
                <div class="stat-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Accepted: ${project.status_distribution ? array_count(array_filter(explode(',', project.status_distribution), fn(s) => s === 'accepted')) : 0}</span>
                </div>
            </div>
            <p class="project-description">${project.description}</p>

            <div class="tech-stack">
                <h4>Technologies:</h4>
                <div class="tech-tags">
                    ${!empty(project.tech_stack) ?
                        json_decode(project.tech_stack, true).map(tech => `<span class="tech-tag">${tech}</span>`).join('') : 'No tech stack specified'}
                    }
                </div>
            </div>

            <div class="project-dates">
                <div class="date-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Started:</span>
                    <span>${formatDate(project.created_at)}</span>
                </div>
                ${project.end_date && project.status === 'completed' ? `
                <div class="date-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Completed:</span>
                    <span>${formatDate(project.end_date)}</span>
                </div>
                ` : ''}
            </div>
            </div>
        `;

        // Update join button if needed
        if (joinBtn) {
            joinBtn.textContent = 'Join Project';
            joinBtn.classList.add('btn-primary');
            joinBtn.onclick = () => joinProject(projectId);
        }
    }

    modal.classList.add('active');
}

// Handle join form submission
document.getElementById('joinForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const projectId = document.getElementById('projectId').value;
    const message = document.getElementById('student_message').value;
    const agreeTerms = document.getElementById('agree_terms').checked;

    if (!projectId || !message || !agreeTerms) {
        showNotification('Please fill in all required fields', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('project_id', projectId);
    formData.append('student_id', <?php echo $userId; ?>);
    formData.append('message', message);

    const submitBtn = document.getElementById('joinBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Sending...';

    fetch('api/research.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeJoinModal();
            showSuccessModal();
        } else {
            showNotification(data.message || 'Failed to send request', 'error');
        }
    })
    .catch(error => {
        console.error('Join request error:', error);
        showNotification('Failed to send request', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send Join Request';
    });
});

// Join from details modal
function joinFromDetails() {
    if (currentProject) {
        joinProject(currentProject.id);
        closeDetailsModal();
    }
}

// Share project
function shareProject(projectId) {
    const project = projectsData.find(p => p.id == projectId);
    if (project) {
        const shareUrl = window.location.origin + window.location.pathname + '?id=' + projectId;
        const title = project.title;
        const description = project.description;

        if (navigator.share) {
            navigator.share({
                title: title,
                text: description,
                url: shareUrl
            });
        } else {
            // Fallback: copy link
            copyToClipboard(shareUrl);
            showNotification('Project link copied to clipboard!', 'success');
        }
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Animate project cards
    const projectCards = document.querySelectorAll('.project-card');
    projectCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.style.animation = 'slideInUp 0.6s ease-out both';
    });

    // Form validation
    const joinForm = document.getElementById('joinForm');
    if (joinForm) {
        joinForm.addEventListener('submit', function(e) {
            const message = document.getElementById('student_message');
            const agreeTerms = document.getElementById('agree_terms');

            if (message.value.length < 20) {
                message.setCustomValidity('Message must be at least 20 characters long');
            } else {
                message.setCustomValidity('');
            }

            agreeTerms.addEventListener('change', function() {
                const submitBtn = document.getElementById('joinBtn');
                submitBtn.disabled = !this.checked;
            });
        });
    }

    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            if (query.length >= 3) {
                filterProjects(query);
            }
        });
    }

    // Filter functionality
    const techFilter = document.getElementById('techStack');
    if (techFilter) {
        techFilter.addEventListener('change', function() {
            const tech = this.value;
            filterProjects(tech);
        });
    }

    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            const status = this.value;
            filterProjects('', status);
        });
    }

    // Intersection Observer for animations
    const projectObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animation = 'slideInUp 0.6s ease-out both';
                projectObserver.unobserve(entry.target);
            }
        });

    projectCards.forEach(card => {
        projectObserver.observe(card);
    });

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (document.getElementById('detailsModal').classList.contains('active')) {
            if (e.key === 'Escape') {
                closeDetailsModal();
            }
        }

        if (document.getElementById('joinModal').classList.contains('active')) {
            if (e.key === 'Escape') {
                closeJoinModal();
            }
        }

        if (document.getElementById('successModal').classList.contains('active')) {
            if (e.key === 'Escape') {
                closeSuccessModal();
            }
        }
    });
});

// Filter projects function
function filterProjects(searchQuery = '', statusFilter = '') {
    let filtered = projectsData;

    if (searchQuery) {
        filtered = filtered.filter(project =>
            project.title.toLowerCase().includes(searchQuery) ||
            project.description.toLowerCase().includes(searchQuery) ||
            (project.tech_stack && JSON.parse(project.tech_stack).some(tech => tech.toLowerCase().includes(searchQuery))
        );
    }

    if (statusFilter) {
        filtered = filtered.filter(project => project.status === statusFilter);
    }

    renderProjects(filtered);
}
</script>