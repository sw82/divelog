<?php
// Start the session for progress tracking
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Increase execution time limit for large imports
ini_set('max_execution_time', 300); // 5 minutes
set_time_limit(300);

// Location cache to avoid duplicate geocoding requests
$GLOBALS['geocoding_cache'] = [];

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
        'rating' => null,
        'comments' => '',
        'fish_sightings' => '',
        'dive_site' => '',
        'air_consumption_start' => null,
        'air_consumption_end' => null,
        'weight' => null,
        'suit_type' => null,  // Changed from empty string to null
        'water_type' => null   // Changed from empty string to null
    ];
    
    // Debug log the row data
    error_log("DEBUG: Raw CSV row: " . json_encode($row));
    
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
    if (isset($row[16]) && $row[16] !== '') $diveLog['rating'] = intval($row[16]);
    if (isset($row[17])) $diveLog['comments'] = $row[17];
    if (isset($row[18])) $diveLog['fish_sightings'] = $row[18];
    
    // New technical dive fields
    if (isset($row[19]) && $row[19] !== '') $diveLog['air_consumption_start'] = intval($row[19]);
    if (isset($row[20]) && $row[20] !== '') $diveLog['air_consumption_end'] = intval($row[20]);
    if (isset($row[21]) && $row[21] !== '') $diveLog['weight'] = floatval(str_replace(',', '.', $row[21])); // Handle European number format
    
    // Normalize suit_type to match the allowed enum values
    if (isset($row[22]) && $row[22] !== '') {
        $suitType = strtolower($row[22] !== null ? trim($row[22]) : '');
        if (in_array($suitType, ['wetsuit', 'drysuit', 'shortie', 'swimsuit', 'other'])) {
            $diveLog['suit_type'] = $suitType;
        } else {
            $diveLog['suit_type'] = 'other';
        }
    }
    
    // Normalize water_type to match the allowed enum values
    if (isset($row[23]) && $row[23] !== '') {
        $waterType = strtolower($row[23] !== null ? trim($row[23]) : '');
        if (in_array($waterType, ['salt', 'fresh', 'brackish'])) {
            $diveLog['water_type'] = $waterType;
        } else {
            $diveLog['water_type'] = null;
        }
    }
    
    return $diveLog;
}

// Function to geocode a location name to coordinates
function geocodeLocation($location) {
    // Check cache first to avoid duplicate requests
    if (isset($GLOBALS['geocoding_cache'][$location])) {
        error_log("Using cached geocoding result for: $location");
        return $GLOBALS['geocoding_cache'][$location];
    }
    
    error_log("Geocoding location: $location");
    
    // First check internet connectivity by pinging a reliable server
    $connected = checkInternetConnectivity();
    if (!$connected) {
        error_log("Geocoding failed: No internet connection available");
        return ['error' => 'No internet connection available for geocoding'];
    }
    
    // URL encode the location
    $encodedLocation = urlencode($location);
    
    // Use Nominatim OpenStreetMap API
    $url = "https://nominatim.openstreetmap.org/search?q=$encodedLocation&format=json&limit=1";
    
    // Add a custom user agent as required by Nominatim usage policy
    $options = [
        'http' => [
            'header' => "User-Agent: DiveLog-Importer/1.0\r\n" .
                        "Referer: https://divelog-app.com\r\n",
            'timeout' => 10  // 10 second timeout (increased from 5)
        ]
    ];
    
    $context = stream_context_create($options);
    
    try {
        // Send the request
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            error_log("Geocoding request failed for location: $location - " . ($error ? $error['message'] : 'Unknown error'));
            return ['error' => 'Failed to contact geocoding service'];
        }
        
        // Decode the JSON response
        $data = json_decode($response, true);
        
        // Check if we got a valid result
        if (empty($data)) {
            error_log("No geocoding results found for location: $location");
            $result = ['error' => 'No geocoding results found for this location'];
            $GLOBALS['geocoding_cache'][$location] = $result; // Cache the negative result too
            return $result;
        }
        
        // Get the first result
        $result = $data[0];
        
        // Create the result to return
        $coordinates = [
            'lat' => floatval($result['lat']),
            'lng' => floatval($result['lon'])
        ];
        
        // Cache the result
        $GLOBALS['geocoding_cache'][$location] = $coordinates;
        
        // Respect rate limits - add a small delay
        // Reduced to 300ms as we're now caching results
        usleep(300000); // 0.3 second
        
        return $coordinates;
    } catch (Exception $e) {
        error_log("Geocoding error for location '$location': " . $e->getMessage());
        return ['error' => 'Geocoding service error: ' . $e->getMessage()];
    }
}

