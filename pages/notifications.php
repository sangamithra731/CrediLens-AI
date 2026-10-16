<?php requireLogin(); ?>
<div class="fade-in">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h2 style="font-size:1.5rem;font-weight:800;color:var(--text-primary);margin:0;">Notifications & Mail Center</h2>
            <p style="font-size:0.875rem;color:var(--text-secondary);margin:0.25rem 0 0;">Manage alerts, trigger loan lifecycle emails, inspect sent logs, or configure live SMTP.</p>
        </div>
        <div style="display:flex;gap:0.75rem;">
            <button class="btn btn-primary btn-sm" onclick="openMailTriggerModal()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                Trigger Email
            </button>
            <button class="btn btn-outline btn-sm" onclick="toggleAiChat()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><path d="M12 2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2 2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><rect x="4" y="8" width="16" height="12" rx="4"/><circle cx="9" cy="14" r="1"/><circle cx="15" cy="14" r="1"/></svg>
                Ask AI Assistant
            </button>
        </div>
    </div>

    <!-- Quick Email Trigger Banner -->
    <div class="card" style="margin-bottom:1.5rem;background:linear-gradient(135deg, rgba(99,102,241,0.08), rgba(139,92,246,0.08));border:1px solid rgba(99,102,241,0.25);">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:42px;height:42px;border-radius:12px;background:var(--primary);display:flex;align-items:center;justify-content:center;color:white;flex-shrink:0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </div>
                <div>
                    <h4 style="margin:0;font-size:1rem;font-weight:700;color:var(--text-primary);">Automated Email Triggers & Live SMTP</h4>
                    <p style="margin:2px 0 0;font-size:0.8rem;color:var(--text-secondary);">Test or dispatch real emails directly to any inbox. Dispatches are tracked with complete HTML previews.</p>
                </div>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                <button class="btn btn-outline btn-sm" onclick="quickTriggerEmail('approved')">Trigger Approval Email</button>
                <button class="btn btn-outline btn-sm" onclick="quickTriggerEmail('submitted')">Trigger Submission Email</button>
                <button class="btn btn-outline btn-sm" onclick="quickTriggerEmail('emi_reminder')">Trigger EMI Reminder</button>
                <button class="btn btn-secondary btn-sm" onclick="switchMailTab('smtp')">⚙️ SMTP Settings</button>
            </div>
        </div>
    </div>

    <!-- Main Tabs -->
    <div style="display:flex;gap:1rem;border-bottom:1px solid var(--border);margin-bottom:1.5rem;">
        <button id="tabBtnNotifs" class="btn btn-text btn-sm" style="font-weight:700;border-bottom:2px solid var(--primary);border-radius:0;padding:8px 16px;" onclick="switchMailTab('notifs')">In-App Notifications</button>
        <button id="tabBtnLogs" class="btn btn-text btn-sm" style="color:var(--text-secondary);border-radius:0;padding:8px 16px;" onclick="switchMailTab('logs')">Sent Email Audit Logs</button>
        <button id="tabBtnSmtp" class="btn btn-text btn-sm" style="color:var(--text-secondary);border-radius:0;padding:8px 16px;" onclick="switchMailTab('smtp')">⚙️ Live SMTP Settings</button>
    </div>

    <!-- Section 1: In-App Notifications -->
    <div id="sectionNotifs">
        <div class="filter-bar">
            <div class="search-input">
                <input type="text" id="notifSearch" placeholder="Search notifications..." oninput="filterNotifications()">
            </div>
            <button class="btn btn-outline btn-sm" onclick="markAllRead()">✓ Mark All Read</button>
            <button class="btn btn-outline btn-sm" onclick="loadNotifications()">🔄 Refresh</button>
        </div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">🔔 Recent Alerts</h3>
                <span id="notifCount" style="font-size:0.8rem;color:var(--text-muted);"></span>
            </div>
            <div id="notifList"><div class="text-center text-muted" style="padding:3rem;">Loading notifications...</div></div>
        </div>
    </div>

    <!-- Section 2: Sent Email Logs -->
    <div id="sectionLogs" style="display:none;">
        <div class="card">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                <h3 class="card-title">📬 Dispatched Email History</h3>
                <button class="btn btn-outline btn-sm" onclick="loadMailLogs()">🔄 Refresh Logs</button>
            </div>
            <div id="mailLogList"><div class="text-center text-muted" style="padding:3rem;">Loading mail logs...</div></div>
        </div>
    </div>

    <!-- Section 3: Live SMTP Settings -->
    <div id="sectionSmtp" style="display:none;">
        <div class="card" style="max-width:800px;margin:0 auto;">
            <div class="card-header">
                <h3 class="card-title">⚙️ Live Email & SMTP Configuration</h3>
            </div>
            <div style="padding:1.5rem;">
                <div style="padding:1rem;background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2);border-radius:8px;margin-bottom:1.5rem;font-size:0.85rem;line-height:1.6;color:var(--text-secondary);">
                    <strong style="color:var(--text-primary);display:block;margin-bottom:0.25rem;">💡 How to send real emails to your Gmail / Outlook inbox:</strong>
                    1. For <strong>Gmail</strong>: Go to your Google Account &rarr; Security &rarr; Enable <strong>2-Step Verification</strong> &rarr; Create an <strong>App Password</strong>.<br>
                    2. Paste your Gmail address in <strong>Username</strong> and the 16-character App Password in <strong>Password</strong>.<br>
                    3. Click <strong>Save SMTP Settings</strong>. Every triggered loan email will deliver directly to real recipient inboxes!
                </div>

                <!-- Provider Quick Buttons -->
                <div style="display:flex;gap:0.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
                    <span style="font-size:0.8rem;font-weight:600;align-self:center;margin-right:0.5rem;">Presets:</span>
                    <button type="button" class="btn btn-outline btn-sm" onclick="applyPreset('gmail')">Gmail (587 TLS)</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="applyPreset('outlook')">Outlook / Hotmail</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="applyPreset('mailtrap')">Mailtrap (Sandbox)</button>
                </div>

                <form id="smtpConfigForm" onsubmit="handleSaveSmtp(event)">
                    <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:1rem;margin-bottom:1rem;">
                        <div class="form-group">
                            <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:0.35rem;">SMTP Host</label>
                            <input type="text" id="smtpHost" class="form-control" value="smtp.gmail.com" required style="width:100%;padding:8px 12px;background:var(--bg-input);border:1px solid var(--border);border-radius:8px;color:var(--text-primary);">
                        </div>
                        <div class="form-group">
                            <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:0.35rem;">Port</label>
                            <input type="number" id="smtpPort" class="form-control" value="587" required style="width:100%;padding:8px 12px;background:var(--bg-input);border:1px solid var(--border);border-radius:8px;color:var(--text-primary);">
                        </div>
                        <div class="form-group">
                            <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:0.35rem;">Encryption</label>
                            <select id="smtpEncryption" class="form-control" style="width:100%;padding:8px 12px;background:var(--bg-input);border:1px solid var(--border);border-radius:8px;color:var(--text-primary);">
                                <option value="tls">TLS (STARTTLS)</option>
                                <option value="ssl">SSL</option>
                                <option value="">None (Plain)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
                        <div class="form-group">
                            <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:0.35rem;">SMTP Username / Email</label>
                            <input type="email" id="smtpUsername" class="form-control" placeholder="your-email@gmail.com" style="width:100%;padding:8px 12px;background:var(--bg-input);border:1px solid var(--border);border-radius:8px;color:var(--text-primary);">
                        </div>
                        <div class="form-group">
                            <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:0.35rem;">SMTP Password / App Password</label>
                            <input type="password" id="smtpPassword" class="form-control" placeholder="16-character App Password" style="width:100%;padding:8px 12px;background:var(--bg-input);border:1px solid var(--border);border-radius:8px;color:var(--text-primary);">
                            <small id="smtpPasswordHelp" style="font-size:0.75rem;color:var(--text-dim);"></small>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem;">
                        <div class="form-group">
                            <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:0.35rem;">Sender Name</label>
                            <input type="text" id="smtpFromName" class="form-control" value="CrediLens AI" style="width:100%;padding:8px 12px;background:var(--bg-input);border:1px solid var(--border);border-radius:8px;color:var(--text-primary);">
                        </div>
                        <div class="form-group">
                            <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:0.35rem;">Sender Email</label>
                            <input type="email" id="smtpFromEmail" class="form-control" value="notifications@credilens.ai" style="width:100%;padding:8px 12px;background:var(--bg-input);border:1px solid var(--border);border-radius:8px;color:var(--text-primary);">
                        </div>
                    </div>

                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
                        <span id="smtpStatusBadge" style="font-size:0.8rem;color:var(--text-muted);">Status: Checking configuration...</span>
                        <div style="display:flex;gap:0.75rem;">
                            <button type="submit" id="saveSmtpBtn" class="btn btn-primary btn-sm">💾 Save SMTP Settings</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Trigger Custom Email -->
