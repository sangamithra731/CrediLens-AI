<?php
/**
 * Module 5: Review Queue — Applications pending human decision
 */
requireLogin();
if (!in_array($_SESSION['user_role'], ['loan_officer','risk_analyst','compliance_officer','admin'])) {
    echo '<div class="alert alert-error">Access denied.</div>'; return;
}
?>
<div class="fade-in">
    <div class="filter-bar">
        <div class="search-input">
            <input type="text" id="qSearchInput" placeholder="Search by ID, name..." oninput="debounce(loadQueue, 400)()">
        </div>
        <select class="filter-select" id="qStatusFilter" onchange="loadQueue()">
            <option value="submitted">Submitted</option>
            <option value="under_review">Under Review</option>
            <option value="document_verification">Document Verification</option>
            <option value="risk_assessment">Risk Assessment</option>
            <option value="clarification_required">Clarification Required</option>
            <option value="compliance_check">Compliance Check</option>
            <option value="">All Active</option>
        </select>
        <button class="btn btn-outline btn-sm" onclick="loadQueue()">🔄 Refresh</button>
    </div>

    <!-- Queue Summary Cards -->
    <div id="queueSummary" class="stats-grid mb-3"></div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">⚖️ Review Queue</h3>
            <span id="qTotal" style="font-size:0.8rem;color:var(--text-muted);"></span>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>App ID</th>
                        <th>Applicant</th>
                        <th>Loan Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Risk Score</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="queueBody">
                    <tr><td colspan="8" class="text-center text-muted" style="padding:2rem;">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Decision Modal -->
<div class="modal-overlay" id="decisionModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>📝 Review Decision</h3>
            <button class="modal-close" onclick="closeModal('decisionModal')">✕</button>
        </div>
        <div class="modal-body" id="decisionModalBody">Loading...</div>
    </div>
</div>

<script>
async function loadQueue() {
    const search = document.getElementById('qSearchInput').value;
    const status = document.getElementById('qStatusFilter').value;
    const url = `api/applications.php?action=list&search=${encodeURIComponent(search)}&status=${status}&page=1`;

    document.getElementById('queueBody').innerHTML = '<tr><td colspan="8" class="text-center text-muted" style="padding:2rem;">⏳ Loading...</td></tr>';

    const [qRes, statsRes] = await Promise.all([fetch(url), fetch('api/applications.php?action=get_stats')]);
    const qData = await qRes.json();
    const statsData = await statsRes.json();

    const s = statsData.stats || {};
    document.getElementById('queueSummary').innerHTML = `
        <div class="stat-card"><div class="stat-header"><div class="stat-icon">📨</div></div><div class="stat-value">${s.submitted || 0}</div><div class="stat-label">Submitted</div></div>
        <div class="stat-card accent"><div class="stat-header"><div class="stat-icon">🔍</div></div><div class="stat-value">${s.under_review || 0}</div><div class="stat-label">Under Review</div></div>
        <div class="stat-card warning"><div class="stat-header"><div class="stat-icon">💬</div></div><div class="stat-value">${s.clarification || 0}</div><div class="stat-label">Clarification</div></div>
        <div class="stat-card success"><div class="stat-header"><div class="stat-icon">🎯</div></div><div class="stat-value">${s.risk_assessment || 0}</div><div class="stat-label">Risk Assessment</div></div>
    `;

    if (!qData.success || qData.applications.length === 0) {
        document.getElementById('queueBody').innerHTML = '<tr><td colspan="8"><div class="empty-state"><div class="icon">✅</div><h3>Queue is clear!</h3><p>No applications pending review</p></div></td></tr>';
        document.getElementById('qTotal').textContent = '0 pending';
        return;
    }

    document.getElementById('qTotal').textContent = qData.total + ' applications';

    document.getElementById('queueBody').innerHTML = qData.applications.map(a => {
        const age = Math.floor((Date.now() - new Date(a.submitted_at || a.created_at)) / 86400000);
        const ageClass = age > 7 ? 'error' : age > 3 ? 'warning' : 'success';
        return `<tr style="cursor:pointer;" onclick="openDecisionModal('${a.application_id}', ${a.id})">
            <td><span class="font-mono" style="color:var(--primary-light);font-weight:600;">${a.application_id}</span></td>
            <td><div style="font-weight:600;">${escHtml(a.full_name || a.applicant_name)}</div><div style="font-size:0.75rem;color:var(--text-muted);">${a.employment_type || ''}</div></td>
            <td>${escHtml(a.loan_type_name)}</td>
            <td><strong>₹${formatAmount(a.requested_amount)}</strong></td>
            <td>${renderStatusBadge(a.status)}</td>
            <td><span class="badge badge-${ageClass}">${age === 0 ? 'Today' : age + 'd ago'}</span></td>
            <td id="risk-${a.id}"><span class="text-muted text-sm">—</span></td>
            <td onclick="event.stopPropagation();">
                <div style="display:flex;gap:0.25rem;">
                    <button class="btn btn-primary btn-sm" onclick="openDecisionModal('${a.application_id}', ${a.id})">Review</button>
                    <button class="btn btn-outline btn-sm" onclick="viewApplication('${a.application_id}')">View</button>
                </div>
            </td>
        </tr>`;
    }).join('');

    // Load risk scores async
    qData.applications.forEach(async a => {
        try {
            const r = await fetch('api/risk.php?action=get&application_id=' + a.id);
            const rd = await r.json();
            const cell = document.getElementById('risk-' + a.id);
            if (cell && rd.success) {
                const score = rd.assessment.risk_score;
                const cat = rd.assessment.risk_category;
                const colors = { low: 'var(--success)', medium: 'var(--warning)', high: '#f97316', very_high: 'var(--error)' };
                cell.innerHTML = `<div style="font-weight:800;color:${colors[cat]||'var(--text-muted)'};">${Math.round(score)}/100</div><div style="font-size:0.68rem;color:var(--text-muted);text-transform:capitalize;">${cat?.replace('_',' ')}</div>`;
            }
        } catch (e) {}
    });
}

