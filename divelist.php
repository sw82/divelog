<?php
// Include database connection
include 'db.php';

// Initialize search variables
$searchTerm = '';
$searchResults = false;
$activityFilter = isset($_GET['activity']) ? $_GET['activity'] : 'all';
$yearFilter = isset($_GET['year']) ? $_GET['year'] : 'all';

// Handle search request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    $searchResults = true;
}

// Fetch dive logs from the database
$query = "SELECT d.*, 
                 (SELECT COUNT(*) FROM fish_sightings WHERE divelog_id = d.id) as fish_count,
                 (SELECT COUNT(*) FROM divelog_images WHERE divelog_id = d.id) as image_count
          FROM divelogs d";
$params = [];
$types = "";
$conditions = [];

// Add search criteria if search term is provided
if (!empty($searchTerm)) {
    $conditions[] = "(d.location LIKE ? OR d.dive_site LIKE ? OR d.description LIKE ? OR d.comments LIKE ? OR d.buddy LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $types .= "sssss";
}

// Add year filter if not showing all
if ($yearFilter !== 'all') {
    $conditions[] = "YEAR(d.date) = ?";
    $params[] = $yearFilter;
    $types .= "s";
}

// Combine all conditions
if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

// Sort by date descending
$query .= " ORDER BY d.date DESC";

// Prepare and execute the query
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

// Count records
$totalDives = 0;
$totalSnorkeling = 0;
$diveRecords = [];
$years = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Track counts by activity type
        if ($row['activity_type'] === 'snorkeling') {
            $totalSnorkeling++;
        } else {
            $totalDives++;
        }
        
        // Add year to years array for filter
        $year = date('Y', strtotime($row['date']));
        if (!in_array($year, $years)) {
            $years[] = $year;
        }
        
        // Add row to records array
        $diveRecords[] = $row;
    }
}

// Sort years in descending order
rsort($years);

$totalRecords = count($diveRecords);

// Get statistics
$maxDepth = 0;
$totalDuration = 0;
$totalFishSightings = 0;
$totalImagesCount = 0;

foreach ($diveRecords as $dive) {
    if (!empty($dive['depth']) && $dive['depth'] > $maxDepth) {
        $maxDepth = $dive['depth'];
    }
    if (!empty($dive['duration'])) {
        $totalDuration += $dive['duration'];
    }
    $totalFishSightings += $dive['fish_count'];
    $totalImagesCount += $dive['image_count'];
}

