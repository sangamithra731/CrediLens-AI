<?php
/**
 * CrediLens AI — Loan Accounting & Payment Reconciliation Engine
 * Enforces double-entry style accounting ledger, validates repayments,
 * prevents duplicate payments, and manages part-payments and early closure.
 */

require_once __DIR__ . '/../config/database.php';

class LoanAccounting {
    private $pdo;

    public function __construct($pdo = null) {
        $this->pdo = $pdo ?: getDBConnection();
    }

    /**
     * Record Loan Disbursement Entry in Ledger
     */
    public function recordDisbursement($applicationId, $amount, $referenceNo, $performedBy = 1) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM loan_accounting_ledger WHERE application_id = ? AND entry_type = 'disbursement'");
        $stmt->execute([$applicationId]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Disbursement already recorded in ledger'];
        }

        $ins = $this->pdo->prepare("INSERT INTO loan_accounting_ledger 
            (application_id, entry_type, debit, credit, balance_after, reference_no, description, recorded_by)
            VALUES (?, 'disbursement', ?, 0.00, ?, ?, 'Initial Loan Principal Disbursement', ?)");
        $ins->execute([$applicationId, $amount, $amount, $referenceNo, $performedBy]);

        return ['success' => true, 'balance' => $amount];
    }

    /**
     * Process EMI Payment with strict duplicate protection
     */
    public function processRepayment($applicationId, $amount, $paymentRef, $principalPart = 0, $interestPart = 0, $recordedBy = null) {
        // Idempotency: Prevent duplicate payment reference
        $check = $this->pdo->prepare("SELECT id FROM loan_accounting_ledger WHERE reference_no = ?");
        $check->execute([$paymentRef]);
        if ($check->fetch()) {
            return ['success' => false, 'message' => 'Payment reference already processed (duplicate rejected)'];
        }

        $currentBalance = $this->getOutstandingBalance($applicationId);
        if ($currentBalance <= 0) {
            return ['success' => false, 'message' => 'Loan is already fully paid and settled'];
        }

        $newBalance = max(0, $currentBalance - $amount);
        $desc = "EMI Payment (Principal: ₹{$principalPart}, Interest: ₹{$interestPart})";

        // Record Credit Entry
        $ins = $this->pdo->prepare("INSERT INTO loan_accounting_ledger 
            (application_id, entry_type, debit, credit, balance_after, reference_no, description, recorded_by)
            VALUES (?, 'emi_principal', 0.00, ?, ?, ?, ?, ?)");
        $ins->execute([$applicationId, $amount, $newBalance, $paymentRef, $desc, $recordedBy]);

        // Auto close if balance reached 0
        if ($newBalance == 0) {
            $this->pdo->prepare("UPDATE loan_applications SET status = 'closed' WHERE id = ?")->execute([$applicationId]);
            $this->pdo->prepare("INSERT INTO loan_accounting_ledger 
                (application_id, entry_type, debit, credit, balance_after, reference_no, description, recorded_by)
                VALUES (?, 'full_closure', 0, 0, 0, ?, 'Loan Fully Settled & Closed — No Dues Certificate Issued', ?)")
                ->execute([$applicationId, 'CLOSURE-' . time(), $recordedBy]);
        }

        return [
            'success' => true,
            'amount_paid' => $amount,
            'remaining_balance' => $newBalance,
            'is_settled' => ($newBalance == 0)
        ];
    }

    /**
     * Process Part-Payment or Early Loan Settlement
     */
    public function processEarlyClosure($applicationId, $settlementAmount, $referenceNo, $recordedBy = null) {
        $balance = $this->getOutstandingBalance($applicationId);
        if ($settlementAmount < $balance) {
            return ['success' => false, 'message' => "Settlement amount (₹$settlementAmount) is less than outstanding balance (₹$balance)"];
        }

        // Record ledger settlement
        $ins = $this->pdo->prepare("INSERT INTO loan_accounting_ledger 
            (application_id, entry_type, debit, credit, balance_after, reference_no, description, recorded_by)
            VALUES (?, 'part_payment', 0.00, ?, 0.00, ?, 'Early Loan Settlement & Pre-closure', ?)");
        $ins->execute([$applicationId, $balance, $referenceNo, $recordedBy]);

        // Mark application as closed
        $this->pdo->prepare("UPDATE loan_applications SET status = 'closed' WHERE id = ?")->execute([$applicationId]);

        return [
            'success' => true,
            'message' => 'Loan successfully pre-closed and settled in full.',
            'balance' => 0.00
        ];
    }

    /**
     * Get Current Outstanding Balance from Ledger
     */
    public function getOutstandingBalance($applicationId) {
        $stmt = $this->pdo->prepare("SELECT balance_after FROM loan_accounting_ledger WHERE application_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$applicationId]);
        $val = $stmt->fetchColumn();

        if ($val !== false) {
            return (float)$val;
        }

        // Fallback: Check approved amount from application
        $app = $this->pdo->prepare("SELECT COALESCE(approved_amount, requested_amount) FROM loan_applications WHERE id = ?");
        $app->execute([$applicationId]);
        return (float)($app->fetchColumn() ?: 0);
    }

    /**
     * Payment Gateway Reconciliation
     */
    public function reconcileTransaction($reference, $expectedAmount, $actualAmount, $gateway = 'razorpay') {
        $diff = abs($expectedAmount - $actualAmount);
        $status = ($diff < 0.01) ? 'matched' : 'mismatched';

        $ins = $this->pdo->prepare("INSERT INTO payment_reconciliations 
            (application_id, transaction_reference, payment_gateway, expected_amount, actual_amount, status, reconciled_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE actual_amount = VALUES(actual_amount), status = VALUES(status), reconciled_at = NOW()");
        $ins->execute([0, $reference, $gateway, $expectedAmount, $actualAmount, $status]);

        return [
            'reference' => $reference,
            'status' => $status,
            'expected' => $expectedAmount,
            'actual' => $actualAmount,
            'discrepancy' => $diff
        ];
    }
}
