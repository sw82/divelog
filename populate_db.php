<?php
// Include database connection
include 'db.php';

// Handle AJAX geocode request
if (isset($_GET['action']) && $_GET['action'] == 'geocode_ajax') {
    header('Content-Type: application/json');
    
    if (!isset($_GET['address']) || empty($_GET['address'])) {
        echo json_encode(['success' => false, 'error' => 'No address provided']);
        exit;
    }
    
    $address = $_GET['address'];
    $result = geocodeAddress($address);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'latitude' => $result['latitude'],
            'longitude' => $result['longitude'],
            'address' => $address
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Could not geocode address']);
    }
    exit;
}

// Function to geocode address to coordinates
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

// Handle geocode request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'geocode') {
    $address = $_POST['address'];
    $result = geocodeAddress($address);
    
    if ($result) {
        $geocodeResult = [
            'success' => true,
            'latitude' => $result['latitude'],
            'longitude' => $result['longitude'],
            'address' => $address
        ];
    } else {
        $geocodeResult = [
            'success' => false,
            'address' => $address
        ];
    }
}

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'populate') {
    // Sample dive log data
    $diveLogs = [
        [
            'location' => 'Great Barrier Reef, Australia',
            'latitude' => -16.7494,
            'longitude' => 145.6700,
            'date' => '2023-06-15',
            'description' => 'Beautiful coral formations with abundant marine life.'
        ],
        [
            'location' => 'Blue Hole, Belize',
            'latitude' => 17.3158,
            'longitude' => -87.5347,
            'date' => '2023-07-20',
            'description' => 'Deep blue sinkhole with clear visibility and reef sharks.'
        ],
        [
            'location' => 'Silfra Fissure, Iceland',
            'latitude' => 64.2558,
            'longitude' => -21.1213,
            'date' => '2023-08-05',
            'description' => 'Crystal clear water between tectonic plates with visibility over 100m.'
        ],
        [
            'location' => 'Maldives, South Ari Atoll',
            'latitude' => 3.4742,
            'longitude' => 72.8554,
            'date' => '2023-09-10',
            'description' => 'Encountered whale sharks and manta rays in warm tropical waters.'
        ],
        [
            'location' => 'Red Sea, Egypt',
            'latitude' => 27.8599,
            'longitude' => 34.3135,
            'date' => '2023-10-25',
            'description' => 'Colorful coral gardens and historical shipwrecks.'
        ]
    ];

    // Prepare and execute the SQL statements
    $successCount = 0;
    foreach ($diveLogs as $log) {
        $stmt = $conn->prepare("INSERT INTO divelogs (location, latitude, longitude, date, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sddss", $log['location'], $log['latitude'], $log['longitude'], $log['date'], $log['description']);
        
        if ($stmt->execute()) {
            $successCount++;
        }
        $stmt->close();
    }

    echo "<div class='success'>Successfully added $successCount dive logs to the database.</div>";
}

