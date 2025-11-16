<?php
// SPARK Platform - Student Dashboard
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/student_header.php';

// Get current user data
$currentUser = getCurrentUser();
if (!$currentUser) {
    die("Error: Unable to get current user data. Please try logging in again.");
}
$userId = $currentUser['id'];

// Fetch dashboard statistics
$stats = [
    'events_registered' => dbCount('event_registrations', 'student_id = ?', [$userId]),
    'events_attended' => dbCount('attendance', 'student_id = ? AND status = ?', [$userId, 'present']),
    'certificates_earned' => dbCount('certificates', 'student_id = ?', [$userId]),
    'projects_joined' => dbCount('project_members', 'student_id = ? AND status = ?', [$userId, 'accepted'])
];

// Fetch recent activities
$recentActivities = dbFetchAll("
    SELECT
        'event' as type, er.registration_date as date, e.title as title, 'registered' as action
    FROM event_registrations er
    JOIN events e ON er.event_id = e.id
    WHERE er.student_id = ?

    UNION ALL

    SELECT
        'attendance' as type, a.scan_time as date, e.title as title, 'attended' as action
    FROM attendance a
    JOIN events e ON a.event_id = e.id
    WHERE a.student_id = ?

    UNION ALL

    SELECT
        'certificate' as type, c.created_at as date, CONCAT('Certificate - ', e.title) as title, 'earned' as action
    FROM certificates c
    JOIN events e ON c.event_id = e.id
    WHERE c.student_id = ?

    ORDER BY date DESC
    LIMIT 5
", [$userId, $userId, $userId]);

// Fetch upcoming registered events
$upcomingEvents = dbFetchAll("
    SELECT e.*, er.payment_status, er.attendance_status
    FROM events e
    JOIN event_registrations er ON e.id = er.event_id
    WHERE er.student_id = ? AND e.event_date > NOW()
    ORDER BY e.event_date ASC
    LIMIT 3
", [$userId]);

// Fetch research projects joined
$researchProjects = dbFetchAll("
    SELECT rp.*, pm.role, pm.status as member_status
    FROM research_projects rp
    JOIN project_members pm ON rp.id = pm.project_id
    WHERE pm.student_id = ? AND rp.status = 'active'
    ORDER BY rp.created_at DESC
    LIMIT 3
", [$userId]);

$page_title = 'Dashboard';
$page_subtitle = 'Welcome back, ' . htmlspecialchars($currentUser['first_name']);
$breadcrumb = [
    ['name' => 'Home', 'link' => SITE_URL . '/student/', 'active' => false],
    ['name' => 'Dashboard', 'link' => '', 'active' => true]
];
$include_student_nav = true;

include __DIR__ . '/../templates/header.php';
?>

<!-- Dashboard Hero Section -->
<section class="dashboard-hero py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="welcome-message" data-aos="fade-right">
                    <h2 class="welcome-title">
                        Welcome back, <span class="accent-text"><?php echo htmlspecialchars($currentUser['first_name']); ?></span>!
                    </h2>
                    <p class="welcome-subtitle">
                        Here's your SPARK platform overview
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="quick-stats text-md-end" data-aos="fade-left">
                    <div class="stat-item">
                        <span class="stat-label">Department:</span>
                        <span class="stat-value"><?php echo htmlspecialchars($currentUser['department']); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Year:</span>
                        <span class="stat-value"><?php echo htmlspecialchars($currentUser['year']); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">PRN:</span>
                        <span class="stat-value"><?php echo htmlspecialchars($currentUser['prn']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Statistics Section -->
<section class="dashboard-stats py-4">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['events_registered']; ?></div>
                        <div class="stat-label">Events Registered</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['events_attended']; ?></div>
                        <div class="stat-label">Events Attended</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['certificates_earned']; ?></div>
                        <div class="stat-label">Certificates Earned</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="400">
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-flask"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['projects_joined']; ?></div>
                        <div class="stat-label">Projects Joined</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Main Dashboard Content -->
<section class="dashboard-content py-4">
    <div class="container">
        <div class="row">
            <!-- Upcoming Events -->
            <div class="col-lg-6 mb-4">
                <div class="dashboard-card" data-aos="fade-right">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-calendar-alt me-2"></i> Upcoming Events
                        </h5>
                        <a href="<?php echo SITE_URL; ?>/student/events.php" class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcomingEvents)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times fa-3x mb-3"></i>
                            <p>No upcoming events registered</p>
                            <a href="<?php echo SITE_URL; ?>/student/events.php" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i> Browse Events
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="events-list">
                            <?php foreach ($upcomingEvents as $event): ?>
                            <div class="event-item">
                                <div class="event-info">
                                    <h6 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h6>
                                    <div class="event-meta">
                                        <span class="event-date">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo formatDate($event['event_date']); ?>
                                        </span>
                                        <?php if ($event['fee'] > 0): ?>
                                        <span class="event-fee">
                                            <i class="fas fa-rupee-sign me-1"></i>
                                            <?php echo formatCurrency($event['fee']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="event-status">
                                        <span class="badge <?php echo $event['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo ucfirst($event['payment_status']); ?>
                                        </span>
                                        <span class="badge bg-info">
                                            <?php echo ucfirst($event['attendance_status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Research Projects -->
            <div class="col-lg-6 mb-4">
                <div class="dashboard-card" data-aos="fade-left">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-flask me-2"></i> Research Projects
                        </h5>
                        <a href="<?php echo SITE_URL; ?>/student/research.php" class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($researchProjects)): ?>
                        <div class="empty-state">
                            <i class="fas fa-microscope fa-3x mb-3"></i>
                            <p>No research projects joined yet</p>
                            <a href="<?php echo SITE_URL; ?>/student/research.php" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i> Explore Projects
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="projects-list">
                            <?php foreach ($researchProjects as $project): ?>
                            <div class="project-item">
                                <div class="project-info">
                                    <h6 class="project-title"><?php echo htmlspecialchars($project['title']); ?></h6>
                                    <p class="project-description">
                                        <?php echo truncateText(htmlspecialchars($project['description']), 100); ?>
                                    </p>
                                    <div class="project-meta">
                                        <span class="project-role">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo ucfirst($project['role']); ?>
                                        </span>
                                        <span class="project-status badge <?php echo $project['member_status'] === 'accepted' ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo ucfirst($project['member_status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-12">
                <div class="dashboard-card" data-aos="fade-up">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-history me-2"></i> Recent Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentActivities)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>No recent activities</p>
                        </div>
                        <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($recentActivities as $activity): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <?php
                                    $iconClass = match($activity['type']) {
                                        'event' => 'fa-calendar-check',
                                        'attendance' => 'fa-user-check',
                                        'certificate' => 'fa-certificate',
                                        default => 'fa-circle'
                                    };
                                    ?>
                                    <i class="fas <?php echo $iconClass; ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <h6 class="timeline-title"><?php echo htmlspecialchars($activity['title']); ?></h6>
                                    <p class="timeline-action">
                                        You <?php echo $activity['action']; ?> <?php echo $activity['type']; ?>
                                    </p>
                                    <span class="timeline-date">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo formatDate($activity['date']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Quick Actions Section -->
<section class="quick-actions py-4">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="quick-actions-card" data-aos="zoom-in">
                    <h5 class="card-title text-center mb-4">Quick Actions</h5>
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <a href="<?php echo SITE_URL; ?>/student/events.php" class="quick-action-btn">
                                <i class="fas fa-calendar-plus"></i>
                                <span>Browse Events</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="<?php echo SITE_URL; ?>/student/certificates.php" class="quick-action-btn">
                                <i class="fas fa-download"></i>
                                <span>Download Certificates</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="<?php echo SITE_URL; ?>/student/opportunities.php" class="quick-action-btn">
                                <i class="fas fa-briefcase"></i>
                                <span>Find Opportunities</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="<?php echo SITE_URL; ?>/student/profile.php" class="quick-action-btn">
                                <i class="fas fa-user-edit"></i>
                                <span>Edit Profile</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/student_footer.php'; ?>

<style>
/* Dashboard Specific Styles */
.dashboard-hero {
    background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
    border-bottom: 1px solid var(--border-color);
}

.welcome-title {
    color: var(--text-primary);
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.accent-text {
    background: linear-gradient(45deg, var(--accent-color), var(--neon-blue));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.welcome-subtitle {
    color: var(--text-secondary);
    font-size: 1.1rem;
    margin: 0;
}

.quick-stats {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 1rem;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    backdrop-filter: blur(10px);
}

.stat-label {
    color: var(--text-secondary);
    font-weight: 500;
}

.stat-value {
    color: var(--accent-color);
    font-weight: bold;
}

.dashboard-stats .stat-card {
    display: flex;
    align-items: center;
    padding: 1.5rem;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.dashboard-stats .stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: left 0.6s ease;
}

.dashboard-stats .stat-card:hover::before {
    left: 100%;
}

.dashboard-stats .stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0, 255, 136, 0.2);
}

.stat-card.primary .stat-icon { background: var(--info-color); }
.stat-card.success .stat-icon { background: var(--success-color); }
.stat-card.warning .stat-icon { background: var(--warning-color); }
.stat-card.info .stat-icon { background: var(--neon-blue); }

.stat-icon {
    width: 60px;
    height: 60px;
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
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
}

.stat-content .stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: var(--text-primary);
    line-height: 1;
}

.stat-content .stat-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin-top: 0.5rem;
}

.dashboard-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    backdrop-filter: blur(10px);
    height: 100%;
    transition: all 0.3s ease;
}

.dashboard-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    border-color: var(--accent-color);
}

.dashboard-card .card-header {
    background: var(--secondary-color);
    border-bottom: 1px solid var(--border-color);
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 15px 15px 0 0;
}

.dashboard-card .card-title {
    color: var(--text-primary);
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
}

.empty-state i {
    color: var(--text-secondary);
    opacity: 0.5;
}

.events-list .event-item,
.projects-list .project-item {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.events-list .event-item:hover,
.projects-list .project-item:hover {
    background: var(--glass-bg);
    border-radius: 10px;
}

.events-list .event-item:last-child,
.projects-list .project-item:last-child {
    border-bottom: none;
}

.event-title,
.project-title {
    color: var(--text-primary);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.event-meta,
.project-meta {
    display: flex;
    gap: 1rem;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.event-date,
.project-role {
    color: var(--accent-color);
}

.event-fee {
    color: var(--success-color);
}

.project-description {
    color: var(--text-secondary);
    margin-bottom: 1rem;
}

.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0.5rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--border-color);
}

.timeline-item {
    position: relative;
    margin-bottom: 2rem;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-icon {
    position: absolute;
    left: -2rem;
    width: 2rem;
    height: 2rem;
    background: var(--accent-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 0.8rem;
    z-index: 1;
}

.timeline-content {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    padding: 1rem;
    backdrop-filter: blur(10px);
}

.timeline-title {
    color: var(--text-primary);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.timeline-action {
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.timeline-date {
    color: var(--accent-color);
    font-size: 0.9rem;
}

.quick-actions-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    padding: 2rem;
    backdrop-filter: blur(10px);
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1.5rem 1rem;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.3s ease;
    height: 100%;
}

.quick-action-btn:hover {
    background: var(--accent-color);
    color: var(--primary-color);
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 255, 136, 0.3);
    text-decoration: none;
}

.quick-action-btn i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    color: var(--accent-color);
    transition: all 0.3s ease;
}

.quick-action-btn:hover i {
    color: var(--primary-color);
}

.quick-action-btn span {
    font-weight: 500;
    text-align: center;
}

/* Responsive Design */
@media (max-width: 768px) {
    .welcome-title {
        font-size: 1.5rem;
    }

    .quick-stats {
        margin-top: 1rem;
    }

    .dashboard-stats .stat-card {
        padding: 1rem;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
        margin-right: 1rem;
    }

    .stat-content .stat-number {
        font-size: 1.5rem;
    }

    .timeline {
        padding-left: 1.5rem;
    }

    .timeline-icon {
        left: -1.5rem;
        width: 1.5rem;
        height: 1.5rem;
    }

    .quick-action-btn {
        padding: 1rem 0.5rem;
    }

    .quick-action-btn i {
        font-size: 1.5rem;
    }
}
</style>

<script>
// Dashboard interactive features
document.addEventListener('DOMContentLoaded', function() {
    // Animate statistics counters
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
                animateCounter(target, finalNumber);
                statsObserver.unobserve(target);
            }
        });
    }, observerOptions);

    statNumbers.forEach(number => {
        statsObserver.observe(number);
    });

    // Counter animation function
    function animateCounter(element, target) {
        const duration = 2000;
        const step = target / (duration / 16);
        let current = 0;

        const updateCounter = () => {
            current += step;
            if (current < target) {
                element.innerText = Math.floor(current);
                requestAnimationFrame(updateCounter);
            } else {
                element.innerText = target;
            }
        };

        updateCounter();
    }

    // Quick actions hover effect
    const quickActionBtns = document.querySelectorAll('.quick-action-btn');
    quickActionBtns.forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            const icon = this.querySelector('i');
            icon.style.transform = 'scale(1.2) rotate(5deg)';
        });

        btn.addEventListener('mouseleave', function() {
            const icon = this.querySelector('i');
            icon.style.transform = 'scale(1) rotate(0deg)';
        });
    });

    // Timeline item hover effects
    const timelineItems = document.querySelectorAll('.timeline-item');
    timelineItems.forEach((item, index) => {
        item.style.animationDelay = `${index * 0.1}s`;
        item.style.animation = 'fadeInUp 0.6s ease forwards';
    });

    // Add CSS animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    `;
    document.head.appendChild(style);
});

// Real-time dashboard updates (placeholder for WebSocket integration)
function updateDashboardStats() {
    // This would be implemented with WebSocket or periodic AJAX calls
    // For now, just refresh every 5 minutes
    setInterval(() => {
        // Refresh data
        location.reload();
    }, 300000);
}

// Initialize real-time updates
updateDashboardStats();
</script>