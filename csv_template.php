<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=dive_logs_template.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Write CSV header
fputcsv($output, [
    'ID (leave empty)',
    'Location (REQUIRED)',
    'Dive Site',
    'Latitude (REQUIRED if no location)',
    'Longitude (REQUIRED if no location)',
    'Date (REQUIRED, YYYY-MM-DD)',
    'Time (IMPORTANT for multiple dives on same day/location)',
    'Description',
    'Depth (m)',
    'Duration (min)',
    'Water Temperature (°C)',
    'Air Temperature (°C)',
    'Visibility (m)',
    'Buddy',
    'Dive Site Type',
    'Activity Type (always diving)',
    'Rating (1-5)',
    'Comments',
    'Fish Sightings (ignored for import)',
    'Air Start (bar)',
    'Air End (bar)',
    'Weight (kg)',
    'Suit Type (wetsuit/drysuit/shortie/swimsuit/other)',
    'Water Type (salt/fresh/brackish)'
], ',', '"', '\\');

// Write one example row with empty values
fputcsv($output, [
    '', // Leave ID empty - will be automatically generated by database
    'Great Barrier Reef', // Required location
    'Ribbon Reef #5', // Optional specific dive site name
    '16.2181', // Latitude - can be left empty if location provided
    '145.4730', // Longitude - can be left empty if location provided
    '2023-01-15', // Required date in YYYY-MM-DD format
    '14:30', // Time - helps distinguish multiple dives on same day/location
    'Beautiful coral formations with diverse marine life', // Optional description
    '18.5', // Optional depth in meters
    '45', // Optional duration in minutes
    '24.5', // Optional water temperature in °C
    '28.2', // Optional air temperature in °C
    '15', // Optional visibility in meters
    'John Doe', // Optional buddy/dive partner
    'Reef', // Optional dive site type
    'diving', // Activity type (only diving supported)
    '4', // Optional rating (1-5)
    'Spotted several turtles and a reef shark', // Optional comments
    '', // Fish sightings (not used for import)
    '200', // Air start pressure in bar
    '50', // Air end pressure in bar
    '10.5', // Weight in kg
    'wetsuit', // Suit type
    'salt' // Water type
], ',', '"', '\\');

// Write a second example with only mandatory fields and a different time for the same day
fputcsv($output, [
    '', // Leave ID empty
    'Great Barrier Reef', // Same location as above
    'Ribbon Reef #5', // Same dive site
    '16.2181', // Same latitude
    '145.4730', // Same longitude
    '2023-01-15', // Same date as above
    '16:45', // Different time - allows recording multiple dives at same location
    'Second dive at the same location', // Description
    '12', // Different depth
    '40', // Different duration
    '', '', '', '', '', '', '', '' // Other fields empty
], ',', '"', '\\');

// Write a third example with only mandatory fields
fputcsv($output, [
    '', // Leave ID empty
    'Maldives Reef', // Only location (will be geocoded)
    '', // No dive site
    '', // No latitude (will be geocoded from location)
    '', // No longitude (will be geocoded from location)
    '2023-02-20', // Required date
    '', '', '', '', '', '', '', '', '', '', '', '' // All other fields empty
], ',', '"', '\\');

// Close file pointer
fclose($output);
?> 