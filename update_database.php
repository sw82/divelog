<?php
// Include database connection
include 'db.php';

// Output messages
$messages = [];

// Check if the connection is successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start transaction
$conn->begin_transaction();

try {
    // Check and add dive_site column if it doesn't exist
    $checkDiveSiteColumn = $conn->query("SHOW COLUMNS FROM divelogs LIKE 'dive_site'");
    
    if ($checkDiveSiteColumn->num_rows == 0) {
        // Add the dive_site column
        $alterDiveSiteQuery = "ALTER TABLE divelogs ADD COLUMN dive_site VARCHAR(255) COMMENT 'Name of the specific dive site'";
        
        if ($conn->query($alterDiveSiteQuery)) {
            $messages[] = "<div class='success'>Successfully added dive_site column to divelogs table.</div>";
            
            // Create index for faster searching
            $createIndexQuery = "CREATE INDEX idx_dive_site ON divelogs(dive_site)";
            if ($conn->query($createIndexQuery)) {
                $messages[] = "<div class='success'>Successfully created index on dive_site column.</div>";
                
                // Update existing records with location as dive_site
                $updateDiveSiteQuery = "UPDATE divelogs SET dive_site = location WHERE dive_site IS NULL";
                if ($conn->query($updateDiveSiteQuery)) {
                    $messages[] = "<div class='success'>Successfully updated existing records with location as dive_site.</div>";
                } else {
                    throw new Exception("Error updating records: " . $conn->error);
                }
            } else {
                throw new Exception("Error creating index: " . $conn->error);
            }
        } else {
            throw new Exception("Error adding dive_site column: " . $conn->error);
        }
    } else {
        $messages[] = "<div class='info'>The dive_site column already exists in the divelogs table.</div>";
    }
    
    // Check for common indexes and add if missing
    $checkDateIndex = $conn->query("SHOW INDEX FROM divelogs WHERE Key_name = 'idx_date'");
    
    if ($checkDateIndex->num_rows == 0) {
        $createDateIndexQuery = "CREATE INDEX idx_date ON divelogs(date)";
        if ($conn->query($createDateIndexQuery)) {
            $messages[] = "<div class='success'>Successfully created index on date column.</div>";
        } else {
            $messages[] = "<div class='warning'>Failed to create index on date column: " . $conn->error . "</div>";
        }
    }
    
    $checkLocationIndex = $conn->query("SHOW INDEX FROM divelogs WHERE Key_name = 'idx_location'");
    
    if ($checkLocationIndex->num_rows == 0) {
        $createLocationIndexQuery = "CREATE INDEX idx_location ON divelogs(location)";
        if ($conn->query($createLocationIndexQuery)) {
            $messages[] = "<div class='success'>Successfully created index on location column.</div>";
        } else {
            $messages[] = "<div class='warning'>Failed to create index on location column: " . $conn->error . "</div>";
        }
    }
    
    // Check for common indexes and add if missing
    $requiredIndexes = [
        ['table' => 'divelogs', 'column' => 'date', 'name' => 'idx_date'],
        ['table' => 'divelogs', 'column' => 'location', 'name' => 'idx_location'],
        ['table' => 'fish_sightings', 'column' => 'fish_species_id', 'name' => 'idx_fish_species'],
        ['table' => 'fish_sightings', 'column' => 'divelog_id', 'name' => 'idx_divelog']
    ];
    
    // Check and add technical dive details columns
    $technicalDiveColumns = [
        ['name' => 'air_consumption_start', 'sql' => "ADD COLUMN air_consumption_start INT COMMENT 'Starting air pressure in bar'"],
        ['name' => 'air_consumption_end', 'sql' => "ADD COLUMN air_consumption_end INT COMMENT 'Ending air pressure in bar'"],
        ['name' => 'weight', 'sql' => "ADD COLUMN weight DECIMAL(5, 2) COMMENT 'Weight used in kg'"],
        ['name' => 'suit_type', 'sql' => "ADD COLUMN suit_type ENUM('wetsuit', 'drysuit', 'shortie', 'swimsuit', 'other') COMMENT 'Type of exposure suit worn'"],
        ['name' => 'water_type', 'sql' => "ADD COLUMN water_type ENUM('salt', 'fresh', 'brackish') COMMENT 'Type of water body'"]
    ];

    foreach ($technicalDiveColumns as $column) {
        $checkColumn = $conn->query("SHOW COLUMNS FROM divelogs LIKE '{$column['name']}'");
        
        if ($checkColumn->num_rows == 0) {
            $alterQuery = "ALTER TABLE divelogs {$column['sql']}";
            
            if ($conn->query($alterQuery)) {
                $messages[] = "<div class='success'>Successfully added {$column['name']} column to divelogs table.</div>";
            } else {
                $messages[] = "<div class='warning'>Failed to add {$column['name']} column: " . $conn->error . "</div>";
            }
        } else {
            $messages[] = "<div class='info'>The {$column['name']} column already exists in the divelogs table.</div>";
        }
    }

    foreach ($requiredIndexes as $index) {
        $indexQuery = "SHOW INDEX FROM {$index['table']} WHERE Column_name = '{$index['column']}' AND Key_name = '{$index['name']}'";
        $indexResult = $conn->query($indexQuery);
        
        if ($indexResult && $indexResult->num_rows == 0) {
            $createIndexQuery = "CREATE INDEX {$index['name']} ON {$index['table']}({$index['column']})";
            if ($conn->query($createIndexQuery)) {
                $messages[] = "<div class='success'>Created index {$index['name']} on {$index['table']}.{$index['column']}.</div>";
            } else {
                $messages[] = "<div class='warning'>Failed to create index {$index['name']}: " . $conn->error . "</div>";
            }
        }
    }
    
    // Commit the transaction
    $conn->commit();
    $messages[] = "<div class='success'>Database updated successfully!</div>";
    
} catch (Exception $e) {
    // Rollback the transaction if something went wrong
    $conn->rollback();
    $messages[] = "<div class='error'>Error: " . $e->getMessage() . "</div>";
}

// Close the connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Update</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navigation.php'; ?>
    
    <div class="container">
        <h1>Database Update</h1>
        
        <div class="messages">
            <?php foreach ($messages as $message): ?>
                <?php echo $message; ?>
            <?php endforeach; ?>
        </div>
        
        <div class="actions">
            <a href="index.php" class="btn">Return to Home</a>
            <a href="populate_db.php" class="btn">Manage Dives</a>
            <a href="manage_db.php" class="btn">Database Management</a>
        </div>
        
        <div class="info-box">
            <h3>About Database Updates</h3>
            <p>This page updates your database schema to the latest version, implementing any necessary changes to support new features.</p>
            <p>For a complete SQL setup script, see the <code>database_setup.sql</code> file in the project root.</p>
        </div>
    </div>
</body>
</html> 