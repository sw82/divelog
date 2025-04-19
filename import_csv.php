<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
include 'db.php';

// Function to determine if a dive log already exists in the database
function diveLogExists($conn, $location, $date, $latitude, $longitude, $dive_time = '') {
    // If dive_time is provided, include it in the check to allow multiple dives at the same location/date with different times
    if (!empty($dive_time)) {
        $stmt = $conn->prepare("SELECT id FROM divelogs WHERE location = ? AND date = ? AND ABS(latitude - ?) < 0.0001 AND ABS(longitude - ?) < 0.0001 AND dive_time = ?");
        $stmt->bind_param("ssdds", $location, $date, $latitude, $longitude, $dive_time);
    } else {
        // If no time provided, check if any dive exists at this location/date/coordinates
        $stmt = $conn->prepare("SELECT id FROM divelogs WHERE location = ? AND date = ? AND ABS(latitude - ?) < 0.0001 AND ABS(longitude - ?) < 0.0001");
        $stmt->bind_param("ssdd", $location, $date, $latitude, $longitude);
    }
    
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
        'fish_sightings' => '',
        'dive_site' => '',
        'air_consumption_start' => null,
        'air_consumption_end' => null,
        'weight' => null,
        'suit_type' => '',
        'water_type' => ''
    ];
    
    // Map CSV columns to database fields
    if (isset($row[0])) $diveLog['id'] = $row[0];
    if (isset($row[1])) $diveLog['location'] = $row[1];
    if (isset($row[2])) $diveLog['dive_site'] = $row[2];
    if (isset($row[3])) $diveLog['latitude'] = floatval(str_replace(',', '.', $row[3])); // Handle European number format
    if (isset($row[4])) $diveLog['longitude'] = floatval(str_replace(',', '.', $row[4])); // Handle European number format
    if (isset($row[5])) $diveLog['date'] = $row[5];
    if (isset($row[6])) $diveLog['dive_time'] = $row[6];
    if (isset($row[7])) $diveLog['description'] = $row[7];
    if (isset($row[8]) && $row[8] !== '') $diveLog['depth'] = floatval(str_replace(',', '.', $row[8])); // Handle European number format
    if (isset($row[9]) && $row[9] !== '') $diveLog['duration'] = intval($row[9]);
    if (isset($row[10]) && $row[10] !== '') $diveLog['temperature'] = floatval(str_replace(',', '.', $row[10])); // Handle European number format
    if (isset($row[11]) && $row[11] !== '') $diveLog['air_temperature'] = floatval(str_replace(',', '.', $row[11])); // Handle European number format
    if (isset($row[12]) && $row[12] !== '') $diveLog['visibility'] = intval($row[12]);
    if (isset($row[13])) $diveLog['buddy'] = $row[13];
    if (isset($row[14])) $diveLog['dive_site_type'] = $row[14];
    if (isset($row[15])) $diveLog['activity_type'] = $row[15];
    if (isset($row[16]) && $row[16] !== '') $diveLog['rating'] = intval($row[16]);
    if (isset($row[17])) $diveLog['comments'] = $row[17];
    if (isset($row[18])) $diveLog['fish_sightings'] = $row[18];
    
    // New technical dive fields
    if (isset($row[19]) && $row[19] !== '') $diveLog['air_consumption_start'] = intval($row[19]);
    if (isset($row[20]) && $row[20] !== '') $diveLog['air_consumption_end'] = intval($row[20]);
    if (isset($row[21]) && $row[21] !== '') $diveLog['weight'] = floatval(str_replace(',', '.', $row[21])); // Handle European number format
    if (isset($row[22])) $diveLog['suit_type'] = $row[22];
    if (isset($row[23])) $diveLog['water_type'] = $row[23];
    
    return $diveLog;
}

