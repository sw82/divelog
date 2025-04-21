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
        console.log(`Creating marker for dive ${dive.id} at ${dive.latitude}, ${dive.longitude}`);
        
        // Ensure latitude and longitude are valid numbers
        const lat = parseFloat(dive.latitude);
        const lng = parseFloat(dive.longitude);
        
        if (isNaN(lat) || isNaN(lng)) {
            console.error(`Invalid coordinates for dive ${dive.id}: lat=${dive.latitude}, lng=${dive.longitude}`);
            return null;
        }
        
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
        
        try {
            // Create marker at the dive's coordinates
            const marker = L.circleMarker([lat, lng], markerOptions);
            
            // Create popup content for the marker
            const popupContent = createDivePopup(dive);
            
            // Bind popup to marker
            marker.bindPopup(popupContent, {
                maxWidth: 350,
                className: 'dive-popup-container'
            });
            
            // Store the dive data in the marker for later access
            marker.diveData = dive;
            
            return marker;
        } catch (error) {
            console.error(`Error creating marker for dive ${dive.id}:`, error);
            return null;
        }
    }
    
    // Create popup HTML content for a dive
    function createDivePopup(dive) {
        // Format the date
        let date = new Date(dive.dive_date || dive.date);
        let formattedDate = 'Unknown Date';
        
        // Check if date is valid before formatting
        if (!isNaN(date.getTime())) {
            formattedDate = date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        } else {
            console.warn('Invalid date format for dive:', dive.id, dive.dive_date);
            // Fallback: try to parse date from the raw date string if available
            if (dive.date) {
                const fallbackDate = new Date(dive.date);
                if (!isNaN(fallbackDate.getTime())) {
                    formattedDate = fallbackDate.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                }
            }
        }
        
        // Add time if available
        if (dive.dive_time) {
            formattedDate += ` at ${dive.dive_time}`;
        }
        
        // Create the star rating
        let ratingHtml = '';
        const rating = parseInt(dive.rating) || 0;
        for (let i = 1; i <= 5; i++) {
            if (i <= rating) {
                ratingHtml += '<i class="fas fa-star"></i>';
            } else {
                ratingHtml += '<i class="far fa-star"></i>';
            }
        }
        
        // Start building the content
        let content = `
            <div class="dive-popup">
                <div class="dive-header">
                    <h3>${dive.dive_site || dive.location || 'Unnamed Dive'}</h3>
                    <div class="dive-rating">${ratingHtml}</div>
                    <div class="dive-date"><i class="far fa-calendar-alt mr-1"></i> ${formattedDate}</div>
                </div>`;
        
        // Images section
        if (dive.images && dive.images.length > 0) {
            content += `<div class="dive-images">`;
            
            // Display up to 3 images
            const imagesToShow = dive.images.slice(0, 3);
            imagesToShow.forEach(image => {
                content += `<img src="${image.image_path}" alt="Dive image" class="dive-img">`;
            });
            
            // If there are more than 3 images, add a note
            if (dive.images.length > 3) {
                content += `<div class="more-images">+${dive.images.length - 3} more</div>`;
            }
            
            content += `</div>`;
        }
        
        // Technical dive details grid - add suit type, water type, air consumption, weight
        content += `
            <div class="dive-details-grid">
                <div class="detail-item">
                    <i class="fas fa-water"></i>
                    <span class="detail-label">Depth</span>
                    <span class="detail-value">${dive.max_depth || dive.depth || '-'} m</span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-clock"></i>
                    <span class="detail-label">Duration</span>
                    <span class="detail-value">${dive.dive_duration || dive.duration || '-'} min</span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-temperature-low"></i>
                    <span class="detail-label">Water Temp</span>
                    <span class="detail-value">${dive.water_temp || dive.temperature || '-'} °C</span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-tint"></i>
                    <span class="detail-label">Visibility</span>
                    <span class="detail-value">${dive.visibility || '-'} m</span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-sun"></i>
                    <span class="detail-label">Air Temp</span>
                    <span class="detail-value">${dive.air_temp || dive.air_temperature || '-'} °C</span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-weight-hanging"></i>
                    <span class="detail-label">Weight</span>
                    <span class="detail-value">${dive.weight || '-'} kg</span>
                </div>
            </div>`;
            
        // Technical dive details - second row for air consumption and suit/water type
        if (dive.air_consumption_start || dive.air_consumption_end || dive.suit_type || dive.water_type) {
            content += `
                <div class="dive-technical-details">
                    <div class="row">`;
            
            // Air consumption
            if (dive.air_consumption_start || dive.air_consumption_end) {
                content += `
                    <div class="col-6">
                        <div class="tech-detail-item">
                            <i class="fas fa-compress-alt"></i>
                            <span class="tech-detail-label">Air Consumption</span>
                            <span class="tech-detail-value">
                                ${dive.air_consumption_start || '-'} → ${dive.air_consumption_end || '-'} bar
                            </span>
                        </div>
                    </div>`;
            }
            
            // Suit type
            if (dive.suit_type) {
                let suitIcon = 'fa-tshirt';
                if (dive.suit_type === 'drysuit') suitIcon = 'fa-user-astronaut';
                else if (dive.suit_type === 'wetsuit') suitIcon = 'fa-user';
                
                content += `
                    <div class="col-6">
                        <div class="tech-detail-item">
                            <i class="fas ${suitIcon}"></i>
                            <span class="tech-detail-label">Suit Type</span>
                            <span class="tech-detail-value">
                                ${dive.suit_type.charAt(0).toUpperCase() + dive.suit_type.slice(1)}
                            </span>
                        </div>
                    </div>`;
            }
            
            // Water type
            if (dive.water_type) {
                content += `
                    <div class="col-6">
                        <div class="tech-detail-item">
                            <i class="fas fa-water"></i>
                            <span class="tech-detail-label">Water Type</span>
                            <span class="tech-detail-value">
                                ${dive.water_type.charAt(0).toUpperCase() + dive.water_type.slice(1)}
                            </span>
                        </div>
                    </div>`;
            }
            
            content += `
                    </div>
                </div>`;
        }
        
        // Description section
        if (dive.description) {
            let description = dive.description;
            let shortDesc = description;
            let readMoreLink = '';
            
            // If description is long, truncate it and add a "read more" link
            if (description.length > 150) {
                shortDesc = description.substring(0, 150) + '...';
                readMoreLink = `<a href="#" class="read-more" onclick="toggleDescription(this, event)">Read more</a>`;
            }
            
            content += `
                <div class="dive-description">
                    <h4><i class="fas fa-info-circle mr-2"></i>Description</h4>
                    <p class="short-desc">${shortDesc}</p>
                    <p class="full-desc" style="display: none;">${description}</p>
                    ${readMoreLink}
                </div>`;
        }
        
        // Additional information
        if (dive.site_type || dive.buddy) {
            content += `<div class="dive-additional-info">`;
            
            if (dive.site_type) {
                content += `<div class="info-item"><i class="fas fa-map-marker-alt mr-1"></i><span class="info-label">Site Type:</span> ${dive.site_type}</div>`;
            }
            
            if (dive.buddy) {
                content += `<div class="info-item"><i class="fas fa-user-friends mr-1"></i><span class="info-label">Buddy:</span> ${dive.buddy}</div>`;
            }
            
            content += `</div>`;
        }
        
        // Comments section
        if (dive.comments) {
            content += `
                <div class="dive-comments">
                    <h4><i class="fas fa-comment-alt mr-2"></i>Comments</h4>
                    <p>${dive.comments}</p>
                </div>`;
        }
        
        // Fish sightings section
        if (dive.fish_sightings && dive.fish_sightings.length > 0) {
            content += `
                <div class="fish-sightings">
                    <h4><i class="fas fa-fish mr-2"></i>Fish Sightings (${dive.fish_sightings.length})</h4>
                    <div class="fish-grid">`;
            
            // Display up to 6 fish
            const fishToShow = dive.fish_sightings.slice(0, 6);
            fishToShow.forEach(fish => {
                let fishImage = fish.image_path ? fish.image_path : 'images/fish_placeholder.png';
                
                content += `
                    <div class="fish-item fish-tooltip-trigger">
                        <img src="${fishImage}" alt="${fish.common_name || 'Fish'}" class="fish-img">
                        <div class="fish-name">${fish.common_name || 'Unknown Fish'}</div>
                        <div class="fish-tooltip">
                            <div class="tooltip-content">
                                <div class="fish-scientific-name">${fish.scientific_name || ''}</div>
                                <div class="fish-quantity">Quantity: ${fish.quantity || '1'}</div>
                                ${fish.notes ? `<div class="fish-notes">${fish.notes}</div>` : ''}
                            </div>
                        </div>
                    </div>`;
            });
            
            content += `</div>`;
            
            // If there are more than 6 fish, add a link
            if (dive.fish_sightings.length > 6) {
                content += `<div class="more-fish"><a href="edit_dive.php?id=${dive.id}" class="view-all-fish">View all ${dive.fish_sightings.length} fish sightings</a></div>`;
            }
            
            content += `</div>`;
        }
        
        // Footer with edit link
        content += `
            <div class="dive-footer">
                <a href="edit_dive.php?id=${dive.id}" class="edit-dive-link"><i class="fas fa-edit mr-1"></i>Edit this dive</a>
            </div>
        </div>`;
        
        return content;
    }
    
    // Function to toggle between short and full description
    function toggleDescription(link, event) {
        event.preventDefault();
        const parent = $(link).parent();
        const shortDesc = parent.find('.short-desc');
        const fullDesc = parent.find('.full-desc');
        
        if (shortDesc.is(':visible')) {
            shortDesc.hide();
            fullDesc.show();
            $(link).text('Read less');
        } else {
            fullDesc.hide();
            shortDesc.show();
            $(link).text('Read more');
        }
    }
    
    // Create a custom cluster icon with a nice appearance
    function createClusterIcon(cluster) {
        const childCount = cluster.getChildCount();
        
        // Get years from the cluster for color calculation
        const years = cluster.getAllChildMarkers()
            .map(marker => marker.diveData && marker.diveData.year)
            .filter(year => year);
        
        // Use most recent year for color if available, otherwise use a default
        let clusterColor = '#4363d8'; // Default blue color
        if (years.length > 0) {
            // Find the most recent year
            const mostRecentYear = Math.max(...years);
            clusterColor = getColorForYear(mostRecentYear);
        }
        
        // Create the HTML for the cluster icon
        let html = `
            <div class="cluster-icon" style="background-color: ${clusterColor}">
                <span>${childCount}</span>
            </div>
        `;
        
        return L.divIcon({
            html: html,
            className: 'custom-cluster-icon',
            iconSize: L.point(36, 36),
            iconAnchor: L.point(18, 18)
        });
    }
    
    // Create a custom popup for clusters
    function createClusterPopup(cluster) {
        const markers = cluster.getAllChildMarkers();
        const dives = markers.map(marker => marker.diveData).filter(dive => dive);
        
        // Group dives by location
        const locations = {};
        dives.forEach(dive => {
            if (!locations[dive.location]) {
                locations[dive.location] = [];
            }
            locations[dive.location].push(dive);
        });
        
        // Create popup content
        let popupContent = `
            <div class="cluster-popup">
                <h3>${markers.length} Dives in this Area</h3>
                <p>Locations:</p>
                <ul class="locations-list">
        `;
        
        // Add each location with count
        Object.keys(locations).sort().forEach(location => {
            const locationDives = locations[location];
            popupContent += `
                <li>
                    ${location} (${locationDives.length})
                    <a href="#" class="zoom-to-location" 
                       data-lat="${locationDives[0].latitude}" 
                       data-lng="${locationDives[0].longitude}">
                       Zoom
                    </a>
                </li>
            `;
        });
        
        popupContent += `
                </ul>
                <p class="cluster-note">Zoom in to see individual dive markers</p>
            </div>
        `;
        
        return popupContent;
    }
    
    // Display markers on the map
    function displayMarkers(dives, selectedYear = 'all') {
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
        
        // Clear existing markers and clusters
        if (window.markerLayer) {
            map.removeLayer(window.markerLayer);
        }
        
        // Filter dives by selected year if needed
        let filteredDives = dives;
        if (selectedYear !== 'all') {
            filteredDives = dives.filter(dive => dive.year && dive.year.toString() === selectedYear.toString());
        }
        
        console.log(`Displaying ${filteredDives.length} dive markers for year: ${selectedYear}`);
        console.log('First 5 dives:', filteredDives.slice(0, 5));
        
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
        
        // Create a marker cluster group with custom options
        window.markerLayer = L.markerClusterGroup({
            maxClusterRadius: 30,               // Smaller radius for more precise clusters
            spiderfyOnMaxZoom: true,            // Enable spiderfy at max zoom
            showCoverageOnHover: false,         // Don't show cluster coverage area
            zoomToBoundsOnClick: true,          // Zoom to bounds when clicking a cluster
            disableClusteringAtZoom: 14,        // Don't cluster at very zoomed in levels
            iconCreateFunction: createClusterIcon,  // Custom cluster icon function
            chunkedLoading: true,               // Load markers in chunks to avoid UI blocking
            animate: true                       // Animate cluster to new markers
        });
        
        // Create and add markers for each dive
        let createdMarkers = 0;
        let invalidCoordinates = 0;
        let errorMarkers = 0;
        
        filteredDives.forEach(dive => {
            try {
                // All dives should have valid coordinates now, but double-check anyway
                if (!dive.latitude || !dive.longitude || 
                    isNaN(parseFloat(dive.latitude)) || isNaN(parseFloat(dive.longitude)) ||
                    Math.abs(parseFloat(dive.latitude)) < 0.0001 || Math.abs(parseFloat(dive.longitude)) < 0.0001) {
                    console.warn(`Dive ${dive.id} has invalid coordinates: lat=${dive.latitude}, lng=${dive.longitude}`);
                    invalidCoordinates++;
                    return;
                }
                
                const marker = createDiveMarker(dive);
                if (marker) {
                    window.markerLayer.addLayer(marker);
                    createdMarkers++;
                } else {
                    errorMarkers++;
                }
            } catch (err) {
                console.error(`Error creating marker for dive ${dive.id}:`, err);
                errorMarkers++;
            }
        });
        
        console.log(`Markers summary: created=${createdMarkers}, invalid=${invalidCoordinates}, errors=${errorMarkers}`);
        
        // Add popup for clusters
        window.markerLayer.on('clusterclick', function(e) {
            const cluster = e.layer;
            const popup = L.popup()
                .setLatLng(cluster.getLatLng())
                .setContent(createClusterPopup(cluster))
                .openOn(map);
            
            // Add event listeners for "Zoom" links after popup is opened
            setTimeout(() => {
                document.querySelectorAll('.zoom-to-location').forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const lat = parseFloat(this.getAttribute('data-lat'));
                        const lng = parseFloat(this.getAttribute('data-lng'));
                        if (!isNaN(lat) && !isNaN(lng)) {
                            map.setView([lat, lng], 14);
                            map.closePopup();
                        }
                    });
                });
            }, 100);
        });
        
        // Add the marker cluster group to the map
        map.addLayer(window.markerLayer);
        
        // Update the counter element if it exists
        const countElement = document.getElementById('filtered-count');
        if (countElement) {
            countElement.textContent = filteredDives.length;
        }
        
        // Count how many layers exist
        const layerCount = window.markerLayer ? window.markerLayer.getLayers().length : 0;
        console.log(`Total layers added to map: ${layerCount}`);
        
        // Fit map to markers if we have any
        if (window.markerLayer && window.markerLayer.getLayers().length > 0) {
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
            
            return div;
        };
        
        return legend;
    }
    
    // Function to show error messages on the map
    function showMapError(message, retryable = true) {
        console.error('Map Error:', message);
        
        // Create error control if it doesn't exist
        let errorControl = document.querySelector('.map-error-control');
        if (!errorControl) {
            errorControl = L.control({ position: 'topright' });
            
            errorControl.onAdd = function() {
                const div = L.DomUtil.create('div', 'map-error-control');
                div.style.padding = '10px 15px';
                div.style.backgroundColor = '#f8d7da';
                div.style.color = '#721c24';
                div.style.borderRadius = '4px';
                div.style.margin = '10px';
                div.style.maxWidth = '300px';
                div.style.boxShadow = '0 0 10px rgba(0,0,0,0.2)';
                return div;
            };
            
            errorControl.addTo(map);
            errorControl = document.querySelector('.map-error-control');
        }
        
        // Update the error message
        errorControl.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                    <strong>Error</strong>
                    <div style="margin-top: 5px;">${message}</div>
                    ${retryable ? '<button id="map-error-retry" style="padding: 3px 8px; background-color: #0277bd; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 8px;">Retry</button>' : ''}
                </div>
                <button id="map-error-close" style="background: none; border: none; cursor: pointer; font-size: 16px; margin-left: 10px;">&times;</button>
            </div>
        `;
        
        // Add event listeners
        document.getElementById('map-error-close').addEventListener('click', function() {
            errorControl.style.display = 'none';
        });
        
        if (retryable) {
            document.getElementById('map-error-retry').addEventListener('click', function() {
                errorControl.style.display = 'none';
                window.location.reload();
            });
        }
    }
    
    // Fetch dive data and initialize map
    fetch('get_dive_data.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server error: ${response.status} ${response.statusText}`);
            }
            console.log('Response received, attempting to parse JSON');
            return response.text().then(text => {
                try {
                    // Check if response is empty
                    if (!text || text.trim() === '') {
                        throw new Error('Server returned empty response');
                    }
                    
                    // Check if response starts with PHP error
                    if (text.includes('Fatal error') || text.includes('Parse error') || text.includes('Warning:')) {
                        console.error('PHP error detected in response');
                        throw new Error('Server error: PHP error in response');
                    }
                    
                    // Log the raw text if it's small enough
                    if (text.length < 1000) {
                        console.log('Raw response:', text);
                    } else {
                        console.log('Raw response (first 1000 chars):', text.substring(0, 1000));
                    }
                    
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Raw response causing error:', text);
                    throw new Error(`Failed to parse server response: ${e.message}`);
                }
            });
        })
        .then(data => {
            // Check if we received an error in the JSON
            if (data && data.error) {
                throw new Error(data.error);
            }
            
            // Check if data is in the expected format
            if (!Array.isArray(data)) {
                // For single dive responses, the API might return {success: true, data: {}}
                if (data.success && data.data) {
                    data = [data.data];
                } else {
                    throw new Error('Server returned unexpected data format');
                }
            }
            
            // Store dive data globally
            window.diveData = data;
            
            console.log(`Received ${data.length} dive logs`);
            console.log('Sample data item:', data.length > 0 ? data[0] : 'No data');
            
            // Extract unique years for filtering
            const uniqueYears = [...new Set(data.map(dive => dive.year).filter(year => year))].sort().reverse();
            
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
            
            // Determine the user-friendly error message
            let errorMessage;
            
            if (error.name === 'TypeError' && error.message.includes('NetworkError')) {
                errorMessage = 'Network error: Please check your internet connection.';
            } else if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
                errorMessage = 'Unable to contact the server. Please try again later.';
            } else if (error.message.includes('JSON')) {
                errorMessage = 'Error processing server response. Please contact the administrator.';
            } else if (error.message.includes('Server error')) {
                errorMessage = error.message;
            } else {
                errorMessage = 'Failed to load dive data. Please refresh the page or try again later.';
            }
            
            // Show error on the map using our new function
            showMapError(errorMessage);
        });
});