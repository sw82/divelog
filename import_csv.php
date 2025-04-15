<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
include 'db.php';

// Function to determine if a dive log already exists in the database
function diveLogExists($conn, $location, $date, $latitude, $longitude) {
    $stmt = $conn->prepare("SELECT id FROM divelogs WHERE location = ? AND date = ? AND ABS(latitude - ?) < 0.0001 AND ABS(longitude - ?) < 0.0001");
    $stmt->bind_param("ssdd", $location, $date, $latitude, $longitude);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

// Function to convert CSV row to dive log data
function parseCSVRow($row) {
    // Define default values
    $diveLog = [
        'id' => null,  // We'll ignore this when inserting
        'location' => '',
        'latitude' => 0,
        'longitude' => 0,
        'date' => '',
        'dive_time' => '',
        'description' => '',
        'depth' => null,
        'duration' => null,
        'temperature' => null,
        'air_temperature' => null,
        'visibility' => null,
        'buddy' => '',
        'dive_site_type' => '',
        'activity_type' => 'diving',
        'rating' => null,
        'comments' => '',
        'fish_sightings' => ''
    ];
    
    // Map CSV columns to database fields
    if (isset($row[0])) $diveLog['id'] = $row[0];
    if (isset($row[1])) $diveLog['location'] = $row[1];
    if (isset($row[2])) $diveLog['latitude'] = floatval($row[2]);
    if (isset($row[3])) $diveLog['longitude'] = floatval($row[3]);
    if (isset($row[4])) $diveLog['date'] = $row[4];
    if (isset($row[5])) $diveLog['dive_time'] = $row[5];
    if (isset($row[6])) $diveLog['description'] = $row[6];
    if (isset($row[7]) && $row[7] !== '') $diveLog['depth'] = floatval($row[7]);
    if (isset($row[8]) && $row[8] !== '') $diveLog['duration'] = intval($row[8]);
    if (isset($row[9]) && $row[9] !== '') $diveLog['temperature'] = floatval($row[9]);
    if (isset($row[10]) && $row[10] !== '') $diveLog['air_temperature'] = floatval($row[10]);
    if (isset($row[11]) && $row[11] !== '') $diveLog['visibility'] = intval($row[11]);
    if (isset($row[12])) $diveLog['buddy'] = $row[12];
    if (isset($row[13])) $diveLog['dive_site_type'] = $row[13];
    if (isset($row[14])) $diveLog['activity_type'] = $row[14];
    if (isset($row[15]) && $row[15] !== '') $diveLog['rating'] = intval($row[15]);
    if (isset($row[16])) $diveLog['comments'] = $row[16];
    if (isset($row[17])) $diveLog['fish_sightings'] = $row[17];
    
    return $diveLog;
}

// Function to validate dive log data
function validateDiveLog($diveLog) {
    $errors = [];
    
    // Required fields
    if (empty($diveLog['location'])) {
        $errors[] = "Location is required";
    }
    
    if (empty($diveLog['date'])) {
        $errors[] = "Date is required";
    } else if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $diveLog['date'])) {
        $errors[] = "Date format should be YYYY-MM-DD";
    }
    
    if ($diveLog['latitude'] == 0 && $diveLog['longitude'] == 0) {
        $errors[] = "Valid latitude and longitude are required";
    }
    
    // Validate activity_type
    if (!empty($diveLog['activity_type']) && 
        !in_array($diveLog['activity_type'], ['diving', 'snorkeling'])) {
        $errors[] = "Activity type must be either 'diving' or 'snorkeling'";
    }
    
    // Validate rating
    if (!empty($diveLog['rating']) && 
        (!is_numeric($diveLog['rating']) || $diveLog['rating'] < 1 || $diveLog['rating'] > 5)) {
        $errors[] = "Rating must be between 1 and 5";
    }
    
    return $errors;
}

