<?php
/**
 * Divelog Application Installer
 * 
 * This script automates the setup process for the Divelog application.
 * Upload this file to your server, navigate to it in a browser, and
 * follow the instructions to complete installation.
 */

// Security check to ensure this script isn't left on the server
session_start();

// Check if reset requested - clear session and restart
if (isset($_GET['reset'])) {
    // Completely reset the session
    $_SESSION = array();
    
    // If session has a cookie, destroy that too
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session completely
    session_destroy();
    
    // Start a new session
    session_start();
    
    // Set a flag to indicate a reset was performed
    $_SESSION['reset_performed'] = true;
    
    // Clear any cached installation checks
    unset($_SESSION['existing_installation_check']);
    
    // Initialize with default values
    $_SESSION['form_data'] = [
        'db_host' => 'localhost',
        'db_user' => '',
        'db_pass' => '',
        'db_name' => 'divelog',
        'admin_email' => '',
        'db_port' => 3306,
        'socket_path' => '',
        'use_socket' => false
    ];
    
    // Generate a new token
    $_SESSION['install_token'] = bin2hex(random_bytes(32));
    
    // Force a bypass of existing installation detection for this session
    $_SESSION['bypass_installation_check'] = true;
    
    // Redirect to the first step
    header('Location: install.php?step=1&cleared=1');
    exit;
}

// Check if clean marker requested - remove installation marker file
if (isset($_GET['clean_marker'])) {
    $markerFile = $baseDir . '/.install_complete';
    
    // Add debugging information
    error_log("Attempting to remove marker file: " . $markerFile);
    error_log("File exists before removal: " . (file_exists($markerFile) ? "Yes" : "No"));
    
    // Try with different methods to ensure removal
    $removalSuccess = false;
    
    // Method 1: Direct unlink with error suppression
    if (file_exists($markerFile)) {
        $removalSuccess = @unlink($markerFile);
        error_log("Method 1 (unlink) result: " . ($removalSuccess ? "Success" : "Failed"));
    }
    
    // Method 2: Use PHP file operations if unlink fails
    if (!$removalSuccess && file_exists($markerFile)) {
        // Try opening with write permissions to truncate
        $fp = @fopen($markerFile, 'w');
        if ($fp) {
            ftruncate($fp, 0);
            fclose($fp);
            $removalSuccess = @unlink($markerFile);
            error_log("Method 2 (truncate+unlink) result: " . ($removalSuccess ? "Success" : "Failed"));
        }
    }
    
    // Method 3: As a last resort, empty the file if we can't delete it
    if (!$removalSuccess && file_exists($markerFile)) {
        $emptySuccess = @file_put_contents($markerFile, '');
        error_log("Method 3 (empty file) result: " . ($emptySuccess !== false ? "Success" : "Failed"));
        $removalSuccess = $emptySuccess !== false;
    }
    
    // Force removal with system command as a last resort
    if (!$removalSuccess && file_exists($markerFile) && function_exists('exec')) {
        @exec('rm -f ' . escapeshellarg($markerFile), $output, $returnVar);
        error_log("Method 4 (exec rm) result: " . ($returnVar === 0 ? "Success" : "Failed"));
        $removalSuccess = $returnVar === 0;
    }
    
    // Check if the file still exists
    error_log("File exists after removal attempts: " . (file_exists($markerFile) ? "Yes" : "No"));
    
    // Set session variables based on the outcome
    if ($removalSuccess || !file_exists($markerFile)) {
        $_SESSION['marker_removed'] = true;
        
        // Also force reset of installation check
        unset($_SESSION['existing_installation_check']);
        $_SESSION['bypass_installation_check'] = true;
    } else {
        $_SESSION['marker_removal_failed'] = true;
        // Store detailed error info for debugging
        $_SESSION['marker_removal_error'] = [
            'file' => $markerFile,
            'exists' => file_exists($markerFile),
            'readable' => is_readable($markerFile),
            'writable' => is_writable($markerFile),
            'permissions' => substr(sprintf('%o', fileperms($markerFile)), -4)
        ];
    }
    
    // Force a re-check of installation status by clearing any cached data
    unset($_SESSION['existing_installation_check']);
    
    // Redirect to the first step with a parameter to force refresh
    header('Location: install.php?step=1&marker_action='.time());
    exit;
}

// Check if directory contents listing is requested
if (isset($_GET['list_dir'])) {
    $_SESSION['show_dir_contents'] = true;
    // Redirect to the first step
    header('Location: install.php?step=1');
    exit;
}

// Check if download all files is requested
if (isset($_GET['download_all'])) {
    // List of essential files to download from GitHub
    $files_to_download = [
        'index.php',
        'database_setup.sql',
        'config.php.example', // Will be renamed to config.php later
        'navigation.php',
        'map.js',
        'get_dive_data.php',
        'style.css',
        'check_years.php',
        'script.js',
        'manage_db.php',
        'divelog_functions.php',
        'populate_db.php',
        'divelist.php',
        'export_csv.php',
        'view_dive.php',
        'edit_dive.php',
        'edit_dive_form.php',
        'fish_manager.php',
        '.htaccess'
    ];
    
    $downloaded_files = [];
    $failed_files = [];
    
    // Create required directories
    $requiredDirs = [
        'uploads', 'uploads/dive_images', 'uploads/fish_images',
        'backups', 'temp'
    ];
    
    foreach ($requiredDirs as $dir) {
        $fullPath = $baseDir . '/' . $dir;
        if (!file_exists($fullPath)) {
            if (!@mkdir($fullPath, 0755, true)) {
                $failed_files[] = $dir . ' (directory creation failed)';
            }
        }
    }
    
    // Download each file
    foreach ($files_to_download as $file) {
        $fileUrl = 'https://raw.githubusercontent.com/sw82/divelog/master/' . $file;
        $fileContent = @file_get_contents($fileUrl);
        
        if ($fileContent !== false) {
            // Handle special case for config.php.example
            $targetFile = $file;
            if ($file === 'config.php.example' && !file_exists($baseDir . '/config.php')) {
                $targetFile = 'config.php';
            }
            
            if (@file_put_contents($baseDir . '/' . $targetFile, $fileContent) !== false) {
                $downloaded_files[] = $targetFile;
            } else {
                $failed_files[] = $file . ' (write failed)';
            }
        } else {
            $failed_files[] = $file . ' (download failed)';
        }
    }
    
    // Store results in session
    $_SESSION['downloaded_files'] = $downloaded_files;
    
    if (count($failed_files) > 0) {
        $_SESSION['failed_files'] = $failed_files;
        $_SESSION['full_package_failed'] = true;
    } else {
        $_SESSION['full_package_downloaded'] = true;
    }
    
    // Redirect to the first step
    header('Location: install.php?step=1&files_downloaded=1');
    exit;
}

