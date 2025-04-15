<?php
// This file contains the form for editing dive records
// It's included by populate_db.php when in edit mode
?>

<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="update_entry">
    <input type="hidden" name="id" value="<?php echo $editEntry['id']; ?>">
    
    <div class="form-group">
        <label for="location">Location:</label>
        <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($editEntry['location']); ?>" required>
    </div>
    
    <div class="form-row-container">
        <div class="form-row">
            <div class="form-group half">
                <label for="latitude">Latitude:</label>
                <input type="number" id="latitude" name="latitude" step="any" value="<?php echo $editEntry['latitude']; ?>" required>
            </div>
            
            <div class="form-group half">
                <label for="longitude">Longitude:</label>
                <input type="number" id="longitude" name="longitude" step="any" value="<?php echo $editEntry['longitude']; ?>" required>
            </div>
        </div>
    </div>
    
    <div class="form-group">
        <label for="date">Date:</label>
        <input type="date" id="date" name="date" value="<?php echo $editEntry['date']; ?>" required>
    </div>
    
    <div class="form-group">
        <label for="description">Description:</label>
        <textarea id="description" name="description"><?php echo htmlspecialchars($editEntry['description']); ?></textarea>
    </div>
    
    <div class="form-row-container">
        <div class="form-row">
            <div class="form-group half">
                <label for="depth">Maximum Depth (m):</label>
                <input type="number" id="depth" name="depth" step="0.1" min="0" value="<?php echo $editEntry['depth']; ?>">
            </div>
            
            <div class="form-group half">
                <label for="duration">Duration (min):</label>
                <input type="number" id="duration" name="duration" min="0" value="<?php echo $editEntry['duration']; ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group half">
                <label for="temperature">Water Temperature (°C):</label>
                <input type="number" id="temperature" name="temperature" step="0.1" value="<?php echo $editEntry['temperature']; ?>">
            </div>
            
            <div class="form-group half">
                <label for="air_temperature">Air Temperature (°C):</label>
                <input type="number" id="air_temperature" name="air_temperature" step="0.1" value="<?php echo $editEntry['air_temperature']; ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group half">
                <label for="visibility">Visibility (m):</label>
                <input type="number" id="visibility" name="visibility" min="0" value="<?php echo $editEntry['visibility']; ?>">
            </div>
            
            <div class="form-group half">
                <label for="buddy">Dive Partner/Buddy:</label>
                <input type="text" id="buddy" name="buddy" value="<?php echo htmlspecialchars($editEntry['buddy']); ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group half">
                <label for="dive_site_type">Dive Site Type:</label>
                <select id="dive_site_type" name="dive_site_type">
                    <option value="">-- Select --</option>
                    <?php 
                    $siteTypes = ['Reef', 'Wall', 'Wreck', 'Cave', 'Drift', 'Shore', 'Deep', 'Muck', 'Night', 'Other'];
                    foreach ($siteTypes as $type) {
                        $selected = ($editEntry['dive_site_type'] == $type) ? 'selected' : '';
                        echo "<option value=\"$type\" $selected>$type</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group half">
                <label for="rating">Rating (1-5):</label>
                <select id="rating" name="rating">
                    <option value="">-- Select --</option>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php if ($editEntry['rating'] == $i) echo 'selected'; ?>><?php echo str_repeat('★', $i); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
    </div>
    
    <div class="form-group">
        <label for="comments">Comments/Notes:</label>
        <textarea id="comments" name="comments"><?php echo htmlspecialchars($editEntry['comments'] ?? ''); ?></textarea>
    </div>
    
    <!-- Image Upload Section -->
    <div class="section-title">Media</div>
    <div class="tab-container">
        <div class="tab-buttons">
            <button type="button" class="tab-button active" data-tab="dive-photos">Dive Photos</button>
            <button type="button" class="tab-button" data-tab="logbook">Logbook Pages</button>
        </div>
        
        <div class="tab-content active" id="dive-photos-tab">
            <h4>Dive Photos</h4>
            <div class="image-upload-container">
                <label for="dive-photos-input" class="file-upload-button">
                    <span class="upload-icon">📷</span> Select Dive Photos
                </label>
                <input type="file" name="images[]" id="dive-photos-input" class="file-input" multiple accept="image/jpeg,image/png,image/gif">
                <span class="selected-files-count" id="dive-photos-input-count">No files selected</span>
                <span class="upload-help">JPG, PNG or GIF (max. 5MB each)</span>
            </div>
            
            <?php if (!empty($diveImages) && $editMode): ?>
            <div class="image-gallery">
                <?php foreach ($diveImages as $image): ?>
                    <?php if($image['type'] == 'dive_photo'): ?>
                    <div class="image-item">
                        <img src="uploads/diveimages/<?php echo htmlspecialchars($image['filename']); ?>" alt="Dive photo">
                        <div class="image-controls">
                            <div class="caption-field">
                                <input type="text" name="image_captions[<?php echo $image['id']; ?>]" placeholder="Add caption" value="<?php echo htmlspecialchars($image['caption'] ?? ''); ?>">
                            </div>
                            <button type="button" class="delete-image-btn" data-image-id="<?php echo $image['id']; ?>">Delete</button>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="tab-content" id="logbook-tab">
            <h4>Logbook Pages</h4>
            <div class="image-upload-container">
                <label for="logbook-pages-input" class="file-upload-button">
                    <span class="upload-icon">📄</span> Select Logbook Pages
                </label>
                <input type="file" name="logbook_images[]" id="logbook-pages-input" class="file-input" multiple accept="image/jpeg,image/png,image/gif">
                <span class="selected-files-count" id="logbook-pages-input-count">No files selected</span>
                <span class="upload-help">JPG, PNG or GIF (max. 5MB each)</span>
            </div>
            
            <?php if (!empty($diveImages) && $editMode): ?>
            <div class="image-gallery">
                <?php foreach ($diveImages as $image): ?>
                    <?php if($image['type'] == 'logbook_page'): ?>
                    <div class="image-item">
                        <img src="uploads/diveimages/<?php echo htmlspecialchars($image['filename']); ?>" alt="Logbook page">
                        <div class="image-controls">
                            <div class="caption-field">
                                <input type="text" name="image_captions[<?php echo $image['id']; ?>]" placeholder="Add caption" value="<?php echo htmlspecialchars($image['caption'] ?? ''); ?>">
                            </div>
                            <button type="button" class="delete-image-btn" data-image-id="<?php echo $image['id']; ?>">Delete</button>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Fish Sightings Section -->
    <div class="form-group">
        <h3>Fish Sightings</h3>
        
        <?php if (!empty($diveFishSightings)): ?>
            <div class="fish-sightings-list">
                <table class="fish-table">
                    <thead>
                        <tr>
                            <th style="width: 30px;"></th>
                            <th>Fish Species</th>
                            <th>Date</th>
                            <th>Quantity</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($diveFishSightings as $sighting): ?>
                            <tr>
                                <td>
                                    <?php if ($sighting['fish_image']): ?>
                                        <img src="uploads/fishimages/<?php echo $sighting['fish_image']; ?>" alt="<?php echo htmlspecialchars($sighting['common_name']); ?>" class="fish-thumbnail">
                                    <?php else: ?>
                                        <div class="fish-thumbnail-placeholder"></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($sighting['common_name']); ?></strong>
                                    <?php if ($sighting['scientific_name']): ?>
                                        <div class="scientific-name"><?php echo htmlspecialchars($sighting['scientific_name']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($sighting['sighting_date'])); ?></td>
                                <td><?php echo ucfirst(htmlspecialchars($sighting['quantity'] ?? 'N/A')); ?></td>
                                <td><?php echo htmlspecialchars($sighting['notes'] ?? ''); ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Are you sure you want to remove this fish sighting?');">
                                        <input type="hidden" name="action" value="delete_fish_sighting">
                                        <input type="hidden" name="sighting_id" value="<?php echo $sighting['sighting_id']; ?>">
                                        <input type="hidden" name="divelog_id" value="<?php echo $editEntry['id']; ?>">
                                        <button type="submit" class="danger">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No fish sightings recorded for this dive.</p>
        <?php endif; ?>
        
        <div class="add-fish-form">
            <h4>Add New Fish Sighting</h4>
            <form method="post" class="fish-form">
                <input type="hidden" name="action" value="add_fish_sighting">
                <input type="hidden" name="divelog_id" value="<?php echo $editEntry['id']; ?>">
                <input type="hidden" name="sighting_date" value="<?php echo $editEntry['date']; ?>">
                
                <div class="form-row">
                    <div class="form-group half">
                        <label for="fish_species_id">Fish Species:</label>
                        <select id="fish_species_id" name="fish_species_id" required>
                            <option value="">-- Select Fish Species --</option>
                            <?php foreach ($allFishSpecies as $fish): ?>
                                <option value="<?php echo $fish['id']; ?>">
                                    <?php echo htmlspecialchars($fish['common_name']); ?> 
                                    <?php if (!empty($fish['scientific_name'])): ?>
                                        (<?php echo htmlspecialchars($fish['scientific_name']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group half">
                        <label for="quantity">Approximate Quantity:</label>
                        <select id="quantity" name="quantity">
                            <option value="single">Single</option>
                            <option value="few">Few (2-5)</option>
                            <option value="many">Many (5-20)</option>
                            <option value="school">School (20+)</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <textarea id="notes" name="notes" rows="2"></textarea>
                </div>
                
                <button type="submit" class="btn">Add Fish Sighting</button>
            </form>
            
            <div class="manage-fish-link">
                <p>Can't find the fish you're looking for? <a href="fish_manager.php" target="_blank">Manage Fish Species</a></p>
            </div>
        </div>
    </div>
    
    <div class="form-buttons">
        <button type="submit" class="btn">Update Dive Log Entry</button>
        <a href="populate_db.php" class="btn" style="background-color: #777;">Cancel</a>
        
        <form method="post" class="delete-form" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this dive log entry? This cannot be undone.');">
            <input type="hidden" name="action" value="delete_entry">
            <input type="hidden" name="id" value="<?php echo $editEntry['id']; ?>">
            <button type="submit" class="danger">Delete Entry</button>
        </form>
    </div>
</form> 