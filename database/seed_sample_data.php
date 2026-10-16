<?php
/**
 * CrediLens AI — 20 Comprehensive Sample Records Seeder
 * Idempotently populates 20 diverse, realistic loan applications spanning
 * all states, loan types, OCR data, risk factors, accounting entries, and fraud checks.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/credit_risk_engine.php';
require_once __DIR__ . '/../includes/fraud_detector.php';
require_once __DIR__ . '/../includes/loan_accounting.php';

$pdo = getDBConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "--- Starting 20 Sample Records Seeding ---\n";

// 1. Ensure borrower users exist
$sampleBorrowers = [
    ['name' => 'Aarav Sharma',    'email' => 'aarav.sharma@example.com',    'phone' => '9876500001'],
    ['name' => 'Priya Patel',     'email' => 'priya.patel@example.com',     'phone' => '9876500002'],
    ['name' => 'Rahul Verma',     'email' => 'rahul.verma@example.com',     'phone' => '9876500003'],
    ['name' => 'Ananya Iyer',     'email' => 'ananya.iyer@example.com',     'phone' => '9876500004'],
    ['name' => 'Vikram Malhotra', 'email' => 'vikram.malhotra@example.com', 'phone' => '9876500005'],
    ['name' => 'Neha Gupta',      'email' => 'neha.gupta@example.com',      'phone' => '9876500006'],
    ['name' => 'Karthik Raja',    'email' => 'karthik.raja@example.com',    'phone' => '9876500007'],
    ['name' => 'Sneha Kulkarni',  'email' => 'sneha.kulkarni@example.com',  'phone' => '9876500008'],
    ['name' => 'Arjun Nair',      'email' => 'arjun.nair@example.com',      'phone' => '9876500009'],
    ['name' => 'Pooja Reddy',     'email' => 'pooja.reddy@example.com',     'phone' => '9876500010'],
    ['name' => 'Rohan Mehta',     'email' => 'rohan.mehta@example.com',     'phone' => '9876500011'],
    ['name' => 'Divya Deshmukh',  'email' => 'divya.deshmukh@example.com',  'phone' => '9876500012'],
    ['name' => 'Aditya Singhania','email' => 'aditya.singhania@example.com', 'phone' => '9876500013'],
    ['name' => 'Meera Nambiar',   'email' => 'meera.nambiar@example.com',   'phone' => '9876500014'],
    ['name' => 'Siddharth Rao',   'email' => 'siddharth.rao@example.com',   'phone' => '9876500015'],
    ['name' => 'Tanvi Joshi',     'email' => 'tanvi.joshi@example.com',     'phone' => '9876500016'],
    ['name' => 'Kunal Bansal',    'email' => 'kunal.bansal@example.com',    'phone' => '9876500017'],
    ['name' => 'Ishita Sen',      'email' => 'ishita.sen@example.com',      'phone' => '9876500018'],
    ['name' => 'Gaurav Chopra',   'email' => 'gaurav.chopra@example.com',   'phone' => '9876500019'],
    ['name' => 'Ritu Saxena',     'email' => 'ritu.saxena@example.com',     'phone' => '9876500020']
];

$userMap = [];
$defaultPassword = password_hash('Applicant@123', PASSWORD_BCRYPT);

foreach ($sampleBorrowers as $b) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$b['email']]);
    $uid = $stmt->fetchColumn();
    if (!$uid) {
        $ins = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, phone, role_id, is_active) VALUES (?, ?, ?, ?, 1, 1)");
        $ins->execute([$b['name'], $b['email'], $defaultPassword, $b['phone']]);
        $uid = $pdo->lastInsertId();
    }
    $userMap[$b['email']] = (int)$uid;
}

// 2. Loan Applications Dataset Specification (20 records)
$applicationsData = [
    // 1. Prime Salaried - Approved & Disbursed (Regular Good EMI)
    [
        'idx' => 1, 'email' => 'aarav.sharma@example.com', 'loan_type' => 1, 'amount' => 500000, 'tenure' => 24,
        'income' => 125000, 'expenses' => 35000, 'existing_emi' => 10000, 'city' => 'Bengaluru', 'state' => 'Karnataka',
        'employer' => 'Infosys Technologies', 'emp_type' => 'salaried', 'exp' => 7,
        'pan' => 'AAAPA1001A', 'aadhaar' => '234567890001',
        'status' => 'disbursed', 'credit_score' => 815, 'risk_score' => 14.2, 'risk_cat' => 'low',
        'approved_amount' => 500000, 'interest_rate' => 10.25, 'tenure_months' => 24
    ],
    // 2. Tech Executive - Disbursed with Overdue SMA-0 (1-30 DPD)
    [
        'idx' => 2, 'email' => 'priya.patel@example.com', 'loan_type' => 2, 'amount' => 1500000, 'tenure' => 60,
        'income' => 180000, 'expenses' => 60000, 'existing_emi' => 25000, 'city' => 'Mumbai', 'state' => 'Maharashtra',
        'employer' => 'Tata Consultancy Services', 'emp_type' => 'salaried', 'exp' => 9,
        'pan' => 'BBBPB1002B', 'aadhaar' => '234567890002',
        'status' => 'disbursed', 'credit_score' => 765, 'risk_score' => 22.5, 'risk_cat' => 'low',
        'approved_amount' => 1500000, 'interest_rate' => 9.50, 'tenure_months' => 60,
        'overdue_bucket' => 'sma_0'
    ],
    // 3. SME Business Owner - Disbursed with Overdue SMA-1 (31-60 DPD)
    [
        'idx' => 3, 'email' => 'rahul.verma@example.com', 'loan_type' => 3, 'amount' => 750000, 'tenure' => 36,
        'income' => 95000, 'expenses' => 40000, 'existing_emi' => 18000, 'city' => 'Delhi', 'state' => 'Delhi',
        'employer' => 'Verma Logistics & Cargo', 'emp_type' => 'business', 'exp' => 4,
        'pan' => 'CCCPC1003C', 'aadhaar' => '234567890003',
        'status' => 'disbursed', 'credit_score' => 680, 'risk_score' => 36.7, 'risk_cat' => 'medium',
        'approved_amount' => 750000, 'interest_rate' => 13.50, 'tenure_months' => 36,
        'overdue_bucket' => 'sma_1'
    ],
    // 4. Defaulted High Risk - Disbursed in 90+ DPD (NPA)
    [
        'idx' => 4, 'email' => 'ananya.iyer@example.com', 'loan_type' => 1, 'amount' => 300000, 'tenure' => 18,
        'income' => 45000, 'expenses' => 28000, 'existing_emi' => 12000, 'city' => 'Chennai', 'state' => 'Tamil Nadu',
        'employer' => 'Iyer Digital Media', 'emp_type' => 'self_employed', 'exp' => 2,
        'pan' => 'DDDPD1004D', 'aadhaar' => '234567890004',
        'status' => 'disbursed', 'credit_score' => 560, 'risk_score' => 56.7, 'risk_cat' => 'high',
        'approved_amount' => 300000, 'interest_rate' => 16.00, 'tenure_months' => 18,
        'overdue_bucket' => 'npa'
    ],
    // 5. Pre-Closed Fully Settled Loan
    [
        'idx' => 5, 'email' => 'vikram.malhotra@example.com', 'loan_type' => 1, 'amount' => 400000, 'tenure' => 24,
        'income' => 140000, 'expenses' => 45000, 'existing_emi' => 0, 'city' => 'Hyderabad', 'state' => 'Telangana',
        'employer' => 'Google India Pvt Ltd', 'emp_type' => 'salaried', 'exp' => 8,
        'pan' => 'EEEPE1005E', 'aadhaar' => '234567890005',
        'status' => 'closed', 'credit_score' => 840, 'risk_score' => 10.0, 'risk_cat' => 'low',
        'approved_amount' => 400000, 'interest_rate' => 10.00, 'tenure_months' => 24
    ],
    // 6. Approved - Awaiting Applicant Acceptance
    [
        'idx' => 6, 'email' => 'neha.gupta@example.com', 'loan_type' => 2, 'amount' => 2500000, 'tenure' => 84,
        'income' => 210000, 'expenses' => 70000, 'existing_emi' => 30000, 'city' => 'Pune', 'state' => 'Maharashtra',
        'employer' => 'Cognizant Technology Solutions', 'emp_type' => 'salaried', 'exp' => 10,
        'pan' => 'FFFPG1006F', 'aadhaar' => '234567890006',
        'status' => 'approved', 'credit_score' => 790, 'risk_score' => 18.3, 'risk_cat' => 'low',
        'approved_amount' => 2500000, 'interest_rate' => 8.90, 'tenure_months' => 84
    ],
    // 7. Approved - Personal Loan Offer Dispatched
    [
        'idx' => 7, 'email' => 'karthik.raja@example.com', 'loan_type' => 1, 'amount' => 600000, 'tenure' => 36,
        'income' => 110000, 'expenses' => 40000, 'existing_emi' => 15000, 'city' => 'Coimbatore', 'state' => 'Tamil Nadu',
        'employer' => 'Bosch Automotive India', 'emp_type' => 'salaried', 'exp' => 6,
        'pan' => 'GGGPK1007G', 'aadhaar' => '234567890007',
        'status' => 'approved', 'credit_score' => 730, 'risk_score' => 28.3, 'risk_cat' => 'medium',
        'approved_amount' => 600000, 'interest_rate' => 11.50, 'tenure_months' => 36
    ],
    // 8. Under Review - Active Clarification Request
    [
        'idx' => 8, 'email' => 'sneha.kulkarni@example.com', 'loan_type' => 1, 'amount' => 350000, 'tenure' => 24,
        'income' => 65000, 'expenses' => 25000, 'existing_emi' => 8000, 'city' => 'Nashik', 'state' => 'Maharashtra',
        'employer' => 'Kulkarni Enterprises', 'emp_type' => 'self_employed', 'exp' => 3,
        'pan' => 'HHHPS1008H', 'aadhaar' => '234567890008',
        'status' => 'clarification_required', 'credit_score' => 670, 'risk_score' => 38.3, 'risk_cat' => 'medium',
        'clarification' => ['subject' => 'Bank Statement Clarification', 'query' => 'Recent credit of Rs 1,50,000 in October requires source of funds explanation.']
    ],
    // 9. Clarification Required - Mismatched Salary Slip
    [
        'idx' => 9, 'email' => 'arjun.nair@example.com', 'loan_type' => 1, 'amount' => 450000, 'tenure' => 36,
        'income' => 85000, 'expenses' => 35000, 'existing_emi' => 10000, 'city' => 'Kochi', 'state' => 'Kerala',
        'employer' => 'Nair Shipping Logistics', 'emp_type' => 'salaried', 'exp' => 5,
        'pan' => 'IIIAN1009I', 'aadhaar' => '234567890009',
        'status' => 'clarification_required', 'credit_score' => 640, 'risk_score' => 43.3, 'risk_cat' => 'medium',
        'clarification' => ['subject' => 'Form 16 / Tax Summary Required', 'query' => 'Gross monthly pay in salary slip shows deductions for variable pay. Please provide Form 16.']
    ],
    // 10. Fraud Flagged - Document Reuse Case (shared hash with another application)
    [
        'idx' => 10, 'email' => 'pooja.reddy@example.com', 'loan_type' => 1, 'amount' => 500000, 'tenure' => 24,
        'income' => 55000, 'expenses' => 30000, 'existing_emi' => 14000, 'city' => 'Hyderabad', 'state' => 'Telangana',
        'employer' => 'Reddy Consultancy', 'emp_type' => 'self_employed', 'exp' => 2,
        'pan' => 'JJJPR1010J', 'aadhaar' => '234567890010',
        'status' => 'under_review', 'credit_score' => 580, 'risk_score' => 53.3, 'risk_cat' => 'high',
        'fraud_flag' => ['type' => 'document_reuse', 'severity' => 'critical', 'score' => 60, 'title' => 'Document Reuse Across Applicants', 'desc' => 'Cryptographic SHA-256 matches salary slip uploaded by another registered user.']
    ],
    // 11. Fraud Flagged - Suspected Income Inflation
    [
        'idx' => 11, 'email' => 'rohan.mehta@example.com', 'loan_type' => 3, 'amount' => 1200000, 'tenure' => 48,
        'income' => 250000, 'expenses' => 80000, 'existing_emi' => 40000, 'city' => 'Ahmedabad', 'state' => 'Gujarat',
        'employer' => 'Mehta Diamonds', 'emp_type' => 'business', 'exp' => 6,
        'pan' => 'KKKRM1011K', 'aadhaar' => '234567890011',
        'status' => 'under_review', 'credit_score' => 610, 'risk_score' => 48.3, 'risk_cat' => 'medium',
        'fraud_flag' => ['type' => 'income_inflation', 'severity' => 'high', 'score' => 40, 'title' => 'Suspected Income Inflation (> 50%)', 'desc' => 'Declared income Rs 2,50,000 exceeds verified bank credits of Rs 1,40,000 by 78%.']
    ],
    // 12. Fraud Flagged - Cross-Account PAN Duplicate
    [
        'idx' => 12, 'email' => 'divya.deshmukh@example.com', 'loan_type' => 1, 'amount' => 200000, 'tenure' => 12,
        'income' => 40000, 'expenses' => 20000, 'existing_emi' => 8000, 'city' => 'Nagpur', 'state' => 'Maharashtra',
        'employer' => 'Central Retail Ltd', 'emp_type' => 'salaried', 'exp' => 2,
        'pan' => 'AAAPA1001A', // Same PAN as Aarav Sharma for duplicate check!
        'aadhaar' => '234567890012',
        'status' => 'under_review', 'credit_score' => 520, 'risk_score' => 63.3, 'risk_cat' => 'high',
        'fraud_flag' => ['type' => 'duplicate_pan_cross_user', 'severity' => 'critical', 'score' => 50, 'title' => 'Cross-Account Identity Match (PAN)', 'desc' => 'PAN AAAPA1001A is registered to a different user account.']
    ],
    // 13. High Risk Escalated Case - Policy Deviation
    [
        'idx' => 13, 'email' => 'aditya.singhania@example.com', 'loan_type' => 1, 'amount' => 800000, 'tenure' => 36,
        'income' => 70000, 'expenses' => 35000, 'existing_emi' => 22000, 'city' => 'Kolkata', 'state' => 'West Bengal',
        'employer' => 'Singhania Trading Co', 'emp_type' => 'business', 'exp' => 3,
        'pan' => 'LLLPS1013L', 'aadhaar' => '234567890013',
        'status' => 'under_review', 'credit_score' => 590, 'risk_score' => 51.7, 'risk_cat' => 'high',
        'escalation' => ['reason' => 'DTI limit breach (48.5%) & variable income', 'priority' => 'high', 'details' => 'Applicant requests exception review for working capital expansion.']
    ],
    // 14. Document Verification Stage
    [
        'idx' => 14, 'email' => 'meera.nambiar@example.com', 'loan_type' => 1, 'amount' => 300000, 'tenure' => 24,
        'income' => 75000, 'expenses' => 28000, 'existing_emi' => 5000, 'city' => 'Thiruvananthapuram', 'state' => 'Kerala',
        'employer' => 'Kerala State Electricity Board', 'emp_type' => 'salaried', 'exp' => 8,
        'pan' => 'MMMAM1014M', 'aadhaar' => '234567890014',
        'status' => 'document_verification', 'credit_score' => 750, 'risk_score' => 25.0, 'risk_cat' => 'low'
    ],
    // 15. Document Verification Stage - Auto Loan
    [
        'idx' => 15, 'email' => 'siddharth.rao@example.com', 'loan_type' => 2, 'amount' => 900000, 'tenure' => 48,
        'income' => 135000, 'expenses' => 50000, 'existing_emi' => 18000, 'city' => 'Mysuru', 'state' => 'Karnataka',
        'employer' => 'Wipro Limited', 'emp_type' => 'salaried', 'exp' => 5,
        'pan' => 'NNNSR1015N', 'aadhaar' => '234567890015',
        'status' => 'document_verification', 'credit_score' => 720, 'risk_score' => 30.0, 'risk_cat' => 'medium'
    ],
    // 16. Risk Assessment In Progress
    [
        'idx' => 16, 'email' => 'tanvi.joshi@example.com', 'loan_type' => 1, 'amount' => 550000, 'tenure' => 36,
        'income' => 90000, 'expenses' => 38000, 'existing_emi' => 12000, 'city' => 'Indore', 'state' => 'Madhya Pradesh',
        'employer' => 'TCS Digital Studio', 'emp_type' => 'salaried', 'exp' => 4,
        'pan' => 'OOOPT1016O', 'aadhaar' => '234567890016',
        'status' => 'risk_assessment', 'credit_score' => 710, 'risk_score' => 31.7, 'risk_cat' => 'medium'
    ],
    // 17. Risk Assessment In Progress - Education Loan
    [
        'idx' => 17, 'email' => 'kunal.bansal@example.com', 'loan_type' => 2, 'amount' => 1200000, 'tenure' => 60,
        'income' => 160000, 'expenses' => 55000, 'existing_emi' => 20000, 'city' => 'Chandigarh', 'state' => 'Punjab',
        'employer' => 'HCL Technologies', 'emp_type' => 'salaried', 'exp' => 6,
        'pan' => 'PPPB1017P', 'aadhaar' => '234567890017',
        'status' => 'risk_assessment', 'credit_score' => 740, 'risk_score' => 26.7, 'risk_cat' => 'low'
    ],
    // 18. Newly Submitted
    [
        'idx' => 18, 'email' => 'ishita.sen@example.com', 'loan_type' => 1, 'amount' => 250000, 'tenure' => 18,
        'income' => 60000, 'expenses' => 25000, 'existing_emi' => 0, 'city' => 'Bhubaneswar', 'state' => 'Odisha',
        'employer' => 'Odisha Mining Corp', 'emp_type' => 'salaried', 'exp' => 3,
        'pan' => 'QQQIS1018Q', 'aadhaar' => '234567890018',
        'status' => 'submitted', 'credit_score' => 700, 'risk_score' => 33.3, 'risk_cat' => 'medium'
    ],
    // 19. Newly Submitted
    [
        'idx' => 19, 'email' => 'gaurav.chopra@example.com', 'loan_type' => 1, 'amount' => 400000, 'tenure' => 24,
        'income' => 80000, 'expenses' => 32000, 'existing_emi' => 7000, 'city' => 'Jaipur', 'state' => 'Rajasthan',
        'employer' => 'Chopra Crafts & Exports', 'emp_type' => 'business', 'exp' => 4,
        'pan' => 'RRRGC1019R', 'aadhaar' => '234567890019',
        'status' => 'submitted', 'credit_score' => 690, 'risk_score' => 35.0, 'risk_cat' => 'medium'
    ],
    // 20. Closed - No Dues Full Settlement
    [
        'idx' => 20, 'email' => 'ritu.saxena@example.com', 'loan_type' => 1, 'amount' => 350000, 'tenure' => 24,
        'income' => 95000, 'expenses' => 35000, 'existing_emi' => 0, 'city' => 'Lucknow', 'state' => 'Uttar Pradesh',
        'employer' => 'UP Power Corp', 'emp_type' => 'salaried', 'exp' => 7,
        'pan' => 'SSSRS1020S', 'aadhaar' => '234567890020',
        'status' => 'closed', 'credit_score' => 780, 'risk_score' => 20.0, 'risk_cat' => 'low',
        'approved_amount' => 350000, 'interest_rate' => 10.50, 'tenure_months' => 24
    ]
];

$creEngine = new CreditRiskEngine($pdo);
$fdEngine = new FraudDetector($pdo);
$acctEngine = new LoanAccounting($pdo);

$insertedCount = 0;

foreach ($applicationsData as $app) {
    $userId = $userMap[$app['email']];
    $appStrId = 'APP-SAMPLE-' . str_pad($app['idx'], 3, '0', STR_PAD_LEFT);

    // Check if application exists
    $stmt = $pdo->prepare("SELECT id FROM loan_applications WHERE application_id = ?");
    $stmt->execute([$appStrId]);
    $existingId = $stmt->fetchColumn();

    if ($existingId) {
        $appId = (int)$existingId;
    } else {
        $ins = $pdo->prepare("INSERT INTO loan_applications (
            application_id, user_id, loan_type_id, full_name, date_of_birth, gender, marital_status,
            address_line1, city, state, pincode, pan_number, aadhaar_number,
            employment_type, employer_name, years_of_experience,
            monthly_income, monthly_expenses, existing_emi, requested_amount, requested_tenure,
            status, credit_score, risk_score, risk_category,
            approved_amount, interest_rate, tenure_months, submitted_at, created_at
        ) VALUES (
            ?, ?, ?, ?, '1990-05-15', 'male', 'married',
            'Sector 4, Building 12', ?, ?, '560001', ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, NOW(), NOW()
        )");
        $ins->execute([
            $appStrId, $userId, $app['loan_type'], explode('@', $app['email'])[0],
            $app['city'], $app['state'], $app['pan'], $app['aadhaar'],
            $app['emp_type'], $app['employer'], $app['exp'],
            $app['income'], $app['expenses'], $app['existing_emi'], $app['amount'], $app['tenure'],
            $app['status'], $app['credit_score'], $app['risk_score'], $app['risk_cat'],
            $app['approved_amount'] ?? null, $app['interest_rate'] ?? null, $app['tenure_months'] ?? null
        ]);
        $appId = (int)$pdo->lastInsertId();
        $insertedCount++;
    }

    // 3. Populate Explainable Risk Factors
    $creEngine->evaluateApplication($appId);

    // 4. Attach Documents & OCR Data
    $docTypes = [
        ['type_id' => 1, 'name' => 'PAN Card', 'file' => 'pan_' . $app['idx'] . '.jpg'],
        ['type_id' => 2, 'name' => 'Aadhaar Card', 'file' => 'aadhaar_' . $app['idx'] . '.jpg'],
        ['type_id' => 3, 'name' => 'Salary Slip', 'file' => 'salary_' . $app['idx'] . '.pdf']
    ];

    foreach ($docTypes as $dt) {
        $chkDoc = $pdo->prepare("SELECT id FROM application_documents WHERE application_id = ? AND document_type_id = ?");
        $chkDoc->execute([$appId, $dt['type_id']]);
        $docId = $chkDoc->fetchColumn();

        if (!$docId) {
            $fileHash = hash('sha256', $appStrId . '_' . $dt['name']);
            if (isset($app['fraud_flag']) && $app['fraud_flag']['type'] === 'document_reuse' && $dt['type_id'] === 3) {
                // Deliberately reuse the hash from app 1 for document reuse testing!
                $fileHash = hash('sha256', 'APP-SAMPLE-001_Salary Slip');
            }

            $insDoc = $pdo->prepare("INSERT INTO application_documents (
                application_id, document_type_id, file_name, file_path, file_size, mime_type, file_hash, ocr_status, ocr_match_score, uploaded_at
            ) VALUES (?, ?, ?, ?, 45200, 'application/pdf', ?, 'completed', 92.5, NOW())");
            $insDoc->execute([$appId, $dt['type_id'], $dt['file'], 'uploads/samples/' . $dt['file'], $fileHash]);
            $docId = $pdo->lastInsertId();

            // Populate document_ocr_data
            $extIncome = $dt['type_id'] === 3 ? (isset($app['fraud_flag']) && $app['fraud_flag']['type'] === 'income_inflation' ? round($app['income'] * 0.55) : $app['income']) : null;
            $insOcr = $pdo->prepare("INSERT INTO document_ocr_data (
                document_id, application_id, document_type, extracted_name, extracted_id_number, extracted_income, match_confidence, name_match, id_match, income_match
            ) VALUES (?, ?, ?, ?, ?, ?, 94.0, 1, 1, 1)");
            $insOcr->execute([$docId, $appId, $dt['name'], explode('@', $app['email'])[0], $app['pan'], $extIncome]);
        }
    }

    // 5. Populate Accounting Ledger & Repayment Schedules for Disbursed & Closed loans
    if (in_array($app['status'], ['disbursed', 'closed'])) {
        $approvedAmt = (float)$app['approved_amount'];
        $rate = (float)$app['interest_rate'];
        $tenure = (int)$app['tenure_months'];
        $disbRef = 'DISB-SMP-' . $appStrId;

        // Record Disbursement in ledger if not exists
        $chkLedger = $pdo->prepare("SELECT COUNT(*) FROM loan_accounting_ledger WHERE application_id = ? AND entry_type = 'disbursement'");
        $chkLedger->execute([$appId]);
        if ($chkLedger->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO loan_accounting_ledger (application_id, entry_type, debit, credit, balance_after, reference_no, description, recorded_by, created_at) VALUES (?, 'disbursement', ?, 0, ?, ?, 'Initial Loan Principal Disbursement', 1, DATE_SUB(NOW(), INTERVAL 4 MONTH))")
                ->execute([$appId, $approvedAmt, $approvedAmt, $disbRef]);
        }

        // Generate EMI Amortization Schedule
        $chkEmi = $pdo->prepare("SELECT COUNT(*) FROM emi_schedule WHERE application_id = ?");
        $chkEmi->execute([$appId]);
        if ($chkEmi->fetchColumn() == 0) {
            $monthlyRate = ($rate / 12) / 100;
            $emi = ($approvedAmt * $monthlyRate * pow(1 + $monthlyRate, $tenure)) / (pow(1 + $monthlyRate, $tenure) - 1);
            $bal = $approvedAmt;

            for ($i = 1; $i <= $tenure; $i++) {
                $intPart = $bal * $monthlyRate;
                $prinPart = $emi - $intPart;
                $bal = max(0, $bal - $prinPart);

                // Date calculation: backdate by 4 months
                $monthOffset = $i - 4;
                $dueDate = date('Y-m-d', strtotime("$monthOffset month"));
                $payStatus = 'upcoming';
                $paidAmt = 0;
                $paidAt = null;

                if ($app['status'] === 'closed') {
                    $payStatus = 'paid';
                    $paidAmt = $emi;
                    $paidAt = date('Y-m-d H:i:s', strtotime("$dueDate -2 days"));
                } elseif ($monthOffset < 0) {
                    // Past installment
                    if (isset($app['overdue_bucket'])) {
                        if ($app['overdue_bucket'] === 'sma_0' && $i === 3) {
                            $payStatus = 'overdue'; // 1-30 DPD
                        } elseif ($app['overdue_bucket'] === 'sma_1' && $i === 2) {
                            $payStatus = 'overdue'; // 31-60 DPD
                        } elseif ($app['overdue_bucket'] === 'npa') {
                            $payStatus = 'overdue'; // 90+ DPD
                        } else {
                            $payStatus = 'paid';
                            $paidAmt = $emi;
                            $paidAt = date('Y-m-d H:i:s', strtotime("$dueDate -1 day"));
                        }
                    } else {
                        $payStatus = 'paid';
                        $paidAmt = $emi;
                        $paidAt = date('Y-m-d H:i:s', strtotime("$dueDate -1 day"));
                    }
                }

                $pdo->prepare("INSERT INTO emi_schedule (application_id, installment_number, due_date, emi_amount, principal_component, interest_component, outstanding_balance, payment_status, paid_amount, paid_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$appId, $i, $dueDate, round($emi, 2), round($prinPart, 2), round($intPart, 2), round($bal, 2), $payStatus, $paidAmt, $paidAt]);

                if ($payStatus === 'paid') {
                    $repRef = 'PAY-' . $appStrId . '-' . $i;
                    $pdo->prepare("INSERT IGNORE INTO loan_accounting_ledger (application_id, entry_type, debit, credit, balance_after, reference_no, description, recorded_by, created_at) VALUES (?, 'emi_principal', 0, ?, ?, ?, 'Monthly EMI Repayment', 1, ?)")
                        ->execute([$appId, round($emi, 2), round($bal, 2), $repRef, $paidAt]);
                }
            }

            if ($app['status'] === 'closed') {
                $pdo->prepare("INSERT INTO loan_accounting_ledger (application_id, entry_type, debit, credit, balance_after, reference_no, description, recorded_by, created_at) VALUES (?, 'full_closure', 0, 0, 0, ?, 'Loan Fully Settled & Closed — No Dues Certificate Issued', 1, NOW())")
                    ->execute([$appId, 'CLOSURE-' . $appStrId]);
            }
        }
    }

    // 6. Populate Clarifications if applicable
    if (isset($app['clarification'])) {
        $chkClar = $pdo->prepare("SELECT COUNT(*) FROM application_clarifications WHERE application_id = ?");
        $chkClar->execute([$appId]);
        if ($chkClar->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO application_clarifications (application_id, requested_by, subject, query, status, created_at) VALUES (?, 2, ?, ?, 'pending_applicant', NOW())")
                ->execute([$appId, $app['clarification']['subject'], $app['clarification']['query']]);
        }
    }

    // 7. Populate Fraud Flags if applicable
    if (isset($app['fraud_flag'])) {
        $chkFraud = $pdo->prepare("SELECT COUNT(*) FROM fraud_checks WHERE application_id = ? AND check_type = ?");
        $chkFraud->execute([$appId, $app['fraud_flag']['type']]);
        if ($chkFraud->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO fraud_checks (application_id, check_type, severity, score, flag_status, title, description, created_at) VALUES (?, ?, ?, ?, 'flagged', ?, ?, NOW())")
                ->execute([$appId, $app['fraud_flag']['type'], $app['fraud_flag']['severity'], $app['fraud_flag']['score'], $app['fraud_flag']['title'], $app['fraud_flag']['desc']]);
        }
    }

    // 8. Populate Escalations if applicable
    if (isset($app['escalation'])) {
        $chkEsc = $pdo->prepare("SELECT COUNT(*) FROM application_escalations WHERE application_id = ?");
        $chkEsc->execute([$appId]);
        if ($chkEsc->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO application_escalations (application_id, escalated_by, priority, reason, details, status, created_at) VALUES (?, 2, ?, ?, ?, 'pending', NOW())")
                ->execute([$appId, $app['escalation']['priority'], $app['escalation']['reason'], $app['escalation']['details']]);
        }
    }

    // 9. Generate Loan Offer if approved
    if ($app['status'] === 'approved') {
        $chkOffer = $pdo->prepare("SELECT COUNT(*) FROM loan_offers WHERE application_id = ?");
        $chkOffer->execute([$appId]);
        if ($chkOffer->fetchColumn() == 0) {
            $appAmt = (float)$app['approved_amount'];
            $rRate = (float)$app['interest_rate'];
            $tMonths = (int)$app['tenure_months'];
            $mRate = ($rRate / 12) / 100;
            $eAmt = ($appAmt * $mRate * pow(1 + $mRate, $tMonths)) / (pow(1 + $mRate, $tMonths) - 1);

            $totPayable = $eAmt * $tMonths;
            $totInterest = $totPayable - $appAmt;

            $pdo->prepare("INSERT INTO loan_offers (application_id, approved_amount, interest_rate, tenure_months, emi_amount, total_interest, total_payable, processing_fee, offer_status, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'sent', DATE_ADD(NOW(), INTERVAL 14 DAY))")
                ->execute([$appId, $appAmt, $rRate, $tMonths, round($eAmt, 2), round($totInterest, 2), round($totPayable, 2), round($appAmt * 0.01, 2)]);
        }
    }
}

echo "Successfully seeded/updated 20 diverse sample records across all lifecycle stages!\n";
