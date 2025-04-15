<?php
// This file redirects to populate_db.php with the edit parameter
// It's used as a bridge between the divelist and the actual edit functionality

// Ensure we have an ID parameter
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // If no valid ID, redirect to the dive list
    header('Location: divelist.php');
    exit;
}

// Get the dive ID
$id = $_GET['id'];

// Redirect to populate_db.php with the edit parameter
header("Location: populate_db.php?edit=$id");
exit;
?> 