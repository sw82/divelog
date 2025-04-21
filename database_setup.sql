-- Divelog Database Setup
-- This file contains the database schema and initial data for the Divelog application

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Table structure for dive_logs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dive_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `location` varchar(255) NOT NULL,
  `dive_site` varchar(255) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `date` date NOT NULL,
  `time` time DEFAULT NULL,
  `description` text DEFAULT NULL,
  `depth` decimal(5,2) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `water_temperature` decimal(5,2) DEFAULT NULL,
  `air_temperature` decimal(5,2) DEFAULT NULL,
  `visibility` decimal(5,2) DEFAULT NULL,
  `buddy` varchar(255) DEFAULT NULL,
  `dive_site_type` enum('Reef','Wreck','Cave','Lake','River','Other') DEFAULT NULL,
  `activity_type` enum('diving') NOT NULL DEFAULT 'diving',
  `rating` int(1) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `fish_sightings` text DEFAULT NULL,
  `air_start` int(11) DEFAULT NULL,
  `air_end` int(11) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `suit_type` enum('wetsuit','drysuit','shortie','swimsuit','other') DEFAULT NULL,
  `water_type` enum('salt','fresh','brackish') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `date_index` (`date`),
  KEY `location_index` (`location`),
  KEY `country_index` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for dive_images
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dive_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dive_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `dive_id` (`dive_id`),
  CONSTRAINT `dive_images_ibfk_1` FOREIGN KEY (`dive_id`) REFERENCES `dive_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for fish_species
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fish_species` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `common_name` varchar(255) NOT NULL,
  `scientific_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `habitat` text DEFAULT NULL,
  `size_range` varchar(100) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `common_name` (`common_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for fish_sightings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fish_sightings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dive_id` int(11) NOT NULL,
  `fish_species_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `dive_id` (`dive_id`),
  KEY `fish_species_id` (`fish_species_id`),
  CONSTRAINT `fish_sightings_ibfk_1` FOREIGN KEY (`dive_id`) REFERENCES `dive_logs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fish_sightings_ibfk_2` FOREIGN KEY (`fish_species_id`) REFERENCES `fish_species` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for fish_images
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fish_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fish_species_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fish_species_id` (`fish_species_id`),
  CONSTRAINT `fish_images_ibfk_1` FOREIGN KEY (`fish_species_id`) REFERENCES `fish_species` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Sample data for fish_species
-- --------------------------------------------------------
INSERT INTO `fish_species` (`common_name`, `scientific_name`, `description`, `habitat`, `size_range`, `image_url`) VALUES
('Clownfish', 'Amphiprioninae', 'The clownfish is a small, brightly colored fish known for making its home among the tentacles of sea anemones.', 'Coral reefs and sea anemones', '7-15 cm', NULL),
('Blue Tang', 'Paracanthurus hepatus', 'The blue tang is a bright blue fish with black markings. It is also known as the palette surgeonfish or regal tang.', 'Coral reefs', '15-30 cm', NULL),
('Great White Shark', 'Carcharodon carcharias', 'The great white shark is a large predatory fish known for its size and fearsome appearance.', 'Coastal and offshore waters', '4-6 m', NULL),
('Manta Ray', 'Manta birostris', 'The manta ray is a large ray with triangular pectoral fins that give it a distinctive appearance. It is filter feeder and harmless to humans.', 'Tropical and subtropical waters', '3-7 m wingspan', NULL),
('Moray Eel', 'Muraenidae', 'Moray eels are a family of eels with elongated bodies and high dorsal fins. They are often found hiding in crevices on reefs.', 'Coral reefs and rocky areas', '0.5-3 m', NULL),
('Lionfish', 'Pterois', 'The lionfish is known for its venomous spines and striking red, white, and black stripes. It is an invasive species in some areas.', 'Coral reefs', '30-40 cm', NULL),
('Sea Turtle', 'Cheloniidae', 'Sea turtles are marine reptiles with a bony shell that protects their body. They are ancient creatures that have been around for millions of years.', 'Oceans worldwide', '60-200 cm', NULL),
('Parrotfish', 'Scaridae', 'Parrotfish are known for their bright colors and beak-like mouths, which they use to scrape algae from coral and rocks.', 'Coral reefs', '30-120 cm', NULL),
('Barracuda', 'Sphyraena', 'Barracudas are long, predatory fish with prominent, sharp-edged, fang-like teeth.', 'Tropical and subtropical oceans', '60-165 cm', NULL),
('Butterflyfish', 'Chaetodontidae', 'Butterflyfish are small, colorful fish with disk-like bodies and a small mouth. They are often seen in pairs.', 'Coral reefs', '12-22 cm', NULL);

-- --------------------------------------------------------
-- Create sample dive log entry
-- --------------------------------------------------------
INSERT INTO `dive_logs` (`location`, `dive_site`, `country`, `latitude`, `longitude`, `date`, `time`, `description`, `depth`, `duration`, `water_temperature`, `air_temperature`, `visibility`, `buddy`, `dive_site_type`, `activity_type`, `rating`, `comments`, `fish_sightings`, `air_start`, `air_end`, `weight`, `suit_type`, `water_type`) VALUES
('Sample Beach', 'Coral Cove', 'Sample Country', 25.123456, -80.123456, '2023-01-01', '10:00:00', 'Sample dive for testing', 18.5, 45, 25.5, 28.0, 15.0, 'Sample Buddy', 'Reef', 'diving', 4, 'This is a sample dive entry created during installation.', 'Clownfish, Butterflyfish', 200, 50, 5.0, 'wetsuit', 'salt'); 