// Check if internet connection is available
function checkInternetConnectivity() {
    // Try to connect to multiple reliable services to ensure we have connectivity
    $connectedToAny = false;
    
    // List of reliable hosts to check
    $hosts = [
        'www.google.com',
        'www.openstreetmap.org',
        'www.cloudflare.com'
    ];
    
    foreach ($hosts as $host) {
        $connected = @fsockopen($host, 80, $errno, $errstr, 2);
        if ($connected) {
            fclose($connected);
            $connectedToAny = true;
            break;
        }
    }
    
    return $connectedToAny;
}

// Function to validate dive log data
function validateDiveLog($diveLog, $useGeocoding = false) {
    $errors = [];
    $warnings = [];
    
    // Convert empty strings to null for numeric fields
    if ($diveLog['depth'] === '') $diveLog['depth'] = null;
    if ($diveLog['duration'] === '') $diveLog['duration'] = null;
    if ($diveLog['temperature'] === '') $diveLog['temperature'] = null;
    if ($diveLog['air_temperature'] === '') $diveLog['air_temperature'] = null;
    if ($diveLog['visibility'] === '') $diveLog['visibility'] = null;
    
    // Required fields
    if (empty($diveLog['location'])) {
        $errors[] = "Location is required";
    }
    
    if (empty($diveLog['date'])) {
        $errors[] = "Date is required";
    } else {
        // Check if the date is valid
        $dateFormat = 'Y-m-d';
        $d = DateTime::createFromFormat($dateFormat, $diveLog['date']);
        if (!$d || $d->format($dateFormat) != $diveLog['date']) {
            $errors[] = "Invalid date format. Use YYYY-MM-DD format.";
        }
    }
    
    if (empty($diveLog['dive_time']) && strpos($diveLog['date'], ' ') === false) {
        $errors[] = "Time is required - either as a separate field or as part of the date (YYYY-MM-DD HH:MM:SS)";
    }
    
    // Handle time in date or separate field
    if (!empty($diveLog['dive_time']) && strpos($diveLog['date'], ' ') === false) {
        // If time is provided separately, ensure it's in a valid format
        $timeFormat = 'H:i:s';
        $t = DateTime::createFromFormat($timeFormat, $diveLog['dive_time']);
        if (!$t) {
            // Try alternative formats
            $t = DateTime::createFromFormat('H:i', $diveLog['dive_time']);
            if (!$t) {
                $errors[] = "Invalid time format. Use HH:MM:SS or HH:MM format.";
            } else {
                // Standardize time format
                $diveLog['dive_time'] = $t->format($timeFormat);
            }
        }
    }
    
    // Check and process coordinates
    $hasValidCoordinates = false;
    
    // Check if coordinates are present and valid
    if (isset($diveLog['latitude']) && isset($diveLog['longitude'])) {
        $lat = floatval($diveLog['latitude']);
        $lon = floatval($diveLog['longitude']);
        
        // Check if coordinates are valid (not zero or extreme values)
        if (abs($lat) > 0.0001 && abs($lon) > 0.0001 && 
            abs($lat) <= 90 && abs($lon) <= 180) {
            $hasValidCoordinates = true;
        }
    }
    
    // If coordinates are missing or invalid, try to geocode the location if allowed
    if (!$hasValidCoordinates && !empty($diveLog['location']) && $useGeocoding) {
        error_log("DEBUG: Attempting to geocode location: " . $diveLog['location']);
        $coordinates = geocodeLocation($diveLog['location']);
        
        // Check if geocoding was successful or returned an error
        if (isset($coordinates['error'])) {
            $errors[] = "Geocoding error for location '{$diveLog['location']}': {$coordinates['error']}";
        } elseif (isset($coordinates['lat']) && isset($coordinates['lng'])) {
            $diveLog['latitude'] = $coordinates['lat'];
            $diveLog['longitude'] = $coordinates['lng'];
            $warnings[] = "Coordinates were missing or invalid - automatically geocoded from location '{$diveLog['location']}'";
            $hasValidCoordinates = true;
        } else {
            $errors[] = "Failed to determine coordinates for location '{$diveLog['location']}'. Please provide valid latitude and longitude.";
        }
    } else if (!$hasValidCoordinates && !$useGeocoding) {
        $errors[] = "Missing or invalid coordinates for location '{$diveLog['location']}'. Geocoding is disabled - please provide valid latitude and longitude.";
    }
    
    // Final check for coordinates
    if (!$hasValidCoordinates) {
        $errors[] = "Valid coordinates (latitude and longitude) are required for mapping dive locations";
    }
    
    // Numeric fields should be numeric
    if ($diveLog['depth'] !== null && !is_numeric($diveLog['depth'])) {
        $errors[] = "Depth must be a number";
    }
    
    if ($diveLog['duration'] !== null && !is_numeric($diveLog['duration'])) {
        $errors[] = "Duration must be a number";
    }
    
    if ($diveLog['temperature'] !== null && !is_numeric($diveLog['temperature'])) {
        $errors[] = "Water temperature must be a number";
    }
    
    if ($diveLog['air_temperature'] !== null && !is_numeric($diveLog['air_temperature'])) {
        $errors[] = "Air temperature must be a number";
    }
    
    if ($diveLog['visibility'] !== null && !is_numeric($diveLog['visibility'])) {
        $errors[] = "Visibility must be a number";
    }
    
    // Validate rating
    if (!empty($diveLog['rating']) && 
        (!is_numeric($diveLog['rating']) || $diveLog['rating'] < 1 || $diveLog['rating'] > 5)) {
        $errors[] = "Rating must be between 1 and 5";
    }
    
    // Validate suit_type if provided
    if (!is_null($diveLog['suit_type']) && !empty($diveLog['suit_type'])) {
        $suitType = strtolower($diveLog['suit_type'] !== null ? trim($diveLog['suit_type']) : '');
        if (!in_array($suitType, ['wetsuit', 'drysuit', 'shortie', 'swimsuit', 'other'])) {
            $errors[] = "Suit type '{$diveLog['suit_type']}' must be one of: wetsuit, drysuit, shortie, swimsuit, other - it will be set to 'other'";
            $diveLog['suit_type'] = 'other'; // Auto-correct to 'other'
        } else {
            $diveLog['suit_type'] = $suitType; // Normalize case
        }
    }
    
    // Validate water_type if provided
    if (!is_null($diveLog['water_type']) && !empty($diveLog['water_type'])) {
        $waterType = strtolower($diveLog['water_type'] !== null ? trim($diveLog['water_type']) : '');
        if (!in_array($waterType, ['salt', 'fresh', 'brackish'])) {
            $errors[] = "Water type '{$diveLog['water_type']}' must be one of: salt, fresh, brackish - it will be left empty";
            $diveLog['water_type'] = null; // Auto-correct to NULL
        } else {
            $diveLog['water_type'] = $waterType; // Normalize case
        }
    }
    
    // Add any warnings to the dive log 
    if (!empty($warnings)) {
        $diveLog['_warnings'] = $warnings;
    }
    
    return [$errors, $diveLog];
}

