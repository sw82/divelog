<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
include 'db.php';

// Function to get fish sightings for a dive
function getFishSightings($divelogId) {
    global $conn;
    $sightings = [];
    
    $stmt = $conn->prepare("
        SELECT fs.*, f.common_name, f.scientific_name
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

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=dive_logs_export.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Write CSV header
fputcsv($output, [
    'ID',
    'Location',
    'Dive Site',
    'Latitude',
    'Longitude',
    'Date',
    'Time',
    'Description',
    'Depth (m)',
    'Duration (min)',
    'Water Temperature (°C)',
    'Air Temperature (°C)',
    'Visibility (m)',
    'Buddy',
    'Dive Site Type',
    'Activity Type',
    'Rating',
    'Comments',
    'Fish Sightings'
], ',', '"', '\\');

// Fetch all dive logs
$query = "SELECT * FROM divelogs ORDER BY date DESC";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Get fish sightings for this dive
        $fishSightings = getFishSightings($row['id']);
        $fishSightingsText = [];
        
        foreach ($fishSightings as $sighting) {
            $fishText = $sighting['common_name'];
            if ($sighting['scientific_name']) {
                $fishText .= " ({$sighting['scientific_name']})";
            }
            if ($sighting['quantity']) {
                $fishText .= " - {$sighting['quantity']}";
            }
            if ($sighting['notes']) {
                $fishText .= " - {$sighting['notes']}";
            }
            $fishSightingsText[] = $fishText;
        }
        
        // Write row to CSV
        fputcsv($output, [
            $row['id'],
            $row['location'],
            $row['dive_site'] ?? '',
            $row['latitude'],
            $row['longitude'],
            $row['date'],
            $row['dive_time'],
            $row['description'],
            $row['depth'],
            $row['duration'],
            $row['temperature'],
            $row['air_temperature'],
            $row['visibility'],
            $row['buddy'],
            $row['dive_site_type'],
            $row['activity_type'],
            $row['rating'],
            $row['comments'],
            implode('; ', $fishSightingsText)
        ], ',', '"', '\\');
    }
}

fclose($output);
?> 