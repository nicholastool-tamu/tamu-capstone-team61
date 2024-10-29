<?php
session_start();

// Clear logs if requested
if (isset($_GET['clear'])) {
    unset($_SESSION['errors']);
    unset($_SESSION['notices']);
    header('Location: errors.php');
    exit();
}

// Include logging functions (in case they're needed)
require_once 'includes/logging_functions.php';

// Allow access to the view
define('ALLOW_ACCESS', true);

// Include the display logic
require_once 'includes/logging_views.php';
