<?php
/**
 * Risk Assessment Page — Risk analyst dashboard for batch assessments
 */
requireLogin();
if (!in_array($_SESSION['user_role'], ['risk_analyst', 'loan_officer', 'admin'])) {
    echo '<div class="alert alert-error">Access denied.</div>'; return;
}
?>
<div class="fade-in">
    <!-- Stats Row -->
    <div class="stats-grid mb-3" id="riskStats">
        <div class="stat-card skeleton" style="height:120px;"></div>
        <div class="stat-card skeleton" style="height:120px;"></div>
        <div class="stat-card skeleton" style="height:120px;"></div>
        <div class="stat-card skeleton" style="height:120px;"></div>
    </div>

    <!-- Risk Assessment Table -->
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">
                <span class="icon" style="width:18px;height:18px;color:var(--primary);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </span>
                Risk Assessment Engine
            </h3>
            <button class="btn btn-primary btn-sm" onclick="batchAssess()" id="batchBtn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13,2 3,14 12,14 11,22 21,10 12,10 13,2"/></svg>
                Batch Assess All Pending
            </button>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Application</th>
                        <th>Applicant</th>
                        <th>Amount</th>
                        <th>Monthly Income</th>
                        <th>DTI Ratio</th>
                        <th>Risk Score</th>
                        <th>Category</th>
                        <th>Recommendation</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="riskTableBody">
                    <tr><td colspan="9" class="text-center text-muted" style="padding:3rem;">
                        <div style="display:flex;flex-direction:column;align-items:center;gap:0.75rem;">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.3;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            Loading risk data...
                        </div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span class="icon" style="width:16px;height:16px;color:var(--text-muted);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    </span>
                    Risk Score Distribution
                </h3>
            </div>
            <div class="card-body"><canvas id="riskDistChart" height="250"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span class="icon" style="width:16px;height:16px;color:var(--warning);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    </span>
                    Risk Factors Summary
                </h3>
            </div>
            <div class="card-body" id="commonFactors">
                <div class="skeleton" style="height:200px;border-radius:var(--radius-md);"></div>
            </div>
        </div>
    </div>
</div>

<!-- Risk Detail Modal -->
<div class="modal-overlay" id="riskDetailModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 id="riskDetailTitle" style="display:flex;align-items:center;gap:0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Risk Assessment Detail
            </h3>
            <button class="modal-close" onclick="closeModal('riskDetailModal')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body" id="riskDetailBody">Loading...</div>
    </div>
</div>

<script>
const RISK_SVG = {
    shield:  `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>`,
    clock:   `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>`,
    check:   `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20,6 9,17 4,12"/></svg>`,
    alert:   `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    search:  `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>`,
    refresh: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="23,4 23,10 17,10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>`,
    play:    `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polygon points="5,3 19,12 5,21 5,3"/></svg>`,
};

