<?php
// SPARK Platform - Student Navigation
$currentUser = getCurrentUser();
?>
<ul class="navbar-nav ms-auto">
    <li class="nav-item">
        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/">
            <i class="fas fa-home me-1"></i> Home
        </a>
    </li>
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="eventsDropdown" role="button" data-bs-toggle="dropdown">
            <i class="fas fa-calendar-alt me-1"></i> Events
        </a>
        <ul class="dropdown-menu dropdown-menu-dark">
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/student/events.php">
                <i class="fas fa-list me-2"></i> All Events
            </a></li>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/student/calendar.php">
                <i class="fas fa-calendar me-2"></i> Event Calendar
            </a></li>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/student/attendance.php">
                <i class="fas fa-qrcode me-2"></i> QR Attendance
            </a></li>
        </ul>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/research.php">
            <i class="fas fa-flask me-1"></i> Research
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/opportunities.php">
            <i class="fas fa-briefcase me-1"></i> Opportunities
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/team.php">
            <i class="fas fa-users me-1"></i> Team
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?php echo SITE_URL; ?>/student/gallery.php">
            <i class="fas fa-images me-1"></i> Gallery
        </a>
    </li>
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
            <i class="fas fa-user-circle me-1"></i>
            <?php echo htmlspecialchars($currentUser['first_name'] ?? 'Profile'); ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark">
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/student/dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a></li>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/student/certificates.php">
                <i class="fas fa-certificate me-2"></i> My Certificates
            </a></li>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/student/profile.php">
                <i class="fas fa-user me-2"></i> Profile
            </a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/student/contact.php">
                <i class="fas fa-envelope me-2"></i> Contact Us
            </a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/student/logout.php">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a></li>
        </ul>
    </li>
</ul>