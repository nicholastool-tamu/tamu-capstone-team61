<?php
// Ensure this page isn't directly accessed
if (!defined('ALLOW_ACCESS')) {
    header("Location: ../index.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>System Log</title>
    <style>
        .log-section {
            margin: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #f9f9f9;
        }
        .file-name {
            font-weight: bold;
            color: #1976d2;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
        }
        .clear-button {
            margin: 20px;
            padding: 10px 20px;
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .clear-button:hover {
            background-color: #d32f2f;
        }
        .error-message {
            color: #d32f2f;
        }
        .notice-success {
            color: #2e7d32;
        }
        .notice-info {
            color: #1976d2;
        }
        .notice-warning {
            color: #ed6c02;
        }
        .log-type {
            font-weight: bold;
            margin-top: 10px;
            padding-top: 5px;
        }
        ul {
            list-style-type: none;
            padding-left: 15px;
        }
        li {
            margin: 5px 0;
            padding: 5px;
            border-radius: 3px;
        }
        li:hover {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>
    <h1>System Log</h1>
    
    <?php 
    $hasContent = (isset($_SESSION['errors']) && !empty($_SESSION['errors'])) || 
                 (isset($_SESSION['notices']) && !empty($_SESSION['notices']));
    
    if ($hasContent): ?>
        <a href="?clear=1"><button class="clear-button">Clear All Logs</button></a>
        
        <?php 
        // Get all unique file names from both errors and notices
        $files = array_unique(array_merge(
            array_keys(isset($_SESSION['errors']) ? $_SESSION['errors'] : []),
            array_keys(isset($_SESSION['notices']) ? $_SESSION['notices'] : [])
        ));
        
        foreach ($files as $file): ?>
            <div class="log-section">
                <div class="file-name"><?php echo htmlspecialchars(basename($file)); ?></div>
                
                <?php if (isset($_SESSION['errors'][$file])): ?>
                    <div class="log-type">Errors:</div>
                    <ul>
                        <?php foreach ($_SESSION['errors'][$file] as $error): ?>
                            <li class="error-message"><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html> 