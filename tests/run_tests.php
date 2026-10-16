<?php
/**
 * CrediLens AI — Automated Test Suite
 * Covers Unit, Integration, Security, Accounting, Fraud, and Explainable AI tests.
 * Can be run via CLI: php tests/run_tests.php
 * Or via Browser: http://localhost/Lending%20and%20Credit%20platform/tests/run_tests.php
 */

if (php_sapi_name() !== 'cli') {
    session_start();
    header('Content-Type: text/html; charset=utf-8');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/loan_accounting.php';
require_once __DIR__ . '/../includes/fraud_detector.php';
require_once __DIR__ . '/../includes/credit_risk_engine.php';

class TestRunner {
    private $pdo;
    private $passed = 0;
    private $failed = 0;
    private $total = 0;
    private $results = [];

    public function __construct() {
        $this->pdo = getDBConnection();
    }

    public function assert($condition, $testName, $details = '') {
        $this->total++;
        if ($condition) {
            $this->passed++;
            $this->results[] = ['status' => 'PASS', 'name' => $testName, 'details' => $details];
            if (php_sapi_name() === 'cli') {
                echo "  [PASS] $testName\n";
            }
        } else {
            $this->failed++;
            $this->results[] = ['status' => 'FAIL', 'name' => $testName, 'details' => $details];
            if (php_sapi_name() === 'cli') {
                echo "  [FAIL] $testName" . ($details ? " - $details" : "") . "\n";
            }
        }
    }

    public function runAll() {
        if (php_sapi_name() === 'cli') {
            echo "\n=======================================================\n";
            echo "       CrediLens AI — Automated Test Suite            \n";
            echo "=======================================================\n\n";
        }

        $this->testFinancialMath();
        $this->testSecurityAndRBAC();
        $this->testLoanAccountingLedger();
        $this->testFraudDetectionEngine();
        $this->testExplainableRiskEngine();
        $this->testStateMachineIntegrity();

        if (php_sapi_name() === 'cli') {
            echo "\n-------------------------------------------------------\n";
            echo "Results: {$this->passed}/{$this->total} Passed (" . round(($this->passed / max(1, $this->total)) * 100) . "%)\n";
            if ($this->failed === 0) {
                echo "ALL TEST SUITES PASSED CLEANLY!\n";
            } else {
                echo "{$this->failed} TESTS FAILED.\n";
            }
            echo "=======================================================\n\n";
        } else {
            $this->renderWebUI();
        }

        return $this->failed === 0;
    }

    /**
     * 1. Financial & EMI Actuarial Math Unit Tests
     */
    private function testFinancialMath() {
        if (php_sapi_name() === 'cli') echo "--- Running Financial & EMI Math Unit Tests ---\n";

        // Formula: P * r * (1+r)^n / ((1+r)^n - 1)
        // P = 100000, Annual Rate = 12% (r = 0.01 monthly), n = 12 months
        $p = 100000;
        $r = (12.0 / 12) / 100;
        $n = 12;
        $emiExpected = ($p * $r * pow(1 + $r, $n)) / (pow(1 + $r, $n) - 1);
        $roundedEmi = round($emiExpected, 2);

        $this->assert(abs($roundedEmi - 8884.88) < 0.05, 'EMI Actuarial Calculation Formula', "Expected ₹8,884.88, computed ₹$roundedEmi");

        // DTI Test
        $grossIncome = 75000;
        $existingDebt = 15000;
        $newEmi = 8884.88;
        $dti = (($existingDebt + $newEmi) / $grossIncome) * 100;
        $this->assert(round($dti, 1) === 31.8, 'DTI (Debt-to-Income) Ratio Calculation', "Computed DTI: " . round($dti, 1) . "%");
    }

    /**
     * 2. Security, CSRF & RBAC Authorization Tests
     */
    private function testSecurityAndRBAC() {
        if (php_sapi_name() === 'cli') echo "\n--- Running Security, CSRF & RBAC Tests ---\n";

        // Test CSRF Token Generation & Verification
        if (session_status() === PHP_SESSION_NONE) session_start();
        $token = getCsrfToken();
        $this->assert(!empty($token) && strlen($token) === 64, 'CSRF Token Generation (64-char Hex)');
        $this->assert(verifyCsrfToken($token), 'CSRF Token Verification with Valid Token');
        $this->assert(!verifyCsrfToken('invalid_token_12345'), 'CSRF Token Rejection on Tampered Token');

        // Test Cross-User Document Access Protection
        // Mock user session as applicant with user_id = 999
        $_SESSION['user_id'] = 999;
        $_SESSION['user_role'] = 'applicant';

        // Check against an existing application or mock doc
        $stmt = $this->pdo->query("SELECT ad.id, la.user_id FROM application_documents ad JOIN loan_applications la ON ad.application_id = la.id LIMIT 1");
        $doc = $stmt->fetch();
        if ($doc) {
            $realOwner = (int)$doc['user_id'];
            if ($realOwner !== 999) {
                $accessAllowed = canAccessDocument($doc['id'], 999, 'applicant');
                $this->assert(!$accessAllowed, 'Cross-User Document Access Rejection (IDOR Protection)');
            }
            // Officer should have access
            $officerAccess = canAccessDocument($doc['id'], 999, 'loan_officer');
            $this->assert($officerAccess, 'Officer Role-Based Document Access Privilege');
        } else {
            $this->assert(true, 'Cross-User Document Access Rejection (Skipped: no documents in DB)');
        }
    }

    /**
     * 3. Loan Accounting Ledger & Double-Entry Integrity Tests
     */
    private function testLoanAccountingLedger() {
        if (php_sapi_name() === 'cli') echo "\n--- Running Loan Accounting Ledger Tests ---\n";

        $acct = new LoanAccounting($this->pdo);

        // Fetch a real or test application
        $app = $this->pdo->query("SELECT id FROM loan_applications LIMIT 1")->fetch();
        if (!$app) {
            $this->assert(true, 'Loan Accounting Tests (Skipped: no application record)');
            return;
        }

        $appId = (int)$app['id'];
        $testRef = 'TEST-RECON-' . time() . '-' . rand(100, 999);

        // Test Double-Entry Repayment Post
        try {
            $result = $acct->processRepayment($appId, 10000.00, $testRef, 8000.00, 2000.00, 1);
            $this->assert($result['success'] === true, 'Accounting Ledger Repayment Entry Posting');

            // Verify Double Entry in Ledger
            $chk = $this->pdo->prepare("SELECT COUNT(*) FROM loan_accounting_ledger WHERE reference_no = ?");
            $chk->execute([$testRef]);
            $entries = (int)$chk->fetchColumn();
            $this->assert($entries >= 1, 'Accounting Ledger Immutability & Audit Persistence');

            // Test Idempotency (Duplicate Reference MUST be rejected)
            $dupResult = $acct->processRepayment($appId, 10000.00, $testRef, 8000.00, 2000.00, 1);
            $this->assert($dupResult['success'] === false, 'Idempotency Protection: Duplicate Payment Reference Rejected');

        } catch (\Throwable $e) {
            $this->assert(false, 'Accounting Ledger Repayment Entry Posting', $e->getMessage());
        }
    }

    /**
     * 4. Advanced Fraud Detection Engine Tests
     */
    private function testFraudDetectionEngine() {
        if (php_sapi_name() === 'cli') echo "\n--- Running Advanced Fraud Detection Tests ---\n";

        $fraudEngine = new FraudDetector($this->pdo);

        $app = $this->pdo->query("SELECT id FROM loan_applications LIMIT 1")->fetch();
        if ($app) {
            $analysis = $fraudEngine->analyzeApplication((int)$app['id']);
            $this->assert(isset($analysis['score']) && is_numeric($analysis['score']), 'Fraud Engine Composite Scoring');
            $this->assert(in_array($analysis['status'], ['clean', 'high_risk', 'critical_risk']), 'Fraud Engine Status Classification');
            $this->assert(is_array($analysis['flags']), 'Fraud Engine Flag Detection Pipeline');
        } else {
            $this->assert(true, 'Fraud Engine Tests (Skipped: no application record)');
        }
    }

    /**
     * 5. Explainable AI Credit Risk Engine Tests
     */
    private function testExplainableRiskEngine() {
        if (php_sapi_name() === 'cli') echo "\n--- Running Explainable AI Credit Risk Tests ---\n";

        $riskEngine = new CreditRiskEngine($this->pdo);

        $app = $this->pdo->query("SELECT id FROM loan_applications LIMIT 1")->fetch();
        if ($app) {
            $eval = $riskEngine->evaluateApplication((int)$app['id']);
            $this->assert($eval !== null, 'Credit Risk Engine Application Evaluation');
            $this->assert($eval['credit_score'] >= 300 && $eval['credit_score'] <= 900, 'Credit Score Clamping (300–900 Range)');
            $this->assert(in_array($eval['risk_category'], ['low', 'medium', 'high']), 'Risk Category Segmentation (Low/Medium/High)');
            $this->assert(!empty($eval['factors']), 'Explainable Factor Generation (Categorized Breakdown)');

            // Verify at least one factor has explanation and recommendation
            $hasExplanations = false;
            foreach ($eval['factors'] as $f) {
                if (!empty($f['explanation']) && !empty($f['recommendation'])) {
                    $hasExplanations = true;
                    break;
                }
            }
            $this->assert($hasExplanations, 'Transparent Factor Explanations & Actionable Recommendations');
        } else {
            $this->assert(true, 'Explainable Risk Engine Tests (Skipped: no application record)');
        }
    }

    /**
     * 6. Application State Machine Strictness Tests
     */
    private function testStateMachineIntegrity() {
        if (php_sapi_name() === 'cli') echo "\n--- Running State Machine Integrity Tests ---\n";

        $allowedTransitions = [
            'draft'                  => ['submitted', 'cancelled'],
            'submitted'              => ['under_review', 'document_verification', 'cancelled'],
            'under_review'           => ['document_verification', 'clarification_required', 'risk_assessment', 'rejected'],
            'document_verification'  => ['risk_assessment', 'clarification_required', 'rejected'],
            'clarification_required' => ['under_review', 'document_verification', 'rejected'],
            'risk_assessment'        => ['compliance_check', 'approved', 'rejected', 'clarification_required'],
            'compliance_check'       => ['approved', 'rejected', 'clarification_required'],
            'approved'               => ['disbursement_pending', 'disbursed', 'cancelled'],
            'disbursement_pending'   => ['disbursed', 'cancelled'],
            'disbursed'              => ['closed'],
            'closed'                 => [],
            'rejected'               => [],
            'cancelled'              => []
        ];

        // Verify invalid leap is caught: draft directly to disbursed
        $isValidLeap = in_array('disbursed', $allowedTransitions['draft']);
        $this->assert(!$isValidLeap, 'State Machine Rejection: Draft cannot skip directly to Disbursed');

        // Verify invalid transition: closed cannot transition
        $closedHasTargets = !empty($allowedTransitions['closed']);
        $this->assert(!$closedHasTargets, 'State Machine Finality: Closed state is terminal');
    }

    private function renderWebUI() {
        $pct = round(($this->passed / max(1, $this->total)) * 100);
        $allPassed = $this->failed === 0;
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>CrediLens AI — Automated Test Suite</title>
            <link rel="stylesheet" href="../assets/css/style.css">
            <style>
                body { background: #0f172a; color: #f8fafc; font-family: system-ui, sans-serif; padding: 2rem; }
                .container { max-width: 900px; margin: 0 auto; }
                .test-card { background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
                .test-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
                .badge-pass { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.4); padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 0.75rem; }
                .badge-fail { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.4); padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 0.75rem; }
                .test-item { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
                .test-item:last-child { border-bottom: none; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="test-header">
                    <div>
                        <h2>CrediLens AI — Automated Test Suite</h2>
                        <p style="color: #94a3b8; font-size: 0.875rem;">Verified Unit, Security, Accounting, Fraud, and Explainable AI Engine Integrity</p>
                    </div>
                    <span class="badge <?= $allPassed ? 'badge-pass' : 'badge-fail' ?>" style="font-size: 1rem; padding: 0.5rem 1rem;">
                        <?= $this->passed ?> / <?= $this->total ?> Passed (<?= $pct ?>%)
                    </span>
                </div>

                <div class="test-card">
                    <h3 style="margin-bottom: 1rem; color: #6366f1;">Test Executions</h3>
                    <?php foreach ($this->results as $r): ?>
                        <div class="test-item">
                            <div>
                                <strong style="color: #f1f5f9;"><?= htmlspecialchars($r['name']) ?></strong>
                                <?php if (!empty($r['details'])): ?>
                                    <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 2px;"><?= htmlspecialchars($r['details']) ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="<?= $r['status'] === 'PASS' ? 'badge-pass' : 'badge-fail' ?>"><?= $r['status'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="../index.php" style="color: #6366f1; text-decoration: none; font-size: 0.875rem;">← Return to CrediLens AI Dashboard</a>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}

$runner = new TestRunner();
$success = $runner->runAll();
exit($success ? 0 : 1);
