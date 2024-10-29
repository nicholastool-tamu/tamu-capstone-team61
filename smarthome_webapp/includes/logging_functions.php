<?php
// Only functions, no output
function logError($file, $message) {
    if (!isset($_SESSION['errors'])) {
        $_SESSION['errors'] = [];
    }
    if (!isset($_SESSION['errors'][$file])) {
        $_SESSION['errors'][$file] = [];
    }
    $_SESSION['errors'][$file][] = $message;
}

function logNotice($file, $message, $type = 'info') {
    if (!isset($_SESSION['notices'])) {
        $_SESSION['notices'] = [];
    }
    if (!isset($_SESSION['notices'][$file])) {
        $_SESSION['notices'][$file] = [];
    }
    $_SESSION['notices'][$file][] = [
        'message' => $message,
        'type' => $type
    ];
} 