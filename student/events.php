<?php
// SPARK Platform - Student Events Page
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

// Login NOT required for viewing events (only for participation)

// Get current user (if logged in)
$currentUser = isLoggedIn() ? getCurrentUser() : null;
$userId = $currentUser ? $currentUser['id'] : null;

// Handle search and filters
$search = sanitize($_GET['search'] ?? '');
$category = sanitize($_GET['category'] ?? '');
$eventType = sanitize($_GET['type'] ?? '');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 6;

// Build where conditions
$whereConditions = ['e.event_date >= NOW()'];
$params = [];

if (!empty($search)) {
    $whereConditions[] = '(e.title LIKE ? OR e.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $whereConditions[] = 'e.category = ?';
    $params[] = $category;
}

if (!empty($eventType)) {
    if ($eventType === 'free') {
        $whereConditions[] = 'e.fee = 0';
    } elseif ($eventType === 'paid') {
        $whereConditions[] = 'e.fee > 0';
    }
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Count total events
$countSql = "SELECT COUNT(*) as total FROM events e $whereClause";
$totalResult = dbFetch($countSql, $params);
$totalEvents = $totalResult['total'];

// Fetch events with pagination
$eventsSql = "
    SELECT e.*,
           COUNT(DISTINCT er.id) as registered_count,
           CASE WHEN er.student_id IS NOT NULL THEN 1 ELSE 0 END as is_registered
    FROM events e
    LEFT JOIN event_registrations er ON e.id = er.event_id AND er.student_id = ?
    $whereClause
    GROUP BY e.id
    ORDER BY e.event_date ASC
    LIMIT ? OFFSET ?
";

$events = dbFetchAll($eventsSql, array_merge([$userId], $params, [$perPage, ($page - 1) * $perPage]));

// Get categories for filter
$categories = dbFetchAll("
    SELECT DISTINCT category, COUNT(*) as count
    FROM events
    WHERE category IS NOT NULL AND category != ''
    GROUP BY category
    ORDER BY count DESC
");

$page_title = 'Events';
$page_subtitle = 'Discover and Register for Amazing Events';
$breadcrumb = [
    ['name' => 'Home', 'link' => SITE_URL . '/student/', 'active' => false],
    ['name' => 'Events', 'link' => '', 'active' => true]
];
$include_student_nav = true;

include __DIR__ . '/../templates/header.php';
?>

<!-- Events Header Section -->
<section class="events-header py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="search-section" data-aos="fade-right">
                    <h3 class="section-title">
                        <i class="fas fa-calendar-alt me-2"></i> Upcoming Events
                    </h3>
                    <div class="search-bar">
                        <form method="GET" class="search-form">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search"
                                       placeholder="Search events..."
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="view-options" data-aos="fade-left">
                    <div class="btn-group" role="group">
                        <a href="?view=grid" class="btn btn-outline-primary <?php echo (!isset($_GET['view']) || $_GET['view'] === 'grid') ? 'active' : ''; ?>">
                            <i class="fas fa-th"></i> Grid
                        </a>
                        <a href="?view=list" class="btn btn-outline-primary <?php echo (isset($_GET['view']) && $_GET['view'] === 'list') ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i> List
                        </a>
                    </div>
                    <a href="calendar.php" class="btn btn-primary">
                        <i class="fas fa-calendar me-2"></i> Calendar View
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Filters Section -->
<section class="filters-section py-3">
    <div class="container">
        <div class="filters-container" data-aos="fade-up">
            <div class="row">
                <div class="col-md-4">
                    <label class="filter-label">Category</label>
                    <select class="form-select" onchange="window.location.href = '?category=' + this.value">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category']); ?> (<?php echo $cat['count']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="filter-label">Event Type</label>
                    <select class="form-select" onchange="window.location.href = '?type=' + this.value">
                        <option value="">All Events</option>
                        <option value="free" <?php echo $eventType === 'free' ? 'selected' : ''; ?>>
                            <i class="fas fa-gift me-1"></i> Free Events
                        </option>
                        <option value="paid" <?php echo $eventType === 'paid' ? 'selected' : ''; ?>>
                            <i class="fas fa-rupee-sign me-1"></i> Paid Events
                        </option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="filter-label">Sort By</label>
                    <select class="form-select" onchange="window.location.href = '?sort=' + this.value">
                        <option value="date">Event Date</option>
                        <option value="name">Event Name</option>
                        <option value="popular">Most Popular</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Events Grid/List Section -->
<section class="events-section py-5">
    <div class="container">
        <div class="events-count mb-4" data-aos="fade-up">
            <p class="mb-0">
                <span class="count-number"><?php echo $totalEvents; ?></span>
                events found
                <?php if (!empty($search) || !empty($category) || !empty($eventType)): ?>
                <a href="events.php" class="clear-filters">
                    <i class="fas fa-times-circle me-1"></i> Clear filters
                </a>
                <?php endif; ?>
            </p>
        </div>

        <?php if (empty($events)): ?>
        <div class="empty-state text-center py-5" data-aos="fade-up">
            <i class="fas fa-calendar-times fa-4x mb-4"></i>
            <h4>No events found</h4>
            <p>Try adjusting your filters or search terms</p>
            <a href="events.php" class="btn btn-primary">
                <i class="fas fa-sync-alt me-2"></i> Clear All Filters
            </a>
        </div>
        <?php else: ?>
        <div class="<?php echo (isset($_GET['view']) && $_GET['view'] === 'list') ? 'events-list' : 'events-grid'; ?>">
            <?php foreach ($events as $index => $event): ?>
            <div class="event-item-wrapper" data-aos="<?php echo (isset($_GET['view']) && $_GET['view'] === 'list') ? 'fade-left' : 'zoom-in'; ?>"
                 data-aos-delay="<?php echo ($index % 6) * 100; ?>">
                <div class="event-card">
                    <?php if ($event['banner_image']): ?>
                    <div class="event-banner">
                        <img src="<?php echo SITE_URL; ?>/assets/images/banners/<?php echo htmlspecialchars($event['banner_image']); ?>"
                             alt="<?php echo htmlspecialchars($event['title']); ?>">
                        <div class="event-date-badge">
                            <?php
                            $eventDate = new DateTime($event['event_date']);
                            echo $eventDate->format('M') . '<br>' . $eventDate->format('d');
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="event-body">
                        <div class="event-header">
                            <h5 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                            <div class="event-category"><?php echo htmlspecialchars($event['category']); ?></div>
                        </div>

                        <div class="event-details">
                            <div class="detail-item">
                                <i class="fas fa-calendar-alt me-2"></i>
                                <?php echo formatDate($event['event_date']); ?>
                            </div>
                            <?php if ($event['location']): ?>
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                <?php echo htmlspecialchars($event['location']); ?>
                            </div>
                            <?php endif; ?>
                            <div class="detail-item">
                                <i class="fas fa-users me-2"></i>
                                <?php echo $event['registered_count']; ?> registered
                                <?php if ($event['max_participants']): ?>
                                / <?php echo $event['max_participants']; ?> spots
                                <?php endif; ?>
                            </div>
                        </div>

                        <p class="event-description">
                            <?php echo truncateText(htmlspecialchars($event['description']), 150); ?>
                        </p>

                        <div class="event-footer">
                            <div class="event-fee">
                                <?php if ($event['fee'] > 0): ?>
                                <span class="fee-amount">
                                    <i class="fas fa-rupee-sign me-1"></i>
                                    <?php echo number_format($event['fee'], 2); ?>
                                </span>
                                <?php else: ?>
                                <span class="fee-free">
                                    <i class="fas fa-gift me-1"></i> Free
                                </span>
                                <?php endif; ?>
                            </div>

                            <div class="event-actions">
                                <?php if ($event['is_registered']): ?>
                                <button class="btn btn-success" disabled>
                                    <i class="fas fa-check me-1"></i> Registered
                                </button>
                                <?php elseif ($event['max_participants'] && $event['registered_count'] >= $event['max_participants']): ?>
                                <button class="btn btn-warning" disabled>
                                    <i class="fas fa-exclamation-triangle me-1"></i> Full
                                </button>
                                <?php else: ?>
                                <button class="btn btn-primary register-event-btn"
                                        data-event-id="<?php echo $event['id']; ?>"
                                        data-event-title="<?php echo htmlspecialchars($event['title']); ?>"
                                        data-event-fee="<?php echo $event['fee']; ?>">
                                    <?php if ($event['fee'] > 0): ?>
                                    <i class="fas fa-shopping-cart me-1"></i> Register & Pay
                                    <?php else: ?>
                                    <i class="fas fa-user-plus me-1"></i> Register
                                    <?php endif; ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalEvents > $perPage): ?>
        <div class="pagination-container" data-aos="fade-up">
            <nav>
                <ul class="pagination justify-content-center">
                    <?php
                    $totalPages = ceil($totalEvents / $perPage);
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

<!-- Registration Modal -->
<div class="modal fade" id="registrationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Event Registration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="event-details"></div>
                <form id="registrationForm">
                    <input type="hidden" id="event_id" name="event_id">

                    <div class="mb-3">
                        <label for="special_requirements" class="form-label">Special Requirements (Optional)</label>
                        <textarea class="form-control" id="special_requirements" name="special_requirements"
                                  rows="3" placeholder="Any dietary restrictions, accessibility needs, etc."></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="agree_terms" required>
                            <label class="form-check-label" for="agree_terms">
                                I agree to the event terms and conditions
                            </label>
                        </div>
                    </div>

                    <div id="payment-section" style="display: none;">
                        <h6>Payment Details</h6>
                        <div class="payment-summary mb-3">
                            <div class="row">
                                <div class="col-6">Event Fee:</div>
                                <div class="col-6 text-end" id="event-fee-display">₹0.00</div>
                            </div>
                            <div class="row">
                                <div class="col-6">Processing Fee:</div>
                                <div class="col-6 text-end">₹0.00</div>
                            </div>
                            <hr>
                            <div class="row fw-bold">
                                <div class="col-6">Total:</div>
                                <div class="col-6 text-end" id="total-amount-display">₹0.00</div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmRegistrationBtn">
                    <i class="fas fa-check me-2"></i> Confirm Registration
                </button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>

<style>
/* Events Page Styles */
.events-header {
    background: var(--secondary-color);
    border-bottom: 1px solid var(--border-color);
}

.section-title {
    color: var(--text-primary);
    font-weight: bold;
    margin-bottom: 1.5rem;
}

.search-bar {
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
    padding-left: 1.5rem;
}

.search-form .btn {
    border-radius: 0 25px 25px 0;
    border: none;
}

.view-options {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 1rem;
}

.filters-section {
    background: var(--card-bg);
    border-bottom: 1px solid var(--border-color);
}

.filters-container {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    padding: 1.5rem;
    backdrop-filter: blur(10px);
}

.filter-label {
    color: var(--accent-color);
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.events-count {
    color: var(--text-secondary);
}

.count-number {
    color: var(--accent-color);
    font-weight: bold;
    font-size: 1.2rem;
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

/* Grid Layout */
.events-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 2rem;
}

/* List Layout */
.events-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.events-list .event-card {
    display: flex;
    max-width: 100%;
}

.events-list .event-banner {
    width: 300px;
    min-width: 300px;
}

.events-list .event-body {
    flex: 1;
}

.event-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 15px;
    overflow: hidden;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.event-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 255, 136, 0.2);
    border-color: var(--accent-color);
}

