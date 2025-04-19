<?php
// Include database connection
include 'db.php';

// Initialize search variables
$searchTerm = '';
$searchResults = false;
$yearFilter = isset($_GET['year']) ? $_GET['year'] : 'all';
$sortField = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'desc';

// Validate sort field to prevent SQL injection
$allowedSortFields = ['date', 'location', 'depth', 'duration', 'rating'];
if (!in_array($sortField, $allowedSortFields)) {
    $sortField = 'date';
}

// Validate sort order
if ($sortOrder !== 'asc' && $sortOrder !== 'desc') {
    $sortOrder = 'desc';
}

// Handle search request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    $searchResults = true;
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_dive' && isset($_POST['dive_id'])) {
    $diveId = intval($_POST['dive_id']);
    
    // Start transaction to ensure all related data is deleted
    $conn->begin_transaction();
    
    try {
        // Delete related fish sightings
        $stmt = $conn->prepare("DELETE FROM fish_sightings WHERE divelog_id = ?");
        $stmt->bind_param("i", $diveId);
        $stmt->execute();
        
        // Delete related images
        $stmt = $conn->prepare("DELETE FROM divelog_images WHERE divelog_id = ?");
        $stmt->bind_param("i", $diveId);
        $stmt->execute();
        
        // Delete the dive log entry
        $stmt = $conn->prepare("DELETE FROM divelogs WHERE id = ?");
        $stmt->bind_param("i", $diveId);
        $stmt->execute();
        
        // Commit the transaction
        $conn->commit();
        
        // Redirect to prevent form resubmission - add cache busting parameter
        header("Location: divelist.php?year={$yearFilter}&message=deleted&cache_bust=" . time());
        exit;
    } catch (Exception $e) {
        // Rollback the transaction if an error occurs
        $conn->rollback();
        $error_message = "Error deleting dive log: " . $e->getMessage();
    }
}

// Check for success message
$message = isset($_GET['message']) ? $_GET['message'] : '';

// Get unique fish species count
$fishSpeciesQuery = "SELECT COUNT(DISTINCT fish_species_id) as unique_species FROM fish_sightings";
$fishSpeciesResult = $conn->query($fishSpeciesQuery);
$uniqueFishSpecies = 0;
if ($fishSpeciesResult && $fishSpeciesResult->num_rows > 0) {
    $uniqueFishSpecies = $fishSpeciesResult->fetch_assoc()['unique_species'];
}

// Fetch dive logs from the database
$query = "SELECT d.*, 
                 (SELECT COUNT(DISTINCT fish_species_id) FROM fish_sightings WHERE divelog_id = d.id) as fish_count,
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