<div class="modal-overlay" id="mailTriggerModal">
    <div class="modal" style="width:100%;max-width:520px;padding:1.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.15rem;font-weight:700;color:var(--text-primary);">📧 Dispatch / Trigger Email</h3>
            <button class="btn btn-icon btn-sm" onclick="closeMailTriggerModal()">✕</button>
        </div>
        <form id="mailTriggerForm" onsubmit="handleMailTriggerSubmit(event)">
            <div class="form-group" style="margin-bottom:1rem;">
                <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:0.35rem;">Email Template / Event</label>
                <select id="mailTemplateType" class="form-control" style="width:100%;padding:8px 12px;background:var(--bg-input);border:1px solid var(--border);border-radius:8px;color:var(--text-primary);">
                    <option value="approved">🎉 Loan Approved</option>
                    <option value="submitted">📥 Application Submitted</option>
                    <option value="rejected">⚠️ Application Rejected</option>
                    <option value="emi_reminder">⏰ EMI Due Reminder</option>
                    <option value="overdue">🚨 Overdue Payment Notice</option>
                    <option value="status_change">🔄 Status Update (Under Review)</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:1rem;">
                <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:0.35rem;">Recipient Email (Your Real Inbox)</label>
                <input type="email" id="mailRecipient" class="form-control" placeholder="user@gmail.com" required style="width:100%;padding:8px 12px;background:var(--bg-input);border:1px solid var(--border);border-radius:8px;color:var(--text-primary);">
            </div>
            <div class="form-group" style="margin-bottom:1rem;">
                <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:0.35rem;">Applicant / Customer Name</label>
                <input type="text" id="mailCustomerName" class="form-control" placeholder="e.g. John Doe" required style="width:100%;padding:8px 12px;background:var(--bg-input);border:1px solid var(--border);border-radius:8px;color:var(--text-primary);">
            </div>
            <div class="form-group" style="margin-bottom:1.5rem;">
                <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:0.35rem;">Loan Application Reference ID</label>
                <input type="text" id="mailAppRef" class="form-control" value="APP-<?= date('Ymd') ?>-001" style="width:100%;padding:8px 12px;background:var(--bg-input);border:1px solid var(--border);border-radius:8px;color:var(--text-primary);">
            </div>
            <div style="display:flex;justify-content:flex-end;gap:0.75rem;">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeMailTriggerModal()">Cancel</button>
                <button type="submit" id="submitMailBtn" class="btn btn-primary btn-sm">🚀 Trigger & Dispatch</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Email Preview Modal -->
