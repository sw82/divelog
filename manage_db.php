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

// Handle clear database request
if (isset($_GET['action']) && $_GET['action'] === 'clear_db') {
    // Only proceed if confirmation is given
    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Delete data from all tables in the correct order to respect foreign key constraints
            $tables = [
                'fish_sightings',
                'divelog_images',
                'divelogs',
                'fish_images',
                'fish_species'
            ];
            
            foreach ($tables as $table) {
                $sql = "TRUNCATE TABLE $table";
                if (!$conn->query($sql)) {
                    throw new Exception("Error clearing table $table: " . $conn->error);
                }
            }
            
            // Commit transaction
            $conn->commit();
            output("All data has been cleared from the database.");
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            outputError("Database operation failed: " . $e->getMessage());
        }
    }
}

// Handle populate with sample data request
if (isset($_GET['action']) && $_GET['action'] === 'populate_sample') {
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // 1. Add sample fish species
        $fishSpecies = [
            ['Clownfish', 'Amphiprion ocellaris', 'A small bright orange fish with white stripes'],
            ['Blue Tang', 'Paracanthurus hepatus', 'A bright blue surgeonfish with black markings'],
            ['Moorish Idol', 'Zanclus cornutus', 'Distinguished by its white, yellow and black coloration'],
            ['Parrotfish', 'Scarus psittacus', 'Known for its bright colors and beak-like mouth'],
            ['Manta Ray', 'Manta birostris', 'Large rays with triangular pectoral fins']
        ];
        
        $fishIds = [];
        $stmt = $conn->prepare("INSERT INTO fish_species (common_name, scientific_name, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $commonName, $scientificName, $description);
        
        foreach ($fishSpecies as $fish) {
            $commonName = $fish[0];
            $scientificName = $fish[1];
            $description = $fish[2];
            $stmt->execute();
            $fishIds[] = $conn->insert_id;
        }
        $stmt->close();
        
        // 2. Add sample dive locations
        $diveLocations = [
            ['Great Barrier Reef', -16.7551, 145.9023, '2023-06-15', 'Beautiful coral formations with diverse marine life', 18.5, 45, 26, 29, 15, 'Sarah', 'Reef', 'diving', 5, 'Amazing visibility and vibrant coral colors'],
            ['Bali Coral Garden', -8.6478, 115.1374, '2023-07-22', 'Colorful coral garden with many small fish', 12.3, 38, 28, 32, 20, 'Mike', 'Reef', 'diving', 4, 'Great spot for underwater photography'],
            ['Florida Keys', 24.5557, -81.7826, '2023-05-10', 'Shallow reef with numerous fish species', 8.7, 55, 25, 30, 18, 'John', 'Reef', 'snorkeling', 4, 'Perfect for beginners'],
            ['Cozumel Drift', 20.4230, -86.9223, '2023-08-05', 'Fast drift dive along vibrant wall', 22.1, 42, 27, 31, 25, 'Lisa', 'Wall', 'diving', 5, 'Exhilarating current and great visibility']
        ];
        
        $diveIds = [];
        $stmt = $conn->prepare("INSERT INTO divelogs (location, latitude, longitude, date, description, depth, duration, temperature, air_temperature, visibility, buddy, dive_site_type, activity_type, rating, comments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sddssddddssssss", $location, $latitude, $longitude, $date, $description, $depth, $duration, $temperature, $airTemp, $visibility, $buddy, $siteType, $activityType, $rating, $comments);
        
        foreach ($diveLocations as $dive) {
            $location = $dive[0];
            $latitude = $dive[1];
            $longitude = $dive[2];
            $date = $dive[3];
            $description = $dive[4];
            $depth = $dive[5];
            $duration = $dive[6];
            $temperature = $dive[7];
            $airTemp = $dive[8];
            $visibility = $dive[9];
            $buddy = $dive[10];
            $siteType = $dive[11];
            $activityType = $dive[12];
            $rating = $dive[13];
            $comments = $dive[14];
            $stmt->execute();
            $diveIds[] = $conn->insert_id;
        }
        $stmt->close();
        
        // 3. Add fish sightings
        $stmt = $conn->prepare("INSERT INTO fish_sightings (divelog_id, fish_species_id, sighting_date, quantity, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $divelogId, $fishSpeciesId, $sightingDate, $quantity, $notes);
        
        // Random sightings for each dive
        foreach ($diveIds as $diveId) {
            // Get the date for this dive
            $diveQuery = "SELECT date FROM divelogs WHERE id = $diveId";
            $result = $conn->query($diveQuery);
            $diveDate = $result->fetch_assoc()['date'];
            
            // Add 2-3 random fish sightings
            $numSightings = rand(2, 3);
            for ($i = 0; $i < $numSightings; $i++) {
                $fishIndex = array_rand($fishIds);
                $fishSpeciesId = $fishIds[$fishIndex];
                $divelogId = $diveId;
                $sightingDate = $diveDate;
                $quantities = ['single', 'few', 'many', 'school'];
                $quantity = $quantities[array_rand($quantities)];
                $notes = "Observed during dive at " . rand(5, 20) . "m depth";
                $stmt->execute();
            }
        }
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        output("Sample data has been successfully added to the database.");
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        outputError("Failed to add sample data: " . $e->getMessage());
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
            <h2>Database Operations</h2>
            <p>Warning: These operations can result in permanent data loss. Use with caution.</p>
            <a href="?action=clear_db&confirm=yes" class="action-button danger-button" onclick="return confirm('WARNING: This will delete ALL data from your database. This action cannot be undone unless you have a backup. Are you sure you want to continue?');">Clear All Data</a>
            <a href="?action=populate_sample" class="action-button" onclick="return confirm('This will add sample data to your database. Continue?');">Populate with Sample Data</a>
        </div>
        
        <div class="card">
            <h2>Import Data</h2>
            <p>Import dive logs from your paper logbook using OCR technology or from CSV files.</p>
            <a href="import.php" class="action-button">Import from Logbook</a>
            <a href="import_csv.php" class="action-button">Import from CSV</a>
        </div>
    </div>
</body>
</html> 