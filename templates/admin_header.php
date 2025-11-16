<?php
// SPARK Platform - Admin Header Template
if (!function_exists('generateToken')) {
    require_once __DIR__ . '/../includes/config.php';
}
if (!function_exists('getPDO')) {
    require_once __DIR__ . '/../includes/database.php';
}
if (!function_exists('getCurrentUser')) {
    require_once __DIR__ . '/../includes/auth.php';
}

// Initialize variables to prevent undefined warnings
$meta_description = $meta_description ?? 'SPARK Admin Panel - Manage Events, Students, and Platform';
$meta_keywords = $meta_keywords ?? 'SPARK Admin, Event Management, Student Management';
$page_title = $page_title ?? 'Admin Dashboard';
$page_subtitle = $page_subtitle ?? '';
$breadcrumb = $breadcrumb ?? [];
$hide_page_header = $hide_page_header ?? false;
$show_alert = $show_alert ?? false;
$include_calendar = $include_calendar ?? false;
$include_admin_nav = true;
$include_student_nav = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($meta_keywords); ?>">
    <title><?php echo htmlspecialchars($page_title); ?> | SPARK Admin</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/assets/images/favicon.ico">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- AOS Animation Library -->
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">

    <!-- FullCalendar CSS -->
    <?php if ($include_calendar): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <?php endif; ?>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/custom.css">
</head>
<body class="black-theme admin-panel">

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay" style="display: none;">
        <div class="loader">
            <div class="spark-loader"></div>
            <p>Loading SPARK Admin...</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="<?php echo SITE_URL; ?>/admin/">
                <div class="logo-container">
                    <div class="spark-logo">
                        <i class="fas fa-bolt"></i>
                    </div>
                </div>
                <span class="brand-text ms-2">SPARK Admin</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <?php include __DIR__ . '/admin-nav.php'; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content admin-content">
        <?php if (!$hide_page_header): ?>
        <div class="page-header admin-page-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="page-title" data-aos="fade-right"><?php echo htmlspecialchars($page_title); ?></h1>
                        <?php if ($page_subtitle): ?>
                        <p class="page-subtitle" data-aos="fade-right" data-aos-delay="100"><?php echo htmlspecialchars($page_subtitle); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($breadcrumb)): ?>
                    <div class="col-md-4">
                        <nav aria-label="breadcrumb" data-aos="fade-left">
                            <ol class="breadcrumb justify-content-md-end">
                                <?php foreach ($breadcrumb as $crumb): ?>
                                <li class="breadcrumb-item <?php echo $crumb['active'] ? 'active' : ''; ?>">
                                    <?php if (!$crumb['active']): ?>
                                        <a href="<?php echo SITE_URL; ?>/admin/<?php echo $crumb['link']; ?>"><?php echo htmlspecialchars($crumb['name']); ?></a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($crumb['name']); ?>
                                    <?php endif; ?>
                                </li>
                                <?php endforeach; ?>
                            </ol>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="page-content">
            <?php if ($show_alert): ?>
            <div class="container-fluid mt-4">
                <?php include __DIR__ . '/../includes/alerts.php'; ?>
            </div>
            <?php endif; ?>
