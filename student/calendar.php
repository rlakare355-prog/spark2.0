<?php
require_once '../includes/student_header.php';
requireRole(['student', 'event_coordinator', 'research_coordinator', 'domain_lead', 'management_head', 'accountant', 'super_admin', 'admin']);

// Get current user
$user = getCurrentUser();

// Handle calendar actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_to_google'])) {
            $eventId = intval($_POST['event_id']);

            // Get event details
            $event = dbFetch("
                SELECT title, description, event_date, location, fee
                FROM events
                WHERE id = ?
            ", [$eventId]);

            if (!$event) {
                throw new Exception("Event not found");
            }

            // Generate Google Calendar URL
            $startDate = date('Ymd\THis', strtotime($event['event_date']));
            $endDate = date('Ymd\THis', strtotime($event['event_date'] . '+2 hours'));

            $googleUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . urlencode($event['title']) .
                        '&dates=' . $startDate . '/' . $endDate .
                        '&details=' . urlencode($event['description'] ?? '') .
                        '&location=' . urlencode($event['location'] ?? '') .
                        '&sf=true&output=xml';

            $_SESSION['google_calendar_url'] = $googleUrl;
            $message = "Google Calendar link generated! Redirecting...";
        }

        if (isset($_POST['export_ical'])) {
            $month = sanitize($_POST['export_month']) ?? date('Y-m');

            // Get events for the month
            $events = dbFetchAll("
                SELECT id, title, description, event_date, location, fee, category
                FROM events
                WHERE DATE(event_date) BETWEEN ? AND LAST_DAY(?)
                ORDER BY event_date ASC
            ", [
                $month . '-01',
                $month . '-01'
            ]);

            // Generate iCal content
            $ical = "BEGIN:VCALENDAR\r\n";
            $ical .= "VERSION:2.0\r\n";
            $ical .= "PRODID:-//SPARK Platform//SPARK Calendar//EN\r\n";
            $ical .= "CALSCALE:GREGORIAN\r\n";

            foreach ($events as $event) {
                $startDate = date('Ymd\THis', strtotime($event['event_date']));
                $endDate = date('Ymd\THis', strtotime($event['event_date'] . '+2 hours'));

                $ical .= "BEGIN:VEVENT\r\n";
                $ical .= "UID:" . $event['id'] . "@spark.sanjivani.edu\r\n";
                $ical .= "DTSTART:" . $startDate . "\r\n";
                $ical .= "DTEND:" . $endDate . "\r\n";
                $ical .= "DTSTAMP:" . date('Ymd\THis') . "\r\n";
                $ical .= "SUMMARY:" . htmlspecialchars($event['title']) . "\r\n";
                $ical .= "DESCRIPTION:" . htmlspecialchars($event['description'] ?? '') . "\r\n";
                $ical .= "LOCATION:" . htmlspecialchars($event['location'] ?? '') . "\r\n";
                $ical .= "CATEGORIES:" . htmlspecialchars($event['category'] ?? 'General') . "\r\n";
                $ical .= "END:VEVENT\r\n";
            }

            $ical .= "END:VCALENDAR\r\n";

            // Send file
            header('Content-Type: text/calendar; charset=utf-8');
            header('Content-Disposition: attachment; filename="spark_calendar_' . str_replace('-', '_', $month) . '.ics"');
            echo $ical;
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get calendar view parameters
$view = sanitize($_GET['view'] ?? 'month'); // month, week, day
$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$category = sanitize($_GET['category'] ?? '');

// Parse date
$currentDate = new DateTime($date);
$year = $currentDate->format('Y');
$month = $currentDate->format('m');
$day = $currentDate->format('d');

// Get events based on view
$events = [];
$startDate = '';
$endDate = '';

