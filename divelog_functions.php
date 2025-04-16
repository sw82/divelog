<?php
// Include database connection
require_once 'db.php';

/**
 * Geocode address to coordinates using OpenStreetMap Nominatim API
 *
 * @param string $address The address to geocode
 * @return array|false Latitude/longitude array or false if geocoding failed
 */
function geocodeAddress($address) {
    // URL encode the address
    $address = urlencode($address);
    
    // OpenStreetMap Nominatim API endpoint (no API key required)
    $url = "https://nominatim.openstreetmap.org/search?format=json&q={$address}&limit=1";
    
    // Set a user agent as required by Nominatim's usage policy
    $options = [
        'http' => [
            'header' => "User-Agent: DiveLogApp/1.0\r\n"
        ]
    ];
    
    $context = stream_context_create($options);
    
    // Make the HTTP request
    $response = file_get_contents($url, false, $context);
    
    // Parse the JSON response
    $data = json_decode($response, true);
    
    // Check if the geocoding was successful
    if (!empty($data)) {
        $latitude = $data[0]['lat'];
        $longitude = $data[0]['lon'];
        return ['latitude' => $latitude, 'longitude' => $longitude];
    } else {
        return false;
    }
}

/**
 * Uploads images associated with a dive log entry
 * 
 * @param int $divelogId The ID of the dive log entry
 * @return array Associative array with upload status
 */
function uploadDiveImages($divelogId) {
    global $conn;
    
    $response = [
        'success' => true,
        'message' => 'All files processed',
        'uploaded' => 0,
        'failed' => 0,
        'file_statuses' => []
    ];
    
    // Process dive photos
    if (!empty($_FILES['images']['name'][0])) {
        processImageUploads($divelogId, 'images', 'dive_photo', $response);
    }
    
    // Process logbook pages
    if (!empty($_FILES['logbook_images']['name'][0])) {
        processImageUploads($divelogId, 'logbook_images', 'logbook_page', $response);
    }
    
    // Update the final message based on results
    if ($response['failed'] > 0 && $response['uploaded'] > 0) {
        $response['message'] = "Uploaded {$response['uploaded']} files, {$response['failed']} failed";
    } elseif ($response['failed'] > 0 && $response['uploaded'] == 0) {
        $response['success'] = false;
        $response['message'] = "Failed to upload all {$response['failed']} files";
    } elseif ($response['uploaded'] > 0) {
        $response['message'] = "Successfully uploaded {$response['uploaded']} files";
    } else {
        $response['message'] = "No files were processed";
    }
    
    return $response;
}

/**
 * Processes image uploads for a specific type
 * 
 * @param int $divelogId The ID of the dive log entry
 * @param string $fileInputName Name of the file input field
 * @param string $imageType Type of image ('dive_photo' or 'logbook_page')
 * @param array &$response Reference to the response array to update
 */
function processImageUploads($divelogId, $fileInputName, $imageType, &$response) {
    global $conn;
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    $targetDir = "uploads/diveimages/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $files = $_FILES[$fileInputName];
    $fileCount = count($files['name']);
    
    for ($i = 0; $i < $fileCount; $i++) {
        $fileStatus = [
            'name' => $files['name'][$i],
            'status' => 'unknown',
            'message' => ''
        ];
        
        // Skip empty file slots
        if (empty($files['name'][$i])) {
            continue;
        }
        
        // Check for errors
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $fileStatus['status'] = 'error';
            $fileStatus['message'] = getUploadErrorMessage($files['error'][$i]);
            $response['failed']++;
            $response['file_statuses'][] = $fileStatus;
            continue;
        }
        
        // Check file type
        $fileType = $files['type'][$i];
        if (!in_array($fileType, $allowedTypes)) {
            $fileStatus['status'] = 'error';
            $fileStatus['message'] = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
            $response['failed']++;
            $response['file_statuses'][] = $fileStatus;
            continue;
        }
        
        // Check file size
        if ($files['size'][$i] > $maxFileSize) {
            $fileStatus['status'] = 'error';
            $fileStatus['message'] = 'File too large. Maximum size is 5MB.';
            $response['failed']++;
            $response['file_statuses'][] = $fileStatus;
            continue;
        }
        
        // Generate unique filename
        $fileExt = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
        $newFilename = uniqid('dive_' . $divelogId . '_') . '.' . $fileExt;
        $targetFile = $targetDir . $newFilename;
        
        // Upload file
        if (move_uploaded_file($files['tmp_name'][$i], $targetFile)) {
            // Insert into database
            $stmt = $conn->prepare("INSERT INTO divelog_images (divelog_id, filename, upload_date, type) VALUES (?, ?, NOW(), ?)");
            $stmt->bind_param("iss", $divelogId, $newFilename, $imageType);
            
            if ($stmt->execute()) {
                $fileStatus['status'] = 'success';
                $fileStatus['message'] = 'File uploaded successfully';
                $fileStatus['filename'] = $newFilename;
                $response['uploaded']++;
            } else {
                $fileStatus['status'] = 'error';
                $fileStatus['message'] = 'Database error: ' . $stmt->error;
                unlink($targetFile); // Delete the uploaded file
                $response['failed']++;
            }
        } else {
            $fileStatus['status'] = 'error';
            $fileStatus['message'] = 'Failed to move uploaded file';
            $response['failed']++;
        }
        
        $response['file_statuses'][] = $fileStatus;
    }
}

