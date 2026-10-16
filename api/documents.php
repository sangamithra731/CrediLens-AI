<?php
/**
 * CrediLens AI — Documents API
 * Handles document upload, processing, and verification
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Authentication required'], 401);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'upload':
        uploadDocument();
        break;
    case 'list':
        listDocuments();
        break;
    case 'get_types':
        getDocumentTypes();
        break;
    case 'delete':
        deleteDocument();
        break;
    case 'process_ai':
        processWithAI();
        break;
    case 'verify':
        verifyDocuments();
        break;
    case 'download':
        downloadDocument();
        break;
    case 'get_ocr_data':
        getOcrData();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function uploadDocument() {
    $pdo = getDBConnection();
    $userId = $_SESSION['user_id'];
    $applicationId = (int)($_POST['application_id'] ?? 0);
    $documentTypeId = (int)($_POST['document_type_id'] ?? 0);
    
    // Verify application access
    $stmt = $pdo->prepare("SELECT * FROM loan_applications WHERE id = ?");
    $stmt->execute([$applicationId]);
    $app = $stmt->fetch();
    
    if (!$app || ($app['user_id'] != $userId && $_SESSION['user_role'] === 'applicant')) {
        jsonResponse(['success' => false, 'message' => 'Application not found or access denied'], 403);
    }
    
    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['success' => false, 'message' => 'No file uploaded or upload error'], 400);
    }
    
    $file = $_FILES['document'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        jsonResponse(['success' => false, 'message' => 'File type not allowed. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS)], 400);
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        jsonResponse(['success' => false, 'message' => 'File size exceeds 10MB limit'], 400);
    }
    
    // Create upload directory
    $uploadDir = UPLOAD_DIR . $app['application_id'] . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate safe filename
    $fileName = $documentTypeId . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
    $filePath = $uploadDir . $fileName;
    
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        jsonResponse(['success' => false, 'message' => 'Failed to save file'], 500);
    }
    
    $relativePath = 'uploads/' . $app['application_id'] . '/' . $fileName;
    $fileHash = hash_file('sha256', $filePath);
    
    $stmt = $pdo->prepare("INSERT INTO application_documents (application_id, document_type_id, file_name, file_path, file_size, mime_type, file_hash, ocr_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$applicationId, $documentTypeId, $file['name'], $relativePath, $file['size'], $file['type'], $fileHash]);
    
    $docId = $pdo->lastInsertId();
    
    addTimelineEvent($applicationId, 'document_uploaded', 'Document Uploaded', 'Document "' . $file['name'] . '" has been uploaded.', $userId);
    logAudit($userId, 'document_uploaded', 'document', $docId);

    // Run proactive fraud check on document upload (detect document reuse)
    try {
        require_once __DIR__ . '/../includes/fraud_detector.php';
        $fraudEngine = new FraudDetector($pdo);
        $fraudEngine->analyzeApplication($applicationId);
    } catch (\Throwable $e) {
        error_log('Fraud check on upload error: ' . $e->getMessage());
    }
    
    jsonResponse([
        'success' => true, 
        'message' => 'Document uploaded successfully',
        'document_id' => $docId,
        'file_name' => $file['name'],
        'file_hash' => $fileHash
    ]);
}

function listDocuments() {
    $pdo = getDBConnection();
    $applicationId = (int)($_GET['application_id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT ad.*, dt.name as document_type_name, dt.category, dt.is_mandatory
        FROM application_documents ad
        JOIN document_types dt ON ad.document_type_id = dt.id
        WHERE ad.application_id = ?
        ORDER BY ad.uploaded_at DESC");
    $stmt->execute([$applicationId]);
    
    jsonResponse(['success' => true, 'documents' => $stmt->fetchAll()]);
}

function getDocumentTypes() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM document_types ORDER BY is_mandatory DESC, category, name");
    jsonResponse(['success' => true, 'types' => $stmt->fetchAll()]);
}

function deleteDocument() {
    $pdo = getDBConnection();
    $docId = (int)($_POST['document_id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT ad.*, la.user_id, la.application_id as app_str_id FROM application_documents ad JOIN loan_applications la ON ad.application_id = la.id WHERE ad.id = ?");
    $stmt->execute([$docId]);
    $doc = $stmt->fetch();
    
    if (!$doc) {
        jsonResponse(['success' => false, 'message' => 'Document not found'], 404);
    }
    
    if ($doc['user_id'] != $_SESSION['user_id'] && $_SESSION['user_role'] === 'applicant') {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    // Delete physical file
    $fullPath = __DIR__ . '/../' . $doc['file_path'];
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }
    
    $stmt = $pdo->prepare("DELETE FROM application_documents WHERE id = ?");
    $stmt->execute([$docId]);
    
    logAudit($_SESSION['user_id'], 'document_deleted', 'document', $docId);
    
    jsonResponse(['success' => true, 'message' => 'Document deleted']);
}

function processWithAI() {
    $pdo = getDBConnection();
    $docId = (int)($_POST['document_id'] ?? 0);
    $applicationId = (int)($_POST['application_id'] ?? 0);
    
    // Get document info
    $stmt = $pdo->prepare("SELECT ad.*, dt.name as doc_type_name FROM application_documents ad JOIN document_types dt ON ad.document_type_id = dt.id WHERE ad.id = ?");
    $stmt->execute([$docId]);
    $doc = $stmt->fetch();
    
    if (!$doc) {
        jsonResponse(['success' => false, 'message' => 'Document not found'], 404);
    }
    
    // Update OCR status
    $stmt = $pdo->prepare("UPDATE application_documents SET ocr_status = 'processing' WHERE id = ?");
    $stmt->execute([$docId]);
    
    // Get application data for cross-verification
    $stmt = $pdo->prepare("SELECT * FROM loan_applications WHERE id = ?");
    $stmt->execute([$applicationId]);
    $app = $stmt->fetch();
    
    $apiKey = GEMINI_API_KEY;
    
    if (empty($apiKey)) {
        // Simulate AI processing with realistic mock data
        $extractedData = simulateAIExtraction($doc, $app);
        
        $stmt = $pdo->prepare("UPDATE application_documents SET 
            ocr_status = 'completed', 
            extracted_data = ?, 
            ai_classification = ?, 
            ai_confidence = ?,
            validation_status = ?,
            validation_errors = ?,
            processed_at = NOW() 
            WHERE id = ?");
        $stmt->execute([
            json_encode($extractedData['fields']),
            $extractedData['classification'],
            $extractedData['confidence'],
            $extractedData['validation_status'],
            json_encode($extractedData['validation_errors']),
            $docId
        ]);
        
        // Store verification results
        if (!empty($extractedData['verifications'])) {
            foreach ($extractedData['verifications'] as $v) {
                $stmt = $pdo->prepare("INSERT INTO document_verifications (application_id, document_id, field_name, declared_value, extracted_value, match_status, confidence, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$applicationId, $docId, $v['field'], $v['declared'], $v['extracted'], $v['status'], $v['confidence'], $v['notes']]);
            }
        }
        
        // Record in document_ocr_data table and trigger fraud checks
        recordOcrIntelligence($pdo, $docId, $applicationId, $doc['doc_type_name'], $extractedData, $app);

        addTimelineEvent($applicationId, 'ai_processing', 'AI Document Processing Complete', 'Document "' . $doc['file_name'] . '" processed with ' . $extractedData['confidence'] . '% confidence.', null, $extractedData);
        
        jsonResponse([
            'success' => true, 
            'message' => 'Document processed successfully (simulated)',
            'extracted_data' => $extractedData
        ]);
    } else {
        // Real Gemini API processing
        $filePath = __DIR__ . '/../' . $doc['file_path'];
        
        if (!file_exists($filePath)) {
            jsonResponse(['success' => false, 'message' => 'File not found on server'], 404);
        }
        
        $fileContent = base64_encode(file_get_contents($filePath));
        $mimeType = $doc['mime_type'] ?: 'application/pdf';
        
        $prompt = "You are a document analysis AI for a lending platform. Analyze this " . $doc['doc_type_name'] . " document and extract the following information in JSON format:

1. Document type classification (confirm if it matches: " . $doc['doc_type_name'] . ")
2. Key fields extracted (name, dates, amounts, account numbers, addresses, etc.)
3. Document validity indicators (is it legible, complete, potentially altered)
4. Any red flags or inconsistencies

Compare with declared applicant information:
- Name: " . ($app['full_name'] ?? 'N/A') . "
- PAN: " . ($app['pan_number'] ?? 'N/A') . "
- Monthly Income: ₹" . number_format($app['monthly_income'] ?? 0) . "
- Employer: " . ($app['employer_name'] ?? 'N/A') . "

Return a JSON object with these keys:
- classification: string (document type)
- confidence: number (0-100)
- fields: object (extracted key-value pairs)
- validation_status: string (valid/invalid/mismatch/incomplete)
- validation_errors: array of strings (issues found)
- verifications: array of objects with {field, declared, extracted, status (match/mismatch/not_found), confidence, notes}";
        
        $requestBody = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        ['inline_data' => ['mime_type' => $mimeType, 'data' => $fileContent]]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json'
            ]
        ];
        
        $ch = curl_init(GEMINI_API_URL . '?key=' . $apiKey);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestBody),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $stmt = $pdo->prepare("UPDATE application_documents SET ocr_status = 'failed' WHERE id = ?");
            $stmt->execute([$docId]);
            jsonResponse(['success' => false, 'message' => 'AI processing failed. HTTP: ' . $httpCode], 500);
        }
        
        $responseData = json_decode($response, true);
        $aiText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $extractedData = json_decode($aiText, true) ?: [];
        
        $stmt = $pdo->prepare("UPDATE application_documents SET 
            ocr_status = 'completed', 
            ocr_text = ?,
            extracted_data = ?, 
            ai_classification = ?, 
            ai_confidence = ?,
            validation_status = ?,
            validation_errors = ?,
            processed_at = NOW() 
            WHERE id = ?");
        $stmt->execute([
            $aiText,
            json_encode($extractedData['fields'] ?? []),
            $extractedData['classification'] ?? $doc['doc_type_name'],
            $extractedData['confidence'] ?? 0,
            $extractedData['validation_status'] ?? 'pending',
            json_encode($extractedData['validation_errors'] ?? []),
            $docId
        ]);
        
        // Store verifications
        if (!empty($extractedData['verifications'])) {
            foreach ($extractedData['verifications'] as $v) {
                $stmt = $pdo->prepare("INSERT INTO document_verifications (application_id, document_id, field_name, declared_value, extracted_value, match_status, confidence, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $applicationId, $docId,
                    $v['field'] ?? '', $v['declared'] ?? '', $v['extracted'] ?? '',
                    $v['status'] ?? 'not_found', $v['confidence'] ?? 0, $v['notes'] ?? ''
                ]);
            }
        }
        
        // Record in document_ocr_data table and trigger fraud checks
        recordOcrIntelligence($pdo, $docId, $applicationId, $doc['doc_type_name'], $extractedData, $app, $aiText);

        addTimelineEvent($applicationId, 'ai_processing', 'AI Document Processing Complete', 'Document analyzed by Gemini AI with ' . ($extractedData['confidence'] ?? 0) . '% confidence.', null);
        
        // Log the external API call
        $stmt = $pdo->prepare("INSERT INTO external_api_logs (application_id, service_name, endpoint, request_data, response_data, status_code, is_mock) VALUES (?, 'gemini_ai', ?, ?, ?, ?, 0)");
        $stmt->execute([$applicationId, GEMINI_API_URL, json_encode(['prompt_length' => strlen($prompt)]), json_encode(['response_length' => strlen($response)]), $httpCode]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Document processed with Gemini AI',
            'extracted_data' => $extractedData
        ]);
    }
}

function recordOcrIntelligence($pdo, $docId, $applicationId, $docType, $extractedData, $app, $rawText = '') {
    $fields = $extractedData['fields'] ?? [];
    $extractedName = $fields['name'] ?? $fields['employee_name'] ?? $fields['account_holder'] ?? $fields['name_found'] ?? null;
    $extractedId = $fields['pan_number'] ?? $fields['aadhaar_number'] ?? $fields['account_number'] ?? null;
    $extractedDob = $fields['dob'] ?? null;
    $extractedIncome = $fields['gross_salary'] ?? $fields['net_salary'] ?? null;
    if ($extractedIncome !== null) {
        $extractedIncome = (float)str_replace([',', ' '], '', $extractedIncome);
    }
    
    // Similarity and matching
    $nameMatch = 0;
    if ($extractedName && !empty($app['full_name'])) {
        $sim = 0;
        similar_text(strtolower($app['full_name']), strtolower($extractedName), $sim);
        $nameMatch = $sim >= 70 ? 1 : 0;
    }
    
    $idMatch = 0;
    if ($extractedId) {
        if (!empty($app['pan_number']) && stripos($extractedId, $app['pan_number']) !== false) $idMatch = 1;
        if (!empty($app['aadhaar_number']) && stripos($extractedId, substr($app['aadhaar_number'], -4)) !== false) $idMatch = 1;
    }
    
    $incomeMatch = 0;
    if ($extractedIncome !== null && (float)$app['monthly_income'] > 0) {
        $diff = abs($extractedIncome - (float)$app['monthly_income']) / (float)$app['monthly_income'];
        $incomeMatch = $diff <= 0.15 ? 1 : 0;
    }
    
    // Insert into document_ocr_data
    $stmt = $pdo->prepare("INSERT INTO document_ocr_data 
        (document_id, application_id, document_type, extracted_name, extracted_id_number, extracted_dob, extracted_income, match_confidence, name_match, id_match, income_match, discrepancies, raw_text)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $docId,
        $applicationId,
        $docType,
        $extractedName,
        $extractedId,
        $extractedDob,
        $extractedIncome,
        $extractedData['confidence'] ?? 90,
        $nameMatch,
        $idMatch,
        $incomeMatch,
        json_encode($extractedData['validation_errors'] ?? []),
        $rawText ?: json_encode($fields)
    ]);
    
    // Update application_documents columns
    $pdo->prepare("UPDATE application_documents SET ocr_status = 'completed', ocr_data_json = ?, ocr_match_score = ? WHERE id = ?")
        ->execute([json_encode($extractedData), $extractedData['confidence'] ?? 90, $docId]);

    // Trigger fraud engine to recalculate inconsistencies
    try {
        require_once __DIR__ . '/../includes/fraud_detector.php';
        $fd = new FraudDetector($pdo);
        $fd->analyzeApplication($applicationId);
    } catch (\Throwable $e) {
        error_log('Fraud check on OCR error: ' . $e->getMessage());
    }
}

function getOcrData() {
    $pdo = getDBConnection();
    $applicationId = (int)($_GET['application_id'] ?? 0);
    $docId = (int)($_GET['document_id'] ?? 0);

    if ($docId > 0) {
        $stmt = $pdo->prepare("SELECT dod.*, ad.file_name, dt.name as document_type_name
            FROM document_ocr_data dod
            JOIN application_documents ad ON dod.document_id = ad.id
            JOIN document_types dt ON ad.document_type_id = dt.id
            WHERE dod.document_id = ?");
        $stmt->execute([$docId]);
        $data = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare("SELECT dod.*, ad.file_name, dt.name as document_type_name
            FROM document_ocr_data dod
            JOIN application_documents ad ON dod.document_id = ad.id
            JOIN document_types dt ON ad.document_type_id = dt.id
            WHERE dod.application_id = ?
            ORDER BY dod.created_at DESC");
        $stmt->execute([$applicationId]);
        $data = $stmt->fetchAll();
    }

    jsonResponse(['success' => true, 'ocr_data' => $data]);
}

function simulateAIExtraction($doc, $app) {
    $docType = strtolower($doc['doc_type_name'] ?? '');
    $name = $app['full_name'] ?? 'Unknown';
    $income = $app['monthly_income'] ?? 0;
    
    $result = [
        'classification' => $doc['doc_type_name'],
        'confidence' => rand(85, 98),
        'fields' => [],
        'validation_status' => 'valid',
        'validation_errors' => [],
        'verifications' => []
    ];
    
    if (strpos($docType, 'pan') !== false) {
        $result['fields'] = [
            'name' => $name,
            'pan_number' => $app['pan_number'] ?? 'ABCDE1234F',
            'father_name' => 'N/A',
            'dob' => $app['date_of_birth'] ?? '1990-01-01',
            'document_type' => 'PAN Card'
        ];
        $result['verifications'][] = ['field' => 'Name', 'declared' => $name, 'extracted' => $name, 'status' => 'match', 'confidence' => 96, 'notes' => 'Name matches declaration'];
        $result['verifications'][] = ['field' => 'PAN Number', 'declared' => $app['pan_number'] ?? '', 'extracted' => $app['pan_number'] ?? 'ABCDE1234F', 'status' => 'match', 'confidence' => 99, 'notes' => 'PAN number verified'];
    } elseif (strpos($docType, 'salary') !== false) {
        $extractedIncome = $income * (rand(95, 105) / 100);
        $matchStatus = abs($extractedIncome - $income) / max($income, 1) < 0.05 ? 'match' : 'mismatch';
        
        $result['fields'] = [
            'employee_name' => $name,
            'employer' => $app['employer_name'] ?? 'Unknown Corp',
            'gross_salary' => round($extractedIncome),
            'net_salary' => round($extractedIncome * 0.82),
            'month' => date('F Y', strtotime('-1 month')),
            'deductions' => round($extractedIncome * 0.18)
        ];
        $result['verifications'][] = ['field' => 'Monthly Income', 'declared' => '₹' . number_format($income), 'extracted' => '₹' . number_format(round($extractedIncome)), 'status' => $matchStatus, 'confidence' => 92, 'notes' => $matchStatus === 'match' ? 'Income matches within 5%' : 'Income mismatch detected — declared vs salary slip differs'];
        $result['verifications'][] = ['field' => 'Employer', 'declared' => $app['employer_name'] ?? '', 'extracted' => $app['employer_name'] ?? 'Unknown Corp', 'status' => 'match', 'confidence' => 94, 'notes' => 'Employer name matches'];
        
        if ($matchStatus === 'mismatch') {
            $result['validation_status'] = 'mismatch';
            $result['validation_errors'][] = 'Declared income (₹' . number_format($income) . ') does not match salary slip (₹' . number_format(round($extractedIncome)) . ')';
        }
    } elseif (strpos($docType, 'bank') !== false) {
        $avgBalance = $income * rand(2, 5);
        $result['fields'] = [
            'account_holder' => $name,
            'bank_name' => 'State Bank of India',
            'account_number' => 'XXXX' . rand(1000, 9999),
            'period' => date('M Y', strtotime('-6 months')) . ' - ' . date('M Y'),
            'average_balance' => round($avgBalance),
            'total_credits' => round($income * 6.2),
            'total_debits' => round($income * 5.8),
            'salary_credits_found' => rand(5, 6)
        ];
        $result['verifications'][] = ['field' => 'Account Holder', 'declared' => $name, 'extracted' => $name, 'status' => 'match', 'confidence' => 97, 'notes' => 'Account holder name matches'];
        $result['verifications'][] = ['field' => 'Salary Credits', 'declared' => '₹' . number_format($income) . '/month', 'extracted' => 'Avg ₹' . number_format(round($income * 1.02)) . '/month', 'status' => 'match', 'confidence' => 89, 'notes' => 'Regular salary credits detected'];
    } elseif (strpos($docType, 'aadhaar') !== false) {
        $result['fields'] = [
            'name' => $name,
            'aadhaar_number' => $app['aadhaar_number'] ?? 'XXXX-XXXX-' . rand(1000, 9999),
            'address' => ($app['address_line1'] ?? '') . ', ' . ($app['city'] ?? ''),
            'dob' => $app['date_of_birth'] ?? '1990-01-01',
            'gender' => $app['gender'] ?? 'Not specified'
        ];
        $result['verifications'][] = ['field' => 'Name', 'declared' => $name, 'extracted' => $name, 'status' => 'match', 'confidence' => 95, 'notes' => 'Name matches Aadhaar'];
        $result['verifications'][] = ['field' => 'Address', 'declared' => ($app['address_line1'] ?? '') . ', ' . ($app['city'] ?? ''), 'extracted' => ($app['address_line1'] ?? '') . ', ' . ($app['city'] ?? ''), 'status' => 'match', 'confidence' => 88, 'notes' => 'Address verified'];
    } else {
        $result['fields'] = [
            'document_type' => $doc['doc_type_name'],
            'name_found' => $name,
            'date_detected' => date('Y-m-d'),
            'status' => 'readable'
        ];
        $result['verifications'][] = ['field' => 'Name', 'declared' => $name, 'extracted' => $name, 'status' => 'match', 'confidence' => 90, 'notes' => 'Name found in document'];
    }
    
    return $result;
}

function verifyDocuments() {
    if ($_SESSION['user_role'] === 'applicant') {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    $pdo = getDBConnection();
    $applicationId = (int)($_POST['application_id'] ?? 0);
    
    // Get all documents for application
    $stmt = $pdo->prepare("SELECT * FROM application_documents WHERE application_id = ?");
    $stmt->execute([$applicationId]);
    $docs = $stmt->fetchAll();
    
    $allValid = true;
    $issues = [];
    
    foreach ($docs as $doc) {
        if ($doc['validation_status'] !== 'valid') {
            $allValid = false;
            $errors = json_decode($doc['validation_errors'], true) ?: [];
            $issues = array_merge($issues, $errors);
        }
    }
    
    // Check mandatory documents
    $stmt = $pdo->prepare("SELECT dt.name FROM document_types dt WHERE dt.is_mandatory = 1 AND dt.id NOT IN (SELECT document_type_id FROM application_documents WHERE application_id = ?)");
    $stmt->execute([$applicationId]);
    $missing = $stmt->fetchAll();
    
    if (!empty($missing)) {
        $allValid = false;
        foreach ($missing as $m) {
            $issues[] = 'Missing mandatory document: ' . $m['name'];
        }
    }
    
    $verificationResult = [
        'complete' => $allValid && empty($missing),
        'all_valid' => $allValid,
        'missing_documents' => array_column($missing, 'name'),
        'issues' => $issues,
        'total_documents' => count($docs),
        'verified_count' => count(array_filter($docs, fn($d) => $d['validation_status'] === 'valid')),
        'flagged_count' => count(array_filter($docs, fn($d) => in_array($d['validation_status'], ['mismatch', 'invalid'])))
    ];
    
    jsonResponse(['success' => true, 'verification' => $verificationResult]);
}

function downloadDocument() {
    $pdo = getDBConnection();
    $docId = (int)($_GET['id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT ad.*, la.user_id FROM application_documents ad JOIN loan_applications la ON ad.application_id = la.id WHERE ad.id = ?");
    $stmt->execute([$docId]);
    $doc = $stmt->fetch();
    
    if (!$doc) {
        die('Document not found');
    }
    
    if ($_SESSION['user_role'] === 'applicant' && $doc['user_id'] != $_SESSION['user_id']) {
        die('Access denied');
    }
    
    $filePath = __DIR__ . '/../' . $doc['file_path'];
    
    if (!file_exists($filePath)) {
        die('File not found on server');
    }
    
    header('Content-Type: ' . ($doc['mime_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: inline; filename="' . $doc['file_name'] . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}
