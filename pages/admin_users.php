<?php
requireLogin();
if ($_SESSION['user_role'] !== 'admin') { echo '<div class="alert alert-error">Admin access required.</div>'; return; }
?>
<div class="fade-in">
    <!-- Admin Tab Navigation -->
    <div class="tabs" id="adminTabs">
        <button class="tab active" onclick="switchAdminTab('users', this)">
            <span class="icon" style="width:15px;height:15px;display:inline-flex;vertical-align:middle;margin-right:6px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </span>Users
        </button>
        <button class="tab" onclick="switchAdminTab('policies', this)">
            <span class="icon" style="width:15px;height:15px;display:inline-flex;vertical-align:middle;margin-right:6px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
            </span>Lending Policies
        </button>
        <button class="tab" onclick="switchAdminTab('audit', this)">
            <span class="icon" style="width:15px;height:15px;display:inline-flex;vertical-align:middle;margin-right:6px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9,12 11,14 15,10"/></svg>
            </span>Audit Logs
        </button>
        <button class="tab" onclick="switchAdminTab('backup', this)">
            <span class="icon" style="width:15px;height:15px;display:inline-flex;vertical-align:middle;margin-right:6px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </span>Backup & Privacy
        </button>
    </div>

    <!-- ========== USERS TAB ========== -->
    <div id="adminTab-users" class="tab-content active">
        <!-- Stats Row -->
        <div class="stats-grid mb-3" id="userStatsRow">
            <div class="stat-card skeleton" style="height:110px;"></div>
            <div class="stat-card skeleton" style="height:110px;"></div>
            <div class="stat-card skeleton" style="height:110px;"></div>
            <div class="stat-card skeleton" style="height:110px;"></div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span class="icon" style="width:16px;height:16px;color:var(--text-muted);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    </span>
                    All Users
                    <span id="userCountBadge" class="badge badge-muted" style="margin-left:0.5rem;font-size:0.7rem;">—</span>
                </h3>
                <div class="flex items-center gap-1" style="gap:0.75rem;">
                    <div class="search-input" style="min-width:200px;">
                        <input type="text" id="userSearch" placeholder="Search users..." oninput="filterUsers()">
                    </div>
                    <select class="filter-select" id="roleFilter" onchange="filterUsers()">
                        <option value="">All Roles</option>
                        <option value="applicant">Applicant</option>
                        <option value="loan_officer">Loan Officer</option>
                        <option value="risk_analyst">Risk Analyst</option>
                        <option value="compliance_officer">Compliance</option>
                        <option value="admin">Admin</option>
                    </select>
                    <button class="btn btn-primary btn-sm" onclick="openModal('createUserModal')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add User
                    </button>
                </div>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Joined</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersBody">
                        <tr><td colspan="6" class="text-center text-muted" style="padding:3rem;">
                            <div style="display:flex;flex-direction:column;align-items:center;gap:0.75rem;">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.3;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                Loading users...
                            </div>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========== POLICIES TAB ========== -->
    <div id="adminTab-policies" class="tab-content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span class="icon" style="width:16px;height:16px;color:var(--warning);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
                    </span>
                    Lending Policy Configuration
                </h3>
                <span style="font-size:0.8rem;color:var(--text-muted);">Changes take effect immediately</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Policy Name</th>
                            <th>Category</th>
                            <th>Current Value</th>
                            <th>Description</th>
                            <th>Updated By</th>
                            <th>Edit</th>
                        </tr>
                    </thead>
                    <tbody id="policiesBody">
                        <tr><td colspan="6" class="text-center text-muted" style="padding:2rem;">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========== AUDIT TAB ========== -->
    <div id="adminTab-audit" class="tab-content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span class="icon" style="width:16px;height:16px;color:var(--text-muted);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9,12 11,14 15,10"/></svg>
                    </span>
                    Audit Trail
                </h3>
                <div class="search-input" style="min-width:240px;">
                    <input type="text" id="auditSearch" placeholder="Search actions..." oninput="debounce(loadAudit,400)()">
                </div>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody id="auditBody">
                        <tr><td colspan="5" class="text-center text-muted" style="padding:2rem;">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========== BACKUP & PRIVACY TAB ========== -->
    <div id="adminTab-backup" class="tab-content">
        <div class="grid-2 mb-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" style="display:flex;align-items:center;gap:0.5rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Database Backup & Recovery
                    </h3>
                    <button class="btn btn-primary btn-sm" onclick="createBackup()" id="backupBtn">
                        ➕ Generate New SQL Backup
                    </button>
                </div>
                <div class="card-body">
                    <p class="text-sm text-muted mb-2">Automated point-in-time database snapshots with safe table exports.</p>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Filename</th>
                                    <th>Size</th>
                                    <th>Created At</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="backupsBody">
                                <tr><td colspan="4" class="text-center text-muted" style="padding:1.5rem;">Loading backups...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" style="display:flex;align-items:center;gap:0.5rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        Data Retention & Purge Audit
                    </h3>
                    <span class="badge badge-success">GDPR / Privacy Compliant</span>
                </div>
                <div class="card-body">
                    <p class="text-sm text-muted mb-2">Tracks statutory data retention lifecycles, consent revocations, and secure multi-pass file zeroing logs.</p>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Entity</th>
                                    <th>Action</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody id="retentionBody">
                                <tr><td colspan="4" class="text-center text-muted" style="padding:1.5rem;">Loading retention logs...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========== Policy History Modal ========== -->
