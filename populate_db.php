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
    
    $stmt = $conn->prepare("INSERT INTO divelogs (location, latitude, longitude, date, description, depth, duration, temperature, air_temperature, visibility, buddy, dive_site_type, rating, comments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sddssddddissss", $location, $latitude, $longitude, $date, $description, $depth, $duration, $temperature, $airTemperature, $visibility, $buddy, $diveSiteType, $rating, $comments);
    
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

// Fetch entry for editing
$editEntry = null;
$diveImages = [];
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
    }
    $stmt->close();
}

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

// Handle image uploads
function uploadDiveImages($divelogId) {
    global $conn;
    $uploadedFiles = [];
    $errors = [];
    $uploadsDir = 'uploads/diveimages';
    
    // Make sure the uploads directory exists
    if (!file_exists($uploadsDir)) {
        if (!mkdir($uploadsDir, 0755, true)) {
            return ['success' => false, 'message' => 'Failed to create uploads directory.'];
        }
    }
    
    // Check if files were uploaded
    if (!empty($_FILES['dive_images']['name'][0])) {
        $fileCount = count($_FILES['dive_images']['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['dive_images']['error'][$i] === UPLOAD_ERR_OK) {
                $tempName = $_FILES['dive_images']['tmp_name'][$i];
                $originalName = $_FILES['dive_images']['name'][$i];
                $fileSize = $_FILES['dive_images']['size'][$i];
                $fileType = $_FILES['dive_images']['type'][$i];
                
                // Validate file type
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($fileType, $allowedTypes)) {
                    $errors[] = "File '$originalName' is not an allowed image type. Only JPG, PNG, GIF, and WEBP are supported.";
                    continue;
                }
                
                // Validate file size (max 5MB)
                $maxSize = 5 * 1024 * 1024; // 5MB
                if ($fileSize > $maxSize) {
                    $errors[] = "File '$originalName' exceeds the maximum allowed size of 5MB.";
                    continue;
                }
                
                // Generate a unique filename
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                $newFilename = $divelogId . '_' . uniqid() . '.' . $extension;
                $destination = $uploadsDir . '/' . $newFilename;
                
                // Move the uploaded file
                if (move_uploaded_file($tempName, $destination)) {
                    // Add to database
                    $stmt = $conn->prepare("INSERT INTO divelog_images (divelog_id, filename, original_filename, file_size, file_type) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("issss", $divelogId, $newFilename, $originalName, $fileSize, $fileType);
                    
                    if ($stmt->execute()) {
                        $uploadedFiles[] = [
                            'id' => $stmt->insert_id,
                            'filename' => $newFilename,
                            'original_name' => $originalName
                        ];
                    } else {
                        $errors[] = "Database error for '$originalName': " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Failed to save '$originalName'.";
                }
            } else {
                $uploadError = $_FILES['dive_images']['error'][$i];
                $errors[] = "Upload error for file #" . ($i + 1) . ": " . $uploadError;
            }
        }
    }
    
    return [
        'success' => !empty($uploadedFiles),
        'uploaded' => $uploadedFiles,
        'errors' => $errors
    ];
}

// Fetch images for a dive log entry
function getDiveImages($divelogId) {
    global $conn;
    $images = [];
    
    $stmt = $conn->prepare("SELECT * FROM divelog_images WHERE divelog_id = ? ORDER BY upload_date DESC");
    $stmt->bind_param("i", $divelogId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $images[] = $row;
    }
    
    $stmt->close();
    return $images;
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
        
        .file-upload-button, .file-upload-label {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background-color: #2196F3;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-size: 14px;
        }
        
        .file-upload-button:hover, .file-upload-label:hover {
            background-color: #0b7dda;
        }
        
        .file-input {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }
        
        .upload-icon {
            font-size: 18px;
        }
        
        .upload-help {
            color: #666;
            font-size: 13px;
            font-style: italic;
        }
        
        /* Image Gallery Styling */
        .image-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }
        
        .image-item {
            width: calc(33.333% - 10px);
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .thumbnail {
            width: 100%;
            height: 150px;
            object-fit: cover;
            display: block;
        }
        
        .image-details {
            padding: 10px;
            background-color: #f9f9f9;
        }
        
        .image-info {
            margin-bottom: 8px;
        }
        
        .image-name {
            display: block;
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .image-caption {
            width: 100%;
            padding: 5px;
            font-size: 13px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        
        .image-actions {
            display: flex;
            justify-content: flex-end;
        }
        
        .delete-image-form {
            margin: 0;
        }
        
        .danger.small {
            padding: 3px 8px;
            font-size: 12px;
        }
        
        @media (max-width: 768px) {
            .image-item {
                width: calc(50% - 8px);
            }
        }
        
        @media (max-width: 480px) {
            .image-item {
                width: 100%;
            }
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
    </script>
</head>
<body>
    <nav class="menu">
        <a href="index.php">View Dive Log</a>
        <a href="populate_db.php" class="active">Manage Database</a>
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

    <?php if ($editEntry): ?>
    <div class="container">
        <h2>Edit Dive Log Entry</h2>
        <form method="post" id="editForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_entry">
            <input type="hidden" name="id" value="<?php echo $editEntry['id']; ?>">
            
            <div class="form-group">
                <label for="location">Location:</label>
                <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($editEntry['location']); ?>" required>
                <button type="button" onclick="geocodeEditLocation()" class="btn edit" style="margin-top: 5px;">Geocode This Location</button>
            </div>
            
            <div class="form-group">
                <label for="latitude">Latitude:</label>
                <input type="number" id="latitude" name="latitude" step="any" value="<?php echo $editEntry['latitude']; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="longitude">Longitude:</label>
                <input type="number" id="longitude" name="longitude" step="any" value="<?php echo $editEntry['longitude']; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="date">Date:</label>
                <input type="date" id="date" name="date" value="<?php echo $editEntry['date']; ?>" required>
            </div>
            
            <div class="form-row-container">
                <div class="form-row">
                    <div class="form-group half">
                        <label for="depth">Maximum Depth (meters):</label>
                        <input type="number" id="depth" name="depth" step="0.1" min="0" value="<?php echo $editEntry['depth']; ?>">
                    </div>
                    
                    <div class="form-group half">
                        <label for="duration">Dive Time (minutes):</label>
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
                        <label for="visibility">Visibility (meters):</label>
                        <input type="number" id="visibility" name="visibility" min="0" value="<?php echo $editEntry['visibility']; ?>">
                    </div>
                    
                    <div class="form-group half">
                        <label for="buddy">Dive Partner:</label>
                        <input type="text" id="buddy" name="buddy" value="<?php echo htmlspecialchars($editEntry['buddy'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group half">
                        <label for="dive_site_type">Dive Site Type:</label>
                        <select id="dive_site_type" name="dive_site_type">
                            <option value="">Select Type</option>
                            <option value="Reef" <?php echo ($editEntry['dive_site_type'] == 'Reef') ? 'selected' : ''; ?>>Reef</option>
                            <option value="Wall" <?php echo ($editEntry['dive_site_type'] == 'Wall') ? 'selected' : ''; ?>>Wall</option>
                            <option value="Wreck" <?php echo ($editEntry['dive_site_type'] == 'Wreck') ? 'selected' : ''; ?>>Wreck</option>
                            <option value="Cave" <?php echo ($editEntry['dive_site_type'] == 'Cave') ? 'selected' : ''; ?>>Cave</option>
                            <option value="Shore" <?php echo ($editEntry['dive_site_type'] == 'Shore') ? 'selected' : ''; ?>>Shore</option>
                            <option value="Lake" <?php echo ($editEntry['dive_site_type'] == 'Lake') ? 'selected' : ''; ?>>Lake</option>
                            <option value="River" <?php echo ($editEntry['dive_site_type'] == 'River') ? 'selected' : ''; ?>>River</option>
                            <option value="Other" <?php echo ($editEntry['dive_site_type'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group half">
                        <label for="rating">Rating:</label>
                        <select id="rating" name="rating">
                            <option value="">Select Rating</option>
                            <option value="1" <?php echo ($editEntry['rating'] == 1) ? 'selected' : ''; ?>>★</option>
                            <option value="2" <?php echo ($editEntry['rating'] == 2) ? 'selected' : ''; ?>>★★</option>
                            <option value="3" <?php echo ($editEntry['rating'] == 3) ? 'selected' : ''; ?>>★★★</option>
                            <option value="4" <?php echo ($editEntry['rating'] == 4) ? 'selected' : ''; ?>>★★★★</option>
                            <option value="5" <?php echo ($editEntry['rating'] == 5) ? 'selected' : ''; ?>>★★★★★</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description" required><?php echo htmlspecialchars($editEntry['description']); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="comments">Additional Comments:</label>
                <textarea id="comments" name="comments"><?php echo htmlspecialchars($editEntry['comments'] ?? ''); ?></textarea>
            </div>
            
            <!-- Image Upload Section -->
            <div class="form-group">
                <label>Upload Images:</label>
                <div class="image-upload-container">
                    <label class="file-upload-button">
                        <span class="upload-icon">📷</span> Select Images
                        <input type="file" name="dive_images[]" multiple accept="image/*" class="file-input">
                    </label>
                    <span class="upload-help">You can select multiple images. Max 5MB each. JPG, PNG, GIF, and WEBP formats only.</span>
                </div>
            </div>
            
            <!-- Existing Images Section -->
            <?php if (!empty($diveImages)): ?>
            <div class="form-group">
                <label>Existing Images:</label>
                <div class="image-gallery">
                    <?php foreach ($diveImages as $image): ?>
                    <div class="image-item">
                        <img src="uploads/diveimages/<?php echo htmlspecialchars($image['filename']); ?>" alt="Dive Image" class="thumbnail">
                        <div class="image-details">
                            <div class="image-info">
                                <span class="image-name" title="<?php echo htmlspecialchars($image['original_filename']); ?>">
                                    <?php echo htmlspecialchars(substr($image['original_filename'], 0, 20) . (strlen($image['original_filename']) > 20 ? '...' : '')); ?>
                                </span>
                                <input type="text" name="image_captions[<?php echo $image['id']; ?>]" 
                                    placeholder="Add caption" 
                                    value="<?php echo htmlspecialchars($image['caption'] ?? ''); ?>" 
                                    class="image-caption">
                            </div>
                            <div class="image-actions">
                                <form method="post" onsubmit="return confirm('Are you sure you want to delete this image?');" class="delete-image-form">
                                    <input type="hidden" name="action" value="delete_image">
                                    <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                    <input type="hidden" name="divelog_id" value="<?php echo $editEntry['id']; ?>">
                                    <button type="submit" class="danger small">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div id="geocodeMessage"></div>
            
            <button type="submit">Update Entry</button>
            <a href="populate_db.php" class="btn">Cancel</a>
        </form>
    </div>
    <script>
        async function geocodeEditLocation() {
            const locationInput = document.getElementById('location');
            const latInput = document.getElementById('latitude');
            const longInput = document.getElementById('longitude');
            const messageDiv = document.getElementById('geocodeMessage');
            
            if (!locationInput.value) {
                messageDiv.innerHTML = '<div class="error">Please enter a location first.</div>';
                return;
            }
            
            messageDiv.innerHTML = '<div class="info">Geocoding location...</div>';
            
            try {
                const response = await fetch('?action=geocode_ajax&address=' + encodeURIComponent(locationInput.value));
                const data = await response.json();
                
                if (data.success) {
                    latInput.value = data.latitude;
                    longInput.value = data.longitude;
                    messageDiv.innerHTML = '<div class="success">Successfully geocoded: ' + data.latitude + ', ' + data.longitude + '</div>';
                } else {
                    messageDiv.innerHTML = '<div class="error">Could not geocode location. Please enter coordinates manually.</div>';
                }
            } catch (error) {
                messageDiv.innerHTML = '<div class="error">Error geocoding location: ' + error.message + '</div>';
            }
        }
    </script>
    <?php endif; ?>
</body>
</html>