/**
 * Returns a human-readable error message for upload errors
 * 
 * @param int $errorCode PHP upload error code
 * @return string Human-readable error message
 */
function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'File too large';
        case UPLOAD_ERR_PARTIAL:
            return 'File was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload stopped by extension';
        default:
            return 'Unknown upload error';
    }
}

/**
 * Get images for a specific dive log entry
 * 
 * @param int $divelogId The dive log ID to retrieve images for
 * @param string $imageType Optional image type filter ('dive_photo', 'logbook_page', or 'all')
 * @return array Array of image data
 */
function getDiveImages($divelogId, $imageType = 'all') {
    global $conn;
    
    $images = [];
    
    $sql = "SELECT id, filename, caption, type FROM divelog_images WHERE divelog_id = ?";
    
    // Add type filter if specified
    if ($imageType !== 'all') {
        $sql .= " AND type = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $divelogId, $imageType);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $divelogId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $images[] = [
                'id' => $row['id'],
                'filename' => $row['filename'],
                'caption' => $row['caption'],
                'type' => $row['type'],
                'url' => 'uploads/diveimages/' . $row['filename']
            ];
        }
    }
    
    $stmt->close();
    return $images;
}

/**
 * Fetch logbook page images for a dive log entry
 * 
 * @param int $divelogId The dive log ID
 * @return array Array of logbook images
 */
function getLogbookImages($divelogId) {
    global $conn;
    $images = [];
    
    $stmt = $conn->prepare("SELECT * FROM divelog_images WHERE divelog_id = ? AND type = 'logbook_page' ORDER BY upload_date DESC");
    $stmt->bind_param("i", $divelogId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $images[] = $row;
    }
    
    $stmt->close();
    return $images;
}

/**
 * Get fish sightings for a dive
 * 
 * @param int $divelogId The dive log ID
 * @return array Array of fish sightings
 */
function getDiveFishSightings($divelogId) {
    global $conn;
    $sightings = [];
    
    $stmt = $conn->prepare("
        SELECT fs.*, fs.id as sighting_id, f.common_name, f.scientific_name, f.id as fish_id, 
               (SELECT filename FROM fish_images WHERE fish_species_id = f.id AND is_primary = 1 LIMIT 1) as fish_image
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

/**
 * Get all fish species for selecting
 * 
 * @return array Array of fish species
 */
function getAllFishSpecies() {
    global $conn;
    $species = [];
    
    $query = "SELECT * FROM fish_species ORDER BY common_name";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $species[] = $row;
        }
    }
    
    return $species;
}

/**
 * Initializes and checks database tables
 * 
 * @return array Status information
 */
