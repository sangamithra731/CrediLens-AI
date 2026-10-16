<?php
/**
 * Module 2: New Loan Application — Multi-step wizard
 */
requireLogin();
if ($_SESSION['user_role'] !== 'applicant') {
    echo '<div class="alert alert-error">Only applicants can create loan applications.</div>';
    return;
}
$pdo = getDBConnection();
$loanTypes = $pdo->query("SELECT * FROM loan_types WHERE is_active = 1 ORDER BY name")->fetchAll();
$states = ['Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Goa','Gujarat','Haryana','Himachal Pradesh','Jharkhand','Karnataka','Kerala','Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura','Uttar Pradesh','Uttarakhand','West Bengal','Delhi','Jammu & Kashmir','Ladakh'];
?>

<div class="fade-in">
    <!-- Progress Steps -->
    <div class="card mb-3">
        <div class="card-body" style="padding: 1.5rem 2rem;">
            <div class="steps" id="applicationSteps">
                <div class="step active" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-label">Personal<br>Details</div>
                </div>
                <div class="step" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-label">Employment<br>& Finance</div>
                </div>
                <div class="step" data-step="3">
                    <div class="step-number">3</div>
                    <div class="step-label">Loan<br>Details</div>
                </div>
                <div class="step" data-step="4">
                    <div class="step-number">4</div>
                    <div class="step-label">Documents<br>Upload</div>
                </div>
                <div class="step" data-step="5">
                    <div class="step-number">5</div>
                    <div class="step-label">Review<br>& Submit</div>
                </div>
            </div>
        </div>
    </div>

    <form id="applicationForm">
        <input type="hidden" id="applicationDbId" value="">

        <!-- STEP 1: Personal Details -->
        <div class="step-panel active" id="panel1">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">👤 Personal Details</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" id="full_name" class="form-control" placeholder="As per official documents" value="<?= htmlspecialchars($_SESSION['user_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth *</label>
                            <input type="date" id="date_of_birth" class="form-control" max="<?= date('Y-m-d', strtotime('-21 years')) ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Gender *</label>
                            <select id="gender" class="form-control" required>
                                <option value="">Select gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other / Prefer not to say</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Marital Status *</label>
                            <select id="marital_status" class="form-control" required>
                                <option value="">Select status</option>
                                <option value="single">Single</option>
                                <option value="married">Married</option>
                                <option value="divorced">Divorced</option>
                                <option value="widowed">Widowed</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 1 *</label>
                        <input type="text" id="address_line1" class="form-control" placeholder="House/Flat no., Street, Area" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address Line 2</label>
                        <input type="text" id="address_line2" class="form-control" placeholder="Landmark (optional)">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">City *</label>
                            <input type="text" id="city" class="form-control" placeholder="City" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">State *</label>
                            <select id="state" class="form-control" required>
                                <option value="">Select state</option>
                                <?php foreach ($states as $s): ?>
                                <option value="<?= $s ?>"><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Pincode *</label>
                            <input type="text" id="pincode" class="form-control" placeholder="6-digit pincode" maxlength="6" pattern="[0-9]{6}" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">PAN Number *</label>
                            <input type="text" id="pan_number" class="form-control" placeholder="ABCDE1234F" maxlength="10" style="text-transform: uppercase;" required>
                            <span class="form-text">10-character PAN (e.g., ABCDE1234F)</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Aadhaar Number *</label>
                            <input type="text" id="aadhaar_number" class="form-control" placeholder="12-digit Aadhaar" maxlength="12" required>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-primary" onclick="nextStep(1)">Next: Employment Details →</button>
                </div>
            </div>
        </div>

        <!-- STEP 2: Employment & Financial -->
        <div class="step-panel hidden" id="panel2">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">💼 Employment & Financial Details</h3>
                </div>
                <div class="card-body">
                    <h4 style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Employment</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Employment Type *</label>
                            <select id="employment_type" class="form-control" required onchange="toggleEmployerFields()">
                                <option value="">Select type</option>
                                <option value="salaried">Salaried</option>
                                <option value="self_employed">Self Employed / Freelancer</option>
                                <option value="business">Business Owner</option>
                                <option value="retired">Retired</option>
                                <option value="student">Student</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group" id="employer-field">
                            <label class="form-label">Employer / Company Name</label>
                            <input type="text" id="employer_name" class="form-control" placeholder="Company name">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Occupation / Designation</label>
                            <input type="text" id="occupation" class="form-control" placeholder="e.g., Software Engineer">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Years of Work Experience *</label>
                            <input type="number" id="years_of_experience" class="form-control" placeholder="Years" min="0" max="50" required>
                        </div>
                    </div>

                    <hr style="border-color: var(--border); margin: 1.5rem 0;">
                    <h4 style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Financial Details</h4>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Monthly Income (₹) *</label>
                            <input type="number" id="monthly_income" class="form-control" placeholder="Net take-home salary" min="0" required oninput="updateDTI()">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Additional Monthly Income (₹)</label>
                            <input type="number" id="additional_income" class="form-control" placeholder="Rent, freelance, etc." min="0" value="0" oninput="updateDTI()">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Monthly Expenses (₹) *</label>
                            <input type="number" id="monthly_expenses" class="form-control" placeholder="Housing, utilities, etc." min="0" required oninput="updateDTI()">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Existing Monthly EMI (₹)</label>
                            <input type="number" id="existing_emi" class="form-control" placeholder="Total ongoing EMIs" min="0" value="0" oninput="updateDTI()">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Number of Existing Active Loans</label>
                        <input type="number" id="existing_loans_count" class="form-control" placeholder="0" min="0" max="20" value="0">
                    </div>

                    <!-- Live DTI Preview -->
                    <div id="dtiPreview" style="background: var(--bg-glass); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1rem; margin-top: 0.5rem; display: none;">
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.75rem; font-weight: 600; text-transform: uppercase;">Live Financial Snapshot</p>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; text-align: center;">
                            <div><div id="dtiValue" style="font-size: 1.5rem; font-weight: 800;">0%</div><div style="font-size: 0.7rem; color: var(--text-muted);">Debt-to-Income</div></div>
                            <div><div id="disposableValue" style="font-size: 1.5rem; font-weight: 800;">₹0</div><div style="font-size: 0.7rem; color: var(--text-muted);">Disposable Income</div></div>
                            <div><div id="savingsRate" style="font-size: 1.5rem; font-weight: 800;">0%</div><div style="font-size: 0.7rem; color: var(--text-muted);">Savings Rate</div></div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-outline" onclick="prevStep(2)">← Back</button>
                    <button type="button" class="btn btn-primary" onclick="nextStep(2)">Next: Loan Details →</button>
                </div>
            </div>
        </div>

        <!-- STEP 3: Loan Details -->
        <div class="step-panel hidden" id="panel3">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">💰 Loan Details</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Loan Type *</label>
                            <select id="loan_type_id" class="form-control" required onchange="updateLoanLimits()">
                                <option value="">Select loan type</option>
                                <?php foreach ($loanTypes as $lt): ?>
                                <option value="<?= $lt['id'] ?>" 
                                        data-min="<?= $lt['min_amount'] ?>" 
                                        data-max="<?= $lt['max_amount'] ?>"
                                        data-min-tenure="<?= $lt['min_tenure'] ?>"
                                        data-max-tenure="<?= $lt['max_tenure'] ?>"
                                        data-rate-min="<?= $lt['interest_rate_min'] ?>"
                                        data-rate-max="<?= $lt['interest_rate_max'] ?>">
                                    <?= htmlspecialchars($lt['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Requested Amount (₹) *</label>
                            <input type="number" id="requested_amount" class="form-control" placeholder="Enter amount" min="10000" required oninput="calculateEMIPreview()">
                            <span class="form-text" id="amountRange"></span>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Repayment Tenure *</label>
                            <select id="requested_tenure" class="form-control" required onchange="calculateEMIPreview()">
                                <option value="">Select tenure</option>
                            </select>
                            <span class="form-text" id="tenureRange"></span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Interest Rate Range</label>
                            <input type="text" id="interest_range" class="form-control" readonly style="color: var(--text-muted);" placeholder="Select loan type first">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Loan Purpose *</label>
                        <textarea id="loan_purpose" class="form-control" placeholder="Describe the purpose of this loan in detail (min. 30 characters)" rows="3" required minlength="30"></textarea>
                    </div>

                    <!-- EMI Preview -->
                    <div id="emiPreview" style="background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(6,182,212,0.08)); border: 1px solid rgba(99,102,241,0.2); border-radius: var(--radius-md); padding: 1.5rem; margin-top: 0.5rem; display: none;">
                        <p style="font-size: 0.8rem; font-weight: 600; color: var(--primary-light); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem;">📊 Estimated EMI Breakdown</p>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; text-align: center;">
                            <div><div id="emiValue" style="font-size: 1.5rem; font-weight: 800; color: var(--primary-light);">₹0</div><div style="font-size: 0.7rem; color: var(--text-muted);">Monthly EMI</div></div>
                            <div><div id="totalInterest" style="font-size: 1.5rem; font-weight: 800; color: var(--warning);">₹0</div><div style="font-size: 0.7rem; color: var(--text-muted);">Total Interest</div></div>
                            <div><div id="totalPayable" style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary);">₹0</div><div style="font-size: 0.7rem; color: var(--text-muted);">Total Payable</div></div>
                            <div><div id="processingFee" style="font-size: 1.5rem; font-weight: 800; color: var(--accent);">~1%</div><div style="font-size: 0.7rem; color: var(--text-muted);">Processing Fee</div></div>
                        </div>
                        <p style="font-size: 0.7rem; color: var(--text-dim); margin-top: 0.75rem; text-align: center;">* Indicative figures using mid-range interest rate. Actual rates subject to credit assessment.</p>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-outline" onclick="prevStep(3)">← Back</button>
                    <button type="button" class="btn btn-primary" onclick="nextStep(3)">Next: Upload Documents →</button>
                </div>
            </div>
        </div>

        <!-- STEP 4: Document Upload -->
        <div class="step-panel hidden" id="panel4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📄 Document Upload</h3>
                    <span class="badge badge-warning">⚠️ Please upload clear, legible documents</span>
                </div>
                <div class="card-body">
                    <div id="docTypesContainer">
                        <p style="color: var(--text-muted); text-align: center; padding: 2rem;">
                            <span style="font-size: 2rem;">⏳</span><br>
                            Save the application first to enable document upload...
                        </p>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-outline" onclick="prevStep(4)">← Back</button>
                    <button type="button" class="btn btn-primary" onclick="nextStep(4)">Next: Review & Submit →</button>
                </div>
            </div>
        </div>

        <!-- STEP 5: Review & Submit -->
        <div class="step-panel hidden" id="panel5">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">✅ Review & Submit</h3>
                </div>
                <div class="card-body" id="reviewSummary">
                    <!-- Summary rendered by JS -->
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-outline" onclick="prevStep(5)">← Back</button>
                    <button type="button" class="btn btn-success btn-lg" onclick="submitApplication()" id="submitBtn">
                        Submit Application 🚀
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let currentStep = 1;
let savedApplicationId = null;
let savedApplicationStrId = null;

function updateStep(step) {
    document.querySelectorAll('.step-panel').forEach(p => p.classList.add('hidden'));
    document.getElementById('panel' + step).classList.remove('hidden');
    document.querySelectorAll('.step').forEach((s, i) => {
        s.classList.remove('active', 'completed');
        if (i + 1 < step) s.classList.add('completed');
        else if (i + 1 === step) s.classList.add('active');
    });
    document.querySelector('.step.active .step-number').textContent = step < 5 ? step : '✓';
    currentStep = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function nextStep(step) {
    if (!validateStep(step)) return;
    if (step === 3) {
        await saveApplication();
        if (!savedApplicationId) return;
        loadDocumentTypes();
    }
    if (step === 4) {
        renderReviewSummary();
    }
    updateStep(step + 1);
}

function prevStep(step) { updateStep(step - 1); }

function validateStep(step) {
    if (step === 1) {
        const fields = ['full_name','date_of_birth','gender','marital_status','address_line1','city','state','pincode','pan_number','aadhaar_number'];
        for (const f of fields) {
            if (!document.getElementById(f).value.trim()) {
                showToast('Please fill all required fields in Personal Details', 'warning');
                document.getElementById(f).focus();
                return false;
            }
        }
        const pan = document.getElementById('pan_number').value.toUpperCase();
        if (!/^[A-Z]{5}[0-9]{4}[A-Z]$/.test(pan)) {
            showToast('Invalid PAN format. Example: ABCDE1234F', 'warning');
            return false;
        }
    }
    if (step === 2) {
        if (!document.getElementById('employment_type').value) {
            showToast('Please select employment type', 'warning'); return false;
        }
        if (!document.getElementById('monthly_income').value || parseFloat(document.getElementById('monthly_income').value) <= 0) {
            showToast('Monthly income is required', 'warning'); return false;
        }
    }
    if (step === 3) {
        if (!document.getElementById('loan_type_id').value || !document.getElementById('requested_amount').value || !document.getElementById('requested_tenure').value) {
            showToast('Please fill all loan details', 'warning'); return false;
        }
        if (document.getElementById('loan_purpose').value.length < 30) {
            showToast('Loan purpose must be at least 30 characters', 'warning'); return false;
        }
    }
    return true;
}

async function saveApplication() {
    const data = new FormData(document.getElementById('applicationForm'));
    data.append('action', savedApplicationId ? 'update' : 'create');
    if (savedApplicationId) data.append('id', savedApplicationId);

    const fields = ['full_name','date_of_birth','gender','marital_status','address_line1','address_line2','city','state','pincode','pan_number','aadhaar_number','employment_type','employer_name','occupation','years_of_experience','monthly_income','additional_income','monthly_expenses','existing_emi','existing_loans_count','requested_amount','requested_tenure','loan_purpose','loan_type_id'];
    fields.forEach(f => {
        const el = document.getElementById(f);
        if (el) data.set(f, el.value);
    });
    data.set('pan_number', document.getElementById('pan_number').value.toUpperCase());

    const res = await fetch('api/applications.php', { method: 'POST', body: data });
    const json = await res.json();

    if (json.success) {
        savedApplicationId = json.id;
        savedApplicationStrId = json.application_id;
        showToast('Application saved! ID: ' + json.application_id, 'success');
    } else {
        showToast('Failed to save: ' + json.message, 'error');
        savedApplicationId = null;
    }
}

async function loadDocumentTypes() {
    const container = document.getElementById('docTypesContainer');
    const res = await fetch('api/documents.php?action=get_types');
    const data = await res.json();
    if (!data.success) return;

    const docsRes = await fetch('api/documents.php?action=list&application_id=' + savedApplicationId);
    const docsData = await docsRes.json();
    const uploaded = docsData.documents || [];

    container.innerHTML = '<div class="doc-grid">' + data.types.map(type => {
        const doc = uploaded.find(d => d.document_type_id == type.id);
        return `
        <div class="doc-card" id="docCard-${type.id}">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                <div class="doc-icon" style="${type.category === 'identity' ? 'color: var(--primary-light)' : type.category === 'income' ? 'color: var(--success)' : 'color: var(--accent)'}">
                    ${type.category === 'identity' ? '🪪' : type.category === 'income' ? '💵' : type.category === 'bank_statement' ? '🏦' : '📄'}
                </div>
                ${type.is_mandatory ? '<span class="badge badge-error" style="font-size:0.65rem;">Required</span>' : '<span class="badge badge-muted" style="font-size:0.65rem;">Optional</span>'}
            </div>
            <div class="doc-name">${type.name}</div>
            <div class="doc-meta" style="margin-bottom:0.75rem;">${type.description || ''}</div>
            ${doc ? `
            <div style="padding:0.625rem; background: var(--success-bg); border-radius: var(--radius-sm); border: 1px solid rgba(16,185,129,0.2); margin-bottom:0.75rem;">
                <div style="font-size:0.75rem; color: var(--success-light); font-weight:600;">✅ ${doc.file_name}</div>
                <div style="font-size:0.68rem; color: var(--text-muted);">${(doc.file_size/1024).toFixed(1)} KB · ${formatDate(doc.uploaded_at)}</div>
            </div>
            ` : ''}
            <input type="file" id="file-${type.id}" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="display:none;" onchange="uploadDoc(${type.id}, this)">
            <button type="button" class="btn btn-outline btn-sm btn-block" onclick="document.getElementById('file-${type.id}').click()">
                ${doc ? '🔄 Replace' : '📤 Upload'}
            </button>
            <div id="uploadProgress-${type.id}" class="hidden" style="margin-top:0.5rem;">
                <div class="progress-bar"><div class="fill" style="width:0%" id="bar-${type.id}"></div></div>
            </div>
        </div>`;
    }).join('') + '</div>';
}

async function uploadDoc(typeId, input) {
    if (!input.files[0]) return;
    const file = input.files[0];
    if (file.size > 10 * 1024 * 1024) { showToast('File too large! Max 10MB', 'error'); return; }

    const progress = document.getElementById('uploadProgress-' + typeId);
    const bar = document.getElementById('bar-' + typeId);
    progress.classList.remove('hidden');

    let pct = 0;
    const interval = setInterval(() => { pct = Math.min(pct + 15, 80); bar.style.width = pct + '%'; }, 150);

    const fd = new FormData();
    fd.append('action', 'upload');
    fd.append('application_id', savedApplicationId);
    fd.append('document_type_id', typeId);
    fd.append('document', file);

    const res = await fetch('api/documents.php', { method: 'POST', body: fd });
    const data = await res.json();

    clearInterval(interval);
    bar.style.width = '100%';

    setTimeout(() => {
        progress.classList.add('hidden');
        bar.style.width = '0';
        if (data.success) {
            showToast('✅ ' + file.name + ' uploaded', 'success');
            loadDocumentTypes(); // refresh
        } else {
            showToast('Upload failed: ' + data.message, 'error');
        }
    }, 400);
}

function renderReviewSummary() {
    const vals = {};
    ['full_name','date_of_birth','gender','marital_status','city','state','pincode','pan_number','employment_type','employer_name','occupation','monthly_income','additional_income','monthly_expenses','existing_emi','requested_amount','requested_tenure','loan_purpose'].forEach(f => {
        const el = document.getElementById(f);
        vals[f] = el ? el.value : '';
    });
    const loanTypeEl = document.getElementById('loan_type_id');
    const loanTypeName = loanTypeEl ? loanTypeEl.options[loanTypeEl.selectedIndex]?.text : '';

    document.getElementById('reviewSummary').innerHTML = `
    <div class="alert alert-info mb-3">
        <strong>Application ID: ${savedApplicationStrId || 'Will be assigned on save'}</strong> — Review your details before submitting.
    </div>
    <div class="grid-2" style="gap:1.5rem;">
        <div>
            <div class="detail-section">
                <h3>👤 Personal Information</h3>
                <div class="detail-grid">
                    <div class="detail-item"><div class="label">Name</div><div class="value">${escHtml(vals.full_name)}</div></div>
                    <div class="detail-item"><div class="label">DOB</div><div class="value">${vals.date_of_birth}</div></div>
                    <div class="detail-item"><div class="label">Gender</div><div class="value">${vals.gender}</div></div>
                    <div class="detail-item"><div class="label">Location</div><div class="value">${vals.city}, ${vals.state}</div></div>
                    <div class="detail-item"><div class="label">PAN</div><div class="value font-mono">${vals.pan_number}</div></div>
                </div>
            </div>
            <div class="detail-section">
                <h3>💼 Employment</h3>
                <div class="detail-grid">
                    <div class="detail-item"><div class="label">Type</div><div class="value">${vals.employment_type}</div></div>
                    <div class="detail-item"><div class="label">Employer</div><div class="value">${vals.employer_name || 'N/A'}</div></div>
                    <div class="detail-item"><div class="label">Monthly Income</div><div class="value">₹${formatAmount(vals.monthly_income)}</div></div>
                    <div class="detail-item"><div class="label">Monthly EMI</div><div class="value">₹${formatAmount(vals.existing_emi)}</div></div>
                </div>
            </div>
        </div>
        <div>
            <div class="detail-section">
                <h3>💰 Loan Request</h3>
                <div class="detail-grid">
                    <div class="detail-item"><div class="label">Type</div><div class="value">${loanTypeName}</div></div>
                    <div class="detail-item"><div class="label">Amount</div><div class="value" style="color:var(--primary-light); font-size:1.125rem;">₹${formatAmount(vals.requested_amount)}</div></div>
                    <div class="detail-item"><div class="label">Tenure</div><div class="value">${vals.requested_tenure} months</div></div>
                    <div class="detail-item"><div class="label">Estimated EMI</div><div class="value" style="color:var(--success);">${document.getElementById('emiValue')?.textContent || 'N/A'}</div></div>
                </div>
                <div class="detail-item mt-2" style="margin-top:0.75rem;">
                    <div class="label">Purpose</div>
                    <div class="value" style="font-size:0.875rem;">${escHtml(vals.loan_purpose)}</div>
                </div>
            </div>
            <div class="alert alert-warning" style="margin-top:1rem;">
                <strong>⚠️ Declaration:</strong> I confirm all information provided is accurate and complete. I authorize the lender to verify any information and access my credit bureau records.
            </div>
        </div>
    </div>`;
}

async function submitApplication() {
    if (!savedApplicationId) { showToast('Please complete all steps first', 'error'); return; }
    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading');
    btn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'submit');
    fd.append('id', savedApplicationId);

    const res = await fetch('api/applications.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
        showToast('🎉 Application submitted! ID: ' + savedApplicationStrId, 'success');
        setTimeout(() => navigateTo('my-applications'), 1500);
    } else {
        showToast('Failed: ' + data.message, 'error');
        btn.classList.remove('loading');
        btn.disabled = false;
    }
}

function toggleEmployerFields() {
    const type = document.getElementById('employment_type').value;
    const ef = document.getElementById('employer-field');
    ef.style.opacity = ['retired','student'].includes(type) ? '0.4' : '1';
}

function updateDTI() {
    const income = parseFloat(document.getElementById('monthly_income').value || 0) + parseFloat(document.getElementById('additional_income').value || 0);
    const emi = parseFloat(document.getElementById('existing_emi').value || 0);
    const expenses = parseFloat(document.getElementById('monthly_expenses').value || 0);

    if (income <= 0) { document.getElementById('dtiPreview').style.display = 'none'; return; }

    const dti = ((emi / income) * 100).toFixed(1);
    const disposable = income - emi - expenses;
    const savings = ((Math.max(0, disposable) / income) * 100).toFixed(1);

    document.getElementById('dtiPreview').style.display = 'block';
    document.getElementById('dtiValue').textContent = dti + '%';
    document.getElementById('dtiValue').style.color = dti > 50 ? 'var(--error)' : dti > 35 ? 'var(--warning)' : 'var(--success)';
    document.getElementById('disposableValue').textContent = '₹' + formatAmount(disposable);
    document.getElementById('savingsRate').textContent = savings + '%';
}

function updateLoanLimits() {
    const sel = document.getElementById('loan_type_id');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;

    const min = opt.dataset.min, max = opt.dataset.max;
    const minT = parseInt(opt.dataset.minTenure), maxT = parseInt(opt.dataset.maxTenure);
    const rMin = opt.dataset.rateMin, rMax = opt.dataset.rateMax;

    document.getElementById('amountRange').textContent = `Range: ₹${formatAmount(min)} – ₹${formatAmount(max)}`;
    document.getElementById('interest_range').value = rMin + '% – ' + rMax + '% p.a.';

    const tenureSel = document.getElementById('requested_tenure');
    tenureSel.innerHTML = '<option value="">Select tenure</option>';
    const options = [6,12,18,24,36,48,60,72,84,96,120,180,240,300,360];
    options.filter(t => t >= minT && t <= maxT).forEach(t => {
        const yr = t >= 12 ? ` (${(t/12).toFixed(t%12===0?0:1)} yr)` : '';
        tenureSel.innerHTML += `<option value="${t}">${t} months${yr}</option>`;
    });
    document.getElementById('tenureRange').textContent = `${minT} – ${maxT} months`;
    calculateEMIPreview();
}

function calculateEMIPreview() {
    const amount = parseFloat(document.getElementById('requested_amount').value || 0);
    const tenure = parseInt(document.getElementById('requested_tenure').value || 0);
    const sel = document.getElementById('loan_type_id');
    const opt = sel.options[sel.selectedIndex];

    if (!amount || !tenure || !opt.value) { document.getElementById('emiPreview').style.display = 'none'; return; }

    const rate = (parseFloat(opt.dataset.rateMin) + parseFloat(opt.dataset.rateMax)) / 2;
    const monthly = rate / 100 / 12;
    const emi = monthly > 0 ? (amount * monthly * Math.pow(1+monthly, tenure)) / (Math.pow(1+monthly, tenure) - 1) : amount / tenure;
    const total = emi * tenure;
    const interest = total - amount;

    document.getElementById('emiPreview').style.display = 'block';
    document.getElementById('emiValue').textContent = '₹' + formatAmount(Math.round(emi));
    document.getElementById('totalInterest').textContent = '₹' + formatAmount(Math.round(interest));
    document.getElementById('totalPayable').textContent = '₹' + formatAmount(Math.round(total));
    document.getElementById('processingFee').textContent = '₹' + formatAmount(Math.round(amount * 0.01));
}
</script>