<div class="modal-overlay" id="emailPreviewModal">
    <div class="modal" style="width:100%;max-width:700px;max-height:85vh;padding:1.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <div>
                <h3 id="previewModalSubject" style="margin:0;font-size:1.15rem;font-weight:700;color:var(--text-primary);">Email Preview</h3>
                <span id="previewModalMeta" style="font-size:0.75rem;color:var(--text-secondary);"></span>
            </div>
            <button class="btn btn-icon btn-sm" onclick="closeEmailPreviewModal()">✕</button>
        </div>
        <div style="flex:1;overflow:auto;background:#ffffff;border-radius:8px;padding:1rem;">
            <iframe id="previewIframe" style="width:100%;height:450px;border:none;"></iframe>
        </div>
    </div>
</div>

<script>
let allNotifications = [];
let cachedLogs = [];

async function loadNotifications() {
    try {
        const res = await fetch('api/admin.php?action=get_notifications');
        const data = await res.json();
        const list = document.getElementById('notifList');
        const count = document.getElementById('notifCount');
        allNotifications = data.notifications || [];
        
        if (allNotifications.length === 0) {
            list.innerHTML = '<div class="empty-state" style="padding:3rem;text-align:center;"><div class="icon" style="font-size:2rem;margin-bottom:0.5rem;">🔔</div><h3>No notifications</h3><p style="color:var(--text-secondary);font-size:0.875rem;">You\'re all caught up!</p></div>';
            count.textContent = '0 notifications';
            return;
        }
        count.textContent = `${data.unread} unread of ${data.total}`;
        renderNotifications(allNotifications);
    } catch (e) {
        document.getElementById('notifList').innerHTML = '<div class="text-center text-muted" style="padding:2rem;">Error loading notifications.</div>';
    }
}

