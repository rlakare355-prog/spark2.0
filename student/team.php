<?php
// SPARK Platform - Student Team Page
if (!function_exists('generateToken')) {
    require_once __DIR__ . '/../includes/config.php';
}
if (!function_exists('getPDO')) {
    require_once __DIR__ . '/../includes/database.php';
}
if (!function_exists('sendEmail')) {
    require_once __DIR__ . '/../includes/functions.php';
}

// Fetch team members
$category = sanitize($_GET['category'] ?? '');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 12;

// Build where conditions
$whereConditions = [];
$params = [];

if (!empty($category)) {
    $whereConditions[] = 'tm.category = ?';
    $params[] = $category;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Count team members
$countSql = "SELECT COUNT(*) as total FROM team_members tm $whereClause";
$totalResult = dbFetch($countSql, $params);
$totalMembers = $totalResult['total'];

// Fetch team members with pagination
$teamMembersSql = "
    SELECT tm.*, s.first_name, s.last_name, s.email,
           s.profile_image as student_profile_image
    FROM team_members tm
    LEFT JOIN students s ON tm.student_id = s.id
    $whereClause
    ORDER BY tm.display_order ASC, tm.created_at DESC
    LIMIT ? OFFSET ?
";

$teamMembers = dbFetchAll($teamMembersSql, array_merge($params, [$perPage, ($page - 1) * $perPage]));

// Get categories for filter
$categories = dbFetchAll("
    SELECT category, COUNT(*) as count
    FROM team_members
    WHERE category IS NOT NULL AND category != ''
    GROUP BY category
    ORDER BY category ASC
");

$page_title = 'Team';
$page_subtitle = 'Meet the SPARK Leadership and Members';
$breadcrumb = [
    ['name' => 'Home', 'link' => SITE_URL . '/student/', 'active' => false],
    ['name' => 'Team', 'link' => '', 'active' => true]
];
$include_student_nav = true;

include __DIR__ . '/../templates/header.php';
?>

<!-- Team Header Section -->
<section class="team-header py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="team-hero" data-aos="fade-right">
                    <h2 class="section-title">
                        <i class="fas fa-users me-2"></i> SPARK Team
                    </h2>
                    <p class="section-description">
                        Meet the passionate individuals driving innovation and excellence at SPARK
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="team-search" data-aos="fade-left">
                    <div class="search-bar">
                        <form method="GET" class="search-form">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search"
                                       placeholder="Search team members...">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Filter Section -->
<section class="categories-filter py-3">
    <div class="container">
        <div class="categories-container" data-aos="fade-up">
            <div class="category-pills">
                <a href="team.php" class="category-pill <?php echo empty($category) ? 'active' : ''; ?>">
                    <i class="fas fa-users me-1"></i> All Members
                    <span class="count"><?php echo array_sum(array_column($categories, 'count')); ?></span>
                </a>
                <?php foreach ($categories as $cat): ?>
                <a href="team.php?category=<?php echo urlencode($cat['category']); ?>"
                   class="category-pill <?php echo $category === $cat['category'] ? 'active' : ''; ?>">
                    <i class="fas fa-user-tie me-1"></i>
                    <?php echo htmlspecialchars($cat['category']); ?>
                    <span class="count"><?php echo $cat['count']; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- Team Members Section -->
<section class="team-section py-5">
    <div class="container">
        <div class="team-count mb-4" data-aos="fade-up">
            <p class="mb-0">
                <span class="count-number"><?php echo $totalMembers; ?></span>
                team members
                <?php if (!empty($category)): ?>
                in <span class="category-name"><?php echo htmlspecialchars($category); ?></span>
                <a href="team.php" class="clear-filters">
                    <i class="fas fa-times-circle me-1"></i> Clear filter
                </a>
                <?php endif; ?>
            </p>
        </div>

        <?php if (empty($teamMembers)): ?>
        <div class="empty-state text-center py-5" data-aos="fade-up">
            <i class="fas fa-users-slash fa-4x mb-4"></i>
            <h4>No team members found</h4>
            <p>Try adjusting your search or filter</p>
            <a href="team.php" class="btn btn-primary">
                <i class="fas fa-sync-alt me-2"></i> Show All Members
            </a>
        </div>
        <?php else: ?>
        <div class="team-grid">
            <?php foreach ($teamMembers as $index => $member): ?>
            <div class="team-member-card" data-aos="zoom-in" data-aos-delay="<?php echo ($index % 6) * 50; ?>">
                <div class="member-header">
                    <div class="member-photo">
                        <?php if ($member['profile_image'] || $member['student_profile_image']): ?>
                        <img src="<?php echo SITE_URL; ?>/assets/images/team/<?php echo htmlspecialchars($member['profile_image'] ?: $member['student_profile_image']); ?>"
                             alt="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>"
                             class="photo-img">
                        <?php else: ?>
                        <div class="photo-placeholder">
                            <i class="fas fa-user"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="member-info">
                        <h4 class="member-name">
                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                        </h4>
                        <div class="member-role">
                            <?php echo htmlspecialchars($member['role']); ?>
                        </div>
                        <div class="member-category">
                            <span class="category-badge <?php echo getCategoryColor($member['category']); ?>">
                                <?php echo htmlspecialchars($member['category']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="member-bio">
                    <p>
                        <?php echo !empty($member['bio']) ? htmlspecialchars($member['bio']) : 'Passionate about technology and innovation.'; ?>
                    </p>
                </div>

                <div class="member-social">
                    <?php if (!empty($member['linkedin_url'])): ?>
                    <a href="<?php echo htmlspecialchars($member['linkedin_url']); ?>" target="_blank"
                       class="social-link linkedin" title="LinkedIn">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                    <?php endif; ?>

                    <?php if (!empty($member['github_url'])): ?>
                    <a href="<?php echo htmlspecialchars($member['github_url']); ?>" target="_blank"
                       class="social-link github" title="GitHub">
                        <i class="fab fa-github"></i>
                    </a>
                    <?php endif; ?>

                    <?php if (!empty($member['display_email'])): ?>
                    <a href="mailto:<?php echo htmlspecialchars($member['display_email']); ?>"
                       class="social-link email" title="Email">
                        <i class="fas fa-envelope"></i>
                    </a>
                    <?php endif; ?>

                    <?php if (empty($member['linkedin_url']) && empty($member['github_url']) && empty($member['display_email'])): ?>
                    <div class="social-link disabled" title="No social links available">
                        <i class="fas fa-link-slash"></i>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalMembers > $perPage): ?>
        <div class="pagination-container" data-aos="fade-up">
            <nav>
                <ul class="pagination justify-content-center">
                    <?php
                    $totalPages = ceil($totalMembers / $perPage);
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

<!-- Join Team Section -->
<section class="join-team-section py-5">
    <div class="container">
        <div class="join-team-card text-center" data-aos="zoom-in">
            <div class="join-team-content">
                <div class="join-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h3 class="join-title">Join the SPARK Team</h3>
                <p class="join-description">
                    Passionate about technology and innovation? Join our team and help shape the future of AI and research at Sanjivani University.
                </p>
                <div class="join-stats">
                    <div class="join-stat">
                        <i class="fas fa-rocket me-2"></i>
                        <span>Grow Your Skills</span>
                    </div>
                    <div class="join-stat">
                        <i class="fas fa-handshake me-2"></i>
                        <span>Build Network</span>
                    </div>
                    <div class="join-stat">
                        <i class="fas fa-trophy me-2"></i>
                        <span>Lead Projects</span>
                    </div>
                </div>
                <a href="contact.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane me-2"></i> Express Interest
                </a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../templates/footer.php'; ?>

<?php
// Helper function to get category color
function getCategoryColor($category) {
    $colors = [
        'Core Leadership' => 'core-leadership',
        'Management Team' => 'management',
        'Technical Division' => 'technical',
        'Non-Technical & Creative Division' => 'non-technical',
        'General Members' => 'general'
    ];
    return $colors[$category] ?? 'general';
}
?>

<style>
/* Team Page Styles */
.team-header {
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

.team-search {
    max-width: 400px;
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

.categories-filter {
    background: var(--card-bg);
    border-bottom: 1px solid var(--border-color);
}

.categories-container {
    padding: 1.5rem;
}

.category-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    justify-content: center;
}

.category-pill {
    display: inline-flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 25px;
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.category-pill::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(0, 255, 136, 0.2), transparent);
    transition: left 0.6s ease;
}

.category-pill:hover::before {
    left: 100%;
}

.category-pill:hover {
    background: var(--accent-color);
    color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 255, 136, 0.3);
    text-decoration: none;
}

.category-pill.active {
    background: var(--accent-color);
    color: var(--primary-color);
    border-color: var(--accent-color);
}

.category-pill .count {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 0.25rem 0.5rem;
    margin-left: 0.75rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.team-count {
    color: var(--text-secondary);
    text-align: center;
}

.count-number {
    color: var(--accent-color);
    font-weight: bold;
    font-size: 1.2rem;
}

.category-name {
    color: var(--neon-blue);
    font-weight: 600;
}

.clear-filters {
    color: var(--neon-pink);
    text-decoration: none;
    margin-left: 1rem;
    transition: all 0.3s ease;
}

.clear-filters:hover {
    color: var(--error-color);
}

.team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

.team-member-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(10px);
}

.team-member-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: conic-gradient(from 0deg, transparent, var(--accent-color), transparent);
    animation: rotate 4s linear infinite;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.team-member-card:hover::before {
    opacity: 0.1;
}

.team-member-card:hover {
    transform: translateY(-10px) scale(1.02);
    box-shadow: 0 30px 60px rgba(0, 255, 136, 0.2);
    border-color: var(--accent-color);
}

.member-header {
    margin-bottom: 1.5rem;
}

.member-photo {
    width: 120px;
    height: 120px;
    margin: 0 auto 1rem;
    position: relative;
}

.photo-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
    border: 4px solid var(--accent-color);
    transition: all 0.3s ease;
}

