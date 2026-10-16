<?php
/**
 * CrediLens AI — Dashboard Page
 * Role-aware dashboard with stats, charts, and recent activity
 */
requireLogin();
$currentUser = getCurrentUser();
$role = $currentUser['role'];
?>

<div class="fade-in">
    <!-- Welcome Banner -->
    <div class="card mb-3" style="background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(6,182,212,0.1)); border-color: rgba(99,102,241,0.2);">
        <div class="card-body" style="padding: 1.5rem 2rem;">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div>
                    <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.25rem;">
                        Good <?= date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening') ?>, <?= htmlspecialchars(explode(' ', $currentUser['full_name'])[0]) ?>!
                    </h2>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">
                        <?php
                        $welcomeTexts = [
                            'applicant'          => 'Track your loan applications and stay updated on their status.',
                            'loan_officer'       => 'Review pending applications and make timely credit decisions.',
                            'risk_analyst'       => 'Assess risk indicators and generate explainable reports.',
                            'compliance_officer' => 'Monitor policy compliance and maintain regulatory standards.',
                            'admin'              => 'Manage the entire platform from users to lending policies.'
                        ];
                        echo $welcomeTexts[$role] ?? 'Welcome to CrediLens AI.';
                        ?>
                    </p>
                </div>
                <div style="text-align: right; color: var(--text-muted); font-size: 0.8rem;">
                    <div style="font-size: 1.5rem; font-weight: 800; color: var(--text-primary);"><?= date('D, d M Y') ?></div>
                    <div><?= ucwords(str_replace('_', ' ', $role)) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid" id="dashStats">
        <div class="stat-card skeleton" style="height: 130px;"></div>
        <div class="stat-card skeleton" style="height: 130px;"></div>
        <div class="stat-card skeleton" style="height: 130px;"></div>
        <div class="stat-card skeleton" style="height: 130px;"></div>
    </div>

    <div class="grid-2 mb-3" style="gap: 1.5rem;">
        <!-- Chart -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span class="icon" style="width:18px;height:18px;color:var(--primary);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23,6 13.5,15.5 8.5,10.5 1,18"/><polyline points="17,6 23,6 23,12"/></svg>
                    </span>
                    Application Trend
                </h3>
                <span style="font-size: 0.75rem; color: var(--text-muted);">Last 6 months</span>
            </div>
            <div class="card-body">
                <canvas id="trendChart" height="220"></canvas>
            </div>
        </div>

        <!-- Loan Type Distribution -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span class="icon" style="width:18px;height:18px;color:var(--accent);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 10 10"/></svg>
                    </span>
                    Loan Distribution
                </h3>
            </div>
            <div class="card-body">
                <canvas id="typeChart" height="220"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Applications -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span class="icon" style="width:18px;height:18px;color:var(--text-muted);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </span>
                Recent Applications
            </h3>
            <button class="btn btn-outline btn-sm" onclick="navigateTo('<?= $role === 'applicant' ? 'my-applications' : 'all-applications' ?>')">View All</button>
        </div>
        <div class="table-container">
            <table id="recentTable">
                <thead>
                    <tr>
                        <th>Application ID</th>
                        <?php if ($role !== 'applicant'): ?><th>Applicant</th><?php endif; ?>
                        <th>Loan Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="recentTableBody">
                    <tr><td colspan="7" class="text-center text-muted" style="padding: 2rem;">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($role !== 'applicant'): ?>
    <!-- Workflow status pipeline (staff view) -->
    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title">
                <span class="icon" style="width:18px;height:18px;color:var(--warning);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13,2 3,14 12,14 11,22 21,10 12,10 13,2"/></svg>
                </span>
                Pipeline Status
            </h3>
        </div>
        <div class="card-body">
            <div id="pipelineStatus" style="display: flex; gap: 1rem; overflow-x: auto; padding-bottom: 0.5rem;"></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Inline SVG icons for JS-generated HTML