switch ($view) {
    case 'day':
        $startDate = $date . ' 00:00:00';
        $endDate = $date . ' 23:59:59';
        break;
    case 'week':
        $weekStart = clone $currentDate;
        $weekStart->modify('monday this week');
        $weekEnd = clone $weekStart;
        $weekEnd->modify('sunday this week');
        $startDate = $weekStart->format('Y-m-d 00:00:00');
        $endDate = $weekEnd->format('Y-m-d 23:59:59');
        break;
    case 'month':
    default:
        $startDate = $year . '-' . $month . '-01 00:00:00';
        $endDate = $year . '-' . $month . '-31 23:59:59';
        break;
}

// Build query
$whereConditions = ["event_date BETWEEN ? AND ?"];
$params = [$startDate, $endDate];

if (!empty($category)) {
    $whereConditions[] = "category = ?";
    $params[] = $category;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

$events = dbFetchAll("
    SELECT e.*, er.student_id as registered
    FROM events e
    LEFT JOIN event_registrations er ON e.id = er.event_id AND er.student_id = ?
    $whereClause
    ORDER BY e.event_date ASC
", array_merge([$_SESSION['user_id']], $params));

// Get user registrations for quick reference
$userRegistrations = [];
foreach ($events as $event) {
    if ($event['registered']) {
        $userRegistrations[] = $event['id'];
    }
}

// Get categories for filter
$categories = dbFetchAll("SELECT DISTINCT category FROM events WHERE category IS NOT NULL ORDER BY category");

// Get important dates (deadlines, etc.)
$importantDates = dbFetchAll("
    SELECT title, date, type, description
    FROM (
        SELECT title, registration_deadline as date, 'deadline' as type, CONCAT('Registration deadline for ', title) as description
        FROM events
        WHERE registration_deadline > NOW()

        UNION ALL

        SELECT CONCAT('Payment Deadline: ', title), DATE_SUB(event_date, INTERVAL 1 DAY) as date, 'payment' as type, CONCAT('Payment due for ', title) as description
        FROM events
        WHERE fee > 0 AND event_date > DATE_ADD(NOW(), INTERVAL 1 DAY)

        UNION ALL

        SELECT 'Today' as title, CURDATE() as date, 'today' as type, 'Current date' as description
    ) as important_dates
    ORDER BY date ASC
    LIMIT 10
");

// Helper function to get events for a specific day
function getDayEvents($events, $date) {
    $dayEvents = [];
    foreach ($events as $event) {
        if (date('Y-m-d', strtotime($event['event_date'])) === $date) {
            $dayEvents[] = $event;
        }
    }
    return $dayEvents;
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="fas fa-calendar-alt me-2"></i>
                    Calendar
                </h2>
                <div>
                    <div class="btn-group me-3">
                        <a href="?view=day&date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-primary <?php echo $view === 'day' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-day me-2"></i>Day
                        </a>
                        <a href="?view=week&date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-primary <?php echo $view === 'week' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-week me-2"></i>Week
                        </a>
                        <a href="?view=month&date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-primary <?php echo $view === 'month' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar me-2"></i>Month
                        </a>
                    </div>
                    <button class="btn btn-outline-secondary" onclick="window.location.href='dashboard.php'">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['google_calendar_url'])): ?>
        <script>
        setTimeout(function() {
            window.open('<?php echo $_SESSION['google_calendar_url']; ?>', '_blank');
            window.location.href = 'calendar.php?view=<?php echo $view; ?>&date=<?php echo $date; ?>';
        }, 2000);
        </script>
        <?php unset($_SESSION['google_calendar_url']); ?>
    <?php endif; ?>

    <div class="row">
        <!-- Left Sidebar -->
        <div class="col-lg-3 mb-4">
            <!-- Calendar Navigation -->
            <div class="card admin-card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar me-2"></i>
                        Navigation
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <button class="btn btn-outline-secondary" onclick="navigateCalendar('previous')">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <h6 class="mb-0" id="currentDateDisplay">
                            <?php echo $currentDate->format('F Y'); ?>
                        </h6>
                        <button class="btn btn-outline-secondary" onclick="navigateCalendar('next')">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" onclick="navigateCalendar('today')">
                            <i class="fas fa-calendar-day me-2"></i>Today
                        </button>
                        <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#exportModal">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- Category Filter -->
            <div class="card admin-card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-filter me-2"></i>
                        Filter by Category
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="mb-2">
                        <input type="hidden" name="view" value="<?php echo $view; ?>">
                        <input type="hidden" name="date" value="<?php echo $date; ?>">
                        <select class="form-select" name="category" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                        <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Important Dates -->
            <div class="card admin-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-star me-2"></i>
                        Important Dates
                    </h5>
                </div>
                <div class="card-body">
                    <div class="important-dates">
                        <?php foreach ($importantDates as $impDate): ?>
                            <div class="important-date-item mb-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            <?php if ($impDate['type'] === 'today'): ?>
                                                <span class="badge bg-info">Today</span>
                                            <?php elseif ($impDate['type'] === 'deadline'): ?>
                                                <span class="badge bg-warning">Deadline</span>
                                            <?php elseif ($impDate['type'] === 'payment'): ?>
                                                <span class="badge bg-danger">Payment</span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($impDate['title']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo formatDate($impDate['date']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Calendar Content -->
        <div class="col-lg-9 mb-4">
            <?php if ($view === 'month'): ?>
                <!-- Month View -->
                <div class="card admin-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calendar me-2"></i>
                            <?php echo $currentDate->format('F Y'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="calendar-grid">
                            <!-- Weekday headers -->
                            <div class="calendar-header">Sun</div>
                            <div class="calendar-header">Mon</div>
                            <div class="calendar-header">Tue</div>
                            <div class="calendar-header">Wed</div>
                            <div class="calendar-header">Thu</div>
                            <div class="calendar-header">Fri</div>
                            <div class="calendar-header">Sat</div>

                            <?php
                            // Calculate first day of month and total days
                            $firstDay = new DateTime("$year-$month-01");
                            $lastDay = new DateTime("$year-$month-" . date('t', strtotime("$year-$month-01")));
                            $startDayOfWeek = $firstDay->format('w');
                            $totalDays = $lastDay->format('d');

                            // Add empty cells for days before month starts
                            for ($i = 0; $i < $startDayOfWeek; $i++) {
                                echo '<div class="calendar-day empty"></div>';
                            }

                            // Add days of the month
                            for ($day = 1; $day <= $totalDays; $day++) {
                                $currentDayDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                                $dayEvents = getDayEvents($events, $currentDayDate);
                                $isToday = $currentDayDate === date('Y-m-d');
                                $isWeekend = date('w', strtotime($currentDayDate)) == 0 || date('w', strtotime($currentDayDate)) == 6;

                                echo '<div class="calendar-day ' . ($isToday ? 'today' : '') . ($isWeekend ? ' weekend' : '') . '">';
                                echo '<div class="day-number">' . $day . '</div>';

                                if (!empty($dayEvents)) {
                                    echo '<div class="day-events">';
                                    foreach ($dayEvents as $event) {
                                        $isRegistered = in_array($event['id'], $userRegistrations);
                                        echo '<div class="event-item ' . ($isRegistered ? 'registered' : '') . '" data-bs-toggle="tooltip" title="' . htmlspecialchars($event['title']) . '">';
                                        echo '<i class="fas fa-circle event-indicator"></i>';
                                        echo '<span class="event-time">' . date('H:i', strtotime($event['event_date'])) . '</span>';
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                }

                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

            <?php elseif ($view === 'week'): ?>
                <!-- Week View -->
                <div class="card admin-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calendar-week me-2"></i>
                            Week of <?php echo $weekStart->format('M j, Y'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="week-view">
                            <?php
                            $weekStart = clone $currentDate;
                            $weekStart->modify('monday this week');

                            for ($day = 0; $day < 7; $day++) {
                                $currentDay = clone $weekStart;
                                $currentDay->modify("+$day days");
                                $dayDate = $currentDay->format('Y-m-d');
                                $dayEvents = getDayEvents($events, $dayDate);
                                $isToday = $dayDate === date('Y-m-d');

                                echo '<div class="week-day ' . ($isToday ? 'today' : '') . '">';
                                echo '<div class="week-day-header">';
                                echo '<h6>' . $currentDay->format('l') . '</h6>';
                                echo '<small>' . $currentDay->format('M j') . '</small>';
                                echo '</div>';

                                echo '<div class="week-events">';
                                if (!empty($dayEvents)) {
                                    foreach ($dayEvents as $event) {
                                        $isRegistered = in_array($event['id'], $userRegistrations);
                                        echo '<div class="week-event-item ' . ($isRegistered ? 'registered' : '') . '">';
                                        echo '<div class="event-time">' . date('H:i', strtotime($event['event_date'])) . '</div>';
                                        echo '<div class="event-title">' . htmlspecialchars($event['title']) . '</div>';
                                        if ($event['fee'] > 0) {
                                            echo '<div class="event-price">₹' . number_format($event['fee'], 2) . '</div>';
                                        }
                                        echo '</div>';
                                    }
                                }
                                echo '</div>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Day View -->
                <div class="card admin-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calendar-day me-2"></i>
                            <?php echo $currentDate->format('l, F j, Y'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="day-view">
                            <?php
                            $dayEvents = getDayEvents($events, $date);

                            if (!empty($dayEvents)) {
                                foreach ($dayEvents as $event) {
                                    $isRegistered = in_array($event['id'], $userRegistrations);
                                    echo '<div class="day-event-item ' . ($isRegistered ? 'registered' : '') . '">';
                                    echo '<div class="day-event-time">' . date('H:i', strtotime($event['event_date'])) . '</div>';
                                    echo '<div class="day-event-content">';
                                    echo '<h6>' . htmlspecialchars($event['title']) . '</h6>';
                                    echo '<p class="text-muted small mb-1">' . htmlspecialchars($event['location'] ?? 'TBD') . '</p>';
                                    if ($event['fee'] > 0) {
                                        echo '<div class="day-event-fee">Fee: ₹' . number_format($event['fee'], 2) . '</div>';
                                    }
                                    if ($isRegistered) {
                                        echo '<span class="badge bg-success">Registered</span>';
                                    } else {
                                        echo '<button class="btn btn-sm btn-primary" onclick="showEventDetails(' . $event['id'] . ')">Register</button>';
                                    }
                                    echo '</div>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<div class="text-center py-5">';
                                echo '<i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>';
                                echo '<h5 class="text-muted">No events scheduled</h5>';
                                echo '<p class="text-muted">Check other dates or subscribe to notifications</p>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Event Details Modal -->
<div class="modal fade" id="eventDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Event Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="eventDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-info" id="addToGoogleBtn" onclick="addToGoogleCalendar()">
                    <i class="fab fa-google me-2"></i>Add to Google Calendar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">
                    <i class="fas fa-download me-2"></i>
                    Export Calendar
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="export_month" class="form-label">Select Month</label>
                        <input type="month" class="form-control bg-secondary text-light border-secondary"
                               id="export_month" name="export_month" value="<?php echo date('Y-m'); ?>">
                    </div>
                    <div class="alert alert-info small">
                        <i class="fas fa-info-circle me-2"></i>
                        Export will include all active events for the selected month in iCal format, compatible with Google Calendar, Outlook, and Apple Calendar.
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="export_ical" class="btn btn-primary">
                        <i class="fas fa-download me-2"></i>Download iCal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Calendar Styles */
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background: var(--border-color);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    overflow: hidden;
}

.calendar-header {
    background: var(--dark-bg);
    padding: 10px;
    text-align: center;
    font-weight: bold;
    font-size: 0.9rem;
    text-transform: uppercase;
    color: var(--accent-color);
}

.calendar-day {
    background: var(--card-bg);
    min-height: 100px;
    padding: 8px;
    position: relative;
    transition: all 0.3s ease;
}

.calendar-day:hover {
    background: rgba(0, 255, 136, 0.1);
    transform: translateY(-2px);
}

.calendar-day.empty {
    background: rgba(255, 255, 255, 0.02);
    min-height: 60px;
}

.calendar-day.today {
    background: rgba(0, 255, 136, 0.1);
    border: 2px solid var(--accent-color);
}

.calendar-day.weekend {
    background: rgba(255, 255, 255, 0.05);
}

.day-number {
    font-weight: bold;
    margin-bottom: 5px;
    color: var(--text-primary);
}

.day-events {
    font-size: 0.75rem;
}

.event-item {
    background: var(--dark-bg);
    border-radius: 4px;
    padding: 2px 6px;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
    transition: all 0.3s ease;
    border-left: 3px solid var(--accent-color);
}

.event-item:hover {
    background: var(--accent-color);
    transform: translateX(2px);
}

.event-item.registered {
    border-left-color: var(--success-color);
    background: rgba(40, 167, 69, 0.2);
}

.event-indicator {
    font-size: 6px;
    margin-right: 4px;
    color: var(--accent-color);
}

.event-time {
    font-size: 0.7rem;
    opacity: 0.8;
}

/* Week View */
.week-view {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 10px;
}

.week-day {
    background: var(--card-bg);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.week-day:hover {
    transform: translateY(-2px);
}

.week-day.today {
    border: 2px solid var(--accent-color);
}

.week-day-header {
    background: var(--dark-bg);
    padding: 10px;
    text-align: center;
    border-bottom: 1px solid var(--border-color);
}

.week-events {
    padding: 10px;
    max-height: 400px;
    overflow-y: auto;
}

.week-event-item {
    background: var(--dark-bg);
    border-radius: 6px;
    padding: 10px;
    margin-bottom: 8px;
    border-left: 3px solid var(--accent-color);
    transition: all 0.3s ease;
}

.week-event-item:hover {
    transform: translateX(5px);
}

.week-event-item.registered {
    border-left-color: var(--success-color);
    background: rgba(40, 167, 69, 0.2);
}

.event-time {
    font-weight: bold;
    color: var(--accent-color);
    margin-bottom: 5px;
}

.event-title {
    font-weight: 600;
    margin-bottom: 3px;
}

.event-price {
    color: var(--accent-color);
    font-weight: bold;
    font-size: 0.9rem;
}

/* Day View */
.day-view {
    max-height: 600px;
    overflow-y: auto;
}

.day-event-item {
    background: var(--card-bg);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    border-left: 4px solid var(--accent-color);
    transition: all 0.3s ease;
}

.day-event-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0, 255, 136, 0.3);
}

.day-event-item.registered {
    border-left-color: var(--success-color);
    background: rgba(40, 167, 69, 0.1);
}

.day-event-time {
    font-size: 1.2rem;
    font-weight: bold;
    color: var(--accent-color);
    margin-bottom: 10px;
}

.day-event-fee {
    color: var(--accent-color);
    font-weight: bold;
    margin-top: 5px;
}

/* Important Dates */
.important-date-item {
    background: var(--card-bg);
    border-radius: 6px;
    padding: 10px;
    border-left: 3px solid var(--accent-color);
    transition: all 0.3s ease;
}

.important-date-item:hover {
    transform: translateX(3px);
}

.important-dates {
    max-height: 400px;
    overflow-y: auto;
}

/* Responsive Design */
@media (max-width: 768px) {
    .calendar-grid {
        grid-template-columns: 1fr;
    }

    .week-view {
        grid-template-columns: 1fr;
    }

    .col-lg-3 {
        margin-bottom: 2rem;
    }
}

/* Calendar Animations */
@keyframes slideInRight {
    from {
        transform: translateX(-100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.calendar-day {
    animation: slideInRight 0.3s ease-out;
}

.week-event-item {
    animation: slideInRight 0.3s ease-out;
}

.day-event-item {
    animation: slideInRight 0.3s ease-out;
}
</style>

<script>
let currentView = '<?php echo $view; ?>';
let currentDate = '<?php echo $date; ?>';

function navigateCalendar(direction) {
    let url = new URL(window.location);

    if (currentView === 'month') {
        const date = new Date(currentDate);
        if (direction === 'previous') {
            date.setMonth(date.getMonth() - 1);
        } else if (direction === 'next') {
            date.setMonth(date.getMonth() + 1);
        } else if (direction === 'today') {
            // Go to current month
            date.setMonth(new Date().getMonth());
            date.setFullYear(new Date().getFullYear());
        }
        url.searchParams.set('date', date.toISOString().split('T')[0]);
    } else if (currentView === 'week') {
        const date = new Date(currentDate);
        if (direction === 'previous') {
            date.setDate(date.getDate() - 7);
        } else if (direction === 'next') {
            date.setDate(date.getDate() + 7);
        } else if (direction === 'today') {
            date.setDate(new Date().getDate());
        }
        url.searchParams.set('date', date.toISOString().split('T')[0]);
    } else if (currentView === 'day') {
        const date = new Date(currentDate);
        if (direction === 'previous') {
            date.setDate(date.getDate() - 1);
        } else if (direction === 'next') {
            date.setDate(date.getDate() + 1);
        } else if (direction === 'today') {
            date.setDate(new Date().getDate());
        }
        url.searchParams.set('date', date.toISOString().split('T')[0]);
    }

    window.location.href = url.toString();
}

function showEventDetails(eventId) {
    fetch('events.php?action=get_event_details&id=' + eventId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const event = data.data;
                let content = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-calendar me-2"></i>Date & Time</h6>
                            <p>${formatDate(event.event_date, true)}</p>

                            <h6><i class="fas fa-map-marker-alt me-2"></i>Location</h6>
                            <p>${event.location || 'TBD'}</p>

                            <h6><i class="fas fa-tag me-2"></i>Category</h6>
                            <p>${event.category || 'General'}</p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-rupee-sign me-2"></i>Registration Fee</h6>
                            <p>${event.fee > 0 ? '₹' + parseFloat(event.fee).toFixed(2) : 'Free'}</p>

                            <h6><i class="fas fa-users me-2"></i>Capacity</h6>
                            <p>${event.max_participants ? event.max_participants + ' participants' : 'Unlimited'}</p>

                            <h6><i class="fas fa-clock me-2"></i>Registration Deadline</h6>
                            <p>${event.registration_deadline ? formatDate(event.registration_deadline, true) : 'No deadline'}</p>
                        </div>
                    </div>

                    <div class="mt-3">
                        <h6><i class="fas fa-info-circle me-2"></i>Description</h6>
                        <p>${event.description || 'No description available'}</p>
                    </div>
                `;

                document.getElementById('eventDetailsContent').innerHTML = content;
                document.getElementById('addToGoogleBtn').onclick = function() {
                    addToGoogleCalendar(event.id);
                };

                new bootstrap.Modal(document.getElementById('eventDetailsModal')).show();
            }
        })
        .catch(error => console.error('Error:', error));
}

function addToGoogleCalendar(eventId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="event_id" value="${eventId}">
        <input type="hidden" name="add_to_google" value="1">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'ArrowLeft' && !e.target.matches('input, textarea')) {
        navigateCalendar('previous');
    } else if (e.key === 'ArrowRight' && !e.target.matches('input, textarea')) {
        navigateCalendar('next');
    } else if (e.key === 't' && !e.target.matches('input, textarea')) {
        navigateCalendar('today');
    }
});
</script>

<?php require_once '../includes/student_footer.php'; ?>