// Function to insert dive log into database
function insertDiveLog($conn, $diveLog) {
    // Debug: Log the suit_type and water_type values
    $suit_debug = is_null($diveLog['suit_type']) ? 'NULL' : "'{$diveLog['suit_type']}'";
    $water_debug = is_null($diveLog['water_type']) ? 'NULL' : "'{$diveLog['water_type']}'";
    error_log("DEBUG: Before SQL - suit_type=$suit_debug, water_type=$water_debug");
    
    // Force suit_type to be only one of the allowed values
    if (!is_null($diveLog['suit_type']) && !empty($diveLog['suit_type'])) {
        $suitType = strtolower($diveLog['suit_type'] !== null ? trim($diveLog['suit_type']) : '');
        if (in_array($suitType, ['wetsuit', 'drysuit', 'shortie', 'swimsuit', 'other'])) {
            $diveLog['suit_type'] = $suitType;
        } else {
            error_log("DEBUG: Invalid suit_type='{$diveLog['suit_type']}', forcing to 'other'");
            $diveLog['suit_type'] = 'other';
        }
    }

    // Force water_type to be only one of the allowed values
    if (!is_null($diveLog['water_type']) && !empty($diveLog['water_type'])) {
        $waterType = strtolower($diveLog['water_type'] !== null ? trim($diveLog['water_type']) : '');
        if (in_array($waterType, ['salt', 'fresh', 'brackish'])) {
            $diveLog['water_type'] = $waterType;
        } else {
            error_log("DEBUG: Invalid water_type='{$diveLog['water_type']}', forcing to NULL");
            $diveLog['water_type'] = null;
        }
    }
    
    // Always use direct SQL instead of prepared statement to avoid issues with ENUM fields
    $sql = "INSERT INTO divelogs (
        location, latitude, longitude, date, dive_time, description, 
        depth, duration, temperature, air_temperature, visibility, 
        buddy, dive_site_type, rating, comments, dive_site,
        air_consumption_start, air_consumption_end, weight, suit_type, water_type
    ) VALUES (
        '" . $conn->real_escape_string($diveLog['location']) . "',
        " . floatval($diveLog['latitude']) . ",
        " . floatval($diveLog['longitude']) . ",
        '" . $conn->real_escape_string($diveLog['date']) . "',
        " . (!empty($diveLog['dive_time']) ? "'" . $conn->real_escape_string($diveLog['dive_time']) . "'" : "NULL") . ",
        " . (!empty($diveLog['description']) ? "'" . $conn->real_escape_string($diveLog['description']) . "'" : "NULL") . ",
        " . ($diveLog['depth'] !== null ? floatval($diveLog['depth']) : "NULL") . ",
        " . ($diveLog['duration'] !== null ? intval($diveLog['duration']) : "NULL") . ",
        " . ($diveLog['temperature'] !== null ? floatval($diveLog['temperature']) : "NULL") . ",
        " . ($diveLog['air_temperature'] !== null ? floatval($diveLog['air_temperature']) : "NULL") . ",
        " . ($diveLog['visibility'] !== null ? intval($diveLog['visibility']) : "NULL") . ",
        " . (!empty($diveLog['buddy']) ? "'" . $conn->real_escape_string($diveLog['buddy']) . "'" : "NULL") . ",
        " . (!empty($diveLog['dive_site_type']) ? "'" . $conn->real_escape_string($diveLog['dive_site_type']) . "'" : "NULL") . ",
        " . ($diveLog['rating'] !== null ? intval($diveLog['rating']) : "NULL") . ",
        " . (!empty($diveLog['comments']) ? "'" . $conn->real_escape_string($diveLog['comments']) . "'" : "NULL") . ",
        " . (!empty($diveLog['dive_site']) ? "'" . $conn->real_escape_string($diveLog['dive_site']) . "'" : "NULL") . ",
        " . ($diveLog['air_consumption_start'] !== null ? intval($diveLog['air_consumption_start']) : "NULL") . ",
        " . ($diveLog['air_consumption_end'] !== null ? intval($diveLog['air_consumption_end']) : "NULL") . ",
        " . ($diveLog['weight'] !== null ? floatval($diveLog['weight']) : "NULL") . ",
        " . (!is_null($diveLog['suit_type']) ? "'" . $conn->real_escape_string($diveLog['suit_type']) . "'" : "NULL") . ",
        " . (!is_null($diveLog['water_type']) ? "'" . $conn->real_escape_string($diveLog['water_type']) . "'" : "NULL") . "
    )";
    
    error_log("DEBUG: SQL: " . $sql);
    
    $success = $conn->query($sql);
    $insertId = $success ? $conn->insert_id : 0;
    $error = $success ? '' : $conn->error;
    
    if (!$success) {
        error_log("DEBUG: MySQL Error: " . $conn->error);
    }
    
    return [
        'success' => $success,
        'id' => $insertId,
        'error' => $error
    ];
}

