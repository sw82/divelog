<?php
require_once 'config.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['error' => 'Invalid data received']);
    exit;
}

try {
    // Prepare the SQL statement
    $stmt = $conn->prepare("
        INSERT INTO divelog (
            date, location, depth, duration, 
            temperature, visibility, comments
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $successCount = 0;
    $errorCount = 0;
    $errors = [];

    // Handle single dive or batch of dives
    $dives = isset($data['dives']) ? $data['dives'] : [$data];

    foreach ($dives as $dive) {
        try {
            // Bind parameters
            $stmt->bind_param(
                "ssddddd",
                $dive['date'],
                $dive['location'],
                $dive['depth'],
                $dive['duration'],
                $dive['temperature'],
                $dive['visibility'],
                $dive['comments']
            );

            // Execute the statement
            if ($stmt->execute()) {
                $successCount++;
            } else {
                $errorCount++;
                $errors[] = "Failed to save dive: " . $stmt->error;
            }
        } catch (Exception $e) {
            $errorCount++;
            $errors[] = "Error saving dive: " . $e->getMessage();
        }
    }

    $stmt->close();

    if ($successCount > 0) {
        echo json_encode([
            'success' => true,
            'message' => "Successfully saved $successCount dives" . 
                        ($errorCount > 0 ? ", $errorCount failed" : ""),
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'error' => 'Failed to save any dives',
            'errors' => $errors
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?> 