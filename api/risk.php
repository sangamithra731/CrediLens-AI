<?php
/**
 * CrediLens AI — Risk Assessment API
 * Handles eligibility checks, risk scoring, and explainable results
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Authentication required'], 401);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'assess':
        performAssessment();
        break;
    case 'get':
        getAssessment();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function performAssessment() {
    if ($_SESSION['user_role'] === 'applicant') {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    $pdo = getDBConnection();
    $applicationId = (int)($_POST['application_id'] ?? 0);
    $userId = $_SESSION['user_id'];
    
    // Get application
    $stmt = $pdo->prepare("SELECT la.*, lt.name as loan_type_name, lt.interest_rate_min, lt.interest_rate_max, lt.min_amount, lt.max_amount FROM loan_applications la JOIN loan_types lt ON la.loan_type_id = lt.id WHERE la.id = ?");
    $stmt->execute([$applicationId]);
    $app = $stmt->fetch();
    
    if (!$app) {
        jsonResponse(['success' => false, 'message' => 'Application not found'], 404);
    }
    
    // Get lending policies
    $stmt = $pdo->query("SELECT policy_key, policy_value FROM lending_policies WHERE is_active = 1");
    $policies = [];
    while ($row = $stmt->fetch()) {
        $policies[$row['policy_key']] = $row['policy_value'];
    }
    
    // === ELIGIBILITY CHECKS ===
    $eligibility = [];
    
    // Age check
    $age = null;
    $ageEligible = null;
    if ($app['date_of_birth']) {
        $dob = new DateTime($app['date_of_birth']);
        $age = $dob->diff(new DateTime())->y;
        $minAge = (int)($policies['min_age'] ?? 21);
        $maxAge = (int)($policies['max_age'] ?? 65);
        $ageEligible = ($age >= $minAge && $age <= $maxAge);
        $eligibility[] = [
            'check' => 'Age Requirement',
            'status' => $ageEligible ? 'passed' : 'failed',
            'detail' => "Applicant age: $age years (Required: $minAge-$maxAge)"
        ];
    }
    
    // Income check
    $minIncome = (float)($policies['min_monthly_income'] ?? 15000);
    $totalIncome = $app['monthly_income'] + $app['additional_income'];
    $incomeEligible = $totalIncome >= $minIncome;
    $eligibility[] = [
        'check' => 'Minimum Income',
        'status' => $incomeEligible ? 'passed' : 'failed',
        'detail' => "Monthly income: ₹" . number_format($totalIncome) . " (Minimum: ₹" . number_format($minIncome) . ")"
    ];
    
    // Employment check
    $minYears = (int)($policies['min_employment_years'] ?? 1);
    $empEligible = $app['years_of_experience'] >= $minYears;
    $eligibility[] = [
        'check' => 'Employment Tenure',
        'status' => $empEligible ? 'passed' : 'failed',
        'detail' => "Work experience: {$app['years_of_experience']} years (Minimum: $minYears)"
    ];
    
    // Loan amount check
    $amountEligible = ($app['requested_amount'] >= $app['min_amount'] && $app['requested_amount'] <= $app['max_amount']);
    $eligibility[] = [
        'check' => 'Loan Amount Range',
        'status' => $amountEligible ? 'passed' : 'failed',
        'detail' => "Requested: ₹" . number_format($app['requested_amount']) . " (Range: ₹" . number_format($app['min_amount']) . " - ₹" . number_format($app['max_amount']) . ")"
    ];
    
    // Document completeness
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM document_types WHERE is_mandatory = 1 AND id NOT IN (SELECT document_type_id FROM application_documents WHERE application_id = ?)");
    $stmt->execute([$applicationId]);
    $missingDocs = $stmt->fetch()['cnt'];
    $docComplete = ($missingDocs == 0);
    $eligibility[] = [
        'check' => 'Document Completeness',
        'status' => $docComplete ? 'passed' : 'failed',
        'detail' => $docComplete ? 'All mandatory documents uploaded' : "$missingDocs mandatory document(s) missing"
    ];
    
    $overallEligible = $incomeEligible && $empEligible && $amountEligible;
    
    // === FINANCIAL INDICATORS ===
    $totalMonthlyObligations = $app['existing_emi'] + $app['monthly_expenses'];
    $dtiRatio = $totalIncome > 0 ? round(($app['existing_emi'] / $totalIncome) * 100, 2) : 0;
    $disposableIncome = $totalIncome - $totalMonthlyObligations;
    $loanToIncome = $totalIncome > 0 ? round(($app['requested_amount'] / ($totalIncome * 12)) * 100, 2) : 0;
    
    // Proposed EMI
    $interestRate = ($app['interest_rate_min'] + $app['interest_rate_max']) / 2;
    $proposedEMI = calculateEMI($app['requested_amount'], $interestRate, $app['requested_tenure']);
    $newDTI = $totalIncome > 0 ? round((($app['existing_emi'] + $proposedEMI) / $totalIncome) * 100, 2) : 0;
    
    // === RISK SCORING ===
    $riskScore = 0;
    $riskFactors = [];
    
    // DTI Risk
    $maxDTI = (float)($policies['max_dti_ratio'] ?? 50);
    $highRiskDTI = (float)($policies['high_risk_dti'] ?? 40);
    
    if ($newDTI > $maxDTI) {
        $riskScore += 35;
        $riskFactors[] = ['name' => 'High Debt-to-Income Ratio', 'value' => $newDTI . '%', 'impact' => 'negative', 'weight' => 35, 'explanation' => "New DTI ratio of {$newDTI}% exceeds maximum allowed ({$maxDTI}%)", 'source' => 'Financial Analysis'];
    } elseif ($newDTI > $highRiskDTI) {
        $riskScore += 20;
        $riskFactors[] = ['name' => 'Elevated Debt-to-Income Ratio', 'value' => $newDTI . '%', 'impact' => 'negative', 'weight' => 20, 'explanation' => "New DTI ratio of {$newDTI}% is above comfort threshold ({$highRiskDTI}%)", 'source' => 'Financial Analysis'];
    } else {
        $riskFactors[] = ['name' => 'Healthy Debt-to-Income Ratio', 'value' => $newDTI . '%', 'impact' => 'positive', 'weight' => -10, 'explanation' => "DTI ratio of {$newDTI}% is within acceptable range", 'source' => 'Financial Analysis'];
        $riskScore -= 10;
    }
    
    // Income adequacy
    if ($disposableIncome < $proposedEMI * 1.5) {
        $riskScore += 25;
        $riskFactors[] = ['name' => 'Low Disposable Income', 'value' => '₹' . number_format($disposableIncome), 'impact' => 'negative', 'weight' => 25, 'explanation' => 'Disposable income may not adequately cover the proposed EMI of ₹' . number_format($proposedEMI), 'source' => 'Income Analysis'];
    } else {
        $riskFactors[] = ['name' => 'Adequate Disposable Income', 'value' => '₹' . number_format($disposableIncome), 'impact' => 'positive', 'weight' => -5, 'explanation' => 'Sufficient income buffer after EMI obligation', 'source' => 'Income Analysis'];
        $riskScore -= 5;
    }
    
    // Employment stability
    if ($app['employment_type'] === 'salaried' && $app['years_of_experience'] >= 3) {
        $riskScore -= 10;
        $riskFactors[] = ['name' => 'Stable Employment', 'value' => $app['years_of_experience'] . ' years, salaried', 'impact' => 'positive', 'weight' => -10, 'explanation' => 'Salaried employment with good tenure indicates stability', 'source' => 'Employment Analysis'];
    } elseif ($app['employment_type'] === 'self_employed') {
        $riskScore += 10;
        $riskFactors[] = ['name' => 'Self-Employed Risk', 'value' => 'Self-employed', 'impact' => 'negative', 'weight' => 10, 'explanation' => 'Self-employment income may be variable', 'source' => 'Employment Analysis'];
    }
    
    // Loan amount relative to income
    if ($loanToIncome > 500) {
        $riskScore += 20;
        $riskFactors[] = ['name' => 'High Loan-to-Income Ratio', 'value' => $loanToIncome . '%', 'impact' => 'negative', 'weight' => 20, 'explanation' => 'Requested amount is significantly high relative to annual income', 'source' => 'Amount Analysis'];
    }
    
    // Existing loans
    if ($app['existing_loans_count'] > 3) {
        $riskScore += 15;
        $riskFactors[] = ['name' => 'Multiple Existing Loans', 'value' => $app['existing_loans_count'] . ' loans', 'impact' => 'negative', 'weight' => 15, 'explanation' => 'High number of existing loan obligations', 'source' => 'Credit History'];
    } elseif ($app['existing_loans_count'] == 0) {
        $riskFactors[] = ['name' => 'No Existing Loans', 'value' => '0 loans', 'impact' => 'positive', 'weight' => -5, 'explanation' => 'No existing loan burden', 'source' => 'Credit History'];
        $riskScore -= 5;
    }
    
    // Age factor
    if ($age !== null) {
        $remainingWorkYears = 60 - $age;
        $loanTenureYears = $app['requested_tenure'] / 12;
        if ($loanTenureYears > $remainingWorkYears) {
            $riskScore += 15;
            $riskFactors[] = ['name' => 'Tenure Exceeds Working Life', 'value' => "$loanTenureYears years vs $remainingWorkYears years remaining", 'impact' => 'negative', 'weight' => 15, 'explanation' => 'Loan tenure extends beyond expected working life', 'source' => 'Age Analysis'];
        }
    }
    
    // Normalize risk score
    $riskScore = max(0, min(100, $riskScore + 30)); // Base 30 + adjustments
    
    // Simulated credit score
    $creditScore = rand(600, 850);
    if ($creditScore < 650) {
        $riskScore += 15;
        $riskFactors[] = ['name' => 'Low Credit Score', 'value' => $creditScore, 'impact' => 'negative', 'weight' => 15, 'explanation' => 'Credit score below recommended threshold of 700', 'source' => 'Credit Bureau (Simulated)'];
    } elseif ($creditScore >= 750) {
        $riskScore -= 10;
        $riskFactors[] = ['name' => 'Excellent Credit Score', 'value' => $creditScore, 'impact' => 'positive', 'weight' => -10, 'explanation' => 'Credit score indicates good repayment history', 'source' => 'Credit Bureau (Simulated)'];
    }
    
    $riskScore = max(0, min(100, $riskScore));
    
    // Risk category
    $lowThreshold = (int)($policies['risk_low_threshold'] ?? 30);
    $highThreshold = (int)($policies['risk_high_threshold'] ?? 70);
    
    if ($riskScore <= $lowThreshold) $riskCategory = 'low';
    elseif ($riskScore <= 50) $riskCategory = 'medium';
    elseif ($riskScore <= $highThreshold) $riskCategory = 'high';
    else $riskCategory = 'very_high';
    
    // AI Recommendation
    if ($riskCategory === 'low' && $overallEligible) $aiRecommendation = 'approve';
    elseif ($riskCategory === 'very_high' || !$overallEligible) $aiRecommendation = 'reject';
    else $aiRecommendation = 'review';
    
    // Policy compliance checks
    $policyChecks = [];
    $policyChecks[] = ['rule' => 'Maximum DTI Ratio', 'status' => $newDTI <= $maxDTI ? 'passed' : 'failed', 'detail' => "DTI: {$newDTI}% (Max: {$maxDTI}%)"];
    $policyChecks[] = ['rule' => 'Minimum Income Requirement', 'status' => $incomeEligible ? 'passed' : 'failed', 'detail' => "Income: ₹" . number_format($totalIncome) . " (Min: ₹" . number_format($minIncome) . ")"];
    $policyChecks[] = ['rule' => 'Loan Amount Within Range', 'status' => $amountEligible ? 'passed' : 'failed', 'detail' => "Amount: ₹" . number_format($app['requested_amount'])];
    $policyChecks[] = ['rule' => 'Age Requirement', 'status' => $ageEligible === null ? 'pending' : ($ageEligible ? 'passed' : 'failed'), 'detail' => $age ? "Age: $age years" : "DOB not provided"];
    
    $complianceStatus = 'compliant';
    foreach ($policyChecks as $pc) {
        if ($pc['status'] === 'failed') {
            $complianceStatus = 'non_compliant';
            break;
        }
        if ($pc['status'] === 'pending') $complianceStatus = 'review_needed';
    }
    
    // AI explanation
    $aiExplanation = [
        'summary' => "Risk assessment completed with an overall score of {$riskScore}/100 ({$riskCategory} risk).",
        'key_findings' => [],
        'recommendation_reason' => ''
    ];
    
    foreach ($riskFactors as $f) {
        if ($f['impact'] === 'negative') {
            $aiExplanation['key_findings'][] = "⚠ {$f['name']}: {$f['explanation']}";
        } else {
            $aiExplanation['key_findings'][] = "✓ {$f['name']}: {$f['explanation']}";
        }
    }
    
    $aiExplanation['recommendation_reason'] = match($aiRecommendation) {
        'approve' => 'Low risk profile with all eligibility criteria met. Application is recommended for approval.',
        'reject' => 'High risk indicators detected or eligibility criteria not met. Manual review strongly recommended before proceeding.',
        'review' => 'Moderate risk indicators detected. Human review recommended to assess borderline factors.'
    };
    
    // Save assessment
    // Delete old assessment if exists
    $stmt = $pdo->prepare("DELETE FROM risk_factors WHERE assessment_id IN (SELECT id FROM risk_assessments WHERE application_id = ?)");
    $stmt->execute([$applicationId]);
    $stmt = $pdo->prepare("DELETE FROM risk_assessments WHERE application_id = ?");
    $stmt->execute([$applicationId]);
    
    $stmt = $pdo->prepare("INSERT INTO risk_assessments (
        application_id, assessed_by, age_eligible, income_eligible, employment_eligible, document_complete, overall_eligible,
        verified_monthly_income, total_monthly_obligations, dti_ratio, disposable_income, loan_to_income_ratio,
        credit_score, credit_score_source, risk_score, risk_category,
        ai_risk_score, ai_recommendation, ai_explanation, ai_flags,
        policy_checks, compliance_status, assessed_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->execute([
        $applicationId, $userId,
        $ageEligible, $incomeEligible, $empEligible, $docComplete, $overallEligible,
        $totalIncome, $totalMonthlyObligations, $dtiRatio, $disposableIncome, $loanToIncome,
        $creditScore, 'Simulated', $riskScore, $riskCategory,
        $riskScore, $aiRecommendation, json_encode($aiExplanation), json_encode([]),
        json_encode($policyChecks), $complianceStatus
    ]);
    
    $assessmentId = $pdo->lastInsertId();
    
    // Save risk factors
    foreach ($riskFactors as $f) {
        $stmt = $pdo->prepare("INSERT INTO risk_factors (assessment_id, factor_name, factor_value, impact, weight, explanation, evidence_source) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$assessmentId, $f['name'], $f['value'], $f['impact'], $f['weight'], $f['explanation'], $f['source']]);
    }
    
    // Save compliance checks
    $stmt = $pdo->prepare("DELETE FROM compliance_checks WHERE application_id = ?");
    $stmt->execute([$applicationId]);
    foreach ($policyChecks as $pc) {
        $stmt = $pdo->prepare("INSERT INTO compliance_checks (application_id, check_type, check_name, status, details, checked_by, checked_at) VALUES (?, 'policy', ?, ?, ?, ?, NOW())");
        $stmt->execute([$applicationId, $pc['rule'], $pc['status'], $pc['detail'], $userId]);
    }
    
    // Update application status
    $stmt = $pdo->prepare("UPDATE loan_applications SET status = 'risk_assessment' WHERE id = ? AND status IN ('submitted','under_review','document_verification')");
    $stmt->execute([$applicationId]);
    
    // Run Explainable AI Credit Risk Engine and Fraud Detector
    $explainableResult = null;
    $fraudResult = null;
    try {
        require_once __DIR__ . '/../includes/credit_risk_engine.php';
        require_once __DIR__ . '/../includes/fraud_detector.php';
        
        $riskEngine = new CreditRiskEngine($pdo);
        $explainableResult = $riskEngine->evaluateApplication($applicationId);
        
        $fraudEngine = new FraudDetector($pdo);
        $fraudResult = $fraudEngine->analyzeApplication($applicationId);
    } catch (\Throwable $e) {
        error_log('Explainable AI/Fraud Engine execution error: ' . $e->getMessage());
    }

    addTimelineEvent($applicationId, 'risk_assessed', 'Risk Assessment Completed', "Risk score: {$riskScore}/100 ({$riskCategory}). Recommendation: {$aiRecommendation}.", $userId);
    logAudit($userId, 'risk_assessment', 'loan_application', $applicationId);
    
    jsonResponse([
        'success' => true,
        'message' => 'Risk assessment completed',
        'assessment' => [
            'id' => $assessmentId,
            'eligibility' => $eligibility,
            'overall_eligible' => $overallEligible,
            'financial_indicators' => [
                'total_income' => $totalIncome,
                'monthly_obligations' => $totalMonthlyObligations,
                'dti_ratio' => $dtiRatio,
                'new_dti_ratio' => $newDTI,
                'disposable_income' => $disposableIncome,
                'loan_to_income' => $loanToIncome,
                'proposed_emi' => $proposedEMI,
                'interest_rate' => $interestRate
            ],
            'credit_score' => $creditScore,
            'risk_score' => $riskScore,
            'risk_category' => $riskCategory,
            'risk_factors' => $riskFactors,
            'explainable_assessment' => $explainableResult,
            'fraud_analysis' => $fraudResult,
            'policy_checks' => $policyChecks,
            'compliance_status' => $complianceStatus,
            'ai_recommendation' => $aiRecommendation,
            'ai_explanation' => $aiExplanation
        ]
    ]);
}

function getAssessment() {
    $pdo = getDBConnection();
    $applicationId = (int)($_GET['application_id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT ra.*, u.full_name as assessor_name FROM risk_assessments ra LEFT JOIN users u ON ra.assessed_by = u.id WHERE ra.application_id = ? ORDER BY ra.created_at DESC LIMIT 1");
    $stmt->execute([$applicationId]);
    $assessment = $stmt->fetch();
    
    if (!$assessment) {
        jsonResponse(['success' => false, 'message' => 'No assessment found'], 404);
    }
    
    $stmt = $pdo->prepare("SELECT * FROM risk_factors WHERE assessment_id = ?");
    $stmt->execute([$assessment['id']]);
    $factors = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT * FROM compliance_checks WHERE application_id = ?");
    $stmt->execute([$applicationId]);
    $compliance = $stmt->fetchAll();

    // Fetch explainable risk factors
    $stmt = $pdo->prepare("SELECT * FROM risk_factor_explanations WHERE application_id = ? ORDER BY category, weight DESC");
    $stmt->execute([$applicationId]);
    $explainableFactors = $stmt->fetchAll();

    // Fetch fraud checks
    $stmt = $pdo->prepare("SELECT * FROM fraud_checks WHERE application_id = ? ORDER BY created_at DESC");
    $stmt->execute([$applicationId]);
    $fraudChecks = $stmt->fetchAll();

    // Fetch OCR data
    $stmt = $pdo->prepare("SELECT * FROM document_ocr_data WHERE application_id = ? ORDER BY created_at DESC");
    $stmt->execute([$applicationId]);
    $ocrData = $stmt->fetchAll();
    
    // Decode JSON fields
    $assessment['ai_explanation'] = json_decode($assessment['ai_explanation'], true);
    $assessment['ai_flags'] = json_decode($assessment['ai_flags'], true);
    $assessment['policy_checks'] = json_decode($assessment['policy_checks'], true);
    
    jsonResponse([
        'success' => true,
        'assessment' => $assessment,
        'risk_factors' => $factors,
        'explainable_factors' => $explainableFactors,
        'fraud_checks' => $fraudChecks,
        'ocr_data' => $ocrData,
        'compliance_checks' => $compliance
    ]);
}
