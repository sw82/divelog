-- Initial Database Setup for Divelog Application
-- This file contains all SQL statements needed for initial setup and migrations

-- Create divelogs table if not exists
CREATE TABLE IF NOT EXISTS divelogs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location VARCHAR(255) NOT NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    date DATE NOT NULL,
    description TEXT,
    depth DECIMAL(5, 2),
    duration INT,
    temperature DECIMAL(5, 2),
    air_temperature DECIMAL(5, 2),
    visibility INT,
    buddy VARCHAR(255),
    dive_site_type VARCHAR(50),
    rating INT,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    air_consumption_start INT COMMENT 'Starting air pressure in bar',
    air_consumption_end INT COMMENT 'Ending air pressure in bar',
    weight DECIMAL(5, 2) COMMENT 'Weight used in kg',
    suit_type ENUM('wetsuit', 'drysuit', 'shortie', 'swimsuit', 'other') COMMENT 'Type of exposure suit worn',
    water_type ENUM('salt', 'fresh', 'brackish') COMMENT 'Type of water body'
);

-- Create divelog_images table if not exists
CREATE TABLE IF NOT EXISTS divelog_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    divelog_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    type VARCHAR(50) DEFAULT 'dive_photo',
    caption TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (divelog_id) REFERENCES divelogs(id) ON DELETE CASCADE
);

-- Create fish_species table if not exists
CREATE TABLE IF NOT EXISTS fish_species (
    id INT AUTO_INCREMENT PRIMARY KEY,
    common_name VARCHAR(255) NOT NULL,
    scientific_name VARCHAR(255),
    description TEXT,
    habitat TEXT,
    size_range VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create fish_images table if not exists
CREATE TABLE IF NOT EXISTS fish_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fish_species_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT 0,
    source_url TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fish_species_id) REFERENCES fish_species(id) ON DELETE CASCADE
);

-- Create fish_sightings table if not exists
CREATE TABLE IF NOT EXISTS fish_sightings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    divelog_id INT NOT NULL,
    fish_species_id INT NOT NULL,
    sighting_date DATE NOT NULL,
    quantity VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (divelog_id) REFERENCES divelogs(id) ON DELETE CASCADE,
    FOREIGN KEY (fish_species_id) REFERENCES fish_species(id) ON DELETE CASCADE
);

-- Add dive_site column if not exists (migration)
ALTER TABLE divelogs ADD COLUMN IF NOT EXISTS dive_site VARCHAR(255) COMMENT 'Name of the specific dive site';

-- Create index for faster searching on dive_site
CREATE INDEX IF NOT EXISTS idx_dive_site ON divelogs(dive_site);

-- Add activity_type column if it doesn't exist (or modify it)
-- Note: Activity type now only allows 'diving' as per requirement to remove snorkeling
ALTER TABLE divelogs 
ADD COLUMN IF NOT EXISTS activity_type ENUM('diving') NOT NULL DEFAULT 'diving' 
COMMENT 'Type of activity (diving only, as per requirements)';

-- Update any existing records with snorkeling to diving
UPDATE divelogs SET activity_type = 'diving' WHERE activity_type = 'snorkeling';

-- Create indexes for common searches
CREATE INDEX IF NOT EXISTS idx_date ON divelogs(date);
CREATE INDEX IF NOT EXISTS idx_location ON divelogs(location);
CREATE INDEX IF NOT EXISTS idx_fish_species ON fish_sightings(fish_species_id);
CREATE INDEX IF NOT EXISTS idx_divelog ON fish_sightings(divelog_id); 

-- Remove snorkeling option (to be implemented later)

-- Add technical dive details columns (if not exists)
ALTER TABLE divelogs 
ADD COLUMN IF NOT EXISTS air_consumption_start INT COMMENT 'Starting air pressure in bar',
ADD COLUMN IF NOT EXISTS air_consumption_end INT COMMENT 'Ending air pressure in bar',
ADD COLUMN IF NOT EXISTS weight DECIMAL(5, 2) COMMENT 'Weight used in kg',
ADD COLUMN IF NOT EXISTS suit_type ENUM('wetsuit', 'drysuit', 'shortie', 'swimsuit', 'other') COMMENT 'Type of exposure suit worn',
ADD COLUMN IF NOT EXISTS water_type ENUM('salt', 'fresh', 'brackish') COMMENT 'Type of water body'; 