<?php
// Include helper functions and database connection
require_once 'divelog_functions.php';
require_once 'db.php';

// ==========================================
// DATABASE OPERATIONS
// ==========================================

// Initialize database and get status
$dbStatus = initDatabaseTables();
$tableExists = $dbStatus['tableExists'];
$recordCount = $dbStatus['recordCount'];

// Display initialization messages
foreach ($dbStatus['messages'] as $message) {
    echo $message;
}

// Fetch entry for editing if in edit mode
$editEntry = null;
$diveImages = [];
$diveFishSightings = [];
$editMode = false;

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
        // Fetch fish sightings for this dive log
        $diveFishSightings = getDiveFishSightings($id);
        $editMode = true;
    }
    $stmt->close();
}

// Get all fish species for the dropdown
$allFishSpecies = getAllFishSpecies();

// Fetch all dive logs for the table view
$allEntries = [];
if ($tableExists) {
    $result = $conn->query("SELECT * FROM divelogs ORDER BY date DESC");
    while ($row = $result->fetch_assoc()) {
        $allEntries[] = $row;
    }
}

// ==========================================
// FORM SUBMISSION HANDLERS
// ==========================================

// Handle sample data population
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
    $diveTime = !empty($_POST['dive_time']) ? $_POST['dive_time'] : null;
    
    $stmt = $conn->prepare("INSERT INTO divelogs (location, latitude, longitude, date, dive_time, description, depth, duration, temperature, air_temperature, visibility, buddy, dive_site_type, rating, comments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sddsssddddissss", $location, $latitude, $longitude, $date, $diveTime, $description, $depth, $duration, $temperature, $airTemperature, $visibility, $buddy, $diveSiteType, $rating, $comments);
    
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
        if (!empty($_FILES['images']['name'][0]) || !empty($_FILES['logbook_images']['name'][0])) {
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

// Handle adding a new fish sighting to a dive
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_fish_sighting') {
    $divelogId = $_POST['divelog_id'];
    $fishSpeciesId = $_POST['fish_species_id'];
    $sightingDate = $_POST['sighting_date'];
    $quantity = $_POST['quantity'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO fish_sightings (divelog_id, fish_species_id, sighting_date, quantity, notes) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $divelogId, $fishSpeciesId, $sightingDate, $quantity, $notes);
    
    if ($stmt->execute()) {
        echo "<div class='success'>Fish sighting added successfully.</div>";
    } else {
        echo "<div class='error'>Error adding fish sighting: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// Handle deleting a fish sighting
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_fish_sighting') {
    $sightingId = $_POST['sighting_id'];
    $divelogId = $_POST['divelog_id'];
    
    $stmt = $conn->prepare("DELETE FROM fish_sightings WHERE id = ? AND divelog_id = ?");
    $stmt->bind_param("ii", $sightingId, $divelogId);
    
    if ($stmt->execute()) {
        echo "<div class='success'>Fish sighting removed successfully.</div>";
    } else {
        echo "<div class='error'>Error removing fish sighting: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// Handle clearing all data
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'clear') {
    $conn->query("TRUNCATE TABLE divelogs");
    echo "<div class='success'>All dive logs have been cleared.</div>";
}

// ==========================================
// HTML OUTPUT
// ==========================================
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dive Log Management</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navigation.php'; ?>
    
    <div class="container">
        <h1>Dive Log Management</h1>
        
        <!-- OCR Import Section -->
        <?php include 'ocr_import_section.php'; ?>
        
        <!-- Database Status -->
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
                                    <input type="number" name="latitude" placeholder="Latitude" step="any" required>
                                    <input type="number" name="longitude" placeholder="Longitude" step="any" required>
                                </div>
                            </td>
                            <td>
                                <input type="date" name="date" required>
                                <input type="time" name="dive_time" step="300">
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

        <?php if (isset($editEntry)): ?>
        <div class="container">
            <h2>Edit Dive Log Entry</h2>
            <?php include 'edit_dive_form.php'; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
<script>
    // Tab functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Handle tab switching
        var tabButtons = document.querySelectorAll('.tab-button');
        tabButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                // Remove active class from all buttons and content
                document.querySelectorAll('.tab-button').forEach(function(btn) {
                    btn.classList.remove('active');
                });
                document.querySelectorAll('.tab-content').forEach(function(content) {
                    content.classList.remove('active');
                });
                
                // Add active class to clicked button and corresponding content
                button.classList.add('active');
                var tabId = button.getAttribute('data-tab') + '-tab';
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // Handle file input change to show selected file count
        document.querySelectorAll('.file-input').forEach(function(input) {
            input.addEventListener('change', function() {
                var countElement = document.getElementById(this.id + '-count');
                if (countElement) {
                    var fileCount = this.files.length;
                    countElement.textContent = fileCount + ' file' + (fileCount !== 1 ? 's' : '') + ' selected';
                }
            });
        });
        
        // Handle delete image buttons
        document.querySelectorAll('.delete-image-btn').forEach(function(button) {
            button.addEventListener('click', function() {
                if (confirm('Are you sure you want to delete this image?')) {
                    var imageId = this.getAttribute('data-image-id');
                    var divelogId = document.querySelector('input[name="id"]').value;
                    
                    // Create form data
                    var formData = new FormData();
                    formData.append('action', 'delete_image');
                    formData.append('image_id', imageId);
                    formData.append('divelog_id', divelogId);
                    
                    // Send delete request
                    fetch('populate_db.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => window.location.reload())
                    .catch(error => console.error('Error:', error));
                }
            });
        });
    });
</script>
</html>