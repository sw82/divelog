<?php
// Include database connection
include 'db.php';

// Function to get dive images
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

// Function to get fish sightings for a dive
function getFishSightings($divelogId) {
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

// Initialize search variables
$searchTerm = '';
$searchResults = false;

// Handle search request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    $searchResults = true;
}

// Build the SQL query
$query = "SELECT id, location, dive_site, latitude, longitude, date, DATE_FORMAT(date, '%Y') AS year,
          depth, duration, rating, temperature, air_temperature, visibility,
          dive_site_type, description, comments, buddy
          FROM divelogs";

// If search term provided, add WHERE clause
if (!empty($searchTerm)) {
    $searchParam = "%$searchTerm%";
    $query .= " WHERE location LIKE ? OR dive_site LIKE ? OR buddy LIKE ?";
}

// Get total count first (for pagination)
$countQuery = str_replace("SELECT id, location, dive_site, latitude, longitude, date, DATE_FORMAT(date, '%Y') AS year,
          depth, duration, rating, temperature, air_temperature, visibility,
          dive_site_type, description, comments, buddy", "SELECT COUNT(*) as total", $query);

// Add order by date for consistent display
$query .= " ORDER BY date DESC";

// Prepare and execute the query with or without search parameters
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

// Prepare data for JavaScript
$diveLogsData = [];
$years = [];
$totalDives = 0;
$latestDive = null;
$deepestDive = null;

if ($result && $result->num_rows > 0) {
    // Track dive count (all are diving now)
    $divingCount = 0;
    
    while ($row = $result->fetch_assoc()) {
        // Track dive count (all are diving now)
        $divingCount++;
        
        // Get images for this dive
        $diveImages = getDiveImages($row['id']);
        
        // Get fish sightings for this dive
        $fishSightings = getFishSightings($row['id']);
        
        $diveLogsData[] = [
            'id' => $row['id'],
            'location' => $row['location'],
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
            'date' => $row['date'],
            'description' => $row['description'],
            'year' => substr($row['date'], 0, 4), // Extract year from date
            'depth' => $row['depth'],
            'duration' => $row['duration'],
            'temperature' => $row['temperature'],
            'air_temperature' => $row['air_temperature'],
            'visibility' => $row['visibility'],
            'buddy' => $row['buddy'],
            'dive_site_type' => $row['dive_site_type'],
            'rating' => $row['rating'],
            'comments' => $row['comments'],
            'images' => array_map(function($img) {
                return [
                    'id' => $img['id'],
                    'filename' => $img['filename'],
                    'caption' => $img['caption']
                ];
            }, $diveImages),
            'fish_sightings' => $fishSightings
        ];
        
        // Collect unique years for filter
        $year = substr($row['date'], 0, 4);
        if (!in_array($year, $years)) {
            $years[] = $year;
        }
        
        // All activities are diving now - track statistics
        // Track latest dive
        if ($latestDive === null || $row['date'] > $latestDive['date']) {
            $latestDive = $row;
        }
        
        // Track deepest dive
        if (!empty($row['depth']) && ($deepestDive === null || $row['depth'] > $deepestDive['depth'])) {
            $deepestDive = $row;
        }
    }
    
    // Set total dives count
    $totalDives = $divingCount;
}

// Convert latitude and longitude to floats for proper handling
foreach ($diveLogsData as $index => $diveLog) {
    $diveLogsData[$index]['latitude'] = (float)$diveLog['latitude'];
    $diveLogsData[$index]['longitude'] = (float)$diveLog['longitude'];
}

// Sort years in descending order
rsort($years);

// Count unique dive locations
$uniqueLocations = [];
foreach ($diveLogsData as $dive) {
    if (!in_array($dive['location'], $uniqueLocations)) {
        $uniqueLocations[] = $dive['location'];
    }
}
$locationCount = count($uniqueLocations);

// Get dive logs for the map
$diveLogsQuery = "SELECT id, location, date, rating, depth, duration, latitude, longitude, 
                        temperature, visibility, air_temperature, dive_site_type, buddy, description, comments,
                        YEAR(date) as year, dive_site,
                        air_consumption_start, air_consumption_end, weight, suit_type, water_type
                 FROM divelogs 
                 WHERE latitude IS NOT NULL AND longitude IS NOT NULL
                 ORDER BY date DESC";
$stmt = $conn->prepare($diveLogsQuery);
$stmt->execute();
$diveLogsResult = $stmt->get_result();

$diveLogs = [];
if ($diveLogsResult && $diveLogsResult->num_rows > 0) {
    while ($row = $diveLogsResult->fetch_assoc()) {
        // Add the raw dive log data
        $diveLog = $row;
        
        // Add technical fields to ensure they're all available
        if (!isset($diveLog['air_consumption_start'])) $diveLog['air_consumption_start'] = null;
        if (!isset($diveLog['air_consumption_end'])) $diveLog['air_consumption_end'] = null;
        if (!isset($diveLog['weight'])) $diveLog['weight'] = null;
        if (!isset($diveLog['suit_type'])) $diveLog['suit_type'] = null;
        if (!isset($diveLog['water_type'])) $diveLog['water_type'] = null;
        if (!isset($diveLog['dive_site'])) $diveLog['dive_site'] = null;
        
        // Get fish sightings count using prepared statement
        $fishQuery = "SELECT COUNT(*) as count FROM fish_sightings WHERE divelog_id = ?";
        $fishStmt = $conn->prepare($fishQuery);
        $fishStmt->bind_param("i", $row['id']);
        $fishStmt->execute();
        $fishResult = $fishStmt->get_result();
        
        if ($fishResult && $fishRow = $fishResult->fetch_assoc()) {
            $diveLog['fish_count'] = $fishRow['count'];
        } else {
            $diveLog['fish_count'] = 0;
        }
        
        // Get images for this dive using prepared statement
        $imagesQuery = "SELECT id, filename, caption FROM divelog_images WHERE divelog_id = ?";
        $imagesStmt = $conn->prepare($imagesQuery);
        $imagesStmt->bind_param("i", $row['id']);
        $imagesStmt->execute();
        $imagesResult = $imagesStmt->get_result();
        
        $diveLog['images'] = [];
        if ($imagesResult && $imagesResult->num_rows > 0) {
            while ($imageRow = $imagesResult->fetch_assoc()) {
                $diveLog['images'][] = $imageRow;
            }
        }
        
        $diveLogs[] = $diveLog;
    }
}

// Statistics
$totalDives = 0;
$totalMinutes = 0;
$maxDepth = 0;
$locations = [];

foreach ($diveLogs as $dive) {
    // Count all dives
    $totalDives++;
    
    // Track locations
    if (!in_array($dive['location'], $locations)) {
        $locations[] = $dive['location'];
    }
    
    // Track max depth
    if (!empty($dive['depth']) && $dive['depth'] > $maxDepth) {
        $maxDepth = $dive['depth'];
    }
    
    // Track total minutes
    if (!empty($dive['duration'])) {
        $totalMinutes += $dive['duration'];
    }
}

$locationCount = count($locations);
$avgDuration = $totalDives > 0 ? round($totalMinutes / $totalDives) : 0;

// Find the latest dive
$latestDive = null;
foreach ($diveLogs as $dive) {
    if ($latestDive === null || strtotime($dive['date']) > strtotime($latestDive['date'])) {
        $latestDive = $dive;
    }
}

// Get highlighted location from URL params if provided
$highlightLat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$highlightLng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
$highlightTitle = isset($_GET['title']) ? htmlspecialchars($_GET['title']) : '';

// Check if we have a cache busting parameter - if so, force refresh of data
$cacheBust = isset($_GET['cache_bust']);
if ($cacheBust) {
    // Force a clean query of the database by setting headers to prevent caching
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    
    // Re-run the query to ensure fresh data
    $diveLogsQuery = "SELECT id, location, date, rating, depth, duration, latitude, longitude, 
                         temperature, visibility, air_temperature, dive_site_type, buddy, description, comments,
                         YEAR(date) as year, dive_site,
                         air_consumption_start, air_consumption_end, weight, suit_type, water_type
                  FROM divelogs 
                  WHERE latitude IS NOT NULL AND longitude IS NOT NULL
                  ORDER BY date DESC";
    $stmt = $conn->prepare($diveLogsQuery);
    $stmt->execute();
    $diveLogsResult = $stmt->get_result();
    
    // Clear and rebuild dive logs array
    $diveLogs = [];
    if ($diveLogsResult && $diveLogsResult->num_rows > 0) {
        while ($row = $diveLogsResult->fetch_assoc()) {
            // Add the raw dive log data
            $diveLog = $row;
            
            // Add technical fields to ensure they're all available
            if (!isset($diveLog['air_consumption_start'])) $diveLog['air_consumption_start'] = null;
            if (!isset($diveLog['air_consumption_end'])) $diveLog['air_consumption_end'] = null;
            if (!isset($diveLog['weight'])) $diveLog['weight'] = null;
            if (!isset($diveLog['suit_type'])) $diveLog['suit_type'] = null;
            if (!isset($diveLog['water_type'])) $diveLog['water_type'] = null;
            if (!isset($diveLog['dive_site'])) $diveLog['dive_site'] = null;
            
            // Get fish sightings count using prepared statement
            $fishQuery = "SELECT COUNT(*) as count FROM fish_sightings WHERE divelog_id = ?";
            $fishStmt = $conn->prepare($fishQuery);
            $fishStmt->bind_param("i", $row['id']);
            $fishStmt->execute();
            $fishResult = $fishStmt->get_result();
            
            if ($fishResult && $fishRow = $fishResult->fetch_assoc()) {
                $diveLog['fish_count'] = $fishRow['count'];
            } else {
                $diveLog['fish_count'] = 0;
            }
            
            // Get images for this dive using prepared statement
            $imagesQuery = "SELECT id, filename, caption FROM divelog_images WHERE divelog_id = ?";
            $imagesStmt = $conn->prepare($imagesQuery);
            $imagesStmt->bind_param("i", $row['id']);
            $imagesStmt->execute();
            $imagesResult = $imagesStmt->get_result();
            
            $diveLog['images'] = [];
            if ($imagesResult && $imagesResult->num_rows > 0) {
                while ($imageRow = $imagesResult->fetch_assoc()) {
                    $diveLog['images'][] = $imageRow;
                }
            }
            
            $diveLogs[] = $diveLog;
        }
    }
    
    // Recalculate statistics
    $totalDives = 0;
    $totalMinutes = 0;
    $maxDepth = 0;
    $locations = [];

    foreach ($diveLogs as $dive) {
        // Count all dives
        $totalDives++;
        
        // Track locations
        if (!in_array($dive['location'], $locations)) {
            $locations[] = $dive['location'];
        }
        
        // Track max depth
        if (!empty($dive['depth']) && $dive['depth'] > $maxDepth) {
            $maxDepth = $dive['depth'];
        }
        
        // Track total minutes
        if (!empty($dive['duration'])) {
            $totalMinutes += $dive['duration'];
        }
    }

    $locationCount = count($locations);
    $avgDuration = $totalDives > 0 ? round($totalMinutes / $totalDives) : 0;

    // Find the latest dive
    $latestDive = null;
    foreach ($diveLogs as $dive) {
        if ($latestDive === null || strtotime($dive['date']) > strtotime($latestDive['date'])) {
            $latestDive = $dive;
        }
    }
}

// Convert dive logs to JSON for JavaScript
$diveLogsJSON = json_encode($diveLogs);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Dive Log Map</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .map-header {
            margin-bottom: 20px;
            text-align: center;
        }
        #map-container {
            width: 100%;
            margin-bottom: 20px;
        }
        #map {
            height: 75vh;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stats-container {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .stat-box {
            flex: 1;
            min-width: 180px;
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            border-top: 3px solid #2196F3;
        }
        
        .stat-box:hover {
            transform: translateY(-5px);
        }
        
        .stat-box.snorkel {
            border-top-color: #4CAF50;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #2196F3;
            margin: 5px 0;
            line-height: 1;
        }
        
        .stat-box.snorkel .stat-value {
            color: #4CAF50;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 0;
        }
        
        .filter-container {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .filter-title {
            margin-bottom: 10px;
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }
        
        .year-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .year-filter {
            background-color: #fff;
            border: 1px solid #ddd;
            padding: 6px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
            color: #333;
        }
        
        .year-filter:hover {
            background-color: #e9ecef;
        }
        
        .year-filter.active {
            background-color: #2196F3;
            color: white;
            border-color: #2196F3;
        }
        
        @media (max-width: 768px) {
            .stat-box {
                min-width: 140px;
            }
        }
    </style>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
</head>
<body>
    <?php include 'navigation.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <!-- Map container with consistent width -->
                <div class="map-container">
                    <div id="map"></div>
                    
                    <!-- Year filter control -->
                    <div class="year-filter-container" id="year-filter"></div>
                    
                    <!-- Legend -->
                    <div class="map-legend" id="map-legend"></div>
                </div>
                
                <!-- Stats container with consistent width -->
                <div class="dive-stats-container">
                    <div class="row" id="dive-stats"></div>
                    
                    <!-- Most Valuable Stats Section -->
                    <div class="row mt-3" id="most-valuable-stats">
                        <div class="col-md-6 mb-3">
                            <div class="stat-card" style="border-top: 3px solid #28a745">
                                <div class="stat-icon">
                                    <i class="fas fa-trophy" style="color: #28a745"></i>
                                </div>
                                <div class="stat-content">
                                    <h3 class="stat-title">Most Efficient Dive</h3>
                                    <div id="most-efficient-dive" class="valuable-stat-content">
                                        <div class="text-center py-3">
                                            <i class="fas fa-spinner fa-spin"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="stat-card" style="border-top: 3px solid #dc3545">
                                <div class="stat-icon">
                                    <i class="fas fa-exclamation-triangle" style="color: #dc3545"></i>
                                </div>
                                <div class="stat-content">
                                    <h3 class="stat-title">Least Efficient Dive</h3>
                                    <div id="least-efficient-dive" class="valuable-stat-content">
                                        <div class="text-center py-3">
                                            <i class="fas fa-spinner fa-spin"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Stats Section - Depth/Duration/Consumption Analysis -->
                <div class="dive-advanced-stats-container mt-4 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Depth & Air Consumption Analysis</h5>
                        </div>
                        <div class="card-body" id="depth-duration-stats">
                            <div class="text-center">
                                <i class="fas fa-spinner fa-spin"></i> Calculating statistics...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Transfer PHP data to JavaScript
        const diveLogsData = <?php echo $diveLogsJSON; ?>;
        
        // Check if we should highlight a specific location from URL params
        const highlightLat = <?php echo $highlightLat ? $highlightLat : 'null'; ?>;
        const highlightLng = <?php echo $highlightLng ? $highlightLng : 'null'; ?>;
        const highlightTitle = "<?php echo $highlightTitle; ?>";
    </script>
    <script src="map.js?v=<?php echo time(); ?>"></script>
    <script src="script.js?v=<?php echo time(); ?>"></script>
</body>
</html> 