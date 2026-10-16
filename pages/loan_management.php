<?php
requireLogin();
?>
<div class="fade-in">
    <div class="filter-bar">
        <div class="search-input">
            <input type="text" id="lmSearch" placeholder="Search by application ID..." oninput="debounce(loadLoanApps,400)()">
        </div>
        <select class="filter-select" id="lmFilter" onchange="loadLoanApps()">
            <option value="approved">Approved</option>
            <option value="disbursed">Disbursed</option>
            <option value="closed">Closed</option>
            <option value="">All</option>
        </select>
    </div>
    <div class="card">
        <div class="card-header"><h3 class="card-title">💰 Loan Management</h3></div>
        <div class="table-container">
            <table>
                <thead><tr><th>App ID</th><th>Applicant</th><th>Amount</th><th>Status</th><th>Offer</th><th>Agreement</th><th>Disbursement</th><th>Actions</th></tr></thead>
                <tbody id="lmBody"><tr><td colspan="8" class="text-center text-muted" style="padding:2rem;">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Loan Action Modal -->
<div class="modal-overlay" id="loanActionModal">
    <div class="modal modal-lg">
        <div class="modal-header"><h3 id="loanModalTitle">Loan Actions</h3><button class="modal-close" onclick="closeModal('loanActionModal')">✕</button></div>
        <div class="modal-body" id="loanModalBody">Loading...</div>
    </div>
</div>

<script>
async function loadLoanApps() {
    const search = document.getElementById('lmSearch').value;
    const status = document.getElementById('lmFilter').value;
    const res = await fetch(`api/applications.php?action=list&search=${encodeURIComponent(search)}&status=${status}`);
    const data = await res.json();
    const role = '<?= $_SESSION['user_role'] ?>';

    if (!data.success || !data.applications.length) {
        document.getElementById('lmBody').innerHTML = '<tr><td colspan="8"><div class="empty-state"><div class="icon">💰</div><h3>No loans found</h3></div></td></tr>';
        return;
    }

    // Fetch loan details for each app in parallel
    const details = await Promise.all(data.applications.map(a =>
        fetch('api/review.php?action=get_loan_details&application_id=' + a.id).then(r => r.json())
    ));

    document.getElementById('lmBody').innerHTML = data.applications.map((a, i) => {
        const d = details[i];
        const offer = d.offer;
        const agreement = d.agreement;
        const disbursement = d.disbursement;
        const emi = d.emi_schedule || [];
        const paidEmi = emi.filter(e => e.payment_status === 'paid').length;

        return `<tr>
            <td><span class="font-mono" style="color:var(--primary-light);font-weight:600;">${a.application_id}</span></td>
            <td>${escHtml(a.full_name||a.applicant_name)}</td>
            <td><strong>₹${formatAmount(a.requested_amount)}</strong></td>
            <td>${renderStatusBadge(a.status)}</td>
            <td>${offer ? `<span class="badge badge-${offer.offer_status==='accepted'?'success':'info'}">${offer.offer_status}</span><br><small>₹${formatAmount(offer.emi_amount)}/mo</small>` : '<span class="text-muted text-sm">—</span>'}</td>
            <td>${agreement ? `<span class="badge badge-${agreement.e_signed?'success':'warning'}">${agreement.e_signed?'Signed':'Pending'}</span>` : '<span class="text-muted text-sm">—</span>'}</td>
            <td>${disbursement ? `<span class="badge badge-success">Disbursed</span><br><small>₹${formatAmount(disbursement.disbursement_amount)}</small>` : '<span class="text-muted text-sm">—</span>'}</td>
            <td>${renderLoanActions(a, offer, agreement, disbursement, emi, role)}</td>
        </tr>`;
    }).join('');
}

