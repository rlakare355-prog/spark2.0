<?php
// SPARK Platform - Admin Dashboard
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin login
requireAdmin();

// Get current user
$currentUser = getCurrentUser();

// Fetch dashboard statistics
$stats = [
    'total_events' => dbCount('events'),
    'active_events' => dbCount('events', 'is_active = 1'),
    'total_students' => dbCount('students'),
    'verified_students' => dbCount('students', 'is_email_verified = 1'),
    'total_registrations' => dbCount('event_registrations'),
    'paid_registrations' => dbCount('event_registrations', 'payment_status = ?', ['paid']),
    'total_payments' => dbCount('payments'),
    'total_revenue' => dbFetch("SELECT SUM(amount) as total FROM payments WHERE status = 'captured'")['total'] ?? 0,
    'total_certificates' => dbCount('certificates'),
    'total_projects' => dbCount('research_projects'),
    'active_projects' => dbCount('research_projects', 'status = ?', ['active']),
    'gallery_photos' => dbCount('gallery', 'is_active = 1'),
    'contact_messages' => dbCount('contact_messages')
];

// Fetch recent registrations
$recentRegistrations = dbFetchAll("
    SELECT er.*, e.title as event_title, s.first_name, s.last_name, s.email
    FROM event_registrations er
    JOIN events e ON er.event_id = e.id
    JOIN students s ON er.student_id = s.id
    ORDER BY er.registration_date DESC
    LIMIT 10
");

// Fetch recent payments
$recentPayments = dbFetchAll("
    SELECT p.*, e.title as event_title, s.first_name, s.last_name
    FROM payments p
    JOIN events e ON p.event_id = e.id
    JOIN students s ON p.student_id = s.id
    ORDER BY p.created_at DESC
    LIMIT 10
");

// Fetch recent activities
$recentActivities = dbFetchAll("
    SELECT al.*, s.first_name, s.last_name
    FROM activity_logs al
    LEFT JOIN students s ON al.admin_id = s.id
    ORDER BY al.created_at DESC
    LIMIT 10
");

// Event participation by department
$departmentStats = dbFetchAll("
    SELECT
        s.department,
        COUNT(DISTINCT er.id) as registrations,
        COUNT(DISTINCT a.id) as attendance
    FROM students s
    LEFT JOIN event_registrations er ON s.id = er.student_id
    LEFT JOIN events e ON er.event_id = e.id
    LEFT JOIN attendance a ON s.id = a.student_id
    GROUP BY s.department
    ORDER BY registrations DESC
");

// Monthly revenue trend
$revenueTrend = dbFetchAll("
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(amount) as revenue,
        COUNT(*) as transactions
    FROM payments
    WHERE status = 'captured'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");

$page_title = 'Admin Dashboard';
$page_subtitle = 'SPARK Platform Analytics';
$hide_page_header = true;
$include_admin_nav = true;

include __DIR__ . '/../templates/header.php';
?>

<!-- Dashboard Hero Section -->
<section class="admin-dashboard-hero">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="welcome-section" data-aos="fade-right">
                    <h1 class="dashboard-title">
                        Welcome back, <span class="accent-text"><?php echo htmlspecialchars($currentUser['first_name']); ?></span>
                    </h1>
                    <p class="dashboard-subtitle">
                        Here's your SPARK platform overview and analytics
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="quick-actions" data-aos="fade-left">
                    <a href="events.php?action=create" class="quick-action-btn primary">
                        <i class="fas fa-plus"></i>
                        <span>Create Event</span>
                    </a>
                    <a href="reports.php" class="quick-action-btn secondary">
                        <i class="fas fa-chart-bar"></i>
                        <span>Generate Report</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Statistics Overview Section -->
<section class="stats-overview py-4">
    <div class="container-fluid">
        <div class="row g-4">
            <!-- Events Statistics -->
            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="100">
                <div class="stat-card events">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" data-target="<?php echo $stats['total_events']; ?>">0</div>
                        <div class="stat-label">Total Events</div>
                        <div class="stat-sub">
                            <span class="active"><?php echo $stats['active_events']; ?></span> Active
                        </div>
                    </div>
                </div>
            </div>

            <!-- Students Statistics -->
            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="200">
                <div class="stat-card students">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" data-target="<?php echo $stats['total_students']; ?>">0</div>
                        <div class="stat-label">Total Students</div>
                        <div class="stat-sub">
                            <span class="verified"><?php echo $stats['verified_students']; ?></span> Verified
                        </div>
                    </div>
                </div>
            </div>

            <!-- Registrations Statistics -->
            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="300">
                <div class="stat-card registrations">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" data-target="<?php echo $stats['total_registrations']; ?>">0</div>
                        <div class="stat-label">Total Registrations</div>
                        <div class="stat-sub">
                            <span class="paid"><?php echo $stats['paid_registrations']; ?></span> Paid
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue Statistics -->
            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="400">
                <div class="stat-card revenue">
                    <div class="stat-icon">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number revenue-number">₹<?php echo number_format($stats['total_revenue'], 0); ?></div>
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-sub">
                            <span><?php echo $stats['total_payments']; ?></span> Transactions
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Charts and Analytics Section -->
<section class="analytics-section py-4">
    <div class="container-fluid">
        <div class="row">
            <!-- Department Participation Chart -->
            <div class="col-lg-6" data-aos="fade-right">
                <div class="chart-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-chart-pie me-2"></i> Department Participation
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="departmentChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Revenue Trend Chart -->
            <div class="col-lg-6" data-aos="fade-left">
                <div class="chart-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-chart-line me-2"></i> Revenue Trend (12 Months)
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Quick Stats Grid -->
<section class="quick-stats-section py-4">
    <div class="container-fluid">
        <div class="row g-4">
            <div class="col-lg-3 col-md-6" data-aos="flip-left" data-aos-delay="100">
                <div class="quick-stat-card projects">
                    <div class="quick-stat-icon">
                        <i class="fas fa-flask"></i>
                    </div>
                    <div class="quick-stat-content">
                        <h3><?php echo $stats['total_projects']; ?></h3>
                        <p>Research Projects</p>
                        <span class="quick-stat-active"><?php echo $stats['active_projects']; ?> Active</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6" data-aos="flip-left" data-aos-delay="200">
                <div class="quick-stat-card certificates">
                    <div class="quick-stat-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <div class="quick-stat-content">
                        <h3><?php echo $stats['total_certificates']; ?></h3>
                        <p>Certificates Issued</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6" data-aos="flip-left" data-aos-delay="300">
                <div class="quick-stat-card gallery">
                    <div class="quick-stat-icon">
                        <i class="fas fa-images"></i>
                    </div>
                    <div class="quick-stat-content">
                        <h3><?php echo $stats['gallery_photos']; ?></h3>
                        <p>Gallery Photos</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6" data-aos="flip-left" data-aos-delay="400">
                <div class="quick-stat-card messages">
                    <div class="quick-stat-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="quick-stat-content">
                        <h3><?php echo $stats['contact_messages']; ?></h3>
                        <p>Contact Messages</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Recent Activities Section -->
<section class="recent-activities py-4">
    <div class="container-fluid">
        <div class="row">
            <!-- Recent Registrations -->
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
                <div class="activity-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-user-plus me-2"></i> Recent Registrations
                        </h5>
                        <a href="events.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="activity-list">
                            <?php if (empty($recentRegistrations)): ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                <p>No recent registrations</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($recentRegistrations as $index => $registration): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-user-check"></i>
                                </div>
                                <div class="activity-content">
                                    <h6><?php echo htmlspecialchars($registration['event_title']); ?></h6>
                                    <p class="activity-details">
                                        <?php echo htmlspecialchars($registration['first_name'] . ' ' . $registration['last_name']); ?>
                                        <span class="activity-status <?php echo $registration['payment_status']; ?>">
                                            <?php echo ucfirst($registration['payment_status']); ?>
                                        </span>
                                    </p>
                                    <small class="activity-date">
                                        <?php echo formatDate($registration['registration_date']); ?>
                                    </small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="200">
                <div class="activity-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-credit-card me-2"></i> Recent Payments
                        </h5>
                        <a href="payments.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="activity-list">
                            <?php if (empty($recentPayments)): ?>
                            <div class="empty-state">
                                <i class="fas fa-credit-card fa-2x mb-2"></i>
                                <p>No recent payments</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($recentPayments as $index => $payment): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-rupee-sign"></i>
                                </div>
                                <div class="activity-content">
                                    <h6><?php echo formatCurrency($payment['amount']); ?></h6>
                                    <p class="activity-details">
                                        <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                        <span class="activity-status <?php echo $payment['status']; ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </p>
                                    <small class="activity-date">
                                        <?php echo formatDate($payment['created_at']); ?>
                                    </small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Activity Log Section -->
<section class="activity-log-section py-4">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12" data-aos="fade-up">
                <div class="activity-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-history me-2"></i> Recent Admin Activities
                        </h5>
                        <a href="logs.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php if (empty($recentActivities)): ?>
                            <div class="empty-state">
                                <i class="fas fa-history fa-2x mb-2"></i>
                                <p>No recent activities</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($recentActivities as $index => $activity): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="fas <?php echo getActivityIcon($activity['action_type']); ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <h6><?php echo ucfirst($activity['action_type']); ?></h6>
                                        <span class="timeline-module"><?php echo ucfirst($activity['module']); ?></span>
                                    </div>
                                    <p class="timeline-description"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></p>
                                    <small class="timeline-meta">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo formatDate($activity['created_at']); ?>
                                        <?php if (!empty($activity['first_name'])): ?>
                                        • <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../templates/footer.php'; ?>

<?php
// Helper function to get activity icon
function getActivityIcon($actionType) {
    $icons = [
        'login' => 'fa-sign-in-alt',
        'create' => 'fa-plus',
        'update' => 'fa-edit',
        'delete' => 'fa-trash',
        'login_failed' => 'fa-exclamation-triangle'
    ];
    return $icons[$actionType] ?? 'fa-circle';
}
?>

<style>
/* Admin Dashboard Specific Styles */
.admin-dashboard-hero {
    background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
    padding: 3rem 0;
    border-bottom: 1px solid var(--border-color);
}

.dashboard-title {
    color: var(--text-primary);
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.accent-text {
    background: linear-gradient(45deg, var(--accent-color), var(--neon-blue));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.dashboard-subtitle {
    color: var(--text-secondary);
    font-size: 1.1rem;
    margin: 0;
}

.quick-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.quick-action-btn.primary {
    background: linear-gradient(45deg, var(--accent-color), var(--neon-blue));
    color: var(--primary-color);
}

.quick-action-btn.secondary {
    background: transparent;
    color: var(--text-primary);
    border-color: var(--border-color);
}

.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 255, 136, 0.3);
}

.stats-overview .stat-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(10px);
}

.stats-overview .stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: left 0.6s ease;
}

.stats-overview .stat-card:hover::before {
    left: 100%;
}

.stats-overview .stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 255, 136, 0.2);
    border-color: var(--accent-color);
}

.stat-card.events .stat-icon { background: var(--info-color); }
.stat-card.students .stat-icon { background: var(--success-color); }
.stat-card.registrations .stat-icon { background: var(--warning-color); }
.stat-card.revenue .stat-icon { background: var(--neon-pink); }

.stat-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 2rem;
    margin: 0 auto 1.5rem;
    transition: all 0.3s ease;
}