function renderNotifications(items) {
    const list = document.getElementById('notifList');
    const icons = { success:'✅', error:'❌', warning:'⚠️', info:'ℹ️', reminder:'⏰' };
    list.innerHTML = items.map(n => `
        <div class="notification-item ${n.is_read?'':'unread'}" onclick="markRead(${n.id}, this)" style="display:flex;align-items:flex-start;gap:12px;padding:12px 16px;border-bottom:1px solid var(--border);cursor:pointer;">
            <div class="notif-icon ${n.type}" style="font-size:1.2rem;margin-top:2px;">${icons[n.type]||'🔔'}</div>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:${n.is_read?'500':'700'};color:var(--text-primary);margin-bottom:0.25rem;">${escHtml(n.title)}</div>
                <div style="font-size:0.825rem;color:var(--text-secondary);line-height:1.4;">${escHtml(n.message)}</div>
                <div style="font-size:0.7rem;color:var(--text-dim);margin-top:0.375rem;">${formatDate(n.created_at)}</div>
            </div>
            ${!n.is_read ? '<div style="width:8px;height:8px;background:var(--primary);border-radius:50%;flex-shrink:0;margin-top:6px;"></div>' : ''}
        </div>`).join('');
}

function filterNotifications() {
    const q = (document.getElementById('notifSearch').value || '').toLowerCase();
    const filtered = allNotifications.filter(n => 
        (n.title || '').toLowerCase().includes(q) || (n.message || '').toLowerCase().includes(q)
    );
    renderNotifications(filtered);
}

async function markRead(id, el) {
    const fd = new FormData(); fd.append('action','mark_read'); fd.append('id',id);
    await fetch('api/admin.php', {method:'POST',body:fd});
    if (el) el.classList.remove('unread');
}

async function markAllRead() {
    const fd = new FormData(); fd.append('action','mark_all_read');
    await fetch('api/admin.php', {method:'POST',body:fd});
    loadNotifications(); 
    showToast('All notifications marked as read','success');
}

function switchMailTab(tab) {
    const notifs = document.getElementById('sectionNotifs');
    const logs = document.getElementById('sectionLogs');
    const smtp = document.getElementById('sectionSmtp');
    const tabBtnNotifs = document.getElementById('tabBtnNotifs');
    const tabBtnLogs = document.getElementById('tabBtnLogs');
    const tabBtnSmtp = document.getElementById('tabBtnSmtp');

    [tabBtnNotifs, tabBtnLogs, tabBtnSmtp].forEach(b => {
        b.style.fontWeight = 'normal';
        b.style.borderBottom = 'none';
        b.style.color = 'var(--text-secondary)';
    });

    notifs.style.display = 'none';
    logs.style.display = 'none';
    smtp.style.display = 'none';

    if (tab === 'logs') {
        logs.style.display = 'block';
        tabBtnLogs.style.fontWeight = '700';
        tabBtnLogs.style.borderBottom = '2px solid var(--primary)';
        tabBtnLogs.style.color = 'var(--text-primary)';
        loadMailLogs();
    } else if (tab === 'smtp') {
        smtp.style.display = 'block';
        tabBtnSmtp.style.fontWeight = '700';
        tabBtnSmtp.style.borderBottom = '2px solid var(--primary)';
        tabBtnSmtp.style.color = 'var(--text-primary)';
        loadSmtpConfig();
    } else {
        notifs.style.display = 'block';
        tabBtnNotifs.style.fontWeight = '700';
        tabBtnNotifs.style.borderBottom = '2px solid var(--primary)';
        tabBtnNotifs.style.color = 'var(--text-primary)';
    }
}

