<?php
// Include database connection
include 'db.php';

// Function to get fish images
function getFishImages($fishSpeciesId) {
    global $conn;
    $images = [];
    
    $stmt = $conn->prepare("SELECT * FROM fish_images WHERE fish_species_id = ? ORDER BY is_primary DESC, upload_date DESC");
    $stmt->bind_param("i", $fishSpeciesId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $images[] = $row;
    }
    
    $stmt->close();
    return $images;
}

// Function to search for fish species on FishBase API
function searchFishBase($searchTerm) {
    $searchTerm = urlencode($searchTerm);
    $url = "https://fishbase.ropensci.org/species?sciname=" . $searchTerm;
    
    $options = [
        'http' => [
            'header' => "User-Agent: DiveLogApp/1.0\r\n"
        ]
    ];
    
    $context = stream_context_create($options);
    
    // Make the HTTP request
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return ['success' => false, 'message' => 'Could not connect to FishBase API'];
    }
    
    // Parse the JSON response
    $data = json_decode($response, true);
    
    if (isset($data['data']) && !empty($data['data'])) {
        return ['success' => true, 'results' => $data['data']];
    } else {
        return ['success' => false, 'message' => 'No results found'];
    }
}

// Function to download an image from a URL
function downloadImageFromUrl($url, $fishSpeciesId) {
    global $conn;
    
    // Create a unique filename
    $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
    if (empty($extension)) {
        $extension = 'jpg'; // Default to jpg if no extension found
    }
    
    $filename = $fishSpeciesId . '_' . uniqid() . '.' . $extension;
    $destination = 'uploads/fishimages/' . $filename;
    
    // Make sure the uploads directory exists
    if (!file_exists('uploads/fishimages')) {
        mkdir('uploads/fishimages', 0755, true);
    }
    
    // Download the image
    $imageData = @file_get_contents($url);
    if ($imageData === false) {
        return ['success' => false, 'message' => 'Could not download image from URL'];
    }
    
    // Save the image
    if (file_put_contents($destination, $imageData)) {
        // Add to database
        $stmt = $conn->prepare("INSERT INTO fish_images (fish_species_id, filename, source_url, source_name) VALUES (?, ?, ?, ?)");
        $sourceName = "Web";
        $stmt->bind_param("isss", $fishSpeciesId, $filename, $url, $sourceName);
        
        if ($stmt->execute()) {
            $imageId = $stmt->insert_id;
            
            // Check if this is the first image for this species
            $checkStmt = $conn->prepare("SELECT COUNT(*) AS count FROM fish_images WHERE fish_species_id = ?");
            $checkStmt->bind_param("i", $fishSpeciesId);
            $checkStmt->execute();
            $countResult = $checkStmt->get_result()->fetch_assoc();
            
            // If it's the first image, make it primary
            if ($countResult['count'] == 1) {
                $updateStmt = $conn->prepare("UPDATE fish_images SET is_primary = 1 WHERE id = ?");
                $updateStmt->bind_param("i", $imageId);
                $updateStmt->execute();
            }
            
            return ['success' => true, 'filename' => $filename, 'id' => $imageId];
        } else {
            return ['success' => false, 'message' => 'Error saving image to database: ' . $stmt->error];
        }
    } else {
        return ['success' => false, 'message' => 'Could not save image file'];
    }
}