<div class="modal-overlay" id="policyHistoryModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 id="policyHistoryTitle" style="display:flex;align-items:center;gap:0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Policy Version History
            </h3>
            <button class="modal-close" onclick="closeModal('policyHistoryModal')">✕</button>
        </div>
        <div class="modal-body" id="policyHistoryBody">Loading...</div>
    </div>
</div>

<!-- ========== Create User Modal ========== -->
<div class="modal-overlay" id="createUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3 style="display:flex;align-items:center;gap:0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                Create New User
            </h3>
            <button class="modal-close" onclick="closeModal('createUserModal')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-group"><label class="form-label">Full Name *</label><input type="text" id="cu-name" class="form-control" placeholder="Full name"></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Email *</label><input type="email" id="cu-email" class="form-control" placeholder="Email address"></div>
                <div class="form-group"><label class="form-label">Phone</label><input type="tel" id="cu-phone" class="form-control" placeholder="Phone number"></div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select id="cu-role" class="form-control">
                        <option value="1">Applicant</option>
                        <option value="2">Loan Officer</option>
                        <option value="3">Risk Analyst</option>
                        <option value="4">Compliance Officer</option>
                        <option value="5">Admin</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Default Password</label><input type="text" id="cu-pass" class="form-control" value="Welcome@123"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('createUserModal')">Cancel</button>
            <button class="btn btn-primary" onclick="createUser()">Create User</button>
        </div>
    </div>
</div>

<!-- ========== Edit Policy Modal ========== -->
<div class="modal-overlay" id="editPolicyModal">
    <div class="modal">
        <div class="modal-header">
            <h3 style="display:flex;align-items:center;gap:0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Policy
            </h3>
            <button class="modal-close" onclick="closeModal('editPolicyModal')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div id="policyEditForm"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('editPolicyModal')">Cancel</button>
            <button class="btn btn-primary" onclick="savePolicy()">Save Policy</button>
        </div>
    </div>
</div>

<script>
let editingPolicyId = null;
let allUsers = [];

function switchAdminTab(tab, btn) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('adminTab-' + tab)?.classList.add('active');
    if (tab === 'users') loadUsers();
    if (tab === 'policies') loadPolicies();
    if (tab === 'audit') loadAudit();
    if (tab === 'backup') { loadBackups(); loadRetentionLogs(); }
}

