<?php
require_once 'config.php';

// Function to process multiple images
function processBatchImages($files) {
    $results = [];
    foreach ($files['tmp_name'] as $key => $tmp_name) {
        if ($files['error'][$key] === UPLOAD_ERR_OK) {
            $ocrResult = processImageWithOCR($tmp_name);
            if (!isset($ocrResult['error'])) {
                $diveData = extractDiveData($ocrResult['text']);
                $results[] = [
                    'filename' => $files['name'][$key],
                    'data' => $diveData,
                    'raw_text' => $ocrResult['text']
                ];
            }
        }
    }
    return $results;
}

// Handle file uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['logbook_images'])) {
        $results = processBatchImages($_FILES['logbook_images']);
        echo json_encode(['success' => true, 'results' => $results]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Import Dive Logs</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .import-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }
        .batch-result {
            margin-top: 20px;
        }
        .dive-entry {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #eee;
            background-color: #fff;
        }
        .field {
            margin-bottom: 10px;
        }
        .field label {
            font-weight: normal;
        }
        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #f0f0f0;
            border-radius: 10px;
            margin-top: 10px;
            display: none;
        }
        .progress {
            width: 0%;
            height: 100%;
            background-color: #4CAF50;
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        .navigation {
            margin-bottom: 20px;
        }
        .navigation a {
            margin-right: 15px;
            text-decoration: none;
            color: #333;
        }
        .navigation a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="navigation">
        <a href="index.php">Home</a>
        <a href="populate_db.php">Manage Dives</a>
        <a href="fish_manager.php">Fish Manager</a>
        <a href="manage_db.php">Manage Database</a>
        <a href="import.php">Import</a>
    </div>

    <h1>Import Dive Logs</h1>
    
    <div class="import-section">
        <h2>OCR Import</h2>
        <p>Upload images of your dive log pages to automatically extract dive information using OCR.</p>
        
        <form id="ocrForm" enctype="multipart/form-data">
            <div class="form-group">
                <label for="logbook_images">Upload Logbook Images:</label>
                <input type="file" name="logbook_images[]" id="logbook_images" accept="image/*" multiple required>
                <small>You can select multiple images at once for batch processing.</small>
            </div>
            <button type="submit">Process Images</button>
        </form>

        <div class="progress-bar">
            <div class="progress"></div>
        </div>

        <div id="batchResult" class="batch-result" style="display: none;">
            <h3>Processed Dives</h3>
            <div id="processedDives"></div>
            <button id="saveAllData" style="display: none;">Save All to Database</button>
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
                        <div class="dive-entry">
                            <h4>Dive ${index + 1} - ${result.filename}</h4>
                            <div class="dive-data">
                                ${Object.entries(result.data).map(([key, value]) => `
                                    <div class="field">
                                        <label>${key}:</label>
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
</body>
</html> 