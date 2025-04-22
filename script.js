document.addEventListener('DOMContentLoaded', function() {
    console.log('Script.js loaded');
    
    // If map.js has already defined these functions, we use them
    // Otherwise, we define our own versions
    if (typeof renderDiveStats !== 'function') {
        // Only define if not already defined by map.js
        window.renderDiveStats = function(dives) {
            console.log(`Rendering dive stats for ${dives.length} dives`);
            
            // Get stats container
            const statsContainer = document.getElementById('dive-stats');
            if (!statsContainer) {
                console.error('Stats container #dive-stats not found in DOM. Creating it...');
                // Try to find the parent container and create the missing element
                const parentContainer = document.querySelector('.dive-stats-container');
                if (parentContainer) {
                    const rowDiv = document.createElement('div');
                    rowDiv.id = 'dive-stats';
                    rowDiv.className = 'row';
                    parentContainer.prepend(rowDiv);
                    console.log('Created #dive-stats container');
                } else {
                    console.error('Could not find parent container .dive-stats-container');
                    return;
                }
            }
            
            // Get the stats container again in case we just created it
            const container = document.getElementById('dive-stats');
            if (!container) {
                console.error('Still cannot find #dive-stats container after attempted creation');
                return;
            }
            
            // Clear existing content
            container.innerHTML = '';
            
            // Calculate statistics
            const totalDives = dives.length;
            
            // Find max depth
            let maxDepth = 0;
            dives.forEach(dive => {
                const depth = parseFloat(dive.max_depth || dive.depth || 0);
                if (depth > maxDepth) maxDepth = depth;
            });
            
            // Calculate average duration
            let totalDuration = 0;
            let durationsCount = 0;
            dives.forEach(dive => {
                const duration = parseInt(dive.dive_duration || dive.duration || 0);
                if (duration > 0) {
                    totalDuration += duration;
                    durationsCount++;
                }
            });
            const avgDuration = durationsCount > 0 ? Math.round(totalDuration / durationsCount) : 0;
            
            // Count unique locations
            const locations = new Set();
            dives.forEach(dive => {
                if (dive.location) locations.add(dive.location);
            });
            const locationCount = locations.size;
            
            // Calculate total dive time
            const totalDiveTime = totalDuration;
            const totalDiveHours = Math.floor(totalDiveTime / 60);
            const totalDiveMinutes = totalDiveTime % 60;
            
            // Find the latest dive
            let latestDive = null;
            if (dives.length > 0) {
                latestDive = dives.reduce((latest, dive) => {
                    const diveDate = new Date(dive.date || dive.dive_date);
                    const latestDate = latest ? new Date(latest.date || latest.dive_date) : new Date(0);
                    return diveDate > latestDate ? dive : latest;
                });
            }
            
            // Create stat cards
            const stats = [
                {
                    title: 'Total Dives',
                    value: totalDives,
                    subtext: `${locationCount} unique locations`,
                    icon: 'fa-diving-scuba',
                    color: '#4363d8'
                },
                {
                    title: 'Max Depth',
                    value: `${maxDepth}m`,
                    subtext: 'deepest dive',
                    icon: 'fa-water',
                    color: '#3cb44b'
                },
                {
                    title: 'Average Duration',
                    value: `${avgDuration} min`,
                    subtext: 'per dive',
                    icon: 'fa-clock',
                    color: '#ffe119'
                },
                {
                    title: 'Total Dive Time',
                    value: `${totalDiveHours}h ${totalDiveMinutes}m`,
                    subtext: 'underwater',
                    icon: 'fa-hourglass-half',
                    color: '#f58231'
                }
            ];
            
            // If we have a latest dive, add it
            if (latestDive) {
                const diveDate = new Date(latestDive.date || latestDive.dive_date);
                const formattedDate = diveDate.toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric',
                    year: 'numeric'
                });
                
                stats.push({
                    title: 'Latest Dive',
                    value: formattedDate,
                    subtext: latestDive.location,
                    icon: 'fa-calendar-check',
                    color: '#911eb4'
                });
            }
            
            // Calculate depth-duration-consumption correlation
            const depthDurationStats = calculateDepthDurationStats(dives);
            if (depthDurationStats) {
                stats.push({
                    title: 'Depth & Duration',
                    value: depthDurationStats.value,
                    subtext: depthDurationStats.subtext,
                    icon: 'fa-tachometer-alt',
                    color: '#e6194B'
                });
            }
            
            console.log(`Created ${stats.length} stat cards for rendering`);
            
            // Create HTML for stats
            stats.forEach(stat => {
                const cardCol = document.createElement('div');
                cardCol.className = 'col-md-6 col-lg stat-col mb-3';
                
                cardCol.innerHTML = `
                    <div class="stat-card" style="border-top: 3px solid ${stat.color}">
                        <div class="stat-icon">
                            <i class="fas ${stat.icon}" style="color: ${stat.color}"></i>
                        </div>
                        <div class="stat-content">
                            <h3 class="stat-title">${stat.title}</h3>
                            <div class="stat-value">${stat.value}</div>
                            <div class="stat-subtext">${stat.subtext}</div>
                        </div>
                    </div>
                `;
                
                container.appendChild(cardCol);
            });
            
            console.log(`Dive stats rendering complete with ${stats.length} stats cards`);
        };
    }
    
    // Initialize stats if dive data is available
    if (typeof diveLogsData !== 'undefined' && diveLogsData.length > 0) {
        console.log(`Found ${diveLogsData.length} dive logs for statistics`);
        
        // Render dive statistics
        renderDiveStats(diveLogsData);
        
        // Render most valuable stats
        renderMostValuableStats(diveLogsData);
        
        // Render advanced stats
        renderAdvancedStats(diveLogsData);
    } else {
        console.warn("No dive log data available for statistics.");
    }

    // Force a hard refresh when coming from a delete operation
    if (window.location.href.includes('cache_bust')) {
        console.log('Cache bust parameter detected, performing full data refresh');
        
        // Clear any stored dive data from previous sessions
        if (localStorage.getItem('diveMapCache')) {
            localStorage.removeItem('diveMapCache');
        }
        
        // Remove the cache_bust parameter from URL without reloading
        const url = new URL(window.location.href);
        url.searchParams.delete('cache_bust');
        window.history.replaceState({}, document.title, url.toString());
    }

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
        // This function is now replaced by map.js's mapDisplayMarkers
        console.log("script.js displayMarkers called, but using mapDisplayMarkers from map.js instead");
        
        // Make sure mapDisplayMarkers is available
        if (typeof mapDisplayMarkers === 'function' && typeof diveLogsData !== 'undefined') {
            mapDisplayMarkers(diveLogsData, selectedYear);
        } else {
            console.error("mapDisplayMarkers function not available or diveLogsData not defined");
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

    // Function to calculate statistics about depth related to dive length and air consumption
    function calculateDepthDurationStats(dives) {
        // Filter dives that have depth, duration and air consumption data
        const validDives = dives.filter(dive => {
            const depth = parseFloat(dive.max_depth || dive.depth || 0);
            const duration = parseFloat(dive.dive_duration || dive.duration || 0);
            const airStart = parseFloat(dive.air_consumption_start || 0);
            const airEnd = parseFloat(dive.air_consumption_end || 0);
            
            return depth > 0 && duration > 0 && airStart > 0 && airEnd > 0;
        });
        
        if (validDives.length < 3) {
            // Not enough data for meaningful statistics
            return {
                value: 'N/A',
                subtext: 'Need more data'
            };
        }
        
        // Calculate air consumption per minute for each dive
        const consumptionRates = validDives.map(dive => {
            const depth = parseFloat(dive.max_depth || dive.depth);
            const duration = parseFloat(dive.dive_duration || dive.duration);
            const airStart = parseFloat(dive.air_consumption_start);
            const airEnd = parseFloat(dive.air_consumption_end);
            const consumption = airStart - airEnd;
            const consumptionRate = consumption / duration; // bar per minute
            
            return {
                depth,
                duration,
                consumptionRate,
                efficiency: depth / consumptionRate, // meters per bar/min - higher is more efficient
                location: dive.location,
                date: dive.date || dive.dive_date,
                dive_site: dive.dive_site,
                air_start: airStart,
                air_end: airEnd
            };
        });
        
        // Calculate average consumption rate
        const avgConsumptionRate = consumptionRates.reduce((sum, dive) => sum + dive.consumptionRate, 0) / consumptionRates.length;
        
        // Calculate average efficiency (depth/consumption)
        const avgEfficiency = consumptionRates.reduce((sum, dive) => sum + dive.efficiency, 0) / consumptionRates.length;
        
        // Find the most efficient dive (highest depth-to-consumption ratio)
        const mostEfficient = consumptionRates.reduce((best, current) => 
            current.efficiency > best.efficiency ? current : best, consumptionRates[0]);
        
        // Find the least efficient dive (lowest depth-to-consumption ratio)
        const leastEfficient = consumptionRates.reduce((worst, current) => 
            current.efficiency < worst.efficiency ? current : worst, consumptionRates[0]);
        
        // Format the result
        const avgConsumptionFormatted = avgConsumptionRate.toFixed(1);
        
        return {
            value: `${avgConsumptionFormatted} bar/min`,
            subtext: `Depth efficiency: ${avgEfficiency.toFixed(1)}m per bar/min`,
            details: {
                avgConsumptionRate,
                avgEfficiency,
                mostEfficient,
                leastEfficient,
                consumptionRates: consumptionRates
            }
        };
    }
    
    // Function to populate the most valuable dive stats 
    function renderMostValuableStats(dives) {
        console.log(`Rendering most valuable stats for ${dives.length} dives`);
        
        // Get container elements
        const mostEfficientContainer = document.getElementById('most-efficient-dive');
        const leastEfficientContainer = document.getElementById('least-efficient-dive');
        
        if (!mostEfficientContainer || !leastEfficientContainer) {
            console.error('Most/least efficient dive containers not found:', 
                mostEfficientContainer ? 'most-efficient-dive found' : 'most-efficient-dive missing',
                leastEfficientContainer ? 'least-efficient-dive found' : 'least-efficient-dive missing'
            );
            
            // Try to locate parent containers and create missing elements
            const parentContainer = document.getElementById('most-valuable-stats');
            if (parentContainer && !mostEfficientContainer) {
                console.log('Attempting to create most-efficient-dive container');
                const mostEfficientCard = parentContainer.querySelector('.col-md-6:first-child .stat-content');
                if (mostEfficientCard) {
                    const div = document.createElement('div');
                    div.id = 'most-efficient-dive';
                    div.className = 'valuable-stat-content';
                    mostEfficientCard.appendChild(div);
                }
            }
            
            if (parentContainer && !leastEfficientContainer) {
                console.log('Attempting to create least-efficient-dive container');
                const leastEfficientCard = parentContainer.querySelector('.col-md-6:last-child .stat-content');
                if (leastEfficientCard) {
                    const div = document.createElement('div');
                    div.id = 'least-efficient-dive';
                    div.className = 'valuable-stat-content';
                    leastEfficientCard.appendChild(div);
                }
            }
        }
        
        // Check if we have enough data
        const validDives = dives.filter(dive => 
            dive.depth && 
            dive.duration && 
            dive.air_consumption_start && 
            dive.air_consumption_end
        );
        
        if (validDives.length < 2) {
            // Not enough data for comparison
            const message = "Need at least 2 dives with complete depth, duration, and air consumption data";
            console.log(message);
            
            if (mostEfficientContainer) {
                mostEfficientContainer.innerHTML = `<p class="text-muted text-center">${message}</p>`;
            }
            
            if (leastEfficientContainer) {
                leastEfficientContainer.innerHTML = `<p class="text-muted text-center">${message}</p>`;
            }
            
            return;
        }
        
        // Calculate consumption rate and efficiency for each dive
        const divesWithMetrics = validDives.map(dive => {
            const depth = parseFloat(dive.depth);
            const duration = parseFloat(dive.duration);
            const airStart = parseFloat(dive.air_consumption_start);
            const airEnd = parseFloat(dive.air_consumption_end);
            
            const consumption = airStart - airEnd;
            const consumptionRate = consumption / duration; // bar per minute
            
            return {
                ...dive,
                depth,
                duration,
                air_start: airStart,
                air_end: airEnd,
                consumption,
                consumptionRate,
                efficiency: depth / consumptionRate, // meters per bar/min - higher is more efficient
            };
        });
        
        // Sort by efficiency (descending)
        divesWithMetrics.sort((a, b) => b.efficiency - a.efficiency);
        
        // Get most and least efficient dives
        const mostEfficient = divesWithMetrics[0];
        const leastEfficient = divesWithMetrics[divesWithMetrics.length - 1];
        
        console.log('Most efficient dive:', mostEfficient.location, 'with efficiency', mostEfficient.efficiency.toFixed(2));
        console.log('Least efficient dive:', leastEfficient.location, 'with efficiency', leastEfficient.efficiency.toFixed(2));
        
        // Render most efficient dive
        if (mostEfficientContainer) {
            const date = new Date(mostEfficient.date);
            const formattedDate = date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
            
            mostEfficientContainer.innerHTML = `
                <div class="stat-metric">
                    <div class="stat-metric-value">${mostEfficient.efficiency.toFixed(2)}</div>
                    <div class="stat-metric-unit">meters per bar/min</div>
                </div>
                <p class="dive-location">${mostEfficient.location}</p>
                <p class="dive-date">${formattedDate}</p>
                <div class="detail-grid">
                    <div class="detail-item">
                        <i class="fas fa-water"></i> ${mostEfficient.depth}m
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-clock"></i> ${mostEfficient.duration}min
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-compress-alt"></i> ${(mostEfficient.air_start - mostEfficient.air_end).toFixed(0)}bar
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-tachometer-alt"></i> ${mostEfficient.consumptionRate.toFixed(2)}bar/min
                    </div>
                </div>
            `;
        }
        
        // Render least efficient dive
        if (leastEfficientContainer) {
            const date = new Date(leastEfficient.date);
            const formattedDate = date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
            
            leastEfficientContainer.innerHTML = `
                <div class="stat-metric">
                    <div class="stat-metric-value">${leastEfficient.efficiency.toFixed(2)}</div>
                    <div class="stat-metric-unit">meters per bar/min</div>
                </div>
                <p class="dive-location">${leastEfficient.location}</p>
                <p class="dive-date">${formattedDate}</p>
                <div class="detail-grid">
                    <div class="detail-item">
                        <i class="fas fa-water"></i> ${leastEfficient.depth}m
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-clock"></i> ${leastEfficient.duration}min
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-compress-alt"></i> ${(leastEfficient.air_start - leastEfficient.air_end).toFixed(0)}bar
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-tachometer-alt"></i> ${leastEfficient.consumptionRate.toFixed(2)}bar/min
                    </div>
                </div>
            `;
        }
        
        console.log('Most valuable stats rendering complete');
    }
    
    // Function to render advanced statistics
    function renderAdvancedStats(dives) {
        console.log(`Rendering advanced stats for ${dives.length} dives`);
        
        const container = document.getElementById('depth-duration-stats');
        if (!container) {
            console.error('Advanced stats container #depth-duration-stats not found');
            return;
        }
        
        // Filter dives that have depth, duration and air consumption data
        const validDives = dives.filter(dive => 
            dive.depth && 
            dive.duration && 
            dive.air_consumption_start && 
            dive.air_consumption_end
        );
        
        if (validDives.length < 3) {
            container.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Insufficient Data:</strong> Add more dives with complete depth, duration, and air consumption information to view advanced statistics.
                </div>
            `;
            console.log('Not enough dive data for advanced stats (need at least 3 dives)');
            return;
        }
        
        console.log(`Processing ${validDives.length} valid dives for advanced stats`);
        
        // Calculate metrics for each dive
        const divesWithMetrics = validDives.map(dive => {
            const depth = parseFloat(dive.depth);
            const duration = parseFloat(dive.duration);
            const airStart = parseFloat(dive.air_consumption_start);
            const airEnd = parseFloat(dive.air_consumption_end);
            
            const consumption = airStart - airEnd;
            const consumptionRate = consumption / duration; // bar per minute
            
            return {
                ...dive,
                depth,
                duration,
                air_start: airStart,
                air_end: airEnd,
                consumption,
                consumptionRate,
                efficiency: depth / consumptionRate // meters per bar/min - higher is more efficient
            };
        });
        
        // Sort by efficiency
        divesWithMetrics.sort((a, b) => b.efficiency - a.efficiency);
        
        // Calculate averages
        const avgDepth = divesWithMetrics.reduce((sum, dive) => sum + dive.depth, 0) / divesWithMetrics.length;
        const avgDuration = divesWithMetrics.reduce((sum, dive) => sum + dive.duration, 0) / divesWithMetrics.length;
        const avgConsumptionRate = divesWithMetrics.reduce((sum, dive) => sum + dive.consumptionRate, 0) / divesWithMetrics.length;
        const avgEfficiency = divesWithMetrics.reduce((sum, dive) => sum + dive.efficiency, 0) / divesWithMetrics.length;
        
        // Get most and least efficient dives
        const mostEfficient = divesWithMetrics[0];
        const leastEfficient = divesWithMetrics[divesWithMetrics.length - 1];
        
        // Create HTML content
        const html = `
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">Air Consumption Overview</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <div>Average Consumption Rate:</div>
                                <strong>${avgConsumptionRate.toFixed(2)} bar/min</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <div>Average Efficiency:</div>
                                <strong>${avgEfficiency.toFixed(2)} m per bar/min</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <div>Most Efficient Dive:</div>
                                <strong>${mostEfficient.efficiency.toFixed(2)} m per bar/min</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <div>Least Efficient Dive:</div>
                                <strong>${leastEfficient.efficiency.toFixed(2)} m per bar/min</strong>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">Factors Affecting Air Consumption</h6>
                        </div>
                        <div class="card-body">
                            <p>Air consumption is typically affected by:</p>
                            <ul>
                                <li><strong>Depth:</strong> Greater depth = higher pressure = more air consumption</li>
                                <li><strong>Activity Level:</strong> More exertion = higher air consumption</li>
                                <li><strong>Experience:</strong> More experienced divers typically have better air efficiency</li>
                                <li><strong>Equipment:</strong> Properly maintained gear minimizes air loss</li>
                                <li><strong>Physical Fitness:</strong> Better fitness = improved breathing efficiency</li>
                                <li><strong>Stress/Anxiety:</strong> Calm divers use less air</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0">Best Air Efficiency</h6>
                        </div>
                        <div class="card-body">
                            <h5>${mostEfficient.location}, ${new Date(mostEfficient.date).toLocaleDateString()}</h5>
                            <div class="dive-details">
                                <p class="mb-1"><strong>Depth:</strong> ${mostEfficient.depth.toFixed(1)} meters</p>
                                <p class="mb-1"><strong>Duration:</strong> ${mostEfficient.duration} minutes</p>
                                <p class="mb-1"><strong>Consumption:</strong> ${(mostEfficient.air_start - mostEfficient.air_end).toFixed(0)} bar total</p>
                                <p class="mb-1"><strong>Consumption Rate:</strong> ${mostEfficient.consumptionRate.toFixed(2)} bar/min</p>
                                <p class="mb-1"><strong>Efficiency:</strong> ${mostEfficient.efficiency.toFixed(2)} m per bar/min</p>
                                ${mostEfficient.comments ? `<p class="mt-2"><strong>Comments:</strong> ${mostEfficient.comments}</p>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0">Worst Air Efficiency</h6>
                        </div>
                        <div class="card-body">
                            <h5>${leastEfficient.location}, ${new Date(leastEfficient.date).toLocaleDateString()}</h5>
                            <div class="dive-details">
                                <p class="mb-1"><strong>Depth:</strong> ${leastEfficient.depth.toFixed(1)} meters</p>
                                <p class="mb-1"><strong>Duration:</strong> ${leastEfficient.duration} minutes</p>
                                <p class="mb-1"><strong>Consumption:</strong> ${(leastEfficient.air_start - leastEfficient.air_end).toFixed(0)} bar total</p>
                                <p class="mb-1"><strong>Consumption Rate:</strong> ${leastEfficient.consumptionRate.toFixed(2)} bar/min</p>
                                <p class="mb-1"><strong>Efficiency:</strong> ${leastEfficient.efficiency.toFixed(2)} m per bar/min</p>
                                ${leastEfficient.comments ? `<p class="mt-2"><strong>Comments:</strong> ${leastEfficient.comments}</p>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-12">
                    <h5 class="mb-3">Air Consumption vs. Depth Analysis</h5>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th>Date</th>
                                    <th>Location</th>
                                    <th>Depth (m)</th>
                                    <th>Duration (min)</th>
                                    <th>Air Used (bar)</th>
                                    <th>Rate (bar/min)</th>
                                    <th>Efficiency (m per bar/min)</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${divesWithMetrics.map(dive => `
                                    <tr>
                                        <td>${new Date(dive.date).toLocaleDateString()}</td>
                                        <td>${dive.location}</td>
                                        <td>${dive.depth.toFixed(1)}</td>
                                        <td>${dive.duration}</td>
                                        <td>${(dive.air_start - dive.air_end).toFixed(0)}</td>
                                        <td>${dive.consumptionRate.toFixed(2)}</td>
                                        <td>${dive.efficiency.toFixed(2)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        container.innerHTML = html;
        console.log('Advanced stats rendering complete');
    }
});