// Add sorting
$query .= " ORDER BY d.{$sortField} {$sortOrder}";

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
$diveRecords = [];
$years = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $totalDives++;
        
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
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2196F3;
            margin-bottom: 5px;
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
        
        .year-filter {
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
            color: #fff;
            border-color: #2196F3;
            font-weight: 500;
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
        
        .fish-count-link {
            padding: 3px 8px;
            border-radius: 12px;
            transition: background-color 0.2s;
            background-color: #e6f7ff;
            border: 1px solid #b3e0ff;
        }
        
        .fish-count-link:hover {
            background-color: #b3e0ff;
            color: #0055b3;
            box-shadow: 0 0 4px rgba(0, 123, 255, 0.3);
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
        
        /* Styles for fish sightings modal */
        .fish-card {
            transition: transform 0.2s;
            margin-bottom: 1rem;
        }
        
        .fish-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .fish-img-container {
            height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
        }
        
        .fish-img {
            max-height: 130px;
            max-width: 100%;
            object-fit: contain;
        }
        
        .fish-icon {
            font-size: 3.5rem;
            color: #adb5bd;
        }
        
        .fish-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
            font-size: 0.95rem;
        }
        
        .fish-scientific {
            font-style: italic;
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }
        
        .fish-details {
            font-size: 0.85rem;
            color: #555;
        }
        
        /* Highlight fish count more prominently */
        .fish-count-link .fa-fish {
            font-size: 1.1rem;
        }
        
        .fish-count-link span {
            font-weight: bold;
        }
        
        /* Add styles for sortable headers */
        th.sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
            padding-right: 20px !important;
        }
        
        th.sortable:hover {
            background-color: #f0f0f0;
        }
        
        th.sortable::after {
            content: '⇕';
            position: absolute;
            right: 6px;
            color: #aaa;
        }
        
        th.sortable.sort-asc::after {
            content: '↑';
            color: #2196F3;
        }
        
        th.sortable.sort-desc::after {
            content: '↓';
            color: #2196F3;
        }
        
        /* Style for delete button */
        .action-btn.delete {
            color: #F44336;
        }
        
        .action-btn.delete:hover {
            background-color: #FFEBEE;
            color: #D32F2F;
        }
        
        /* Success message */
        .alert-floating {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            animation: fadeInOut 5s forwards;
        }
        
        @keyframes fadeInOut {
            0% { opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { opacity: 0; display: none; }
        }
    </style>
</head>
<body>
    <?php include 'navigation.php'; ?>
    
    <div class="dive-list-container">
        <h1 class="text-center my-4">Dive Log Entries</h1>
        
        <?php if ($message === 'deleted'): ?>
        <div class="alert alert-success alert-floating">
            Dive log entry successfully deleted!
        </div>
        <?php endif; ?>
        
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
                <div class="stat-value"><?php echo $uniqueFishSpecies; ?></div>
                <div class="stat-label">Unique Fish Species</div>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <?php if (count($years) > 1): ?>
            <div class="filter-label">Year:</div>
            <div class="year-filter">
                <a href="?year=all<?php echo !empty($searchTerm) ? '&search='.urlencode($searchTerm) : ''; ?>" class="filter-btn <?php echo $yearFilter === 'all' ? 'active' : ''; ?>">
                    All Years
                </a>
                <?php foreach ($years as $year): ?>
                <a href="?year=<?php echo $year; ?><?php echo !empty($searchTerm) ? '&search='.urlencode($searchTerm) : ''; ?>" class="filter-btn <?php echo $yearFilter === (string)$year ? 'active' : ''; ?>">
                    <?php echo $year; ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <form method="GET" action="" class="search-form">
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
            <a href="?year=<?php echo $yearFilter; ?>" class="float-end">Clear Search</a>
        </div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="dive-table">
                <thead>
                    <tr>
                        <th class="sortable <?php echo $sortField === 'date' ? 'sort-'.$sortOrder : ''; ?>" onclick="sortTable('date')">Date</th>
                        <th class="sortable <?php echo $sortField === 'location' ? 'sort-'.$sortOrder : ''; ?>" onclick="sortTable('location')">Location</th>
                        <th class="optional-column">Details</th>
                        <th class="sortable <?php echo $sortField === 'depth' ? 'sort-'.$sortOrder : ''; ?>" onclick="sortTable('depth')">Depth/Time</th>
                        <th>Content</th>
                        <th class="sortable <?php echo $sortField === 'rating' ? 'sort-'.$sortOrder : ''; ?>" onclick="sortTable('rating')">Rating</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($diveRecords)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">No dive logs found.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($diveRecords as $dive): 
                            $diveDate = strtotime($dive['date']);
                        ?>
                        <tr>
                            <td class="dive-date">
                                <div class="dive-date-day"><?php echo date('d M', $diveDate); ?></div>
                                <div class="dive-date-year"><?php echo date('Y', $diveDate); ?></div>
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
                                <?php if (!empty($dive['depth'])): ?>
                                <div><i class="fas fa-arrow-down fa-sm text-primary"></i> <strong><?php echo $dive['depth']; ?> m</strong></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($dive['duration'])): ?>
                                <div><i class="fas fa-clock fa-sm text-muted"></i> <?php echo $dive['duration']; ?> min</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="dive-counts">
                                    <?php if (!empty($dive['fish_count'])): ?>
                                    <div class="dive-count fish-count-link" onclick="loadFishSightings(<?php echo $dive['id']; ?>, '<?php echo addslashes($dive['location']); ?>')" style="cursor: pointer;" title="Click to view fish species">
                                        <i class="fas fa-fish text-primary"></i>
                                        <span><?php echo $dive['fish_count']; ?> <?php echo $dive['fish_count'] > 1 ? 'species' : 'species'; ?></span>
                                        <i class="fas fa-eye ms-1" style="font-size: 0.8rem; opacity: 0.7;"></i>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($dive['image_count'])): ?>
                                    <div class="dive-count">
                                        <i class="fas fa-camera text-success"></i>
                                        <span><?php echo $dive['image_count']; ?> <?php echo $dive['image_count'] > 1 ? 'photos' : 'photo'; ?></span>
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
                                <a href="#" class="action-btn delete" title="Delete Dive" onclick="confirmDelete(<?php echo $dive['id']; ?>, '<?php echo addslashes($dive['location']); ?>')">
                                    <i class="fas fa-trash-alt"></i>
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
        
        // Function to sort the table
        function sortTable(field) {
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            
            // Determine sort order (toggle or default to desc)
            let order = 'desc';
            if (urlParams.get('sort') === field && urlParams.get('order') === 'desc') {
                order = 'asc';
            }
            
            // Set sort parameters
            urlParams.set('sort', field);
            urlParams.set('order', order);
            
            // Redirect to the same page with updated sort parameters
            window.location.href = '?' + urlParams.toString();
        }
        
        // Function to confirm delete
        function confirmDelete(diveId, location) {
            if (confirm(`Are you sure you want to delete the dive at "${location}"?\n\nThis will permanently delete the dive log and all associated fish sightings and images. This action cannot be undone.`)) {
                // Create and submit a form to delete the dive
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'divelist.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_dive';
                
                const diveIdInput = document.createElement('input');
                diveIdInput.type = 'hidden';
                diveIdInput.name = 'dive_id';
                diveIdInput.value = diveId;
                
                form.appendChild(actionInput);
                form.appendChild(diveIdInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Automatically hide the success message after 5 seconds
        setTimeout(function() {
            const successAlert = document.querySelector('.alert-floating');
            if (successAlert) {
                successAlert.style.display = 'none';
            }
        }, 5000);

        // Function to load fish sightings for a specific dive
        function loadFishSightings(diveId, diveName) {
            // Fetch fish sightings data via AJAX
            fetch('get_fish_sightings.php?dive_id=' + diveId)
                .then(response => response.json())
                .then(data => {
                    // Update modal title
                    document.getElementById('fishModalLabel').textContent = 'Fish Sightings - ' + diveName;
                    
                    // Get the container for fish sightings
                    const fishContainer = document.getElementById('fishSightingsContainer');
                    fishContainer.innerHTML = '';
                    
                    if (data.length === 0) {
                        fishContainer.innerHTML = '<p class="text-center">No fish sightings recorded for this dive.</p>';
                        return;
                    }
                    
                    // Create HTML for each fish sighting
                    let fishHTML = '<div class="row">';
                    data.forEach(fish => {
                        fishHTML += `
                            <div class="col-md-4 col-sm-6 mb-3">
                                <div class="card h-100 fish-card">
                                    <div class="fish-img-container">
                                        ${fish.image_url ? 
                                            `<img src="${fish.image_url}" alt="${fish.common_name}" class="fish-img">` : 
                                            `<div class="fish-icon"><i class="fas fa-fish"></i></div>`
                                        }
                                    </div>
                                    <div class="card-body">
                                        <h5 class="fish-name">${fish.common_name || 'Unknown Fish'}</h5>
                                        ${fish.scientific_name ? `<p class="fish-scientific">${fish.scientific_name}</p>` : ''}
                                        <div class="fish-details">
                                            ${fish.quantity ? `<p class="mb-1"><i class="fas fa-hashtag fa-sm me-1"></i> ${fish.quantity}</p>` : ''}
                                            ${fish.notes ? `<p class="mb-0"><i class="fas fa-comment-alt fa-sm me-1"></i> ${fish.notes}</p>` : ''}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    fishHTML += '</div>';
                    
                    fishContainer.innerHTML = fishHTML;
                    
                    // Show the modal
                    const fishModal = new bootstrap.Modal(document.getElementById('fishModal'));
                    fishModal.show();
                })
                .catch(error => {
                    console.error('Error fetching fish sightings:', error);
                    alert('Failed to load fish sightings. Please try again.');
                });
        }
    </script>

    <!-- Fish Sightings Modal -->
    <div class="modal fade" id="fishModal" tabindex="-1" aria-labelledby="fishModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="fishModalLabel">Fish Sightings</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div id="fishSightingsContainer">
                        <!-- Fish sightings will be loaded here -->
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading fish sightings...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 