<?php
// SPARK Platform - Alert Messages Component

// Show success messages
if (isset($_SESSION['success'])) {
    foreach ($_SESSION['success'] as $message) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
        echo '<i class="fas fa-check-circle me-2"></i>' . htmlspecialchars($message);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
    unset($_SESSION['success']);
}

// Show error messages
if (isset($_SESSION['errors'])) {
    foreach ($_SESSION['errors'] as $message) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
        echo '<i class="fas fa-exclamation-circle me-2"></i>' . htmlspecialchars($message);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
    unset($_SESSION['errors']);
}

// Show warning messages
if (isset($_SESSION['warnings'])) {
    foreach ($_SESSION['warnings'] as $message) {
        echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">';
        echo '<i class="fas fa-exclamation-triangle me-2"></i>' . htmlspecialchars($message);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
    unset($_SESSION['warnings']);
}

// Show info messages
if (isset($_SESSION['info'])) {
    foreach ($_SESSION['info'] as $message) {
        echo '<div class="alert alert-info alert-dismissible fade show" role="alert">';
        echo '<i class="fas fa-info-circle me-2"></i>' . htmlspecialchars($message);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
    unset($_SESSION['info']);
}

// Flash message helper functions
function setFlashMessage($type, $message) {
    if (!isset($_SESSION[$type])) {
        $_SESSION[$type] = [];
    }
    $_SESSION[$type][] = $message;
}

function setSuccess($message) {
    setFlashMessage('success', $message);
}

function setError($message) {
    setFlashMessage('errors', $message);
}

function setWarning($message) {
    setFlashMessage('warnings', $message);
}

function setInfo($message) {
    setFlashMessage('info', $message);
}
?>