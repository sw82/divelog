<?php
// OCR Import Section
?>
<div class="section" style="margin-bottom: 30px;">
    <h2>Import Dive Logs (OCR)</h2>
    <p>Upload images of your dive log pages to automatically extract dive information using OCR.</p>
    
    <form id="ocrForm" enctype="multipart/form-data" style="margin-bottom: 20px;">
        <div class="form-group">
            <label for="logbook_images">Upload Logbook Images:</label>
            <input type="file" name="logbook_images[]" id="logbook_images" accept="image/*" multiple required>
            <small>You can select multiple images at once for batch processing.</small>
        </div>
        <button type="submit" class="button">Process Images</button>
    </form>

    <div class="progress-bar" style="display: none;">
        <div class="progress"></div>
    </div>

    <div id="batchResult" class="section" style="display: none;">
        <h3>Processed Dives</h3>
        <div id="processedDives"></div>
        <button id="saveAllData" class="button" style="display: none;">Save All to Database</button>
    </div>
</div>

<script>
document.getElementById('ocrForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData();
    const files = document.getElementById('logbook_images').files;
    for (let i = 0; i < files.length; i++) {
        formData.append('logbook_images[]', files[i]);
    }

    const progressBar = document.querySelector('.progress-bar');
    const progress = document.querySelector('.progress');
    progressBar.style.display = 'block';

    fetch('process_ocr.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            alert('Error: ' + data.error);
            return;
        }

        const batchResult = document.getElementById('batchResult');
        const processedDives = document.getElementById('processedDives');
        
        let html = '';
        data.results.forEach((result, index) => {
            html += `
                <div class="dive-entry" style="margin-bottom: 20px; padding: 15px; border: 1px solid #eee; background-color: #fff;">
                    <h4>Dive ${index + 1} - ${result.filename}</h4>
                    <div class="dive-data">
                        ${Object.entries(result.data).map(([key, value]) => `
                            <div class="field" style="margin-bottom: 10px;">
                                <label style="font-weight: normal;">${key}:</label>
                                <input type="text" name="${key}_${index}" value="${value || ''}" style="width: 100%;">
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        });
        
        processedDives.innerHTML = html;
        batchResult.style.display = 'block';
        document.getElementById('saveAllData').style.display = 'block';
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while processing the images');
    });
});

document.getElementById('saveAllData').addEventListener('click', function() {
    const diveEntries = document.querySelectorAll('.dive-entry');
    const data = [];

    diveEntries.forEach((entry, index) => {
        const diveData = {};
        entry.querySelectorAll('input').forEach(input => {
            const [key] = input.name.split('_');
            diveData[key] = input.value;
        });
        data.push(diveData);
    });

    fetch('save_ocr_data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ dives: data })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('All dives saved successfully!');
            window.location.reload();
        } else {
            alert('Error saving data: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving the data');
    });
});
</script> 