divelog
=======

A maps thingy to keep track of my dives.
Done with Vibe Coding.

## ToDo

- [x] Initial Commit
- [x] Add Geolocation and geocode
- [x] Show Map
- [x] git
- [x] can the list of fish be a list of fish in the database or a smart way to keep track of all - I later want to get images of the fish I've seen from Wikipedia or the web (once found they need to be storead as well so we don't need to look them up every time again). 
- [x] I need a smart way to also backup the database schema and the content.
- [x] color code the marker on the map overview based on the years
- [x] list of fish seen
- [x] make edit and add fish when creating or editing a dive entry
- [x] create a smart way to add my old log book. it is all pictures atm.
- [x] download multiple dives at once as csv with all values
- [x] search for a specific location (all three fields) or divepoint
- [ ] backup your data to google drive
- [x] next to diving add snorkeling as option in the database as well in the interfaces.
- [x] if there are many dive spots at one location/zoom level add a number and once you click it offer a selection.
- [x] check for security. also if I add this to git I don't want any personla information in it
- [x] add to the readme all steps needed to set up the project (database, webserver etc.)
- [x] mobile first implementation. everything needs to be super responsive. if needed use the best framework for this.
- [x] check all sub pages if the menu and navigation is right and the hierachy. I see different menues in different subpages. normalize this
- [x] check if the export csv function is still correct and according to the latest database style.
- [x] write a csv importer. check if the entry exists before
- [x] when clicking on "Drag & drop your CSV file here or click to browse" choose file I can select it in the Finder, but it will not be upolao0ded. But it works once I drag and drop it. so please check the upload functionality.
- [x] improve marker clustering for better visibility when zoomed out
- [x] for the Dive Log Entries add more details from the database. I like the style btw.
- [x] change menu structure: 1) "view dive log" = rename to dive map 2) divelist 3) fish list (same style as divelist) 4) Manage Database. Once you have the four items take care that when clicking one of the pages all menues looks the same and have the same entries. also make sure there is no hamburger menu. 
- [x] edit dives must work. each part where you can edit a dive must jump to the divelist in an edit mode. also the edit function in the divelist does not work
- [x] if in a csv import you only have a location, but no lat len this is okay and should be reverse geocoded internally. the functionally is there anyways. 
- [x] refactor carefully and delete files not needed. also check if all untrackeck files are needed. is every file needed and used (e.g. been shown) or can they be deleted. check for function which never been used. make sure all .sql files are in an initial setup for the database. we don't want any overhead. the project is suppoesed to work light weighted.
- [x] when uploading to git repo never upload my custom database entries. only the sample data
- [x] security check that you cannot accidentally remove the database or make sql injections
- [x] the uplaods content shall also not be added to git. 
- [x] remove snorkeling again. this supposed to be a divers log
- [x] add air consumption (bar) and weight added as well as type of suit (wetsuit, drysuit, just shorts etc.), type of water (salt, sweet, sea, lake etc.). update database schema, csv export etc.
- [x] I have a description and a coment field. why both?
- [x] no need for the fish list at the moment. comment out and remove from the menu. but keep it in the database for each dive a list of fish. also make sure to show them when clicking on an entry or in the overview of dives.
- [x] make leaflet map not zoom out more than the whole world 
- [x] check the agenda of the map since there is no snorkeling anymore
- [x] sort dive list entry and create sorting for each column
- [x] for the divelist also add a "remove dive" and all functions accordingly
- [x] design: the stats need to have the same width as the map
- [x] design: check the dive pop ups and make sure it is nice and clean, also aligned and well use of icons as you do in the divelog
- [x] stats: about depth relatad to length and bar consumption
- [x] include the fish db into the export/backup. also all relations in the sql so that you could restore it easily
- [x] create a php script or so which creates all nesecassary files, folders etc and copies them from the git to the server. so I just upload this one php script and it will create everything and asks me to fill in database credentials, maybe a name or whatever is needed
- [x] Fatal error: Uncaught mysqli_sql_exception: Unknown column 'fish_id' in 'where clause' in fish_manager.php:111 Stack trace:  on line 111