const ICONS = {
    file:    `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>`,
    clock:   `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>`,
    check:   `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20,6 9,17 4,12"/></svg>`,
    dollar:  `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>`,
    folder:  `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>`,
    refresh: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23,4 23,10 17,10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>`,
    send:    `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22,2 15,22 11,13 2,9 22,2"/></svg>`,
    search:  `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>`,
    shield:  `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>`,
    alert:   `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    x:       `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`,
};

(async function initDashboard() {
    try {
        const res = await fetch('api/applications.php?action=get_stats');
        const data = await res.json();
        if (!data.success) return;
        const s = data.stats;

        // Render stat cards
        const role = '<?= $role ?>';
        let cards = '';
        if (role === 'applicant') {
            cards = `
                <div class="stat-card slide-up">
                    <div class="stat-header"><div class="stat-icon icon" style="color:var(--primary);">${ICONS.file}</div></div>
                    <div class="stat-value">${s.total || 0}</div>
                    <div class="stat-label">Total Applications</div>
                </div>
                <div class="stat-card accent slide-up">
                    <div class="stat-header"><div class="stat-icon icon" style="color:var(--accent);">${ICONS.clock}</div></div>
                    <div class="stat-value">${s.in_progress || 0}</div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-card success slide-up">
                    <div class="stat-header"><div class="stat-icon icon" style="color:var(--success);">${ICONS.check}</div></div>
                    <div class="stat-value">${s.approved || 0}</div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card warning slide-up">
                    <div class="stat-header"><div class="stat-icon icon" style="color:var(--warning);">${ICONS.dollar}</div></div>
                    <div class="stat-value">₹${formatAmount(s.total_requested || 0)}</div>
                    <div class="stat-label">Total Requested</div>
                </div>
            `;
        } else {
            cards = `
                <div class="stat-card slide-up">
                    <div class="stat-header"><div class="stat-icon icon" style="color:var(--primary);">${ICONS.folder}</div></div>
                    <div class="stat-value">${s.total || 0}</div>
                    <div class="stat-label">Total Applications</div>
                </div>
                <div class="stat-card accent slide-up">
                    <div class="stat-header"><div class="stat-icon icon" style="color:var(--accent);">${ICONS.refresh}</div></div>
                    <div class="stat-value">${(s.submitted || 0) + (s.under_review || 0) + (s.doc_verification || 0) + (s.risk_assessment || 0)}</div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-card success slide-up">
                    <div class="stat-header"><div class="stat-icon icon" style="color:var(--success);">${ICONS.check}</div></div>
                    <div class="stat-value">${s.approved || 0}</div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card warning slide-up">
                    <div class="stat-header"><div class="stat-icon icon" style="color:var(--warning);">${ICONS.dollar}</div></div>
                    <div class="stat-value">${s.disbursed || 0}</div>
                    <div class="stat-label">Disbursed</div>
                </div>
            `;
        }
        document.getElementById('dashStats').innerHTML = cards;

        // Pipeline (staff)
        if (role !== 'applicant') {
            const pipeline = [
                { label: 'Submitted',       count: s.submitted || 0,       color: 'var(--info)',    icon: ICONS.send    },
                { label: 'Under Review',    count: s.under_review || 0,    color: 'var(--primary)', icon: ICONS.search  },
                { label: 'Doc Check',       count: s.doc_verification || 0,color: 'var(--accent)',  icon: ICONS.file    },
                { label: 'Risk Assessment', count: s.risk_assessment || 0, color: 'var(--warning)', icon: ICONS.shield  },
                { label: 'Clarification',   count: s.clarification || 0,   color: '#f97316',        icon: ICONS.alert   },
                { label: 'Approved',        count: s.approved || 0,        color: 'var(--success)', icon: ICONS.check   },
                { label: 'Rejected',        count: s.rejected || 0,        color: 'var(--error)',   icon: ICONS.x       },
                { label: 'Disbursed',       count: s.disbursed || 0,       color: '#10b981',        icon: ICONS.dollar  },
            ];
            const container = document.getElementById('pipelineStatus');
            if (container) {
                container.innerHTML = pipeline.map(p => `
                    <div style="flex-shrink:0; text-align:center; padding: 1rem 1.5rem; background: var(--bg-glass); border: 1px solid var(--border); border-radius: var(--radius-md); min-width: 120px;">
                        <div style="display:flex;align-items:center;justify-content:center;width:40px;height:40px;background:var(--bg-glass);border-radius:50%;margin:0 auto 0.75rem;color:${p.color};">${p.icon}</div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: ${p.color};">${p.count}</div>
                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem; font-weight: 600; text-transform: uppercase;">${p.label}</div>
                    </div>
                `).join('<div style="display:flex;align-items:center;color:var(--text-dim);font-size:1.25rem;padding:0 0.25rem;">›</div>');
            }
        }

        // Recent table
        const recent = s.recent || [];
        const tbody = document.getElementById('recentTableBody');
        if (recent.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state"><div class="icon" style="font-size:3rem;margin-bottom:1rem;">${ICONS.file}</div><h3>No applications yet</h3><p>Start by creating your first loan application</p><button class="btn btn-primary" onclick="navigateTo('new-application')">Apply Now</button></div></td></tr>`;
        } else {
            tbody.innerHTML = recent.map(a => `
                <tr onclick="viewApplication('${a.application_id}')" style="cursor:pointer;">
                    <td><span class="font-mono text-sm" style="color:var(--primary-light)">${a.application_id}</span></td>
                    ${role !== 'applicant' ? `<td><strong>${escHtml(a.full_name || 'N/A')}</strong></td>` : ''}
                    <td>${escHtml(a.loan_type)}</td>
                    <td><strong>₹${formatAmount(a.requested_amount)}</strong></td>
                    <td>${renderStatusBadge(a.status)}</td>
                    <td style="color:var(--text-muted)">${formatDate(a.created_at)}</td>
                    <td><button class="btn btn-ghost btn-sm" onclick="event.stopPropagation();viewApplication('${a.application_id}')">View →</button></td>
                </tr>
            `).join('');
        }

        // Charts
        renderTrendChart(s.monthly_trend || []);
        renderTypeChart(s.loan_type_dist || []);

    } catch(e) {
        console.error('Dashboard error:', e);
    }
})();

function renderTrendChart(data) {
    const ctx = document.getElementById('trendChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(d => d.month),
            datasets: [{
                label: 'Applications',
                data: data.map(d => d.count),
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99,102,241,0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#6366f1',
                pointRadius: 5
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: 'rgba(128,128,128,0.1)' }, ticks: { color: '#64748b', font: { size: 11 } } },
                y: { grid: { color: 'rgba(128,128,128,0.1)' }, ticks: { color: '#64748b', font: { size: 11 } } }
            }
        }
    });
}

function renderTypeChart(data) {
    const ctx = document.getElementById('typeChart');
    if (!ctx) return;
    const colors = ['#6366f1','#06b6d4','#10b981','#f59e0b','#ef4444'];
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(d => d.name),
            datasets: [{ data: data.map(d => d.count), backgroundColor: colors, borderWidth: 0, hoverOffset: 6 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: '#94a3b8', padding: 16, font: { size: 11 } } }
            }
        }
    });
}
</script>
