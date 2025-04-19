document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const menuItems = document.querySelector('.menu-items');
    
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            menuItems.classList.toggle('active');
        });
    }
    
    // Close menu when clicking outside
    document.addEventListener('click', function(event) {
        if (menuItems && menuItems.classList.contains('active') && 
            !event.target.closest('.menu') && event.target !== mobileMenuToggle) {
            menuItems.classList.remove('active');
        }
    });
    
    // Initialize the map with a world view
    var map = L.map('map', {
        minZoom: 2,  // Prevent zooming out beyond world view
        maxBounds: [[-90, -180], [90, 180]],  // Restrict to one world
        maxBoundsViscosity: 1.0,  // Make the bounds completely solid
        worldCopyJump: false  // Disable world copies
    }).setView([20, 0], 2); // Center at 0,0 with zoom level 2 (world view)

    // Add the tile layer (map background)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        noWrap: true,  // Prevent multiple worlds horizontally
        bounds: [[-90, -180], [90, 180]],  // Restrict tiles to one world
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Create a marker cluster group for dive markers with custom styling
    var markerClusterGroup = L.markerClusterGroup({
        showCoverageOnHover: false,
        maxClusterRadius: 50,
        spiderfyOnMaxZoom: true,
        disableClusteringAtZoom: 16,
        zoomToBoundsOnClick: true,
        animate: true,
        animateAddingMarkers: true,
        chunkedLoading: true,
        singleMarkerMode: true
    });
    map.addLayer(markerClusterGroup);

    // Add event for cluster click to show a popup with summary of contained dives
    markerClusterGroup.on('clusterclick', function(event) {
        const cluster = event.layer;
        const markers = cluster.getAllChildMarkers();
        
        // Get unique locations from the cluster's markers
        const locations = {};
        markers.forEach(function(marker) {
            // Each marker has its original dive log data attached
            const diveLog = marker.diveData;
            
            if (diveLog) {
                const locationKey = diveLog.location;
                if (!locations[locationKey]) {
                    locations[locationKey] = [];
                }
                locations[locationKey].push(diveLog);
            }
        });
        
        // Create popup content with location summary
        let popupContent = `
            <div class="dive-popup cluster-popup">
                <h3>Dive Locations in this Area</h3>
                <p>${markers.length} dives at ${Object.keys(locations).length} locations</p>
                <ul class="locations-list">
        `;
        
        // Add each location with dive count
        Object.entries(locations).forEach(([locationName, dives]) => {
            popupContent += `
                <li>
                    <strong>${locationName}</strong>: ${dives.length} dive${dives.length > 1 ? 's' : ''} 
                    <small>(${dives.map(d => d.date.substring(0, 4)).join(', ')})</small>
                </li>
            `;
        });
        
        popupContent += `
                </ul>
                <p class="cluster-note">Click to zoom in and see individual dive locations.</p>
            </div>
        `;
        
        // Create a popup at the cluster position
        L.popup({
            maxWidth: 300,
            className: 'cluster-popup-container'
        })
        .setLatLng(cluster.getLatLng())
        .setContent(popupContent)
        .openOn(map);
    });

    // When a marker is added to the cluster group, ensure the popup works correctly
    markerClusterGroup.on('layeradd', function(event) {
        const layer = event.layer;
        if (layer && layer.getLatLng && layer.bindPopup) {
            // Make sure popups on individual markers work
            layer.on('click', function(e) {
                // Stop the click event from propagating to the map
                L.DomEvent.stopPropagation(e);
                
                // Open the popup for this marker
                this.openPopup();
            });
        }
    });

    // Handle window resize - update map size
    function adjustMapSize() {
        if (map) {
            map.invalidateSize();
        }
    }
    
    // Run on load and when window resizes
    adjustMapSize();
    window.addEventListener('resize', adjustMapSize);

    // Store all markers and marker groups for filtering
    var markers = [];
    var markerGroups = {};
    
    // Define colors for different years
    var yearColors = {};
    var colorPalette = [
        '#e6194B', '#3cb44b', '#ffe119', '#4363d8', '#f58231', 
        '#911eb4', '#42d4f4', '#f032e6', '#bfef45', '#fabed4', 
        '#469990', '#dcbeff', '#9A6324', '#fffac8', '#800000', 
        '#aaffc3', '#808000', '#ffd8b1', '#000075', '#a9a9a9'
    ];
    
    // Function to get color for a year
    function getColorForYear(year) {
        if (!yearColors[year]) {
            // Assign a color from the palette, or cycle back if we have more years than colors
            const colorIndex = Object.keys(yearColors).length % colorPalette.length;
            yearColors[year] = colorPalette[colorIndex];
        }
        return yearColors[year];
    }
    
    // Function to create a colored marker
    function createColoredMarker(lat, lng, color, count) {
        let html;
        
        if (count > 1) {
            // Create a marker with a number for multiple activities
            html = `<div style="background-color: ${color}; width: 28px; height: 28px; border-radius: 14px; border: 2px solid white; display: flex; align-items: center; justify-content: center; font-weight: bold; color: white;">${count}</div>`;
        } else {
            // Regular marker for a single activity
            html = `<div style="background-color: ${color}; width: 24px; height: 24px; border-radius: 12px; border: 2px solid white;"></div>`;
        }
        
        // Create marker with interactive options
        const marker = L.marker([lat, lng], {
            icon: L.divIcon({
                className: 'colored-marker',
                html: html,
                iconSize: count > 1 ? [32, 32] : [28, 28],
                iconAnchor: count > 1 ? [16, 16] : [14, 14],
                popupAnchor: [0, -14]
            }),
            interactive: true,
            bubblingMouseEvents: false
        });
        
        return marker;
    }
    
    // Function to get appropriate popup dimensions based on screen width
    function getPopupDimensions(isMultiple = false) {
        const isMobile = window.innerWidth < 768;
        if (isMultiple) {
            return {
                maxWidth: isMobile ? 280 : 400,
                minWidth: isMobile ? 250 : 320
            };
        } else {
            return {
                maxWidth: isMobile ? 250 : 350,
                minWidth: isMobile ? 200 : 300
            };
        }
    }
    
    // Function to display markers based on selected year
    function displayMarkers(selectedYear) {
        // Clear all existing markers from the map
        markers.forEach(function(marker) {
            if (marker._map) { // Only remove if it's on the map
                map.removeLayer(marker);
            }
        });
        
        // Remove all markers from the cluster group
        markerClusterGroup.clearLayers();
        
        // Reset markers array
        markers = [];
        
        // Create a bounds object to fit visible markers
        var bounds = L.latLngBounds();
        let visibleCount = 0;
        
        // Add markers for all visible dives (without manual proximity grouping)
        if (typeof diveLogsData !== 'undefined' && diveLogsData.length > 0) {
            diveLogsData.forEach(function(diveLog) {
                if (selectedYear === 'all' || diveLog.year === selectedYear) {
                    // This dive should be visible
                    visibleCount++;
                    
                    const latLng = [parseFloat(diveLog.latitude), parseFloat(diveLog.longitude)];
                    const markerColor = getColorForYear(diveLog.year);
                    
                    // Create a marker for this dive
                    let marker = createColoredMarker(latLng[0], latLng[1], markerColor, 1);
                    
                    // Create popup content for a single dive
                    const popupContent = createSingleDivePopup(diveLog);
                    
                    // Bind popup to marker with responsive dimensions
                    const popupDimensions = getPopupDimensions();
                    marker.bindPopup(popupContent, {
                        maxWidth: popupDimensions.maxWidth,
                        minWidth: popupDimensions.minWidth,
                        className: 'dive-popup-container'
                    });
                    
                    // Store the year with the marker for filtering
                    marker.diveYear = diveLog.year;
                    
                    // Store the dive data with the marker for cluster popups
                    marker.diveData = diveLog;
                    
                    // Add explicit click handler to ensure popup opens
                    marker.on('click', function(e) {
                        this.openPopup();
                    });
                    
                    // Add to markers array and to cluster group
                    markers.push(marker);
                    markerClusterGroup.addLayer(marker);
                    bounds.extend(marker.getLatLng());
                }
            });
            
            // Fit map to visible markers
            if (visibleCount > 1 && bounds.isValid()) {
                map.fitBounds(bounds, {
                    padding: [50, 50] // Add some padding around the bounds
                });
            } else if (visibleCount === 1 && bounds.isValid()) {
                // If there's only one visible marker, center on it with a closer zoom
                map.setView(bounds.getCenter(), 10);
            } else if (visibleCount === 0) {
                // If there are no markers, show world view
                map.setView([20, 0], 2);
            }
            
            // Update counter in UI
            const countElement = document.getElementById('filtered-count');
            if (countElement) {
                countElement.textContent = visibleCount;
            }
        } else {
            console.warn('No dive logs data available or data is empty');
        }
    }
    
    // Function to create popup content for a single dive
    function createSingleDivePopup(diveLog) {
        var popupContent = `
            <div class="dive-popup">
                <h3>${diveLog.location}</h3>
                <p><strong>Date:</strong> ${diveLog.date}</p>
                <p><strong>Activity:</strong> Diving</p>`;
        
        // Add dive details if available
        var detailsAdded = false;
        popupContent += '<div class="dive-details">';
        
        // Add basic dive metrics
        if (diveLog.depth) {
            popupContent += `<p><strong>Depth:</strong> ${diveLog.depth} m</p>`;
            detailsAdded = true;
        }
        if (diveLog.duration) {
            popupContent += `<p><strong>Duration:</strong> ${diveLog.duration} min</p>`;
            detailsAdded = true;
        }
        if (diveLog.temperature) {
            popupContent += `<p><strong>Water Temp:</strong> ${diveLog.temperature}°C</p>`;
            detailsAdded = true;
        }
        
        // Add additional dive details
        if (diveLog.air_temperature) {
            popupContent += `<p><strong>Air Temp:</strong> ${diveLog.air_temperature}°C</p>`;
            detailsAdded = true;
        }
        if (diveLog.visibility) {
            popupContent += `<p><strong>Visibility:</strong> ${diveLog.visibility} m</p>`;
            detailsAdded = true;
        }
        if (diveLog.buddy) {
            popupContent += `<p><strong>Dive Partner:</strong> ${diveLog.buddy}</p>`;
            detailsAdded = true;
        }
        if (diveLog.dive_site_type) {
            popupContent += `<p><strong>Site Type:</strong> ${diveLog.dive_site_type}</p>`;
            detailsAdded = true;
        }
        if (diveLog.rating) {
            // Convert rating number to stars
            const stars = '★'.repeat(diveLog.rating);
            popupContent += `<p><strong>Rating:</strong> ${stars}</p>`;
            detailsAdded = true;
        }
        
        popupContent += '</div>';
        
        // Add description
        if (diveLog.description) {
            popupContent += `<p class="dive-description">${diveLog.description}</p>`;
        }
        
        // Add comments if available
        if (diveLog.comments) {
            popupContent += `<div class="dive-comments">
                <strong>Comments:</strong>
                <p>${diveLog.comments}</p>
            </div>`;
        }
        
        // Add dive images if available
        if (diveLog.images && diveLog.images.length > 0) {
            popupContent += '<div class="popup-images">';
            
            // Limit to 3 images in popup for performance
            const imagesToShow = diveLog.images.slice(0, 3);
            imagesToShow.forEach(function(image) {
                popupContent += `
                    <div class="popup-image">
                        <img src="uploads/diveimages/${image.filename}" alt="Dive Image">
                        ${image.caption ? `<span class="image-caption">${image.caption}</span>` : ''}
                    </div>
                `;
            });
            
            // If there are more images, add a note
            if (diveLog.images.length > 3) {
                popupContent += `<p class="more-images">+ ${diveLog.images.length - 3} more images</p>`;
            }
            
            popupContent += '</div>';
        }
        
        // Add fish sightings if available
        if (diveLog.fish_sightings && diveLog.fish_sightings.length > 0) {
            popupContent += `
                <div class="fish-sightings-container">
                    <div class="fish-sightings-title">Fish Spotted (${diveLog.fish_sightings.length}):</div>
                    <div class="fish-list">
            `;
            
            // Show up to 6 fish in the popup
            const fishToShow = diveLog.fish_sightings.slice(0, 6);
            fishToShow.forEach(function(sighting) {
                // Determine the display name
                const fishName = sighting.common_name || sighting.scientific_name || 'Unknown Fish';
                
                // Create fish item with image if available
                popupContent += `<div class="fish-item">`;
                
                if (sighting.fish_image) {
                    popupContent += `<img src="uploads/fishimages/${sighting.fish_image}" alt="${fishName}" class="fish-image">`;
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
            if (diveLog.fish_sightings.length > 6) {
                popupContent += `<a href="populate_db.php?edit=${diveLog.id}" class="more-fish">+ ${diveLog.fish_sightings.length - 6} more fish species</a>`;
            }
            
            popupContent += `</div>`;
        }
        
        popupContent += `
            <div class="popup-footer">
                <a href="populate_db.php?edit=${diveLog.id}" class="popup-edit-link">Edit Dive</a>
            </div>
        </div>`;
        
        return popupContent;
    }
    
    // Create a legend for year colors
    function createYearLegend(years) {
        var legend = L.control({ position: 'bottomright' });
        
        legend.onAdd = function(map) {
            var div = L.DomUtil.create('div', 'year-legend');
            div.innerHTML = '<h4>Dive Years</h4>';
            
            // Sort years chronologically
            years.sort();
            
            // Add each year with its color
            years.forEach(function(year) {
                div.innerHTML += 
                    `<div class="legend-item">
                        <span class="color-box" style="background-color: ${getColorForYear(year)}"></span>
                        <span>${year}</span>
                    </div>`;
            });
            
            // Add activity type legend
            div.innerHTML += `
                <h4 style="margin-top: 15px;">Activity Type</h4>
                <div class="legend-item">
                    <span class="color-box" style="background-color: #4363d8; border: 2px solid white;"></span>
                    <span>Diving</span>
                </div>`;
            
            return div;
        };
        
        return legend;
    }

    // Check if we have dive log data
    if (typeof diveLogsData !== 'undefined' && diveLogsData.length > 0) {
        // Debug info
        console.log(`Found ${diveLogsData.length} dive logs to display`);
        
        // Collect unique years for the legend
        var uniqueYears = [];
        
        // Track unique years
        diveLogsData.forEach(function(diveLog) {
            if (!uniqueYears.includes(diveLog.year)) {
                uniqueYears.push(diveLog.year);
            }
        });
        
        // Add the year legend to the map
        createYearLegend(uniqueYears).addTo(map);
        
        // Initial display of all markers
        displayMarkers('all');
        
        // Add event listener for dive details
        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('view-dive-details')) {
                e.preventDefault();
                const diveId = e.target.getAttribute('data-dive-id');
                
                // Find the dive data
                const diveData = diveLogsData.find(dive => dive.id == diveId);
                if (diveData) {
                    // Generate detailed popup content
                    const popupContent = createSingleDivePopup(diveData);
                    
                    // Show the popup at the marker location
                    const latLng = [parseFloat(diveData.latitude), parseFloat(diveData.longitude)];
                    L.popup({
                        maxWidth: 350,
                        className: 'dive-popup-container'
                    })
                    .setLatLng(latLng)
                    .setContent(popupContent)
                    .openOn(map);
                    
                    // Center map on this location
                    map.setView(latLng, 14);
                }
            }
        });
        
        // Add year filter click events
        document.querySelectorAll('.year-filter').forEach(function(button) {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                document.querySelectorAll('.year-filter').forEach(function(btn) {
                    btn.classList.remove('active');
                });
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Filter markers by selected year
                displayMarkers(this.getAttribute('data-year'));
            });
        });
        
        // Create filtered count element
        var countElement = document.createElement('span');
        countElement.id = 'filtered-count';
        countElement.textContent = diveLogsData.length;
        
        var countContainer = document.querySelector('.filter-container h3');
        if (countContainer) {
            countContainer.innerHTML += ' <span>(<span id="filtered-count"></span> of ' + diveLogsData.length + ' activities)</span>';
            
            // Set initial count
            document.getElementById('filtered-count').textContent = diveLogsData.length;
        }
    } else {
        console.warn("No dive log data available. Make sure diveLogsData is properly defined.");
    }

    // When a dive detail link is clicked, we want to load the dive data
    $(document).on('click', '.dive-detail-link', function(e) {
        e.preventDefault();
        const diveId = $(this).data('dive-id');
        
        // Show loading indicator
        $('#dive-detail-modal .modal-body').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Loading dive details...</p></div>');
        $('#dive-detail-modal').modal('show');
        
        // Fetch the dive data
        $.ajax({
            url: 'get_dive_data.php',
            type: 'GET',
            data: { id: diveId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const dive = response.data;
                    const popupContent = createDivePopup(dive);
                    $('#dive-detail-modal .modal-body').html(popupContent);
                    
                    // Initialize tooltips
                    $('.fish-tooltip-trigger').hover(
                        function() { $(this).find('.fish-tooltip').show(); },
                        function() { $(this).find('.fish-tooltip').hide(); }
                    );
                } else {
                    $('#dive-detail-modal .modal-body').html('<div class="alert alert-danger">Error loading dive details</div>');
                }
            },
            error: function() {
                $('#dive-detail-modal .modal-body').html('<div class="alert alert-danger">Error connecting to server</div>');
            }
        });
    });
});