async function loadRiskData() {
    const res = await fetch('api/applications.php?action=list&page=1');
    const data = await res.json();
    if (!data.success) return;

    let assessed = 0, pending = 0, low = 0, medium = 0, high = 0, veryHigh = 0;
    const rows = [];
    const scores = [];

    for (const app of data.applications) {
        try {
            const rRes = await fetch('api/risk.php?action=get&application_id=' + app.id);
            const rData = await rRes.json();

            if (rData.success && rData.assessment) {
                assessed++;
                const r = rData.assessment;
                scores.push(r.risk_score);
                if (r.risk_category === 'low') low++;
                else if (r.risk_category === 'medium') medium++;
                else if (r.risk_category === 'high') high++;
                else veryHigh++;
                rows.push({ app, risk: r, factors: rData.risk_factors || [] });
            } else if (['submitted','under_review','document_verification','risk_assessment'].includes(app.status)) {
                pending++;
                rows.push({ app, risk: null, factors: [] });
            }
        } catch (e) {}
    }

    // Stats
    document.getElementById('riskStats').innerHTML = `
        <div class="stat-card">
            <div class="stat-header"><div class="stat-icon icon" style="color:var(--primary);">${RISK_SVG.shield}</div></div>
            <div class="stat-value">${assessed}</div>
            <div class="stat-label">Assessed</div>
        </div>
        <div class="stat-card accent">
            <div class="stat-header"><div class="stat-icon icon" style="color:var(--accent);">${RISK_SVG.clock}</div></div>
            <div class="stat-value">${pending}</div>
            <div class="stat-label">Pending Assessment</div>
        </div>
        <div class="stat-card success">
            <div class="stat-header"><div class="stat-icon icon" style="color:var(--success);">${RISK_SVG.check}</div></div>
            <div class="stat-value">${low}</div>
            <div class="stat-label">Low Risk</div>
        </div>
        <div class="stat-card error">
            <div class="stat-header"><div class="stat-icon icon" style="color:var(--error);">${RISK_SVG.alert}</div></div>
            <div class="stat-value">${high + veryHigh}</div>
            <div class="stat-label">High / Very High Risk</div>
        </div>
    `;

    // Table
    if (rows.length === 0) {
        document.getElementById('riskTableBody').innerHTML = `<tr><td colspan="9"><div class="empty-state">
            <div style="margin-bottom:1rem;opacity:0.4;">${RISK_SVG.shield}</div>
            <h3>No applications to assess</h3>
            <p>Applications will appear here once submitted</p>
        </div></td></tr>`;
        renderFactorsSummary([], 0);
        return;
    }

    const recBadge = { approve: 'success', review: 'warning', reject: 'error' };
    const catColors = { low: 'var(--success)', medium: 'var(--warning)', high: '#f97316', very_high: 'var(--error)' };

    document.getElementById('riskTableBody').innerHTML = rows.map(({ app, risk }) => `
        <tr>
            <td><span class="font-mono" style="color:var(--primary-light);font-weight:600;cursor:pointer;" onclick="viewApplication('${app.application_id}')">${app.application_id}</span></td>
            <td><strong>${escHtml(app.full_name || app.applicant_name || '—')}</strong></td>
            <td>₹${formatAmount(app.requested_amount)}</td>
            <td>₹${formatAmount(app.monthly_income)}/mo</td>
            <td>${risk ? `<span style="font-weight:800;color:${risk.dti_ratio>50?'var(--error)':risk.dti_ratio>35?'var(--warning)':'var(--success)'};">${risk.dti_ratio}%</span>` : '—'}</td>
            <td>${risk
                ? `<span style="font-size:1.125rem;font-weight:800;color:${catColors[risk.risk_category]||'var(--text-muted)'};">${Math.round(risk.risk_score)}</span><span style="color:var(--text-dim);font-size:0.8rem;">/100</span>`
                : '<span class="badge badge-muted">Not assessed</span>'}</td>
            <td>${risk
                ? `<span class="badge badge-${risk.risk_category==='low'?'success':risk.risk_category==='medium'?'warning':'error'}">${risk.risk_category?.replace('_',' ')}</span>`
                : '—'}</td>
            <td>${risk
                ? `<span class="badge badge-${recBadge[risk.ai_recommendation]||'muted'}">${risk.ai_recommendation||'N/A'}</span>`
                : '—'}</td>
            <td>
                <div style="display:flex;gap:0.25rem;align-items:center;">
                    ${risk ? `<button class="btn btn-ghost btn-sm" onclick="showRiskDetail(${app.id},'${app.application_id}')">${RISK_SVG.search} Detail</button>` : ''}
                    <button class="btn btn-primary btn-sm" onclick="runAssessment(${app.id})">
                        ${risk ? RISK_SVG.refresh : RISK_SVG.play} ${risk ? 'Re-assess' : 'Assess'}
                    </button>
                </div>
            </td>
        </tr>`).join('');

    // Chart
    if (typeof Chart !== 'undefined') {
        const ctx = document.getElementById('riskDistChart');
        if (ctx) {
            // Destroy existing chart if any
            if (ctx._chart) ctx._chart.destroy();
            const ch = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Low Risk', 'Medium', 'High', 'Very High'],
                    datasets: [{
                        data: [low, medium, high, veryHigh],
                        backgroundColor: ['rgba(16,185,129,0.7)','rgba(245,158,11,0.7)','rgba(249,115,22,0.7)','rgba(239,68,68,0.7)'],
                        borderRadius: 8,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { color: 'rgba(128,128,128,0.1)' }, ticks: { color: '#64748b' } },
                        y: { grid: { color: 'rgba(128,128,128,0.1)' }, ticks: { color: '#64748b', precision: 0 } }
                    }
                }
            });
            ctx._chart = ch;
        }
    }

    const allFactors = rows.flatMap(r => r.factors);
    renderFactorsSummary(allFactors, assessed);
}