// Check if force cleanup requested - aggressive removal of installation markers
if (isset($_GET['force_cleanup'])) {
    // List of possible marker files and remnants
    $filesToRemove = [
        $baseDir . '/.install_complete',
        $baseDir . '/install_complete',
        $baseDir . '/.installation_in_progress',
        $baseDir . '/.installation_started'
    ];
    
    // Try to remove all possible marker files
    $removedFiles = [];
    $failedFiles = [];
    
    foreach ($filesToRemove as $file) {
        if (file_exists($file)) {
            $removed = false;
            
            // Try multiple methods
            // 1. Direct unlink
            if (@unlink($file)) {
                $removed = true;
            } else {
                // 2. Truncate first then unlink
                $fp = @fopen($file, 'w');
                if ($fp) {
                    ftruncate($fp, 0);
                    fclose($fp);
                    if (@unlink($file)) {
                        $removed = true;
                    }
                }
                
                // 3. Replace with empty file
                if (!$removed && @file_put_contents($file, '') !== false) {
                    $removed = true;
                }
                
                // 4. System command
                if (!$removed && function_exists('exec')) {
                    @exec('rm -f ' . escapeshellarg($file));
                    if (!file_exists($file)) {
                        $removed = true;
                    }
                }
            }
            
            if ($removed || !file_exists($file)) {
                $removedFiles[] = $file;
            } else {
                $failedFiles[] = $file;
            }
        }
    }
    
    // Clear all session data that might include installation status
    $_SESSION = array();
    
    // Set a new session
    $_SESSION['reset_performed'] = true;
    $_SESSION['force_cleanup_performed'] = true;
    $_SESSION['force_cleanup_removed'] = $removedFiles;
    $_SESSION['force_cleanup_failed'] = $failedFiles;
    $_SESSION['bypass_installation_check'] = true;
    
    // Generate a new token
    $_SESSION['install_token'] = bin2hex(random_bytes(32));
    
    // Redirect to first step
    header('Location: install.php?step=1&cleanup=done');
    exit;
}

if (!isset($_SESSION['install_token'])) {
    $_SESSION['install_token'] = bin2hex(random_bytes(32));
}

// Initialize form data storage in session if not exists
if (!isset($_SESSION['form_data'])) {
    $_SESSION['form_data'] = [
        'db_host' => 'localhost',
        'db_user' => '',
        'db_pass' => '',
        'db_name' => 'divelog',
        'admin_email' => '',
        'db_port' => 3306,
        'socket_path' => '',
        'use_socket' => false
    ];
}

// Set error reporting for debugging during installation
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define application paths
$baseDir = __DIR__;
$uploadsDir = $baseDir . '/uploads';
$backupsDir = $baseDir . '/backups';
$tempDir = $baseDir . '/temp';
$configFile = $baseDir . '/config.php';
$databaseSetupFile = $baseDir . '/database_setup.sql';

// Define required PHP extensions
$requiredExtensions = [
    'mysqli', 'gd', 'fileinfo', 'zip'
];

// Define required directories to create
$requiredDirs = [
    'uploads', 'uploads/dive_images', 'uploads/fish_images',
    'backups', 'temp'
];

// Check for existing installation
$existingInstallation = null;
if (!isset($_GET['force_new'])) {
    // Check if we should bypass the installation check (after reset)
    if (isset($_SESSION['bypass_installation_check']) && $_SESSION['bypass_installation_check'] === true) {
        $existingInstallation = false;
        // Clear the flag after one use
        unset($_SESSION['bypass_installation_check']);
    } else {
        // Use cached check unless explicitly requesting a refresh
        if (isset($_SESSION['existing_installation_check']) && !isset($_GET['marker_action'])) {
            $existingInstallation = $_SESSION['existing_installation_check'];
        } else {
            $existingInstallation = checkExistingInstallation();
            // Cache the check result
            $_SESSION['existing_installation_check'] = $existingInstallation;
        }
    }
}

// Set up page tracking
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Handle form submissions
$message = '';
$error = '';
$success = false;

// Check for reset notification
$reset_message = '';
if (isset($_SESSION['reset_performed']) && $_SESSION['reset_performed'] === true) {
    $reset_message = "The installation has been completely reset. All settings have been restored to defaults.";
    // Clear the flag so the message appears only once
    $_SESSION['reset_performed'] = false;
}

// Check for marker removal messages
$marker_message = '';
if (isset($_SESSION['marker_removed']) && $_SESSION['marker_removed'] === true) {
    $marker_message = "Installation marker file has been successfully removed.";
    $_SESSION['marker_removed'] = false;
} else if (isset($_SESSION['marker_removal_failed']) && $_SESSION['marker_removal_failed'] === true) {
    $marker_message = "Failed to remove installation marker file. Please check file permissions.";
    
    // Add detailed error information if available
    if (isset($_SESSION['marker_removal_error'])) {
        $errorDetails = $_SESSION['marker_removal_error'];
        $marker_message .= "<div class=\"mt-2 small\">Debugging information:<ul>";
        $marker_message .= "<li>File path: " . htmlspecialchars($errorDetails['file']) . "</li>";
        $marker_message .= "<li>File exists: " . ($errorDetails['exists'] ? 'Yes' : 'No') . "</li>";
        $marker_message .= "<li>File readable: " . ($errorDetails['readable'] ? 'Yes' : 'No') . "</li>";
        $marker_message .= "<li>File writable: " . ($errorDetails['writable'] ? 'Yes' : 'No') . "</li>";
        $marker_message .= "<li>File permissions: " . htmlspecialchars($errorDetails['permissions']) . "</li>";
        $marker_message .= "</ul></div>";
        
        // Clear the error details
        unset($_SESSION['marker_removal_error']);
    }
    
    $_SESSION['marker_removal_failed'] = false;
}

// Check for db file download success
$db_file_message = '';
if (isset($_SESSION['db_file_downloaded']) && $_SESSION['db_file_downloaded'] === true) {
    $db_file_message = "Database setup file successfully downloaded and saved.";
    $_SESSION['db_file_downloaded'] = false;
}

