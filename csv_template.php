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
    'ID',
    'Location',
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

// Write one example row with empty values
fputcsv($output, [
    '', // ID will be generated by database
    'Example Location', // Required
    '12.3456', // Required latitude
    '45.6789', // Required longitude
    '2023-01-15', // Required date in YYYY-MM-DD format
    '14:30', // Optional time
    'Example dive description', // Optional description
    '18.5', // Optional depth in meters
    '45', // Optional duration in minutes
    '24.5', // Optional water temperature in °C
    '28.2', // Optional air temperature in °C
    '15', // Optional visibility in meters
    'John Doe', // Optional buddy/dive partner
    'Reef', // Optional dive site type
    'diving', // Optional activity type (diving or snorkeling)
    '4', // Optional rating (1-5)
    'Additional comments about the dive', // Optional comments
    '' // Fish sightings (not used for import)
], ',', '"', '\\');

// Close file pointer
fclose($output);
?> 