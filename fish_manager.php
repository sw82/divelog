<?php
// Include database connection
require_once 'db.php';

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : '';
$fish_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_fish']) || isset($_POST['update_fish'])) {
        // Get form data
        $common_name = $conn->real_escape_string($_POST['common_name']);
        $scientific_name = $conn->real_escape_string($_POST['scientific_name']);
        $description = $conn->real_escape_string($_POST['description']);
        $habitat = $conn->real_escape_string($_POST['habitat']);
        $size_range = $conn->real_escape_string($_POST['size_range']);
        
        // Validate required fields
        if (empty($common_name) || empty($scientific_name)) {
            $error = "Common name and scientific name are required!";
        } else {
            if (isset($_POST['add_fish'])) {
                // Check if fish already exists
                $checkStmt = $conn->prepare("SELECT id FROM fish_species WHERE common_name = ? OR scientific_name = ?");
                $checkStmt->bind_param("ss", $common_name, $scientific_name);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = "A fish with this common name or scientific name already exists!";
                } else {
                    // Insert new fish
                    $stmt = $conn->prepare("INSERT INTO fish_species (common_name, scientific_name, description, habitat, size_range) 
                                          VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $common_name, $scientific_name, $description, $habitat, $size_range);
                    
                    if ($stmt->execute()) {
                        $message = "Fish species added successfully!";
                    } else {
                        $error = "Error adding fish species: " . $stmt->error;
                    }
                }
            } else if (isset($_POST['update_fish']) && $fish_id > 0) {
                // Update existing fish
                $stmt = $conn->prepare("UPDATE fish_species SET common_name = ?, scientific_name = ?, 
                                      description = ?, habitat = ?, size_range = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $common_name, $scientific_name, $description, $habitat, $size_range, $fish_id);
                
                if ($stmt->execute()) {
                    $message = "Fish species updated successfully!";
                    // Reset action to view the list
                    $action = '';
                } else {
                    $error = "Error updating fish species: " . $stmt->error;
                }
            }
        }
    }
}

// Handle delete action
if ($action === 'delete' && $fish_id > 0) {
    // Check if fish is used in any sightings
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM fish_sightings WHERE fish_species_id = ?");
    $checkStmt->bind_param("i", $fish_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $error = "Cannot delete this fish species. It has {$row['count']} sightings recorded.";
    } else {
        // Delete fish
        $stmt = $conn->prepare("DELETE FROM fish_species WHERE id = ?");
        $stmt->bind_param("i", $fish_id);
        
        if ($stmt->execute()) {
            $message = "Fish species deleted successfully!";
        } else {
            $error = "Error deleting fish species: " . $stmt->error;
        }
    }
    
    // Reset action to view the list
    $action = '';
}

// Get fish data for edit form
$fish_data = null;
if ($action === 'edit' && $fish_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM fish_species WHERE id = ?");
    $stmt->bind_param("i", $fish_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $fish_data = $result->fetch_assoc();
    } else {
        $error = "Fish species not found!";
        $action = '';
    }
}

// Get all fish species for display
$fish_list = [];
$query = "SELECT fs.*, (SELECT COUNT(*) FROM fish_sightings WHERE fish_species_id = fs.id) as sightings_count 
          FROM fish_species fs ORDER BY common_name";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $fish_list[] = $row;
    }
}

// Page title
$page_title = "Fish Species Manager";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .fish-card {
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        .fish-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .actions {
            margin-top: 10px;
        }
        .scientific-name {
            font-style: italic;
            color: #666;
        }
        .badge-sightings {
            background-color: #17a2b8;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1><?php echo $page_title; ?></h1>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($action === 'add' || $action === 'edit'): ?>
            <!-- Add/Edit Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <?php echo ($action === 'add') ? 'Add New Fish Species' : 'Edit Fish Species'; ?>
                </div>
                <div class="card-body">
                    <form method="post" action="<?php echo ($action === 'edit') ? "fish_manager.php?action=edit&id={$fish_id}" : 'fish_manager.php?action=add'; ?>">
                        <div class="form-group">
                            <label for="common_name">Common Name*</label>
                            <input type="text" class="form-control" id="common_name" name="common_name" required 
                                value="<?php echo isset($fish_data['common_name']) ? htmlspecialchars($fish_data['common_name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="scientific_name">Scientific Name*</label>
                            <input type="text" class="form-control" id="scientific_name" name="scientific_name" required 
                                value="<?php echo isset($fish_data['scientific_name']) ? htmlspecialchars($fish_data['scientific_name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php 
                                echo isset($fish_data['description']) ? htmlspecialchars($fish_data['description']) : ''; 
                            ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="habitat">Habitat</label>
                            <input type="text" class="form-control" id="habitat" name="habitat" 
                                value="<?php echo isset($fish_data['habitat']) ? htmlspecialchars($fish_data['habitat']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="size_range">Size Range</label>
                            <input type="text" class="form-control" id="size_range" name="size_range" 
                                value="<?php echo isset($fish_data['size_range']) ? htmlspecialchars($fish_data['size_range']) : ''; ?>">
                        </div>
                        
                        <button type="submit" name="<?php echo ($action === 'add') ? 'add_fish' : 'update_fish'; ?>" class="btn btn-primary">
                            <?php echo ($action === 'add') ? 'Add Fish' : 'Update Fish'; ?>
                        </button>
                        <a href="fish_manager.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Fish List -->
            <div class="mb-3">
                <a href="fish_manager.php?action=add" class="btn btn-success">
                    <i class="fa fa-plus"></i> Add New Fish Species
                </a>
                <a href="add_sample_fish.php" class="btn btn-info ml-2">
                    Add Sample Fish Data
                </a>
                <a href="index.php" class="btn btn-secondary ml-2">
                    Back to Dive Log
                </a>
            </div>
            
            <?php if (empty($fish_list)): ?>
                <div class="alert alert-info">No fish species found. Add some to get started!</div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($fish_list as $fish): ?>
                        <div class="col-md-4">
                            <div class="card fish-card">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($fish['common_name']); ?></h5>
                                    <h6 class="card-subtitle mb-2 scientific-name"><?php echo htmlspecialchars($fish['scientific_name']); ?></h6>
                                    
                                    <?php if ($fish['sightings_count'] > 0): ?>
                                        <span class="badge badge-sightings">
                                            <?php echo $fish['sightings_count']; ?> sighting<?php echo $fish['sightings_count'] > 1 ? 's' : ''; ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($fish['description'])): ?>
                                        <p class="card-text mt-2"><?php echo htmlspecialchars($fish['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="mt-2">
                                        <?php if (!empty($fish['habitat'])): ?>
                                            <p><strong>Habitat:</strong> <?php echo htmlspecialchars($fish['habitat']); ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($fish['size_range'])): ?>
                                            <p><strong>Size:</strong> <?php echo htmlspecialchars($fish['size_range']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="actions">
                                        <a href="fish_manager.php?action=edit&id=<?php echo $fish['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                        
                                        <?php if ($fish['sightings_count'] == 0): ?>
                                            <a href="fish_manager.php?action=delete&id=<?php echo $fish['id']; ?>" 
                                               onclick="return confirm('Are you sure you want to delete this fish species?')" 
                                               class="btn btn-sm btn-danger">Delete</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html> 