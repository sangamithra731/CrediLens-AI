<?php
/**
 * CrediLens AI — Explainable AI Credit Risk Assessment Engine
 * Transparent, policy-governed scoring model (300–900 scale)
 * Breaks down decisions into categorized factors with mathematical validation.
 */

require_once __DIR__ . '/../config/database.php';

class CreditRiskEngine {
    private $pdo;

    public function __construct($pdo = null) {
        $this->pdo = $pdo ?: getDBConnection();
    }

    /**
     * Compute comprehensive risk assessment with explainable factor breakdown
     */
    public function evaluateApplication($applicationId) {
        $stmt = $this->pdo->prepare("SELECT la.*, lt.name as loan_type_name, lt.interest_rate_min, lt.interest_rate_max
            FROM loan_applications la
            JOIN loan_types lt ON la.loan_type_id = lt.id
            WHERE la.id = ?");
        $stmt->execute([$applicationId]);
        $app = $stmt->fetch();

        if (!$app) return null;

        $income = (float)$app['monthly_income'] + (float)$app['additional_income'];
        $expenses = (float)$app['monthly_expenses'];
        $existingEmi = (float)$app['existing_emi'];
        $requestedAmount = (float)$app['requested_amount'];
        $tenure = (int)$app['requested_tenure'] ?: 12;
        $expYears = (int)$app['years_of_experience'];

        // 1. Calculate Estimated New EMI (annual rate assumed at 12%)
        $rateMonthly = (12.0 / 12) / 100;
        $estNewEmi = ($rateMonthly > 0 && $tenure > 0)
            ? ($requestedAmount * $rateMonthly * pow(1 + $rateMonthly, $tenure)) / (pow(1 + $rateMonthly, $tenure) - 1)
            : ($requestedAmount / $tenure);

        // 2. Financial Metrics
        $totalObligations = $existingEmi + $estNewEmi;
        $dti = $income > 0 ? ($totalObligations / $income) * 100 : 100;
        $disposableIncome = $income - $totalObligations - $expenses;
        $loanToAnnualIncome = ($income * 12 > 0) ? ($requestedAmount / ($income * 12)) : 10;

        // 3. Score Breakdown by Category (Base = 650)
        $score = 650;
        $factors = [];

        // Category 1: Affordability & DTI (Weight: 35%)
        if ($dti <= 30) {
            $score += 60;
            $factors[] = [
                'code' => 'DTI_PRIME',
                'name' => 'Prime Debt-to-Income Ratio (' . round($dti, 1) . '%)',
                'category' => 'affordability',
                'weight' => 0.35,
                'score' => 95,
                'impact' => 'positive',
                'explanation' => 'Applicant total debt obligations comprise only ' . round($dti, 1) . '% of gross monthly earnings, providing substantial liquidity cushion.',
                'recommendation' => 'Eligible for prime interest rate bracket; low default probability.'
            ];
        } elseif ($dti <= 45) {
            $score += 20;
            $factors[] = [
                'code' => 'DTI_STANDARD',
                'name' => 'Standard Debt-to-Income Ratio (' . round($dti, 1) . '%)',
                'category' => 'affordability',
                'weight' => 0.35,
                'score' => 70,
                'impact' => 'neutral',
                'explanation' => 'DTI ratio is within standard underwriting policy benchmark (<= 45%).',
                'recommendation' => 'Standard rate bracket applicable. Normal monitoring.'
            ];
        } elseif ($dti <= 50) {
            $score -= 25;
            $factors[] = [
                'code' => 'DTI_ELEVATED',
                'name' => 'Elevated Debt-to-Income Ratio (' . round($dti, 1) . '%)',
                'category' => 'affordability',
                'weight' => 0.35,
                'score' => 50,
                'impact' => 'negative',
                'explanation' => 'DTI is in elevated range (45.01%–50.00%). Debt service absorbs half of income, leaving narrow emergency headroom.',
                'recommendation' => 'Requires compensating factors (high net surplus, co-applicant, or tenure elongation) and senior underwriter approval.'
            ];
        } else {
            $score -= 75;
            $factors[] = [
                'code' => 'DTI_EXCESSIVE',
                'name' => 'Excessive Debt Burden (' . round($dti, 1) . '%)',
                'category' => 'affordability',
                'weight' => 0.35,
                'score' => 20,
                'impact' => 'negative',
                'explanation' => 'Total debt obligations exceed maximum regulatory threshold (> 50%), representing acute over-leverage risk.',
                'recommendation' => 'Reject or restrict loan quantum to reduce total monthly EMI below 45% DTI.'
            ];
        }

        // Category 2: Employment Stability & Experience (Weight: 25%)
        if ($expYears >= 5) {
            $score += 45;
            $factors[] = [
                'code' => 'EXP_SENIOR',
                'name' => 'Stable Career Track Record (' . $expYears . '+ years)',
                'category' => 'employment',
                'weight' => 0.25,
                'score' => 90,
                'impact' => 'positive',
                'explanation' => 'Over ' . $expYears . ' years of industry experience indicates strong career durability and consistent earnings.',
                'recommendation' => 'Positive factor towards sanctioning requested tenure.'
            ];
        } elseif ($expYears >= 2) {
            $score += 15;
            $factors[] = [
                'code' => 'EXP_ADEQUATE',
                'name' => 'Adequate Employment Continuity (' . $expYears . ' years)',
                'category' => 'employment',
                'weight' => 0.25,
                'score' => 75,
                'impact' => 'neutral',
                'explanation' => 'Meets minimum employment policy guidelines (>= 1 year).',
                'recommendation' => 'Standard verification of employer status.'
            ];
        } else {
            $score -= 30;
            $factors[] = [
                'code' => 'EXP_LIMITED',
                'name' => 'Limited Employment Tenure (' . $expYears . ' years)',
                'category' => 'employment',
                'weight' => 0.25,
                'score' => 45,
                'impact' => 'negative',
                'explanation' => 'Short employment history presents moderate stability risk.',
                'recommendation' => 'Request employer verification letter or 6-month continuous bank statement.'
            ];
        }

        // Category 3: Disposable Cash Flow (Weight: 20%)
        if ($disposableIncome >= 25000) {
            $score += 40;
            $factors[] = [
                'code' => 'NET_SURPLUS_HEALTHY',
                'name' => 'Substantial Monthly Net Surplus (₹' . number_format($disposableIncome) . ')',
                'category' => 'affordability',
                'weight' => 0.20,
                'score' => 90,
                'impact' => 'positive',
                'explanation' => 'Substantial post-obligation disposable income demonstrates high repayment capacity.',
                'recommendation' => 'Strong candidate for quick sanction.'
            ];
        } elseif ($disposableIncome > 0) {
            $score += 10;
            $factors[] = [
                'code' => 'NET_SURPLUS_TIGHT',
                'name' => 'Tight Disposable Buffer (₹' . number_format($disposableIncome) . ')',
                'category' => 'affordability',
                'weight' => 0.20,
                'score' => 60,
                'impact' => 'neutral',
                'explanation' => 'Net disposable surplus is positive but sensitive to unexpected life expenses.',
                'recommendation' => 'Advise conservative tenure adjustment.'
            ];
        } else {
            $score -= 60;
            $factors[] = [
                'code' => 'NET_DEFICIT',
                'name' => 'Negative Monthly Free Cash Flow',
                'category' => 'affordability',
                'weight' => 0.20,
                'score' => 20,
                'impact' => 'negative',
                'explanation' => 'Declared living expenses and current debt obligations exceed declared monthly inflows.',
                'recommendation' => 'Underwrite only with co-applicant or downscaled principal.'
            ];
        }

        // Category 4: Fraud & KYC Integrity Check (Weight: 20%)
        $fraudStmt = $this->pdo->prepare("SELECT COUNT(*) FROM fraud_checks WHERE application_id = ? AND flag_status = 'flagged'");
        $fraudStmt->execute([$applicationId]);
        $fraudCount = (int)$fraudStmt->fetchColumn();

        if ($fraudCount === 0) {
            $score += 35;
            $factors[] = [
                'code' => 'FRAUD_CLEAN',
                'name' => 'Clean KYC & Document Integrity',
                'category' => 'kyc_compliance',
                'weight' => 0.20,
                'score' => 100,
                'impact' => 'positive',
                'explanation' => 'No duplicate applications, document tampering, or identity discrepancies detected.',
                'recommendation' => 'Proceed directly to standard verification.'
            ];
        } else {
            $score -= 90;
            $factors[] = [
                'code' => 'FRAUD_FLAGGED',
                'name' => 'Active Fraud / Discrepancy Flags (' . $fraudCount . ' flagged)',
                'category' => 'kyc_compliance',
                'weight' => 0.20,
                'score' => 25,
                'impact' => 'negative',
                'explanation' => 'System flagged ' . $fraudCount . ' potential risk indicators requiring manual analyst scrutiny.',
                'recommendation' => 'Mandatory manual underwriting escalation before any loan offer.'
            ];
        }

        // Clamp final score between 300 and 900
        $finalScore = max(300, min(900, $score));

        // Segment Risk Category
        $riskCategory = 'medium';
        $recommendation = 'review';
        if ($finalScore >= 750) {
            $riskCategory = 'low';
            $recommendation = 'approve';
        } elseif ($finalScore < 600) {
            $riskCategory = 'high';
            $recommendation = 'reject';
        }

        // Persist Factors
        $this->pdo->prepare("DELETE FROM risk_factor_explanations WHERE application_id = ?")->execute([$applicationId]);
        $ins = $this->pdo->prepare("INSERT INTO risk_factor_explanations 
            (application_id, factor_code, factor_name, category, weight, score, impact, explanation, recommendation)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($factors as $f) {
            $ins->execute([
                $applicationId, $f['code'], $f['name'], $f['category'],
                $f['weight'], $f['score'], $f['impact'], $f['explanation'], $f['recommendation']
            ]);
        }

        // Update loan application risk metrics
        $upd = $this->pdo->prepare("UPDATE loan_applications SET 
            credit_score = ?, 
            risk_score = ?, 
            risk_category = ? 
            WHERE id = ?");
        $riskPercent = round(((900 - $finalScore) / 600) * 100, 1);
        $upd->execute([$finalScore, $riskPercent, $riskCategory, $applicationId]);

        return [
            'application_id' => $applicationId,
            'credit_score' => $finalScore,
            'risk_score' => $riskPercent,
            'risk_category' => $riskCategory,
            'recommendation' => $recommendation,
            'dti_ratio' => round($dti, 1),
            'disposable_income' => round($disposableIncome, 2),
            'factors' => $factors
        ];
    }
}