.event-banner {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.event-banner img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.event-card:hover .event-banner img {
    transform: scale(1.05);
}

.event-date-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: var(--accent-color);
    color: var(--primary-color);
    text-align: center;
    padding: 0.5rem;
    border-radius: 8px;
    font-weight: bold;
    font-size: 0.9rem;
    line-height: 1.2;
}

.event-body {
    padding: 1.5rem;
}

.event-header {
    margin-bottom: 1rem;
}

.event-title {
    color: var(--text-primary);
    font-weight: bold;
    margin-bottom: 0.5rem;
    line-height: 1.3;
}

.event-category {
    color: var(--neon-blue);
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.event-details {
    margin-bottom: 1rem;
}

.detail-item {
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.event-description {
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 1.5rem;
}

.event-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.fee-amount {
    color: var(--success-color);
    font-weight: bold;
    font-size: 1.2rem;
}

.fee-free {
    color: var(--accent-color);
    font-weight: bold;
    font-size: 1.1rem;
}

.pagination-container {
    margin-top: 3rem;
}

.pagination .page-link {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    padding: 0.75rem 1rem;
    margin: 0 2px;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.pagination .page-link:hover {
    background: var(--accent-color);
    color: var(--primary-color);
    border-color: var(--accent-color);
    transform: translateY(-2px);
}

.pagination .page-item.active .page-link {
    background: var(--accent-color);
    border-color: var(--accent-color);
}

.payment-summary {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    padding: 1rem;
    backdrop-filter: blur(10px);
}

/* Responsive Design */
@media (max-width: 768px) {
    .events-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .search-bar {
        max-width: 100%;
        margin-bottom: 1rem;
    }

    .view-options {
        justify-content: flex-start;
        margin-bottom: 1rem;
    }

    .events-list .event-card {
        flex-direction: column;
    }

    .events-list .event-banner {
        width: 100%;
        min-width: auto;
        height: 200px;
    }

    .event-footer {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }

    .event-actions .btn {
        width: 100%;
    }
}

/* Animation for event cards */
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

.event-item-wrapper {
    animation: slideInUp 0.6s ease-out both;
}

/* Loading state for events */
.event-card.loading {
    position: relative;
    overflow: hidden;
}

.event-card.loading::after {
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
    // Registration modal functionality
    const registrationModal = new bootstrap.Modal(document.getElementById('registrationModal'));
    let currentEvent = null;

    // Event registration buttons
    document.querySelectorAll('.register-event-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            currentEvent = {
                id: this.dataset.eventId,
                title: this.dataset.eventTitle,
                fee: parseFloat(this.dataset.eventFee)
            };

            // Populate modal
            document.getElementById('event_id').value = currentEvent.id;

            let detailsHTML = `
                <h6>${currentEvent.title}</h6>
                <p><strong>Registration Fee:</strong> ₹${currentEvent.fee.toFixed(2)}</p>
            `;

            if (currentEvent.fee > 0) {
                document.getElementById('payment-section').style.display = 'block';
                document.getElementById('event-fee-display').textContent = '₹' + currentEvent.fee.toFixed(2);
                document.getElementById('total-amount-display').textContent = '₹' + currentEvent.fee.toFixed(2);
                detailsHTML += '<p class="text-warning">Payment will be processed securely via Razorpay</p>';
            } else {
                document.getElementById('payment-section').style.display = 'none';
            }

            document.getElementById('event-details').innerHTML = detailsHTML;
            registrationModal.show();
        });
    });

    // Confirm registration
    document.getElementById('confirmRegistrationBtn').addEventListener('click', function() {
        const form = document.getElementById('registrationForm');
        const agreeTerms = document.getElementById('agree_terms').checked;

        if (!agreeTerms) {
            showNotification('Please agree to the event terms and conditions', 'error');
            return;
        }

        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';

        if (currentEvent.fee > 0) {
            // Initiate payment
            initiatePayment();
        } else {
            // Free event registration
            submitRegistration('free');
        }
    });

    // Payment initiation
    function initiatePayment() {
        fetch('<?php echo SITE_URL; ?>/api/payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'create_order',
                event_id: currentEvent.id,
                amount: currentEvent.fee * 100, // Convert to paise
                currency: 'INR'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Open Razorpay payment
                const options = {
                    key: data.data.key_id,
                    amount: data.data.amount,
                    currency: 'INR',
                    name: 'SPARK Event Registration',
                    description: currentEvent.title,
                    order_id: data.data.order_id,
                    handler: function(response) {
                        submitRegistration('paid', response);
                    },
                    prefill: {
                        name: '<?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>',
                        email: '<?php echo htmlspecialchars($currentUser['email']); ?>',
                        contact: '<?php echo htmlspecialchars($currentUser['contact_no']); ?>'
                    },
                    theme: {
                        color: '#000000',
                        back_color: '#00ff88'
                    }
                };

                const razorpay = new Razorpay(options);
                razorpay.open();
            } else {
                throw new Error(data.message);
            }
        })
        .catch(error => {
            console.error('Payment error:', error);
            showNotification('Payment initiation failed. Please try again.', 'error');
            document.getElementById('confirmRegistrationBtn').disabled = false;
            document.getElementById('confirmRegistrationBtn').innerHTML = '<i class="fas fa-check me-2"></i> Confirm Registration';
        });
    }

    // Submit registration
    function submitRegistration(paymentType, paymentResponse = null) {
        const formData = new FormData();
        formData.append('event_id', currentEvent.id);
        formData.append('special_requirements', document.getElementById('special_requirements').value);
        formData.append('payment_type', paymentType);

        if (paymentResponse) {
            formData.append('payment_id', paymentResponse.razorpay_payment_id);
            formData.append('order_id', paymentResponse.razorpay_order_id);
            formData.append('signature', paymentResponse.razorpay_signature);
        }

        fetch('<?php echo SITE_URL; ?>/api/payment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Registration successful! Check your email for confirmation.', 'success');
                registrationModal.hide();
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                throw new Error(data.message);
            }
        })
        .catch(error => {
            console.error('Registration error:', error);
            showNotification('Registration failed. Please try again.', 'error');
        })
        .finally(() => {
            document.getElementById('confirmRegistrationBtn').disabled = false;
            document.getElementById('confirmRegistrationBtn').innerHTML = '<i class="fas fa-check me-2"></i> Confirm Registration';
        });
    }

    // Event hover effects
    document.querySelectorAll('.event-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
});
</script>