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
- [ ] create a smart way to add my old log book. it is all pictures atm.
- [ ] download multiple dives at once / zip them
- [ ] search for a specific location (all three fields) or divepoint
- [ ] backup your data to google drive
- [ ] if there are many dive spots at one location/zoom level add a number and once you click it offer a selection.



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
- Simplified architecture for easier maintenance and self-hosting

## Features

- **Interactive Map**: View all dive locations on a map with detailed popups
- **Dive Log Management**: Add, edit, and delete dive entries with detailed information
- **Image Management**: Upload and manage dive images
- **Fish Species Tracking**: Record and manage fish species with images
- **Fish Sightings**: Link fish sightings to specific dives
- **Year Filtering**: Filter dives by year
- **Database Backup**: Easy backup and restore functionality

## Usage

1. **View Dive Log**: Open index.php to see the interactive map with all dives
2. **Manage Database**: Use populate_db.php to add or edit dive entries
3. **Fish Species**: Use fish_manager.php to manage fish species and sightings
4. **Backup Database**: Use backup_db.php to create and manage backups