// Handle adding a new fish species
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_species') {
    $commonName = $_POST['common_name'];
    $scientificName = $_POST['scientific_name'];
    $description = $_POST['description'] ?? '';
    $habitat = $_POST['habitat'] ?? '';
    $sizeRange = $_POST['size_range'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO fish_species (common_name, scientific_name, description, habitat, size_range) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $commonName, $scientificName, $description, $habitat, $sizeRange);
    
    if ($stmt->execute()) {
        $fishSpeciesId = $stmt->insert_id;
        echo "<div class='success'>Fish species added successfully.</div>";
        
        // Handle image uploads if present
        if (!empty($_FILES['fish_images']['name'][0])) {
            $uploadResult = uploadFishImages($fishSpeciesId);
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
        
        // Handle image URL if provided
        if (!empty($_POST['image_url'])) {
            $imageUrlResult = downloadImageFromUrl($_POST['image_url'], $fishSpeciesId);
            if ($imageUrlResult['success']) {
                echo "<div class='success'>Successfully downloaded image from URL.</div>";
            } else {
                echo "<div class='error'>" . $imageUrlResult['message'] . "</div>";
            }
        }
    } else {
        echo "<div class='error'>Error adding fish species: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// Handle adding a new fish sighting
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_sighting') {
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

// Handle fish image uploads
function uploadFishImages($fishSpeciesId) {
    global $conn;
    $uploadedFiles = [];
    $errors = [];
    $uploadsDir = 'uploads/fishimages';
    
    // Make sure the uploads directory exists
    if (!file_exists($uploadsDir)) {
        if (!mkdir($uploadsDir, 0755, true)) {
            return ['success' => false, 'message' => 'Failed to create uploads directory.'];
        }
    }
    
    // Check if files were uploaded
    if (!empty($_FILES['fish_images']['name'][0])) {
        $fileCount = count($_FILES['fish_images']['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['fish_images']['error'][$i] === UPLOAD_ERR_OK) {
                $tempName = $_FILES['fish_images']['tmp_name'][$i];
                $originalName = $_FILES['fish_images']['name'][$i];
                $fileSize = $_FILES['fish_images']['size'][$i];
                $fileType = $_FILES['fish_images']['type'][$i];
                
                // Validate file type
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($fileType, $allowedTypes)) {
                    $errors[] = "File '$originalName' is not an allowed image type. Only JPG, PNG, GIF, and WEBP are supported.";
                    continue;
                }
                
                // Validate file size (max 5MB)
                $maxSize = 5 * 1024 * 1024; // 5MB
                if ($fileSize > $maxSize) {
                    $errors[] = "File '$originalName' exceeds the maximum allowed size of 5MB.";
                    continue;
                }
                
                // Generate a unique filename
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                $newFilename = $fishSpeciesId . '_' . uniqid() . '.' . $extension;
                $destination = $uploadsDir . '/' . $newFilename;
                
                // Move the uploaded file
                if (move_uploaded_file($tempName, $destination)) {
                    // Add to database
                    $stmt = $conn->prepare("INSERT INTO fish_images (fish_species_id, filename, original_filename, file_size, file_type, source_name) VALUES (?, ?, ?, ?, ?, ?)");
                    $sourceName = "Personal";
                    $stmt->bind_param("isssss", $fishSpeciesId, $newFilename, $originalName, $fileSize, $fileType, $sourceName);
                    
                    if ($stmt->execute()) {
                        $imageId = $stmt->insert_id;
                        
                        // Check if this is the first image for this species
                        $checkStmt = $conn->prepare("SELECT COUNT(*) AS count FROM fish_images WHERE fish_species_id = ?");
                        $checkStmt->bind_param("i", $fishSpeciesId);
                        $checkStmt->execute();
                        $countResult = $checkStmt->get_result()->fetch_assoc();
                        
                        // If it's the first image, make it primary
                        if ($countResult['count'] == 1) {
                            $updateStmt = $conn->prepare("UPDATE fish_images SET is_primary = 1 WHERE id = ?");
                            $updateStmt->bind_param("i", $imageId);
                            $updateStmt->execute();
                        }
                        
                        $uploadedFiles[] = [
                            'id' => $imageId,
                            'filename' => $newFilename,
                            'original_name' => $originalName
                        ];
                    } else {
                        $errors[] = "Database error for '$originalName': " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Failed to save '$originalName'.";
                }
            } else {
                $uploadError = $_FILES['fish_images']['error'][$i];
                $errors[] = "Upload error for file #" . ($i + 1) . ": " . $uploadError;
            }
        }
    }
    
    return [
        'success' => !empty($uploadedFiles),
        'uploaded' => $uploadedFiles,
        'errors' => $errors
    ];
}

// Fetch all fish species for display
$allFishSpecies = [];
$fishSpeciesQuery = "SELECT * FROM fish_species ORDER BY common_name";
$fishSpeciesResult = $conn->query($fishSpeciesQuery);
if ($fishSpeciesResult && $fishSpeciesResult->num_rows > 0) {
    while ($row = $fishSpeciesResult->fetch_assoc()) {
        // Get images for this fish
        $row['images'] = getFishImages($row['id']);
        
        // Get count of sightings for this fish
        $sightingCountQuery = "SELECT COUNT(*) as count FROM fish_sightings WHERE fish_species_id = " . $row['id'];
        $sightingCountResult = $conn->query($sightingCountQuery);
        $sightingCountRow = $sightingCountResult->fetch_assoc();
        $row['sighting_count'] = $sightingCountRow['count'];
        
        $allFishSpecies[] = $row;
    }
}

// Fetch all dive logs for selecting when adding sightings
$allDiveLogs = [];
$diveLogsQuery = "SELECT id, location, date FROM divelogs ORDER BY date DESC";
$diveLogsResult = $conn->query($diveLogsQuery);
if ($diveLogsResult && $diveLogsResult->num_rows > 0) {
    while ($row = $diveLogsResult->fetch_assoc()) {
        $allDiveLogs[] = $row;
    }
}

// Handle search for fish species
$searchResults = null;
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'search_fish' && !empty($_GET['search_term'])) {
    $searchTerm = $_GET['search_term'];
    $searchResults = searchFishBase($searchTerm);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fish Species Manager</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .fish-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .fish-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .fish-image {
            height: 200px;
            width: 100%;
            object-fit: cover;
            background-color: #f5f5f5;
        }
        .fish-details {
            padding: 15px;
        }
        .fish-name {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 5px 0;
        }
        .fish-scientific {
            font-style: italic;
            color: #666;
            margin: 0 0 10px 0;
        }
        .fish-sightings {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .search-results {
            margin-top: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
        }
        .result-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .result-item:last-child {
            border-bottom: none;
        }
        .tabs {
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .tab {
            display: inline-block;
            padding: 10px 20px;
            cursor: pointer;
            border: 1px solid transparent;
            border-bottom: none;
            margin-bottom: -1px;
        }
        .tab.active {
            border-color: #ddd;
            border-bottom-color: white;
            background-color: white;
            font-weight: bold;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <nav class="menu">
        <a href="index.php">View Dive Log</a>
        <a href="populate_db.php">Manage Dives</a>
        <a href="fish_manager.php" class="active">Fish Species</a>
        <a href="manage_db.php">Manage Database</a>
        <a href="import.php">Import</a>
    </nav>
    
    <h1>Fish Species Manager</h1>
    
    <div class="tabs">
        <div class="tab active" data-tab="all-fish">All Fish</div>
        <div class="tab" data-tab="add-species">Add New Species</div>
        <div class="tab" data-tab="add-sighting">Record Sighting</div>
        <div class="tab" data-tab="search-fish">Search Fish Database</div>
    </div>
    
    <!-- All Fish Tab -->
    <div class="tab-content active" id="all-fish">
        <h2>All Fish Species (<?php echo count($allFishSpecies); ?>)</h2>
        
        <?php if (empty($allFishSpecies)): ?>
            <p>No fish species have been added yet. Use the "Add New Species" tab to add fish.</p>
        <?php else: ?>
            <div class="fish-grid">
                <?php foreach ($allFishSpecies as $fish): ?>
                    <div class="fish-card">
                        <?php if (!empty($fish['images'])): ?>
                            <img src="uploads/fishimages/<?php echo $fish['images'][0]['filename']; ?>" alt="<?php echo htmlspecialchars($fish['common_name']); ?>" class="fish-image">
                        <?php else: ?>
                            <div class="fish-image" style="display: flex; align-items: center; justify-content: center; background-color: #eee;">
                                <span>No Image</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="fish-details">
                            <h3 class="fish-name"><?php echo htmlspecialchars($fish['common_name']); ?></h3>
                            <p class="fish-scientific"><?php echo htmlspecialchars($fish['scientific_name']); ?></p>
                            
                            <?php if ($fish['sighting_count'] > 0): ?>
                                <div class="fish-sightings">Spotted <?php echo $fish['sighting_count']; ?> time<?php echo $fish['sighting_count'] > 1 ? 's' : ''; ?></div>
                            <?php else: ?>
                                <div class="fish-sightings" style="background-color: #999;">Not yet spotted</div>
                            <?php endif; ?>
                            
                            <p><?php echo !empty($fish['description']) ? htmlspecialchars(substr($fish['description'], 0, 100)) . '...' : 'No description available.'; ?></p>
                            
                            <a href="fish_details.php?id=<?php echo $fish['id']; ?>" class="btn">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Add New Species Tab -->
    <div class="tab-content" id="add-species">
        <h2>Add New Fish Species</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_species">
            
            <div class="form-group">
                <label for="common_name">Common Name:</label>
                <input type="text" id="common_name" name="common_name" required>
            </div>
            
            <div class="form-group">
                <label for="scientific_name">Scientific Name:</label>
                <input type="text" id="scientific_name" name="scientific_name" placeholder="Genus species">
            </div>
            
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description" rows="4"></textarea>
            </div>
            
            <div class="form-row-container">
                <div class="form-row">
                    <div class="form-group half">
                        <label for="habitat">Typical Habitat:</label>
                        <input type="text" id="habitat" name="habitat">
                    </div>
                    
                    <div class="form-group half">
                        <label for="size_range">Size Range:</label>
                        <input type="text" id="size_range" name="size_range" placeholder="e.g., 10-15 cm">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="fish_images">Upload Images:</label>
                <input type="file" id="fish_images" name="fish_images[]" multiple accept="image/*">
            </div>
            
            <div class="form-group">
                <label for="image_url">Or Import Image from URL:</label>
                <input type="url" id="image_url" name="image_url" placeholder="https://...">
            </div>
            
            <button type="submit" class="btn">Add Fish Species</button>
        </form>
    </div>
    
    <!-- Add Sighting Tab -->
    <div class="tab-content" id="add-sighting">
        <h2>Record Fish Sighting</h2>
        
        <?php if (empty($allFishSpecies)): ?>
            <p>No fish species have been added yet. Please add fish species first.</p>
        <?php elseif (empty($allDiveLogs)): ?>
            <p>No dive logs have been recorded yet. Please add dive logs first.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="add_sighting">
                
                <div class="form-group">
                    <label for="divelog_id">Select Dive:</label>
                    <select id="divelog_id" name="divelog_id" required>
                        <option value="">-- Select Dive --</option>
                        <?php foreach ($allDiveLogs as $dive): ?>
                            <option value="<?php echo $dive['id']; ?>">
                                <?php echo date('M d, Y', strtotime($dive['date'])); ?> - <?php echo htmlspecialchars($dive['location']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="fish_species_id">Select Fish Species:</label>
                    <select id="fish_species_id" name="fish_species_id" required>
                        <option value="">-- Select Fish Species --</option>
                        <?php foreach ($allFishSpecies as $fish): ?>
                            <option value="<?php echo $fish['id']; ?>">
                                <?php echo htmlspecialchars($fish['common_name']); ?> 
                                <?php if (!empty($fish['scientific_name'])): ?>
                                    (<?php echo htmlspecialchars($fish['scientific_name']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="sighting_date">Sighting Date:</label>
                    <input type="date" id="sighting_date" name="sighting_date" required>
                    <small>Leave as dive date if spotted during the dive.</small>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Approximate Quantity:</label>
                    <select id="quantity" name="quantity">
                        <option value="single">Single</option>
                        <option value="few">Few (2-5)</option>
                        <option value="many">Many (5-20)</option>
                        <option value="school">School (20+)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn">Record Sighting</button>
            </form>
        <?php endif; ?>
    </div>
    
    <!-- Search Fish Database Tab -->
    <div class="tab-content" id="search-fish">
        <h2>Search Fish Database</h2>
        <p>Search for fish species in FishBase to add to your collection.</p>
        
        <form method="get">
            <input type="hidden" name="action" value="search_fish">
            
            <div class="form-group">
                <label for="search_term">Search Term:</label>
                <input type="text" id="search_term" name="search_term" placeholder="Enter common or scientific name" required>
            </div>
            
            <button type="submit" class="btn">Search FishBase</button>
        </form>
        
        <?php if ($searchResults): ?>
            <div class="search-results">
                <h3>Search Results</h3>
                
                <?php if ($searchResults['success'] && !empty($searchResults['results'])): ?>
                    <?php foreach ($searchResults['results'] as $result): ?>
                        <div class="result-item">
                            <h4><?php echo htmlspecialchars($result['Genus'] . ' ' . $result['Species']); ?></h4>
                            <p><strong>Common Name:</strong> <?php echo htmlspecialchars($result['FBname'] ?? 'Not available'); ?></p>
                            <p><strong>Family:</strong> <?php echo htmlspecialchars($result['Family'] ?? 'Unknown'); ?></p>
                            
                            <form method="post" style="display: inline-block;">
                                <input type="hidden" name="action" value="add_species">
                                <input type="hidden" name="common_name" value="<?php echo htmlspecialchars($result['FBname'] ?? $result['Genus'] . ' ' . $result['Species']); ?>">
                                <input type="hidden" name="scientific_name" value="<?php echo htmlspecialchars($result['Genus'] . ' ' . $result['Species']); ?>">
                                <input type="hidden" name="habitat" value="<?php echo htmlspecialchars($result['Fresh'] ? 'Freshwater' : 'Marine'); ?>">
                                <button type="submit" class="btn">Add to My Collection</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No results found for your search term. Try a different search.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching functionality
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs and content
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Show corresponding content
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // Pre-fill sighting date with dive date when dive is selected
            const diveSelect = document.getElementById('divelog_id');
            if (diveSelect) {
                diveSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption.value) {
                        const diveDate = selectedOption.textContent.split(' - ')[0].trim();
                        const dateComponents = diveDate.split(' ');
                        
                        // Convert date format from "Jan 01, 2023" to "2023-01-01"
                        const months = {"Jan": "01", "Feb": "02", "Mar": "03", "Apr": "04", "May": "05", "Jun": "06", 
                                        "Jul": "07", "Aug": "08", "Sep": "09", "Oct": "10", "Nov": "11", "Dec": "12"};
                        
                        const monthName = dateComponents[0];
                        const day = dateComponents[1].replace(',', '');
                        const year = dateComponents[2];
                        
                        const formattedDate = `${year}-${months[monthName]}-${day.padStart(2, '0')}`;
                        document.getElementById('sighting_date').value = formattedDate;
                    }
                });
            }
        });
    </script>
</body>
</html> 