function renderFactorsSummary(allFactors, assessed) {
    const negativeFactors = allFactors.filter(f => f.impact === 'negative');
    const positiveFactors = allFactors.filter(f => f.impact === 'positive');
    const factorCounts = {};
    negativeFactors.forEach(f => { factorCounts[f.factor_name] = (factorCounts[f.factor_name] || 0) + 1; });
    const sortedFactors = Object.entries(factorCounts).sort((a, b) => b[1] - a[1]).slice(0, 5);

    document.getElementById('commonFactors').innerHTML = `
        <div style="margin-bottom:1.25rem;">
            <div style="display:flex;align-items:center;gap:0.5rem;font-size:0.875rem;font-weight:700;color:var(--error-light);margin-bottom:1rem;">
                ${RISK_SVG.alert} Most Common Risk Factors
            </div>
            ${sortedFactors.length === 0
                ? `<p class="text-muted text-sm" style="padding:1rem;text-align:center;">No risk factor data yet.</p>`
                : sortedFactors.map(([name, count]) => `
                <div style="display:flex;align-items:center;gap:0.75rem;padding:0.625rem 0;border-bottom:1px solid var(--border);">
                    <div style="width:28px;height:28px;background:var(--error-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--error);">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </div>
                    <div style="flex:1;font-size:0.875rem;color:var(--text-secondary);">${escHtml(name)}</div>
                    <div style="font-weight:800;color:var(--error);font-size:0.875rem;">${count}×</div>
                </div>`).join('')}
        </div>
        <div style="padding:1rem;background:var(--success-bg);border-radius:var(--radius-md);border:1px solid rgba(16,185,129,0.15);">
            <div style="display:flex;align-items:center;gap:0.5rem;font-size:0.875rem;font-weight:700;color:var(--success);margin-bottom:0.5rem;">
                ${RISK_SVG.check} Positive Indicators
            </div>
            <p class="text-sm" style="color:var(--success-light);">Across <strong>${assessed}</strong> assessed application${assessed!==1?'s':''}, <strong>${positiveFactors.length}</strong> positive factor${positiveFactors.length!==1?'s were':' was'} identified.</p>
        </div>
    `;
}