function initDatabaseTables() {
    global $conn;
    
    $messages = [];
    $tableExists = false;
    
    // Check if the table exists
    $result = $conn->query("SHOW TABLES LIKE 'divelogs'");
    if ($result->num_rows > 0) {
        $tableExists = true;
        
        // Check if we need to upgrade the table structure (add new fields)
        $result = $conn->query("SHOW COLUMNS FROM divelogs LIKE 'depth'");
        if ($result->num_rows == 0) {
            // New fields need to be added
            $alterTable = "ALTER TABLE divelogs 
                ADD COLUMN depth FLOAT COMMENT 'Maximum depth in meters',
                ADD COLUMN duration INT COMMENT 'Dive time in minutes',
                ADD COLUMN temperature FLOAT COMMENT 'Water temperature in °C',
                ADD COLUMN air_temperature FLOAT COMMENT 'Air temperature in °C',
                ADD COLUMN visibility INT COMMENT 'Visibility in meters',
                ADD COLUMN buddy VARCHAR(255) COMMENT 'Dive partner name',
                ADD COLUMN dive_site_type VARCHAR(100) COMMENT 'Type of dive site',
                ADD COLUMN rating INT COMMENT 'Dive rating 1-5',
                ADD COLUMN comments TEXT COMMENT 'Additional comments'";
                
            if ($conn->query($alterTable) === TRUE) {
                $messages[] = "<div class='success'>Table structure updated with new fields.</div>";
            } else {
                $messages[] = "<div class='error'>Error updating table structure: " . $conn->error . "</div>";
            }
        } else {
            // Check for air_temperature and comments fields
            $result = $conn->query("SHOW COLUMNS FROM divelogs LIKE 'air_temperature'");
            if ($result->num_rows == 0) {
                $alterTable = "ALTER TABLE divelogs 
                    ADD COLUMN air_temperature FLOAT COMMENT 'Air temperature in °C',
                    ADD COLUMN comments TEXT COMMENT 'Additional comments'";
                    
                if ($conn->query($alterTable) === TRUE) {
                    $messages[] = "<div class='success'>Table structure updated with additional fields.</div>";
                } else {
                    $messages[] = "<div class='error'>Error updating table structure: " . $conn->error . "</div>";
                }
            }
        }
        
        // Check if divelog_images table exists
        $result = $conn->query("SHOW TABLES LIKE 'divelog_images'");
        if ($result->num_rows == 0) {
            // Create the images table
            $createImagesTable = "CREATE TABLE divelog_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                divelog_id INT NOT NULL COMMENT 'Foreign key to divelogs table',
                filename VARCHAR(255) NOT NULL COMMENT 'Image filename',
                original_filename VARCHAR(255) COMMENT 'Original uploaded filename',
                file_size INT COMMENT 'File size in bytes',
                file_type VARCHAR(100) COMMENT 'MIME type',
                type ENUM('dive_photo', 'logbook_page') DEFAULT 'dive_photo' COMMENT 'Type of image',
                upload_date DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Upload timestamp',
                caption TEXT COMMENT 'Image caption',
                FOREIGN KEY (divelog_id) REFERENCES divelogs(id) ON DELETE CASCADE
            )";
            
            if ($conn->query($createImagesTable) === TRUE) {
                $messages[] = "<div class='success'>Images table created successfully.</div>";
                
                // Create uploads directory if it doesn't exist
                $uploadsDir = 'uploads/diveimages';
                if (!file_exists($uploadsDir)) {
                    if (mkdir($uploadsDir, 0755, true)) {
                        $messages[] = "<div class='success'>Uploads directory created successfully.</div>";
                    } else {
                        $messages[] = "<div class='error'>Failed to create uploads directory. Please create it manually: $uploadsDir</div>";
                    }
                }
            } else {
                $messages[] = "<div class='error'>Error creating images table: " . $conn->error . "</div>";
            }
        } else {
            // Check if the type column exists
            $result = $conn->query("SHOW COLUMNS FROM divelog_images LIKE 'type'");
            if ($result->num_rows == 0) {
                // Add the type column
                $alterTable = "ALTER TABLE divelog_images 
                    ADD COLUMN type ENUM('dive_photo', 'logbook_page') DEFAULT 'dive_photo' COMMENT 'Type of image'";
                    
                if ($conn->query($alterTable) === TRUE) {
                    $messages[] = "<div class='success'>Images table updated with type field.</div>";
                } else {
                    $messages[] = "<div class='error'>Error updating images table: " . $conn->error . "</div>";
                }
            }
        }

        // Check if fish_species table exists
        $result = $conn->query("SHOW TABLES LIKE 'fish_species'");
        if ($result->num_rows == 0) {
            // Create the fish species table
            $createFishSpeciesTable = "CREATE TABLE fish_species (
                id INT AUTO_INCREMENT PRIMARY KEY,
                common_name VARCHAR(255) NOT NULL COMMENT 'Common name of the fish',
                scientific_name VARCHAR(255) COMMENT 'Scientific name (genus species)',
                description TEXT COMMENT 'Description of the fish',
                habitat TEXT COMMENT 'Typical habitat',
                size_range VARCHAR(100) COMMENT 'Typical size range',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            
            if ($conn->query($createFishSpeciesTable) === TRUE) {
                $messages[] = "<div class='success'>Fish species table created successfully.</div>";
            } else {
                $messages[] = "<div class='error'>Error creating fish species table: " . $conn->error . "</div>";
            }
        }

        // Check if fish_sightings table exists
        $result = $conn->query("SHOW TABLES LIKE 'fish_sightings'");
        if ($result->num_rows == 0) {
            // Create the fish sightings table (junction table between dives and fish)
            $createFishSightingsTable = "CREATE TABLE fish_sightings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                divelog_id INT NOT NULL COMMENT 'Foreign key to divelogs table',
                fish_species_id INT NOT NULL COMMENT 'Foreign key to fish_species table',
                sighting_date DATE COMMENT 'Date fish was spotted',
                quantity VARCHAR(50) COMMENT 'Estimated quantity (single, few, many, school)',
                notes TEXT COMMENT 'Notes about the sighting',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (divelog_id) REFERENCES divelogs(id) ON DELETE CASCADE,
                FOREIGN KEY (fish_species_id) REFERENCES fish_species(id) ON DELETE CASCADE
            )";
            
            if ($conn->query($createFishSightingsTable) === TRUE) {
                $messages[] = "<div class='success'>Fish sightings table created successfully.</div>";
            } else {
                $messages[] = "<div class='error'>Error creating fish sightings table: " . $conn->error . "</div>";
            }
        }

        // Check if fish_images table exists
        $result = $conn->query("SHOW TABLES LIKE 'fish_images'");
        if ($result->num_rows == 0) {
            // Create the fish images table
            $createFishImagesTable = "CREATE TABLE fish_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fish_species_id INT NOT NULL COMMENT 'Foreign key to fish_species table',
                filename VARCHAR(255) NOT NULL COMMENT 'Image filename',
                source_url VARCHAR(512) COMMENT 'URL where image was sourced from',
                source_name VARCHAR(255) COMMENT 'Source name (e.g., Wikipedia, Personal)',
                license_info TEXT COMMENT 'Image license information',
                is_primary BOOLEAN DEFAULT 0 COMMENT 'Is this the primary image for the species',
                upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (fish_species_id) REFERENCES fish_species(id) ON DELETE CASCADE
            )";
            
            if ($conn->query($createFishImagesTable) === TRUE) {
                $messages[] = "<div class='success'>Fish images table created successfully.</div>";
                
                // Create uploads directory for fish images if it doesn't exist
                $fishUploadsDir = 'uploads/fishimages';
                if (!file_exists($fishUploadsDir)) {
                    if (mkdir($fishUploadsDir, 0755, true)) {
                        $messages[] = "<div class='success'>Fish images uploads directory created successfully.</div>";
                    } else {
                        $messages[] = "<div class='error'>Failed to create fish images uploads directory. Please create it manually: $fishUploadsDir</div>";
                    }
                }
            } else {
                $messages[] = "<div class='error'>Error creating fish images table: " . $conn->error . "</div>";
            }
        }
    }

    // Create table if it doesn't exist
    if (!$tableExists) {
        $createTable = "CREATE TABLE divelogs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            location VARCHAR(255) NOT NULL COMMENT 'Name of dive location',
            latitude FLOAT NOT NULL COMMENT 'Latitude coordinates',
            longitude FLOAT NOT NULL COMMENT 'Longitude coordinates',
            date DATE COMMENT 'Date of the dive',
            description TEXT COMMENT 'Description of the dive',
            depth FLOAT COMMENT 'Maximum depth in meters',
            duration INT COMMENT 'Dive time in minutes',
            temperature FLOAT COMMENT 'Water temperature in °C',
            air_temperature FLOAT COMMENT 'Air temperature in °C',
            visibility INT COMMENT 'Visibility in meters',
            buddy VARCHAR(255) COMMENT 'Dive partner name',
            dive_site_type VARCHAR(100) COMMENT 'Type of dive site',
            rating INT COMMENT 'Dive rating 1-5',
            comments TEXT COMMENT 'Additional comments',
            dive_site VARCHAR(255) COMMENT 'Name of the specific dive site'
        )";
        
        if ($conn->query($createTable) === TRUE) {
            $messages[] = "<div class='success'>Table 'divelogs' created successfully.</div>";
            $tableExists = true;
        } else {
            $messages[] = "<div class='error'>Error creating table: " . $conn->error . "</div>";
        }
    }

    // Count existing records
    $recordCount = 0;
    if ($tableExists) {
        $result = $conn->query("SELECT COUNT(*) as count FROM divelogs");
        $row = $result->fetch_assoc();
        $recordCount = $row['count'];
    }
    
    // Return status
    return [
        'tableExists' => $tableExists,
        'recordCount' => $recordCount,
        'messages' => $messages
    ];
} 