async function openDecisionModal(appStrId, appId) {
    openModal('decisionModal');
    document.getElementById('decisionModalBody').innerHTML = '<div class="text-center" style="padding:2rem;"><div style="font-size:2rem;">⏳</div><p class="text-muted">Loading application...</p></div>';

    const res = await fetch('api/applications.php?action=get&id=' + appId);
    const data = await res.json();

    if (!data.success) { document.getElementById('decisionModalBody').innerHTML = '<div class="alert alert-error">Failed to load application</div>'; return; }

    const app = data.application;
    const risk = data.risk_assessment;
    const docs = data.documents || [];

    const recColors = { approve: 'success', review: 'warning', reject: 'error' };
    const recText = { approve: '✅ Recommend Approval', review: '⚠️ Needs Review', reject: '❌ Recommend Rejection' };

    document.getElementById('decisionModalBody').innerHTML = `
    <div class="tabs">
        <button class="tab active" onclick="switchModalTab('dt-overview', this)">Overview</button>
        <button class="tab" onclick="switchModalTab('dt-docs', this)">Documents (${docs.length})</button>
        <button class="tab" onclick="switchModalTab('dt-risk', this)">Risk Assessment</button>
        <button class="tab" onclick="switchModalTab('dt-decision', this)">Make Decision</button>
    </div>

    <!-- Overview Tab -->
    <div class="tab-content active" id="dt-overview">
        <div class="grid-2" style="gap:1rem;">
            <div>
                <div class="detail-section">
                    <h3>👤 Applicant</h3>
                    <div class="detail-grid">
                        <div class="detail-item"><div class="label">Name</div><div class="value">${escHtml(app.full_name)}</div></div>
                        <div class="detail-item"><div class="label">PAN</div><div class="value font-mono">${app.pan_number || 'N/A'}</div></div>
                        <div class="detail-item"><div class="label">Employment</div><div class="value">${app.employment_type?.replace('_',' ') || 'N/A'}</div></div>
                        <div class="detail-item"><div class="label">Employer</div><div class="value">${app.employer_name || 'N/A'}</div></div>
                        <div class="detail-item"><div class="label">Monthly Income</div><div class="value">₹${formatAmount(app.monthly_income)}</div></div>
                        <div class="detail-item"><div class="label">Existing EMI</div><div class="value">₹${formatAmount(app.existing_emi)}</div></div>
                    </div>
                </div>
            </div>
            <div>
                <div class="detail-section">
                    <h3>💰 Loan Request</h3>
                    <div class="detail-grid">
                        <div class="detail-item"><div class="label">Type</div><div class="value">${app.loan_type_name}</div></div>
                        <div class="detail-item"><div class="label">Amount</div><div class="value" style="color:var(--primary-light);font-size:1.25rem;font-weight:800;">₹${formatAmount(app.requested_amount)}</div></div>
                        <div class="detail-item"><div class="label">Tenure</div><div class="value">${app.requested_tenure} months</div></div>
                        <div class="detail-item"><div class="label">Status</div><div class="value">${renderStatusBadge(app.status)}</div></div>
                    </div>
                    <div style="margin-top:1rem; padding:0.75rem; background:var(--bg-glass); border-radius:var(--radius-sm); font-size:0.875rem;">
                        <strong>Purpose:</strong> ${escHtml(app.loan_purpose || 'Not specified')}
                    </div>
                </div>
                ${risk ? `
                <div style="background:linear-gradient(135deg,${risk.risk_category==='low'?'rgba(16,185,129,0.1)':risk.risk_category==='medium'?'rgba(245,158,11,0.1)':'rgba(239,68,68,0.1)'},transparent); border:1px solid var(--border); border-radius:var(--radius-md); padding:1rem; text-align:center;">
                    <div style="font-size:2.5rem;font-weight:800;color:${risk.risk_category==='low'?'var(--success)':risk.risk_category==='medium'?'var(--warning)':'var(--error)'};">${Math.round(risk.risk_score)}/100</div>
                    <div style="text-transform:capitalize; color:var(--text-secondary);">${risk.risk_category?.replace('_',' ')} Risk</div>
                    <div class="badge ${risk.ai_recommendation==='approve'?'badge-success':risk.ai_recommendation==='reject'?'badge-error':'badge-warning'}" style="margin-top:0.5rem;">${recText[risk.ai_recommendation]||'Review'}</div>
                </div>` : '<div class="alert alert-warning">No risk assessment yet. Run assessment first.</div>'}
            </div>
        </div>
    </div>

    <!-- Documents Tab -->
    <div class="tab-content" id="dt-docs">
        ${docs.length === 0 ? '<div class="empty-state"><div class="icon">📄</div><h3>No documents uploaded</h3></div>' : 
        '<div class="doc-grid">' + docs.map(d => `
        <div class="doc-card">
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;">
                <span style="font-size:1.25rem;">${d.mime_type?.includes('pdf')?'📕':'🖼️'}</span>
                <div>
                    <div class="doc-name">${escHtml(d.document_type_name)}</div>
                    <div class="doc-meta">${(d.file_size/1024).toFixed(1)} KB</div>
                </div>
                <span class="badge badge-${d.validation_status==='valid'?'success':d.validation_status==='mismatch'?'error':'warning'}" style="margin-left:auto;">${d.validation_status||'pending'}</span>
            </div>
            ${d.ocr_status === 'completed' && d.extracted_data ? (() => {
                try {
                    const fields = JSON.parse(d.extracted_data);
                    return '<div style="background:var(--bg-glass);border-radius:var(--radius-sm);padding:0.75rem;font-size:0.75rem;">' +
                        Object.entries(fields).slice(0,4).map(([k,v]) => `<div style="margin-bottom:0.25rem;"><span style="color:var(--text-muted)">${k}:</span> <strong>${v}</strong></div>`).join('') +
                        '</div>';
                } catch(e) { return ''; }
            })() : `<div style="font-size:0.75rem;color:var(--text-muted);">OCR: ${d.ocr_status||'pending'}</div>`}
            <div class="doc-actions">
                <a href="api/documents.php?action=download&id=${d.id}" target="_blank" class="btn btn-outline btn-sm">👁 View</a>
                <button class="btn btn-primary btn-sm" onclick="processDocAI(${d.id}, ${appId})">🤖 Analyze</button>
            </div>
        </div>`).join('') + '</div>'}
    </div>

    <!-- Risk Tab -->
    <div class="tab-content" id="dt-risk">
        ${risk ? `
        <div class="grid-2" style="gap:1rem;margin-bottom:1rem;">
            <div class="detail-item"><div class="label">Verified Income</div><div class="value">₹${formatAmount(risk.verified_monthly_income)}/mo</div></div>
            <div class="detail-item"><div class="label">DTI Ratio</div><div class="value" style="color:${risk.dti_ratio>50?'var(--error)':risk.dti_ratio>35?'var(--warning)':'var(--success)'};">${risk.dti_ratio}%</div></div>
            <div class="detail-item"><div class="label">Disposable Income</div><div class="value">₹${formatAmount(risk.disposable_income)}/mo</div></div>
            <div class="detail-item"><div class="label">Credit Score</div><div class="value">${risk.credit_score||'N/A'}</div></div>
        </div>
        <button class="btn btn-primary btn-sm mb-2" onclick="runRiskAssessment(${appId})">🔄 Re-run Assessment</button>
        <div id="riskFactorsContainer">
            <p class="text-muted text-sm">Loading risk factors...</p>
        </div>` : `
        <div class="empty-state">
            <div class="icon">🎯</div>
            <h3>No Risk Assessment</h3>
            <p>Run the risk assessment engine to evaluate this application</p>
            <button class="btn btn-primary" onclick="runRiskAssessment(${appId})">Run Assessment</button>
        </div>`}
    </div>

    <!-- Decision Tab -->
    <div class="tab-content" id="dt-decision">
        <div class="form-group">
            <label class="form-label">Decision *</label>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.75rem;margin-bottom:1rem;">
                ${[['approve','✅ Approve','success'],['clarify','💬 Request Info','warning'],['escalate','🔺 Escalate','info'],['reject','❌ Reject','error']].map(([val,label,type]) => `
                <label style="cursor:pointer;">
                    <input type="radio" name="reviewDecision" value="${val}" style="display:none;" onchange="updateDecisionUI('${val}')">
                    <div class="btn btn-outline btn-block" id="dec-${val}" style="justify-content:center;">${label}</div>
                </label>`).join('')}
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Reason / Notes *</label>
            <textarea id="decisionReason" class="form-control" rows="3" placeholder="Explain your decision in detail..."></textarea>
        </div>
        <div id="offerSection" class="hidden">
            <hr style="border-color:var(--border);margin:1rem 0;">
            <h4 style="font-size:0.875rem;font-weight:700;margin-bottom:1rem;color:var(--text-primary);">💰 Generate Loan Offer</h4>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Approved Amount (₹)</label>
                    <input type="number" id="approvedAmount" class="form-control" value="${app.requested_amount}" oninput="previewOffer()">
                </div>
                <div class="form-group">
                    <label class="form-label">Interest Rate (%)</label>
                    <input type="number" id="approvedRate" class="form-control" value="${((parseFloat(app.interest_rate_min||10)+parseFloat(app.interest_rate_max||18))/2).toFixed(2)}" step="0.25" oninput="previewOffer()">
                </div>
                <div class="form-group">
                    <label class="form-label">Tenure (months)</label>
                    <input type="number" id="approvedTenure" class="form-control" value="${app.requested_tenure}" oninput="previewOffer()">
                </div>
            </div>
            <div id="offerPreview" style="background:var(--bg-glass);border-radius:var(--radius-sm);padding:1rem;text-align:center;" class="hidden">
                <span style="font-size:0.8rem;color:var(--text-muted);">EMI:</span>
                <span id="offerEmi" style="font-weight:800;color:var(--success);font-size:1.25rem;">₹0</span>
            </div>
        </div>
        <div class="modal-footer" style="border:none;padding:0;margin-top:1rem;">
            <button class="btn btn-outline" onclick="closeModal('decisionModal')">Cancel</button>
            <button class="btn btn-primary" onclick="submitDecision(${appId})">Submit Decision</button>
        </div>
    </div>`;

    // Load risk factors
    if (risk) {
        fetch('api/risk.php?action=get&application_id=' + appId).then(r => r.json()).then(rd => {
            const container = document.getElementById('riskFactorsContainer');
            if (!container) return;
            const factors = rd.risk_factors || [];
            container.innerHTML = factors.map(f => `
                <div class="risk-factor-item ${f.impact}">
                    <div class="factor-icon">${f.impact==='positive'?'✅':f.impact==='negative'?'⚠️':'ℹ️'}</div>
                    <div style="flex:1;"><div class="factor-name">${escHtml(f.factor_name)}</div><div class="factor-detail">${escHtml(f.explanation)}</div></div>
                    <div class="factor-value" style="color:${f.impact==='positive'?'var(--success)':'var(--error)'};">${escHtml(String(f.factor_value))}</div>
                </div>`).join('') || '<p class="text-muted">No risk factors found.</p>';
        });
    }
}