async function loadMailLogs() {
    const container = document.getElementById('mailLogList');
    container.innerHTML = '<div class="text-center text-muted" style="padding:2rem;">Fetching dispatched email logs...</div>';
    
    try {
        const res = await fetch('api/mail.php?action=get_logs');
        const data = await res.json();
        cachedLogs = data.logs || [];
        
        if (cachedLogs.length === 0) {
            container.innerHTML = `
                <div class="empty-state" style="padding:3rem;text-align:center;">
                    <div style="font-size:2rem;margin-bottom:0.5rem;">📬</div>
                    <h3>No Emails Dispatched Yet</h3>
                    <p style="color:var(--text-secondary);font-size:0.875rem;">Click 'Trigger Email' or change an application status to generate notifications.</p>
                    <button class="btn btn-primary btn-sm" style="margin-top:1rem;" onclick="openMailTriggerModal()">Trigger First Email</button>
                </div>`;
            return;
        }

        container.innerHTML = cachedLogs.map((log, idx) => `
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--border);gap:12px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:36px;height:36px;border-radius:8px;background:rgba(99,102,241,0.12);color:var(--primary);display:flex;align-items:center;justify-content:center;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </div>
                    <div>
                        <div style="font-weight:700;color:var(--text-primary);font-size:0.9rem;">${escHtml(log.subject)}</div>
                        <div style="font-size:0.775rem;color:var(--text-secondary);">
                            To: <strong>${escHtml(log.to)}</strong> &bull; Sent: ${log.timestamp}
                            ${log.driver ? ` &bull; Via: <strong>${log.driver.toUpperCase()}</strong>` : ''}
                        </div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="font-size:0.75rem;padding:3px 8px;border-radius:12px;background:${log.sent ? 'rgba(16,185,129,0.15)' : 'rgba(59,130,246,0.15)'};color:${log.sent ? '#10b981' : '#3b82f6'};font-weight:600;">
                        ${log.sent ? (log.driver === 'smtp' ? '✓ Delivered via SMTP' : '✓ Delivered') : '📝 Logged (Local)'}
                    </span>
                    <button class="btn btn-outline btn-sm" onclick="previewEmailLog(${idx})">Preview HTML</button>
                </div>
            </div>
        `).join('');
    } catch (e) {
        container.innerHTML = '<div class="text-center text-muted" style="padding:2rem;">Failed to load email logs.</div>';
    }
}

async function loadSmtpConfig() {
    try {
        const res = await fetch('api/mail.php?action=get_smtp_config');
        const data = await res.json();
        if (data.success && data.config) {
            const c = data.config;
            document.getElementById('smtpHost').value = c.host || 'smtp.gmail.com';
            document.getElementById('smtpPort').value = c.port || 587;
            document.getElementById('smtpEncryption').value = c.encryption || 'tls';
            document.getElementById('smtpUsername').value = c.username || '';
            document.getElementById('smtpFromName').value = c.from_name || 'CrediLens AI';
            document.getElementById('smtpFromEmail').value = c.from_email || 'notifications@credilens.ai';

            const badge = document.getElementById('smtpStatusBadge');
            const help = document.getElementById('smtpPasswordHelp');
            if (c.username && c.has_password) {
                badge.innerHTML = '<span style="color:#10b981;font-weight:600;">● SMTP Configured</span> (Ready for live delivery)';
                help.textContent = 'Password is saved. Leave blank to keep current password.';
            } else {
                badge.innerHTML = '<span style="color:#f59e0b;font-weight:600;">○ SMTP Not Set</span> (Emails currently saved to local audit trail)';
                help.textContent = 'Enter your 16-character Google App Password.';
            }
        }
    } catch (e) {}
}

