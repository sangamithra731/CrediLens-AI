<?php
/**
 * Application Detail — Full view of one loan application
 */
requireLogin();
$appStrId = $_GET['app_id'] ?? '';
if (empty($appStrId)) { echo '<div class="alert alert-error">No application specified.</div>'; return; }
$role = $_SESSION['user_role'];
?>
<div class="fade-in">
    <div class="flex items-center gap-2 mb-3">
        <button class="btn btn-ghost btn-sm" onclick="history.back()">← Back</button>
        <span style="color:var(--text-muted);">Application</span>
        <span class="font-mono" style="color:var(--primary-light); font-weight:700;" id="appIdDisplay"><?= htmlspecialchars($appStrId) ?></span>
        <div id="appStatusBadge" style="margin-left:0.5rem;"></div>
        <div style="margin-left:auto; display:flex; gap:0.5rem;" id="appActions"></div>
    </div>

    <div id="appDetailContent">
        <div class="card"><div class="card-body text-center" style="padding:3rem;"><div style="font-size:2rem;">⏳</div><p class="text-muted">Loading application...</p></div></div>
    </div>
</div>

<script>
(async function() {
    // Find application ID from string ID
    const res = await fetch(`api/applications.php?action=list&search=${encodeURIComponent('<?= htmlspecialchars($appStrId) ?>')}`);
    const listData = await res.json();
    if (!listData.success || !listData.applications.length) {
        document.getElementById('appDetailContent').innerHTML = '<div class="alert alert-error">Application not found.</div>';
        return;
    }
    const appId = listData.applications[0].id;

    // Load full details
    const detailRes = await fetch('api/applications.php?action=get&id=' + appId);
    const data = await detailRes.json();

    if (!data.success) {
        document.getElementById('appDetailContent').innerHTML = '<div class="alert alert-error">Failed to load application details.</div>';
        return;
    }

    const app = data.application;
    const docs = data.documents || [];
    const risk = data.risk_assessment;
    const riskFactors = data.risk_factors || [];
    const reviews = data.reviews || [];
    const clarifications = data.clarifications || [];
    const timeline = data.timeline || [];
    const complianceChecks = data.compliance_checks || [];
    const emiSchedule = data.emi_schedule || [];
    const loanOffer = data.loan_offer;
    const role = '<?= $role ?>';

    document.getElementById('appStatusBadge').innerHTML = renderStatusBadge(app.status);

    // Build action buttons based on role and status
    let actions = '';
    if (role !== 'applicant' && ['submitted','under_review','document_verification'].includes(app.status)) {
        actions += `<button class="btn btn-primary btn-sm" onclick="runRiskFromDetail(${appId})">🎯 Assess Risk</button>`;
    }
    if (role !== 'applicant' && app.status === 'risk_assessment') {
        actions += `<button class="btn btn-primary btn-sm" onclick="navigateTo('review-queue')">⚖️ Review</button>`;
    }
    if (role === 'applicant' && app.status === 'clarification_required') {
        actions += `<button class="btn btn-warning btn-sm" onclick="scrollToSection('clarifications')">💬 Respond to Clarification</button>`;
    }
    if (role !== 'applicant') {
        actions += `<button class="btn btn-outline btn-sm" onclick="changeStatus(${appId})">🔄 Update Status</button>`;
    }
    document.getElementById('appActions').innerHTML = actions;

    // Main content
    document.getElementById('appDetailContent').innerHTML = `
    <div class="grid-sidebar">
        <div>
            <div class="tabs mb-3">
                <button class="tab active" onclick="switchDetailTab('tab-personal', this)">👤 Personal</button>
                <button class="tab" onclick="switchDetailTab('tab-financial', this)">💼 Financial</button>
                <button class="tab" onclick="switchDetailTab('tab-docs', this)">📄 Documents</button>
                <button class="tab" onclick="switchDetailTab('tab-risk', this)">🎯 Risk</button>
                <button class="tab" onclick="switchDetailTab('tab-reviews', this)">⚖️ Reviews</button>
                ${emiSchedule.length ? '<button class="tab" onclick="switchDetailTab(\'tab-emi\', this)">📅 EMI</button>' : ''}
            </div>

            <!-- Personal -->
            <div class="tab-content active" id="tab-personal">
                <div class="detail-section"><h3>👤 Personal Information</h3>
                <div class="detail-grid">
                    <div class="detail-item"><div class="label">Full Name</div><div class="value">${escHtml(app.full_name)}</div></div>
                    <div class="detail-item"><div class="label">Date of Birth</div><div class="value">${app.date_of_birth || 'N/A'}</div></div>
                    <div class="detail-item"><div class="label">Gender</div><div class="value">${app.gender?.replace('_',' ') || 'N/A'}</div></div>
                    <div class="detail-item"><div class="label">Marital Status</div><div class="value">${app.marital_status || 'N/A'}</div></div>
                    <div class="detail-item"><div class="label">PAN Number</div><div class="value font-mono">${app.pan_number || 'N/A'}</div></div>
                    <div class="detail-item"><div class="label">Aadhaar</div><div class="value font-mono">${app.aadhaar_number ? '****-****-' + app.aadhaar_number.slice(-4) : 'N/A'}</div></div>
                </div></div>
                <div class="detail-section"><h3>📍 Address</h3>
                <div class="detail-item">
                    <div class="label">Full Address</div>
                    <div class="value">${[app.address_line1, app.address_line2, app.city, app.state, app.pincode].filter(Boolean).join(', ')}</div>
                </div></div>
                <div class="detail-section"><h3>💰 Loan Request</h3>
                <div class="detail-grid">
                    <div class="detail-item"><div class="label">Loan Type</div><div class="value">${app.loan_type_name}</div></div>
                    <div class="detail-item"><div class="label">Amount</div><div class="value" style="color:var(--primary-light);font-size:1.25rem;font-weight:800;">₹${formatAmount(app.requested_amount)}</div></div>
                    <div class="detail-item"><div class="label">Tenure</div><div class="value">${app.requested_tenure} months</div></div>
                    <div class="detail-item"><div class="label">Rate Range</div><div class="value">${app.interest_rate_min}% – ${app.interest_rate_max}%</div></div>
                </div>
                <div class="detail-item" style="margin-top:0.75rem;"><div class="label">Purpose</div><div class="value">${escHtml(app.loan_purpose || 'N/A')}</div></div></div>
            </div>

            <!-- Financial -->
            <div class="tab-content" id="tab-financial">
                <div class="detail-section"><h3>💼 Employment</h3>
                <div class="detail-grid">
                    <div class="detail-item"><div class="label">Employment Type</div><div class="value">${app.employment_type?.replace('_',' ') || 'N/A'}</div></div>
                    <div class="detail-item"><div class="label">Employer</div><div class="value">${app.employer_name || 'N/A'}</div></div>
                    <div class="detail-item"><div class="label">Occupation</div><div class="value">${app.occupation || 'N/A'}</div></div>
                    <div class="detail-item"><div class="label">Experience</div><div class="value">${app.years_of_experience} years</div></div>
                </div></div>
                <div class="detail-section"><h3>💵 Financial Overview</h3>
                <div class="detail-grid">
                    <div class="detail-item"><div class="label">Monthly Income</div><div class="value" style="color:var(--success);">₹${formatAmount(app.monthly_income)}</div></div>
                    <div class="detail-item"><div class="label">Additional Income</div><div class="value">₹${formatAmount(app.additional_income)}</div></div>
                    <div class="detail-item"><div class="label">Monthly Expenses</div><div class="value" style="color:var(--warning);">₹${formatAmount(app.monthly_expenses)}</div></div>
                    <div class="detail-item"><div class="label">Existing EMI</div><div class="value" style="color:var(--error);">₹${formatAmount(app.existing_emi)}</div></div>
                    <div class="detail-item"><div class="label">Existing Loans</div><div class="value">${app.existing_loans_count}</div></div>
                    <div class="detail-item"><div class="label">Disposable Income</div><div class="value" style="color:var(--primary-light);">₹${formatAmount((parseFloat(app.monthly_income)+parseFloat(app.additional_income||0)) - parseFloat(app.monthly_expenses||0) - parseFloat(app.existing_emi||0))}</div></div>
                </div></div>
                ${loanOffer ? `<div class="detail-section"><h3>📋 Loan Offer</h3>
                <div class="detail-grid">
                    <div class="detail-item"><div class="label">Approved Amount</div><div class="value" style="color:var(--success);font-size:1.125rem;font-weight:800;">₹${formatAmount(loanOffer.approved_amount)}</div></div>
                    <div class="detail-item"><div class="label">Interest Rate</div><div class="value">${loanOffer.interest_rate}% p.a.</div></div>
                    <div class="detail-item"><div class="label">Monthly EMI</div><div class="value" style="color:var(--primary-light);">₹${formatAmount(loanOffer.emi_amount)}</div></div>
                    <div class="detail-item"><div class="label">Offer Status</div><div class="value"><span class="badge badge-${loanOffer.offer_status==='accepted'?'success':'warning'}">${loanOffer.offer_status}</span></div></div>
                </div></div>` : ''}
            </div>

            <!-- Documents -->
            <div class="tab-content" id="tab-docs">
                ${docs.length === 0 ? '<div class="empty-state"><div class="icon">📄</div><h3>No documents uploaded</h3></div>' :
                '<div class="doc-grid">' + docs.map(d => `
                <div class="doc-card">
                    <div style="display:flex;gap:0.5rem;align-items:center;margin-bottom:0.75rem;">
                        <span style="font-size:1.5rem;">${d.mime_type?.includes('pdf')?'📕':'🖼️'}</span>
                        <div style="flex:1;min-width:0;"><div class="doc-name">${escHtml(d.document_type_name)}</div><div class="doc-meta">${(d.file_size/1024).toFixed(1)} KB</div></div>
                        <span class="badge badge-${d.validation_status==='valid'?'success':d.validation_status==='mismatch'?'error':'warning'}">${d.validation_status||'pending'}</span>
                    </div>
                    ${d.ocr_status === 'completed' && d.extracted_data ? (() => {
                        try { const f = JSON.parse(d.extracted_data); return '<div style="background:var(--bg-glass);border-radius:var(--radius-sm);padding:0.5rem;font-size:0.75rem;margin-bottom:0.75rem;">' + Object.entries(f).slice(0,4).map(([k,v])=>`<div><span style="color:var(--text-muted)">${k}:</span> <strong>${v}</strong></div>`).join('') + '</div>'; }
                        catch(e) { return ''; }
                    })() : `<div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.75rem;">OCR: ${d.ocr_status||'pending'}</div>`}
                    <div style="display:flex;gap:0.5rem;">
                        <a href="api/documents.php?action=download&id=${d.id}" target="_blank" class="btn btn-outline btn-sm flex-1">View</a>
                        ${role !== 'applicant' ? `<button class="btn btn-primary btn-sm" onclick="analyzeDoc(${d.id}, ${appId})">🤖 AI</button>` : ''}
                    </div>
                </div>`).join('') + '</div>'}
            </div>

            <!-- Risk -->
            <div class="tab-content" id="tab-risk">
                ${risk ? `
                <div style="text-align:center;margin-bottom:1.5rem;">
                    <div style="font-size:4rem;font-weight:900;color:${risk.risk_category==='low'?'var(--success)':risk.risk_category==='medium'?'var(--warning)':risk.risk_category==='high'?'#f97316':'var(--error)'};">${Math.round(risk.risk_score)}<span style="font-size:1.5rem;color:var(--text-muted);">/100</span></div>
                    <div style="text-transform:capitalize;color:var(--text-secondary);font-weight:600;">${risk.risk_category?.replace('_',' ')} Risk</div>
                    <div class="badge badge-${risk.ai_recommendation==='approve'?'success':risk.ai_recommendation==='reject'?'error':'warning'}" style="margin-top:0.5rem;font-size:0.9rem;padding:0.5rem 1.25rem;">AI: ${risk.ai_recommendation?.charAt(0).toUpperCase()+risk.ai_recommendation?.slice(1)}</div>
                </div>
                <div class="detail-grid mb-3">
                    <div class="detail-item"><div class="label">DTI Ratio</div><div class="value" style="color:${risk.dti_ratio>50?'var(--error)':risk.dti_ratio>35?'var(--warning)':'var(--success)'};">${risk.dti_ratio}%</div></div>
                    <div class="detail-item"><div class="label">Credit Score</div><div class="value">${risk.credit_score||'N/A'}</div></div>
                    <div class="detail-item"><div class="label">Disposable Income</div><div class="value">₹${formatAmount(risk.disposable_income)}/mo</div></div>
                    <div class="detail-item"><div class="label">Compliance</div><div class="value"><span class="badge badge-${risk.compliance_status==='compliant'?'success':'error'}">${risk.compliance_status}</span></div></div>
                </div>
                <h4 style="font-size:0.875rem;font-weight:700;margin-bottom:1rem;">Risk Factors</h4>
                ${riskFactors.map(f=>`
                <div class="risk-factor-item ${f.impact}">
                    <div class="factor-icon">${f.impact==='positive'?'✅':'⚠️'}</div>
                    <div style="flex:1;"><div class="factor-name">${escHtml(f.factor_name)}</div><div class="factor-detail">${escHtml(f.explanation)}</div><div style="font-size:0.68rem;color:var(--text-dim);">Source: ${f.evidence_source}</div></div>
                    <div class="factor-value" style="color:${f.impact==='positive'?'var(--success)':'var(--error)'};">${escHtml(String(f.factor_value))}</div>
                </div>`).join('')}
                ${complianceChecks.length ? `<h4 style="font-size:0.875rem;font-weight:700;margin:1rem 0;">Policy Compliance</h4>` + complianceChecks.map(c=>`<div class="check-item ${c.status}"><div class="check-icon">${c.status==='passed'?'✓':'✗'}</div><div style="flex:1;font-size:0.875rem;font-weight:600;">${escHtml(c.check_name)}</div><div style="font-size:0.75rem;color:var(--text-muted);">${escHtml(c.details)}</div></div>`).join('') : ''}
                ${role !== 'applicant' ? `<button class="btn btn-primary btn-sm mt-2" onclick="runRiskFromDetail(${appId})">🔄 Re-run Assessment</button>` : ''}
                ` : `<div class="empty-state"><div class="icon">🎯</div><h3>No Risk Assessment</h3>${role!=='applicant'?'<button class="btn btn-primary" onclick="runRiskFromDetail('+appId+')">Run Assessment</button>':''}</div>`}
            </div>

            <!-- Reviews -->
            <div class="tab-content" id="tab-reviews" id="clarifications">
                <h4 style="font-size:0.875rem;font-weight:700;margin-bottom:1rem;">Decision History</h4>
                ${reviews.length === 0 ? '<div class="empty-state"><p>No reviews yet</p></div>' :
                reviews.map(r=>`<div style="padding:1rem;background:var(--bg-glass);border-radius:var(--radius-md);border:1px solid var(--border);margin-bottom:0.75rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                        <div><strong>${escHtml(r.reviewer_name)}</strong> <span style="color:var(--text-muted);font-size:0.8rem;">(${r.reviewer_role?.replace('_',' ')})</span></div>
                        <span class="badge badge-${r.decision==='approve'?'success':r.decision==='reject'?'error':'warning'}">${r.decision}</span>
                    </div>
                    <p style="font-size:0.875rem;color:var(--text-secondary);">${escHtml(r.decision_reason)}</p>
                    <div style="font-size:0.75rem;color:var(--text-dim);margin-top:0.5rem;">${formatDate(r.reviewed_at)}</div>
                </div>`).join('')}

                ${clarifications.length > 0 ? `<h4 style="font-size:0.875rem;font-weight:700;margin-top:1.5rem;margin-bottom:1rem;">Clarification Requests</h4>
                ${clarifications.map(c=>`<div style="padding:1rem;background:var(--warning-bg);border:1px solid rgba(245,158,11,0.2);border-radius:var(--radius-md);margin-bottom:0.75rem;">
                    <div style="font-weight:700;margin-bottom:0.5rem;">${escHtml(c.subject)}</div>
                    <p style="font-size:0.875rem;color:var(--text-secondary);">${escHtml(c.message)}</p>
                    ${c.response ? `<div style="margin-top:0.75rem;padding:0.75rem;background:var(--bg-glass);border-radius:var(--radius-sm);"><strong style="font-size:0.75rem;color:var(--text-muted);">RESPONSE:</strong><br>${escHtml(c.response)}</div>` : 
                    (role==='applicant'?`<div style="margin-top:0.75rem;"><textarea id="clarResp-${c.id}" class="form-control" rows="2" placeholder="Your response..."></textarea><button class="btn btn-primary btn-sm" style="margin-top:0.5rem;" onclick="respondClarification(${c.id})">Submit Response</button></div>`:'<span class="badge badge-warning">Awaiting Response</span>')}
                    <div style="font-size:0.75rem;color:var(--text-dim);margin-top:0.5rem;">Due: ${c.due_date}</div>
                </div>`).join('')}` : ''}
            </div>

            <!-- EMI Schedule -->
            ${emiSchedule.length ? `
            <div class="tab-content" id="tab-emi">
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem;text-align:center;">
                    <div style="background:var(--bg-glass);border-radius:var(--radius-md);padding:1rem;"><div style="font-size:1.5rem;font-weight:800;color:var(--success);">${emiSchedule.filter(e=>e.payment_status==='paid').length}/${emiSchedule.length}</div><div style="font-size:0.75rem;color:var(--text-muted);">Paid</div></div>
                    <div style="background:var(--bg-glass);border-radius:var(--radius-md);padding:1rem;"><div style="font-size:1.5rem;font-weight:800;color:var(--warning);">${emiSchedule.filter(e=>e.payment_status==='overdue').length}</div><div style="font-size:0.75rem;color:var(--text-muted);">Overdue</div></div>
                    <div style="background:var(--bg-glass);border-radius:var(--radius-md);padding:1rem;"><div style="font-size:1.5rem;font-weight:800;color:var(--info);">${emiSchedule.filter(e=>e.payment_status==='upcoming').length}</div><div style="font-size:0.75rem;color:var(--text-muted);">Upcoming</div></div>
                </div>
                <div class="table-container"><table>
                    <thead><tr><th>#</th><th>Due Date</th><th>EMI</th><th>Principal</th><th>Interest</th><th>Status</th></tr></thead>
                    <tbody>${emiSchedule.map(e=>`<tr>
                        <td style="font-weight:600;">${e.installment_number}</td>
                        <td style="font-size:0.8rem;">${e.due_date}</td>
                        <td>₹${formatAmount(e.emi_amount)}</td>
                        <td style="color:var(--primary-light);">₹${formatAmount(e.principal_component)}</td>
                        <td style="color:var(--warning);">₹${formatAmount(e.interest_component)}</td>
                        <td><span class="badge badge-${e.payment_status==='paid'?'success':e.payment_status==='overdue'?'error':e.payment_status==='due'?'warning':'muted'}">${e.payment_status}</span></td>
                    </tr>`).join('')}</tbody>
                </table></div>
            </div>` : ''}
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Status + Actions -->
            <div class="card mb-3">
                <div class="card-body">
                    <div style="font-size:0.75rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.75rem;">Application Info</div>
                    <div style="display:grid;gap:0.75rem;">
                        <div><div style="font-size:0.7rem;color:var(--text-dim);">App ID</div><div class="font-mono" style="font-weight:700;color:var(--primary-light);">${app.application_id}</div></div>
                        <div><div style="font-size:0.7rem;color:var(--text-dim);">Status</div>${renderStatusBadge(app.status)}</div>
                        <div><div style="font-size:0.7rem;color:var(--text-dim);">Loan Type</div><div style="font-weight:600;">${app.loan_type_name}</div></div>
                        <div><div style="font-size:0.7rem;color:var(--text-dim);">Amount</div><div style="font-weight:800;color:var(--primary-light);">₹${formatAmount(app.requested_amount)}</div></div>
                        <div><div style="font-size:0.7rem;color:var(--text-dim);">Submitted</div><div style="font-size:0.8rem;">${app.submitted_at ? formatDate(app.submitted_at) : 'Not yet submitted'}</div></div>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card">
                <div class="card-header"><h3 class="card-title">📅 Timeline</h3></div>
                <div class="card-body">
                    ${timeline.length === 0 ? '<p class="text-muted text-sm">No events yet</p>' :
                    `<div class="timeline">${timeline.map(t => {
                        const iconMap = { created: 'purple', submitted: 'blue', document_uploaded: 'blue', ai_processing: 'blue', risk_assessed: 'yellow', decision: t.event_title?.includes('Approved')?'green':'red', disbursed: 'green', status_change: 'blue' };
                        const color = iconMap[t.event_type] || 'purple';
                        return `<div class="timeline-item">
                            <div class="timeline-dot ${color==='green'?'success':color==='red'?'error':color==='yellow'?'warning':''}"></div>
                            <div class="timeline-content">
                                <h4>${escHtml(t.event_title)}</h4>
                                ${t.event_description ? `<p>${escHtml(t.event_description)}</p>` : ''}
                                <div class="time">${t.performer_name ? t.performer_name + ' · ' : ''}${formatDate(t.created_at)}</div>
                            </div>
                        </div>`;
                    }).join('')}</div>`}
                </div>
            </div>
        </div>
    </div>`;
})();

