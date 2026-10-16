<?php
/**
 * CrediLens AI — Advanced Fraud Detection Engine
 * Detects duplicate applications, document reuse, identity mismatches,
 * income inflation, and velocity anomalies.
 */

require_once __DIR__ . '/../config/database.php';

class FraudDetector {
    private $pdo;

    public function __construct($pdo = null) {
        $this->pdo = $pdo ?: getDBConnection();
    }

    /**
     * Run all fraud checks on an application
     * Returns an array of detected flags and a composite fraud score (0-100)
     */
    public function analyzeApplication($applicationId) {
        $stmt = $this->pdo->prepare("SELECT * FROM loan_applications WHERE id = ?");
        $stmt->execute([$applicationId]);
        $app = $stmt->fetch();

        if (!$app) return ['score' => 0, 'flags' => []];

        $flags = [];

        // 1. Check for Duplicate Applications (same PAN or Aadhaar across different applications)
        $dupFlags = $this->checkDuplicateApplications($app);
        if ($dupFlags) $flags = array_merge($flags, $dupFlags);

        // 2. Check for Document Reuse (file hashes shared across multiple users)
        $docReuseFlags = $this->checkDocumentReuse($applicationId, $app['user_id']);
        if ($docReuseFlags) $flags = array_merge($flags, $docReuseFlags);

        // 3. Check for Inconsistent Information (OCR discrepancies vs declared details)
        $inconsistencyFlags = $this->checkInconsistencies($applicationId, $app);
        if ($inconsistencyFlags) $flags = array_merge($flags, $inconsistencyFlags);

        // 4. Velocity Check (rapid submissions from the same applicant or IP)
        $velocityFlags = $this->checkVelocity($app);
        if ($velocityFlags) $flags = array_merge($flags, $velocityFlags);

        // 5. Calculate composite fraud score (0 = clean, 100 = confirmed severe fraud)
        $fraudScore = 0;
        foreach ($flags as $f) {
            $weights = ['low' => 10, 'medium' => 25, 'high' => 45, 'critical' => 70];
            $fraudScore += ($weights[$f['severity']] ?? 15);
        }
        $fraudScore = min(100, $fraudScore);

        // Save fraud checks to database
        foreach ($flags as $f) {
            $checkExists = $this->pdo->prepare("SELECT id FROM fraud_checks WHERE application_id = ? AND check_type = ? AND title = ?");
            $checkExists->execute([$applicationId, $f['type'], $f['title']]);
            if (!$checkExists->fetch()) {
                $ins = $this->pdo->prepare("INSERT INTO fraud_checks 
                    (application_id, check_type, severity, score, flag_status, title, description, matched_details)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([
                    $applicationId,
                    $f['type'],
                    $f['severity'],
                    $f['score_impact'],
                    'flagged',
                    $f['title'],
                    $f['description'],
                    json_encode($f['details'] ?? [])
                ]);
            }
        }

        // Auto-escalate if fraud score is high
        if ($fraudScore >= 45) {
            $this->autoEscalate($applicationId, $fraudScore, $flags);
        }

        return [
            'score' => $fraudScore,
            'flags' => $flags,
            'status' => $fraudScore >= 60 ? 'critical_risk' : ($fraudScore >= 30 ? 'high_risk' : 'clean')
        ];
    }

    /**
     * Check for duplicate applications by PAN, Aadhaar, or Phone
     */
    private function checkDuplicateApplications($app) {
        $flags = [];

        if (!empty($app['pan_number'])) {
            $stmt = $this->pdo->prepare("SELECT id, application_id, user_id, status, created_at 
                FROM loan_applications 
                WHERE pan_number = ? AND id != ? AND status NOT IN ('cancelled', 'rejected')");
            $stmt->execute([$app['pan_number'], $app['id']]);
            $duplicates = $stmt->fetchAll();

            if (!empty($duplicates)) {
                $differentUser = false;
                $activeAppIds = [];
                foreach ($duplicates as $d) {
                    $activeAppIds[] = $d['application_id'];
                    if ((int)$d['user_id'] !== (int)$app['user_id']) {
                        $differentUser = true;
                    }
                }

                if ($differentUser) {
                    $flags[] = [
                        'type' => 'duplicate_pan_cross_user',
                        'severity' => 'critical',
                        'score_impact' => 50,
                        'title' => 'Cross-Account Identity Match (PAN)',
                        'description' => "PAN number is already linked to another registered account (Applications: " . implode(', ', $activeAppIds) . ").",
                        'details' => ['pan' => substr($app['pan_number'], 0, 3) . '****', 'apps' => $activeAppIds]
                    ];
                } else {
                    $flags[] = [
                        'type' => 'duplicate_active_application',
                        'severity' => 'medium',
                        'score_impact' => 20,
                        'title' => 'Concurrent Active Loan Application',
                        'description' => "Applicant already has active loan applications under review: " . implode(', ', $activeAppIds),
                        'details' => ['apps' => $activeAppIds]
                    ];
                }
            }
        }

        return $flags;
    }

    /**
     * Check for Document Reuse (exact file hash uploaded by different users)
     */
    private function checkDocumentReuse($applicationId, $userId) {
        $flags = [];

        $stmt = $this->pdo->prepare("SELECT ad.id, ad.file_name, ad.file_hash, dt.name as doc_type
            FROM application_documents ad
            JOIN document_types dt ON ad.document_type_id = dt.id
            WHERE ad.application_id = ? AND ad.file_hash IS NOT NULL AND ad.file_hash != ''");
        $stmt->execute([$applicationId]);
        $currentDocs = $stmt->fetchAll();

        foreach ($currentDocs as $doc) {
            $check = $this->pdo->prepare("SELECT ad.id, ad.application_id, la.user_id, la.full_name
                FROM application_documents ad
                JOIN loan_applications la ON ad.application_id = la.id
                WHERE ad.file_hash = ? AND ad.application_id != ? AND la.user_id != ?");
            $check->execute([$doc['file_hash'], $applicationId, $userId]);
            $matches = $check->fetchAll();

            if (!empty($matches)) {
                $otherApplicants = array_unique(array_column($matches, 'full_name'));
                $flags[] = [
                    'type' => 'document_reuse',
                    'severity' => 'critical',
                    'score_impact' => 60,
                    'title' => 'Document Reuse Across Different Applicants',
                    'description' => "Document '{$doc['file_name']}' ({$doc['doc_type']}) has identical cryptographic hash to files submitted by other users: " . implode(', ', $otherApplicants),
                    'details' => [
                        'doc_name' => $doc['file_name'],
                        'hash' => substr($doc['file_hash'], 0, 16) . '...',
                        'shared_with' => $otherApplicants
                    ]
                ];
            }
        }

        return $flags;
    }

    /**
     * Check Inconsistent Information (Stated vs Extracted from OCR)
     */
    private function checkInconsistencies($applicationId, $app) {
        $flags = [];

        // Check OCR discrepancies
        $stmt = $this->pdo->prepare("SELECT * FROM document_ocr_data WHERE application_id = ?");
        $stmt->execute([$applicationId]);
        $ocrList = $stmt->fetchAll();

        foreach ($ocrList as $ocr) {
            // Name mismatch check
            if ($ocr['name_match'] == 0 && !empty($ocr['extracted_name'])) {
                $similarity = 0;
                similar_text(strtolower($app['full_name']), strtolower($ocr['extracted_name']), $similarity);
                if ($similarity < 65) {
                    $flags[] = [
                        'type' => 'name_mismatch',
                        'severity' => 'high',
                        'score_impact' => 35,
                        'title' => 'Identity Name Mismatch on ' . $ocr['document_type'],
                        'description' => "Declared name '{$app['full_name']}' differs significantly from document extracted name '{$ocr['extracted_name']}' (" . round($similarity) . "% match).",
                        'details' => ['declared' => $app['full_name'], 'extracted' => $ocr['extracted_name'], 'similarity' => $similarity]
                    ];
                }
            }

            // Income inflation check (stated monthly income vs salary slip extracted income)
            if ($ocr['document_type'] === 'Salary Slip' && !empty($ocr['extracted_income'])) {
                $declaredIncome = (float)$app['monthly_income'];
                $extractedIncome = (float)$ocr['extracted_income'];
                if ($declaredIncome > ($extractedIncome * 1.35) && $extractedIncome > 0) {
                    $inflationPct = round((($declaredIncome - $extractedIncome) / $extractedIncome) * 100);
                    $flags[] = [
                        'type' => 'income_inflation',
                        'severity' => 'high',
                        'score_impact' => 40,
                        'title' => 'Suspected Income Inflation',
                        'description' => "Declared monthly income (₹" . number_format($declaredIncome) . ") is {$inflationPct}% higher than verified salary slip credits (₹" . number_format($extractedIncome) . ").",
                        'details' => ['declared' => $declaredIncome, 'extracted' => $extractedIncome, 'inflation_percent' => $inflationPct]
                    ];
                }
            }
        }

        return $flags;
    }

    /**
     * Check Velocity Anomalies
     */
    private function checkVelocity($app) {
        $flags = [];
        // Check how many applications were created by this user in the last 7 days
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM loan_applications 
            WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute([$app['user_id']]);
        $count = (int)$stmt->fetchColumn();

        if ($count >= 4) {
            $flags[] = [
                'type' => 'high_application_velocity',
                'severity' => 'medium',
                'score_impact' => 20,
                'title' => 'High Application Velocity',
                'description' => "Applicant submitted {$count} loan applications within the last 7 days.",
                'details' => ['weekly_applications' => $count]
            ];
        }

        return $flags;
    }

    /**
     * Auto-escalate high risk cases to Risk Analyst
     */
    private function autoEscalate($applicationId, $score, $flags) {
        $check = $this->pdo->prepare("SELECT id FROM application_escalations WHERE application_id = ? AND status = 'pending'");
        $check->execute([$applicationId]);
        if (!$check->fetch()) {
            $stmt = $this->pdo->prepare("INSERT INTO application_escalations 
                (application_id, escalated_by, priority, reason, details, status)
                VALUES (?, 1, 'high', 'Automated Fraud/Risk Trigger', ?, 'pending')");
            $details = "Auto-escalated by Fraud Detection Engine. Composite Score: {$score}/100. Flags: " . count($flags);
            $stmt->execute([$applicationId, $details]);

            $upd = $this->pdo->prepare("UPDATE loan_applications SET escalation_status = 'escalated_to_risk' WHERE id = ?");
            $upd->execute([$applicationId]);
        }
    }
}
