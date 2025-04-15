document.addEventListener('DOMContentLoaded', function() {
    console.log('Map.js loaded');
    
    // Initialize the map
    const map = L.map('map').setView([20, 0], 2);
    
    // Add the tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Define colors for different years
    const yearColors = [
        '#e6194B', '#3cb44b', '#ffe119', '#4363d8', 
        '#f58231', '#911eb4', '#42d4f4', '#f032e6', 
        '#bfef45', '#fabed4', '#469990', '#dcbeff', 
        '#9A6324', '#fffac8', '#800000', '#aaffc3', 
        '#808000', '#ffd8b1', '#000075', '#a9a9a9'
    ];
    
    // Get color for a specific year
    function getColorForYear(year) {
        const yearIndex = parseInt(year) % yearColors.length;
        return yearColors[yearIndex];
    }
    
    // Create a marker for a dive
    function createDiveMarker(dive) {
        // Determine marker color based on year
        const color = getColorForYear(dive.year);
        
        // Create marker options
        const markerOptions = {
            radius: 8,
            fillColor: color,
            color: '#fff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.8
        };
        
        // If it's a snorkeling activity, use a dashed border
        if (dive.activity_type === 'snorkeling') {
            markerOptions.dashArray = '3,3';
            markerOptions.weight = 3;
        }
        
        // Create marker at the dive's coordinates
        const marker = L.circleMarker([dive.latitude, dive.longitude], markerOptions);
        
        // Create popup content for the marker
        const popupContent = createDivePopup(dive);
        
        // Bind popup to marker
        marker.bindPopup(popupContent, {
            maxWidth: 350,
            className: 'dive-popup-container'
        });
        
        return marker;
    }
    
    // Create popup HTML content for a dive
    function createDivePopup(dive) {
        // Format the date
        const date = new Date(dive.date);
        const formattedDate = date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        // Start popup content
        let popupContent = `
            <div class="dive-popup">
                <h3>${dive.location}</h3>
                <div class="dive-date">${formattedDate}</div>`;
        
        // Add rating as stars if available
        if (dive.rating) {
            const stars = '★'.repeat(parseInt(dive.rating));
            popupContent += `<div class="dive-rating">${stars}</div>`;
        }
        
        popupContent += `<div class="dive-details">`;
        
        // Add dive details if available (more comprehensive)
        if (dive.max_depth) {
            popupContent += `<p><strong>Depth:</strong> ${dive.max_depth} m</p>`;
        }
        
        if (dive.duration) {
            popupContent += `<p><strong>Duration:</strong> ${dive.duration} min</p>`;
        }
        
        if (dive.activity_type) {
            const activityLabel = dive.activity_type.charAt(0).toUpperCase() + dive.activity_type.slice(1);
            popupContent += `<p><strong>Activity:</strong> ${activityLabel}</p>`;
        }
        
        if (dive.temperature) {
            popupContent += `<p><strong>Water Temp:</strong> ${dive.temperature}°C</p>`;
        }
        
        if (dive.visibility) {
            popupContent += `<p><strong>Visibility:</strong> ${dive.visibility} m</p>`;
        }
        
        if (dive.dive_site_type) {
            popupContent += `<p><strong>Site Type:</strong> ${dive.dive_site_type}</p>`;
        }
        
        if (dive.buddy) {
            popupContent += `<p><strong>Buddy:</strong> ${dive.buddy}</p>`;
        }
        
        // Close details section
        popupContent += '</div>';
        
        // Add description if available
        if (dive.description) {
            popupContent += `<p class="dive-description">${dive.description}</p>`;
        }
        
        // Add images if available (max 3 for performance)
        if (dive.images && dive.images.length > 0) {
            popupContent += '<div class="popup-images">';
            
            // Limit to 3 images in popup
            const imagesToShow = dive.images.slice(0, 3);
            imagesToShow.forEach(function(image) {
                popupContent += `
                    <div class="popup-image">
                        <img src="uploads/diveimages/${image}" alt="Dive Image">
                    </div>
                `;
            });
            
            // If there are more images, add a note
            if (dive.images.length > 3) {
                popupContent += `<p class="more-images">+ ${dive.images.length - 3} more images</p>`;
            }
            
            popupContent += '</div>';
        }
        
        // Add fish sightings if available
        if (dive.fish_sightings && dive.fish_sightings.length > 0) {
            popupContent += `
                <div class="fish-sightings-container">
                    <div class="fish-sightings-title">Fish Spotted (${dive.fish_sightings.length}):</div>
                    <div class="fish-list">
            `;
            
            // Show up to 6 fish in the popup
            const fishToShow = dive.fish_sightings.slice(0, 6);
            fishToShow.forEach(function(sighting) {
                // Determine the display name
                const fishName = sighting.common_name || sighting.scientific_name || 'Unknown Fish';
                
                // Create fish item with image if available
                popupContent += `<div class="fish-item">`;
                
                if (sighting.image_path) {
                    popupContent += `<img src="uploads/fishimages/${sighting.image_path}" alt="${fishName}" class="fish-image">`;
                } else {
                    popupContent += `<div class="fish-image-placeholder">No Image</div>`;
                }
                
                popupContent += `
                    <div class="fish-info">
                        <div class="fish-name" title="${fishName}">${fishName}</div>
                        <div class="fish-quantity">${sighting.quantity || 'Spotted'}</div>
                    </div>
                </div>`;
            });
            
            popupContent += `</div>`;
            
            // If there are more fish, add a link
            if (dive.fish_sightings.length > 6) {
                popupContent += `<a href="populate_db.php?edit=${dive.id}" class="more-fish">+ ${dive.fish_sightings.length - 6} more fish species</a>`;
            }
            
            popupContent += `</div>`;
        }
        
        // Add footer with edit link
        popupContent += `
            <div class="popup-footer">
                <a href="populate_db.php?edit=${dive.id}" class="popup-edit-link">Edit Dive</a>
            </div>
        </div>`;
        
        return popupContent;
    }
    
    // Display markers on the map
    function displayMarkers(dives, selectedYear = 'all') {
        // Clear existing markers
        if (window.markerLayer) {
            window.markerLayer.clearLayers();
        }
        
        // Check if we have valid dive data
        if (!dives || !Array.isArray(dives)) {
            console.error('Invalid dive data:', dives);
            // Show error message on the map
            const errorDiv = L.DomUtil.create('div', 'map-error-message');
            errorDiv.innerHTML = `<p>Failed to load dive data. Please refresh the page or try again later.</p>`;
            errorDiv.style.padding = '10px';
            errorDiv.style.backgroundColor = '#f8d7da';
            errorDiv.style.color = '#721c24';
            errorDiv.style.borderRadius = '4px';
            errorDiv.style.margin = '10px';
            errorDiv.style.textAlign = 'center';
            
            // Add to map
            map.getContainer().appendChild(errorDiv);
            return;
        }
        
        // Create a new layer group for markers
        window.markerLayer = L.layerGroup();
        
        // Filter dives by selected year if needed
        let filteredDives = dives;
        if (selectedYear !== 'all') {
            filteredDives = dives.filter(dive => dive.year && dive.year.toString() === selectedYear.toString());
        }
        
        console.log(`Displaying ${filteredDives.length} dive markers for year: ${selectedYear}`);
        
        // Handle the case where there are no dives to show
        if (filteredDives.length === 0) {
            // Update counter if it exists
            const countElement = document.getElementById('filtered-count');
            if (countElement) {
                countElement.textContent = '0';
            }
            
            // Add empty message to map
            if (selectedYear !== 'all') {
                const noDataDiv = L.DomUtil.create('div', 'no-data-message');
                noDataDiv.innerHTML = `<p>No dive logs found for year ${selectedYear}.</p>`;
                noDataDiv.style.padding = '10px';
                noDataDiv.style.backgroundColor = '#e2e3e5';
                noDataDiv.style.color = '#383d41';
                noDataDiv.style.borderRadius = '4px';
                noDataDiv.style.margin = '10px';
                noDataDiv.style.textAlign = 'center';
                
                // Add to map
                map.getContainer().appendChild(noDataDiv);
                
                // Set a timeout to remove the message after 3 seconds
                setTimeout(() => {
                    if (noDataDiv.parentNode) {
                        noDataDiv.parentNode.removeChild(noDataDiv);
                    }
                }, 3000);
            }
            
            return;
        }
        
        // Create and add markers for each dive
        filteredDives.forEach(dive => {
            // Check that required coordinates exist and are valid numbers
            if (!dive.latitude || !dive.longitude || 
                isNaN(parseFloat(dive.latitude)) || isNaN(parseFloat(dive.longitude))) {
                console.warn(`Missing or invalid coordinates for dive ${dive.id} - ${dive.location}`);
                return;
            }
            
            try {
                const marker = createDiveMarker(dive);
                window.markerLayer.addLayer(marker);
            } catch (err) {
                console.error(`Error creating marker for dive ${dive.id}:`, err);
            }
        });
        
        // Add the layer group to the map
        window.markerLayer.addTo(map);
        
        // Update the counter element if it exists
        const countElement = document.getElementById('filtered-count');
        if (countElement) {
            countElement.textContent = filteredDives.length;
        }
        
        // Fit map to markers if we have any
        if (window.markerLayer.getLayers().length > 0) {
            try {
                map.fitBounds(window.markerLayer.getBounds(), {
                    padding: [50, 50],
                    maxZoom: 12
                });
            } catch (err) {
                console.error('Error fitting bounds:', err);
                // Fallback to world view
                map.setView([20, 0], 2);
            }
        } else {
            // Default world view if no markers
            map.setView([20, 0], 2);
        }
    }
    
    // Create year legend and filter controls
    function createYearLegend(years) {
        const legend = L.control({ position: 'bottomright' });
        
        legend.onAdd = function() {
            const div = L.DomUtil.create('div', 'year-legend');
            div.innerHTML = '<h4>Filter by Year</h4>';
            
            // Add "All Years" option
            div.innerHTML += `
                <div class="legend-item">
                    <input type="radio" name="year-filter" id="year-all" value="all" checked>
                    <label for="year-all">All Years</label>
                </div>`;
            
            // Sort years chronologically
            years.sort();
            
            // Add each year with its color
            years.forEach(year => {
                div.innerHTML += `
                    <div class="legend-item">
                        <input type="radio" name="year-filter" id="year-${year}" value="${year}">
                        <label for="year-${year}">
                            <span class="color-box" style="background-color: ${getColorForYear(year)}"></span>
                            ${year}
                        </label>
                    </div>`;
            });
            
            // Add activity type legend
            div.innerHTML += `
                <h4 style="margin-top: 15px;">Activity Types</h4>
                <div class="legend-item">
                    <span class="color-box" style="background-color: #4363d8; border: 2px solid white;"></span>
                    <span>Diving</span>
                </div>
                <div class="legend-item">
                    <span class="color-box" style="background-color: #4363d8; border: 3px dashed white;"></span>
                    <span>Snorkeling</span>
                </div>`;
            
            return div;
        };
        
        return legend;
    }
    
    // Fetch dive data and initialize map
    fetch('get_dive_data.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server responded with status ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            // Check if we received an error in the JSON
            if (data && data.error) {
                throw new Error(data.error);
            }
            
            // Store dive data globally
            window.diveData = data;
            
            console.log(`Received ${data.length} dive logs`);
            
            // Extract unique years for filtering
            const uniqueYears = [...new Set(data.map(dive => dive.year))].sort().reverse();
            
            // Add the year legend with filter controls
            createYearLegend(uniqueYears).addTo(map);
            
            // Display all markers initially
            displayMarkers(data);
            
            // Add event listeners for year filter controls
            document.addEventListener('change', function(e) {
                if (e.target && e.target.name === 'year-filter') {
                    const selectedYear = e.target.value;
                    displayMarkers(data, selectedYear);
                }
            });
            
            // Add event listeners for buttons if they exist
            document.querySelectorAll('.year-filter').forEach(button => {
                button.addEventListener('click', function() {
                    const selectedYear = this.getAttribute('data-year');
                    
                    // Update radio buttons in legend if they exist
                    const radio = document.getElementById(`year-${selectedYear === 'all' ? 'all' : selectedYear}`);
                    if (radio) {
                        radio.checked = true;
                    }
                    
                    // Update active class on buttons
                    document.querySelectorAll('.year-filter').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    this.classList.add('active');
                    
                    // Update markers
                    displayMarkers(data, selectedYear);
                });
            });
        })
        .catch(error => {
            console.error('Error fetching dive data:', error);
            
            // Show a user-friendly error message
            const errorDiv = L.DomUtil.create('div', 'map-error-message');
            errorDiv.innerHTML = `
                <h3>Error Loading Dive Data</h3>
                <p>${error.message || 'Failed to load dive data. Please refresh the page or try again later.'}</p>
                <button id="retryButton" style="padding: 6px 12px; background-color: #0277bd; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 10px;">Retry</button>
            `;
            
            errorDiv.style.padding = '15px';
            errorDiv.style.backgroundColor = '#f8d7da';
            errorDiv.style.color = '#721c24';
            errorDiv.style.borderRadius = '4px';
            errorDiv.style.margin = '20px auto';
            errorDiv.style.textAlign = 'center';
            errorDiv.style.maxWidth = '80%';
            
            // Clear any existing content in the map container
            const mapElement = document.getElementById('map');
            if (mapElement) {
                // Center the error message in the map
                errorDiv.style.position = 'absolute';
                errorDiv.style.zIndex = '1000';
                errorDiv.style.top = '50%';
                errorDiv.style.left = '50%';
                errorDiv.style.transform = 'translate(-50%, -50%)';
                
                mapElement.appendChild(errorDiv);
                
                // Add event listener to retry button
                const retryButton = document.getElementById('retryButton');
                if (retryButton) {
                    retryButton.addEventListener('click', function() {
                        // Remove the error message
                        if (errorDiv.parentNode) {
                            errorDiv.parentNode.removeChild(errorDiv);
                        }
                        
                        // Reload the page to retry
                        window.location.reload();
                    });
                }
            }
        });
});