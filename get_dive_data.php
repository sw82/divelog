<?php
// Start output buffering to prevent "headers already sent" issues
ob_start();

// Include database connection
include_once 'db.php';

// Set header for JSON response
header('Content-Type: application/json');

try {
    // Use the existing $conn from db.php
    global $conn;
    
    // Check if connection is valid
    if (!$conn || $conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Check if we're fetching a specific dive or all dives
    if (isset($_GET['id'])) {
        $dive_id = intval($_GET['id']);
        
        // Query to get specific dive
        $query = "SELECT id, location, country, date as dive_date, rating, depth as max_depth, duration, 
                     latitude, longitude, temperature, visibility, 
                     air_temperature, dive_site_type, buddy, description, comments,
                     YEAR(date) as year, dive_site,
                     air_consumption_start, air_consumption_end, weight, suit_type, water_type
              FROM divelogs
              WHERE id = ?";
              
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $dive_id);
    } else {
        // Query to get all dive logs with coordinates and more detailed information
        $query = "SELECT id, location, country, date as dive_date, rating, depth as max_depth, duration, 
                         latitude, longitude, temperature, visibility, 
                         air_temperature, dive_site_type, buddy, description, comments,
                         YEAR(date) as year, dive_site,
                         air_consumption_start, air_consumption_end, weight, suit_type, water_type
                  FROM divelogs
                  ORDER BY date DESC";
        
        // Convert to prepared statement
        $stmt = $conn->prepare($query);
    }
    
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $divelogs = [];
    while ($row = $result->fetch_assoc()) {
        $divelogs[] = $row;
    }
    
    // Array to store the complete data
    $result = [];
    
    // For each dive log, get images and fish sightings
    foreach ($divelogs as $dive) {
        // Format the data with all available fields
        $formatted_dive = [
            'id' => $dive['id'],
            'location' => $dive['location'] ?? '',
            'country' => $dive['country'] ?? '',
            'dive_date' => $dive['dive_date'] ? date('Y-m-d', strtotime($dive['dive_date'])) : '',
            'date' => $dive['dive_date'] ?? '',
            'year' => isset($dive['year']) && !empty($dive['year']) ? (int)$dive['year'] : null,
            'rating' => $dive['rating'] ?? null,
            'depth' => $dive['max_depth'] ?? null,
            'max_depth' => $dive['max_depth'] ?? null,
            'duration' => $dive['duration'] ?? null,
            'latitude' => (!empty($dive['latitude'])) ? (float)$dive['latitude'] : null,
            'longitude' => (!empty($dive['longitude'])) ? (float)$dive['longitude'] : null,
            'temperature' => $dive['temperature'] ?? null,
            'visibility' => $dive['visibility'] ?? null,
            'air_temperature' => $dive['air_temperature'] ?? null,
            'dive_site_type' => $dive['dive_site_type'] ?? '',
            'buddy' => $dive['buddy'] ?? '',
            'description' => $dive['description'] ?? '',
            'comments' => $dive['comments'] ?? '',
            'dive_site' => $dive['dive_site'] ?? '',
            'air_consumption_start' => $dive['air_consumption_start'] !== null ? (int)$dive['air_consumption_start'] : null,
            'air_consumption_end' => $dive['air_consumption_end'] !== null ? (int)$dive['air_consumption_end'] : null,
            'weight' => $dive['weight'] !== null ? (float)$dive['weight'] : null,
            'suit_type' => $dive['suit_type'] ?? null,
            'water_type' => $dive['water_type'] ?? null
        ];
        
        // Get dive images
        $imagesQuery = "SELECT id, filename, caption, upload_date FROM divelog_images WHERE divelog_id = ? ORDER BY upload_date DESC";
        $imagesStmt = $conn->prepare($imagesQuery);
        $imagesStmt->bind_param("i", $dive['id']);
        $imagesStmt->execute();
        $imagesResult = $imagesStmt->get_result();
        
        $images = [];
        while ($imageRow = $imagesResult->fetch_assoc()) {
            $images[] = [
                'id' => $imageRow['id'],
                'filename' => $imageRow['filename'],
                'caption' => $imageRow['caption'] ?? '',
                'upload_date' => $imageRow['upload_date'] ?? '',
                'image_path' => 'uploads/diveimages/' . $imageRow['filename']
            ];
        }
        
        $formatted_dive['images'] = $images;
        
        // Get fish sightings with more details
        $sightingsQuery = "SELECT fs.id as sighting_id, fs.fish_species_id, 
                                  fs.sighting_date, fs.quantity, fs.notes,
                                  fsp.common_name, fsp.scientific_name, fsp.description as fish_description,
                                  fsp.habitat, fsp.size_range,
                                  (SELECT filename FROM fish_images 
                                   WHERE fish_species_id = fs.fish_species_id AND is_primary = 1
                                   LIMIT 1) as image_path
                           FROM fish_sightings fs
                           JOIN fish_species fsp ON fs.fish_species_id = fsp.id
                           WHERE fs.divelog_id = ?
                           ORDER BY fs.sighting_date DESC";
        
        $sightingsStmt = $conn->prepare($sightingsQuery);
        $sightingsStmt->bind_param("i", $dive['id']);
        $sightingsStmt->execute();
        $sightingsResult = $sightingsStmt->get_result();
        
        $sightings = [];
        while ($sightingRow = $sightingsResult->fetch_assoc()) {
            // Format and sanitize sighting data
            $sightings[] = [
                'sighting_id' => $sightingRow['sighting_id'],
                'fish_species_id' => $sightingRow['fish_species_id'],
                'sighting_date' => $sightingRow['sighting_date'] ?? '',
                'quantity' => $sightingRow['quantity'] ?? '',
                'notes' => $sightingRow['notes'] ?? '',
                'common_name' => $sightingRow['common_name'] ?? '',
                'scientific_name' => $sightingRow['scientific_name'] ?? '',
                'fish_description' => $sightingRow['fish_description'] ?? '',
                'habitat' => $sightingRow['habitat'] ?? '',
                'size_range' => $sightingRow['size_range'] ?? '',
                'image_path' => $sightingRow['image_path'] ? 'uploads/fishimages/' . $sightingRow['image_path'] : null
            ];
        }
        
        $formatted_dive['fish_sightings'] = $sightings;
        
        // Add to result array
        $result[] = $formatted_dive;
    }
    
    // Check if we were looking for a specific dive
    if (isset($_GET['id'])) {
        if (count($result) > 0) {
            // Return just the first (and only) dive with success flag
            echo json_encode(['success' => true, 'data' => $result[0]]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Dive not found']);
        }
    } else {
        // Validate data before returning
        foreach ($result as &$dive) {
            // Ensure latitude and longitude are valid numbers
            if (isset($dive['latitude']) && (is_nan($dive['latitude']) || is_infinite($dive['latitude']))) {
                $dive['latitude'] = null;
            }
            if (isset($dive['longitude']) && (is_nan($dive['longitude']) || is_infinite($dive['longitude']))) {
                $dive['longitude'] = null;
            }
        }
        
        // Return the array of dives
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    // Return error message
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
} catch (Error $e) {
    // Catch PHP errors
    http_response_code(500);
    echo json_encode(['error' => 'PHP error: ' . $e->getMessage()]);
}

// Flush the output buffer and end buffering
ob_end_flush();
?> 