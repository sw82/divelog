<?php
/**
 * Database Management Script
 * 
 * This script provides functionality for database management, including:
 * - Backup database (schema and content)
 * - Restore database from backup
 * - Export data to CSV
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
        echo "<div class='notification success'>" . $message . "</div>";
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
        echo "<div class='notification error'>" . $message . "</div>";
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

/**
 * Function to get fish sightings for a dive (used for CSV export)
 */
function getFishSightings($divelogId) {
    global $conn;
    $sightings = [];
    
    $stmt = $conn->prepare("
        SELECT fs.*, f.common_name, f.scientific_name
        FROM fish_sightings fs
        JOIN fish_species f ON fs.fish_species_id = f.id
        WHERE fs.divelog_id = ?
        ORDER BY fs.sighting_date DESC
    ");
    $stmt->bind_param("i", $divelogId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $sightings[] = $row;
    }
    
    $stmt->close();
    return $sightings;
}

// Handle export to CSV
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=dive_logs_export.csv');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Write CSV header
    fputcsv($output, [
        'ID',
        'Location',
        'Latitude',
        'Longitude',
        'Date',
        'Time',
        'Description',
        'Depth (m)',
        'Duration (min)',
        'Water Temperature (°C)',
        'Air Temperature (°C)',
        'Visibility (m)',
        'Buddy',
        'Dive Site Type',
        'Activity Type',
        'Rating',
        'Comments',
        'Fish Sightings'
    ], ',', '"', '\\');

    // Fetch all dive logs
    $query = "SELECT * FROM divelogs ORDER BY date DESC";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Get fish sightings for this dive
            $fishSightings = getFishSightings($row['id']);
            $fishSightingsText = [];
            
            foreach ($fishSightings as $sighting) {
                $fishText = $sighting['common_name'];
                if ($sighting['scientific_name']) {
                    $fishText .= " ({$sighting['scientific_name']})";
                }
                if ($sighting['quantity']) {
                    $fishText .= " - {$sighting['quantity']}";
                }
                if ($sighting['notes']) {
                    $fishText .= " - {$sighting['notes']}";
                }
                $fishSightingsText[] = $fishText;
            }
            
            // Write row to CSV
            fputcsv($output, [
                $row['id'],
                $row['location'],
                $row['latitude'],
                $row['longitude'],
                $row['date'],
                $row['dive_time'],
                $row['description'],
                $row['depth'],
                $row['duration'],
                $row['temperature'],
                $row['air_temperature'],
                $row['visibility'],
                $row['buddy'],
                $row['dive_site_type'],
                $row['activity_type'],
                $row['rating'],
                $row['comments'],
                implode('; ', $fishSightingsText)
            ], ',', '"', '\\');
        }
    }

    fclose($output);
    exit;
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

// Handle backup creation if requested
if (isset($_GET['action']) && $_GET['action'] === 'create_backup') {
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
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Management</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .card {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: #f9f9f9;
        }
        .card h2 {
            margin-top: 0;
            color: #2196F3;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .action-button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .action-button:hover {
            background-color: #0b7dda;
        }
        .danger-button {
            background-color: #f44336;
        }
        .danger-button:hover {
            background-color: #d32f2f;
        }
        .success-button {
            background-color: #4CAF50;
        }
        .success-button:hover {
            background-color: #45a049;
        }
        .backup-list {
            margin-top: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .backup-list-item {
            padding: 15px;
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
            margin-top: 5px;
        }
        .backup-size {
            background-color: #f5f5f5;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 14px;
        }
        .action-links a {
            padding: 5px 10px;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            margin-left: 10px;
            display: inline-block;
        }
        .download-link {
            background-color: #4CAF50;
        }
        .download-link:hover {
            background-color: #45a049;
        }
        .delete-link {
            background-color: #f44336;
        }
        .delete-link:hover {
            background-color: #d32f2f;
        }
        .notification {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .success {
            background-color: #f5f5f5;
            border-left: 5px solid #4CAF50;
        }
        .error {
            background-color: #ffebee;
            border-left: 5px solid #f44336;
        }
    </style>
</head>
<body>
    <?php include 'navigation.php'; ?>
    
    <div class="container">
        <h1>Database Management</h1>
        
        <div class="card">
            <h2>Database Backup</h2>
            <p>Create a backup of your database to preserve your dive logs and fish data.</p>
            <a href="?action=create_backup" class="action-button success-button">Create New Backup</a>
            
            <h3>Available Backups</h3>
            <?php
            // Get all backup files
            $backupFiles = glob($backupDir . '/*.sql');
            
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
            ?>
        </div>
        
        <div class="card">
            <h2>Export Data</h2>
            <p>Export your dive logs to CSV format for backup or analysis in spreadsheet software.</p>
            <a href="?action=export_csv" class="action-button">Export to CSV</a>
        </div>
        
        <div class="card">
            <h2>Import Data</h2>
            <p>Import dive logs from your paper logbook using OCR technology.</p>
            <a href="import.php" class="action-button">Import from Logbook</a>
        </div>
    </div>
</body>
</html> 