async function loadUsers() {
    const res = await fetch('api/admin.php?action=get_users');
    const data = await res.json();
    if (!data.success) return;
    allUsers = data.users;

    // Build stat counts
    const roles = { applicant:0, loan_officer:0, risk_analyst:0, compliance_officer:0, admin:0 };
    let activeCount = 0;
    data.users.forEach(u => {
        if (u.role_name && roles.hasOwnProperty(u.role_name)) roles[u.role_name]++;
        if (u.is_active) activeCount++;
    });
    document.getElementById('userCountBadge').textContent = data.users.length + ' users';

    document.getElementById('userStatsRow').innerHTML = `
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon icon" style="color:var(--primary);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
            </div>
            <div class="stat-value">${data.users.length}</div>
            <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card success">
            <div class="stat-header">
                <div class="stat-icon icon" style="color:var(--success);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
                </div>
            </div>
            <div class="stat-value">${activeCount}</div>
            <div class="stat-label">Active Users</div>
        </div>
        <div class="stat-card accent">
            <div class="stat-header">
                <div class="stat-icon icon" style="color:var(--accent);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
                </div>
            </div>
            <div class="stat-value">${roles.applicant}</div>
            <div class="stat-label">Applicants</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-header">
                <div class="stat-icon icon" style="color:var(--warning);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
            </div>
            <div class="stat-value">${roles.loan_officer + roles.risk_analyst + roles.compliance_officer + roles.admin}</div>
            <div class="stat-label">Staff Members</div>
        </div>
    `;

    renderUsers(data.users);
}

function renderUsers(users) {
    const roleColors = {applicant:'info',loan_officer:'success',risk_analyst:'warning',compliance_officer:'primary',admin:'error'};
    document.getElementById('usersBody').innerHTML = users.length === 0
        ? `<tr><td colspan="6"><div class="empty-state"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.3;margin-bottom:1rem;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg><h3>No users found</h3><p>Try adjusting your search filter</p></div></td></tr>`
        : users.map(u => `
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:0.75rem;">
                    <div class="user-avatar" style="width:36px;height:36px;font-size:0.8rem;">${escHtml(u.full_name.slice(0,2).toUpperCase())}</div>
                    <div>
                        <div style="font-weight:600;color:var(--text-primary);">${escHtml(u.full_name)}</div>
                        <div style="font-size:0.75rem;color:var(--text-muted);">${escHtml(u.email)}</div>
                    </div>
                </div>
            </td>
            <td><span class="badge badge-${roleColors[u.role_name]||'muted'}">${u.role_name?.replace(/_/g,' ')}</span></td>
            <td>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <span class="status-dot ${u.is_active?'green':'red'}"></span>
                    <span style="font-size:0.8rem;">${u.is_active?'Active':'Inactive'}</span>
                </div>
            </td>
            <td style="color:var(--text-muted);font-size:0.8rem;">${u.last_login ? formatDate(u.last_login) : '—'}</td>
            <td style="color:var(--text-muted);font-size:0.8rem;">${u.created_at ? formatDate(u.created_at) : '—'}</td>
            <td>
                <div style="display:flex;gap:0.375rem;justify-content:flex-end;align-items:center;">
                    <select class="filter-select" onchange="changeRole(${u.id}, this.value)" style="font-size:0.75rem;padding:0.25rem 1.5rem 0.25rem 0.5rem;" title="Change role">
                        ${[1,2,3,4,5].map((r,i) => `<option value="${r}" ${u.role_id==r?'selected':''}>${['Applicant','Loan Officer','Risk Analyst','Compliance','Admin'][i]}</option>`).join('')}
                    </select>
                    <button class="btn btn-ghost btn-sm" onclick="toggleUser(${u.id})" title="${u.is_active?'Deactivate':'Activate'}" style="color:${u.is_active?'var(--error)':'var(--success)'};">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">${u.is_active?'<circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/>':'<circle cx="12" cy="12" r="10"/><polyline points="12,8 12,12 14,14"/>'}</svg>
                        ${u.is_active?'Deactivate':'Activate'}
                    </button>
                </div>
            </td>
        </tr>`).join('');
}

function filterUsers() {
    const q = document.getElementById('userSearch').value.toLowerCase();
    const roleF = document.getElementById('roleFilter').value;
    const filtered = allUsers.filter(u => {
        const matchQ = !q || u.full_name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q);
        const matchR = !roleF || u.role_name === roleF;
        return matchQ && matchR;
    });
    renderUsers(filtered);
}

async function createUser() {
    const fd = new FormData();
    fd.append('action','create_user');
    fd.append('full_name', document.getElementById('cu-name').value);
    fd.append('email', document.getElementById('cu-email').value);
    fd.append('phone', document.getElementById('cu-phone').value);
    fd.append('role_id', document.getElementById('cu-role').value);
    fd.append('password', document.getElementById('cu-pass').value);
    const res = await fetch('api/admin.php',{method:'POST',body:fd});
    const data = await res.json();
    showToast(data.message, data.success?'success':'error');
    if (data.success) { closeModal('createUserModal'); loadUsers(); }
}

