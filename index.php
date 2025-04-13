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

// Fetch dive logs from the database
$query = "SELECT * FROM divelogs";
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
            }, $diveImages)
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

// Sort years in descending order
rsort($years);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dive Log</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
</head>
<body>
    <nav class="menu">
        <a href="index.php" class="active">View Dive Log</a>
        <a href="populate_db.php">Manage Database</a>
    </nav>
    <h1>Dive Log</h1>
    
    <div class="stats-container">
        <div class="stat-box">
            <h3>Total Dives</h3>
            <p class="stat-value"><?php echo $totalDives; ?></p>
        </div>
        <?php if ($latestDive): ?>
        <div class="stat-box">
            <h3>Latest Dive</h3>
            <p class="stat-value"><?php echo date('M d, Y', strtotime($latestDive['date'])); ?></p>
            <p class="stat-location"><?php echo htmlspecialchars($latestDive['location']); ?></p>
        </div>
        <?php endif; ?>
        <?php if ($deepestDive && !empty($deepestDive['depth'])): ?>
        <div class="stat-box">
            <h3>Deepest Dive</h3>
            <p class="stat-value"><?php echo $deepestDive['depth']; ?> m</p>
            <p class="stat-location"><?php echo htmlspecialchars($deepestDive['location']); ?></p>
        </div>
        <?php endif; ?>
        <div class="stat-box">
            <h3>Number of Locations</h3>
            <p class="stat-value"><?php echo count(array_unique(array_column($diveLogsData, 'location'))); ?></p>
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
    
    <div id="map" style="height: 600px;"></div>
    
    <script>
        // Pass PHP data to JavaScript
        var diveLogsData = <?php echo json_encode($diveLogsData); ?>;
    </script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="script.js"></script>
</body>
</html> 