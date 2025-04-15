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

// Fetch all dive logs from the database
$query = "SELECT * FROM divelogs";
$params = [];
$types = "";

// Add search criteria if search term is provided
if (!empty($searchTerm)) {
    $query .= " WHERE location LIKE ? OR description LIKE ? OR dive_site_type LIKE ? OR buddy LIKE ?";
    $searchParam = "%$searchTerm%";
    $params = [$searchParam, $searchParam, $searchParam, $searchParam];
    $types = "ssss";
}

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
    // Count the diving activities (not snorkeling)
    $divingCount = 0;
    $snorkelingCount = 0;
    
    while ($row = $result->fetch_assoc()) {
        // Track activity type counts
        if ($row['activity_type'] === 'snorkeling') {
            $snorkelingCount++;
        } else {
            $divingCount++;
        }
        
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
            'activity_type' => $row['activity_type'],
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
        
        // Check if it's a dive (not snorkeling) for dive-specific statistics
        if ($row['activity_type'] !== 'snorkeling') {
            // Track latest dive
            if ($latestDive === null || $row['date'] > $latestDive['date']) {
                $latestDive = $row;
            }
            
            // Track deepest dive
            if (!empty($row['depth']) && ($deepestDive === null || $row['depth'] > $deepestDive['depth'])) {
                $deepestDive = $row;
            }
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
$diveLogsQuery = "SELECT id, location, date, rating, depth, duration, latitude, longitude, activity_type, 
                        temperature, visibility, air_temperature, dive_site_type, buddy, description, comments,
                        YEAR(date) as year
                 FROM divelogs 
                 WHERE latitude IS NOT NULL AND longitude IS NOT NULL
                 ORDER BY date DESC";
$diveLogsResult = $conn->query($diveLogsQuery);

$diveLogs = [];
if ($diveLogsResult && $diveLogsResult->num_rows > 0) {
    while ($row = $diveLogsResult->fetch_assoc()) {
        // Add the raw dive log data
        $diveLog = $row;
        
        // Get fish sightings count
        $fishQuery = "SELECT COUNT(*) as count FROM fish_sightings WHERE divelog_id = " . $row['id'];
        $fishResult = $conn->query($fishQuery);
        if ($fishResult && $fishRow = $fishResult->fetch_assoc()) {
            $diveLog['fish_count'] = $fishRow['count'];
        } else {
            $diveLog['fish_count'] = 0;
        }
        
        // Get images for this dive
        $imagesQuery = "SELECT id, filename, caption FROM divelog_images WHERE divelog_id = " . $row['id'];
        $imagesResult = $conn->query($imagesQuery);
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
$totalSnorkeling = 0;
$totalMinutes = 0;
$maxDepth = 0;
$locations = [];

foreach ($diveLogs as $dive) {
    // Count by activity type
    if ($dive['activity_type'] === 'snorkeling') {
        $totalSnorkeling++;
    } else {
        $totalDives++;
    }
    
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

$totalActivities = $totalDives + $totalSnorkeling;
$locationCount = count($locations);
$avgDuration = $totalActivities > 0 ? round($totalMinutes / $totalActivities) : 0;

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dive Log Map</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        #map {
            height: 600px;
            width: 100%;
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

    <div class="container mt-4 mb-5">
        <div class="stats-container">
            <div class="stat-box">
                <div class="stat-label">Total Activities</div>
                <div class="stat-value"><?php echo $totalActivities; ?></div>
                <div class="stat-label"><?php echo $locationCount; ?> locations</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Dive Activities</div>
                <div class="stat-value"><?php echo $totalDives; ?></div>
                <div class="stat-label"><?php echo round(($totalDives / max(1, $totalActivities)) * 100); ?>% of total</div>
            </div>
            <div class="stat-box snorkel">
                <div class="stat-label">Snorkeling</div>
                <div class="stat-value"><?php echo $totalSnorkeling; ?></div>
                <div class="stat-label"><?php echo round(($totalSnorkeling / max(1, $totalActivities)) * 100); ?>% of total</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Max Depth</div>
                <div class="stat-value"><?php echo $maxDepth; ?>m</div>
                <div class="stat-label">deepest dive</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Average Duration</div>
                <div class="stat-value"><?php echo $avgDuration; ?></div>
                <div class="stat-label">minutes per activity</div>
            </div>
            <?php if ($latestDive): ?>
            <div class="stat-box" style="border-top-color: #FF9800;">
                <div class="stat-label">Latest Dive</div>
                <div class="stat-value" style="color: #FF9800;"><?php echo date('M d', strtotime($latestDive['date'])); ?></div>
                <div class="stat-label"><?php echo htmlspecialchars($latestDive['location']); ?></div>
            </div>
            <?php endif; ?>
        </div>
        
        <div id="map"></div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Transfer PHP data to JavaScript
        const diveLogsData = <?php echo json_encode($diveLogs); ?>;
        
        // Check if we should highlight a specific location from URL params
        const highlightLat = <?php echo $highlightLat ? $highlightLat : 'null'; ?>;
        const highlightLng = <?php echo $highlightLng ? $highlightLng : 'null'; ?>;
        const highlightTitle = "<?php echo $highlightTitle; ?>";
    </script>
    <script src="map.js"></script>
</body>
</html> 