### install script and get started
- [x] install script: install script: "Database setup file not found. Please place your database_setup.sql file in the application's root directory." after succesfully enter credentials
- [x] install script: in the end I get a "Not Found The requested URL was not found on this server."
- [x] install script: make sure to copy the needed files from the github source
- [x] if install script reruns make sure it shall check before what is there already
- [x] add a proper admin panel to manage all aspects of the application from a single interface
- [x] implement HTTPS support by default for better security
- [ ] create user role system for multiple divers sharing the same installation
- [x] add readme section about installing and the script 

## Installation Guide

### Using the One-File Installer

The simplest way to install Divelog is using our one-file installer:

1. **Download the Installer**: Get the `install.php` file from the repository
2. **Upload to Server**: Upload only this file to your web server via FTP or file manager
3. **Run the Installer**: Navigate to `https://your-domain.com/install.php` in your browser
4. **Follow the Steps**: The installer will guide you through:
   - System requirements check
   - Directory creation
   - Database configuration
   - Database setup
   - Final configuration

The installer automatically:
- Checks if your server meets all requirements
- Creates necessary directories with proper permissions
- Sets up database connection
- Imports the database schema
- Creates the configuration file
- Adds sample data (optional)
- Self-deletes after completion for security (recommended)

### What to Expect During Installation

The installation process consists of 5 easy steps:

1. **Requirements Check**:
   - PHP version compatibility (PHP 8.0+ recommended)
   - Required PHP extensions (mysqli, gd, fileinfo, zip)
   - Server settings (execution time, file uploads, etc.)

2. **Directory Setup**:
   - Creates required folders (uploads, backups, temp)
   - Sets proper permissions (0755)
   - Verifies write access

3. **Database Configuration**:
   - Enter database connection details
   - Tests the connection
   - Creates configuration file

4. **Database Setup**:
   - Downloads or imports database schema
   - Creates all required tables
   - Handles existing tables (with backup option)

5. **Finalize Installation**:
   - Completes the setup process
   - Provides links to the application
   - Option to delete installer script for security

### Troubleshooting Installation

If you encounter issues during installation:

- **Database Connection Errors**: Verify your database credentials and ensure the database server is running.
- **Permission Issues**: Make sure the web server user has write access to the installation directory.
- **Download Failures**: If automatic downloads fail, you can manually upload the required files.
- **Existing Installation**: The installer will detect and warn about existing installations, offering options to proceed safely.

For manual troubleshooting, check the installer's detailed error messages or your server's error logs.

### Dealing with Permission Issues

If you see errors like "directory creation failed" or "write failed" during installation:

1. **Check Server Permissions**: 
   - Most shared hosting environments restrict PHP's ability to create directories or write files
   - Contact your hosting provider to confirm the correct permissions and directories where PHP can write

2. **Manual Installation Alternative**:
   - Download the complete application package from GitHub
   - Extract it on your local computer
   - Upload all files to your server via FTP
   - Ensure directories have proper permissions:
     ```
     uploads/          (755 or 775)
     uploads/dive_images  (755 or 775)
     uploads/fish_images  (755 or 775)
     backups/          (755 or 775)
     temp/             (755 or 775)
     ```
   - Manually create config.php using the template below
   - Run the installer with `?step=3` to continue with database setup

3. **Host-Specific Settings**:
   - Some hosts require files and directories to be in specific locations
   - For cPanel hosting: place upload directories in `/home/username/public_html/uploads`
   - For Plesk hosting: use `/var/www/vhosts/domain.com/httpdocs/uploads`

4. **Using FTP for Setup**:
   - Upload all files via FTP
   - Create the required directories manually
   - Set correct permissions using your FTP client
   - Run the installer with `?skip_file_creation=1` to bypass file creation steps

5. **Config File Template**:
   ```php
   <?php
   // Database configuration
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'divelog');
   
   // Create connection
   $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
   
   // Check connection
   if ($conn->connect_error) {
     die("Connection failed: " . $conn->connect_error);
   }
   
   // Set charset
   $conn->set_charset('utf8mb4');
   ?>
   ```

### Advanced Installation Options

The installer provides advanced options for specific needs:

- **Custom MySQL Socket**: If your database uses a non-standard socket path
- **Custom Port**: For database servers running on non-default ports
- **Force Installation**: Override existing installation checks when needed
- **Manual Download**: Option to manually download and place required files

### Security During Installation

The installer implements several security measures:

- Verification of token authenticity for form submissions
- Secure handling of database credentials
- Creation of proper file permissions
- Option to delete the installer after completion (recommended)
- Detection of existing installations to prevent accidental data loss

### Post-Installation Steps

After completing the installation:

1. **Set Up HTTPS**: Configure SSL for secure connections (recommended)
2. **Configure Backups**: Set up regular database backups
3. **Test the Application**: Try adding a dive and verify all features work
4. **Update Password**: If using sample data, change any default passwords

## Recent Changes

- **Added Admin Panel**:
  - Created comprehensive admin dashboard to manage all aspects of the application
  - Centralized interface for managing dives, fish, database operations, and settings
  - Intuitive navigation with quick access to all administrative functions
  - Responsive design that works on mobile devices and desktops
  - Enhanced security with confirmation dialogs for destructive actions
  - Real-time statistics and system information display

- **HTTPS Support by Default**:
  - Implemented automatic HTTPS redirection for better security
  - Added HSTS headers to enforce secure connections
  - Updated documentation with SSL configuration guidance
  - Ensured all assets load securely to prevent mixed content warnings
  - Added fallback for development environments without SSL certificates

- **New One-File Installer Script**:
  - Added a single-file PHP installer that automates the setup process
  - Guides users through system requirements verification
  - Creates necessary directories with proper permissions
  - Handles database configuration and initialization
  - Populates sample data for immediate testing
  - Self-deletes after successful installation for security
  - Provides a user-friendly step-by-step interface with progress tracking

- **Standardized CSV Format and Import/Export**:
  - Improved CSV file handling for consistent formatting
  - Fixed issues with decimal separators (standardized to periods)
  - Added proper quoting for text fields containing special characters
  - Enhanced handling of duplicate entries
  - Removed inconsistencies in field formatting
  - Fixed coordinate formatting for proper geocoding
  - Improved data validation during import process
  - Standardized empty field handling

- **Fixed SQL Error for Empty Numeric Fields**:
  - Fixed "Incorrect integer value" error when submitting empty values for numeric fields like visibility
  - Improved form handling to convert empty string inputs to NULL values in database
  - Enhanced error handling for all numeric fields (visibility, water temperature, air temperature, etc.)
  - Ensures robust form submissions even with incomplete dive data

- **Enhanced Error Handling in Map Display**:
  - Added robust error handling for fetch requests in map.js
  - Implemented user-friendly error messages for various error types (network errors, server errors, JSON parsing issues)
  - Created dismissable error notifications with retry functionality
  - Added detailed logging for debugging server response issues
  - Improved data validation for non-standard API responses

- **Enhanced User Interface Aesthetics**:
  - Redesigned dive popups with consistent styling, improved alignment, and better icon usage
  - Updated statistics cards to match map width and provide a cohesive visual experience
  - Added visual indicators for technical dive details (air consumption, weight, suit type, water type)
  - Implemented responsive layout that adapts to different screen sizes
  - Improved color coding and visual hierarchy for better scan-ability
  - Integrated comprehensive dive statistics dashboard directly on the map page showing dive metrics:
    - Total dives count and unique locations
    - Maximum depth achieved
    - Average dive duration
    - Total underwater time
    - Latest dive information
- **CSV Import Fixes and Enhancements**:
  - Fixed "Choose File" button functionality to properly open the file selection dialog
  - Added PHP 8 compatibility fixes for handling null values in string functions
  - Resolved deprecation warnings related to trim() function
  - Improved error handling in CSV processing
- **Enhanced CSV Import Performance and Reliability**:
  - Added timeout prevention with increased execution time limit (5 minutes)
  - Implemented geocoding optimization with location caching
  - Added batch processing with progress tracking for large imports
  - Added real-time progress indicator for better user feedback
  - Added internet connectivity check before geocoding attempts
  - Improved error handling for missing coordinates
  - Added option to enable/disable automatic geocoding
