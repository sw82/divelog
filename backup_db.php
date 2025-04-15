<?php
/**
 * Database Backup Script
 * 
 * This script creates a backup of both the database schema and content.
 * It saves the backup as a SQL file that can be imported later if needed.
 */

// Database connection details - will be replaced by the values from db.php
$db_config = [];

// Include the db.php file to get the database connection details
if (file_exists('db.php')) {
    // Capture the output to prevent it from being displayed
    ob_start();
    include 'db.php';
    ob_end_clean();
    
    // Get the database connection details from the global variables
    $db_config = [
        'host' => $servername,
        'user' => $username,
        'pass' => $password,
        'name' => $dbname
    ];
} else {
    die("Database configuration file (db.php) not found.");
}

// Create a unique filename for the backup
$timestamp = date('Y-m-d_H-i-s');
$backupDir = 'backups';
$backupFileName = $backupDir . '/' . $db_config['name'] . '_' . $timestamp . '.sql';

// Create the backups directory if it doesn't exist
if (!file_exists($backupDir)) {
    if (!mkdir($backupDir, 0755, true)) {
        die("Failed to create backup directory.");
    }
}

// Check if this is a CLI or web request
$isCLI = (php_sapi_name() === 'cli');

/**
 * Function to output messages based on request type
 */
function output($message) {
    global $isCLI;
    if ($isCLI) {
        echo $message . PHP_EOL;
    } else {
        echo "<div style='margin: 10px 0; padding: 10px; background-color: #f5f5f5; border-left: 5px solid #4CAF50;'>" . $message . "</div>";
    }
}

/**
 * Function to output error messages based on request type
 */
function outputError($message) {
    global $isCLI;
    if ($isCLI) {
        echo "ERROR: " . $message . PHP_EOL;
    } else {
        echo "<div style='margin: 10px 0; padding: 10px; background-color: #ffebee; border-left: 5px solid #f44336;'>" . $message . "</div>";
    }
}