.stat-card:hover .stat-icon {
    transform: scale(1.1);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    color: var(--text-primary);
    line-height: 1;
    margin-bottom: 0.5rem;
}

.stat-number.revenue-number {
    color: var(--accent-color);
}

.stat-label {
    color: var(--text-secondary);
    font-size: 1rem;
    font-weight: 500;
}

.stat-sub {
    font-size: 0.9rem;
}

.stat-sub .active {
    color: var(--success-color);
}

.stat-sub .paid {
    color: var(--accent-color);
}

.chart-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    backdrop-filter: blur(10px);
}

.chart-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.chart-card .card-title {
    color: var(--text-primary);
    font-weight: 600;
    margin: 0;
}

.quick-stats-section .quick-stat-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.quick-stats-section .quick-stat-card:hover {
    transform: translateY(-3px);
    border-color: var(--accent-color);
    box-shadow: 0 15px 30px rgba(0, 255, 136, 0.2);
}

.quick-stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.5rem;
    margin: 0 auto 1rem;
    transition: all 0.3s ease;
}

.quick-stat-card.projects .quick-stat-icon { background: var(--neon-blue); }
.quick-stat-card.certificates .quick-stat-icon { background: var(--success-color); }
.quick-stat-card.gallery .quick-stat-icon { background: var(--warning-color); }
.quick-stat-card.messages .quick-stat-icon { background: var(--neon-pink); }