.team-member-card:hover .photo-img {
    transform: scale(1.1);
    box-shadow: 0 10px 20px rgba(0, 255, 136, 0.4);
}

.photo-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, var(--accent-color), var(--neon-blue));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 3rem;
    transition: all 0.3s ease;
}

.team-member-card:hover .photo-placeholder {
    transform: scale(1.1);
    box-shadow: 0 10px 20px rgba(0, 255, 136, 0.4);
}

.member-name {
    color: var(--text-primary);
    font-size: 1.3rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
    line-height: 1.2;
}

.member-role {
    color: var(--neon-blue);
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.category-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.category-badge.core-leadership {
    background: linear-gradient(45deg, var(--neon-pink), var(--error-color));
    color: var(--text-primary);
}

.category-badge.management {
    background: linear-gradient(45deg, var(--warning-color), #ff8c00);
    color: var(--text-primary);
}

.category-badge.technical {
    background: linear-gradient(45deg, var(--neon-blue), #0066cc);
    color: var(--text-primary);
}

.category-badge.non-technical {
    background: linear-gradient(45deg, var(--neon-pink), var(--accent-color));
    color: var(--primary-color);
}

.category-badge.general {
    background: linear-gradient(45deg, var(--accent-color), var(--success-color));
    color: var(--primary-color);
}

.member-bio {
    margin-bottom: 1.5rem;
}

.member-bio p {
    color: var(--text-secondary);
    line-height: 1.6;
    margin: 0;
}

.member-social {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

.social-link {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 1.2rem;
}

.social-link:hover {
    transform: translateY(-5px) scale(1.1);
}

.social-link.linkedin {
    background: #0077b5;
    color: white;
}

.social-link.linkedin:hover {
    background: #005885;
    box-shadow: 0 10px 20px rgba(0, 119, 181, 0.4);
}

.social-link.github {
    background: #333;
    color: white;
}

.social-link.github:hover {
    background: #000;
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.4);
}

.social-link.email {
    background: var(--accent-color);
    color: var(--primary-color);
}

.social-link.email:hover {
    background: var(--neon-blue);
    color: var(--text-primary);
    box-shadow: 0 10px 20px rgba(0, 255, 136, 0.4);
}

.social-link.disabled {
    background: var(--border-color);
    color: var(--text-secondary);
    cursor: not-allowed;
}

.join-team-section {
    background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
    border-top: 1px solid var(--border-color);
}

.join-team-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 3rem;
    backdrop-filter: blur(10px);
    position: relative;
    overflow: hidden;
}

.join-team-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="rgba(0,255,136,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
    opacity: 0.05;
}

.join-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(45deg, var(--accent-color), var(--neon-blue));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 2.5rem;
    margin: 0 auto 2rem;
    animation: pulse 2s ease-in-out infinite;
    box-shadow: 0 20px 40px rgba(0, 255, 136, 0.3);
}

