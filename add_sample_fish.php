<?php
// Include database connection
require_once 'db.php';

// Define common fish species seen while diving
$fish_species = [
    [
        'common_name' => 'Clownfish',
        'scientific_name' => 'Amphiprion ocellaris',
        'description' => 'A small, colorful fish known for living among sea anemones. Made famous by the movie "Finding Nemo".',
        'habitat' => 'Coral reefs, sea anemones',
        'size_range' => '8-11 cm'
    ],
    [
        'common_name' => 'Blue Tang',
        'scientific_name' => 'Paracanthurus hepatus',
        'description' => 'A bright blue, oval-shaped fish with black markings. Known as "Dory" from the movie "Finding Nemo".',
        'habitat' => 'Coral reefs',
        'size_range' => '15-30 cm'
    ],
    [
        'common_name' => 'Great White Shark',
        'scientific_name' => 'Carcharodon carcharias',
        'description' => 'One of the largest predatory fish, known for its size, power, and distinctive coloration.',
        'habitat' => 'Open ocean, coastal waters',
        'size_range' => '4.6-6.1 m'
    ],
    [
        'common_name' => 'Manta Ray',
        'scientific_name' => 'Manta birostris',
        'description' => 'Large ray with triangular pectoral fins that resemble wings. Known for their graceful swimming.',
        'habitat' => 'Tropical and subtropical waters',
        'size_range' => '3-7 m wingspan'
    ],
    [
        'common_name' => 'Moray Eel',
        'scientific_name' => 'Muraenidae',
        'description' => 'Elongated fish with distinctive jaws and teeth. Often hide in crevices with only their heads exposed.',
        'habitat' => 'Coral reefs, rocky areas',
        'size_range' => '0.5-4 m'
    ],
    [
        'common_name' => 'Lionfish',
        'scientific_name' => 'Pterois',
        'description' => 'Distinctive fish with red and white stripes and venomous spines. Extremely invasive in Atlantic waters.',
        'habitat' => 'Coral reefs',
        'size_range' => '30-35 cm'
    ],
    [
        'common_name' => 'Parrotfish',
        'scientific_name' => 'Scaridae',
        'description' => 'Colorful fish with beak-like jaws used to scrape algae off coral. Known for producing sand through their digestive processes.',
        'habitat' => 'Coral reefs',
        'size_range' => '30-120 cm'
    ],
    [
        'common_name' => 'Sea Turtle',
        'scientific_name' => 'Cheloniidae',
        'description' => 'Marine reptiles with streamlined shells and flippers. Often seen gliding gracefully through water.',
        'habitat' => 'Oceans worldwide',
        'size_range' => '60-200 cm shell length'
    ],
    [
        'common_name' => 'Butterflyfish',
        'scientific_name' => 'Chaetodontidae',
        'description' => 'Small, colorful fish with disk-shaped bodies and distinctive patterns. Often travel in pairs.',
        'habitat' => 'Coral reefs',
        'size_range' => '12-22 cm'
    ],
    [
        'common_name' => 'Octopus',
        'scientific_name' => 'Octopoda',
        'description' => 'Intelligent cephalopods with eight arms and excellent camouflage abilities.',
        'habitat' => 'Coral reefs, rocky bottoms',
        'size_range' => '1 cm - 5 m depending on species'
    ],
    [
        'common_name' => 'Whale Shark',
        'scientific_name' => 'Rhincodon typus',
        'description' => 'The largest living fish, with a distinctive pattern of light spots on a dark background. Filter feeder.',
        'habitat' => 'Tropical oceans',
        'size_range' => '5.5-14 m'
    ],
    [
        'common_name' => 'Angelfish',
        'scientific_name' => 'Pomacanthidae',
        'description' => 'Colorful reef fish with compressed, disk-shaped bodies. Often have distinctive patterns.',
        'habitat' => 'Coral reefs',
        'size_range' => '10-60 cm'
    ],
    [
        'common_name' => 'Barracuda',
        'scientific_name' => 'Sphyraena',
        'description' => 'Predatory fish with elongated bodies and prominent, sharp-edged teeth. Often swim near the surface.',
        'habitat' => 'Tropical and subtropical waters',
        'size_range' => '50-200 cm'
    ],
    [
        'common_name' => 'Stingray',
        'scientific_name' => 'Dasyatidae',
        'description' => 'Flat-bodied fish with long tails often bearing venomous spines. Usually found on sandy bottoms.',
        'habitat' => 'Sandy or muddy bottoms',
        'size_range' => '0.5-2 m disc width'
    ],
    [
        'common_name' => 'Spotted Eagle Ray',
        'scientific_name' => 'Aetobatus narinari',
        'description' => 'Distinctive ray with white spots on dark background. Has a long tail and pointed snout.',
        'habitat' => 'Coral reefs, sandy bottoms',
        'size_range' => '2-3 m wingspan'
    ]
];

// Count existing fish species
$countStmt = $conn->query("SELECT COUNT(*) as count FROM fish_species");
$countRow = $countStmt->fetch_assoc();
$existingCount = $countRow['count'];

// Check if we should proceed
if ($existingCount > 0) {
    echo "<h2>Fish Species Database</h2>";
    echo "<p>There are already {$existingCount} fish species in your database.</p>";
    
    if (!isset($_GET['force']) || $_GET['force'] != '1') {
        echo "<p>If you want to add sample fish anyway, <a href='add_sample_fish.php?force=1'>click here</a>.</p>";
        echo "<p><a href='fish_manager.php'>Go to Fish Manager</a></p>";
        exit;
    }
    
    echo "<p>Adding sample fish species even though you already have some...</p>";
}

// Counter for added species
$addedCount = 0;
$skippedCount = 0;

// Add each fish species
foreach ($fish_species as $fish) {
    // Check if this fish already exists
    $checkStmt = $conn->prepare("SELECT id FROM fish_species WHERE common_name = ? OR scientific_name = ?");
    $checkStmt->bind_param("ss", $fish['common_name'], $fish['scientific_name']);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        // Fish already exists, skip it
        echo "<p>Skipping {$fish['common_name']} ({$fish['scientific_name']}) - already exists</p>";
        $skippedCount++;
        continue;
    }
    
    // Add new fish species
    $stmt = $conn->prepare("INSERT INTO fish_species (common_name, scientific_name, description, habitat, size_range) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", 
        $fish['common_name'], 
        $fish['scientific_name'],
        $fish['description'],
        $fish['habitat'],
        $fish['size_range']
    );
    
    if ($stmt->execute()) {
        echo "<p>Added: {$fish['common_name']} ({$fish['scientific_name']})</p>";
        $addedCount++;
    } else {
        echo "<p>Error adding {$fish['common_name']}: " . $stmt->error . "</p>";
    }
    
    $stmt->close();
}

// Display summary
echo "<h3>Summary</h3>";
echo "<p>Added {$addedCount} new fish species</p>";
echo "<p>Skipped {$skippedCount} existing fish species</p>";
echo "<p>Total fish species in database: " . ($existingCount + $addedCount) . "</p>";
echo "<p><a href='fish_manager.php'>Go to Fish Manager</a></p>";
?> 