// Function to validate dive log data
function validateDiveLog($diveLog) {
    $errors = [];
    
    // Required fields
    if (empty($diveLog['location'])) {
        $errors[] = "Location is missing - please provide a location name";
    }
    
    if (empty($diveLog['date'])) {
        $errors[] = "Date is missing - please provide a date in YYYY-MM-DD format";
    } else if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $diveLog['date'])) {
        $errors[] = "Date format is incorrect - please use YYYY-MM-DD format";
    }
    
    // If multiple dives at same location/date are likely, suggest adding a time
    if (empty($diveLog['dive_time'])) {
        // We'll just add a warning note, not a hard error
        $diveLog['_warnings'] = ['No dive time provided - consider adding a time if you have multiple dives at the same location on the same day'];
    }
    
    // If both latitude and longitude are 0 or empty, try to geocode the location
    if (($diveLog['latitude'] == 0 && $diveLog['longitude'] == 0) || 
        (empty($diveLog['latitude']) && empty($diveLog['longitude']))) {
        
        // Only attempt geocoding if we have a location
        if (!empty($diveLog['location'])) {
            // Include geocoding function if not already included
            if (!function_exists('geocodeAddress')) {
                require_once 'divelog_functions.php';
            }
            
            $coordinates = geocodeAddress($diveLog['location']);
            if ($coordinates !== false) {
                $diveLog['latitude'] = $coordinates['latitude'];
                $diveLog['longitude'] = $coordinates['longitude'];
            } else {
                $errors[] = "Could not find coordinates for '{$diveLog['location']}' - please provide latitude and longitude manually or check the spelling";
            }
        } else {
            $errors[] = "Coordinates are required when location is not provided";
        }
    }
    
    // Validate activity_type
    if (!empty($diveLog['activity_type']) && 
        $diveLog['activity_type'] !== 'diving') {
        $errors[] = "Activity type must be 'diving'";
    }
    
    // Validate rating
    if (!empty($diveLog['rating']) && 
        (!is_numeric($diveLog['rating']) || $diveLog['rating'] < 1 || $diveLog['rating'] > 5)) {
        $errors[] = "Rating must be between 1 and 5";
    }
    
    // Validate suit_type if provided
    if (!empty($diveLog['suit_type']) && 
        !in_array(strtolower($diveLog['suit_type']), ['wetsuit', 'drysuit', 'shortie', 'swimsuit', 'other'])) {
        $errors[] = "Suit type must be one of: wetsuit, drysuit, shortie, swimsuit, other";
    }
    
    // Validate water_type if provided
    if (!empty($diveLog['water_type']) && 
        !in_array(strtolower($diveLog['water_type']), ['salt', 'fresh', 'brackish'])) {
        $errors[] = "Water type must be one of: salt, fresh, brackish";
    }
    
    return [$errors, $diveLog];
}