- **Fish Management Integration**: Removed fish list from navigation menu while maintaining full fish sighting functionality within dive details.
- **Enhanced CSV Import Reliability**: Fixed errors related to ENUM fields and improved handling of suit_type and water_type entries.
- **Enhanced Data Privacy**: Added .gitignore rules to prevent sensitive data from being uploaded.
- **Fixed File Upload Issues**: Improved file input handling for CSV imports.
- **Removed Snorkeling Option**: Streamlined activity tracking to focus solely on diving.
- **Clarified Field Purposes**: Distinction between "Description" and "Comments" fields.
- **Added Technical Dive Details**: Included tracking for air consumption, weight, suit type, and water type.
- **Improved CSV Import**: Enhanced detection of CSV delimiters and error handling.
- **Added Country Information and Flags**:
  - Enhanced dive popups with country information display
  - Added Unicode flag emojis based on country names
  - Implemented a robust country name to ISO code mapping system
  - Styled country display with responsive design for all device sizes
  - Added visual separation between country information and other dive details
- **Enhanced Installer Script**:
  - Fixed issues with script execution and duplicated content
  - Added detection for existing installations to prevent accidental overwrites
  - Implemented automatic backup of existing database before reinstallation
  - Improved user interface with clear warnings and options for existing installations
  - Added force flag option to bypass existing installation checks when needed
  - Enhanced cleanup functionality with multiple methods for removing installation markers
  - Added advanced "Force Cleanup" option for resolving stubborn installation issues
  - Improved handling of inconsistent installation states with clear guidance
  - Added detailed feedback on cleanup operations for better troubleshooting

## Project Changes

This project has undergone a major restructuring:

- Moved from Node.js/Express/MongoDB stack to PHP/MySQL implementation
- Created dive log system with robust database schema
- Added support for:
  - Dive location tracking with interactive map visualization
  - Comprehensive dive metrics (depth, duration, temperature, etc.)
  - Image uploads for each dive
  - Year-based filtering
  - Dive site categorization
  - Ratings system
  - Fish species tracking with images from both uploads and web sources
  - Recording fish sightings linked to specific dives
  - Database backup functionality (schema and content)
  - Mobile-first responsive design using Bootstrap 5
- Simplified architecture for easier maintenance and self-hosting

## Features

- **Interactive Map**: 
  - View all dive locations on a map with detailed popups
  - Advanced marker clustering for better visualization of dive sites
  - Intelligent grouping of nearby dive locations
  - Interactive cluster popups showing location summaries
  - Comprehensive statistics dashboard showing dive metrics:
    - Total dives count and unique locations
    - Maximum depth achieved
    - Average dive duration
    - Total underwater time
    - Latest dive details
  - Year filtering for focused analysis
- **Dive Log Management**: 
  - Add, edit, and delete dive entries with detailed information
  - Record comprehensive dive metrics:
    - Basic: depth, duration, water/air temperature, visibility
    - Technical: air consumption (start/end pressure), weight, suit type, water type
  - Track dive buddies and site types
  - Rate dives on a 1-5 scale
  - Add comments and descriptions
- **Image Management**: Upload and manage dive images
- **Fish Species Tracking**: Record and manage fish species with images
- **Fish Sightings**: Link fish sightings to specific dives
- **Year Filtering**: Filter dives by year
- **Backup & Export**: 
  - Database backup and restore functionality
  - Export dive logs to CSV format
  - Database management options:
    - Delete all entries for fresh start
    - Populate with sample data for testing
    - Selective export of dive logs
- **OCR Import**: Convert handwritten dive logs to digital entries using OCR technology
  - Batch process multiple logbook pages
  - Automatic data extraction for:
    - Date
    - Location
    - Depth
    - Duration
    - Temperature
    - Visibility
    - Comments
  - Manual review and correction of extracted data
- **Responsive Design**:
  - Mobile-first approach ensures optimal viewing on all devices
  - Touch-friendly interface elements
  - Adaptive layout that scales from phones to desktop screens
  - Optimized map controls for touch screens
