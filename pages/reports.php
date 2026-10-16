<?php
requireLogin();
if ($_SESSION['user_role'] === 'applicant') { echo '<div class="alert alert-error">Access denied.</div>'; return; }
?>
<div class="fade-in">
    <!-- Portfolio Health & Delinquency Tracking Banner -->
    <div class="card mb-3" style="background:linear-gradient(135deg,rgba(99,102,241,0.06),transparent);border:1px solid rgba(99,102,241,0.2);">
        <div class="card-header" style="border-bottom:1px solid rgba(99,102,241,0.15);">
            <h3 class="card-title" style="display:flex;align-items:center;gap:0.5rem;color:var(--primary-light);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Portfolio Monitoring & Risk Performance
            </h3>
            <span class="badge badge-info" id="portUpdateTag">Live Ledger Synced</span>
        </div>
        <div class="card-body">
            <div class="stats-grid mb-3" id="portfolioStats" style="margin-bottom:1.5rem;">
                <div class="stat-card skeleton" style="height:100px;"></div>
                <div class="stat-card skeleton" style="height:100px;"></div>
                <div class="stat-card skeleton" style="height:100px;"></div>
                <div class="stat-card skeleton" style="height:100px;"></div>
            </div>

            <!-- Delinquency Aging Breakdown -->
            <h4 style="font-size:0.875rem;font-weight:700;margin-bottom:0.75rem;color:var(--text-secondary);">
                📊 Overdue & Delinquency Aging Buckets (PAR / DPD)
            </h4>
            <div class="grid-4" id="agingBucketsGrid" style="gap:1rem;">
                <div class="skeleton" style="height:70px;border-radius:var(--radius-md);"></div>
                <div class="skeleton" style="height:70px;border-radius:var(--radius-md);"></div>
                <div class="skeleton" style="height:70px;border-radius:var(--radius-md);"></div>
                <div class="skeleton" style="height:70px;border-radius:var(--radius-md);"></div>
            </div>
        </div>
    </div>

    <!-- Application Lifecycle Stats Grid -->
    <div class="stats-grid mb-3" id="reportStats"></div>

    <div class="grid-2 mb-3">
        <div class="card"><div class="card-header"><h3 class="card-title">📈 Monthly Applications</h3></div><div class="card-body"><canvas id="monthlyChart" height="250"></canvas></div></div>
        <div class="card"><div class="card-header"><h3 class="card-title">🍩 Application Status</h3></div><div class="card-body"><canvas id="statusChart" height="250"></canvas></div></div>
    </div>
    <div class="grid-2 mb-3">
        <div class="card"><div class="card-header"><h3 class="card-title">⚠️ Risk Distribution</h3></div><div class="card-body"><canvas id="riskChart" height="250"></canvas></div></div>
        <div class="card"><div class="card-header"><h3 class="card-title">🏦 Loan Type Mix</h3></div><div class="card-body"><canvas id="loanTypeChart" height="250"></canvas></div></div>
    </div>
    <div class="grid-2">
        <div class="card">
            <div class="card-header"><h3 class="card-title">💸 EMI Collection Status</h3></div>
            <div class="card-body" id="emiSummary">Loading...</div>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="card-title">👤 Top Underwriters & Reviewers</h3></div>
            <div class="card-body" id="reviewersList">Loading...</div>
        </div>
    </div>
