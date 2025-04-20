<?php
// Start session to access import progress
session_start();

// Set header to return JSON
header('Content-Type: application/json');

// Initialize default response
$response = [
    'status' => 'not_started',
    'total_rows' => 0,
    'processed_rows' => 0,
    'percent' => 0
];

// Check if the import process is tracking progress
if (isset($_SESSION['import_progress'])) {
    $progress = $_SESSION['import_progress'];
    
    $response['status'] = $progress['status'];
    $response['total_rows'] = (int)$progress['total_rows'];
    $response['processed_rows'] = (int)$progress['processed_rows'];
    
    // Calculate percentage
    if ($progress['total_rows'] > 0) {
        $response['percent'] = round(($progress['processed_rows'] / $progress['total_rows']) * 100);
    }
    
    // If import is completed, clean up the session
    if ($progress['status'] === 'completed' && $progress['processed_rows'] >= $progress['total_rows']) {
        // Keep the completed status for one more check, then clear it
        if (!isset($_SESSION['import_progress']['cleanup_flag'])) {
            $_SESSION['import_progress']['cleanup_flag'] = true;
        } else {
            // Clear the progress tracking on the second check after completion
            unset($_SESSION['import_progress']);
        }
    }
}

// Return the progress as JSON
echo json_encode($response);
?> 