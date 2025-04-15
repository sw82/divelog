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


## Recent Changes

- **Enhanced Map Styling**: Improved map legend/filter background for better visibility
- **Updated UI Elements**: Removed duplicate headings and streamlined interface
- **Added Latest Dive**: Added latest dive information to statistics dashboard
- **Enhanced Map Clustering**: Implemented Leaflet.markercluster for superior marker clustering
  - Dynamic clustering based on zoom level
  - Interactive cluster markers with summary popups
  - Improved performance for maps with many dive locations
  - Custom-styled clusters matching the application theme

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
- **Dive Log Management**: Add, edit, and delete dive entries with detailed information
- **Image Management**: Upload and manage dive images
- **Fish Species Tracking**: Record and manage fish species with images
- **Fish Sightings**: Link fish sightings to specific dives
- **Year Filtering**: Filter dives by year
- **Backup & Export**: 
  - Database backup and restore functionality
  - Export dive logs to CSV format
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

## Usage

1. **View Dive Log**: Open index.php to see the interactive map with all dives
2. **Manage Database**: Use populate_db.php to add or edit dive entries
3. **Fish Species**: Use fish_manager.php to manage fish species and sightings
4. **Backup & Export**: Use manage_db.php to:
   - Create and manage database backups
   - Export dive logs to CSV format
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
   - Import the initial schema from `database/divelog.sql`
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
     
     // Directory configuration
     define('UPLOADS_DIR', 'uploads/');
     define('DIVE_IMAGES_DIR', UPLOADS_DIR . 'diveimages/');
     define('FISH_IMAGES_DIR', UPLOADS_DIR . 'fishimages/');
     define('BACKUPS_DIR', 'backups/');
     
     // Ensure directories exist
     $directories = [UPLOADS_DIR, DIVE_IMAGES_DIR, FISH_IMAGES_DIR, BACKUPS_DIR];
     foreach ($directories as $dir) {
         if (!file_exists($dir) && !mkdir($dir, 0755, true)) {
             error_log("Failed to create directory: $dir");
         }
     }
     ?>
     ```

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
   - Use the built-in backup functionality
   - Schedule automated backups using cron jobs
   - Store backups in a secure location

2. **Updates**
   - Pull latest changes from repository
   - Check for database schema updates
   - Test all functionality after updates