async function runAssessment(appId) {
    showToast('Running risk assessment...', 'info');
    const fd = new FormData();
    fd.append('action', 'assess');
    fd.append('application_id', appId);
    const res = await fetch('api/risk.php', { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.success ? 'Assessment complete' : data.message, data.success ? 'success' : 'error');
    if (data.success) loadRiskData();
}

async function batchAssess() {
    const btn = document.getElementById('batchBtn');
    btn.disabled = true;
    btn.classList.add('loading');
    showToast('Starting batch assessment...', 'info');
    const res = await fetch('api/applications.php?action=list');
    const data = await res.json();
    if (!data.success) { btn.disabled = false; btn.classList.remove('loading'); return; }

    const pending = data.applications.filter(a => ['submitted','under_review','document_verification','risk_assessment'].includes(a.status));
    let done = 0;
    for (const app of pending) {
        const fd = new FormData();
        fd.append('action', 'assess');
        fd.append('application_id', app.id);
        await fetch('api/risk.php', { method: 'POST', body: fd });
        done++;
    }
    btn.disabled = false;
    btn.classList.remove('loading');
    showToast(`Batch complete: ${done} application${done!==1?'s':''} assessed`, 'success');
    loadRiskData();
}

async function showRiskDetail(appId, appStrId) {
    document.getElementById('riskDetailTitle').innerHTML = `
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Risk Detail — ${appStrId}`;
    openModal('riskDetailModal');
    document.getElementById('riskDetailBody').innerHTML = `<div class="text-center" style="padding:3rem;">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" style="animation:spin 1s linear infinite;margin-bottom:1rem;display:block;margin-left:auto;margin-right:auto;"><polyline points="23,4 23,10 17,10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        Loading...
    </div>`;

    const res = await fetch('api/risk.php?action=get&application_id=' + appId);
    const data = await res.json();

    if (!data.success) {
        document.getElementById('riskDetailBody').innerHTML = '<div class="alert alert-error">Failed to load risk data</div>';
        return;
    }

    const r = data.assessment;
    const factors = data.risk_factors || [];
    const expFactors = data.explainable_factors || [];
    const fraudChecks = data.fraud_checks || [];
    const ocrData = data.ocr_data || [];
    const catColors = { low: 'var(--success)', medium: 'var(--warning)', high: '#f97316', very_high: 'var(--error)' };
    const recBadge  = { approve: 'success', review: 'warning', reject: 'error' };

    document.getElementById('riskDetailBody').innerHTML = `
    <!-- Top KPI Banner -->
    <div style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;margin-bottom:1.5rem;">
        <div style="text-align:center;padding:1.5rem;background:linear-gradient(135deg,${r.risk_category==='low'?'rgba(16,185,129,0.1)':r.risk_category==='medium'?'rgba(245,158,11,0.1)':'rgba(239,68,68,0.1)'},transparent);border-radius:var(--radius-lg);border:1px solid var(--border);">
            <div style="font-size:3.5rem;font-weight:900;color:${catColors[r.risk_category]};">${Math.round(r.risk_score)}<span style="font-size:1.25rem;color:var(--text-muted);">/100</span></div>
            <div style="text-transform:capitalize;font-weight:700;color:var(--text-secondary);margin-bottom:0.75rem;">${r.risk_category?.replace('_',' ')} Risk</div>
            <span class="badge badge-${recBadge[r.ai_recommendation]||'muted'}" style="font-size:0.85rem;padding:0.4rem 0.8rem;">
                AI Recommendation: ${r.ai_recommendation?.charAt(0).toUpperCase()+r.ai_recommendation?.slice(1)}
            </span>
            <div style="margin-top:0.75rem;font-size:0.8rem;color:var(--text-muted);">Credit Score: <strong>${r.credit_score || '720'}</strong> / 900</div>
        </div>

        <div class="detail-grid" style="grid-template-columns:repeat(3, 1fr);align-content:center;gap:0.75rem;">
            <div class="detail-item"><div class="label">Verified Income</div><div class="value">₹${formatAmount(r.verified_monthly_income)}/mo</div></div>
            <div class="detail-item"><div class="label">DTI Ratio</div><div class="value" style="color:${r.dti_ratio>50?'var(--error)':r.dti_ratio>35?'var(--warning)':'var(--success)'};">${r.dti_ratio}%</div></div>
            <div class="detail-item"><div class="label">Disposable Income</div><div class="value">₹${formatAmount(r.disposable_income)}</div></div>
            <div class="detail-item"><div class="label">Loan-to-Income</div><div class="value">${r.loan_to_income_ratio}×</div></div>
            <div class="detail-item"><div class="label">Compliance</div><div class="value"><span class="badge badge-${r.compliance_status==='compliant'?'success':'error'}">${r.compliance_status}</span></div></div>
            <div class="detail-item"><div class="label">Fraud Alert Status</div><div class="value"><span class="badge badge-${fraudChecks.length>0?'error':'success'}">${fraudChecks.length>0 ? fraudChecks.length + ' Flags Detected' : 'Clean'}</span></div></div>
        </div>
    </div>

    <!-- Fraud Detection Section -->
    <div style="margin-bottom:1.5rem;">
        <h4 style="font-size:0.9rem;font-weight:700;margin-bottom:0.75rem;display:flex;align-items:center;gap:0.5rem;color:${fraudChecks.length>0?'var(--error)':'var(--success)'};">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Advanced Fraud & Anomaly Intelligence (${fraudChecks.length})
        </h4>
        ${fraudChecks.length === 0 ? `
            <div style="padding:0.875rem 1rem;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);border-radius:var(--radius-md);display:flex;align-items:center;gap:0.75rem;">
                <span style="color:var(--success);font-size:1.25rem;">✓</span>
                <span style="font-size:0.85rem;color:var(--success-light);">No duplicate identity records, document cryptographic reuses, or velocity anomalies detected across the platform.</span>
            </div>
        ` : `
            <div style="display:flex;flex-direction:column;gap:0.6rem;">
                ${fraudChecks.map(fc => `
                    <div style="padding:0.75rem 1rem;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);border-radius:var(--radius-md);">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.25rem;">
                            <strong style="color:var(--error);font-size:0.85rem;">⚠️ ${escHtml(fc.title)}</strong>
                            <span class="badge badge-${fc.severity==='critical'?'error':'warning'}">${fc.severity.toUpperCase()}</span>
                        </div>
                        <div style="font-size:0.8rem;color:var(--text-secondary);">${escHtml(fc.description)}</div>
                    </div>
                `).join('')}
            </div>
        `}
    </div>

    <!-- Explainable Risk Factor Breakdown -->
    <div style="margin-bottom:1.5rem;">
        <h4 style="font-size:0.9rem;font-weight:700;margin-bottom:0.75rem;display:flex;align-items:center;gap:0.5rem;color:var(--primary-light);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            Explainable AI Decision Factors (${expFactors.length || factors.length})
        </h4>
        <div style="display:flex;flex-direction:column;gap:0.5rem;">
            ${(expFactors.length > 0 ? expFactors : factors).map(f => `
                <div class="risk-factor-item ${f.impact || (f.weight > 0 ? 'positive' : 'negative')}" style="padding:0.75rem 1rem;">
                    <div class="factor-icon">
                        ${f.impact === 'positive'
                            ? `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20,6 9,17 4,12"/></svg>`
                            : `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`}
                    </div>
                    <div style="flex:1;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <div class="factor-name" style="font-weight:700;">${escHtml(f.factor_name || f.name)}</div>
                            <span class="badge badge-${f.impact==='positive'?'success':f.impact==='neutral'?'warning':'error'}" style="font-size:0.7rem;">${f.category ? f.category.toUpperCase() : 'FACTOR'}</span>
                        </div>
                        <div class="factor-detail" style="margin-top:0.25rem;">${escHtml(f.explanation || '')}</div>
                        ${f.recommendation ? `<div style="font-size:0.75rem;color:var(--accent);margin-top:0.2rem;">💡 Recommendation: ${escHtml(f.recommendation)}</div>` : ''}
                    </div>
                    <div class="factor-value" style="color:${f.impact==='positive'?'var(--success)':'var(--error)'};font-weight:800;font-size:0.95rem;">
                        ${f.score ? f.score + ' pts' : escHtml(String(f.factor_value || ''))}
                    </div>
                </div>
            `).join('')}
        </div>
    </div>

    <!-- Document OCR Intelligence -->
    ${ocrData.length > 0 ? `
    <div>
        <h4 style="font-size:0.9rem;font-weight:700;margin-bottom:0.75rem;display:flex;align-items:center;gap:0.5rem;color:var(--text-primary);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
            OCR Document Cross-Verification (${ocrData.length})
        </h4>
        <div class="table-container" style="border:1px solid var(--border);border-radius:var(--radius-md);">
            <table>
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Extracted Name</th>
                        <th>Extracted ID / Acc</th>
                        <th>Extracted Income</th>
                        <th>Confidence</th>
                        <th>Match Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${ocrData.map(od => `
                        <tr>
                            <td><strong>${escHtml(od.document_type)}</strong></td>
                            <td>${escHtml(od.extracted_name || 'N/A')}</td>
                            <td><span class="font-mono">${escHtml(od.extracted_id_number || 'N/A')}</span></td>
                            <td>${od.extracted_income ? '₹' + formatAmount(od.extracted_income) : '—'}</td>
                            <td><span style="font-weight:700;color:var(--primary-light);">${Math.round(od.match_confidence)}%</span></td>
                            <td>
                                <span class="badge badge-${od.name_match ? 'success' : 'warning'}">
                                    ${od.name_match ? 'Verified' : 'Review Required'}
                                </span>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    </div>` : ''}
    `;
}

loadRiskData();
</script>