// Average duration
$avgDuration = $totalRecords > 0 ? round($totalDuration / $totalRecords) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dive List</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .dive-list-container {
            margin: 20px auto;
            max-width: 1200px;
        }
        
        .dive-stats {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            flex: 1;
            min-width: 150px;
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-top: 3px solid #2196F3;
        }
        
        .snorkel-stat {
            border-top-color: #4CAF50;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2196F3;
            margin-bottom: 5px;
        }
        
        .snorkel-stat .stat-value {
            color: #4CAF50;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
        }
        
        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-label {
            font-size: 14px;
            font-weight: 600;
            color: #555;
            margin-right: 5px;
        }
        
        .activity-filter, .year-filter {
            display: flex;
            gap: 5px;
        }
        
        .filter-btn {
            border: 1px solid #ddd;
            background-color: #fff;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .filter-btn:hover {
            background-color: #f1f1f1;
        }
        
        .filter-btn.active {
            background-color: #2196F3;
            color: white;
            border-color: #2196F3;
        }
        
        .year-filter .filter-btn.active {
            background-color: #673AB7;
            border-color: #673AB7;
        }
        
        .search-form {
            display: flex;
            min-width: 250px;
        }
        
        .search-form input {
            flex-grow: 1;
            border: 1px solid #ddd;
            border-radius: 4px 0 0 4px;
            padding: 6px 12px;
            font-size: 14px;
        }
        
        .search-form button {
            background-color: #2196F3;
            border: none;
            color: white;
            padding: 6px 12px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
        }
        
        .dive-table {
            width: 100%;
            border-collapse: collapse;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .dive-table th {
            background-color: #f5f5f5;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid #ddd;
            color: #444;
        }
        
        .dive-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            vertical-align: middle;
        }
        
        .dive-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .dive-date {
            white-space: nowrap;
            font-weight: 600;
        }
        
        .dive-date-day {
            font-size: 16px;
        }
        
        .dive-date-year {
            font-size: 13px;
            color: #666;
        }
        
        .dive-rating {
            display: flex;
            color: #f1c40f;
        }
        
        .dive-counts {
            display: flex;
            gap: 10px;
        }
        
        .dive-count {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: #555;
        }
        
        .dive-actions {
            white-space: nowrap;
        }
        
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            margin: 0 2px;
            border-radius: 4px;
            color: #2196F3;
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            background-color: #e3f2fd;
            color: #0d6efd;
        }
        
        .action-btn.edit {
            color: #4CAF50;
        }
        
        .action-btn.edit:hover {
            background-color: #e8f5e9;
            color: #388E3C;
        }
        
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
        }
        
        .diving-row {
            border-left: 4px solid #2196F3;
        }
        
        .activity-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            color: white;
            margin-right: 5px;
            font-weight: 600;
        }
        
        .diving-badge {
            background-color: #2196F3;
        }
        
        .location-info {
            line-height: 1.4;
        }
        
        .location-name {
            font-weight: 500;
        }
        
        .badge-container {
            margin-top: 4px;
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        
        .detail-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            background-color: #f1f1f1;
            color: #555;
        }
        
        @media (max-width: 991px) {
            .dive-table .optional-column {
                display: none;
            }
            
            .stat-card {
                min-width: 120px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navigation.php'; ?>
    
    <div class="dive-list-container">
        <h1 class="text-center my-4">Dive Log Entries</h1>
        
        <!-- Statistics Section -->
        <div class="dive-stats">
            <div class="stat-card">
                <div class="stat-value"><?php echo $totalDives; ?></div>
                <div class="stat-label">Total Dives</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $maxDepth; ?>m</div>
                <div class="stat-label">Maximum Depth</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $avgDuration; ?>min</div>
                <div class="stat-label">Average Duration</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $totalFishSightings; ?></div>
                <div class="stat-label">Fish Sightings</div>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <div class="filter-label">Activity:</div>
                <div class="activity-filter">
                    <a href="?activity=all&year=<?php echo $yearFilter; ?><?php echo !empty($searchTerm) ? '&search='.urlencode($searchTerm) : ''; ?>" class="filter-btn active">
                        Diving
                    </a>
                </div>
            </div>
            
            <?php if (count($years) > 1): ?>
            <div class="filter-label ms-3">Year:</div>
            <div class="year-filter">
                <a href="?activity=<?php echo $activityFilter; ?>&year=all<?php echo !empty($searchTerm) ? '&search='.urlencode($searchTerm) : ''; ?>" class="filter-btn <?php echo $yearFilter === 'all' ? 'active' : ''; ?>">
                    All Years
                </a>
                <?php foreach ($years as $year): ?>
                <a href="?activity=<?php echo $activityFilter; ?>&year=<?php echo $year; ?><?php echo !empty($searchTerm) ? '&search='.urlencode($searchTerm) : ''; ?>" class="filter-btn <?php echo $yearFilter === (string)$year ? 'active' : ''; ?>">
                    <?php echo $year; ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <form method="GET" action="" class="search-form">
                <?php if ($activityFilter !== 'all'): ?>
                <input type="hidden" name="activity" value="<?php echo htmlspecialchars($activityFilter); ?>">
                <?php endif; ?>
                <?php if ($yearFilter !== 'all'): ?>
                <input type="hidden" name="year" value="<?php echo htmlspecialchars($yearFilter); ?>">
                <?php endif; ?>
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Search logs..." 
                    value="<?php echo htmlspecialchars($searchTerm); ?>"
                >
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
        
        <?php if ($searchResults): ?>
        <div class="alert alert-info">
            Found <?php echo count($diveRecords); ?> results for "<?php echo htmlspecialchars($searchTerm); ?>"
            <a href="?activity=<?php echo $activityFilter; ?>&year=<?php echo $yearFilter; ?>" class="float-end">Clear Search</a>
        </div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="dive-table">
                <thead>
                    <tr>
                        <th style="width: 120px;">Date</th>
                        <th>Location</th>
                        <th class="optional-column">Details</th>
                        <th style="width: 120px;">Depth/Time</th>
                        <th style="width: 120px;">Content</th>
                        <th style="width: 80px;">Rating</th>
                        <th style="width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($diveRecords)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">No dive logs found.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($diveRecords as $dive): 
                            $isDiving = $dive['activity_type'] !== 'snorkeling';
                            $diveDate = strtotime($dive['date']);
                        ?>
                        <tr class="<?php echo $isDiving ? 'diving-row' : 'snorkeling-row'; ?>">
                            <td class="dive-date">
                                <div class="dive-date-day"><?php echo date('d M', $diveDate); ?></div>
                                <div class="dive-date-year"><?php echo date('Y', $diveDate); ?></div>
                                <div class="mt-1">
                                    <span class="activity-badge <?php echo $isDiving ? 'diving-badge' : 'snorkeling-badge'; ?>">
                                        <?php echo $isDiving ? 'Dive' : 'Snorkel'; ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="location-info">
                                    <div class="location-name"><?php echo htmlspecialchars($dive['location']); ?></div>
                                    <div class="badge-container">
                                        <?php if (!empty($dive['dive_site_type'])): ?>
                                        <span class="detail-badge"><?php echo htmlspecialchars($dive['dive_site_type']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($dive['buddy'])): ?>
                                        <span class="detail-badge">With: <?php echo htmlspecialchars($dive['buddy']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="optional-column" style="font-size: 13px;">
                                <?php if (!empty($dive['description'])): ?>
                                <div class="text-muted" style="max-width: 350px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars(substr($dive['description'], 0, 100)); ?>
                                    <?php echo strlen($dive['description']) > 100 ? '...' : ''; ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($dive['visibility']) || !empty($dive['temperature']) || !empty($dive['air_temperature'])): ?>
                                <div class="badge-container mt-1">
                                    <?php if (!empty($dive['visibility'])): ?>
                                    <span class="detail-badge"><i class="fas fa-eye fa-sm"></i> <?php echo $dive['visibility']; ?>m</span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($dive['temperature'])): ?>
                                    <span class="detail-badge"><i class="fas fa-water fa-sm"></i> <?php echo $dive['temperature']; ?>°C</span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($dive['air_temperature'])): ?>
                                    <span class="detail-badge"><i class="fas fa-sun fa-sm"></i> <?php echo $dive['air_temperature']; ?>°C</span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isDiving && !empty($dive['depth'])): ?>
                                <div><i class="fas fa-arrow-down fa-sm text-primary"></i> <strong><?php echo $dive['depth']; ?> m</strong></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($dive['duration'])): ?>
                                <div><i class="fas fa-clock fa-sm text-muted"></i> <?php echo $dive['duration']; ?> min</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="dive-counts">
                                    <?php if (!empty($dive['fish_count'])): ?>
                                    <div class="dive-count">
                                        <i class="fas fa-fish text-primary"></i>
                                        <span><?php echo $dive['fish_count']; ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($dive['image_count'])): ?>
                                    <div class="dive-count">
                                        <i class="fas fa-camera text-success"></i>
                                        <span><?php echo $dive['image_count']; ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="dive-rating">
                                    <?php 
                                    if (!empty($dive['rating'])) {
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $dive['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                        }
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                    ?>
                                </div>
                            </td>
                            <td class="dive-actions">
                                <a href="view_dive.php?id=<?php echo $dive['id']; ?>" class="action-btn" title="View Dive Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_dive.php?id=<?php echo $dive['id']; ?>" class="action-btn edit" title="Edit Dive">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="#" class="action-btn" title="View on Map" onclick="showOnMap(<?php echo $dive['latitude']; ?>, <?php echo $dive['longitude']; ?>, '<?php echo addslashes($dive['location']); ?>')">
                                    <i class="fas fa-map-marker-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showOnMap(lat, lng, title) {
            // Redirect to the map page with the coordinates as parameters
            window.location.href = 'index.php?lat=' + lat + '&lng=' + lng + '&title=' + encodeURIComponent(title);
            return false;
        }
    </script>
</body>
</html> 