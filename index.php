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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#333333">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <title>Dive Log</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <!-- Add MarkerCluster CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .fish-sightings-container {
            margin-top: 15px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        .fish-sightings-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .fish-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .fish-item {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
            background-color: #f9f9f9;
            max-width: 100%;
        }
        .fish-image {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 3px;
            margin-right: 8px;
            flex-shrink: 0;
        }
        .fish-image-placeholder {
            width: 40px;
            height: 40px;
            background-color: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 3px;
            margin-right: 8px;
            font-size: 10px;
            text-align: center;
            color: #777;
        }
        .fish-info {
            flex-grow: 1;
            overflow: hidden;
        }
        .fish-name {
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 12px;
        }
        .fish-quantity {
            font-size: 11px;
            color: #666;
        }
        .more-fish {
            margin-top: 10px;
            font-size: 12px;
            color: #2196F3;
        }
        
        /* Year Legend Styles */
        .year-legend {
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.4);
            max-width: 200px;
        }
        .year-legend h4 {
            margin: 0 0 8px 0;
            font-size: 14px;
            color: #333;
        }
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            font-size: 12px;
        }
        .color-box {
            display: inline-block;
            width: 16px;
            height: 16px;
            margin-right: 8px;
            border-radius: 3px;
            border: 1px solid rgba(0,0,0,0.2);
        }
        
        /* Colored Marker Style */
        .colored-marker {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Search Form */
        .search-form {
            margin-bottom: 15px;
            display: flex;
            max-width: 500px;
            margin: 0 auto 15px;
        }
        
        .search-form input {
            flex-grow: 1;
            border: 1px solid #ddd;
            border-radius: 4px 0 0 4px;
            padding: 8px 12px;
            font-size: 14px;
        }
        
        .search-form button {
            background-color: #2196F3;
            border: none;
            color: white;
            padding: 8px 15px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
        }
        
        .search-results {
            margin-bottom: 15px;
            padding: 8px 15px;
            background-color: #f5f5f5;
            border-radius: 4px;
            text-align: center;
            font-size: 14px;
        }
        
        .search-results-count {
            font-weight: bold;
            color: #2196F3;
        }
        
        .clear-search {
            color: #f44336;
            text-decoration: none;
            margin-left: 10px;
        }
        
        /* Marker Cluster Custom Styles */
        .custom-cluster-icon {
            background: none;
        }
        
        .cluster-icon {
            background-color: #4363d8;
            width: 36px;
            height: 36px;
            border-radius: 18px;
            border: 3px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            color: white;
            box-shadow: 0 1px 5px rgba(0,0,0,0.3);
        }
        
        /* Override Leaflet MarkerCluster Default Styles */
        .marker-cluster-small,
        .marker-cluster-medium,
        .marker-cluster-large {
            background-color: rgba(67, 99, 216, 0.2) !important;
        }
        
        .marker-cluster-small div,
        .marker-cluster-medium div,
        .marker-cluster-large div {
            background-color: #4363d8 !important;
            color: white !important;
            font-weight: bold !important;
            border: 3px solid white !important;
            box-shadow: 0 1px 5px rgba(0,0,0,0.3) !important;
        }
        
        /* Cluster Popup Styles */
        .cluster-popup-container {
            margin-bottom: 30px;
        }
        
        .cluster-popup h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 16px;
            color: #333;
        }
        
        .cluster-popup p {
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .locations-list {
            margin: 0;
            padding-left: 20px;
            margin-bottom: 15px;
        }
        
        .locations-list li {
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .cluster-note {
            font-style: italic;
            color: #666;
            font-size: 12px !important;
            text-align: center;
            border-top: 1px solid #eee;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    <?php include 'navigation.php'; ?>
    <div class="container-fluid">
        <h1 class="text-center my-3">Dive Log</h1>
        
        <!-- Search Form -->
        <form method="GET" action="" class="search-form">
            <input 
                type="text" 
                name="search" 
                placeholder="Search for location, dive site, buddy..." 
                value="<?php echo htmlspecialchars($searchTerm); ?>"
            >
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
        
        <?php if ($searchResults): ?>
        <div class="search-results">
            Found <span class="search-results-count"><?php echo count($diveLogsData); ?></span> 
            dive log entries for "<?php echo htmlspecialchars($searchTerm); ?>"
            <a href="index.php" class="clear-search"><i class="fas fa-times"></i> Clear</a>
        </div>
        <?php endif; ?>
        
        <div class="stats-container row justify-content-center">
            <div class="col-4 col-md-3 mb-2">
                <a href="divelist.php" class="stat-link">
                    <div class="stat-box">
                        <h3>Dives</h3>
                        <p class="stat-value"><?php echo $totalDives; ?></p>
                    </div>
                </a>
            </div>
            <?php if ($latestDive): ?>
            <div class="col-5 col-md-4 mb-2">
                <div class="stat-box">
                    <h3>Latest</h3>
                    <p class="stat-value"><?php echo date('d M', strtotime($latestDive['date'])); ?></p>
                    <p class="stat-location"><?php echo htmlspecialchars($latestDive['location']); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="filter-container">
            <h3>Filter by Year:</h3>
            <div class="year-filters">
                <button class="year-filter active" data-year="all">All Years</button>
                <?php foreach ($years as $year): ?>
                <button class="year-filter" data-year="<?php echo $year; ?>"><?php echo $year; ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div id="map"></div>
        
        <div class="text-center mt-4 mb-4">
            <h3>All Dive Log Entries (<?php echo count($diveLogsData); ?>)</h3>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Pass PHP data to JavaScript
        var diveLogsData = <?php echo json_encode($diveLogsData); ?>;
        console.log("Loaded " + diveLogsData.length + " dive log entries");
    </script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>
    <script src="script.js"></script>
</body>
</html> 