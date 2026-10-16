<?php
/**
 * Module 2/5: Applications List — My Applications (Applicant) + All Applications (Staff)
 */
requireLogin();
$role = $_SESSION['user_role'];
$isApplicant = $role === 'applicant';
$pageMode = $isApplicant ? 'my-applications' : 'all-applications';
?>
<div class="fade-in">
    <div class="filter-bar">
        <div class="search-input">
            <input type="text" id="searchInput" placeholder="Search by ID, name, PAN..." oninput="debounce(loadApplications, 400)()">
        </div>
        <select class="filter-select" id="statusFilter" onchange="loadApplications()">
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="submitted">Submitted</option>
            <option value="under_review">Under Review</option>
            <option value="document_verification">Document Verification</option>
            <option value="risk_assessment">Risk Assessment</option>
            <option value="clarification_required">Clarification Required</option>
            <option value="compliance_check">Compliance Check</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="disbursed">Disbursed</option>
            <option value="closed">Closed</option>
        </select>
        <?php if ($isApplicant): ?>
        <button class="btn btn-primary" onclick="navigateTo('new-application')">+ New Application</button>
        <?php endif; ?>
        <button class="btn btn-outline btn-sm" onclick="loadApplications()">🔄 Refresh</button>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= $isApplicant ? '📋 My Loan Applications' : '📁 All Applications' ?></h3>
            <span id="totalCount" style="font-size: 0.8rem; color: var(--text-muted);">Loading...</span>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Application ID</th>
                        <?php if (!$isApplicant): ?><th>Applicant</th><?php endif; ?>
                        <th>Loan Type</th>
                        <th>Amount</th>
                        <th>Tenure</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="applicationsBody">
                    <tr><td colspan="9" class="text-center text-muted" style="padding:2rem;">Loading applications...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer" style="justify-content: space-between;">
            <span id="pageInfo" style="font-size:0.8rem; color:var(--text-muted);"></span>
            <div id="pagination" class="pagination" style="margin: 0;"></div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;

async function loadApplications(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    const url = `api/applications.php?action=list&page=${page}&search=${encodeURIComponent(search)}&status=${status}`;

    document.getElementById('applicationsBody').innerHTML = '<tr><td colspan="9" class="text-center text-muted" style="padding:2rem;">⏳ Loading...</td></tr>';

    const res = await fetch(url);
    const data = await res.json();
    const role = '<?= $role ?>';

    if (!data.success || data.applications.length === 0) {
        document.getElementById('applicationsBody').innerHTML = `<tr><td colspan="9"><div class="empty-state"><div class="icon">📋</div><h3>No applications found</h3><p>${role === 'applicant' ? 'Start your first loan application' : 'No applications match your filters'}</p>${role === 'applicant' ? '<button class="btn btn-primary" onclick="navigateTo(\'new-application\')">Apply Now</button>' : ''}</div></td></tr>`;
        document.getElementById('totalCount').textContent = '0 applications';
        document.getElementById('pagination').innerHTML = '';
        return;
    }

    document.getElementById('totalCount').textContent = data.total + ' total';
    document.getElementById('pageInfo').textContent = `Page ${data.page} of ${data.pages}`;

    document.getElementById('applicationsBody').innerHTML = data.applications.map(a => `
        <tr onclick="viewApplication('${a.application_id}')" style="cursor:pointer;">
            <td><span class="font-mono" style="color:var(--primary-light); font-weight:600;">${a.application_id}</span></td>
            ${role !== 'applicant' ? `<td>
                <div style="font-weight:600;">${escHtml(a.full_name || a.applicant_name)}</div>
                <div style="font-size:0.75rem; color:var(--text-muted);">${a.pan_number || ''}</div>
            </td>` : ''}
            <td>${escHtml(a.loan_type_name)}</td>
            <td><strong>₹${formatAmount(a.requested_amount)}</strong></td>
            <td>${a.requested_tenure} mo.</td>
            <td>${renderStatusBadge(a.status)}</td>
            <td>${renderPriorityBadge(a.priority)}</td>
            <td style="color:var(--text-muted); font-size:0.8rem;">${a.submitted_at ? formatDate(a.submitted_at) : '<em>Not submitted</em>'}</td>
            <td onclick="event.stopPropagation();">
                <div style="display:flex; gap:0.25rem;">
                    <button class="btn btn-ghost btn-sm" onclick="viewApplication('${a.application_id}')" title="View Details">👁</button>
                    ${a.status === 'draft' && role === 'applicant' ? `<button class="btn btn-ghost btn-sm" onclick="editApplication(${a.id})" title="Edit">✏️</button>` : ''}
                    ${['submitted','under_review','document_verification'].includes(a.status) && role !== 'applicant' ? `<button class="btn btn-ghost btn-sm" onclick="quickAssign(${a.id})" title="Assign">👤</button>` : ''}
                </div>
            </td>
        </tr>
    `).join('');

    // Pagination
    let pages = '';
    if (data.pages > 1) {
        pages += `<button onclick="loadApplications(${page-1})" ${page<=1?'disabled':''}>‹ Prev</button>`;
        for (let i = Math.max(1, page-2); i <= Math.min(data.pages, page+2); i++) {
            pages += `<button onclick="loadApplications(${i})" class="${i===page?'active':''}">${i}</button>`;
        }
        pages += `<button onclick="loadApplications(${page+1})" ${page>=data.pages?'disabled':''}>Next ›</button>`;
    }
    document.getElementById('pagination').innerHTML = pages;
}

function renderPriorityBadge(p) {
    const map = { low: 'muted', medium: 'info', high: 'warning', urgent: 'error' };
    return `<span class="badge badge-${map[p] || 'muted'}">${p || 'normal'}</span>`;
}

function editApplication(id) {
    navigateTo('new-application', { edit: id });
}

loadApplications();
</script>
