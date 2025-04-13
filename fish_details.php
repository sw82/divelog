<?php
// Include database connection
include 'db.php';

// Get fish species ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirect to fish manager if no valid ID provided
    header('Location: fish_manager.php');
    exit;
}

$fishId = $_GET['id'];

// Fetch fish species details
$stmt = $conn->prepare("SELECT * FROM fish_species WHERE id = ?");
$stmt->bind_param("i", $fishId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Fish species not found, redirect
    header('Location: fish_manager.php');
    exit;
}

$fishDetails = $result->fetch_assoc();
$stmt->close();

// Fetch fish images
$images = [];
$imagesStmt = $conn->prepare("SELECT * FROM fish_images WHERE fish_species_id = ? ORDER BY is_primary DESC, upload_date DESC");
$imagesStmt->bind_param("i", $fishId);
$imagesStmt->execute();
$imagesResult = $imagesStmt->get_result();

while ($imageRow = $imagesResult->fetch_assoc()) {
    $images[] = $imageRow;
}
$imagesStmt->close();

// Fetch fish sightings
$sightings = [];
$sightingsStmt = $conn->prepare("
    SELECT fs.*, d.location, d.date AS dive_date 
    FROM fish_sightings fs
    JOIN divelogs d ON fs.divelog_id = d.id
    WHERE fs.fish_species_id = ?
    ORDER BY fs.sighting_date DESC
");
$sightingsStmt->bind_param("i", $fishId);
$sightingsStmt->execute();
$sightingsResult = $sightingsStmt->get_result();

while ($sightingRow = $sightingsResult->fetch_assoc()) {
    $sightings[] = $sightingRow;
}
$sightingsStmt->close();

// Handle primary image selection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'set_primary') {
    $imageId = $_POST['image_id'];
    
    // Reset all images to non-primary
    $resetStmt = $conn->prepare("UPDATE fish_images SET is_primary = 0 WHERE fish_species_id = ?");
    $resetStmt->bind_param("i", $fishId);
    $resetStmt->execute();
    
    // Set selected image as primary
    $setPrimaryStmt = $conn->prepare("UPDATE fish_images SET is_primary = 1 WHERE id = ? AND fish_species_id = ?");
    $setPrimaryStmt->bind_param("ii", $imageId, $fishId);
    if ($setPrimaryStmt->execute()) {
        // Refresh the page to show changes
        header("Location: fish_details.php?id={$fishId}&success=primary_updated");
        exit;
    }
}

// Handle image deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_image') {
    $imageId = $_POST['image_id'];
    
    // Get the filename first
    $fileStmt = $conn->prepare("SELECT filename, is_primary FROM fish_images WHERE id = ? AND fish_species_id = ?");
    $fileStmt->bind_param("ii", $imageId, $fishId);
    $fileStmt->execute();
    $fileResult = $fileStmt->get_result();
    
    if ($fileRow = $fileResult->fetch_assoc()) {
        $filename = $fileRow['filename'];
        $isPrimary = $fileRow['is_primary'];
        $filePath = 'uploads/fishimages/' . $filename;
        
        // Delete from database
        $deleteStmt = $conn->prepare("DELETE FROM fish_images WHERE id = ?");
        $deleteStmt->bind_param("i", $imageId);
        
        if ($deleteStmt->execute()) {
            // Try to delete the file
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // If we deleted the primary image, set a new one if available
            if ($isPrimary) {
                // Find another image to set as primary
                $newPrimaryStmt = $conn->prepare("SELECT id FROM fish_images WHERE fish_species_id = ? LIMIT 1");
                $newPrimaryStmt->bind_param("i", $fishId);
                $newPrimaryStmt->execute();
                $newPrimaryResult = $newPrimaryStmt->get_result();
                
                if ($newPrimaryRow = $newPrimaryResult->fetch_assoc()) {
                    $newPrimaryId = $newPrimaryRow['id'];
                    $updatePrimaryStmt = $conn->prepare("UPDATE fish_images SET is_primary = 1 WHERE id = ?");
                    $updatePrimaryStmt->bind_param("i", $newPrimaryId);
                    $updatePrimaryStmt->execute();
                }
            }
            
            // Redirect back with success message
            header("Location: fish_details.php?id={$fishId}&success=image_deleted");
            exit;
        }
    }
}

// Handle image caption update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_caption') {
    $imageId = $_POST['image_id'];
    $caption = $_POST['caption'];
    
    $updateCaptionStmt = $conn->prepare("UPDATE fish_images SET caption = ? WHERE id = ? AND fish_species_id = ?");
    $updateCaptionStmt->bind_param("sii", $caption, $imageId, $fishId);
    
    if ($updateCaptionStmt->execute()) {
        header("Location: fish_details.php?id={$fishId}&success=caption_updated");
        exit;
    }
}