.join-title {
    color: var(--text-primary);
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 1rem;
}

.join-description {
    color: var(--text-secondary);
    font-size: 1.1rem;
    line-height: 1.6;
    margin-bottom: 2rem;
}

.join-stats {
    display: flex;
    justify-content: space-around;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.join-stat {
    text-align: center;
    color: var(--text-primary);
    transition: all 0.3s ease;
}

.join-stat:hover {
    transform: translateY(-3px);
}

.join-stat i {
    color: var(--accent-color);
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    display: block;
}

.join-stat span {
    font-weight: 600;
    font-size: 1rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .section-title {
        font-size: 2rem;
    }

    .team-search {
        max-width: 100%;
        margin-top: 1rem;
    }

    .category-pills {
        justify-content: flex-start;
    }

    .team-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }

    .team-member-card {
        padding: 1.5rem;
    }

    .member-photo {
        width: 100px;
        height: 100px;
    }

    .member-name {
        font-size: 1.1rem;
    }

    .member-social {
        gap: 0.75rem;
    }

    .social-link {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }

    .join-stats {
        flex-direction: column;
        gap: 1.5rem;
    }

    .join-team-card {
        padding: 2rem;
    }

    .join-icon {
        width: 60px;
        height: 60px;
        font-size: 2rem;
    }

    .join-title {
        font-size: 1.5rem;
    }
}