// Handle manual entry form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'manual_add') {
    $location = $_POST['location'];
    $date = $_POST['date'];
    $description = $_POST['description'];
    
    // Get new fields from form
    $depth = !empty($_POST['depth']) ? $_POST['depth'] : null;
    $duration = !empty($_POST['duration']) ? $_POST['duration'] : null;
    $temperature = !empty($_POST['temperature']) ? $_POST['temperature'] : null;
    $airTemperature = !empty($_POST['air_temperature']) ? $_POST['air_temperature'] : null;
    $visibility = !empty($_POST['visibility']) ? $_POST['visibility'] : null;
    $buddy = !empty($_POST['buddy']) ? $_POST['buddy'] : null;
    $diveSiteType = !empty($_POST['dive_site_type']) ? $_POST['dive_site_type'] : null;
    $rating = !empty($_POST['rating']) ? $_POST['rating'] : null;
    $comments = !empty($_POST['comments']) ? $_POST['comments'] : null;
    $diveTime = !empty($_POST['dive_time']) ? $_POST['dive_time'] : null;
    
    // Check if coordinates are provided
    if (!empty($_POST['latitude']) && !empty($_POST['longitude'])) {
        // Use provided coordinates
        $latitude = $_POST['latitude'];
        $longitude = $_POST['longitude'];
    } else {
        // Try to geocode the location
        $result = geocodeAddress($location);
        if ($result) {
            $latitude = $result['latitude'];
            $longitude = $result['longitude'];
            echo "<div class='info'>Location automatically geocoded: {$latitude}, {$longitude}</div>";
        } else {
            echo "<div class='error'>Could not geocode location. Please enter coordinates manually.</div>";
            // Set default values or redirect back
            $latitude = 0;
            $longitude = 0;
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO divelogs (location, latitude, longitude, date, dive_time, description, depth, duration, temperature, air_temperature, visibility, buddy, dive_site_type, rating, comments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sddsssddddissss", $location, $latitude, $longitude, $date, $diveTime, $description, $depth, $duration, $temperature, $airTemperature, $visibility, $buddy, $diveSiteType, $rating, $comments);
    
    if ($stmt->execute()) {
        $newDiveLogId = $stmt->insert_id;
        echo "<div class='success'>Successfully added new dive log entry.</div>";
        
        // Handle image uploads if present
        if (!empty($_FILES['dive_images']['name'][0])) {
            $uploadResult = uploadDiveImages($newDiveLogId);
            if ($uploadResult['success']) {
                $fileCount = count($uploadResult['uploaded']);
                echo "<div class='success'>Successfully uploaded $fileCount image" . ($fileCount > 1 ? 's' : '') . ".</div>";
            }
            if (!empty($uploadResult['errors'])) {
                foreach ($uploadResult['errors'] as $error) {
                    echo "<div class='error'>$error</div>";
                }
            }
        }
    } else {
        echo "<div class='error'>Error adding entry: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// Handle delete entry
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_entry') {
    $id = $_POST['id'];
    
    $stmt = $conn->prepare("DELETE FROM divelogs WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo "<div class='success'>Entry deleted successfully.</div>";
    } else {
        echo "<div class='error'>Error deleting entry: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// Handle delete image
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_image') {
    $imageId = $_POST['image_id'];
    $divelogId = $_POST['divelog_id'];
    
    // Get the filename first
    $stmt = $conn->prepare("SELECT filename FROM divelog_images WHERE id = ? AND divelog_id = ?");
    $stmt->bind_param("ii", $imageId, $divelogId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $filename = $row['filename'];
        $imagePath = 'uploads/diveimages/' . $filename;
        
        // Delete from database
        $deleteStmt = $conn->prepare("DELETE FROM divelog_images WHERE id = ?");
        $deleteStmt->bind_param("i", $imageId);
        
        if ($deleteStmt->execute()) {
            // Try to delete the file
            if (file_exists($imagePath) && unlink($imagePath)) {
                echo "<div class='success'>Image deleted successfully.</div>";
            } else {
                echo "<div class='warning'>Image record deleted, but could not delete file from server.</div>";
            }
        } else {
            echo "<div class='error'>Error deleting image: " . $deleteStmt->error . "</div>";
        }
        $deleteStmt->close();
    } else {
        echo "<div class='error'>Image not found or does not belong to this dive log.</div>";
    }
    $stmt->close();
}

// Handle edit entry form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_entry') {
    $id = $_POST['id'];
    $location = $_POST['location'];
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];
    $date = $_POST['date'];
    $description = $_POST['description'];
    
    // Get new fields from form
    $depth = !empty($_POST['depth']) ? $_POST['depth'] : null;
    $duration = !empty($_POST['duration']) ? $_POST['duration'] : null;
    $temperature = !empty($_POST['temperature']) ? $_POST['temperature'] : null;
    $airTemperature = !empty($_POST['air_temperature']) ? $_POST['air_temperature'] : null;
    $visibility = !empty($_POST['visibility']) ? $_POST['visibility'] : null;
    $buddy = !empty($_POST['buddy']) ? $_POST['buddy'] : null;
    $diveSiteType = !empty($_POST['dive_site_type']) ? $_POST['dive_site_type'] : null;
    $rating = !empty($_POST['rating']) ? $_POST['rating'] : null;
    $comments = !empty($_POST['comments']) ? $_POST['comments'] : null;
    
    $stmt = $conn->prepare("UPDATE divelogs SET 
        location = ?, 
        latitude = ?, 
        longitude = ?, 
        date = ?, 
        description = ?,
        depth = ?,
        duration = ?,
        temperature = ?,
        air_temperature = ?,
        visibility = ?,
        buddy = ?,
        dive_site_type = ?,
        rating = ?,
        comments = ? 
        WHERE id = ?");
    $stmt->bind_param("sddssddddiisssi", 
        $location, 
        $latitude, 
        $longitude, 
        $date, 
        $description, 
        $depth, 
        $duration, 
        $temperature, 
        $airTemperature, 
        $visibility, 
        $buddy, 
        $diveSiteType, 
        $rating, 
        $comments, 
        $id
    );
    
    if ($stmt->execute()) {
        echo "<div class='success'>Entry updated successfully.</div>";
        
        // Handle image uploads if present
        if (!empty($_FILES['dive_images']['name'][0])) {
            $uploadResult = uploadDiveImages($id);
            if ($uploadResult['success']) {
                $fileCount = count($uploadResult['uploaded']);
                echo "<div class='success'>Successfully uploaded $fileCount image" . ($fileCount > 1 ? 's' : '') . ".</div>";
            }
            if (!empty($uploadResult['errors'])) {
                foreach ($uploadResult['errors'] as $error) {
                    echo "<div class='error'>$error</div>";
                }
            }
        }
        
        // Update image captions if provided
        if (isset($_POST['image_captions']) && is_array($_POST['image_captions'])) {
            foreach ($_POST['image_captions'] as $imageId => $caption) {
                $updateCaption = $conn->prepare("UPDATE divelog_images SET caption = ? WHERE id = ? AND divelog_id = ?");
                $updateCaption->bind_param("sii", $caption, $imageId, $id);
                $updateCaption->execute();
                $updateCaption->close();
            }
        }
    } else {
        echo "<div class='error'>Error updating entry: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// Handle adding a new fish sighting to a dive
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_fish_sighting') {
    $divelogId = $_POST['divelog_id'];
    $fishSpeciesId = $_POST['fish_species_id'];
    $sightingDate = $_POST['sighting_date'];
    $quantity = $_POST['quantity'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO fish_sightings (divelog_id, fish_species_id, sighting_date, quantity, notes) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $divelogId, $fishSpeciesId, $sightingDate, $quantity, $notes);
    
    if ($stmt->execute()) {
        echo "<div class='success'>Fish sighting added successfully.</div>";
    } else {
        echo "<div class='error'>Error adding fish sighting: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// Handle deleting a fish sighting
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_fish_sighting') {
    $sightingId = $_POST['sighting_id'];
    $divelogId = $_POST['divelog_id'];
    
    $stmt = $conn->prepare("DELETE FROM fish_sightings WHERE id = ? AND divelog_id = ?");
    $stmt->bind_param("ii", $sightingId, $divelogId);
    
    if ($stmt->execute()) {
        echo "<div class='success'>Fish sighting removed successfully.</div>";
    } else {
        echo "<div class='error'>Error removing fish sighting: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// Fetch entry for editing
$editEntry = null;
$diveImages = [];
$diveFishSightings = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM divelogs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $editEntry = $result->fetch_assoc();
        // Fetch images for this dive log
        $diveImages = getDiveImages($id);
        // Fetch fish sightings for this dive log
        $diveFishSightings = getDiveFishSightings($id);
    }
    $stmt->close();
}

// Get all fish species for the dropdown
$allFishSpecies = getAllFishSpecies();

// Check if the table exists
$tableExists = false;
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
            echo "<div class='success'>Table structure updated with new fields.</div>";
        } else {
            echo "<div class='error'>Error updating table structure: " . $conn->error . "</div>";
        }
    } else {
        // Check for air_temperature and comments fields
        $result = $conn->query("SHOW COLUMNS FROM divelogs LIKE 'air_temperature'");
        if ($result->num_rows == 0) {
            $alterTable = "ALTER TABLE divelogs 
                ADD COLUMN air_temperature FLOAT COMMENT 'Air temperature in °C',
                ADD COLUMN comments TEXT COMMENT 'Additional comments'";
                
            if ($conn->query($alterTable) === TRUE) {
                echo "<div class='success'>Table structure updated with additional fields.</div>";
            } else {
                echo "<div class='error'>Error updating table structure: " . $conn->error . "</div>";
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
            echo "<div class='success'>Images table created successfully.</div>";
            
            // Create uploads directory if it doesn't exist
            $uploadsDir = 'uploads/diveimages';
            if (!file_exists($uploadsDir)) {
                if (mkdir($uploadsDir, 0755, true)) {
                    echo "<div class='success'>Uploads directory created successfully.</div>";
                } else {
                    echo "<div class='error'>Failed to create uploads directory. Please create it manually: $uploadsDir</div>";
                }
            }
        } else {
            echo "<div class='error'>Error creating images table: " . $conn->error . "</div>";
        }
    } else {
        // Check if the type column exists
        $result = $conn->query("SHOW COLUMNS FROM divelog_images LIKE 'type'");
        if ($result->num_rows == 0) {
            // Add the type column
            $alterTable = "ALTER TABLE divelog_images 
                ADD COLUMN type ENUM('dive_photo', 'logbook_page') DEFAULT 'dive_photo' COMMENT 'Type of image'";
                
            if ($conn->query($alterTable) === TRUE) {
                echo "<div class='success'>Images table updated with type field.</div>";
            } else {
                echo "<div class='error'>Error updating images table: " . $conn->error . "</div>";
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
            echo "<div class='success'>Fish species table created successfully.</div>";
        } else {
            echo "<div class='error'>Error creating fish species table: " . $conn->error . "</div>";
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
            echo "<div class='success'>Fish sightings table created successfully.</div>";
        } else {
            echo "<div class='error'>Error creating fish sightings table: " . $conn->error . "</div>";
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
            echo "<div class='success'>Fish images table created successfully.</div>";
            
            // Create uploads directory for fish images if it doesn't exist
            $fishUploadsDir = 'uploads/fishimages';
            if (!file_exists($fishUploadsDir)) {
                if (mkdir($fishUploadsDir, 0755, true)) {
                    echo "<div class='success'>Fish images uploads directory created successfully.</div>";
                } else {
                    echo "<div class='error'>Failed to create fish images uploads directory. Please create it manually: $fishUploadsDir</div>";
                }
            }
        } else {
            echo "<div class='error'>Error creating fish images table: " . $conn->error . "</div>";
        }
    }
}

// Clear table if requested
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'clear') {
    $conn->query("TRUNCATE TABLE divelogs");
    echo "<div class='success'>All dive logs have been cleared.</div>";
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
        comments TEXT COMMENT 'Additional comments'
    )";
    
    if ($conn->query($createTable) === TRUE) {
        echo "<div class='success'>Table 'divelogs' created successfully.</div>";
    } else {
        echo "<div class='error'>Error creating table: " . $conn->error . "</div>";
    }
}

// Count existing records
$result = $conn->query("SELECT COUNT(*) as count FROM divelogs");
$row = $result->fetch_assoc();
$recordCount = $row['count'];

// Fetch all dive logs for the table view
$allEntries = [];
if ($tableExists) {
    $result = $conn->query("SELECT * FROM divelogs ORDER BY date DESC");
    while ($row = $result->fetch_assoc()) {
        $allEntries[] = $row;
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
            $stmt = $conn->prepare("INSERT INTO divelog_images (divelog_id, filename, uploaded_at, type) VALUES (?, ?, NOW(), ?)");
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

// Fetch logbook page images for a dive log entry
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

// Function to get fish sightings for a dive
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

// Function to get all fish species for selecting
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Populate Dive Log Database</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
        h1 { color: #333; }
        h2 { color: #555; margin-top: 30px; }
        .container { background: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .success { color: green; padding: 10px; background: #e8f5e9; margin: 10px 0; border-radius: 3px; }
        .error { color: red; padding: 10px; background: #ffebee; margin: 10px 0; border-radius: 3px; }
        .info { color: #333; padding: 10px; background: #e3f2fd; margin: 10px 0; border-radius: 3px; }
        button, .btn { padding: 8px 12px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 5px; text-decoration: none; font-size: 14px; display: inline-block; }
        button:hover, .btn:hover { background: #45a049; }
        .danger { background: #f44336; }
        .danger:hover { background: #d32f2f; }
        .edit { background: #2196F3; }
        .edit:hover { background: #0b7dda; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        textarea { height: 100px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; font-weight: bold; }
        tr:hover { background-color: #f5f5f5; }
        .description-cell { max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .action-cell { white-space: nowrap; }
        
        /* Inline form styles */
        .add-new-row { background-color: #e8f5e9; }
        .inline-form { margin: 0; }
        .inline-form-group { display: flex; }
        .inline-form input, 
        .add-new-row input { 
            width: 100%; 
            margin: 0; 
            padding: 6px; 
            font-size: 14px;
            box-sizing: border-box;
        }
        .coordinates-group { 
            display: flex; 
            gap: 5px; 
        }
        .coordinates-group input { 
            flex: 1; 
        }
        .add-btn { 
            width: 100%;
            padding: 6px 12px; 
            margin: 0;
        }
        
        .auto-geocode-note {
            font-size: 11px;
            color: #666;
            text-align: center;
            margin-top: 3px;
            font-style: italic;
        }
        
        /* Geocoder styles */
        .geocoder-container {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #e3f2fd;
            border-radius: 5px;
        }
        .geocoder-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .geocoder-input {
            flex: 1;
        }
        .geocoder-result {
            margin-top: 15px;
            padding: 10px;
            background-color: #fff;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .toggle-btn {
            font-size: 12px;
            padding: 4px 8px;
            margin-left: 10px;
            background-color: #777;
            vertical-align: middle;
        }
        
        /* Dive details styling */
        .details-cell { font-size: 13px; }
        .dive-details { display: flex; flex-direction: column; gap: 3px; }
        .dive-details strong { color: #555; }
        .dive-details div { margin-bottom: 2px; }
        
        /* Notes styling */
        .notes-cell { font-size: 13px; }
        .description-note { margin-bottom: 8px; }
        .comments-note { 
            font-style: italic; 
            color: #666;
            border-top: 1px dotted #ddd;
            padding-top: 5px;
            margin-top: 5px;
        }
        
        /* Form row styling for edit page */
        .form-row-container { margin-bottom: 20px; }
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        .form-group.half {
            flex: 1;
        }
        
        /* Styles for the table textarea */
        td textarea {
            width: 100%;
            padding: 6px;
            font-size: 14px;
            height: 60px;
            resize: vertical;
        }
        
        /* More fields container */
        .more-fields-container {
            font-size: 13px;
        }
        .more-fields-container .form-row {
            margin-bottom: 5px;
        }
        
        /* Image Upload Styling */
        .image-upload-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .file-input {
            display: none;
        }
        .file-upload-button {
            padding: 8px 12px;
            background: #2196F3;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            font-size: 14px;
        }
        .file-upload-button:hover {
            background: #0b7dda;
        }
        .upload-icon {
            margin-right: 8px;
        }
        .upload-help {
            color: #666;
            font-size: 13px;
            margin-left: 10px;
        }
        
        /* Existing Images Styling */
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        .image-item {
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        .image-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            display: block;
        }
        .image-controls {
            padding: 8px;
            background: #f5f5f5;
        }
        .caption-field {
            margin-top: 8px;
        }
        .caption-field input {
            width: 100%;
            padding: 5px;
            font-size: 12px;
            border: 1px solid #ddd;
        }
        
        /* Fish sightings styling */
        .fish-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .fish-table th, .fish-table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        .fish-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .fish-thumbnail {
            width: 30px;
            height: 30px;
            object-fit: cover;
            border-radius: 4px;
        }
        .fish-thumbnail-placeholder {
            width: 30px;
            height: 30px;
            background-color: #eee;
            border-radius: 4px;
        }
        .scientific-name {
            font-style: italic;
            font-size: 12px;
            color: #666;
        }
        .add-fish-form {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .fish-form {
            margin-top: 15px;
        }
        .manage-fish-link {
            margin-top: 10px;
            font-size: 13px;
            font-style: italic;
        }
        .form-buttons {
            margin-top: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .delete-form {
            margin-left: auto;
        }
        
        /* Tab styling */
        .tab-container {
            margin-bottom: 20px;
        }
        .tab-buttons {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 15px;
        }
        .tab-button {
            padding: 8px 16px;
            background: #f1f1f1;
            border: 1px solid #ddd;
            border-bottom: none;
            border-radius: 4px 4px 0 0;
            margin-right: 5px;
            cursor: pointer;
        }
        .tab-button.active {
            background: #fff;
            border-bottom: 1px solid #fff;
            margin-bottom: -1px;
            font-weight: bold;
        }
        .tab-content {
            display: none;
            padding: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
        }
        .tab-content.active {
            display: block;
        }
    </style>
    <script>
        // Function to fill coordinates and location from geocoding result
        function fillCoordinates(latitude, longitude, address) {
            const addRowLatInput = document.querySelector('.add-new-row input[name="latitude"]');
            const addRowLongInput = document.querySelector('.add-new-row input[name="longitude"]');
            const addRowLocationInput = document.querySelector('.add-new-row input[name="location"]');
            
            if (addRowLatInput && addRowLongInput) {
                addRowLatInput.value = latitude;
                addRowLongInput.value = longitude;
                
                if (addRowLocationInput && address) {
                    addRowLocationInput.value = address;
                }
            }
        }
        
        // Function to toggle geocoder visibility
        function toggleGeocoder() {
            const container = document.getElementById('geocoderContainer');
            const toggleBtn = document.getElementById('geocoderToggle');
            
            if (container.style.display === 'none') {
                container.style.display = 'block';
                toggleBtn.textContent = 'Hide';
            } else {
                container.style.display = 'none';
                toggleBtn.textContent = 'Show';
            }
        }
        
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const tabName = this.getAttribute('data-tab');
                    
                    // Remove active class from all buttons and content
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to current button and content
                    this.classList.add('active');
                    document.getElementById(tabName + '-tab').classList.add('active');
                });
            });
            
            // File input display count
            document.getElementById('dive-photos-input').addEventListener('change', function() {
                const fileCount = this.files.length;
                document.getElementById('dive-photos-count').textContent = 
                    fileCount > 0 ? `${fileCount} file${fileCount > 1 ? 's' : ''} selected` : 'No files selected';
            });
            
            document.getElementById('logbook-pages-input').addEventListener('change', function() {
                const fileCount = this.files.length;
                document.getElementById('logbook-pages-count').textContent = 
                    fileCount > 0 ? `${fileCount} file${fileCount > 1 ? 's' : ''} selected` : 'No files selected';
            });
        });
    </script>
</head>
<body>
    <nav class="menu">
        <a href="index.php">View Dive Log</a>
        <a href="populate_db.php" class="active">Manage Database</a>
        <a href="fish_manager.php">Fish Species</a>
        <a href="backup_db.php">Backup Database</a>
    </nav>
    <h1>Dive Log Database Manager</h1>
    
    <div class="container">
        <div class="info">
            Current status: 
            <?php if($tableExists): ?>
                Table 'divelogs' exists with <?php echo $recordCount; ?> records.
            <?php else: ?>
                Table 'divelogs' does not exist yet.
            <?php endif; ?>
        </div>
        
        <form method="post">
            <input type="hidden" name="action" value="populate">
            <button type="submit">Populate with Sample Data</button>
        </form>
        
        <br>
        
        <form method="post" onsubmit="return confirm('Are you sure you want to clear all dive logs?');">
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="danger">Clear All Data</button>
        </form>
    </div>

    <div class="container">
        <h2>
            Geocode Address to Coordinates
            <button type="button" onclick="toggleGeocoder()" class="toggle-btn" id="geocoderToggle">Hide</button>
        </h2>
        <div class="geocoder-container" id="geocoderContainer">
            <form method="post" class="geocoder-form">
                <input type="hidden" name="action" value="geocode">
                <input type="text" name="address" placeholder="Enter location address" class="geocoder-input" required>
                <button type="submit">Geocode</button>
            </form>
            
            <?php if (isset($geocodeResult)): ?>
                <div class="geocoder-result">
                    <?php if ($geocodeResult['success']): ?>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($geocodeResult['address']); ?></p>
                        <p><strong>Coordinates:</strong> <?php echo $geocodeResult['latitude']; ?>, <?php echo $geocodeResult['longitude']; ?></p>
                        <button onclick="fillCoordinates(<?php echo $geocodeResult['latitude']; ?>, <?php echo $geocodeResult['longitude']; ?>, <?php echo json_encode($geocodeResult['address']); ?>)" class="btn">
                            Use These Coordinates
                        </button>
                    <?php else: ?>
                        <p class="error">Could not geocode address: <?php echo htmlspecialchars($geocodeResult['address']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <h2>All Dive Log Entries</h2>
        <?php if (empty($allEntries) && !$editEntry): ?>
            <p>No dive log entries found. Add an entry using the form below.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 30px;">ID</th>
                        <th style="width: 15%;">Location</th>
                        <th style="width: 10%;">Coordinates</th>
                        <th style="width: 10%;">Date</th>
                        <th style="width: 20%;">Dive Details</th>
                        <th style="width: 20%;">Notes</th>
                        <th style="width: 15%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$editEntry): ?>
                    <tr class="add-new-row">
                        <td>New</td>
                        <td>
                            <form method="post" class="inline-form" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="manual_add">
                                <div class="inline-form-group">
                                    <input type="text" name="location" placeholder="Location" required>
                                </div>
                        </td>
                        <td>
                            <div class="coordinates-group">
                                <input type="number" name="latitude" placeholder="Auto" step="any">
                                <input type="number" name="longitude" placeholder="Auto" step="any">
                            </div>
                            <div class="auto-geocode-note">Will auto-geocode</div>
                        </td>
                        <td>
                            <input type="date" name="date" required>
                            <input type="time" name="dive_time" step="300">
                        </td>
                        <td>
                            <div class="more-fields-container">
                                <div class="form-row">
                                    <input type="number" name="depth" placeholder="Depth (m)" step="0.1" min="0">
                                    <input type="number" name="duration" placeholder="Time (min)" min="0">
                                </div>
                                <div class="form-row">
                                    <input type="number" name="temperature" placeholder="Water Temp (°C)" step="0.1">
                                    <input type="number" name="air_temperature" placeholder="Air Temp (°C)" step="0.1">
                                </div>
                                <div class="form-row">
                                    <input type="number" name="visibility" placeholder="Vis (m)" min="0">
                                    <input type="text" name="buddy" placeholder="Dive Partner">
                                </div>
                                <div class="form-row">
                                    <select name="dive_site_type">
                                        <option value="">Site Type</option>
                                        <option value="Reef">Reef</option>
                                        <option value="Wall">Wall</option>
                                        <option value="Wreck">Wreck</option>
                                        <option value="Cave">Cave</option>
                                        <option value="Shore">Shore</option>
                                        <option value="Lake">Lake</option>
                                        <option value="River">River</option>
                                        <option value="Other">Other</option>
                                    </select>
                                    <select name="rating">
                                        <option value="">Rating</option>
                                        <option value="1">★</option>
                                        <option value="2">★★</option>
                                        <option value="3">★★★</option>
                                        <option value="4">★★★★</option>
                                        <option value="5">★★★★★</option>
                                    </select>
                                </div>
                                <div class="form-row">
                                    <label class="file-upload-label">
                                        <span class="upload-icon">📷</span> Add Images
                                        <input type="file" name="dive_images[]" multiple accept="image/*" class="file-input">
                                    </label>
                                </div>
                            </div>
                        </td>
                        <td>
                            <input type="text" name="description" placeholder="Description" required>
                        </td>
                        <td>
                            <textarea name="comments" placeholder="Additional comments"></textarea>
                        </td>
                        <td>
                            <button type="submit" class="add-btn">Add Entry</button>
                            </form>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($allEntries as $entry): ?>
                        <tr>
                            <td><?php echo $entry['id']; ?></td>
                            <td><?php echo htmlspecialchars($entry['location']); ?></td>
                            <td><?php echo $entry['latitude']; ?>, <?php echo $entry['longitude']; ?></td>
                            <td><?php echo $entry['date']; ?></td>
                            <td class="details-cell">
                                <div class="dive-details">
                                    <?php if (!empty($entry['depth'])): ?>
                                        <div><strong>Depth:</strong> <?php echo $entry['depth']; ?> m</div>
                                    <?php endif; ?>
                                    <?php if (!empty($entry['duration'])): ?>
                                        <div><strong>Time:</strong> <?php echo $entry['duration']; ?> min</div>
                                    <?php endif; ?>
                                    <?php if (!empty($entry['temperature'])): ?>
                                        <div><strong>Water Temp:</strong> <?php echo $entry['temperature']; ?>°C</div>
                                    <?php endif; ?>
                                    <?php if (!empty($entry['air_temperature'])): ?>
                                        <div><strong>Air Temp:</strong> <?php echo $entry['air_temperature']; ?>°C</div>
                                    <?php endif; ?>
                                    <?php if (!empty($entry['buddy'])): ?>
                                        <div><strong>Partner:</strong> <?php echo htmlspecialchars($entry['buddy']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($entry['dive_site_type'])): ?>
                                        <div><strong>Site Type:</strong> <?php echo $entry['dive_site_type']; ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($entry['rating'])): ?>
                                        <div><strong>Rating:</strong> 
                                            <?php 
                                                $stars = '';
                                                for ($i = 0; $i < $entry['rating']; $i++) {
                                                    $stars .= '★';
                                                }
                                                echo $stars;
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="notes-cell">
                                <div class="description-note"><?php echo htmlspecialchars($entry['description']); ?></div>
                                <?php if (!empty($entry['comments'])): ?>
                                    <div class="comments-note"><?php echo htmlspecialchars($entry['comments']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="action-cell">
                                <a href="populate_db.php?edit=<?php echo $entry['id']; ?>" class="btn edit">Edit</a>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this entry?');">
                                    <input type="hidden" name="action" value="delete_entry">
                                    <input type="hidden" name="id" value="<?php echo $entry['id']; ?>">
                                    <button type="submit" class="danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if (isset($editEntry)): ?>
    <div class="container">
        <h2>Edit Dive Log Entry</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_entry">
            <input type="hidden" name="id" value="<?php echo $editEntry['id']; ?>">
            
            <div class="form-group">
                <label for="location">Location:</label>
                <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($editEntry['location']); ?>" required>
            </div>
            
            <div class="form-row-container">
                <div class="form-row">
                    <div class="form-group half">
                        <label for="latitude">Latitude:</label>
                        <input type="number" id="latitude" name="latitude" step="any" value="<?php echo $editEntry['latitude']; ?>" required>
                    </div>
                    
                    <div class="form-group half">
                        <label for="longitude">Longitude:</label>
                        <input type="number" id="longitude" name="longitude" step="any" value="<?php echo $editEntry['longitude']; ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="date">Date:</label>
                <input type="date" id="date" name="date" value="<?php echo $editEntry['date']; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($editEntry['description']); ?></textarea>
            </div>
            
            <div class="form-row-container">
                <div class="form-row">
                    <div class="form-group half">
                        <label for="depth">Maximum Depth (m):</label>
                        <input type="number" id="depth" name="depth" step="0.1" min="0" value="<?php echo $editEntry['depth']; ?>">
                    </div>
                    
                    <div class="form-group half">
                        <label for="duration">Duration (min):</label>
                        <input type="number" id="duration" name="duration" min="0" value="<?php echo $editEntry['duration']; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group half">
                        <label for="temperature">Water Temperature (°C):</label>
                        <input type="number" id="temperature" name="temperature" step="0.1" value="<?php echo $editEntry['temperature']; ?>">
                    </div>
                    
                    <div class="form-group half">
                        <label for="air_temperature">Air Temperature (°C):</label>
                        <input type="number" id="air_temperature" name="air_temperature" step="0.1" value="<?php echo $editEntry['air_temperature']; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group half">
                        <label for="visibility">Visibility (m):</label>
                        <input type="number" id="visibility" name="visibility" min="0" value="<?php echo $editEntry['visibility']; ?>">
                    </div>
                    
                    <div class="form-group half">
                        <label for="buddy">Dive Partner/Buddy:</label>
                        <input type="text" id="buddy" name="buddy" value="<?php echo htmlspecialchars($editEntry['buddy']); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group half">
                        <label for="dive_site_type">Dive Site Type:</label>
                        <select id="dive_site_type" name="dive_site_type">
                            <option value="">-- Select --</option>
                            <?php 
                            $siteTypes = ['Reef', 'Wall', 'Wreck', 'Cave', 'Drift', 'Shore', 'Deep', 'Muck', 'Night', 'Other'];
                            foreach ($siteTypes as $type) {
                                $selected = ($editEntry['dive_site_type'] == $type) ? 'selected' : '';
                                echo "<option value=\"$type\" $selected>$type</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group half">
                        <label for="rating">Rating (1-5):</label>
                        <select id="rating" name="rating">
                            <option value="">-- Select --</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php if ($editEntry['rating'] == $i) echo 'selected'; ?>><?php echo str_repeat('★', $i); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="comments">Comments/Notes:</label>
                <textarea id="comments" name="comments"><?php echo htmlspecialchars($editEntry['comments'] ?? ''); ?></textarea>
            </div>
            
            <!-- Image Upload Section -->
            <div class="section-title">Media</div>
            <div class="tab-container">
                <div class="tab-buttons">
                    <button type="button" class="tab-button active" data-tab="dive-photos">Dive Photos</button>
                    <button type="button" class="tab-button" data-tab="logbook">Logbook Pages</button>
                </div>
                
                <div class="tab-content active" id="dive-photos-tab">
                    <h4>Dive Photos</h4>
                    <div class="image-upload-container">
                        <label for="dive-photos-input" class="file-upload-button">
                            <span class="upload-icon">📷</span> Select Dive Photos
                        </label>
                        <input type="file" name="images[]" id="dive-photos-input" class="file-input" multiple accept="image/jpeg,image/png,image/gif">
                        <span class="selected-files-count" id="dive-photos-count">No files selected</span>
                        <span class="upload-help">JPG, PNG or GIF (max. 5MB each)</span>
                    </div>
                    
                    <?php if (!empty($diveImages) && $editMode): ?>
                    <div class="image-gallery">
                        <?php foreach ($diveImages as $image): ?>
                            <?php if($image['type'] == 'dive_photo'): ?>
                            <div class="image-item">
                                <img src="uploads/diveimages/<?php echo htmlspecialchars($image['filename']); ?>" alt="Dive photo">
                                <div class="image-controls">
                                    <div class="caption-field">
                                        <input type="text" name="image_caption[<?php echo $image['id']; ?>]" placeholder="Add caption" value="<?php echo htmlspecialchars($image['caption'] ?? ''); ?>">
                                    </div>
                                    <button type="button" class="delete-image-btn" data-image-id="<?php echo $image['id']; ?>">Delete</button>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="tab-content" id="logbook-tab">
                    <h4>Logbook Pages</h4>
                    <div class="image-upload-container">
                        <label for="logbook-pages-input" class="file-upload-button">
                            <span class="upload-icon">📄</span> Select Logbook Pages
                        </label>
                        <input type="file" name="logbook_images[]" id="logbook-pages-input" class="file-input" multiple accept="image/jpeg,image/png,image/gif">
                        <span class="selected-files-count" id="logbook-pages-count">No files selected</span>
                        <span class="upload-help">JPG, PNG or GIF (max. 5MB each)</span>
                    </div>
                    
                    <?php if (!empty($diveImages) && $editMode): ?>
                    <div class="image-gallery">
                        <?php foreach ($diveImages as $image): ?>
                            <?php if($image['type'] == 'logbook_page'): ?>
                            <div class="image-item">
                                <img src="uploads/diveimages/<?php echo htmlspecialchars($image['filename']); ?>" alt="Logbook page">
                                <div class="image-controls">
                                    <div class="caption-field">
                                        <input type="text" name="image_caption[<?php echo $image['id']; ?>]" placeholder="Add caption" value="<?php echo htmlspecialchars($image['caption'] ?? ''); ?>">
                                    </div>
                                    <button type="button" class="delete-image-btn" data-image-id="<?php echo $image['id']; ?>">Delete</button>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Fish Sightings Section -->
            <div class="form-group">
                <h3>Fish Sightings</h3>
                
                <?php if (!empty($diveFishSightings)): ?>
                    <div class="fish-sightings-list">
                        <table class="fish-table">
                            <thead>
                                <tr>
                                    <th style="width: 30px;"></th>
                                    <th>Fish Species</th>
                                    <th>Date</th>
                                    <th>Quantity</th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($diveFishSightings as $sighting): ?>
                                    <tr>
                                        <td>
                                            <?php if ($sighting['fish_image']): ?>
                                                <img src="uploads/fishimages/<?php echo $sighting['fish_image']; ?>" alt="<?php echo htmlspecialchars($sighting['common_name']); ?>" class="fish-thumbnail">
                                            <?php else: ?>
                                                <div class="fish-thumbnail-placeholder"></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($sighting['common_name']); ?></strong>
                                            <?php if ($sighting['scientific_name']): ?>
                                                <div class="scientific-name"><?php echo htmlspecialchars($sighting['scientific_name']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($sighting['sighting_date'])); ?></td>
                                        <td><?php echo ucfirst(htmlspecialchars($sighting['quantity'] ?? 'N/A')); ?></td>
                                        <td><?php echo htmlspecialchars($sighting['notes'] ?? ''); ?></td>
                                        <td>
                                            <form method="post" onsubmit="return confirm('Are you sure you want to remove this fish sighting?');">
                                                <input type="hidden" name="action" value="delete_fish_sighting">
                                                <input type="hidden" name="sighting_id" value="<?php echo $sighting['sighting_id']; ?>">
                                                <input type="hidden" name="divelog_id" value="<?php echo $editEntry['id']; ?>">
                                                <button type="submit" class="danger">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No fish sightings recorded for this dive.</p>
                <?php endif; ?>
                
                <div class="add-fish-form">
                    <h4>Add New Fish Sighting</h4>
                    <form method="post" class="fish-form">
                        <input type="hidden" name="action" value="add_fish_sighting">
                        <input type="hidden" name="divelog_id" value="<?php echo $editEntry['id']; ?>">
                        <input type="hidden" name="sighting_date" value="<?php echo $editEntry['date']; ?>">
                        
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="fish_species_id">Fish Species:</label>
                                <select id="fish_species_id" name="fish_species_id" required>
                                    <option value="">-- Select Fish Species --</option>
                                    <?php foreach ($allFishSpecies as $fish): ?>
                                        <option value="<?php echo $fish['id']; ?>">
                                            <?php echo htmlspecialchars($fish['common_name']); ?> 
                                            <?php if (!empty($fish['scientific_name'])): ?>
                                                (<?php echo htmlspecialchars($fish['scientific_name']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group half">
                                <label for="quantity">Approximate Quantity:</label>
                                <select id="quantity" name="quantity">
                                    <option value="single">Single</option>
                                    <option value="few">Few (2-5)</option>
                                    <option value="many">Many (5-20)</option>
                                    <option value="school">School (20+)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes:</label>
                            <textarea id="notes" name="notes" rows="2"></textarea>
                        </div>
                        
                        <button type="submit" class="btn">Add Fish Sighting</button>
                    </form>
                    
                    <div class="manage-fish-link">
                        <p>Can't find the fish you're looking for? <a href="fish_manager.php" target="_blank">Manage Fish Species</a></p>
                    </div>
                </div>
            </div>
            
            <div class="form-buttons">
                <button type="submit" class="btn">Update Dive Log Entry</button>
                <a href="populate_db.php" class="btn" style="background-color: #777;">Cancel</a>
                
                <form method="post" class="delete-form" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this dive log entry? This cannot be undone.');">
                    <input type="hidden" name="action" value="delete_entry">
                    <input type="hidden" name="id" value="<?php echo $editEntry['id']; ?>">
                    <button type="submit" class="danger">Delete Entry</button>
                </form>
            </div>
        </form>
    </div>
    <?php endif; ?>
</body>
</html>