// Handle fish details update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_fish') {
    $commonName = $_POST['common_name'];
    $scientificName = $_POST['scientific_name'];
    $description = $_POST['description'];
    $habitat = $_POST['habitat'];
    $sizeRange = $_POST['size_range'];
    
    $updateFishStmt = $conn->prepare("UPDATE fish_species SET common_name = ?, scientific_name = ?, description = ?, habitat = ?, size_range = ? WHERE id = ?");
    $updateFishStmt->bind_param("sssssi", $commonName, $scientificName, $description, $habitat, $sizeRange, $fishId);
    
    if ($updateFishStmt->execute()) {
        // Refresh the fish details
        $fishDetails['common_name'] = $commonName;
        $fishDetails['scientific_name'] = $scientificName;
        $fishDetails['description'] = $description;
        $fishDetails['habitat'] = $habitat;
        $fishDetails['size_range'] = $sizeRange;
        
        // Set success message
        $successMessage = "Fish details updated successfully.";
    } else {
        $errorMessage = "Error updating fish details: " . $updateFishStmt->error;
    }
}

// Handle sighting deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_sighting') {
    $sightingId = $_POST['sighting_id'];
    
    $deleteSightingStmt = $conn->prepare("DELETE FROM fish_sightings WHERE id = ? AND fish_species_id = ?");
    $deleteSightingStmt->bind_param("ii", $sightingId, $fishId);
    
    if ($deleteSightingStmt->execute()) {
        header("Location: fish_details.php?id={$fishId}&success=sighting_deleted");
        exit;
    }
}

