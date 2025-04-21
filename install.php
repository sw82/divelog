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
if (!isset($_SESSION['install_token'])) {
    $_SESSION['install_token'] = bin2hex(random_bytes(32));
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

// Set up page tracking
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Handle form submissions
$message = '';
$error = '';
$success = false;

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
                $dbHost = $_POST['db_host'] ?? 'localhost';
                $dbUser = $_POST['db_user'] ?? '';
                $dbPass = $_POST['db_pass'] ?? '';
                $dbName = $_POST['db_name'] ?? '';
                
                // Test database connection
                $conn = @new mysqli($dbHost, $dbUser, $dbPass);
                if ($conn->connect_error) {
                    $error = "Database connection failed: " . $conn->connect_error;
                } else {
                    // Create database if it doesn't exist
                    $conn->query("CREATE DATABASE IF NOT EXISTS `$dbName`");
                    
                    // Select the database
                    $conn->select_db($dbName);
                    
                    // Create config file
                    $configResult = createConfigFile($dbHost, $dbUser, $dbPass, $dbName);
                    if ($configResult === true) {
                        $message = "Configuration file created successfully!";
                        $success = true;
                    } else {
                        $error = "Error creating configuration file: " . $configResult;
                    }
                    
                    $conn->close();
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
                $adminEmail = $_POST['admin_email'] ?? '';
                
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
 * Create configuration file
 */
function createConfigFile($host, $user, $pass, $name) {
    global $configFile;
    
    try {
        $configContent = "<?php
// Database configuration
define('DB_HOST', '" . addslashes($host) . "');
define('DB_USER', '" . addslashes($user) . "');
define('DB_PASS', '" . addslashes($pass) . "');
define('DB_NAME', '" . addslashes($name) . "');

// Create connection
\$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

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
 * Set up database tables using SQL file
 */
function setupDatabase() {
    global $databaseSetupFile, $configFile;
    
    try {
        // Include the config file to get DB connection
        if (!file_exists($configFile)) {
            return "Config file not found";
        }
        
        require $configFile;
        
        // Check if database file exists
        if (!file_exists($databaseSetupFile)) {
            return "Database setup file not found";
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
            return "Config file not found";
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
                <!-- Step 1: Requirements Check -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">System Requirements Check</h5>
                    </div>
                    <div class="card-body">
                        <div class="check-item">
                            <span>PHP Version (>= 8.0.0)</span>
                            <span>
                                <?php $phpVersionCheck = checkPHPVersion(); ?>
                                <?php if ($phpVersionCheck): ?>
                                    <span class="badge bg-success">Yes (<?php echo PHP_VERSION; ?>)</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">No (<?php echo PHP_VERSION; ?>)</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php foreach ($requiredExtensions as $ext): ?>
                            <div class="check-item">
                                <span>PHP Extension: <?php echo $ext; ?></span>
                                <span>
                                    <?php $extCheck = checkExtension($ext); ?>
                                    <?php if ($extCheck): ?>
                                        <span class="badge bg-success">Enabled</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Missing</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="check-item">
                            <span>Max Execution Time (>= 300s)</span>
                            <span>
                                <?php $timeCheck = checkExecutionTime(); ?>
                                <?php if ($timeCheck): ?>
                                    <span class="badge bg-success">OK (<?php echo ini_get('max_execution_time'); ?>s)</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Low (<?php echo ini_get('max_execution_time'); ?>s)</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="check-item">
                            <span>File Uploads Enabled</span>
                            <span>
                                <?php $uploadsCheck = checkFileUploads(); ?>
                                <?php if ($uploadsCheck): ?>
                                    <span class="badge bg-success">Enabled</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Disabled</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="check-item">
                            <span>Upload Max Filesize (>= 10MB)</span>
                            <span>
                                <?php $sizeCheck = checkUploadSize(); ?>
                                <?php if ($sizeCheck): ?>
                                    <span class="badge bg-success">OK (<?php echo ini_get('upload_max_filesize'); ?>)</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Low (<?php echo ini_get('upload_max_filesize'); ?>)</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="check-item">
                            <span>Base Directory Writable</span>
                            <span>
                                <?php $dirCheck = checkDirWritable($baseDir); ?>
                                <?php if ($dirCheck): ?>
                                    <span class="badge bg-success">Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">No</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <form method="post" action="?step=1">
                    <div class="d-flex justify-content-between">
                        <div>
                            <?php
                            $canProceed = checkPHPVersion() && 
                                         checkDirWritable($baseDir) && 
                                         checkFileUploads();
                            
                            // Check required extensions
                            foreach ($requiredExtensions as $ext) {
                                if (!checkExtension($ext)) {
                                    $canProceed = false;
                                    break;
                                }
                            }
                            ?>
                            
                            <?php if (!$canProceed): ?>
                                <div class="alert alert-warning">
                                    Some requirements are not met. The installation may not function correctly.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <button type="submit" name="proceed" class="btn btn-primary" <?php echo !$canProceed ? 'disabled' : ''; ?>>
                                Continue
                            </button>
                        </div>
                    </div>
                </form>

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
                        
                        <form method="post" action="?step=3">
                            <div class="mb-3">
                                <label for="db_host" class="form-label">Database Host</label>
                                <input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="db_user" class="form-label">Database Username</label>
                                <input type="text" class="form-control" id="db_user" name="db_user" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="db_pass" class="form-label">Database Password</label>
                                <input type="password" class="form-control" id="db_pass" name="db_pass">
                            </div>
                            
                            <div class="mb-3">
                                <label for="db_name" class="form-label">Database Name</label>
                                <input type="text" class="form-control" id="db_name" name="db_name" value="divelog" required>
                                <div class="form-text">If the database doesn't exist, the installer will attempt to create it.</div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <div>
                                    <button type="submit" name="create_config" class="btn btn-info">
                                        Test Connection & Create Config
                                    </button>
                                </div>
                                <div>
                                    <a href="?step=2" class="btn btn-secondary me-2">Back</a>
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
                        
                        <?php if (file_exists($databaseSetupFile)): ?>
                            <div class="alert alert-info">
                                Database setup file found: <?php echo basename($databaseSetupFile); ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                Database setup file not found. Please place your database_setup.sql file in the application's root directory.
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
                                    <button type="submit" name="proceed" class="btn btn-primary" <?php echo !$success && !file_exists($configFile) ? 'disabled' : ''; ?>>
                                        Continue
                                    </button>
                                </div>
                            </div>
                        </form>
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
                                <input type="email" class="form-control" id="admin_email" name="admin_email" placeholder="your@email.com">
                                <div class="form-text">This will be stored for future reference.</div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="delete_installer" name="delete_installer" value="1" checked>
                                <label class="form-check-label" for="delete_installer">Delete installer after completion (recommended)</label>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <div>
                                    <a href="?step=4" class="btn btn-secondary">Back</a>
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
</body>
</html> 