// Function to insert dive log into database
function insertDiveLog($conn, $diveLog) {
    $stmt = $conn->prepare("INSERT INTO divelogs (
        location, latitude, longitude, date, dive_time, description, 
        depth, duration, temperature, air_temperature, visibility, 
        buddy, dive_site_type, activity_type, rating, comments
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param(
        "sddsssddddisssis", 
        $diveLog['location'], 
        $diveLog['latitude'], 
        $diveLog['longitude'], 
        $diveLog['date'], 
        $diveLog['dive_time'], 
        $diveLog['description'], 
        $diveLog['depth'], 
        $diveLog['duration'], 
        $diveLog['temperature'], 
        $diveLog['air_temperature'], 
        $diveLog['visibility'], 
        $diveLog['buddy'], 
        $diveLog['dive_site_type'], 
        $diveLog['activity_type'], 
        $diveLog['rating'], 
        $diveLog['comments']
    );
    
    $success = $stmt->execute();
    $insertId = $success ? $stmt->insert_id : 0;
    $error = $success ? '' : $stmt->error;
    
    $stmt->close();
    
    return [
        'success' => $success,
        'id' => $insertId,
        'error' => $error
    ];
}

// Initialize variables
$uploadError = '';
$importResults = [
    'total' => 0,
    'imported' => 0,
    'skipped' => 0,
    'errors' => [],
    'success' => []
];

// Process CSV upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'import_csv') {
    // Check if file was uploaded without errors
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $fileType = $_FILES['csv_file']['type'];
        $fileName = $_FILES['csv_file']['name'];
        $fileTmpName = $_FILES['csv_file']['tmp_name'];
        
        // Check file extension
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        if (strtolower($fileExtension) != 'csv') {
            $uploadError = "Only CSV files are allowed.";
        } else {
            // Open the CSV file
            $handle = fopen($fileTmpName, "r");
            if ($handle !== FALSE) {
                // Read the header row
                $headerRow = fgetcsv($handle, 1000, ",", "\"", "\\");
                
                // Start transaction for all inserts
                $conn->begin_transaction();
                
                $rowNumber = 1; // Start from row 1 (after header)
                $importResults['total'] = 0;
                
                // Read each row of data
                while (($row = fgetcsv($handle, 1000, ",", "\"", "\\")) !== FALSE) {
                    $rowNumber++;
                    $importResults['total']++;
                    
                    // Parse the CSV row
                    $diveLog = parseCSVRow($row);
                    
                    // Validate the data
                    $validationErrors = validateDiveLog($diveLog);
                    if (!empty($validationErrors)) {
                        $importResults['errors'][] = "Row $rowNumber: " . implode("; ", $validationErrors);
                        continue;
                    }
                    
                    // Check if dive log already exists
                    if (diveLogExists($conn, $diveLog['location'], $diveLog['date'], $diveLog['latitude'], $diveLog['longitude'])) {
                        $importResults['skipped']++;
                        $importResults['success'][] = "Row $rowNumber: Skipped - dive log already exists for {$diveLog['location']} on {$diveLog['date']}";
                        continue;
                    }
                    
                    // Insert the dive log
                    $insertResult = insertDiveLog($conn, $diveLog);
                    if ($insertResult['success']) {
                        $importResults['imported']++;
                        $importResults['success'][] = "Row $rowNumber: Successfully imported {$diveLog['location']} on {$diveLog['date']}";
                    } else {
                        $importResults['errors'][] = "Row $rowNumber: Database error - " . $insertResult['error'];
                    }
                }
                
                // Commit if there were no errors, rollback otherwise
                if (empty($importResults['errors'])) {
                    $conn->commit();
                } else {
                    $conn->rollback();
                }
                
                fclose($handle);
            } else {
                $uploadError = "Could not open the CSV file.";
            }
        }
    } else {
        $uploadError = "Error uploading file. Code: " . $_FILES['csv_file']['error'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Dive Logs from CSV</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .import-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .instructions {
            background-color: #f5f5f5;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border-left: 4px solid #2196F3;
        }
        .csv-form {
            margin-bottom: 30px;
        }
        .results {
            margin-top: 20px;
        }
        .result-summary {
            background-color: #e8f5e9;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .result-details {
            margin-top: 15px;
        }
        .success-item {
            color: #4caf50;
        }
        .error-item {
            color: #f44336;
        }
        .file-drop-area {
            border: 2px dashed #ccc;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            margin-bottom: 15px;
            cursor: pointer;
        }
        .file-drop-area.active {
            border-color: #2196F3;
            background-color: rgba(33, 150, 243, 0.1);
        }
        .download-template {
            margin-top: 10px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <?php include 'navigation.php'; ?>
    
    <div class="import-container">
        <h1>Import Dive Logs from CSV</h1>
        
        <div class="instructions">
            <h2>Instructions</h2>
            <p>Upload a CSV file containing dive log information. The CSV should have the following columns:</p>
            <ol>
                <li>ID (optional, will be ignored)</li>
                <li>Location (required)</li>
                <li>Latitude (required)</li>
                <li>Longitude (required)</li>
                <li>Date (required, format: YYYY-MM-DD)</li>
                <li>Time (optional)</li>
                <li>Description (optional)</li>
                <li>Depth in meters (optional)</li>
                <li>Duration in minutes (optional)</li>
                <li>Water Temperature in °C (optional)</li>
                <li>Air Temperature in °C (optional)</li>
                <li>Visibility in meters (optional)</li>
                <li>Buddy/Dive Partner (optional)</li>
                <li>Dive Site Type (optional)</li>
                <li>Activity Type (optional, 'diving' or 'snorkeling')</li>
                <li>Rating 1-5 (optional)</li>
                <li>Comments (optional)</li>
                <li>Fish Sightings (optional, will be ignored during import)</li>
            </ol>
            <p>You can download a template CSV file with the correct format and sample data to help you get started.</p>
            <a href="csv_template.php" class="download-template">Download Template CSV</a>
        </div>
        
        <div class="csv-form">
            <h2>Upload CSV File</h2>
            
            <?php if (!empty($uploadError)): ?>
                <div class="error"><?php echo $uploadError; ?></div>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_csv">
                
                <div class="file-drop-area" id="fileDropArea">
                    <span>Drag & drop your CSV file here or click to browse</span>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                </div>
                
                <div id="selected-file-name"></div>
                
                <button type="submit" class="btn">Import CSV</button>
            </form>
        </div>
        
        <?php if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'import_csv' && empty($uploadError)): ?>
            <div class="results">
                <h2>Import Results</h2>
                <div class="result-summary">
                    <p><strong>Total rows processed:</strong> <?php echo $importResults['total']; ?></p>
                    <p><strong>Successfully imported:</strong> <?php echo $importResults['imported']; ?></p>
                    <p><strong>Skipped (already exist):</strong> <?php echo $importResults['skipped']; ?></p>
                    <p><strong>Errors:</strong> <?php echo count($importResults['errors']); ?></p>
                </div>
                
                <?php if (!empty($importResults['success'])): ?>
                    <h3>Successful Imports</h3>
                    <div class="result-details">
                        <ul>
                            <?php foreach ($importResults['success'] as $success): ?>
                                <li class="success-item"><?php echo htmlspecialchars($success); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($importResults['errors'])): ?>
                    <h3>Errors</h3>
                    <div class="result-details">
                        <ul>
                            <?php foreach ($importResults['errors'] as $error): ?>
                                <li class="error-item"><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileDropArea = document.getElementById('fileDropArea');
            const fileInput = document.getElementById('csv_file');
            const fileNameDisplay = document.getElementById('selected-file-name');
            
            // Update file name display when file is selected
            fileInput.addEventListener('change', function() {
                if (fileInput.files.length > 0) {
                    fileNameDisplay.textContent = 'Selected file: ' + fileInput.files[0].name;
                } else {
                    fileNameDisplay.textContent = '';
                }
            });
            
            // File drop functionality
            fileDropArea.addEventListener('click', function() {
                fileInput.click();
            });
            
            fileDropArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                fileDropArea.classList.add('active');
            });
            
            fileDropArea.addEventListener('dragleave', function() {
                fileDropArea.classList.remove('active');
            });
            
            fileDropArea.addEventListener('drop', function(e) {
                e.preventDefault();
                fileDropArea.classList.remove('active');
                
                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    
                    // Trigger change event
                    const event = new Event('change');
                    fileInput.dispatchEvent(event);
                }
            });
        });
    </script>
</body>
</html> 