/* Animations */
@keyframes rotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
}

/* Team card hover effects */
.team-member-card {
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

/* Loading state */
.team-member-card.loading {
    position: relative;
    overflow: hidden;
}

.team-member-card.loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(0, 255, 136, 0.2), transparent);
    animation: loading-shimmer 1.5s infinite;
}

@keyframes loading-shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchForm = document.querySelector('.search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            const searchInput = this.querySelector('input[name="search"]');
            if (searchInput && searchInput.value.trim()) {
                // Form will naturally submit
                return true;
            } else {
                e.preventDefault();
                showNotification('Please enter a search term', 'warning');
            }
        });
    }

    // Team member card hover effects
    const teamCards = document.querySelectorAll('.team-member-card');
    teamCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-10px) scale(1.02) rotate(2deg)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1) rotate(0deg)';
        });
    });

    // Social link interactions
    const socialLinks = document.querySelectorAll('.social-link:not(.disabled)');
    socialLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Add click tracking
            const platform = this.classList.contains('linkedin') ? 'LinkedIn' :
                           this.classList.contains('github') ? 'GitHub' :
                           this.classList.contains('email') ? 'Email' : 'Unknown';
            console.log('Social link clicked:', platform);
        });
    });

    // Category filter animations
    const categoryPills = document.querySelectorAll('.category-pill');
    categoryPills.forEach((pill, index) => {
        pill.style.animationDelay = `${index * 0.05}s`;
        pill.style.animation = 'fadeInUp 0.6s ease-out both';
    });

    // Intersection Observer for lazy loading and animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const memberObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animation = 'slideInUp 0.6s ease-out both';
                memberObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);

    teamCards.forEach(card => {
        memberObserver.observe(card);
    });

    // Smooth scroll for pagination
    document.querySelectorAll('.page-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetUrl = this.getAttribute('href');
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 300);
        });
    });

    // Join section animation
    const joinSection = document.querySelector('.join-team-card');
    if (joinSection) {
        joinSection.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.02)';
        });

        joinSection.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    }
});
</script>