function switchModalTab(id, btn) {
    document.querySelectorAll('#decisionModalBody .tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('#decisionModalBody .tab-content').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(id)?.classList.add('active');
}

function updateDecisionUI(val) {
    ['approve','clarify','escalate','reject'].forEach(v => {
        const el = document.getElementById('dec-' + v);
        if (el) el.style.background = v === val ? 'var(--primary)' : '';
        if (el) el.style.color = v === val ? 'white' : '';
    });
    document.getElementById('offerSection').classList.toggle('hidden', val !== 'approve');
    if (val === 'approve') previewOffer();
}

function previewOffer() {
    const a = parseFloat(document.getElementById('approvedAmount')?.value||0);
    const r = parseFloat(document.getElementById('approvedRate')?.value||12);
    const t = parseInt(document.getElementById('approvedTenure')?.value||12);
    if (!a||!t) return;
    const mr = r/100/12;
    const emi = mr>0?(a*mr*Math.pow(1+mr,t))/(Math.pow(1+mr,t)-1):a/t;
    const el = document.getElementById('offerEmi');
    if (el) { el.textContent = '₹'+formatAmount(Math.round(emi)); document.getElementById('offerPreview').classList.remove('hidden'); }
}

async function processDocAI(docId, appId) {
    showToast('🤖 AI processing document...', 'info');
    const fd = new FormData();
    fd.append('action','process_ai');
    fd.append('document_id', docId);
    fd.append('application_id', appId);
    const res = await fetch('api/documents.php', {method:'POST', body:fd});
    const data = await res.json();
    showToast(data.success ? '✅ Document analyzed successfully' : '❌ '+data.message, data.success ? 'success' : 'error');
    if (data.success) openDecisionModal('', appId);
}

async function runRiskAssessment(appId) {
    showToast('🎯 Running risk assessment...', 'info');
    const fd = new FormData();
    fd.append('action','assess');
    fd.append('application_id', appId);
    const res = await fetch('api/risk.php', {method:'POST', body:fd});
    const data = await res.json();
    showToast(data.success ? '✅ Risk assessment complete' : '❌ '+data.message, data.success ? 'success' : 'error');
    if (data.success) { closeModal('decisionModal'); loadQueue(); }
}

async function submitDecision(appId) {
    const decision = document.querySelector('input[name="reviewDecision"]:checked')?.value;
    const reason = document.getElementById('decisionReason').value;
    if (!decision) { showToast('Please select a decision', 'warning'); return; }
    if (!reason.trim()) { showToast('Please provide a reason', 'warning'); return; }

    const fd = new FormData();
    fd.append('action','submit_review');
    fd.append('application_id', appId);
    fd.append('review_type','final_review');
    fd.append('decision', decision);
    fd.append('reason', reason);

    // If approval, generate offer too
    if (decision === 'approve') {
        const offerFd = new FormData();
        offerFd.append('action','generate_offer');
        offerFd.append('application_id', appId);
        offerFd.append('approved_amount', document.getElementById('approvedAmount').value);
        offerFd.append('interest_rate', document.getElementById('approvedRate').value);
        offerFd.append('tenure_months', document.getElementById('approvedTenure').value);
        offerFd.append('processing_fee', document.getElementById('approvedAmount').value * 0.01);
        await fetch('api/review.php', {method:'POST', body:offerFd});
    }

    const res = await fetch('api/review.php', {method:'POST', body:fd});
    const data = await res.json();
    showToast(data.success ? '✅ Decision submitted' : '❌ '+data.message, data.success?'success':'error');
    if (data.success) { closeModal('decisionModal'); loadQueue(); }
}

loadQueue();
</script>
