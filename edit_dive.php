<?php
// Include helper functions and database connection
require_once 'divelog_functions.php';
require_once 'db.php';

// Initialize variables
$success_message = '';
$error_message = '';
$divelog = null;
$dive_images = [];
$fish_sightings = [];

// Check if an ID was provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error_message = "Invalid dive log ID.";
} else {
    $dive_id = $_GET['id'];
    
    // Fetch the dive entry
    $stmt = $conn->prepare("SELECT * FROM divelogs WHERE id = ?");
    $stmt->bind_param("i", $dive_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error_message = "Dive log entry not found.";
    } else {
        $divelog = $result->fetch_assoc();
        
        // Fetch images for this dive
        $stmt = $conn->prepare("SELECT * FROM divelog_images WHERE divelog_id = ?");
        $stmt->bind_param("i", $dive_id);
        $stmt->execute();
        $images_result = $stmt->get_result();
        
        while ($image = $images_result->fetch_assoc()) {
            $dive_images[] = $image;
        }
        
        // Fetch fish sightings for this dive
        $fish_sightings = getDiveFishSightings($dive_id);
        
        // Handle form submission for updating the dive
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            if ($_POST['action'] === 'update_dive') {
                // Update the dive log entry
                $stmt = $conn->prepare("UPDATE divelogs SET 
                    location = ?, 
                    country = ?,
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
                    comments = ?,
                    dive_site = ?,
                    air_consumption_start = ?,
                    air_consumption_end = ?,
                    weight = ?,
                    suit_type = ?,
                    water_type = ?
                    WHERE id = ?");
                
                // Convert empty visibility to NULL
                $visibility = $_POST['visibility'] === '' ? null : $_POST['visibility'];
                // Convert other potentially empty numeric fields
                $water_temp = $_POST['water_temp'] === '' ? null : $_POST['water_temp'];
                $air_temp = $_POST['air_temp'] === '' ? null : $_POST['air_temp'];
                $air_start = $_POST['air_start'] === '' ? null : $_POST['air_start'];
                $air_end = $_POST['air_end'] === '' ? null : $_POST['air_end'];
                $weight = $_POST['weight'] === '' ? null : $_POST['weight'];
                
                $stmt->bind_param("ssddssddddssisiiddsssi", 
                    $_POST['location'],
                    $_POST['country'],
                    $_POST['latitude'],
                    $_POST['longitude'],
                    $_POST['dive_date'],
                    $_POST['description'],
                    $_POST['max_depth'],
                    $_POST['dive_duration'],
                    $water_temp,
                    $air_temp,
                    $visibility,
                    $_POST['dive_buddy'],
                    $_POST['site_type'],
                    $_POST['rating'],
                    $_POST['comments'],
                    $_POST['dive_site'],
                    $air_start,
                    $air_end,
                    $weight,
                    $_POST['suit_type'],
                    $_POST['water_type'],
                    $dive_id
                );
                
                if ($stmt->execute()) {
                    // Handle image uploads
                    if (!empty($_FILES['images']['name'][0])) {
                        $upload_result = uploadDiveImages($dive_id, $_FILES['images']);
                        if (isset($upload_result['error'])) {
                            $error_message = $upload_result['error'];
                        } else {
                            // Reload images
                            $stmt = $conn->prepare("SELECT * FROM divelog_images WHERE divelog_id = ?");
                            $stmt->bind_param("i", $dive_id);
                            $stmt->execute();
                            $images_result = $stmt->get_result();
                            
                            $dive_images = [];
                            while ($image = $images_result->fetch_assoc()) {
                                $dive_images[] = $image;
                            }
                        }
                    }
                    
                    $success_message = "Dive log updated successfully!";
                    // Reload the dive data to show updated values
                    $stmt = $conn->prepare("SELECT * FROM divelogs WHERE id = ?");
                    $stmt->bind_param("i", $dive_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $divelog = $result->fetch_assoc();
                } else {
                    $error_message = "Error updating dive log: " . $stmt->error;
                }
            } elseif ($_POST['action'] === 'delete_image' && isset($_POST['image_id'])) {
                // Delete an image
                $image_id = $_POST['image_id'];
                $stmt = $conn->prepare("DELETE FROM divelog_images WHERE id = ? AND divelog_id = ?");
                $stmt->bind_param("ii", $image_id, $dive_id);
                
                if ($stmt->execute()) {
                    // Reload images
                    $stmt = $conn->prepare("SELECT * FROM divelog_images WHERE divelog_id = ?");
                    $stmt->bind_param("i", $dive_id);
                    $stmt->execute();
                    $images_result = $stmt->get_result();
                    
                    $dive_images = [];
                    while ($image = $images_result->fetch_assoc()) {
                        $dive_images[] = $image;
                    }
                    
                    $success_message = "Image deleted successfully!";
                } else {
                    $error_message = "Error deleting image: " . $stmt->error;
                }
            } elseif ($_POST['action'] === 'add_fish_sighting' && isset($_POST['fish_species_id'])) {
                // Add a fish sighting
                $stmt = $conn->prepare("INSERT INTO fish_sightings (divelog_id, fish_species_id, sighting_date, quantity, notes) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iisis", 
                    $dive_id, 
                    $_POST['fish_species_id'], 
                    $_POST['sighting_date'], 
                    $_POST['quantity'], 
                    $_POST['notes']
                );
                
                if ($stmt->execute()) {
                    $success_message = "Fish sighting added successfully!";
                    // Reload fish sightings
                    $fish_sightings = getDiveFishSightings($dive_id);
                } else {
                    $error_message = "Error adding fish sighting: " . $stmt->error;
                }
            } elseif ($_POST['action'] === 'delete_fish_sighting' && isset($_POST['sighting_id'])) {
                // Delete a fish sighting
                $sighting_id = $_POST['sighting_id'];
                $stmt = $conn->prepare("DELETE FROM fish_sightings WHERE id = ? AND divelog_id = ?");
                $stmt->bind_param("ii", $sighting_id, $dive_id);
                
                if ($stmt->execute()) {
                    $success_message = "Fish sighting deleted successfully!";
                    // Reload fish sightings
                    $fish_sightings = getDiveFishSightings($dive_id);
                } else {
                    $error_message = "Error deleting fish sighting: " . $stmt->error;
                }
            } elseif ($_POST['action'] === 'delete_dive' && isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
                // Delete entire dive log and related data
                $conn->begin_transaction();
                
                try {
                    // Delete related fish sightings
                    $stmt = $conn->prepare("DELETE FROM fish_sightings WHERE divelog_id = ?");
                    $stmt->bind_param("i", $dive_id);
                    $stmt->execute();
                    
                    // Delete related images
                    $stmt = $conn->prepare("DELETE FROM divelog_images WHERE divelog_id = ?");
                    $stmt->bind_param("i", $dive_id);
                    $stmt->execute();
                    
                    // Delete the dive log entry
                    $stmt = $conn->prepare("DELETE FROM divelogs WHERE id = ?");
                    $stmt->bind_param("i", $dive_id);
                    $stmt->execute();
                    
                    // Commit the transaction
                    $conn->commit();
                    
                    // Redirect to dive list with success message - add cache busting parameter
                    header("Location: divelist.php?message=deleted&cache_bust=" . time());
                    exit;
                } catch (Exception $e) {
                    // Rollback the transaction if an error occurs
                    $conn->rollback();
                    $error_message = "Error deleting dive log: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all fish species for dropdown
$fish_species = getAllFishSpecies();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Dive Log</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'navigation.php'; ?>
    
    <div class="container mt-4">
        <h1 class="mb-4">Edit Dive Log</h1>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($divelog): ?>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_dive">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="location">Location:</label>
                            <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($divelog['location'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="dive_site">Dive Site Name:</label>
                            <input type="text" class="form-control" id="dive_site" name="dive_site" value="<?php echo htmlspecialchars($divelog['dive_site'] ?? ''); ?>" placeholder="Optional name for this dive site">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="country">Country:</label>
                            <input type="text" class="form-control" id="country" name="country" value="<?php echo htmlspecialchars($divelog['country'] ?? ''); ?>" placeholder="Country of dive location">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="latitude">Latitude:</label>
                            <input type="text" class="form-control" id="latitude" name="latitude" value="<?php echo htmlspecialchars($divelog['latitude'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="longitude">Longitude:</label>
                            <input type="text" class="form-control" id="longitude" name="longitude" value="<?php echo htmlspecialchars($divelog['longitude'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="dive_date">Date:</label>
                            <input type="date" class="form-control" id="dive_date" name="dive_date" value="<?php echo htmlspecialchars($divelog['date'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="dive_time">Time:</label>
                            <input type="time" class="form-control" id="dive_time" name="dive_time" value="<?php echo htmlspecialchars($divelog['dive_time'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="max_depth">Max Depth (m):</label>
                            <input type="number" class="form-control" id="max_depth" name="max_depth" value="<?php echo htmlspecialchars($divelog['depth'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="dive_duration">Duration (min):</label>
                            <input type="number" class="form-control" id="dive_duration" name="dive_duration" value="<?php echo htmlspecialchars($divelog['duration'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="visibility">Visibility (m):</label>
                            <input type="number" class="form-control" id="visibility" name="visibility" value="<?php echo htmlspecialchars($divelog['visibility'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="water_temp">Water Temp (°C):</label>
                            <input type="number" class="form-control" id="water_temp" name="water_temp" value="<?php echo htmlspecialchars($divelog['temperature'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="air_temp">Air Temp (°C):</label>
                            <input type="number" class="form-control" id="air_temp" name="air_temp" value="<?php echo htmlspecialchars($divelog['air_temperature'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="dive_buddy">Dive Buddy:</label>
                            <input type="text" class="form-control" id="dive_buddy" name="dive_buddy" value="<?php echo htmlspecialchars($divelog['buddy'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="site_type">Site Type:</label>
                            <select class="form-control" id="site_type" name="site_type">
                                <option value="reef" <?php echo ($divelog['dive_site_type'] === 'reef') ? 'selected' : ''; ?>>Reef</option>
                                <option value="wreck" <?php echo ($divelog['dive_site_type'] === 'wreck') ? 'selected' : ''; ?>>Wreck</option>
                                <option value="cave" <?php echo ($divelog['dive_site_type'] === 'cave') ? 'selected' : ''; ?>>Cave</option>
                                <option value="wall" <?php echo ($divelog['dive_site_type'] === 'wall') ? 'selected' : ''; ?>>Wall</option>
                                <option value="other" <?php echo ($divelog['dive_site_type'] === 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="rating">Rating:</label>
                            <select class="form-control" id="rating" name="rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo (($divelog['rating'] ?? 0) == $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <h4 class="mt-4">Technical Details</h4>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="air_start">Air Start (bar):</label>
                            <input type="number" class="form-control" id="air_start" name="air_start" value="<?php echo htmlspecialchars($divelog['air_consumption_start'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="air_end">Air End (bar):</label>
                            <input type="number" class="form-control" id="air_end" name="air_end" value="<?php echo htmlspecialchars($divelog['air_consumption_end'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="weight">Weight (kg):</label>
                            <input type="number" step="0.5" class="form-control" id="weight" name="weight" value="<?php echo htmlspecialchars($divelog['weight'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="suit_type">Suit Type:</label>
                            <select class="form-control" id="suit_type" name="suit_type">
                                <option value="" <?php echo empty($divelog['suit_type']) ? 'selected' : ''; ?>>-- Select --</option>
                                <option value="wetsuit" <?php echo ($divelog['suit_type'] === 'wetsuit') ? 'selected' : ''; ?>>Wetsuit</option>
                                <option value="drysuit" <?php echo ($divelog['suit_type'] === 'drysuit') ? 'selected' : ''; ?>>Drysuit</option>
                                <option value="shortie" <?php echo ($divelog['suit_type'] === 'shortie') ? 'selected' : ''; ?>>Shortie</option>
                                <option value="swimsuit" <?php echo ($divelog['suit_type'] === 'swimsuit') ? 'selected' : ''; ?>>Swimsuit</option>
                                <option value="other" <?php echo ($divelog['suit_type'] === 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="water_type">Water Type:</label>
                            <select class="form-control" id="water_type" name="water_type">
                                <option value="" <?php echo empty($divelog['water_type']) ? 'selected' : ''; ?>>-- Select --</option>
                                <option value="salt" <?php echo ($divelog['water_type'] === 'salt') ? 'selected' : ''; ?>>Salt water</option>
                                <option value="fresh" <?php echo ($divelog['water_type'] === 'fresh') ? 'selected' : ''; ?>>Fresh water</option>
                                <option value="brackish" <?php echo ($divelog['water_type'] === 'brackish') ? 'selected' : ''; ?>>Brackish</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="images">Add Images:</label>
                    <input type="file" class="form-control-file" id="images" name="images[]" multiple>
                    <small class="form-text text-muted">You can select multiple images to upload (JPG, PNG, GIF).</small>
                </div>
                
                <?php if (!empty($dive_images)): ?>
                    <div class="form-group">
                        <label>Current Images:</label>
                        <div class="row image-gallery">
                            <?php foreach ($dive_images as $image): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card">
                                        <img src="<?php echo htmlspecialchars($image['image_data']); ?>" class="card-img-top" alt="Dive Image">
                                        <div class="card-body">
                                            <form method="POST">
                                                <input type="hidden" name="action" value="delete_image">
                                                <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($divelog['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="comments">Comments:</label>
                    <textarea class="form-control" id="comments" name="comments" rows="3"><?php echo htmlspecialchars($divelog['comments'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group d-flex justify-content-between align-items-center">
                    <div>
                        <button type="submit" class="btn btn-primary">Update Dive Log</button>
                        <a href="index.php" class="btn btn-secondary">Back to Map</a>
                    </div>
                    <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#deleteConfirmModal">
                        <i class="fas fa-trash-alt mr-1"></i> Delete Dive Log
                    </button>
                </div>
            </form>
            
            <!-- Delete Confirmation Modal -->
            <div class="modal fade" id="deleteConfirmModal" tabindex="-1" role="dialog" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Deletion</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to delete this dive log entry for <strong><?php echo htmlspecialchars($divelog['location'] ?? ''); ?></strong>?</p>
                            <p><strong>Warning:</strong> This will permanently delete:</p>
                            <ul>
                                <li>All dive details</li>
                                <li><?php echo count($dive_images); ?> uploaded image(s)</li>
                                <li><?php echo count($fish_sightings); ?> fish sighting(s)</li>
                            </ul>
                            <p class="text-danger"><strong>This action cannot be undone!</strong></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <form method="POST">
                                <input type="hidden" name="action" value="delete_dive">
                                <input type="hidden" name="confirm_delete" value="yes">
                                <button type="submit" class="btn btn-danger">Delete Permanently</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <h2 class="mt-4 mb-3">Fish Sightings</h2>
            
            <?php if (!empty($fish_sightings)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Species</th>
                                <th>Date</th>
                                <th>Quantity</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fish_sightings as $sighting): ?>
                                <tr>
                                    <td>
                                        <a href="fish_details.php?id=<?php echo $sighting['fish_species_id']; ?>">
                                            <?php echo htmlspecialchars(($sighting['common_name'] ?? '') . ' (' . ($sighting['scientific_name'] ?? '') . ')'); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($sighting['sighting_date'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($sighting['quantity'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($sighting['notes'] ?? ''); ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete_fish_sighting">
                                            <input type="hidden" name="sighting_id" value="<?php echo $sighting['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No fish sightings recorded for this dive.</p>
            <?php endif; ?>
            
            <div class="card mt-3">
                <div class="card-header">Add Fish Sighting</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_fish_sighting">
                        
                        <div class="form-group">
                            <label for="fish_species_id">Fish Species:</label>
                            <select class="form-control" id="fish_species_id" name="fish_species_id" required>
                                <option value="">-- Select Fish Species --</option>
                                <?php foreach ($fish_species as $species): ?>
                                    <option value="<?php echo $species['id']; ?>">
                                        <?php echo htmlspecialchars(($species['common_name'] ?? '') . ' (' . ($species['scientific_name'] ?? '') . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="sighting_date">Date:</label>
                            <input type="date" class="form-control" id="sighting_date" name="sighting_date" value="<?php echo $divelog['date'] ?? date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="quantity">Quantity:</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes:</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-success">Add Fish Sighting</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <?php echo $error_message ? $error_message : "No dive log specified."; ?>
                <p><a href="index.php" class="btn btn-primary mt-2">Back to Map</a></p>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
    <script>
        // Preview images before upload
        document.getElementById('images').addEventListener('change', function(event) {
            const preview = document.createElement('div');
            preview.className = 'row mt-2';
            
            for (let i = 0; i < event.target.files.length; i++) {
                const file = event.target.files[i];
                if (!file.type.match('image.*')) continue;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const col = document.createElement('div');
                    col.className = 'col-md-3 mb-2';
                    
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'img-thumbnail';
                    img.style.maxHeight = '150px';
                    
                    col.appendChild(img);
                    preview.appendChild(col);
                }
                
                reader.readAsDataURL(file);
            }
            
            const previewContainer = document.querySelector('.form-group:has(#images)');
            const existingPreview = previewContainer.querySelector('.row');
            if (existingPreview) {
                previewContainer.removeChild(existingPreview);
            }
            
            if (event.target.files.length > 0) {
                previewContainer.appendChild(preview);
            }
        });
    </script>
</body>
</html>