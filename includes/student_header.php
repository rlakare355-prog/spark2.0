<?php
// SPARK Platform - Student Page Header
if (!function_exists('generateToken')) {
    require_once __DIR__ . '/config.php';
}
if (!function_exists('loginStudent')) {
    require_once __DIR__ . '/auth.php';
}
if (!function_exists('getPDO')) {
    require_once __DIR__ . '/database.php';
}

// Require login
requireLogin();

// Check if user has required role
if (isset($required_roles)) {
    if (!is_array($required_roles)) {
        $required_roles = [$required_roles];
    }
    requireRole($required_roles);
}

// Include calendar CSS
$include_calendar = true;

$page_title = $page_title ?? 'Student Portal';
$breadcrumb = $breadcrumb ?? [];
$hide_page_header = $hide_page_header ?? false;
$meta_description = $meta_description ?? '';
$meta_keywords = $meta_keywords ?? '';

include __DIR__ . '/../templates/header.php';
?>