async function changeRole(userId, roleId) {
    const fd = new FormData();
    fd.append('action','update_user');
    fd.append('user_id', userId);
    fd.append('role_id', roleId);
    await fetch('api/admin.php',{method:'POST',body:fd});
    showToast('Role updated','success');
}

async function toggleUser(userId) {
    const fd = new FormData();
    fd.append('action','toggle_user');
    fd.append('user_id', userId);
    await fetch('api/admin.php',{method:'POST',body:fd});
    showToast('User status updated','success');
    loadUsers();
}

async function loadPolicies() {
    const res = await fetch('api/admin.php?action=get_policies');
    const data = await res.json();
    if (!data.success) return;
    const catColors = {eligibility:'info',risk:'warning',process:'success'};
    document.getElementById('policiesBody').innerHTML = data.policies.map(p => `
        <tr>
            <td><strong>${escHtml(p.policy_name)}</strong></td>
            <td><span class="badge badge-${catColors[p.category]||'muted'}">${p.category}</span></td>
            <td><span style="font-family:monospace;font-size:1rem;font-weight:800;color:var(--primary-light);">${escHtml(p.policy_value)}</span></td>
            <td style="color:var(--text-muted);font-size:0.8rem;max-width:240px;">${escHtml(p.description||'')}</td>
            <td style="color:var(--text-muted);font-size:0.75rem;">${p.updater_name||'System'}</td>
            <td>
                <div style="display:flex;gap:0.35rem;align-items:center;">
                    <button class="btn btn-outline btn-sm" onclick="editPolicy(${p.id},'${escHtml(p.policy_name)}','${escHtml(p.policy_value)}','${escHtml(p.description||'')}','${p.data_type}')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Edit
                    </button>
                    <button class="btn btn-ghost btn-sm" onclick="viewPolicyHistory(${p.id},'${escHtml(p.policy_name)}')">
                        📜 History
                    </button>
                </div>
            </td>
        </tr>`).join('');
}

function editPolicy(id, name, value, desc, type) {
    editingPolicyId = id;
    document.getElementById('policyEditForm').innerHTML = `
        <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:1rem;">${desc}</p>
        <div class="form-group mb-2">
            <label class="form-label">${name} *</label>
            <input type="${type==='number'?'number':'text'}" id="policyValue" class="form-control" value="${value}" step="${type==='number'?'0.01':''}">
        </div>
        <div class="form-group">
            <label class="form-label">Audit Rationale / Reason for Change *</label>
            <input type="text" id="policyRationale" class="form-control" placeholder="E.g., Updated by Underwriting Committee resolution">
        </div>`;
    openModal('editPolicyModal');
}

async function savePolicy() {
    const val = document.getElementById('policyValue').value;
    const rat = document.getElementById('policyRationale')?.value || 'Admin policy update';
    if (!rat.trim()) { showToast('Please enter an audit rationale for this change', 'warning'); return; }

    const fd = new FormData();
    fd.append('action','update_policy');
    fd.append('policy_id', editingPolicyId);
    fd.append('policy_value', val);
    fd.append('rationale', rat);
    const res = await fetch('api/admin.php',{method:'POST',body:fd});
    const data = await res.json();
    showToast(data.message, data.success?'success':'error');
    if (data.success) { closeModal('editPolicyModal'); loadPolicies(); }
}

