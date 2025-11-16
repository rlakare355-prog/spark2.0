<?php
// SPARK Platform - Admin Navigation
$currentUser = getCurrentUser();
$userRole = $currentUser['role'] ?? '';
?>
<ul class="navbar-nav ms-auto">
    <li class="nav-item">
        <a class="nav-link" href="<?php echo SITE_URL; ?>/admin/">
            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
        </a>
    </li>
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="managementDropdown" role="button" data-bs-toggle="dropdown">
            <i class="fas fa-cogs me-1"></i> Management
        </a>
        <ul class="dropdown-menu dropdown-menu-dark">
            <?php if (hasPermission('manage_events') || in_array($userRole, ['super_admin', 'admin'])): ?>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/events.php">
                <i class="fas fa-calendar-alt me-2"></i> Manage Events
            </a></li>
            <?php endif; ?>
            <?php if (hasPermission('manage_payments') || in_array($userRole, ['super_admin', 'admin', 'accountant'])): ?>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/payments.php">
                <i class="fas fa-credit-card me-2"></i> Manage Payments
            </a></li>
            <?php endif; ?>
            <?php if (hasPermission('manage_attendance') || in_array($userRole, ['super_admin', 'admin', 'event_coordinator'])): ?>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/attendance.php">
                <i class="fas fa-qrcode me-2"></i> Manage Attendance
            </a></li>
            <?php endif; ?>
        </ul>
    </li>
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="contentDropdown" role="button" data-bs-toggle="dropdown">
            <i class="fas fa-edit me-1"></i> Content
        </a>
        <ul class="dropdown-menu dropdown-menu-dark">
            <?php if (hasPermission('manage_team') || in_array($userRole, ['super_admin', 'admin', 'management_head'])): ?>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/team.php">
                <i class="fas fa-users me-2"></i> Manage Team
            </a></li>
            <?php endif; ?>
            <?php if (hasPermission('manage_homepage') || in_array($userRole, ['super_admin', 'admin', 'management_head'])): ?>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/homepage.php">
                <i class="fas fa-home me-2"></i> Manage Homepage
            </a></li>
            <?php endif; ?>
            <?php if (hasPermission('manage_gallery') || in_array($userRole, ['super_admin', 'admin', 'management_head'])): ?>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/gallery.php">
                <i class="fas fa-images me-2"></i> Manage Gallery
            </a></li>
            <?php endif; ?>
        </ul>
    </li>
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="academicDropdown" role="button" data-bs-toggle="dropdown">
            <i class="fas fa-graduation-cap me-1"></i> Academic
        </a>
        <ul class="dropdown-menu dropdown-menu-dark">
            <?php if (hasPermission('manage_research') || in_array($userRole, ['super_admin', 'admin', 'research_coordinator', 'domain_lead'])): ?>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/research.php">
                <i class="fas fa-flask me-2"></i> Research Projects
            </a></li>
            <?php endif; ?>
            <?php if (in_array($userRole, ['super_admin', 'admin', 'event_coordinator'])): ?>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/certificates.php">
                <i class="fas fa-certificate me-2"></i> Certificates
            </a></li>
            <?php endif; ?>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/opportunities.php">
                <i class="fas fa-briefcase me-2"></i> Opportunities
            </a></li>
        </ul>
    </li>
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="systemDropdown" role="button" data-bs-toggle="dropdown">
            <i class="fas fa-server me-1"></i> System
        </a>
        <ul class="dropdown-menu dropdown-menu-dark">
            <?php if (in_array($userRole, ['super_admin', 'admin'])): ?>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/roles.php">
                <i class="fas fa-user-shield me-2"></i> Manage Roles
            </a></li>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/logs.php">
                <i class="fas fa-history me-2"></i> Activity Logs
            </a></li>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/contact.php">
                <i class="fas fa-envelope me-2"></i> Contact Messages
            </a></li>
            <?php endif; ?>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/settings.php">
                <i class="fas fa-cog me-2"></i> Settings
            </a></li>
        </ul>
    </li>
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
            <i class="fas fa-user-circle me-1"></i>
            <?php echo htmlspecialchars($currentUser['first_name'] ?? 'Admin'); ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark">
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/profile.php">
                <i class="fas fa-user me-2"></i> Profile
            </a></li>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/change-password.php">
                <i class="fas fa-lock me-2"></i> Change Password
            </a></li>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/student/" target="_blank">
                <i class="fas fa-external-link-alt me-2"></i> View Student Panel
            </a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/logout.php">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a></li>
        </ul>
    </li>
</ul>