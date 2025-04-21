<?php
// Include database connection
include 'db.php';

// Set header to JSON
header('Content-Type: application/json');

// Check if dive_id is provided
if (!isset($_GET['dive_id']) || !is_numeric($_GET['dive_id'])) {
    echo json_encode(['error' => 'Invalid dive ID']);
    exit;
}

$diveId = intval($_GET['dive_id']);

// Prepare the query to get fish sightings for the specified dive
$query = "SELECT fs.*, fsp.common_name, fsp.scientific_name, 
          (SELECT fi.filename FROM fish_images fi WHERE fi.fish_species_id = fs.fish_species_id AND fi.is_primary = 1 LIMIT 1) as primary_image
          FROM fish_sightings fs
          LEFT JOIN fish_species fsp ON fs.fish_species_id = fsp.id
          WHERE fs.divelog_id = ?
          ORDER BY fs.id";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $diveId);
$stmt->execute();
$result = $stmt->get_result();

$fishSightings = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Add image URL if available
        $row['image_url'] = $row['primary_image'] ? 'uploads/fishimages/' . $row['primary_image'] : null;
        $fishSightings[] = $row;
    }
}

$stmt->close();

// Return the fish sightings data as JSON
echo json_encode($fishSightings);
?> 