</div>
<script>
(async function() {
    // 1. Fetch Portfolio Monitoring
    try {
        const pRes = await fetch('api/admin.php?action=get_portfolio_monitoring');
        const pData = await pRes.json();
        if (pData.success && pData.portfolio) {
            const p = pData.portfolio;
            const b = p.aging_buckets || {};
            
            document.getElementById('portfolioStats').innerHTML = `
                <div class="stat-card">
                    <div class="stat-header"><div class="stat-icon" style="color:var(--primary);">🏦</div></div>
                    <div class="stat-value">₹${formatAmount(p.total_disbursed || 0)}</div>
                    <div class="stat-label">Total Disbursed</div>
                </div>
                <div class="stat-card accent">
                    <div class="stat-header"><div class="stat-icon" style="color:var(--accent);">📋</div></div>
                    <div class="stat-value">₹${formatAmount(p.total_outstanding || 0)}</div>
                    <div class="stat-label">Outstanding Principal</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-header"><div class="stat-icon" style="color:var(--success);">⚡</div></div>
                    <div class="stat-value">${p.collection_efficiency_pct}%</div>
                    <div class="stat-label">Collection Efficiency</div>
                </div>
                <div class="stat-card ${p.npa_ratio_pct > 5 ? 'error' : 'warning'}">
                    <div class="stat-header"><div class="stat-icon" style="color:${p.npa_ratio_pct > 5 ? 'var(--error)' : 'var(--warning)'};">⚠️</div></div>
                    <div class="stat-value">${p.npa_ratio_pct}%</div>
                    <div class="stat-label">NPA Ratio (90+ DPD)</div>
                </div>
            `;

            document.getElementById('agingBucketsGrid').innerHTML = `
                <div style="padding:0.75rem 1rem;background:var(--bg-glass);border:1px solid var(--border);border-radius:var(--radius-md);">
                    <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.25rem;">Current Due (0 DPD)</div>
                    <div style="font-size:1.15rem;font-weight:800;color:var(--success);">₹${formatAmount(b.current_due || 0)}</div>
                </div>
                <div style="padding:0.75rem 1rem;background:var(--bg-glass);border:1px solid var(--border);border-radius:var(--radius-md);">
                    <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.25rem;">1-30 Days DPD (SMA-0)</div>
                    <div style="font-size:1.15rem;font-weight:800;color:var(--warning);">₹${formatAmount(b.overdue_30 || 0)}</div>
                </div>
                <div style="padding:0.75rem 1rem;background:var(--bg-glass);border:1px solid var(--border);border-radius:var(--radius-md);">
                    <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.25rem;">31-60 Days DPD (SMA-1)</div>
                    <div style="font-size:1.15rem;font-weight:800;color:#f97316;">₹${formatAmount(b.overdue_60 || 0)}</div>
                </div>
                <div style="padding:0.75rem 1rem;background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.25);border-radius:var(--radius-md);">
                    <div style="font-size:0.75rem;color:var(--error);margin-bottom:0.25rem;font-weight:700;">90+ DPD (NPA)</div>
                    <div style="font-size:1.15rem;font-weight:800;color:var(--error);">₹${formatAmount(b.overdue_90_npa || 0)}</div>
                </div>
            `;
        }
    } catch(e) {
        console.error('Failed to load portfolio metrics:', e);
    }

    // 2. Fetch General Report Data
    const res = await fetch('api/admin.php?action=get_report_data');
    const d = await res.json();
    if (!d.success) return;
    const r = d.report;

    // Stats
    const total = r.status_distribution.reduce((a,b)=>a+parseInt(b.count),0);
    const approved = r.status_distribution.find(s=>s.status==='approved')||{count:0};
    const disbursed = r.status_distribution.find(s=>s.status==='disbursed')||{count:0};
    const dsb = r.disbursement_summary || {};
    document.getElementById('reportStats').innerHTML = `
        <div class="stat-card"><div class="stat-header"><div class="stat-icon">📁</div></div><div class="stat-value">${total}</div><div class="stat-label">Total Applications</div></div>
        <div class="stat-card success"><div class="stat-header"><div class="stat-icon">✅</div></div><div class="stat-value">${approved.count}</div><div class="stat-label">Approved Applications</div></div>
        <div class="stat-card accent"><div class="stat-header"><div class="stat-icon">💰</div></div><div class="stat-value">${disbursed.count}</div><div class="stat-label">Disbursed Loans</div></div>
        <div class="stat-card warning"><div class="stat-header"><div class="stat-icon">⏱️</div></div><div class="stat-value">${Math.round(r.avg_processing_hours || 24)}h</div><div class="stat-label">Avg Underwriting Time</div></div>
    `;

    // Monthly chart
    const months = r.monthly_applications || [];
    new Chart(document.getElementById('monthlyChart'), { type:'bar', data:{ labels:months.map(m=>m.month), datasets:[{label:'Applications',data:months.map(m=>m.count),backgroundColor:'rgba(99,102,241,0.6)',borderColor:'#6366f1',borderWidth:2,borderRadius:4}]}, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#64748b'}},y:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#64748b'}}}}});

    // Status chart
    const statuses = r.status_distribution || [];
    const statusColors = ['#6366f1','#06b6d4','#10b981','#f59e0b','#ef4444','#8b5cf6','#f97316','#ec4899'];
    new Chart(document.getElementById('statusChart'), { type:'doughnut', data:{ labels:statuses.map(s=>s.status), datasets:[{data:statuses.map(s=>s.count),backgroundColor:statusColors,borderWidth:0}]}, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right',labels:{color:'#94a3b8',font:{size:11}}}}}});

    // Risk chart
    const risks = r.risk_distribution || [];
    const rColors = {low:'#10b981',medium:'#f59e0b',high:'#f97316',very_high:'#ef4444'};
    new Chart(document.getElementById('riskChart'), { type:'bar', data:{ labels:risks.map(r=>r.risk_category?.replace('_',' ')), datasets:[{data:risks.map(r=>r.count),backgroundColor:risks.map(r=>rColors[r.risk_category]||'#6366f1'),borderRadius:4}]}, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#64748b'}},y:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#64748b'}}}}});

    // Loan type chart
    const lts = r.loan_type_distribution || [];
    new Chart(document.getElementById('loanTypeChart'), { type:'doughnut', data:{ labels:lts.map(l=>l.name), datasets:[{data:lts.map(l=>l.count),backgroundColor:['#6366f1','#06b6d4','#10b981','#f59e0b','#ef4444'],borderWidth:0}]}, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{color:'#94a3b8',font:{size:11}}}}}});

    // EMI Summary
    const emis = r.emi_summary || [];
    document.getElementById('emiSummary').innerHTML = emis.length === 0 ? '<div class="empty-state"><p>No EMI data</p></div>' :
        emis.map(e => `<div style="display:flex;justify-content:space-between;align-items:center;padding:0.75rem 0;border-bottom:1px solid var(--border);">
            <div><span class="badge badge-${e.payment_status==='paid'?'success':e.payment_status==='overdue'?'error':'info'}">${e.payment_status}</span></div>
            <div style="text-align:right;"><div style="font-weight:700;">₹${formatAmount(e.total)}</div><div style="font-size:0.75rem;color:var(--text-muted);">${e.count} installments</div></div>
        </div>`).join('');

    // Top reviewers
    const reviewers = r.top_reviewers || [];
    document.getElementById('reviewersList').innerHTML = reviewers.length === 0 ? '<div class="empty-state"><p>No review data</p></div>' :
        reviewers.map((rv,i) => `<div style="display:flex;align-items:center;gap:1rem;padding:0.75rem 0;border-bottom:1px solid var(--border);">
            <div style="width:32px;height:32px;background:var(--gradient-primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.875rem;color:white;">${i+1}</div>
            <div style="flex:1;font-weight:600;">${escHtml(rv.full_name)}</div>
            <div style="font-weight:700;color:var(--primary-light);">${rv.review_count} reviews</div>
        </div>`).join('');
})();
</script>