// Function to insert dive log into database
function insertDiveLog($conn, $diveLog) {
    $stmt = $conn->prepare("INSERT INTO divelogs (
        location, latitude, longitude, date, dive_time, description, 
        depth, duration, temperature, air_temperature, visibility, 
        buddy, dive_site_type, activity_type, rating, comments, dive_site,
        air_consumption_start, air_consumption_end, weight, suit_type, water_type
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param(
        "sddsssddddisssissidsss", 
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
        $diveLog['comments'],
        $diveLog['dive_site'],
        $diveLog['air_consumption_start'],
        $diveLog['air_consumption_end'],
        $diveLog['weight'],
        $diveLog['suit_type'],
        $diveLog['water_type']
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
                // Detect delimiter by examining the first line
                $firstLine = fgets($handle);
                rewind($handle); // Go back to start of file
                
                // Check for common delimiters
                $delimiter = ','; // Default
                if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
                    $delimiter = ';';
                }
                
                // Read the header row
                $headerRow = fgetcsv($handle, 1000, $delimiter, "\"", "\\");
                
                // Start transaction for all inserts
                $conn->begin_transaction();
                
                $rowNumber = 1; // Start from row 1 (after header)
                $importResults['total'] = 0;
                
                // Read each row of data
                while (($row = fgetcsv($handle, 1000, $delimiter, "\"", "\\")) !== FALSE) {
                    $rowNumber++;
                    
                    // Check if row is empty or just contains whitespace
                    if (!$row || count($row) <= 1) {
                        continue; // Skip this row
                    }
                    
                    // Better empty row check - see if all cells are empty
                    $isEmpty = true;
                    foreach ($row as $cell) {
                        if (trim($cell) !== '') {
                            $isEmpty = false;
                            break;
                        }
                    }
                    
                    if ($isEmpty) {
                        continue; // Skip this row and proceed to the next one
                    }
                    
                    $importResults['total']++;
                    
                    // Parse the CSV row
                    $diveLog = parseCSVRow($row);
                    
                    // Validate the data
                    $validationResult = validateDiveLog($diveLog);
                    $validationErrors = $validationResult[0];
                    $diveLog = $validationResult[1]; // This may contain updated coordinates from geocoding
                    
                    if (!empty($validationErrors)) {
                        $importResults['errors'][] = "Row $rowNumber: " . implode("; ", $validationErrors);
                        continue;
                    }
                    
                    // Display warnings if any (but continue with import)
                    if (isset($diveLog['_warnings']) && !empty($diveLog['_warnings'])) {
                        foreach ($diveLog['_warnings'] as $warning) {
                            $importResults['success'][] = "Row $rowNumber: <span style='color:#ff9800;'>Warning: " . $warning . "</span>";
                        }
                        // Remove the warnings from the dive log data before inserting
                        unset($diveLog['_warnings']);
                    }
                    
                    // Check if dive log already exists
                    if (diveLogExists($conn, $diveLog['location'], $diveLog['date'], $diveLog['latitude'], $diveLog['longitude'], $diveLog['dive_time'])) {
                        $importResults['skipped']++;
                        $timeInfo = !empty($diveLog['dive_time']) ? " at " . $diveLog['dive_time'] : "";
                        $importResults['success'][] = "Row $rowNumber: Skipped - a dive at {$diveLog['location']} on {$diveLog['date']}{$timeInfo} already exists in your log";
                        continue;
                    }
                    
                    // Insert the dive log
                    $insertResult = insertDiveLog($conn, $diveLog);
                    if ($insertResult['success']) {
                        $importResults['imported']++;
                        $timeInfo = !empty($diveLog['dive_time']) ? " at " . $diveLog['dive_time'] : "";
                        $importResults['success'][] = "Row $rowNumber: Successfully imported dive at {$diveLog['location']} on {$diveLog['date']}{$timeInfo}";
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
            position: relative;
        }
        .file-drop-area.active {
            border-color: #2196F3;
            background-color: rgba(33, 150, 243, 0.1);
        }
        /* Style for the file input to make it invisible but accessible */
        #csv_file {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
            cursor: pointer;
            z-index: 2;
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
                <li>ID (leave empty - this is automatically generated by the database)</li>
                <li><strong>Location (required)</strong> - City, country, or dive site region</li>
                <li>Dive Site - Specific name of the dive site</li>
                <li>Latitude - GPS coordinate (if empty but location is provided, geocoding will be attempted)</li>
                <li>Longitude - GPS coordinate (if empty but location is provided, geocoding will be attempted)</li>
                <li><strong>Date (required, format: YYYY-MM-DD)</strong></li>
                <li>Time (format: HH:MM, recommended if you have multiple dives at the same location on the same day)</li>
                <li>Description (optional)</li>
                <li>Depth in meters (optional)</li>
                <li>Duration in minutes (optional)</li>
                <li>Water Temperature in °C (optional)</li>
                <li>Air Temperature in °C (optional)</li>
                <li>Visibility in meters (optional)</li>
                <li>Buddy/Dive Partner (optional)</li>
                <li>Dive Site Type (optional)</li>
                <li>Activity Type (always 'diving')</li>
                <li>Rating 1-5 (optional)</li>
                <li>Comments (optional)</li>
                <li>Fish Sightings (optional, will be ignored during import)</li>
                <li>Air Consumption Start (optional, in minutes)</li>
                <li>Air Consumption End (optional, in minutes)</li>
                <li>Weight (optional, in kilograms)</li>
                <li>Suit Type (optional, one of: wetsuit, drysuit, shortie, swimsuit, other)</li>
                <li>Water Type (optional, one of: salt, fresh, brackish)</li>
            </ol>
            <p>You can download a template CSV file with the correct format and sample data to help you get started. <strong>Note:</strong> If you don't provide latitude/longitude coordinates, the system will attempt to automatically geocode them based on the location name.</p>
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
            
            // Prevent the default click behavior since the input is now overlaying the entire drop area
            fileDropArea.addEventListener('click', function(e) {
                // We no longer need this since the input is now over the entire drop area
                // fileInput.click();
                
                // Don't need to do anything as the input itself will capture the click
                // but we'll stop propagation just to be safe
                // e.stopPropagation();
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