// Check for full package download success
$full_package_message = '';
if (isset($_SESSION['full_package_downloaded']) && $_SESSION['full_package_downloaded'] === true) {
    $full_package_message = "Application files have been successfully downloaded. " .
                           count($_SESSION['downloaded_files']) . " files were downloaded.";
    $_SESSION['full_package_downloaded'] = false;
} else if (isset($_SESSION['full_package_failed']) && $_SESSION['full_package_failed'] === true) {
    $full_package_message = "Failed to download some application files. Please check permissions or try again.";
    $_SESSION['full_package_failed'] = false;
}

// Process form submissions based on current step
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1: // Requirements check
            if (isset($_POST['proceed'])) {
                header('Location: install.php?step=2');
                exit;
            }
            break;
            
        case 2: // Directory setup
            if (isset($_POST['create_dirs'])) {
                $dirResult = createDirectories();
                if ($dirResult === true) {
                    $message = "Directories created successfully!";
                    $success = true;
                } else {
                    $error = "Error creating directories: " . $dirResult;
                }
            } elseif (isset($_POST['proceed'])) {
                header('Location: install.php?step=3');
                exit;
            }
            break;
            
        case 3: // Database configuration
            if (isset($_POST['create_config'])) {
                // Store form values in session
                $_SESSION['form_data']['db_host'] = $_POST['db_host'] ?? 'localhost';
                $_SESSION['form_data']['db_user'] = $_POST['db_user'] ?? '';
                $_SESSION['form_data']['db_pass'] = $_POST['db_pass'] ?? '';
                $_SESSION['form_data']['db_name'] = $_POST['db_name'] ?? 'divelog';
                $_SESSION['form_data']['db_port'] = $_POST['db_port'] ?? 3306;
                $_SESSION['form_data']['socket_path'] = $_POST['socket_path'] ?? '';
                $_SESSION['form_data']['use_socket'] = isset($_POST['use_socket']) && $_POST['use_socket'] == '1';
                
                // Get values from session
                $dbHost = $_SESSION['form_data']['db_host'];
                $dbUser = $_SESSION['form_data']['db_user'];
                $dbPass = $_SESSION['form_data']['db_pass'];
                $dbName = $_SESSION['form_data']['db_name'];
                $dbPort = $_SESSION['form_data']['db_port'];
                $socketPath = $_SESSION['form_data']['socket_path'];
                $useSocket = $_SESSION['form_data']['use_socket'];
                
                // Store additional connection settings in session
                $_SESSION['form_data']['db_port'] = $dbPort;
                $_SESSION['form_data']['socket_path'] = $socketPath;
                $_SESSION['form_data']['use_socket'] = $useSocket;
                
                // Add connection timeout to prevent long hangs
                $conn = null;
                try {
                    // Test database connection with error handling
                    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Enable exceptions
                    
                    // Set socket path if provided
                    if ($useSocket && !empty($socketPath)) {
                        ini_set('mysqli.default_socket', $socketPath);
                    }
                    
                    // Connect with appropriate parameters
                    $conn = new mysqli($dbHost, $dbUser, $dbPass, null, $dbPort);
                    
                    // Create database if it doesn't exist
                    $conn->query("CREATE DATABASE IF NOT EXISTS `$dbName`");
                    
                    // Select the database
                    $conn->select_db($dbName);
                    
                    // Create config file
                    $configResult = createConfigFile($dbHost, $dbUser, $dbPass, $dbName, $socketPath, $dbPort);
                    if ($configResult === true) {
                        $message = "Configuration file created successfully!";
                        $success = true;
                    } else {
                        $error = "Error creating configuration file: " . $configResult;
                    }
                    
                    if ($conn) $conn->close();
                } catch (mysqli_sql_exception $e) {
                    $errorCode = $e->getCode();
                    $errorMessage = $e->getMessage();
                    
                    if (strpos($errorMessage, 'No such file or directory') !== false) {
                        $error = "Cannot connect to MySQL server at '$dbHost'. This could be due to:<br>
                            1. The MySQL server is not running<br>
                            2. The hostname is incorrect<br>
                            3. MySQL is using a non-standard socket location<br><br>
                            Technical details: " . $errorMessage;
                    } else if ($errorCode == 1045) {
                        $error = "Access denied. Please check your database username and password.";
                    } else if ($errorCode == 1044) {
                        $error = "Access denied when selecting the database. Make sure the user has necessary privileges.";
                    } else if ($errorCode == 2002) {
                        $error = "Cannot connect to MySQL server. Please check that the server is running and accessible.";
                    } else {
                        $error = "Database error: " . $errorMessage;
                    }
                }
            } elseif (isset($_POST['proceed'])) {
                header('Location: install.php?step=4');
                exit;
            }
            break;
            
        case 4: // Database setup
            if (isset($_POST['setup_database'])) {
                $setupResult = setupDatabase();
                if ($setupResult === true) {
                    $message = "Database set up successfully!";
                    $success = true;
                } else {
                    $error = "Error setting up database: " . $setupResult;
                }
            } elseif (isset($_POST['proceed'])) {
                header('Location: install.php?step=5');
                exit;
            }
            break;
            
        case 5: // Finalize installation
            if (isset($_POST['finalize'])) {
                $_SESSION['form_data']['admin_email'] = $_POST['admin_email'] ?? '';
                $adminEmail = $_SESSION['form_data']['admin_email'];
                
                $finishResult = finalizeInstallation($adminEmail);
                if ($finishResult === true) {
                    $message = "Installation completed successfully!";
                    $success = true;
                    
                    // Delete the installer
                    if (isset($_POST['delete_installer']) && $_POST['delete_installer'] == 1) {
                        // Schedule deletion on next page load
                        $_SESSION['delete_installer'] = true;
                    }
                } else {
                    $error = "Error finalizing installation: " . $finishResult;
                }
            }
            break;
    }
}

// Handle installer self-deletion
if (isset($_SESSION['delete_installer']) && $_SESSION['delete_installer'] === true) {
    $_SESSION['delete_installer'] = false;
    @unlink(__FILE__);
    header('Location: index.php');
    exit;
}

/**
 * Create required directories
 */
