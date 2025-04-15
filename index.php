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

// Fetch dive logs from the database - only diving activities for statistics
$query = "SELECT * FROM divelogs WHERE activity_type = 'diving' OR activity_type IS NULL";
$result = $conn->query($query);

// Prepare data for JavaScript
$diveLogsData = [];
$years = [];
$totalDives = 0;
$latestDive = null;
$deepestDive = null;

if ($result && $result->num_rows > 0) {
    $totalDives = $result->num_rows;
    
    while ($row = $result->fetch_assoc()) {
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

// Now fetch all activities (including snorkeling) for display in map
$allActivitiesQuery = "SELECT * FROM divelogs";
$allResult = $conn->query($allActivitiesQuery);

if ($allResult && $allResult->num_rows > 0) {
    while ($row = $allResult->fetch_assoc()) {
        // Skip if this is already in diveLogsData (it's a dive)
        $isDive = false;
        foreach ($diveLogsData as $dive) {
            if ($dive['id'] == $row['id']) {
                $isDive = true;
                break;
            }
        }
        
        if (!$isDive) {
            // Get images for this activity
            $activityImages = getDiveImages($row['id']);
            
            // Get fish sightings for this activity
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
                }, $activityImages),
                'fish_sightings' => $fishSightings
            ];
            
            // Collect unique years for filter
            $year = substr($row['date'], 0, 4);
            if (!in_array($year, $years)) {
                $years[] = $year;
            }
        }
    }
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
    </style>
</head>
<body>
    <?php include 'navigation.php'; ?>
    <div class="container-fluid">
        <h1 class="text-center my-3">Dive Log</h1>
        
        <div class="stats-container row">
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-box">
                    <h3>Total Dives</h3>
                    <p class="stat-value"><?php echo $totalDives; ?></p>
                    <p class="stat-note">(Snorkeling not included)</p>
                </div>
            </div>
            <?php if ($latestDive): ?>
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-box">
                    <h3>Latest Dive</h3>
                    <p class="stat-value"><?php echo date('M d, Y', strtotime($latestDive['date'])); ?></p>
                    <p class="stat-location"><?php echo htmlspecialchars($latestDive['location']); ?></p>
                    <p class="stat-note">(Diving only)</p>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($deepestDive && !empty($deepestDive['depth'])): ?>
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-box">
                    <h3>Deepest Dive</h3>
                    <p class="stat-value"><?php echo $deepestDive['depth']; ?> m</p>
                    <p class="stat-location"><?php echo htmlspecialchars($deepestDive['location']); ?></p>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-box">
                    <h3>Dive Locations</h3>
                    <p class="stat-value"><?php echo count(array_unique(array_column($diveLogsData, 'location'))); ?></p>
                    <p class="stat-note">(Diving only)</p>
                </div>
            </div>
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
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Pass PHP data to JavaScript
        var diveLogsData = <?php echo json_encode($diveLogsData); ?>;
        console.log("Loaded " + diveLogsData.length + " dive log entries");
    </script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="script.js"></script>
</body>
</html> 