function applyPreset(preset) {
    if (preset === 'gmail') {
        document.getElementById('smtpHost').value = 'smtp.gmail.com';
        document.getElementById('smtpPort').value = '587';
        document.getElementById('smtpEncryption').value = 'tls';
    } else if (preset === 'outlook') {
        document.getElementById('smtpHost').value = 'smtp-mail.outlook.com';
        document.getElementById('smtpPort').value = '587';
        document.getElementById('smtpEncryption').value = 'tls';
    } else if (preset === 'mailtrap') {
        document.getElementById('smtpHost').value = 'sandbox.smtp.mailtrap.io';
        document.getElementById('smtpPort').value = '2525';
        document.getElementById('smtpEncryption').value = 'tls';
    }
}

async function handleSaveSmtp(e) {
    e.preventDefault();
    const btn = document.getElementById('saveSmtpBtn');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    const fd = new FormData();
    fd.append('action', 'save_smtp_config');
    fd.append('host', document.getElementById('smtpHost').value);
    fd.append('port', document.getElementById('smtpPort').value);
    fd.append('encryption', document.getElementById('smtpEncryption').value);
    fd.append('username', document.getElementById('smtpUsername').value);
    fd.append('password', document.getElementById('smtpPassword').value);
    fd.append('from_name', document.getElementById('smtpFromName').value);
    fd.append('from_email', document.getElementById('smtpFromEmail').value);

    try {
        const res = await fetch('api/mail.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            loadSmtpConfig();
        } else {
            showToast(data.message || 'Error saving SMTP', 'error');
        }
    } catch (e) {
        showToast('Connection error', 'error');
    }

    btn.disabled = false;
    btn.textContent = '💾 Save SMTP Settings';
}

function openMailTriggerModal() {
    document.getElementById('mailRecipient').value = "<?= htmlspecialchars($currentUser['email']) ?>";
    document.getElementById('mailCustomerName').value = "<?= htmlspecialchars($currentUser['full_name']) ?>";
    openModal('mailTriggerModal');
}

function closeMailTriggerModal() {
    closeModal('mailTriggerModal');
}

async function quickTriggerEmail(type) {
    const btn = event.target;
    btn.disabled = true;
    const oldText = btn.textContent;
    btn.textContent = 'Triggering...';

    const fd = new FormData();
    fd.append('action', 'trigger_test');
    fd.append('type', type);
    fd.append('to', "<?= htmlspecialchars($currentUser['email']) ?>");
    fd.append('name', "<?= htmlspecialchars($currentUser['full_name']) ?>");
    fd.append('application_id', 'APP-' + Math.floor(100000 + Math.random() * 900000));

    try {
        const res = await fetch('api/mail.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            loadNotifications();
        } else {
            showToast(data.message || 'Trigger failed', 'error');
        }
    } catch (e) {
        showToast('Network error triggering mail', 'error');
    }

    btn.disabled = false;
    btn.textContent = oldText;
}

async function handleMailTriggerSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('submitMailBtn');
    btn.disabled = true;
    btn.textContent = 'Sending...';

    const fd = new FormData();
    fd.append('action', 'trigger_test');
    fd.append('type', document.getElementById('mailTemplateType').value);
    fd.append('to', document.getElementById('mailRecipient').value);
    fd.append('name', document.getElementById('mailCustomerName').value);
    fd.append('application_id', document.getElementById('mailAppRef').value);

    try {
        const res = await fetch('api/mail.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeMailTriggerModal();
            loadNotifications();
            loadMailLogs();
        } else {
            showToast(data.message || 'Trigger failed', 'error');
        }
    } catch (e) {
        showToast('Connection error triggering mail', 'error');
    }

    btn.disabled = false;
    btn.textContent = '🚀 Trigger & Dispatch';
}

function previewEmailLog(idx) {
    const log = cachedLogs[idx];
    if (!log) return;
    document.getElementById('previewModalSubject').textContent = log.subject;
    document.getElementById('previewModalMeta').textContent = `To: ${log.to} | Dispatched: ${log.timestamp}`;
    const iframe = document.getElementById('previewIframe');
    iframe.srcdoc = log.body_html || '<p>No preview available</p>';
    openModal('emailPreviewModal');
}

function closeEmailPreviewModal() {
    closeModal('emailPreviewModal');
}

loadNotifications();
</script>