// Check for success messages from redirects
$successMessage = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'primary_updated':
            $successMessage = "Primary image updated successfully.";
            break;
        case 'image_deleted':
            $successMessage = "Image deleted successfully.";
            break;
        case 'caption_updated':
            $successMessage = "Image caption updated successfully.";
            break;
        case 'sighting_deleted':
            $successMessage = "Sighting record deleted successfully.";
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($fishDetails['common_name']); ?> - Fish Details</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .fish-header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 30px;
        }
        .primary-image {
            width: 300px;
            height: 300px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 30px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .fish-info {
            flex: 1;
        }
        .scientific-name {
            font-style: italic;
            color: #666;
            margin-top: 0;
        }
        .info-item {
            margin-bottom: 15px;
        }
        .info-label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        .tabs {
            margin: 30px 0 20px;
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
            padding: 20px 0;
        }
        .tab-content.active {
            display: block;
        }
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        .gallery-item {
            position: relative;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            padding-bottom: 15px;
        }
        .gallery-img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .gallery-controls {
            padding: 10px;
            text-align: center;
        }
        .gallery-caption {
            font-size: 14px;
            text-align: center;
            padding: 0 10px;
            margin-top: 5px;
            height: 40px;
            overflow: hidden;
        }
        .is-primary-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color: #4CAF50;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
        }
        .sightings-table {
            width: 100%;
            border-collapse: collapse;
        }
        .sightings-table th, .sightings-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        .sightings-table th {
            background-color: #f5f5f5;
        }
        .edit-form {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .caption-form {
            margin-top: 10px;
        }
        .caption-input {
            width: calc(100% - 90px);
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .caption-btn {
            padding: 6px 10px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <nav class="menu">
        <a href="index.php">View Dive Log</a>
        <a href="populate_db.php">Manage Database</a>
        <a href="fish_manager.php">Fish Species</a>
        <a href="backup_db.php">Backup Database</a>
    </nav>
    
    <div class="back-link">
        <a href="fish_manager.php">← Back to All Fish</a>
    </div>
    
    <h1><?php echo htmlspecialchars($fishDetails['common_name']); ?></h1>
    
    <?php if (!empty($successMessage)): ?>
        <div class="success-message"><?php echo $successMessage; ?></div>
    <?php endif; ?>
    
    <div class="fish-header">
        <?php if (!empty($images)): ?>
            <img src="uploads/fishimages/<?php echo $images[0]['filename']; ?>" alt="<?php echo htmlspecialchars($fishDetails['common_name']); ?>" class="primary-image">
        <?php else: ?>
            <div class="primary-image" style="display: flex; align-items: center; justify-content: center; background-color: #eee;">
                <span>No Image Available</span>
            </div>
        <?php endif; ?>
        
        <div class="fish-info">
            <h2 class="scientific-name"><?php echo htmlspecialchars($fishDetails['scientific_name']); ?></h2>
            
            <?php if (!empty($fishDetails['description'])): ?>
                <div class="info-item">
                    <span class="info-label">Description:</span>
                    <p><?php echo nl2br(htmlspecialchars($fishDetails['description'])); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($fishDetails['habitat'])): ?>
                <div class="info-item">
                    <span class="info-label">Typical Habitat:</span>
                    <p><?php echo htmlspecialchars($fishDetails['habitat']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($fishDetails['size_range'])): ?>
                <div class="info-item">
                    <span class="info-label">Size Range:</span>
                    <p><?php echo htmlspecialchars($fishDetails['size_range']); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="info-item">
                <span class="info-label">Sightings:</span>
                <p><?php echo count($sightings); ?> sighting<?php echo count($sightings) != 1 ? 's' : ''; ?> recorded</p>
            </div>
            
            <div class="info-item">
                <span class="info-label">Last Updated:</span>
                <p><?php echo date('F j, Y', strtotime($fishDetails['updated_at'])); ?></p>
            </div>
        </div>
    </div>
    
    <div class="tabs">
        <div class="tab active" data-tab="images">Images (<?php echo count($images); ?>)</div>
        <div class="tab" data-tab="sightings">Sightings (<?php echo count($sightings); ?>)</div>
        <div class="tab" data-tab="edit">Edit Details</div>
    </div>
    
    <!-- Images Tab -->
    <div class="tab-content active" id="images">
        <?php if (empty($images)): ?>
            <p>No images available for this fish species. <a href="#" onclick="document.querySelector('[data-tab=edit]').click(); return false;">Add some images</a> to enhance your record.</p>
        <?php else: ?>
            <div class="image-gallery">
                <?php foreach($images as $image): ?>
                    <div class="gallery-item">
                        <?php if ($image['is_primary']): ?>
                            <div class="is-primary-badge">Primary</div>
                        <?php endif; ?>
                        
                        <img src="uploads/fishimages/<?php echo $image['filename']; ?>" alt="<?php echo htmlspecialchars($fishDetails['common_name']); ?>" class="gallery-img">
                        
                        <div class="gallery-caption">
                            <?php echo htmlspecialchars($image['caption'] ?? ''); ?>
                        </div>
                        
                        <div class="gallery-controls">
                            <?php if (!$image['is_primary']): ?>
                                <form method="post" style="display: inline-block;">
                                    <input type="hidden" name="action" value="set_primary">
                                    <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                    <button type="submit" class="btn" title="Set as primary image">Make Primary</button>
                                </form>
                            <?php endif; ?>
                            
                            <form method="post" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this image?');">
                                <input type="hidden" name="action" value="delete_image">
                                <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                <button type="submit" class="danger" title="Delete this image">Delete</button>
                            </form>
                        </div>
                        
                        <form method="post" class="caption-form">
                            <input type="hidden" name="action" value="update_caption">
                            <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                            <input type="text" name="caption" value="<?php echo htmlspecialchars($image['caption'] ?? ''); ?>" class="caption-input" placeholder="Add caption">
                            <button type="submit" class="caption-btn">Update</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Sightings Tab -->
    <div class="tab-content" id="sightings">
        <?php if (empty($sightings)): ?>
            <p>No sightings recorded for this fish species yet. <a href="fish_manager.php">Record a sighting</a> when you spot this fish on a dive.</p>
        <?php else: ?>
            <table class="sightings-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Dive Location</th>
                        <th>Quantity</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($sightings as $sighting): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($sighting['sighting_date'])); ?></td>
                            <td>
                                <a href="populate_db.php?edit=<?php echo $sighting['divelog_id']; ?>" title="View dive details">
                                    <?php echo htmlspecialchars($sighting['location']); ?>
                                </a>
                                <div style="font-size: 12px; color: #666;">
                                    Dive date: <?php echo date('M d, Y', strtotime($sighting['dive_date'])); ?>
                                </div>
                            </td>
                            <td><?php echo ucfirst(htmlspecialchars($sighting['quantity'] ?? 'N/A')); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($sighting['notes'] ?? '')); ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Are you sure you want to delete this sighting record?');">
                                    <input type="hidden" name="action" value="delete_sighting">
                                    <input type="hidden" name="sighting_id" value="<?php echo $sighting['id']; ?>">
                                    <button type="submit" class="danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Edit Details Tab -->
    <div class="tab-content" id="edit">
        <form method="post" class="edit-form" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_fish">
            
            <div class="form-group">
                <label for="common_name">Common Name:</label>
                <input type="text" id="common_name" name="common_name" value="<?php echo htmlspecialchars($fishDetails['common_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="scientific_name">Scientific Name:</label>
                <input type="text" id="scientific_name" name="scientific_name" value="<?php echo htmlspecialchars($fishDetails['scientific_name']); ?>" placeholder="Genus species">
            </div>
            
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($fishDetails['description']); ?></textarea>
            </div>
            
            <div class="form-row-container">
                <div class="form-row">
                    <div class="form-group half">
                        <label for="habitat">Typical Habitat:</label>
                        <input type="text" id="habitat" name="habitat" value="<?php echo htmlspecialchars($fishDetails['habitat']); ?>">
                    </div>
                    
                    <div class="form-group half">
                        <label for="size_range">Size Range:</label>
                        <input type="text" id="size_range" name="size_range" value="<?php echo htmlspecialchars($fishDetails['size_range']); ?>" placeholder="e.g., 10-15 cm">
                    </div>
                </div>
            </div>
            
            <h3>Add New Images</h3>
            
            <div class="form-group">
                <label for="fish_images">Upload Images:</label>
                <input type="file" id="fish_images" name="fish_images[]" multiple accept="image/*">
            </div>
            
            <div class="form-group">
                <label for="image_url">Or Import Image from URL:</label>
                <input type="url" id="image_url" name="image_url" placeholder="https://...">
            </div>
            
            <button type="submit" class="btn">Update Fish Details</button>
        </form>
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
        });
    </script>
</body>
</html> 