// Initialize variables
$uploadError = '';
$systemStatus = checkSystemStatus();
$importResults = [
    'total' => 0,
    'imported' => 0,
    'skipped' => 0,
    'errors' => [],
    'success' => [],
    'warnings' => []
];

// Check overall system status
function checkSystemStatus() {
    $status = [
        'database' => false,
        'internet' => false
    ];
    
    // Check database connection
    global $conn;
    $status['database'] = ($conn && !$conn->connect_error);
    
    // Check internet connectivity
    $status['internet'] = checkInternetConnectivity();
    
    return $status;
}

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
            // Check for prerequisites before processing
            $useGeocoding = isset($_POST['use_geocoding']) && $_POST['use_geocoding'] == '1';
            $prerequisiteCheck = checkPrerequisites($useGeocoding);
            
            if (!$prerequisiteCheck['success']) {
                $uploadError = $prerequisiteCheck['error'];
            } else {
                $importResults = processCSVFile($fileTmpName, $conn, $useGeocoding);
            }
        }
    } else {
        $uploadError = "Error uploading file. Code: " . $_FILES['csv_file']['error'];
    }
}

// Check all prerequisites before processing
function checkPrerequisites($useGeocoding = false) {
    // Check database connection first
    global $conn;
    if (!$conn || $conn->connect_error) {
        return [
            'success' => false,
            'error' => "Database connection not available. Please check your database settings."
        ];
    }
    
    // If geocoding might be used, check internet connectivity
    if ($useGeocoding) {
        $connected = checkInternetConnectivity();
        if (!$connected) {
            return [
                'success' => false,
                'error' => "Internet connection is required for geocoding. Please connect to the internet or provide coordinates in your CSV file."
            ];
        }
    }
    
    return ['success' => true];
}