- **Export & Import**:
  - Export dive logs to CSV format with all dive data
  - Import dive logs from CSV with flexible field mapping
  - Intelligent handling of duplicate detection for multiple dives:
    - Uses time slot to differentiate between dives at the same location on the same day
    - Provides helpful warnings when time information might be needed
  - Automatic geocoding of locations without coordinates
  - Support for various CSV formats (comma or semicolon delimiters)
  - European number format support (comma as decimal separator)
  - Detailed error feedback and import result summary

## Usage

1. **View Dive Log**: Open index.php to see the interactive map with all dives
2. **Manage Database**: Use manage_db.php to:
   - Create and manage database backups
   - Restore from backup files
   - Export dive logs to CSV format
   - Delete database entries
   - Populate database with sample data for testing
3. **Manage Dive Entries**: Use populate_db.php to add, edit, or delete dive entries
4. **Fish Species**: Use fish_manager.php to manage fish species and sightings
5. **Import Logs**: Use import.php to convert handwritten logs using OCR

## OCR Requirements

To use the OCR functionality, you need to install Tesseract OCR on your server:

```bash
# On macOS
brew install tesseract

# On Ubuntu/Debian
sudo apt-get install tesseract-ocr
```

The OCR feature supports:
- Multiple image formats (JPEG, PNG, etc.)
- Batch processing of multiple logbook pages
- Automatic data extraction with manual review
- Saving processed data directly to the database

## Easy Installation

The Divelog application now includes a simple installer script that makes deployment on a new server quick and easy.

### Using the Installer

1. **Upload a Single File**: Only upload `install.php` to your server
2. **Access the Installer**: Navigate to `install.php` in your web browser
3. **Follow the Steps**: The installer will guide you through:
   - System requirements check
   - Directory creation with proper permissions
   - Database configuration setup
   - Database schema and sample data initialization
   - Final configuration settings

The installer will check your server's compatibility, create necessary directories, help you set up your database connection, and initialize the database with the required tables and sample data.

### Database Setup

The application provides an easy way to download the database setup file:

1. **Download the Setup File**: Click the "Download Database setup file" button from the installer
2. **Import the SQL File**: Use phpMyAdmin or any MySQL client to import the downloaded file
3. **Complete the Installation**: Follow the remaining steps in the installer to connect to your newly created database

This ensures you have the correct database schema with all necessary tables for the application to function properly.

### Existing Installation Detection

The installer now includes smart detection for existing installations:
- Automatically detects if Divelog is already installed on the server
- Provides clear warnings when existing components are found
- Offers options to either continue with the existing installation or start fresh
- Creates automatic backups before overwriting existing database tables
- Includes a force mode option to bypass checks when needed

### Enhanced Cleanup Features

