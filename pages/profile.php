<?php
/**
 * Profile Page — User can view/edit their own profile
 */
requireLogin();
$currentUser = getCurrentUser();
?>
<div class="fade-in">
    <div class="grid-sidebar">
        <!-- Profile Card -->
        <div>
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title">👤 My Profile</h3></div>
                <div class="card-body">
                    <form id="profileForm" onsubmit="updateProfile(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <input type="text" id="prof-name" class="form-control" value="<?= htmlspecialchars($currentUser['full_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?= htmlspecialchars($currentUser['email']) ?>" readonly style="opacity:0.6;">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="tel" id="prof-phone" class="form-control" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control" value="<?= ucwords(str_replace('_', ' ', $currentUser['role'])) ?>" readonly style="opacity:0.6;">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">🔒 Change Password</h3></div>
                <div class="card-body">
                    <form id="passwordForm" onsubmit="changePassword(event)">
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" id="current-pass" class="form-control" placeholder="Enter current password" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" id="new-pass" class="form-control" placeholder="Min. 8 characters" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" id="confirm-pass" class="form-control" placeholder="Repeat new password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-warning">Update Password</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar Info -->
        <div>
            <div class="card mb-3">
                <div class="card-body" style="text-align:center; padding:2rem;">
                    <div class="user-avatar" style="width:80px;height:80px;font-size:2rem;margin:0 auto 1rem;">
                        <?= strtoupper(substr($currentUser['full_name'], 0, 2)) ?>
                    </div>
                    <h3 style="font-weight:700; margin-bottom:0.25rem;"><?= htmlspecialchars($currentUser['full_name']) ?></h3>
                    <p style="color:var(--text-muted); font-size:0.875rem;"><?= htmlspecialchars($currentUser['email']) ?></p>
                    <span class="badge badge-primary" style="margin-top:0.5rem;"><?= ucwords(str_replace('_', ' ', $currentUser['role'])) ?></span>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">📊 Account Info</h3></div>
                <div class="card-body">
                    <div style="display:grid; gap:1rem;">
                        <div>
                            <div style="font-size:0.7rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:0.05em;">Member Since</div>
                            <div style="font-weight:600;"><?= date('d M Y', strtotime($currentUser['created_at'])) ?></div>
                        </div>
                        <div>
                            <div style="font-size:0.7rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:0.05em;">Last Login</div>
                            <div style="font-weight:600;"><?= $currentUser['last_login'] ? date('d M Y, H:i', strtotime($currentUser['last_login'])) : 'N/A' ?></div>
                        </div>
                        <div>
                            <div style="font-size:0.7rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:0.05em;">Account Status</div>
                            <div><span class="badge badge-success"><span class="status-dot green"></span> Active</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function updateProfile(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'update_profile');
    fd.append('full_name', document.getElementById('prof-name').value);
    fd.append('phone', document.getElementById('prof-phone').value);
    const res = await fetch('api/auth.php', {method:'POST', body:fd});
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
}

async function changePassword(e) {
    e.preventDefault();
    const newPass = document.getElementById('new-pass').value;
    const confirmPass = document.getElementById('confirm-pass').value;
    if (newPass !== confirmPass) { showToast('Passwords do not match', 'warning'); return; }
    if (newPass.length < 8) { showToast('Password must be at least 8 characters', 'warning'); return; }

    const fd = new FormData();
    fd.append('action', 'update_profile');
    fd.append('current_password', document.getElementById('current-pass').value);
    fd.append('new_password', newPass);
    const res = await fetch('api/auth.php', {method:'POST', body:fd});
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) document.getElementById('passwordForm').reset();
}
</script>
