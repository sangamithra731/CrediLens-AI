<?php
/**
 * Documents Page — View and manage documents across applications
 */
requireLogin();
$role = $_SESSION['user_role'];
?>
<div class="fade-in">
    <div class="filter-bar">
        <div class="search-input">
            <input type="text" id="docSearch" placeholder="Search by application ID or document name..." oninput="debounce(loadDocs, 400)()">
        </div>
        <select class="filter-select" id="docStatusFilter" onchange="loadDocs()">
            <option value="">All Statuses</option>
            <option value="pending">Pending OCR</option>
            <option value="completed">OCR Completed</option>
            <option value="valid">Valid</option>
            <option value="mismatch">Mismatch</option>
        </select>
        <button class="btn btn-outline btn-sm" onclick="loadDocs()">🔄 Refresh</button>
    </div>

    <div class="stats-grid mb-3" id="docStats"></div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📄 Document Center</h3>
            <span id="docTotal" style="font-size:0.8rem; color:var(--text-muted);"></span>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Application</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>OCR Status</th>
                        <th>Validation</th>
                        <th>Uploaded</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="docTableBody">
                    <tr><td colspan="8" class="text-center text-muted" style="padding:2rem;">Loading documents...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- AI Analysis Modal -->
<div class="modal-overlay" id="aiModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>🤖 AI Document Analysis</h3>
            <button class="modal-close" onclick="closeModal('aiModal')">✕</button>
        </div>
        <div class="modal-body" id="aiModalBody">Loading...</div>
    </div>
</div>

<script>
async function loadDocs() {
    const search = document.getElementById('docSearch').value;
    const status = document.getElementById('docStatusFilter').value;

    // Get all applications first, then aggregate docs
    const res = await fetch(`api/applications.php?action=list&search=${encodeURIComponent(search)}`);
    const data = await res.json();
    if (!data.success) return;

    let allDocs = [];
    for (const app of data.applications.slice(0, 20)) {
        try {
            const dRes = await fetch(`api/documents.php?action=list&application_id=${app.id}`);
            const dData = await dRes.json();
            if (dData.documents) {
                dData.documents.forEach(d => {
                    d.application_id_str = app.application_id;
                    d.app_id = app.id;
                    d.applicant_name = app.full_name || app.applicant_name;
                });
                allDocs = allDocs.concat(dData.documents);
            }
        } catch (e) {}
    }

    // Filter by status
    if (status) {
        if (status === 'valid' || status === 'mismatch') {
            allDocs = allDocs.filter(d => d.validation_status === status);
        } else {
            allDocs = allDocs.filter(d => d.ocr_status === status);
        }
    }

    // Stats
    const stats = {
        total: allDocs.length,
        pending: allDocs.filter(d => d.ocr_status === 'pending').length,
        completed: allDocs.filter(d => d.ocr_status === 'completed').length,
        valid: allDocs.filter(d => d.validation_status === 'valid').length,
        mismatch: allDocs.filter(d => d.validation_status === 'mismatch').length,
    };

    document.getElementById('docStats').innerHTML = `
        <div class="stat-card"><div class="stat-header"><div class="stat-icon">📄</div></div><div class="stat-value">${stats.total}</div><div class="stat-label">Total Documents</div></div>
        <div class="stat-card accent"><div class="stat-header"><div class="stat-icon">⏳</div></div><div class="stat-value">${stats.pending}</div><div class="stat-label">Pending OCR</div></div>
        <div class="stat-card success"><div class="stat-header"><div class="stat-icon">✅</div></div><div class="stat-value">${stats.valid}</div><div class="stat-label">Validated</div></div>
        <div class="stat-card error"><div class="stat-header"><div class="stat-icon">⚠️</div></div><div class="stat-value">${stats.mismatch}</div><div class="stat-label">Mismatches</div></div>
    `;

    document.getElementById('docTotal').textContent = allDocs.length + ' documents';

    if (allDocs.length === 0) {
        document.getElementById('docTableBody').innerHTML = '<tr><td colspan="8"><div class="empty-state"><div class="icon">📄</div><h3>No documents found</h3></div></td></tr>';
        return;
    }

    const role = '<?= $role ?>';

    document.getElementById('docTableBody').innerHTML = allDocs.map(d => `
        <tr>
            <td>
                <div style="display:flex; gap:0.5rem; align-items:center;">
                    <span style="font-size:1.25rem;">${d.mime_type?.includes('pdf') ? '📕' : '🖼️'}</span>
                    <div>
                        <div style="font-weight:600; font-size:0.875rem;">${escHtml(d.file_name)}</div>
                        <div style="font-size:0.7rem; color:var(--text-dim);">${d.mime_type || 'unknown'}</div>
                    </div>
                </div>
            </td>
            <td>
                <span class="font-mono" style="color:var(--primary-light); cursor:pointer;" onclick="viewApplication('${d.application_id_str}')">${d.application_id_str}</span>
                <div style="font-size:0.7rem; color:var(--text-muted);">${escHtml(d.applicant_name || '')}</div>
            </td>
            <td><span class="badge badge-muted">${escHtml(d.document_type_name)}</span></td>
            <td style="font-size:0.8rem; color:var(--text-muted);">${(d.file_size / 1024).toFixed(1)} KB</td>
            <td>
                <span class="badge badge-${d.ocr_status === 'completed' ? 'success' : d.ocr_status === 'failed' ? 'error' : 'warning'}">
                    ${d.ocr_status || 'pending'}
                </span>
            </td>
            <td>
                <span class="badge badge-${d.validation_status === 'valid' ? 'success' : d.validation_status === 'mismatch' ? 'error' : 'muted'}">
                    ${d.validation_status || 'pending'}
                </span>
            </td>
            <td style="font-size:0.8rem; color:var(--text-muted);">${formatDate(d.uploaded_at)}</td>
            <td>
                <div style="display:flex; gap:0.25rem;">
                    <a href="api/documents.php?action=download&id=${d.id}" target="_blank" class="btn btn-ghost btn-sm" title="Download">📥</a>
                    ${role !== 'applicant' ? `<button class="btn btn-primary btn-sm" onclick="runDocAI(${d.id}, ${d.app_id})" title="AI Analyze">🤖</button>` : ''}
                    ${d.ocr_status === 'completed' ? `<button class="btn btn-ghost btn-sm" onclick="viewExtracted(${d.id})" title="View Extracted">🔍</button>` : ''}
                </div>
            </td>
        </tr>`).join('');
}