The installer includes powerful cleanup capabilities to handle problematic installations:
- **Multiple Cleanup Methods**: Uses several approaches to ensure all installation markers are properly removed
- **Force Cleanup Option**: Advanced cleanup functionality for stubborn cases where standard methods fail
- **Detailed Feedback**: Clear reporting on which files were successfully removed and which might need manual intervention
- **Session Handling**: Properly resets all session variables to ensure a truly fresh start
- **Inconsistency Detection**: Identifies and provides specific solutions for inconsistent installation states (like when markers exist but configuration files don't)

### Security Features

The installer includes security features:
- Verification of server requirements before proceeding
- Secure database creation
- Proper file permission settings
- Optional self-deletion after installation

### Post-Installation

After installation:
1. The application will be fully configured and ready to use
2. You can log in and start adding your dive logs
3. Sample data will be available for you to test and explore features

This one-file approach eliminates the need to manually copy files, create directories, set permissions, or run SQL scripts.

## Setup Guide

### Prerequisites

1. **Web Server**
   - Apache or Nginx web server
   - PHP 8.0 or higher
   - PHP extensions:
     - mysqli
     - gd (for image processing)
     - fileinfo (for file uploads)
     - zip (for database backups)

2. **Database**
   - MySQL 5.7 or higher
   - phpMyAdmin (recommended for database management)

### Installation Steps

1. **Clone the Repository**
   ```bash
   git clone https://github.com/yourusername/divelog.git
   cd divelog
   ```

2. **Configure Web Server**
   - Point your web server's document root to the project directory
   - Ensure the following directories are writable:
     - `uploads/`
     - `backups/`

3. **Database Setup**
   - Create a new MySQL database
   - Import the initial schema from `database_setup.sql`
   - Create a `config.php` file with the following template:
     ```php
     <?php
     // Database configuration
     define('DB_HOST', 'localhost');
     define('DB_USER', 'your_username');
     define('DB_PASS', 'your_password');
     define('DB_NAME', 'divelog');
     
     // Create connection
     $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
     
     // Check connection
     if ($conn->connect_error) {
       die("Connection failed: " . $conn->connect_error);
     }
     ?>
     ```
   - Alternatively, after database creation, navigate to `update_database.php` in your browser to automatically 
     set up the required tables and columns

4. **File Permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 backups/
   chmod 644 config.php
   ```

5. **Security Configuration**
   - Set up proper .htaccess rules to protect sensitive files
   - Configure PHP settings in php.ini:
     ```ini
     upload_max_filesize = 10M
     post_max_size = 10M
     max_execution_time = 300
     ```

6. **Testing the Installation**
   - Access the application through your web browser
   - Try to add a new dive entry
   - Verify image uploads work
   - Check if the map displays correctly

### Troubleshooting

1. **Database Connection Issues**
   - Verify database credentials in config.php
   - Check if MySQL service is running
   - Ensure database user has proper permissions

2. **Image Upload Problems**
   - Check file permissions on uploads directory
   - Verify PHP GD extension is installed
   - Check PHP error logs for specific issues

3. **Map Display Issues**
   - Ensure JavaScript is enabled in browser
   - Check browser console for errors
   - Verify internet connection for map tiles

4. **CSV Import Performance Issues**
   - Check PHP execution time limit in php.ini (should be at least 300 seconds)
   - Ensure internet connection is available for geocoding functionality
   - Consider disabling automatic geocoding for large imports
   - Provide complete coordinates in CSV files when possible
   - For large imports, process files in smaller batches
   - Check browser console for AJAX errors during progress tracking

### Backup and Maintenance

1. **Regular Backups**
   - Use the built-in backup functionality in manage_db.php
   - Create manual backups before major changes
   - Schedule automated backups using cron jobs
   - Store backups in a secure location
   - Restore from backups when needed

2. **Database Management**
   - Use manage_db.php for comprehensive database operations:
     - Reset/delete database entries when needed
     - Populate with sample data for testing or demonstrations
     - Export selected dive logs to CSV format
     - Manage database backup files (download/delete)

3. **Updates**
   - Pull latest changes from repository
   - Check for database schema updates
   - Test all functionality after updates

## Security Enhancement Plan

To address the remaining security concerns, the following improvements should be implemented:

### Database Protection
1. **Confirmation Dialogs for Destructive Actions**
   - Current status: Basic JavaScript confirmation dialogs are in place but could be enhanced
   - Needed: Add more robust confirmation for database truncation operations with a required typing verification

2. **User Privilege Management**
   - Current status: No user privilege system in place
   - Needed: Add basic user roles (admin, regular user) with different access levels to destructive operations

3. **Transaction Safety**
   - Current status: Using transactions in some critical operations
   - Needed: Ensure all database modifications use transactions consistently with proper error handling

### SQL Injection Prevention
1. **Prepared Statements Usage**
   - Current status: Most queries use prepared statements, but some direct queries exist
   - Needed: Convert all direct $conn->query() calls to use prepared statements with parameter binding

2. **Input Validation**
   - Current status: Basic validation exists but could be more comprehensive
   - Needed: Add consistent input validation for all form submissions using a centralized validation library

3. **Query Building Safety**
   - Current status: Some queries are built dynamically with concatenation
   - Needed: Replace string concatenation in queries with parameterized alternatives

### Implementation Timeline
1. **Immediate Actions**
   - Add confirmation typing verification for database truncation operations
   - Convert direct queries to prepared statements in critical files

2. **Short-term Improvements**
   - Create a centralized validation library
   - Add database logging for all destructive operations

3. **Long-term Enhancements**
   - Implement user role management
   - Add comprehensive audit logging system

### Expected Outcomes
- Prevention of accidental database table deletions
- Protection against SQL injection vulnerabilities
- Improved operational safety with better confirmation mechanisms
- Enhanced error handling and recovery procedures