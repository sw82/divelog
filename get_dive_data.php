<?php
// Include database connection - make sure db.php doesn't have trailing whitespace or closing PHP tag
include_once 'db.php';

// Set header for JSON response
header('Content-Type: application/json');

try {
    // Use the existing $conn from db.php
    global $conn;
    
    // Check if connection is valid
    if (!$conn || $conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    
    // Query to get all dive logs with coordinates
    $query = "SELECT id, location, date as dive_date, rating, depth as max_depth, duration, 
                     latitude, longitude, activity_type, 
                     YEAR(date) as year
              FROM divelogs
              WHERE latitude IS NOT NULL AND longitude IS NOT NULL
              ORDER BY date DESC";
    
    $result = $conn->query($query);
    
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
        // Format the data
        $formatted_dive = [
            'id' => $dive['id'],
            'location' => $dive['location'],
            'date' => $dive['dive_date'],
            'year' => $dive['year'],
            'rating' => $dive['rating'],
            'max_depth' => $dive['max_depth'],
            'duration' => $dive['duration'],
            'latitude' => $dive['latitude'],
            'longitude' => $dive['longitude'],
            'activity_type' => $dive['activity_type'] ?: 'diving'
        ];
        
        // Get dive images
        $imagesQuery = "SELECT filename FROM divelog_images WHERE divelog_id = ?";
        $imagesStmt = $conn->prepare($imagesQuery);
        $imagesStmt->bind_param("i", $dive['id']);
        $imagesStmt->execute();
        $imagesResult = $imagesStmt->get_result();
        
        $images = [];
        while ($imageRow = $imagesResult->fetch_assoc()) {
            $images[] = $imageRow['filename'];
        }
        
        $formatted_dive['images'] = $images;
        
        // Get fish sightings
        $sightingsQuery = "SELECT fs.id as sighting_id, fs.fish_species_id, 
                                  fs.sighting_date, fs.quantity, fs.notes,
                                  fsp.common_name, fsp.scientific_name, 
                                  (SELECT filename FROM fish_images 
                                   WHERE fish_species_id = fs.fish_species_id AND is_primary = 1
                                   LIMIT 1) as image_path
                           FROM fish_sightings fs
                           JOIN fish_species fsp ON fs.fish_species_id = fsp.id
                           WHERE fs.divelog_id = ?";
        
        $sightingsStmt = $conn->prepare($sightingsQuery);
        $sightingsStmt->bind_param("i", $dive['id']);
        $sightingsStmt->execute();
        $sightingsResult = $sightingsStmt->get_result();
        
        $sightings = [];
        while ($sightingRow = $sightingsResult->fetch_assoc()) {
            $sightings[] = $sightingRow;
        }
        
        $formatted_dive['fish_sightings'] = $sightings;
        
        // Add to result array
        $result[] = $formatted_dive;
    }
    
    // Return the data as JSON
    echo json_encode($result);
    
} catch (Exception $e) {
    // Return error message
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?> 