async function runDocAI(docId, appId) {
    showToast('🤖 Running AI analysis...', 'info');
    const fd = new FormData();
    fd.append('action', 'process_ai');
    fd.append('document_id', docId);
    fd.append('application_id', appId);
    const res = await fetch('api/documents.php', { method: 'POST', body: fd });
    const data = await res.json();
    showToast(data.success ? '✅ Analysis complete' : '❌ ' + data.message, data.success ? 'success' : 'error');
    if (data.success) {
        loadDocs();
        // Show the result
        showAIResult(data);
    }
}

function showAIResult(data) {
    openModal('aiModal');
    const extracted = data.extracted_data || {};
    const validation = data.validation || {};

    document.getElementById('aiModalBody').innerHTML = `
        <div class="grid-2" style="gap:1.5rem;">
            <div>
                <h4 style="margin-bottom:1rem; font-size:0.875rem; font-weight:700;">📋 Extracted Information</h4>
                ${Object.entries(extracted).map(([k, v]) => `
                <div style="display:flex; justify-content:space-between; padding:0.5rem 0; border-bottom:1px solid var(--border);">
                    <span style="color:var(--text-muted); font-size:0.875rem;">${k}</span>
                    <span style="font-weight:600;">${escHtml(String(v))}</span>
                </div>`).join('')}
            </div>
            <div>
                <h4 style="margin-bottom:1rem; font-size:0.875rem; font-weight:700;">✅ Validation Results</h4>
                <div class="check-item ${validation.is_valid ? 'passed' : 'failed'}" style="margin-bottom:0.5rem;">
                    <div class="check-icon">${validation.is_valid ? '✓' : '✗'}</div>
                    <div>Document ${validation.is_valid ? 'Valid' : 'Issues Found'}</div>
                </div>
                ${validation.confidence ? `<div style="margin-top:1rem;"><div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.5rem;">AI Confidence</div><div class="progress-bar"><div class="fill ${validation.confidence > 80 ? 'success' : 'warning'}" style="width:${validation.confidence}%;"></div></div><div style="text-align:right; font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;">${validation.confidence}%</div></div>` : ''}
                ${validation.notes ? `<div class="alert alert-info" style="margin-top:1rem;"><strong>AI Notes:</strong> ${escHtml(validation.notes)}</div>` : ''}
            </div>
        </div>`;
}

async function viewExtracted(docId) {
    openModal('aiModal');
    document.getElementById('aiModalBody').innerHTML = '<div class="text-center" style="padding:2rem;">⏳ Loading...</div>';

    // We don't have a direct get endpoint, but the data is in the table row
    // For now, show a message directing to the application detail
    document.getElementById('aiModalBody').innerHTML = `
        <div class="text-center" style="padding:2rem;">
            <p style="color:var(--text-secondary);">View full extracted data and validation results in the Application Detail page.</p>
        </div>`;
}

loadDocs();
</script>