// Process a CSV file
function processCSVFile($filePath, $conn, $useGeocoding = false) {
    $importResults = [
        'total' => 0,
        'imported' => 0,
        'skipped' => 0,
        'errors' => [],
        'success' => [],
        'warnings' => [],
        'batch_size' => 10, // Process in batches of 10 records
        'current_batch' => 0
    ];
    
    // Set a session variable to track progress if not already set
    if (!isset($_SESSION['import_progress'])) {
        $_SESSION['import_progress'] = [
            'total_rows' => 0,
            'processed_rows' => 0,
            'status' => 'starting'
        ];
    }

    // Open the CSV file
    $handle = fopen($filePath, "r");
    if ($handle !== FALSE) {
        // Detect delimiter by examining the first line
        $firstLine = fgets($handle);
        rewind($handle); // Go back to start of file
        
        // Check for common delimiters
        $delimiter = ','; // Default
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        }
        
        error_log("DEBUG: Detected CSV delimiter: '$delimiter'");
        
        // Read the header row
        $headerRow = fgetcsv($handle, 1000, $delimiter, "\"", "\\");
        error_log("DEBUG: CSV header: " . json_encode($headerRow));
        
        // Start transaction for all inserts
        $conn->begin_transaction();
        
        $rowNumber = 1; // Start from row 1 (after header)
        $importResults['total'] = 0;
        $batchCount = 0;
        $currentBatch = 1;
        
        // Count total rows first to set up progress tracking
        $totalRows = 0;
        $tmpHandle = fopen($filePath, "r");
        if ($tmpHandle) {
            // Skip header
            fgetcsv($tmpHandle, 1000, $delimiter, "\"", "\\");
            while (($row = fgetcsv($tmpHandle, 1000, $delimiter, "\"", "\\")) !== FALSE) {
                // Only count non-empty rows
                $isEmpty = true;
                foreach ($row as $cell) {
                    if ($cell !== null && trim($cell) !== '') {
                        $isEmpty = false;
                        break;
                    }
                }
                if (!$isEmpty) {
                    $totalRows++;
                }
            }
            fclose($tmpHandle);
        }
        
        $_SESSION['import_progress']['total_rows'] = $totalRows;
        $_SESSION['import_progress']['status'] = 'processing';
        session_write_close(); // Write session data and release lock
        
        // Read each row of data
        while (($row = fgetcsv($handle, 1000, $delimiter, "\"", "\\")) !== FALSE) {
            $rowNumber++;
            
            // Check if row is empty or just contains whitespace
            if (!$row || count($row) <= 1) {
                error_log("DEBUG: Skipping empty row $rowNumber");
                continue; // Skip this row
            }
            
            // Better empty row check - see if all cells are empty
            $isEmpty = true;
            foreach ($row as $cell) {
                if ($cell !== null && trim($cell) !== '') {
                    $isEmpty = false;
                    break;
                }
            }
            
            if ($isEmpty) {
                error_log("DEBUG: Skipping blank row $rowNumber");
                continue; // Skip this row and proceed to the next one
            }
            
            $importResults['total']++;
            $batchCount++;
            
            error_log("DEBUG: Processing row $rowNumber: " . json_encode($row));
            
            // Parse the CSV row
            $diveLog = parseCSVRow($row);
            
            // Validate the data
            $validationResult = validateDiveLog($diveLog, $useGeocoding);
            $validationErrors = $validationResult[0];
            $diveLog = $validationResult[1]; // This may contain updated coordinates from geocoding
            
            if (!empty($validationErrors)) {
                $importResults['errors'][] = "Row $rowNumber: " . implode("; ", $validationErrors);
                continue;
            }
            
            // Display warnings if any (but continue with import)
            if (isset($diveLog['_warnings']) && !empty($diveLog['_warnings'])) {
                foreach ($diveLog['_warnings'] as $warning) {
                    $importResults['warnings'][] = "Row $rowNumber: Warning: " . $warning;
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
            
            // Update progress
            $_SESSION['import_progress']['processed_rows'] = $importResults['total'];
            
            // If batch is complete, commit and restart transaction
            if ($batchCount >= $importResults['batch_size']) {
                error_log("DEBUG: Committing batch $currentBatch");
                $conn->commit();
                $conn->begin_transaction();
                $batchCount = 0;
                $currentBatch++;
                $importResults['current_batch'] = $currentBatch;
                
                // Write session data for progress update
                session_start();
                session_write_close();
            }
        }
        
        // Update progress at the end
        $_SESSION['import_progress']['status'] = 'completed';
        $_SESSION['import_progress']['processed_rows'] = $importResults['total'];
        session_start();
        session_write_close();
        
        // Commit the final batch
        if (empty($importResults['errors'])) {
            $conn->commit();
        } else {
            $conn->rollback();
        }
        
        fclose($handle);
    } else {
        $importResults['errors'][] = "Could not open the CSV file.";
    }
    
    return $importResults;
}

// CLI mode - for testing CSV imports directly
if (PHP_SAPI === 'cli') {
    if ($argc < 2) {
        echo "Usage: php import_csv.php <path_to_csv_file>\n";
        exit(1);
    }
    
    $csvFile = $argv[1];
    if (!file_exists($csvFile)) {
        echo "Error: File not found: $csvFile\n";
        exit(1);
    }
    
    echo "Processing CSV file: $csvFile\n";
    
    // Process the CSV file
    $results = processCSVFile($csvFile, $conn);
    
    // Output the results
    echo "Total rows processed: {$results['total']}\n";
    echo "Successfully imported: {$results['imported']}\n";
    echo "Skipped (already exist): {$results['skipped']}\n";
    echo "Errors: " . count($results['errors']) . "\n";
    
    if (!empty($results['success'])) {
        echo "\nSuccessful Imports:\n";
        foreach ($results['success'] as $success) {
            echo "- $success\n";
        }
    }
    
    if (!empty($results['errors'])) {
        echo "\nErrors:\n";
        foreach ($results['errors'] as $error) {
            echo "- $error\n";
        }
    }
    
    exit(0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import CSV - Dive Log</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Site's main stylesheet -->
    <link rel="stylesheet" href="style.css">
    <style>
        .import-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .file-drop-area {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .file-drop-area.is-active {
            border-color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.05);
        }
        .file-msg {
            font-size: 18px;
            margin-bottom: 15px;
            color: #6c757d;
        }
        .results-container {
            margin-top: 20px;
        }
        .success-item {
            color: #28a745;
        }
        .error-item {
            color: #dc3545;
        }
        .warning-item {
            color: #ffc107;
        }
        .file-input {
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .file-input-wrapper {
            position: relative;
            display: inline-block;
        }
        .csv-instructions {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .required-field {
            font-weight: bold;
            color: #dc3545;
        }
        .optional-field {
            color: #6c757d;
        }
        .system-status {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .status-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .status-label {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'navigation.php'; ?>
    
    <div class="container import-container">
        <h1 class="mb-4">Import Dive Logs from CSV</h1>
        
        <!-- System Status Indicators -->
        <div class="system-status mb-4">
            <h5>System Status</h5>
            <div class="d-flex gap-3">
                <div class="status-item">
                    <span class="status-label">Database Connection:</span>
                    <?php if ($systemStatus['database']): ?>
                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> Connected</span>
                    <?php else: ?>
                        <span class="badge bg-danger"><i class="fas fa-times me-1"></i> Not Connected</span>
                    <?php endif; ?>
                </div>
                <div class="status-item">
                    <span class="status-label">Internet Connection:</span>
                    <?php if ($systemStatus['internet']): ?>
                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> Connected</span>
                    <?php else: ?>
                        <span class="badge bg-danger"><i class="fas fa-times me-1"></i> Not Connected</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="csv-instructions">
            <h4>CSV Format Instructions</h4>
            <p>Your CSV file should contain the following fields:</p>
            <ul>
                <li><span class="required-field">Location</span> - Dive site location (city, country, etc.)</li>
                <li>Dive Site - Specific name of the dive site</li>
                <li><span class="required-field">Latitude</span> - Latitude coordinates (decimal format, e.g. 25.7617)</li>
                <li><span class="required-field">Longitude</span> - Longitude coordinates (decimal format, e.g. -80.1918)</li>
                <li><span class="required-field">Date</span> - Date of the dive (YYYY-MM-DD format)</li>
                <li><span class="required-field">Time</span> - Time of the dive (HH:MM:SS or HH:MM format)</li>
                <li>Description - Brief description of the dive</li>
                <li>Depth - Maximum depth in meters</li>
                <li>Duration - Dive duration in minutes</li>
                <li>Temperature - Water temperature in Celsius</li>
                <li>Air Temperature - Air temperature in Celsius</li>
                <li>Visibility - Visibility in meters</li>
                <li>Buddy - Dive buddy's name</li>
                <li>Dive Site Type - Type of dive site (reef, wreck, wall, etc.)</li>
                <li>Rating - Dive rating from 1 to 5</li>
                <li>Comments - Additional comments about the dive</li>
                <li>Air Consumption Start - Starting air pressure in bar</li>
                <li>Air Consumption End - Ending air pressure in bar</li>
                <li>Weight - Weight used during the dive in kg</li>
                <li>Suit Type - Type of exposure suit (wetsuit, drysuit, shortie, swimsuit, other)</li>
                <li>Water Type - Type of water (salt, fresh, brackish)</li>
            </ul>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Important:</strong> If you don't provide valid latitude and longitude, the system will attempt to geocode the location automatically, which requires:
                <ul class="mb-0 mt-2">
                    <li>An active internet connection</li>
                    <li>Access to OpenStreetMap's geocoding service</li>
                    <li>A valid, recognizable location name</li>
                </ul>
                <p class="mb-0 mt-2">For best results, provide accurate coordinates directly in your CSV file.</p>
            </div>
        </div>
        
        <?php if ($uploadError): ?>
            <div class="alert alert-danger"><?php echo $uploadError; ?></div>
        <?php endif; ?>
        
        <form action="" method="post" enctype="multipart/form-data" id="import-form">
            <div class="file-drop-area" id="drop-area">
                <div class="file-msg">Drag & drop your CSV file here or click to browse</div>
                <div class="file-input-wrapper">
                    <button type="button" class="btn btn-primary">Choose File</button>
                    <input type="file" name="csv_file" id="file-input" class="file-input" accept=".csv">
                </div>
                <div id="file-name" class="mt-2"></div>
            </div>
            
            <!-- Geocoding option -->
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" value="1" id="use_geocoding" name="use_geocoding" <?php echo $systemStatus['internet'] ? '' : 'disabled'; ?> checked>
                <label class="form-check-label" for="use_geocoding">
                    Enable automatic geocoding for records with missing or invalid coordinates
                    <?php if (!$systemStatus['internet']): ?>
                        <span class="text-danger">(Requires internet connection)</span>
                    <?php endif; ?>
                </label>
            </div>
            
            <!-- Progress bar (hidden by default) -->
            <div id="import-progress" class="d-none mb-4">
                <h5>Import Progress</h5>
                <div class="progress mb-2">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         role="progressbar" 
                         aria-valuenow="0" 
                         aria-valuemin="0" 
                         aria-valuemax="100" 
                         style="width: 0%">0%</div>
                </div>
                <div class="text-center" id="progress-status">Preparing import...</div>
            </div>
            
            <input type="hidden" name="action" value="import_csv">
            <button type="submit" class="btn btn-success w-100" id="import-button" <?php echo ($systemStatus['database']) ? '' : 'disabled'; ?>>
                Import Dive Logs
                <?php if (!$systemStatus['database']): ?>
                    <span class="ms-2">(Database connection required)</span>
                <?php endif; ?>
            </button>
        </form>
        
        <?php if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'import_csv' && !$uploadError): ?>
            <div class="results-container">
                <h3>Import Results</h3>
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Summary</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Total rows processed
                                <span class="badge bg-primary rounded-pill"><?php echo $importResults['total']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Successfully imported
                                <span class="badge bg-success rounded-pill"><?php echo $importResults['imported']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Skipped (already exists)
                                <span class="badge bg-warning text-dark rounded-pill"><?php echo $importResults['skipped']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Errors
                                <span class="badge bg-danger rounded-pill"><?php echo count($importResults['errors']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Warnings
                                <span class="badge bg-warning text-dark rounded-pill"><?php echo count($importResults['warnings']); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <?php if (!empty($importResults['warnings'])): ?>
                    <div class="card mb-3">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">Warnings</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($importResults['warnings'] as $warning): ?>
                                    <li class="list-group-item warning-item">
                                        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($warning); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($importResults['errors'])): ?>
                    <div class="card mb-3">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0">Errors</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($importResults['errors'] as $error): ?>
                                    <li class="list-group-item error-item">
                                        <i class="fas fa-times-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($importResults['success'])): ?>
                    <div class="card mb-3">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Successfully Processed</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($importResults['success'] as $success): ?>
                                    <li class="list-group-item success-item">
                                        <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File drop functionality
        const dropArea = document.getElementById('drop-area');
        const fileInput = document.getElementById('file-input');
        const fileName = document.getElementById('file-name');
        const chooseFileBtn = document.querySelector('.file-input-wrapper button');
        
        // Make the "Choose File" button trigger the file input dialog
        chooseFileBtn.addEventListener('click', function() {
            fileInput.click();
        });
        
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });
        
        // Highlight drop area when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });
        
        // Handle dropped files
        dropArea.addEventListener('drop', handleDrop, false);
        
        // Handle selected files
        fileInput.addEventListener('change', handleFiles, false);
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        function highlight() {
            dropArea.classList.add('is-active');
        }
        
        function unhighlight() {
            dropArea.classList.remove('is-active');
        }
        
        function handleDrop(e) {
            var dt = e.dataTransfer;
            var files = dt.files;
            
            handleFiles(files);
        }
        
        function handleFiles(e) {
            const files = e.target ? e.target.files : e;
            if (files.length) {
                updateFileInfo(files[0]);
            }
        }
        
        function updateFileInfo(file) {
            fileName.textContent = `Selected file: ${file.name} (${formatFileSize(file.size)})`;
            
            // Check if it's a CSV file
            if (!file.name.toLowerCase().endsWith('.csv')) {
                fileName.innerHTML += '<br><span class="text-danger">Warning: File does not have a .csv extension!</span>';
            }
        }
        
        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' bytes';
            else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            else return (bytes / 1048576).toFixed(1) + ' MB';
        }
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const importForm = document.getElementById('import-form');
        const importButton = document.getElementById('import-button');
        const progressBar = document.querySelector('#import-progress .progress-bar');
        const progressStatus = document.getElementById('progress-status');
        const progressContainer = document.getElementById('import-progress');
        
        // If there's an in-progress import, show the progress bar
        <?php if (isset($_SESSION['import_progress']) && $_SESSION['import_progress']['status'] === 'processing'): ?>
        progressContainer.classList.remove('d-none');
        checkProgress();
        <?php endif; ?>
        
        importForm.addEventListener('submit', function(e) {
            // Only show progress for imports with geocoding enabled
            if (document.getElementById('use_geocoding').checked) {
                e.preventDefault();
                
                // Show progress bar
                progressContainer.classList.remove('d-none');
                importButton.disabled = true;
                importButton.innerHTML = 'Importing... Please wait';
                
                // Submit the form using AJAX to allow progress tracking
                const formData = new FormData(importForm);
                
                // Make the ajax request
                fetch('import_csv.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Replace the current page content with the response
                    document.open();
                    document.write(html);
                    document.close();
                })
                .catch(error => {
                    console.error('Error:', error);
                    progressStatus.textContent = 'Error occurred during import. Please try again.';
                    progressStatus.classList.add('text-danger');
                    importButton.disabled = false;
                    importButton.innerHTML = 'Import Dive Logs';
                });
                
                // Start checking progress
                checkProgress();
            }
        });
        
        function checkProgress() {
            fetch('check_import_progress.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'completed') {
                    progressBar.style.width = '100%';
                    progressBar.textContent = '100%';
                    progressBar.classList.remove('progress-bar-animated');
                    progressStatus.textContent = 'Import completed!';
                    return;
                }
                
                const total = data.total_rows || 1; // Avoid division by zero
                const processed = data.processed_rows || 0;
                const percent = Math.round((processed / total) * 100);
                
                progressBar.style.width = percent + '%';
                progressBar.textContent = percent + '%';
                progressBar.setAttribute('aria-valuenow', percent);
                
                progressStatus.textContent = `Processed ${processed} of ${total} records...`;
                
                if (data.status === 'processing') {
                    // Continue checking progress
                    setTimeout(checkProgress, 1000);
                }
            })
            .catch(error => {
                console.error('Error checking progress:', error);
            });
        }
    });
    </script>
</body>
</html> 