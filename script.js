document.addEventListener('DOMContentLoaded', function() {
    // Initialize the map with a world view
    var map = L.map('map').setView([20, 0], 2); // Center at 0,0 with zoom level 2 (world view)

    // Add the tile layer (map background)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Store all markers for filtering
    var markers = [];
    
    // Function to display markers based on selected year
    function displayMarkers(selectedYear) {
        // Create a bounds object to fit visible markers
        var bounds = L.latLngBounds();
        let visibleCount = 0;
        
        // Loop through all markers
        markers.forEach(function(marker) {
            if (selectedYear === 'all' || marker.diveYear === selectedYear) {
                // Show this marker
                marker.addTo(map);
                bounds.extend(marker.getLatLng());
                visibleCount++;
            } else {
                // Hide this marker
                map.removeLayer(marker);
            }
        });
        
        // Fit map to visible markers
        if (visibleCount > 1) {
            map.fitBounds(bounds, {
                padding: [50, 50] // Add some padding around the bounds
            });
        } else if (visibleCount === 1) {
            // If there's only one visible marker, center on it with a closer zoom
            map.setView(bounds.getCenter(), 10);
        }
        
        // Update counter in UI
        document.getElementById('filtered-count').textContent = visibleCount;
    }

    // Check if we have dive log data
    if (typeof diveLogsData !== 'undefined' && diveLogsData.length > 0) {
        // Create markers for each dive spot
        diveLogsData.forEach(function(diveLog) {
            // Create marker
            var marker = L.marker([diveLog.latitude, diveLog.longitude]);
            
            // Store the year with the marker for filtering
            marker.diveYear = diveLog.year;
            
            // Create popup content
            var popupContent = `
                <div class="dive-popup">
                    <h3>${diveLog.location}</h3>
                    <p><strong>Date:</strong> ${diveLog.date}</p>`;
            
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
            
            // Bind popup to marker
            marker.bindPopup(popupContent, {
                maxWidth: 350,
                minWidth: 300,
                className: 'dive-popup-container'
            });
            
            // Add to markers array
            markers.push(marker);
            
            // Add to map initially
            marker.addTo(map);
        });
        
        // Initial map fit to bounds
        if (markers.length > 1) {
            var bounds = L.latLngBounds(markers.map(function(marker) {
                return marker.getLatLng();
            }));
            
            map.fitBounds(bounds, {
                padding: [50, 50] // Add some padding around the bounds
            });
        } else if (markers.length === 1) {
            // If there's only one marker, center on it with a closer zoom
            map.setView(markers[0].getLatLng(), 10);
        }
        
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
        countElement.textContent = markers.length;
        
        var countContainer = document.querySelector('.filter-container h3');
        countContainer.innerHTML += ' <span>(<span id="filtered-count"></span> of ' + markers.length + ' dives)</span>';
        
        // Set initial count
        document.getElementById('filtered-count').textContent = markers.length;
    }
}); 