function renderLoanActions(a, offer, agreement, disbursement, emi, role) {
    const btns = [];
    if (a.status === 'approved' && !offer && role !== 'applicant') {
        btns.push(`<button class="btn btn-primary btn-sm" onclick="openLoanAction(${a.id}, 'generate_offer', '${a.application_id}')">💰 Offer</button>`);
    }
    if (offer && offer.offer_status === 'sent' && role === 'applicant') {
        btns.push(`<button class="btn btn-success btn-sm" onclick="acceptOffer(${offer.id})">✅ Accept</button>`);
    }
    if (offer && offer.offer_status === 'accepted' && !agreement && role !== 'applicant') {
        btns.push(`<button class="btn btn-primary btn-sm" onclick="createAgreement(${a.id}, ${offer.id})">📝 Agreement</button>`);
    }
    if (agreement && !agreement.e_signed && role === 'applicant') {
        btns.push(`<button class="btn btn-success btn-sm" onclick="signAgreement(${agreement.id})">✍️ Sign</button>`);
    }
    if (agreement && agreement.e_signed && !disbursement && role !== 'applicant') {
        btns.push(`<button class="btn btn-primary btn-sm" onclick="openLoanAction(${a.id}, 'disburse', '${a.application_id}', ${offer?.emi_amount||0}, ${agreement.id})">💸 Disburse</button>`);
    }
    if (disbursement && emi.length === 0 && role !== 'applicant') {
        btns.push(`<button class="btn btn-outline btn-sm" onclick="generateEMI(${a.id})">📅 EMI</button>`);
    }
    if (emi.length > 0) {
        btns.push(`<button class="btn btn-ghost btn-sm" onclick="viewEMI(${a.id}, '${a.application_id}')">📊 EMI (${emi.filter(e=>e.payment_status==='paid').length}/${emi.length})</button>`);
    }
    btns.push(`<button class="btn btn-ghost btn-sm" onclick="viewApplication('${a.application_id}')">👁</button>`);
    return '<div style="display:flex;gap:0.25rem;flex-wrap:wrap;">' + btns.join('') + '</div>';
}

async function acceptOffer(offerId) {
    const fd = new FormData(); fd.append('action','accept_offer'); fd.append('offer_id',offerId);
    const res = await fetch('api/review.php',{method:'POST',body:fd});
    const data = await res.json();
    showToast(data.message, data.success?'success':'error');
    if (data.success) loadLoanApps();
}

async function createAgreement(appId, offerId) {
    const fd = new FormData(); fd.append('action','create_agreement'); fd.append('application_id',appId); fd.append('offer_id',offerId);
    const res = await fetch('api/review.php',{method:'POST',body:fd});
    const data = await res.json();
    showToast(data.message, data.success?'success':'error');
    if (data.success) loadLoanApps();
}

async function signAgreement(agreementId) {
    if (!confirm('By clicking OK, you digitally sign this loan agreement. This action is final.')) return;
    const fd = new FormData(); fd.append('action','sign_agreement'); fd.append('agreement_id',agreementId);
    const res = await fetch('api/review.php',{method:'POST',body:fd});
    const data = await res.json();
    showToast(data.message, data.success?'success':'error');
    if (data.success) loadLoanApps();
}

async function generateEMI(appId) {
    const fd = new FormData(); fd.append('action','generate_emi_schedule'); fd.append('application_id',appId);
    const res = await fetch('api/review.php',{method:'POST',body:fd});
    const data = await res.json();
    showToast(data.message, data.success?'success':'error');
    if (data.success) loadLoanApps();
}

function openLoanAction(appId, action, appStrId, emiAmount, agreementId) {
    document.getElementById('loanModalTitle').textContent = action === 'generate_offer' ? '💰 Generate Loan Offer' : '💸 Process Disbursement';
    openModal('loanActionModal');

    if (action === 'generate_offer') {
        document.getElementById('loanModalBody').innerHTML = `
            <p class="text-muted mb-2">Configure the loan offer for <strong>${appStrId}</strong></p>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Approved Amount (₹)</label><input type="number" id="goa" class="form-control" placeholder="Amount"></div>
                <div class="form-group"><label class="form-label">Interest Rate (%)</label><input type="number" id="gor" class="form-control" value="12" step="0.25"></div>
                <div class="form-group"><label class="form-label">Tenure (months)</label><input type="number" id="got" class="form-control" value="36"></div>
            </div>
            <div class="form-group"><label class="form-label">Processing Fee (₹)</label><input type="number" id="gopf" class="form-control" value="0"></div>
            <div class="modal-footer" style="border:none;padding:1rem 0 0;">
                <button class="btn btn-outline" onclick="closeModal('loanActionModal')">Cancel</button>
                <button class="btn btn-primary" onclick="submitOffer(${appId})">Generate Offer</button>
            </div>`;
    } else {
        document.getElementById('loanModalBody').innerHTML = `
            <p class="text-muted mb-2">Record disbursement for <strong>${appStrId}</strong></p>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Disbursement Amount (₹)</label><input type="number" id="da" class="form-control" placeholder="Amount"></div>
                <div class="form-group"><label class="form-label">Bank Name</label><input type="text" id="dbn" class="form-control" placeholder="Bank name"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Account Number</label><input type="text" id="dacc" class="form-control" placeholder="Account number"></div>
                <div class="form-group"><label class="form-label">IFSC Code</label><input type="text" id="difsc" class="form-control" placeholder="IFSC code"></div>
            </div>
            <div class="alert alert-warning">⚠️ This is a simulated disbursement for demo purposes. No real funds will be transferred.</div>
            <div class="modal-footer" style="border:none;padding:1rem 0 0;">
                <button class="btn btn-outline" onclick="closeModal('loanActionModal')">Cancel</button>
                <button class="btn btn-success" onclick="submitDisbursement(${appId}, ${agreementId})">Confirm Disbursement</button>
            </div>`;
    }
}

