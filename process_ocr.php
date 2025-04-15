<?php
require_once 'config.php';

// Check if Tesseract is installed
function isTesseractInstalled() {
    exec('which tesseract', $output, $returnCode);
    return $returnCode === 0;
}

// Process image with OCR
function processImageWithOCR($imagePath) {
    if (!isTesseractInstalled()) {
        return ['error' => 'Tesseract OCR is not installed on the server'];
    }

    // Create a temporary file for the OCR output
    $tempFile = tempnam(sys_get_temp_dir(), 'ocr_');
    $outputFile = $tempFile . '.txt';

    // Run Tesseract OCR
    $command = "tesseract " . escapeshellarg($imagePath) . " " . escapeshellarg($tempFile) . " -l eng";
    exec($command, $output, $returnCode);

    if ($returnCode !== 0) {
        return ['error' => 'OCR processing failed'];
    }

    // Read the OCR output
    $text = file_get_contents($outputFile);
    
    // Clean up temporary files
    unlink($outputFile);
    unlink($tempFile . '.txt');

    return ['text' => $text];
}

// Extract dive data from OCR text
function extractDiveData($text) {
    $data = [
        'date' => null,
        'location' => null,
        'depth' => null,
        'duration' => null,
        'temperature' => null,
        'visibility' => null,
        'comments' => null
    ];

    // Try to extract date (common formats)
    if (preg_match('/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/', $text, $matches)) {
        $data['date'] = $matches[1];
    }

    // Try to extract depth (look for numbers followed by 'm' or 'meters')
    if (preg_match('/(\d+)\s*(?:m|meters?)/i', $text, $matches)) {
        $data['depth'] = $matches[1];
    }

    // Try to extract duration (look for time format or minutes)
    if (preg_match('/(\d+)\s*(?:min|minutes?)/i', $text, $matches)) {
        $data['duration'] = $matches[1];
    }

    // Try to extract temperature (look for numbers followed by '°C' or 'C')
    if (preg_match('/(\d+)\s*(?:°C|C)/i', $text, $matches)) {
        $data['temperature'] = $matches[1];
    }

    // Try to extract visibility (look for numbers followed by 'm' or 'meters')
    if (preg_match('/vis(?:ibility)?\s*(\d+)\s*(?:m|meters?)/i', $text, $matches)) {
        $data['visibility'] = $matches[1];
    }

    // Store the full text as comments for manual review
    $data['comments'] = $text;

    return $data;
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logbook_image'])) {
    $file = $_FILES['logbook_image'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'File upload failed']);
        exit;
    }

    // Process image with OCR
    $ocrResult = processImageWithOCR($file['tmp_name']);
    
    if (isset($ocrResult['error'])) {
        echo json_encode($ocrResult);
        exit;
    }

    // Extract dive data from OCR text
    $diveData = extractDiveData($ocrResult['text']);

    // Return the extracted data
    echo json_encode([
        'success' => true,
        'data' => $diveData,
        'raw_text' => $ocrResult['text']
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Process Dive Log Image</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="file"] {
            margin-bottom: 10px;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }
        .field {
            margin-bottom: 10px;
        }
        .field label {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>Process Dive Log Image</h1>
    <form id="uploadForm" enctype="multipart/form-data">
        <div class="form-group">
            <label for="logbook_image">Upload Logbook Image:</label>
            <input type="file" name="logbook_image" id="logbook_image" accept="image/*" required>
        </div>
        <button type="submit">Process Image</button>
    </form>

    <div id="result" class="result" style="display: none;">
        <h2>Extracted Data</h2>
        <div id="extractedData"></div>
        <button id="saveData" style="display: none;">Save to Database</button>
    </div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('logbook_image', document.getElementById('logbook_image').files[0]);

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

                const resultDiv = document.getElementById('result');
                const extractedDataDiv = document.getElementById('extractedData');
                
                // Display extracted data
                let html = '';
                for (const [key, value] of Object.entries(data.data)) {
                    html += `
                        <div class="field">
                            <label>${key}:</label>
                            <input type="text" name="${key}" value="${value || ''}" style="width: 100%;">
                        </div>
                    `;
                }
                
                extractedDataDiv.innerHTML = html;
                resultDiv.style.display = 'block';
                document.getElementById('saveData').style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing the image');
            });
        });

        document.getElementById('saveData').addEventListener('click', function() {
            // Collect all field values
            const data = {};
            document.querySelectorAll('#extractedData input').forEach(input => {
                data[input.name] = input.value;
            });

            // Send data to server to save in database
            fetch('save_ocr_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Data saved successfully!');
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