async function viewPolicyHistory(policyId, policyName) {
    document.getElementById('policyHistoryTitle').innerHTML = `📜 Version Audit — ${escHtml(policyName)}`;
    openModal('policyHistoryModal');
    document.getElementById('policyHistoryBody').innerHTML = '<div class="text-center" style="padding:2rem;">Loading version history...</div>';

    const res = await fetch('api/admin.php?action=get_policy_versions&policy_id=' + policyId);
    const data = await res.json();

    if (!data.success || !data.versions || data.versions.length === 0) {
        document.getElementById('policyHistoryBody').innerHTML = '<div class="empty-state"><p>No previous version changes recorded yet.</p></div>';
        return;
    }

    document.getElementById('policyHistoryBody').innerHTML = `
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Version</th>
                        <th>Old Value</th>
                        <th>New Value</th>
                        <th>Rationale</th>
                        <th>Changed By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.versions.map(v => `
                        <tr>
                            <td><span class="badge badge-info">v${v.version}</span></td>
                            <td><span class="font-mono text-muted">${escHtml(v.old_value || 'None')}</span></td>
                            <td><span class="font-mono" style="font-weight:700;color:var(--primary-light);">${escHtml(v.new_value)}</span></td>
                            <td style="font-size:0.85rem;">${escHtml(v.rationale)}</td>
                            <td>${escHtml(v.changer_name || 'Admin')}</td>
                            <td style="font-size:0.75rem;color:var(--text-muted);">${formatDate(v.created_at)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

async function loadBackups() {
    const res = await fetch('api/backup.php?action=list');
    const data = await res.json();
    if (!data.success) return;
    const backups = data.backups || [];
    document.getElementById('backupsBody').innerHTML = backups.length === 0
        ? `<tr><td colspan="4" class="text-center text-muted" style="padding:1.5rem;">No database backups created yet</td></tr>`
        : backups.map(b => `
        <tr>
            <td><span class="font-mono text-sm">${escHtml(b.backup_name)}</span></td>
            <td>${(b.file_size / 1024).toFixed(1)} KB</td>
            <td style="font-size:0.75rem;color:var(--text-muted);">${formatDate(b.created_at)}</td>
            <td>
                <a href="api/backup.php?action=download&id=${b.id}" class="btn btn-outline btn-sm">
                    💾 Download SQL
                </a>
            </td>
        </tr>`).join('');
}

async function createBackup() {
    const btn = document.getElementById('backupBtn');
    btn.disabled = true;
    showToast('Creating SQL snapshot...', 'info');
    const fd = new FormData();
    fd.append('action', 'create');
    const res = await fetch('api/backup.php', { method: 'POST', body: fd });
    const data = await res.json();
    btn.disabled = false;
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) loadBackups();
}

async function loadRetentionLogs() {
    const res = await fetch('api/privacy.php?action=get_retention_logs');
    const data = await res.json();
    if (!data.success) return;
    const logs = data.logs || [];
    document.getElementById('retentionBody').innerHTML = logs.length === 0
        ? `<tr><td colspan="4" class="text-center text-muted" style="padding:1.5rem;">No retention audit actions recorded yet</td></tr>`
        : logs.map(l => `
        <tr>
            <td style="font-size:0.75rem;color:var(--text-muted);">${formatDate(l.created_at)}</td>
            <td><span class="badge badge-muted">${escHtml(l.entity_type)} #${l.entity_id}</span></td>
            <td><code style="background:var(--bg-glass);padding:0.2rem 0.4rem;border-radius:4px;">${escHtml(l.action)}</code></td>
            <td style="font-size:0.8rem;color:var(--text-secondary);">${escHtml(l.reason || 'Statutory Retention Policy')}</td>
        </tr>`).join('');
}

async function loadAudit() {
    const search = document.getElementById('auditSearch')?.value || '';
    const res = await fetch(`api/admin.php?action=get_audit_logs&search=${encodeURIComponent(search)}`);
    const data = await res.json();
    if (!data.success) return;
    document.getElementById('auditBody').innerHTML = data.logs.length === 0
        ? `<tr><td colspan="5" class="text-center text-muted" style="padding:2rem;">No audit logs found</td></tr>`
        : data.logs.map(l => `
        <tr>
            <td style="font-size:0.75rem;color:var(--text-muted);white-space:nowrap;">${formatDate(l.created_at)}</td>
            <td>
                <div style="font-weight:600;font-size:0.875rem;">${escHtml(l.user_name||'System')}</div>
                <div style="font-size:0.7rem;color:var(--text-muted);">${l.user_email||''}</div>
            </td>
            <td><code style="background:var(--bg-glass);padding:0.2rem 0.5rem;border-radius:4px;font-size:0.8rem;">${l.action}</code></td>
            <td style="font-size:0.8rem;color:var(--text-muted);">${l.entity_type||''} ${l.entity_id?'#'+l.entity_id:''}</td>
            <td style="font-size:0.75rem;color:var(--text-dim);">${l.ip_address||'N/A'}</td>
        </tr>`).join('');
}

loadUsers();
</script>