// If not CLI, output HTML headers
if (!$isCLI) {
    echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Backup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        h1 {
            color: #2196F3;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .backup-list {
            margin-top: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .backup-list-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .backup-list-item:last-child {
            border-bottom: none;
        }
        .backup-date {
            color: #666;
            font-size: 14px;
        }
        .backup-size {
            background-color: #f5f5f5;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 14px;
        }
        .download-link {
            padding: 5px 10px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        .download-link:hover {
            background-color: #45a049;
        }
        .delete-link {
            padding: 5px 10px;
            background-color: #f44336;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            margin-left: 10px;
        }
        .delete-link:hover {
            background-color: #d32f2f;
        }
        .action-links {
            display: flex;
        }
        .back-link {
            margin-top: 20px;
            display: inline-block;
            padding: 8px 16px;
            background-color: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .back-link:hover {
            background-color: #0b7dda;
        }
        .export-section {
            margin-top: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #f9f9f9;
        }
        .export-button {
            padding: 10px 20px;
            background-color: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            margin-top: 10px;
        }
        .export-button:hover {
            background-color: #0b7dda;
        }
    </style>
</head>
<body>
    <h1>Database Backup</h1>
";
}

// Handle delete request if specified
if (isset($_GET['delete'])) {
    $deleteFile = basename($_GET['delete']);
    $deleteFilePath = $backupDir . '/' . $deleteFile;
    
    // Validate file path is within backup directory to prevent directory traversal
    $realDeletePath = realpath($deleteFilePath);
    $realBackupDir = realpath($backupDir);
    
    if ($realDeletePath && strpos($realDeletePath, $realBackupDir) === 0 && file_exists($deleteFilePath)) {
        if (unlink($deleteFilePath)) {
            output("Backup file deleted: " . $deleteFile);
        } else {
            outputError("Failed to delete backup file: " . $deleteFile);
        }
    } else {
        outputError("Invalid backup file specified.");
    }
    
    // If not CLI, provide a back link
    if (!$isCLI) {
        echo "<a href='?action=list' class='back-link'>Back to Backup List</a>";
    }
    
    // Exit to prevent further processing
    if (!$isCLI) {
        echo "</body></html>";
    }
    exit;
}

// Handle download request if specified
if (isset($_GET['download'])) {
    $downloadFile = basename($_GET['download']);
    $downloadFilePath = $backupDir . '/' . $downloadFile;
    
    // Validate file path is within backup directory to prevent directory traversal
    $realDownloadPath = realpath($downloadFilePath);
    $realBackupDir = realpath($backupDir);
    
    if ($realDownloadPath && strpos($realDownloadPath, $realBackupDir) === 0 && file_exists($downloadFilePath)) {
        // Set headers for file download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $downloadFile . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($downloadFilePath));
        readfile($downloadFilePath);
        exit;
    } else {
        outputError("Invalid backup file specified.");
    }
}

// List existing backups if requested
if (isset($_GET['action']) && $_GET['action'] === 'list') {
    // Get all backup files
    $backupFiles = glob($backupDir . '/*.sql');
    
    if (!$isCLI) {
        echo "<h2>Available Backups</h2>";
        
        if (empty($backupFiles)) {
            echo "<p>No backup files found.</p>";
        } else {
            echo "<div class='backup-list'>";
            
            // Sort by newest first
            usort($backupFiles, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            foreach ($backupFiles as $file) {
                $fileName = basename($file);
                $fileSize = filesize($file);
                $formattedSize = formatFileSize($fileSize);
                $fileDate = date('F j, Y, g:i a', filemtime($file));
                
                echo "<div class='backup-list-item'>";
                echo "<div>";
                echo "<div>" . $fileName . "</div>";
                echo "<div class='backup-date'>" . $fileDate . "</div>";
                echo "</div>";
                echo "<div>";
                echo "<span class='backup-size'>" . $formattedSize . "</span>";
                echo "<div class='action-links'>";
                echo "<a href='?download=" . urlencode($fileName) . "' class='download-link'>Download</a>";
                echo "<a href='?delete=" . urlencode($fileName) . "' class='delete-link' onclick=\"return confirm('Are you sure you want to delete this backup file?');\">Delete</a>";
                echo "</div>";
                echo "</div>";
                echo "</div>";
            }
            
            echo "</div>";
        }
        
        echo "<a href='?' class='back-link'>Create New Backup</a>";
        echo "</body></html>";
        exit;
    } else {
        // CLI output for listing backups
        output("Available Backups:");
        
        if (empty($backupFiles)) {
            output("No backup files found.");
        } else {
            foreach ($backupFiles as $file) {
                $fileName = basename($file);
                $fileSize = filesize($file);
                $formattedSize = formatFileSize($fileSize);
                $fileDate = date('F j, Y, g:i a', filemtime($file));
                
                output($fileName . " (" . $formattedSize . ") - " . $fileDate);
            }
        }
        exit;
    }
}

/**
 * Format file size in human-readable format
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Create the backup command using mysqldump
$command = sprintf(
    'mysqldump --opt -h %s -u %s --password=%s %s > %s',
    escapeshellarg($db_config['host']),
    escapeshellarg($db_config['user']),
    escapeshellarg($db_config['pass']),
    escapeshellarg($db_config['name']),
    escapeshellarg($backupFileName)
);

// Execute the backup command
$output = [];
$exitCode = 0;
exec($command, $output, $exitCode);

if ($exitCode === 0) {
    output("Backup created successfully: " . basename($backupFileName));
    output("File size: " . formatFileSize(filesize($backupFileName)));
} else {
    outputError("Backup failed. Exit code: " . $exitCode);
    outputError("Command output: " . implode("\n", $output));
}

// Add .gitignore to the backups directory if it doesn't exist
$gitignoreFile = $backupDir . '/.gitignore';
if (!file_exists($gitignoreFile)) {
    file_put_contents($gitignoreFile, "# Ignore all backup files\n*.sql\n");
    output("Created .gitignore file to exclude database backups from git repository.");
}

if (!$isCLI) {
    echo "<a href='?action=list' class='back-link'>View All Backups</a>";

    // Add CSV export section
    echo "<div class='export-section'>";
    echo "<h2>Export Data</h2>";
    echo "<p>Export your dive logs to CSV format for backup or analysis.</p>";
    echo "<a href='export_csv.php' class='export-button'>Export to CSV</a>";
    echo "</div>";

    echo "</body></html>";
}
?> 