function switchDetailTab(id, btn) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(id)?.classList.add('active');
}

async function runRiskFromDetail(appId) {
    showToast('🎯 Running risk assessment...', 'info');
    const fd = new FormData(); fd.append('action','assess'); fd.append('application_id', appId);
    const res = await fetch('api/risk.php',{method:'POST',body:fd});
    const data = await res.json();
    showToast(data.success ? '✅ Assessment complete! Refreshing...' : '❌ '+data.message, data.success?'success':'error');
    if (data.success) setTimeout(() => location.reload(), 1500);
}

async function analyzeDoc(docId, appId) {
    showToast('🤖 Processing document with AI...', 'info');
    const fd = new FormData(); fd.append('action','process_ai'); fd.append('document_id',docId); fd.append('application_id',appId);
    const res = await fetch('api/documents.php',{method:'POST',body:fd});
    const data = await res.json();
    showToast(data.success?'✅ Analysis complete':'❌ '+data.message, data.success?'success':'error');
    if (data.success) setTimeout(() => location.reload(), 1500);
}

async function respondClarification(clarId) {
    const response = document.getElementById('clarResp-'+clarId)?.value;
    if (!response?.trim()) { showToast('Please enter a response','warning'); return; }
    const fd = new FormData(); fd.append('action','respond_clarification'); fd.append('clarification_id',clarId); fd.append('response',response);
    const res = await fetch('api/review.php',{method:'POST',body:fd});
    const data = await res.json();
    showToast(data.message, data.success?'success':'error');
    if (data.success) setTimeout(() => location.reload(), 1500);
}

async function changeStatus(appId) {
    const status = prompt('Enter new status (under_review, risk_assessment, compliance_check, approved, rejected, disbursed, closed):');
    if (!status) return;
    const fd = new FormData(); fd.append('action','update_status'); fd.append('id',appId); fd.append('status',status);
    const res = await fetch('api/applications.php',{method:'POST',body:fd});
    const data = await res.json();
    showToast(data.message, data.success?'success':'error');
    if (data.success) setTimeout(() => location.reload(), 1500);
}
</script>