function createDirectories() {
    global $requiredDirs, $baseDir;
    
    try {
        foreach ($requiredDirs as $dir) {
            $fullPath = $baseDir . '/' . $dir;
            if (!file_exists($fullPath)) {
                if (!mkdir($fullPath, 0755, true)) {
                    return "Failed to create directory: $dir";
                }
            } elseif (!is_writable($fullPath)) {
                if (!chmod($fullPath, 0755)) {
                    return "Directory exists but is not writable: $dir";
                }
            }
        }
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

/**
 * Check for existing installation
 */
function checkExistingInstallation() {
    global $configFile, $baseDir;
    
    $installStatus = [
        'config_exists' => false,
        'db_connected' => false,
        'tables_exist' => false,
        'directories_exist' => false,
        'complete_marker' => false,
        'details' => [],
        'inconsistent' => false
    ];
    
    // Check if config file exists
    if (file_exists($configFile)) {
        $installStatus['config_exists'] = true;
        $installStatus['details'][] = "Configuration file exists";
        
        // Try to connect to the database
        try {
            // Include config file safely with @ to suppress warning messages
            if (@include($configFile)) {
                $installStatus['db_connected'] = true;
                $installStatus['details'][] = "Database connection successful";
                
                // Check if tables exist
                $tables = ['divelogs', 'divelog_images', 'fish_species', 'fish_sightings'];
                $existingTables = [];
                
                foreach ($tables as $table) {
                    $result = $conn->query("SHOW TABLES LIKE '$table'");
                    if ($result && $result->num_rows > 0) {
                        $existingTables[] = $table;
                    }
                }
                
                if (count($existingTables) > 0) {
                    $installStatus['tables_exist'] = true;
                    $installStatus['details'][] = "Found existing tables: " . implode(", ", $existingTables);
                }
            } else {
                $installStatus['details'][] = "Config file exists but could not be loaded";
            }
        } catch (Exception $e) {
            $installStatus['details'][] = "Error connecting to database: " . $e->getMessage();
        }
    } else {
        $installStatus['details'][] = "No configuration file found";
    }
    
    // Check if installation complete marker exists
    $markerFile = $baseDir . '/.install_complete';
    if (file_exists($markerFile)) {
        $installStatus['complete_marker'] = true;
        $markerContent = file_get_contents($markerFile);
        
        // Check for future dates in marker file (potential timezone issue)
        $markerDate = trim(strtok($markerContent, "\n"));
        if (!empty($markerDate) && strtotime($markerDate) !== false) {
            $now = time();
            $markerTimestamp = strtotime($markerDate);
            
            if ($markerTimestamp > $now) {
                $installStatus['details'][] = "WARNING: Installation marker contains a future date ($markerDate). This may indicate a timezone or server clock issue.";
            }
        }
        
        $markerDetails = "Installation marker found at: " . $markerFile;
        $markerDetails .= $markerContent ? " (created: " . trim(strtok($markerContent, "\n")) . ")" : "";
        $markerDetails .= " [File size: " . filesize($markerFile) . " bytes]";
        $installStatus['details'][] = $markerDetails;
        
        // Add warning about potential false detection
        if (!is_readable($markerFile)) {
            $installStatus['details'][] = "WARNING: Marker file exists but is not readable - permissions issue?";
        }
        
        // Check for inconsistent state (marker exists but no config)
        if (!$installStatus['config_exists']) {
            $installStatus['inconsistent'] = true;
            $installStatus['details'][] = "INCONSISTENT STATE: Installation marker exists but no configuration file found. This suggests an incomplete or corrupted installation.";
        }
    } else {
        // Double check using different methods to detect marker issues
        $allFiles = scandir($baseDir);
        if (in_array('.install_complete', $allFiles)) {
            $installStatus['details'][] = "WARNING: Marker file detected in directory listing but file_exists() failed";
        }
    }
    
    // Check if directories exist
    global $requiredDirs;
    $existingDirs = [];
    foreach ($requiredDirs as $dir) {
        if (file_exists($baseDir . '/' . $dir)) {
            $existingDirs[] = $dir;
        }
    }
    
    if (count($existingDirs) > 0) {
        $installStatus['directories_exist'] = true;
        $installStatus['details'][] = "Found existing directories: " . implode(", ", $existingDirs);
    }
    
    return $installStatus;
}

/**
 * Create configuration file
 */
function createConfigFile($host, $user, $pass, $name, $socket = null, $port = 3306) {
    global $configFile;
    
    try {
        $socketConfig = '';
        if (!empty($socket)) {
            $socketConfig = "\n// Socket path configuration
ini_set('mysqli.default_socket', '" . addslashes($socket) . "');";
        }
        
        $portConfig = '';
        if ($port != 3306) {
            $portConfig = ", " . (int)$port;
        } else {
            $portConfig = '';
        }
        
        $configContent = "<?php
// Database configuration
define('DB_HOST', '" . addslashes($host) . "');
define('DB_USER', '" . addslashes($user) . "');
define('DB_PASS', '" . addslashes($pass) . "');
define('DB_NAME', '" . addslashes($name) . "');
define('DB_PORT', " . (int)$port . ");$socketConfig

// Create connection
\$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME$portConfig);

// Check connection
if (\$conn->connect_error) {
  die(\"Connection failed: \" . \$conn->connect_error);
}

// Set charset
\$conn->set_charset('utf8mb4');
?>";

        if (file_put_contents($configFile, $configContent) === false) {
            return "Failed to write config file";
        }
        
        // Set appropriate permissions
        chmod($configFile, 0644);
        
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

/**
 * Set up the database
 */
function setupDatabase() {
    global $databaseSetupFile, $configFile;
    
    try {
        // Include the config file to get DB connection
        if (!file_exists($configFile)) {
            return "Config file not found. Please complete the database configuration step first.";
        }
        
        // Include config file safely
        if (!@include($configFile)) {
            return "Could not load the config file. Please check file permissions.";
        }
        
        // Check if database file exists
        if (!file_exists($databaseSetupFile)) {
            // Try to download from GitHub repository
            if (isset($_POST['download_setup_file']) && $_POST['download_setup_file'] == '1') {
                $setupFileUrl = 'https://raw.githubusercontent.com/sw82/divelog/master/database_setup.sql';
                $setupFileContent = @file_get_contents($setupFileUrl);
                
                if ($setupFileContent !== false) {
                    // Save the file
                    if (file_put_contents($databaseSetupFile, $setupFileContent) !== false) {
                        // Successfully downloaded and saved
                        $_SESSION['db_file_downloaded'] = true;
                        return true;
                    } else {
                        return "download_failed:Failed to save database_setup.sql file. Please check directory permissions.";
                    }
                } else {
                    return "download_failed:Failed to download database_setup.sql file. Please upload it manually.";
                }
            } else {
                return "file_missing:Database setup file not found. Please place your database_setup.sql file in the application's root directory.";
            }
        }
        
        // Check for existing tables
        $existingTablesCheck = $conn->query("SHOW TABLES");
        $existingTables = [];
        
        if ($existingTablesCheck && $existingTablesCheck->num_rows > 0) {
            while ($row = $existingTablesCheck->fetch_row()) {
                $existingTables[] = $row[0];
            }
        }
        
        // If tables already exist, ask for confirmation before proceeding
        if (!empty($existingTables)) {
            if (!isset($_POST['confirm_overwrite']) || $_POST['confirm_overwrite'] != '1') {
                return "existing_tables:" . implode(',', $existingTables);
            }
            
            // User confirmed overwrite - first back up the current database
            if (isset($_POST['backup_before_overwrite']) && $_POST['backup_before_overwrite'] == '1') {
                $backupFile = __DIR__ . '/backups/pre_reinstall_backup_' . date('Y-m-d_H-i-s') . '.sql';
                $tables = implode(' ', $existingTables);
                $command = "mysqldump -h " . DB_HOST . " -u " . DB_USER . " -p'" . DB_PASS . "' " . DB_NAME . " $tables > $backupFile";
                exec($command, $output, $returnVar);
                
                if ($returnVar !== 0) {
                    // Backup failed, but we'll continue with installation if user confirmed
                    error_log("Backup before reinstall failed: " . implode("\n", $output));
                }
            }
        }
        
        // Read SQL file
        $sql = file_get_contents($databaseSetupFile);
        if (!$sql) {
            return "Could not read database setup file";
        }
        
        // Split into separate queries
        $queries = explode(';', $sql);
        
        // Execute each query
        foreach ($queries as $query) {
            $query = trim($query);
            if (empty($query)) continue;
            
            if (!$conn->query($query)) {
                return "Error executing query: " . $conn->error;
            }
        }
        
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

/**
 * Finalize the installation
 */
function finalizeInstallation($adminEmail) {
    global $baseDir, $configFile;
    
    try {
        if (!file_exists($configFile)) {
            return "Config file not found. Please complete the database configuration step first.";
        }
        
        // Create additional files if needed
        $installCompletePath = $baseDir . '/.install_complete';
        file_put_contents($installCompletePath, date('Y-m-d H:i:s') . "\nAdmin: " . $adminEmail);
        
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

/**
 * Check if a PHP extension is loaded
 */
function checkExtension($name) {
    return extension_loaded($name);
}

/**
 * Check if directory is writable
 */
function checkDirWritable($dir) {
    return is_writable($dir);
}

/**
 * Check if PHP version is compatible
 */
function checkPHPVersion() {
    return version_compare(PHP_VERSION, '8.0.0', '>=');
}

/**
 * Check if max_execution_time is sufficient
 */
function checkExecutionTime() {
    $current = ini_get('max_execution_time');
    return ($current == 0 || $current >= 300); // 0 = unlimited
}

/**
 * Check if file_uploads is enabled
 */
function checkFileUploads() {
    return ini_get('file_uploads') == 1;
}

/**
 * Check if upload_max_filesize is sufficient
 */
function checkUploadSize() {
    $value = ini_get('upload_max_filesize');
    $number = (int)$value;
    $unit = strtolower(substr($value, -1));
    
    // Convert to MB
    switch ($unit) {
        case 'g': $number *= 1024; break;
        case 'k': $number /= 1024; break;
        case 'm': break; // already in MB
        default: $number /= 1048576; // bytes to MB
    }
    
    return $number >= 10;
}

// HTML output starts here
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Divelog Installer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding-top: 2rem;
            padding-bottom: 2rem;
            background-color: #f8f9fa;
        }
        .installer-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }
        .step-indicator {
            display: flex;
            margin-bottom: 2rem;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            position: relative;
        }
        .step:not(:last-child):after {
            content: '';
            position: absolute;
            top: 50%;
            right: 0;
            width: 100%;
            height: 2px;
            background-color: #dee2e6;
            transform: translateY(-50%);
            z-index: 1;
        }
        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            line-height: 30px;
            border-radius: 50%;
            background-color: #dee2e6;
            color: #6c757d;
            position: relative;
            z-index: 2;
        }
        .step.active .step-number {
            background-color: #0d6efd;
            color: #fff;
        }
        .step.completed .step-number {
            background-color: #198754;
            color: #fff;
        }
        .check-item {
            padding: 0.5rem 0;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #f0f0f0;
        }
        .header-logo {
            max-width: 100px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="installer-container">
            <div class="text-center mb-4">
                <h1>Divelog Installer</h1>
                <p class="text-muted">Follow the steps to install your Divelog application</p>
            </div>

            <!-- Step indicator -->
            <div class="step-indicator">
                <div class="step <?php echo ($step >= 1) ? 'active' : ''; ?> <?php echo ($step > 1) ? 'completed' : ''; ?>">
                    <div class="step-number">1</div>
                    <div class="step-title">Requirements</div>
                </div>
                <div class="step <?php echo ($step >= 2) ? 'active' : ''; ?> <?php echo ($step > 2) ? 'completed' : ''; ?>">
                    <div class="step-number">2</div>
                    <div class="step-title">Directories</div>
                </div>
                <div class="step <?php echo ($step >= 3) ? 'active' : ''; ?> <?php echo ($step > 3) ? 'completed' : ''; ?>">
                    <div class="step-number">3</div>
                    <div class="step-title">Database</div>
                </div>
                <div class="step <?php echo ($step >= 4) ? 'active' : ''; ?> <?php echo ($step > 4) ? 'completed' : ''; ?>">
                    <div class="step-number">4</div>
                    <div class="step-title">Setup</div>
                </div>
                <div class="step <?php echo ($step >= 5) ? 'active' : ''; ?> <?php echo ($step > 5) ? 'completed' : ''; ?>">
                    <div class="step-number">5</div>
                    <div class="step-title">Complete</div>
                </div>
            </div>

            <?php if ($existingInstallation): ?>
                <div class="alert alert-warning mb-4">
                    <h4 class="alert-heading">Existing Installation Detected!</h4>
                    <p>The installer has detected an existing Divelog installation:</p>
                    <ul>
                        <?php foreach($existingInstallation['details'] as $detail): ?>
                            <li><?php echo htmlspecialchars($detail); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <?php if (isset($_SESSION['show_dir_contents']) && $_SESSION['show_dir_contents']): ?>
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Directory Contents</h5>
                        </div>
                        <div class="card-body">
                            <p>All files in installation directory (including hidden files):</p>
                            <ul class="mb-0">
                                <?php 
                                $allFiles = scandir($baseDir);
                                foreach ($allFiles as $file) {
                                    $isHidden = substr($file, 0, 1) === '.';
                                    $fileSize = is_file($baseDir . '/' . $file) ? filesize($baseDir . '/' . $file) : 'directory';
                                    echo '<li>' . ($isHidden ? '<strong>' : '') . 
                                         htmlspecialchars($file) . ($isHidden ? '</strong>' : '') . 
                                         ' (' . $fileSize . ' bytes)</li>';
                                }
                                ?>
                            </ul>
                        </div>
                    </div>
                    <?php 
                    // Clear the flag so it only shows once
                    $_SESSION['show_dir_contents'] = false;
                    ?>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <p>You have the following options:</p>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="?force_new=1" class="btn btn-warning">Ignore and Continue Installation</a>
                            <?php if (!isset($existingInstallation['inconsistent']) || !$existingInstallation['inconsistent']): ?>
                            <a href="index.php" class="btn btn-primary">Go to Existing Installation</a>
                            <?php endif; ?>
                            <a href="?reset=1" class="btn btn-danger">Start Fresh Installation</a>
                            <?php if (isset($existingInstallation['complete_marker']) && $existingInstallation['complete_marker']): ?>
                            <a href="?clean_marker=1" class="btn btn-outline-secondary">Remove Installation Marker</a>
                            <?php endif; ?>
                            <a href="?list_dir=1" class="btn btn-outline-info">Show Directory Contents</a>
                        </div>
                        <div class="mt-2 small text-muted">
                            <ul class="mb-0">
                                <li><strong>Ignore and Continue:</strong> Proceeds with installation while keeping existing files</li>
                                <?php if (!isset($existingInstallation['inconsistent']) || !$existingInstallation['inconsistent']): ?>
                                <li><strong>Go to Existing Installation:</strong> Exits the installer and opens your current application</li>
                                <?php endif; ?>
                                <li><strong>Start Fresh:</strong> Resets all installation progress and begins from step 1 with default settings</li>
                                <?php if (isset($existingInstallation['complete_marker']) && $existingInstallation['complete_marker']): ?>
                                <li><strong>Remove Installation Marker:</strong> Deletes the hidden .install_complete file only</li>
                                <?php endif; ?>
                                <li><strong>Show Directory Contents:</strong> Displays all files in the directory, including hidden files</li>
                            </ul>
                        </div>
                        
                        <?php if (isset($existingInstallation['inconsistent']) && $existingInstallation['inconsistent']): ?>
                        <div class="alert alert-danger mt-3">
                            <h5 class="alert-heading">Inconsistent Installation Detected!</h5>
                            <p>Your installation appears to be incomplete or corrupted. The installation marker file exists, but no configuration file was found.</p>
                            <p><strong>Recommended action:</strong> Click "Remove Installation Marker" to clear the incomplete installation state, then proceed with a fresh installation.</p>
                            <div class="mt-3">
                                <a href="?force_cleanup=1" class="btn btn-danger">Force Complete Cleanup</a>
                                <small class="text-muted ms-2">Use this option if normal cleanup methods aren't working</small>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['force_cleanup_performed']) && $_SESSION['force_cleanup_performed']): ?>
                        <div class="alert alert-success mt-3">
                            <h5 class="alert-heading">Force Cleanup Performed</h5>
                            <?php if (!empty($_SESSION['force_cleanup_removed'])): ?>
                            <p>Successfully removed the following files:</p>
                            <ul>
                                <?php foreach ($_SESSION['force_cleanup_removed'] as $file): ?>
                                <li><?php echo htmlspecialchars(basename($file)); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p>No marker files were found to remove.</p>
                            <?php endif; ?>
                            
                            <?php if (!empty($_SESSION['force_cleanup_failed'])): ?>
                            <div class="alert alert-warning">
                                <p>Failed to remove these files:</p>
                                <ul>
                                    <?php foreach ($_SESSION['force_cleanup_failed'] as $file): ?>
                                    <li><?php echo htmlspecialchars(basename($file)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <p>You may need to manually remove these files via FTP or shell access.</p>
                            </div>
                            <?php endif; ?>
                            
                            <p>The installer has been reset and should now function properly.</p>
                        </div>
                        <?php unset($_SESSION['force_cleanup_performed'], $_SESSION['force_cleanup_removed'], $_SESSION['force_cleanup_failed']); ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($reset_message)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-arrow-repeat"></i> <?php echo $reset_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($marker_message)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-check-circle"></i> <?php echo $marker_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($db_file_message)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-check-circle"></i> <?php echo $db_file_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($full_package_message)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-check-circle"></i> <?php echo $full_package_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Step content -->
            <?php if ($step == 1): ?>
                <!-- Welcome Step -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h2>Welcome to Divelog Installation</h2>
                                <p>This installer will walk you through setting up your Divelog application.</p>
                                
                                <?php if (!empty($marker_message)): ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-check-circle"></i> <?php echo $marker_message; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($db_file_message)): ?>
                                    <div class="alert alert-success">
                                        <i class="bi bi-check-circle"></i> <?php echo $db_file_message; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($full_package_message)): ?>
                                    <div class="alert alert-success">
                                        <i class="bi bi-check-circle"></i> <?php echo $full_package_message; ?>
                                    </div>
                                    
                                    <?php if (isset($_SESSION['failed_files']) && is_array($_SESSION['failed_files']) && count($_SESSION['failed_files']) > 0): ?>
                                        <div class="alert alert-warning">
                                            <p><strong>Warning:</strong> Some files could not be downloaded:</p>
                                            <ul class="mb-0">
                                                <?php foreach ($_SESSION['failed_files'] as $failed_file): ?>
                                                    <li><?php echo htmlspecialchars($failed_file); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <?php unset($_SESSION['failed_files']); ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if ($existingInstallation): ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle"></i> An existing installation was detected.
                                        <div class="mt-2">
                                            <a href="?force_new=1" class="btn btn-sm btn-warning">Force New Installation</a>
                                            <a href="?clean_marker=1" class="btn btn-sm btn-danger">Remove Installation Marker</a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-primary">
                                        <i class="bi bi-info-circle"></i> Before proceeding, make sure you have:
                                        <ul>
                                            <li>A MySQL database and valid credentials</li>
                                            <li>All the required application files</li>
                                        </ul>
                                        
                                        <div class="mt-3">
                                            <p><strong>Missing files?</strong> You can download them automatically:</p>
                                            <div class="d-flex gap-2">
                                                <a href="?download_all=1" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-download"></i> Download All Required Files
                                                </a>
                                                <form method="post" action="?step=3">
                                                    <input type="hidden" name="download_setup_file" value="1">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-file-earmark-code"></i> Download Database Setup File Only
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-4 d-flex justify-content-between">
                                    <div></div>
                                    <a href="?step=2" class="btn btn-primary">Next: Database Configuration</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($step == 2): ?>
                <!-- Step 2: Directory Setup -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Directory Setup</h5>
                    </div>
                    <div class="card-body">
                        <p>The installer will create the following directories:</p>
                        <ul>
                            <?php foreach ($requiredDirs as $dir): ?>
                                <li>
                                    <?php echo $dir; ?>
                                    <?php if (file_exists($baseDir . '/' . $dir)): ?>
                                        <?php if (is_writable($baseDir . '/' . $dir)): ?>
                                            <span class="badge bg-success">Exists & Writable</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Exists but Not Writable</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-info">Will be created</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <form method="post" action="?step=2">
                    <div class="d-flex justify-content-between">
                        <div>
                            <button type="submit" name="create_dirs" class="btn btn-info">
                                Create Directories
                            </button>
                        </div>
                        <div>
                            <a href="?step=1" class="btn btn-secondary me-2">Back</a>
                            <a href="?reset=1" class="btn btn-outline-secondary me-2">Reset</a>
                            <button type="submit" name="proceed" class="btn btn-primary" <?php echo !$success && !file_exists($uploadsDir) ? 'disabled' : ''; ?>>
                                Continue
                            </button>
                        </div>
                    </div>
                </form>

            <?php elseif ($step == 3): ?>
                <!-- Step 3: Database Configuration -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Database Configuration</h5>
                    </div>
                    <div class="card-body">
                        <p>Enter your database connection details:</p>
                        
                        <div class="alert alert-info">
                            <p><strong>Need database setup?</strong> <a href="download.php" class="btn btn-sm btn-primary">Download database_setup.sql</a> and import it to your MySQL server before continuing.</p>
                        </div>

                        <form method="post" action="?step=3">
                            <div class="mb-3">
                                <label for="db_host" class="form-label">Database Host</label>
                                <input type="text" class="form-control" id="db_host" name="db_host" value="<?php echo htmlspecialchars($_SESSION['form_data']['db_host']); ?>" required>
                                <div class="form-text">Usually 'localhost' or an IP address. For shared hosting, check with your provider.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="db_user" class="form-label">Database Username</label>
                                <input type="text" class="form-control" id="db_user" name="db_user" value="<?php echo htmlspecialchars($_SESSION['form_data']['db_user']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="db_pass" class="form-label">Database Password</label>
                                <input type="password" class="form-control" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($_SESSION['form_data']['db_pass']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="db_name" class="form-label">Database Name</label>
                                <input type="text" class="form-control" id="db_name" name="db_name" value="<?php echo htmlspecialchars($_SESSION['form_data']['db_name']); ?>" required>
                                <div class="form-text">If the database doesn't exist, the installer will attempt to create it.</div>
                            </div>
                            
                            <div class="mb-3">
                                <a class="btn btn-link p-0" data-bs-toggle="collapse" href="#advancedOptions" role="button" aria-expanded="false" aria-controls="advancedOptions">
                                    <i class="bi bi-gear-fill"></i> Advanced Options
                                </a>
                                <div class="collapse mt-2" id="advancedOptions">
                                    <div class="card card-body bg-light">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="useSocket" name="use_socket" value="1">
                                            <label class="form-check-label" for="useSocket">
                                                Use custom MySQL socket path
                                            </label>
                                        </div>
                                        <div class="mb-3 socket-path" style="display: none;">
                                            <label for="socketPath" class="form-label">MySQL Socket Path</label>
                                            <input type="text" class="form-control" id="socketPath" name="socket_path" placeholder="/var/run/mysqld/mysqld.sock">
                                            <div class="form-text">Common paths: /var/run/mysqld/mysqld.sock, /tmp/mysql.sock, /var/lib/mysql/mysql.sock</div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="dbPort" class="form-label">MySQL Port</label>
                                            <input type="number" class="form-control" id="dbPort" name="db_port" value="3306">
                                            <div class="form-text">Default is 3306. Change only if your MySQL server uses a different port.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <div>
                                    <button type="submit" name="create_config" class="btn btn-info">
                                        Test Connection & Create Config
                                    </button>
                                </div>
                                <div>
                                    <a href="?step=2" class="btn btn-secondary me-2">Back</a>
                                    <a href="?reset=1" class="btn btn-outline-secondary me-2">Reset</a>
                                    <button type="submit" name="proceed" class="btn btn-primary" <?php echo !$success && !file_exists($configFile) ? 'disabled' : ''; ?>>
                                        Continue
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
            <?php elseif ($step == 4): ?>
                <!-- Step 4: Database Setup -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Database Setup</h5>
                    </div>
                    <div class="card-body">
                        <p>Now the installer will set up the database tables required for the Divelog application.</p>
                        
                        <?php
                        // Check for specific error messages
                        $setupErrorType = '';
                        $setupErrorMessage = '';
                        
                        if (strpos($error, 'download_failed:') === 0) {
                            $setupErrorType = 'download_failed';
                            $setupErrorMessage = substr($error, strlen('download_failed:'));
                            // Clear the general error so we can handle it specially
                            $error = '';
                        } else if (strpos($error, 'file_missing:') === 0) {
                            $setupErrorType = 'file_missing';
                            $setupErrorMessage = substr($error, strlen('file_missing:'));
                            // Clear the general error so we can handle it specially
                            $error = '';
                        }
                        
                        // Check if the error message indicates existing tables
                        if (strpos($error, 'existing_tables:') === 0) {
                            $tableList = substr($error, strlen('existing_tables:'));
                            $existingTableArray = explode(',', $tableList);
                        ?>
                            <div class="alert alert-warning">
                                <h5>Existing Database Tables Detected!</h5>
                                <p>The following tables already exist in the database:</p>
                                <ul>
                                    <?php foreach ($existingTableArray as $table): ?>
                                        <li><?php echo htmlspecialchars($table); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <p>How would you like to proceed?</p>
                                
                                <form method="post" action="?step=4" class="mt-3">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="confirm_overwrite" value="1" id="confirmOverwrite" required>
                                        <label class="form-check-label" for="confirmOverwrite">
                                            Yes, I understand this will overwrite existing data. Continue with installation.
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="backup_before_overwrite" value="1" id="backupBeforeOverwrite" checked>
                                        <label class="form-check-label" for="backupBeforeOverwrite">
                                            Create a backup of existing data before proceeding
                                        </label>
                                    </div>
                                    
                                    <button type="submit" name="setup_database" class="btn btn-warning">
                                        Proceed with Overwrite
                                    </button>
                                </form>
                            </div>
                        <?php } else if ($setupErrorType == 'file_missing' || $setupErrorType == 'download_failed') { ?>
                            <div class="alert <?php echo $setupErrorType == 'download_failed' ? 'alert-danger' : 'alert-warning'; ?>">
                                <h5><?php echo $setupErrorMessage; ?></h5>
                                
                                <div class="mt-3">
                                    <p>You have the following options:</p>
                                    
                                    <form method="post" action="?step=4" class="mb-3">
                                        <input type="hidden" name="download_setup_file" value="1">
                                        <button type="submit" class="btn btn-primary">
                                            Download Database Setup File
                                        </button>
                                    </form>
                                    
                                    <p>Or upload the file manually:</p>
                                    <ol>
                                        <li>Download the <a href="https://raw.githubusercontent.com/sw82/divelog/master/database_setup.sql" target="_blank">database_setup.sql</a> file to your computer</li>
                                        <li>Upload it to your server in the same directory as install.php</li>
                                        <li>Refresh this page</li>
                                    </ol>
                                </div>
                            </div>
                        <?php } else { ?>
                            <?php if (file_exists($databaseSetupFile)): ?>
                                <div class="alert alert-info">
                                    Database setup file found: <?php echo basename($databaseSetupFile); ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    Database setup file not found. Please place your database_setup.sql file in the application's root directory.
                                    <form method="post" action="?step=4" class="mt-3">
                                        <input type="hidden" name="download_setup_file" value="1">
                                        <button type="submit" class="btn btn-primary">Download Database Setup File</button>
                                    </form>
                                    <div class="mt-2">
                                        <small class="text-muted">This will download the latest database_setup.sql file from the GitHub repository.</small>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <form method="post" action="?step=4">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <button type="submit" name="setup_database" class="btn btn-info" <?php echo !file_exists($databaseSetupFile) ? 'disabled' : ''; ?>>
                                            Set Up Database
                                        </button>
                                    </div>
                                    <div>
                                        <a href="?step=3" class="btn btn-secondary me-2">Back</a>
                                        <a href="?reset=1" class="btn btn-outline-secondary me-2">Reset</a>
                                        <button type="submit" name="proceed" class="btn btn-primary" <?php echo !$success && !file_exists($configFile) ? 'disabled' : ''; ?>>
                                            Continue
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php } ?>
                    </div>
                </div>
                
            <?php elseif ($step == 5): ?>
                <!-- Step 5: Finalize Installation -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Complete Installation</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success">
                            <h4 class="alert-heading">Almost there!</h4>
                            <p>You're about to complete the installation of the Divelog application.</p>
                        </div>
                        
                        <form method="post" action="?step=5">
                            <div class="mb-3">
                                <label for="admin_email" class="form-label">Admin Email (Optional)</label>
                                <input type="email" class="form-control" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($_SESSION['form_data']['admin_email']); ?>" placeholder="your@email.com">
                                <div class="form-text">This will be stored for future reference.</div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="delete_installer" name="delete_installer" value="1" checked>
                                <label class="form-check-label" for="delete_installer">Delete installer after completion (recommended)</label>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <div>
                                    <a href="?step=4" class="btn btn-secondary">Back</a>
                                    <a href="?reset=1" class="btn btn-outline-secondary">Reset</a>
                                </div>
                                <div>
                                    <button type="submit" name="finalize" class="btn btn-success">
                                        Complete Installation
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <h4 class="alert-heading">Installation Completed!</h4>
                        <p>The Divelog application has been successfully installed on your server.</p>
                        <hr>
                        <p class="mb-0">
                            <a href="index.php" class="btn btn-primary">Go to Application</a>
                        </p>
                    </div>
                <?php endif; ?>
                
            <?php endif; ?>
            
            <div class="mt-4 text-center">
                <small class="text-muted">Divelog Installer v1.0</small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Check if we need to clear local storage (after reset)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('cleared')) {
            // Clean sweep of ALL localStorage in case there are other keys we might miss
            localStorage.clear();
            console.log("Installation storage has been cleared");
        }
        
        // Toggle socket path field visibility
        const useSocketCheckbox = document.getElementById('useSocket');
        const socketPathField = document.querySelector('.socket-path');
        
        if (useSocketCheckbox && socketPathField) {
            // Set initial state based on PHP session data
            <?php if (isset($_SESSION['form_data']['use_socket']) && $_SESSION['form_data']['use_socket']): ?>
            useSocketCheckbox.checked = true;
            socketPathField.style.display = 'block';
            <?php endif; ?>
            
            useSocketCheckbox.addEventListener('change', function() {
                socketPathField.style.display = this.checked ? 'block' : 'none';
            });
        }
        
        // Store form values in browser storage for better UX
        const formFields = document.querySelectorAll('input[type="text"], input[type="password"], input[type="email"], input[type="number"]');
        formFields.forEach(field => {
            field.addEventListener('change', function() {
                if (field.type !== 'password') { // Don't store passwords in localStorage
                    localStorage.setItem('divelog_installer_' + field.name, field.value);
                }
            });
            
            // Restore from localStorage if not already set
            const storedValue = localStorage.getItem('divelog_installer_' + field.name);
            if (storedValue && field.value === '' && field.type !== 'password') {
                field.value = storedValue;
            }
        });
    });
    </script>
</body>
</html> 