.quick-stat-card h3 {
    color: var(--text-primary);
    font-size: 2rem;
    font-weight: bold;
    margin: 0 0 0.5rem;
}

.quick-stat-card p {
    color: var(--text-secondary);
    margin: 0 0 0.5rem;
}

.quick-stat-active {
    display: block;
    color: var(--accent-color);
    font-size: 0.9rem;
    font-weight: 600;
}

.activity-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    backdrop-filter: blur(10px);
}

.activity-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.activity-list {
    max-height: 400px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 0;
    border-bottom: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-item:hover {
    background: var(--glass-bg);
    border-radius: 10px;
}

.activity-icon {
    width: 40px;
    height: 40px;
    background: var(--accent-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1rem;
    flex-shrink: 0;
}

.activity-content {
    flex: 1;
}

.activity-content h6 {
    color: var(--text-primary);
    font-weight: 600;
    margin: 0 0 0.25rem;
}

.activity-details {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin: 0 0 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.activity-status {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.activity-status.paid {
    background: var(--success-color);
    color: var(--primary-color);
}

.activity-status.pending {
    background: var(--warning-color);
    color: var(--primary-color);
}

.activity-status.failed {
    background: var(--error-color);
    color: var(--text-primary);
}

.activity-date {
    color: var(--text-secondary);
    font-size: 0.8rem;
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

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.timeline-header h6 {
    color: var(--text-primary);
    font-weight: 600;
    margin: 0;
}

.timeline-module {
    background: var(--accent-color);
    color: var(--primary-color);
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.timeline-description {
    color: var(--text-secondary);
    margin: 0 0 0.5rem;
}

.timeline-meta {
    color: var(--text-secondary);
    font-size: 0.8rem;
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
}

.empty-state i {
    color: var(--text-secondary);
    opacity: 0.5;
    margin-bottom: 1rem;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .dashboard-title {
        font-size: 2rem;
    }

    .quick-actions {
        justify-content: center;
        margin-top: 2rem;
    }

    .stats-overview .stat-card {
        padding: 1.5rem;
    }

    .stat-number {
        font-size: 2rem;
    }
}

@media (max-width: 768px) {
    .admin-dashboard-hero {
        padding: 2rem 0;
    }

    .dashboard-title {
        font-size: 1.8rem;
        text-align: center;
    }

    .dashboard-subtitle {
        text-align: center;
        margin-bottom: 1rem;
    }

    .quick-actions {
        justify-content: center;
    }

    .stats-overview .stat-card {
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .stat-number {
        font-size: 1.5rem;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
        margin: 0 auto 1rem;
    }

    .chart-card,
    .activity-card {
        padding: 1rem;
    }

    .activity-list {
        max-height: 300px;
    }
}
</style>

<script>
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
                const finalNumber = target.textContent.replace(/[^0-9]/g, '');
                if (!isNaN(finalNumber)) {
                    animateCounter(target, parseInt(finalNumber));
                }
                statsObserver.unobserve(target);
            }
        });
    }, observerOptions);

    statNumbers.forEach(number => {
        if (!number.textContent.includes('₹')) {
            statsObserver.observe(number);
        }
    });

    // Counter animation function
    function animateCounter(element, target) {
        const duration = 2000;
        const step = target / (duration / 16);
        let current = 0;

        const updateCounter = () => {
            current += step;
            if (current < target) {
                element.innerText = Math.floor(current).toLocaleString();
                requestAnimationFrame(updateCounter);
            } else {
                element.innerText = target.toLocaleString();
            }
        };

        updateCounter();
    }

    // Initialize Department Chart
    const departmentCtx = document.getElementById('departmentChart');
    if (departmentCtx) {
        new Chart(departmentCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($departmentStats, 'department')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($departmentStats, 'registrations')); ?>,
                    backgroundColor: [
                        '#00ff88',
                        '#00aaff',
                        '#ff00aa',
                        '#ff9800',
                        '#ff4757'
                    ],
                    borderWidth: 2,
                    borderColor: '#1a1a1a'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#ffffff',
                            padding: 20,
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });
    }

    // Initialize Revenue Chart
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($revenueTrend, 'month')); ?>,
                datasets: [{
                    label: 'Monthly Revenue',
                    data: <?php echo json_encode(array_column($revenueTrend, 'revenue')); ?>,
                    borderColor: '#00ff88',
                    backgroundColor: 'rgba(0, 255, 136, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        labels: {
                            color: '#ffffff'
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: '#333333'
                        },
                        ticks: {
                            color: '#ffffff'
                        }
                    },
                    y: {
                        grid: {
                            color: '#333333'
                        },
                        ticks: {
                            color: '#ffffff',
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // Real-time data refresh (placeholder)
    function refreshDashboardData() {
        // This would be implemented with WebSocket or periodic AJAX calls
        console.log('Refreshing dashboard data...');
    }

    // Refresh data every 5 minutes
    setInterval(refreshDashboardData, 300000);
});
</script>