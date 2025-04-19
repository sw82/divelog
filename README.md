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


## Recent Changes

- **Dive List Enhancements**:
  - Added column sorting functionality with toggling between ascending and descending order
  - Implemented dive deletion with confirmation dialog and related record cleanup
  - Added visual indicators for sort direction
  - Added success message when a dive is deleted
  - Improved table styling with interactive sortable headers
- **Map Improvements**:
  - Limited map zoom to prevent viewing multiple worlds
  - Removed snorkeling from map legend to match actual data
  - Added bounds restriction to keep users within the world map
  - Enhanced tile layer configuration for better performance
- **Enhanced CSV Import Reliability**:
  - Fixed "Data truncated" errors for ENUM fields by switching to direct SQL queries
  - Improved handling of suit_type and water_type entries with proper validation
  - Added fallback mechanisms for invalid field values to prevent import failures
  - Enhanced error logging for better troubleshooting
  - Implemented robust handling of European number formats (comma as decimal separator)
- **Enhanced Data Privacy**:
  - Added .gitignore rules to prevent database backups from being uploaded
  - Configured separate .gitignore in backups directory for SQL files
  - Protected sensitive configuration files from accidental commits
  - Added proper handling of uploads directory to keep structure in git but exclude content
- **Fixed File Upload Issues**:
  - Fixed file input handling for CSV import to prevent double-click requirement
  - Improved UX for file selection with proper styling
- **Removed Snorkeling Option**:
  - Simplified activity tracking to focus on diving only
  - Updated database schema to remove snorkeling as an activity type
  - Converted existing snorkeling entries to diving
  - Streamlined UI by removing snorkeling filters and toggle options
- **Streamlined Navigation**:
  - Removed Fish List from main navigation menu
  - Maintained fish functionality within dive details 
  - Preserved fish sighting tracking for individual dive logs
  - Directed fish-related pages to appropriate navigation contexts
- **Clarified Field Purposes**:
  - Distinguished between "Description" (objective dive site observations) and "Comments" (personal notes)
  - Enhanced the organization of dive information in a more structured way
  - Maintained compatibility with standard dive logging practices
- **Added Technical Dive Details**:
  - Added air consumption tracking (start and end pressure in bar)
  - Added weight tracking (in kg)
  - Added suit type selection (wetsuit, drysuit, shortie, swimsuit, other)
  - Added water type classification (salt, fresh, brackish)
  - Updated forms, CSV import/export, and database schema
- **Improved CSV Import**:
  - Added automatic detection of CSV delimiters (comma or semicolon)
  - Enhanced support for semicolon-separated values to better preserve comma-containing data
  - Enhanced empty row detection to skip blank lines
  - Added support for European number formats (using comma as decimal separator)
  - Improved error messages for more user-friendly troubleshooting
  - Added time-based duplicate detection to support multiple dives at the same location on the same day
  - Enhanced template with examples showing multiple dives at same location with different times
  - Added warnings when time information is missing but might be needed
- **Added Dive Site Name Field**: Added specific dive site name field separate from location
- **CSV Import Geocoding**: Added automatic geocoding for CSV imports with location but no coordinates
- **CSV Export/Import Updates**: Updated CSV template with clearer field requirements
- **Improved Data Export**: Enhanced CSV export with proper column headers and dive site field
- **Enhanced Map Styling**: Improved map legend/filter background for better visibility
- **Updated UI Elements**: Removed duplicate headings and streamlined interface
- **Added Latest Dive**: Added latest dive information to statistics dashboard
- **Enhanced Map Clustering**: Implemented Leaflet.markercluster for superior marker clustering
  - Dynamic clustering based on zoom level
  - Interactive cluster markers with summary popups
  - Improved performance for maps with many dive locations
  - Custom-styled clusters matching the application theme
- **Bug Fixes**:
  - Fixed "Invalid Date" display issue in map popups for certain date formats
  - Improved date handling with robust error checking and fallback mechanisms
  - Standardized date formatting across PHP and JavaScript components

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