async function submitOffer(appId) {
    const fd = new FormData();
    fd.append('action','generate_offer');
    fd.append('application_id',appId);
    fd.append('approved_amount',document.getElementById('goa').value);
    fd.append('interest_rate',document.getElementById('gor').value);
    fd.append('tenure_months',document.getElementById('got').value);
    fd.append('processing_fee',document.getElementById('gopf').value);
    const res = await fetch('api/review.php',{method:'POST',body:fd});
    const data = await res.json();
    showToast(data.message,data.success?'success':'error');
    if (data.success) { closeModal('loanActionModal'); loadLoanApps(); }
}

async function submitDisbursement(appId, agreementId) {
    const fd = new FormData();
    fd.append('action','process_disbursement');
    fd.append('application_id',appId);
    fd.append('agreement_id',agreementId);
    fd.append('amount',document.getElementById('da').value);
    fd.append('bank_name',document.getElementById('dbn').value);
    fd.append('bank_account',document.getElementById('dacc').value);
    fd.append('ifsc_code',document.getElementById('difsc').value);
    const res = await fetch('api/review.php',{method:'POST',body:fd});
    const data = await res.json();
    showToast(data.message,data.success?'success':'error');
    if (data.success) { closeModal('loanActionModal'); loadLoanApps(); }
}

function viewEMI(appId, appStrId) {
    document.getElementById('loanModalTitle').textContent = '📅 EMI Schedule — ' + appStrId;
    openModal('loanActionModal');
    document.getElementById('loanModalBody').innerHTML = '<div class="text-center" style="padding:2rem;">⏳ Loading...</div>';

    fetch('api/review.php?action=get_loan_details&application_id=' + appId).then(r=>r.json()).then(data => {
        const emi = data.emi_schedule || [];
        if (!emi.length) { document.getElementById('loanModalBody').innerHTML = '<div class="empty-state"><p>No EMI schedule generated yet.</p></div>'; return; }

        const paid = emi.filter(e=>e.payment_status==='paid').length;
        const total = emi.reduce((a,b)=>a+parseFloat(b.emi_amount),0);
        const paidAmt = emi.filter(e=>e.payment_status==='paid').reduce((a,b)=>a+parseFloat(b.paid_amount||0),0);

        document.getElementById('loanModalBody').innerHTML = `
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem;text-align:center;">
                <div style="background:var(--bg-glass);border-radius:var(--radius-md);padding:1rem;"><div style="font-size:1.5rem;font-weight:800;color:var(--success);">${paid}/${emi.length}</div><div style="font-size:0.75rem;color:var(--text-muted);">Installments Paid</div></div>
                <div style="background:var(--bg-glass);border-radius:var(--radius-md);padding:1rem;"><div style="font-size:1.5rem;font-weight:800;color:var(--primary-light);">₹${formatAmount(paidAmt)}</div><div style="font-size:0.75rem;color:var(--text-muted);">Amount Paid</div></div>
                <div style="background:var(--bg-glass);border-radius:var(--radius-md);padding:1rem;"><div style="font-size:1.5rem;font-weight:800;color:var(--text-primary);">₹${formatAmount(total-paidAmt)}</div><div style="font-size:0.75rem;color:var(--text-muted);">Balance</div></div>
            </div>
            <div class="progress-bar mb-2"><div class="fill success" style="width:${(paid/emi.length*100).toFixed(0)}%"></div></div>
            <div class="table-container"><table>
                <thead><tr><th>#</th><th>Due Date</th><th>EMI</th><th>Principal</th><th>Interest</th><th>Balance</th><th>Status</th></tr></thead>
                <tbody>${emi.slice(0,24).map(e => `<tr>
                    <td style="font-weight:600;">${e.installment_number}</td>
                    <td style="font-size:0.8rem;">${e.due_date}</td>
                    <td>₹${formatAmount(e.emi_amount)}</td>
                    <td style="color:var(--primary-light);">₹${formatAmount(e.principal_component)}</td>
                    <td style="color:var(--warning);">₹${formatAmount(e.interest_component)}</td>
                    <td>₹${formatAmount(e.outstanding_balance)}</td>
                    <td>${renderEMIStatus(e.payment_status)}</td>
                </tr>`).join('')}</tbody>
            </table></div>`;
    });
}

function renderEMIStatus(s) {
    const map = {paid:'success',upcoming:'muted',due:'warning',overdue:'error',partial:'info'};
    return `<span class="badge badge-${map[s]||'muted'}">${s}</span>`;
}

loadLoanApps();
</script>
