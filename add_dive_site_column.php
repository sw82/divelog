<?php
// Include database connection
require_once 'db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting database update...\n";

try {
    // Check if the dive_site column already exists
    $result = $conn->query("SHOW COLUMNS FROM divelogs LIKE 'dive_site'");
    
    if ($result->num_rows > 0) {
        echo "dive_site column already exists in the divelogs table.\n";
    } else {
        // Add the dive_site column
        $sql1 = "ALTER TABLE divelogs ADD COLUMN dive_site VARCHAR(255) COMMENT 'Name of the specific dive site'";
        if ($conn->query($sql1) === TRUE) {
            echo "Successfully added dive_site column to divelogs table.\n";
            
            // Create index for faster searching
            $sql2 = "CREATE INDEX idx_dive_site ON divelogs(dive_site)";
            if ($conn->query($sql2) === TRUE) {
                echo "Successfully created index on dive_site column.\n";
                
                // Update existing records with location as dive_site
                $sql3 = "UPDATE divelogs SET dive_site = location WHERE dive_site IS NULL";
                if ($conn->query($sql3) === TRUE) {
                    echo "Successfully updated existing records with location as dive_site.\n";
                } else {
                    echo "Error updating existing records: " . $conn->error . "\n";
                }
            } else {
                echo "Error creating index: " . $conn->error . "\n";
            }
        } else {
            echo "Error adding column: " . $conn->error . "\n";
        }
    }
    
    echo "Database update completed.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 