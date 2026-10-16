<?php
/**
 * CrediLens AI — Auth Page (Login / Register / Forgot Password)
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CrediLens AI — Secure login to your lending and credit assessment platform.">
    <title>Sign In | CrediLens AI</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="toast-container" id="toastContainer"></div>

<div class="auth-wrapper">
    <div class="auth-container">
        <div class="auth-logo">
            <div class="logo-icon">🏦</div>
            <h1>CrediLens AI</h1>
            <p>Smarter Decisions · Fairer Lending · Safer Tomorrow</p>
        </div>

        <!-- Tabs -->
        <div class="auth-tabs">
            <button class="auth-tab active" onclick="switchTab('login')" id="tab-login">Sign In</button>
            <button class="auth-tab" onclick="switchTab('register')" id="tab-register">Register</button>
            <button class="auth-tab" onclick="switchTab('forgot')" id="tab-forgot">Reset Password</button>
        </div>

        <div class="auth-card">
            <!-- Login Form -->
            <form class="auth-form active" id="form-login" onsubmit="handleLogin(event)">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-icon">✉️</span>
                        <input type="email" id="login-email" class="form-control" placeholder="you@example.com" required autocomplete="email">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="login-password" class="form-control" placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('login-password', this)">👁</button>
                    </div>
                </div>
                <div id="login-error" class="alert alert-error hidden"></div>
                <button type="submit" class="btn btn-primary btn-block btn-lg" id="login-btn">
                    Sign In
                </button>
                <div style="margin-top: 1.5rem; padding: 1rem; background: var(--bg-glass); border-radius: var(--radius-sm); border: 1px solid var(--border);">
                    <p style="font-size: 0.75rem; color: var(--text-muted); text-align: center; margin-bottom: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Demo Credentials</p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <?php
                        $demoUsers = [
                            ['role' => 'Applicant', 'email' => 'applicant@demo.com', 'color' => 'var(--accent)'],
                            ['role' => 'Loan Officer', 'email' => 'officer@demo.com', 'color' => 'var(--success)'],
                            ['role' => 'Risk Analyst', 'email' => 'analyst@demo.com', 'color' => 'var(--warning)'],
                            ['role' => 'Compliance', 'email' => 'compliance@demo.com', 'color' => '#a78bfa'],
                            ['role' => 'Admin', 'email' => 'admin@credilens.ai', 'color' => 'var(--error)'],
                        ];
                        foreach ($demoUsers as $u):
                        ?>
                        <button type="button" onclick="fillDemo('<?= $u['email'] ?>', 'Demo@123')" style="padding: 0.375rem 0.5rem; background: var(--bg-glass); border: 1px solid var(--border); border-radius: 6px; color: <?= $u['color'] ?>; font-size: 0.7rem; font-weight: 600; cursor: pointer; transition: all 0.2s; text-align: left; font-family: inherit;" onmouseover="this.style.borderColor='<?= $u['color'] ?>'" onmouseout="this.style.borderColor='var(--border)'">
                            <?= $u['role'] ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <p style="font-size: 0.68rem; color: var(--text-dim); text-align: center; margin-top: 0.5rem;">Password: <code style="background: var(--bg-glass); padding: 0.1rem 0.4rem; border-radius: 3px;">Demo@123</code></p>
                </div>
            </form>

            <!-- Register Form -->
            <form class="auth-form" id="form-register" onsubmit="handleRegister(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" id="reg-name" class="form-control" placeholder="Your full name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" id="reg-phone" class="form-control" placeholder="10-digit phone">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" id="reg-email" class="form-control" placeholder="you@example.com" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-icon">🔒</span>
                            <input type="password" id="reg-password" class="form-control" placeholder="Min. 8 characters" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('reg-password', this)">👁</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" id="reg-confirm" class="form-control" placeholder="Repeat password" required>
                    </div>
                </div>
                <div id="reg-error" class="alert alert-error hidden"></div>
                <button type="submit" class="btn btn-primary btn-block btn-lg" id="reg-btn">
                    Create Account
                </button>
                <p style="text-align: center; margin-top: 1rem; font-size: 0.8rem; color: var(--text-muted);">
                    Accounts are created as <strong style="color: var(--primary-light)">Applicant</strong> role by default.
                </p>
            </form>

            <!-- Forgot Password Form -->
            <div class="auth-form" id="form-forgot">
                <!-- Step 1: Enter email -->
                <div id="forgot-step1">
                    <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">Enter your registered email to receive an OTP.</p>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-icon">✉️</span>
                            <input type="email" id="forgot-email" class="form-control" placeholder="you@example.com">
                        </div>
                    </div>
                    <div id="forgot-error" class="alert alert-error hidden"></div>
                    <button type="button" class="btn btn-primary btn-block" onclick="handleForgotPassword()">Send OTP</button>
                </div>
                <!-- Step 2: Enter OTP + new password -->
                <div id="forgot-step2" class="hidden">
                    <div class="alert alert-success" id="otp-display"></div>
                    <div class="form-group">
                        <label class="form-label">OTP Code</label>
                        <input type="text" id="forgot-otp" class="form-control" placeholder="6-digit OTP" maxlength="6">
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" id="forgot-newpass" class="form-control" placeholder="New password (min. 8 chars)">
                    </div>
                    <div id="reset-error" class="alert alert-error hidden"></div>
                    <button type="button" class="btn btn-primary btn-block" onclick="handleResetPassword()">Reset Password</button>
                    <button type="button" class="btn btn-ghost btn-block" onclick="document.getElementById('forgot-step1').classList.remove('hidden'); document.getElementById('forgot-step2').classList.add('hidden');">← Back</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    document.getElementById('form-' + tab).classList.add('active');
}

function togglePassword(id, btn) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}

function fillDemo(email, pass) {
    document.getElementById('login-email').value = email;
    document.getElementById('login-password').value = pass;
    switchTab('login');
}

function showError(id, msg) {
    const el = document.getElementById(id);
    el.textContent = msg;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 5000);
}

async function handleLogin(e) {
    e.preventDefault();
    const btn = document.getElementById('login-btn');
    btn.classList.add('loading');
    btn.disabled = true;

    const data = new FormData();
    data.append('action', 'login');
    data.append('email', document.getElementById('login-email').value);
    data.append('password', document.getElementById('login-password').value);

    try {
        const res = await fetch('api/auth.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
            window.location.href = 'index.php';
        } else {
            showError('login-error', json.message);
        }
    } catch (err) {
        showError('login-error', 'Connection error. Please try again.');
    } finally {
        btn.classList.remove('loading');
        btn.disabled = false;
    }
}

async function handleRegister(e) {
    e.preventDefault();
    const btn = document.getElementById('reg-btn');
    btn.classList.add('loading');
    btn.disabled = true;

    const data = new FormData();
    data.append('action', 'register');
    data.append('full_name', document.getElementById('reg-name').value);
    data.append('email', document.getElementById('reg-email').value);
    data.append('phone', document.getElementById('reg-phone').value);
    data.append('password', document.getElementById('reg-password').value);
    data.append('confirm_password', document.getElementById('reg-confirm').value);

    try {
        const res = await fetch('api/auth.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.success) {
            switchTab('login');
            showToast(json.message, 'success');
        } else {
            showError('reg-error', json.message);
        }
    } catch (err) {
        showError('reg-error', 'Connection error. Please try again.');
    } finally {
        btn.classList.remove('loading');
        btn.disabled = false;
    }
}

async function handleForgotPassword() {
    const email = document.getElementById('forgot-email').value;
    if (!email) { showError('forgot-error', 'Email is required'); return; }

    const data = new FormData();
    data.append('action', 'forgot_password');
    data.append('email', email);

    const res = await fetch('api/auth.php', { method: 'POST', body: data });
    const json = await res.json();

    if (json.success) {
        document.getElementById('forgot-step1').classList.add('hidden');
        document.getElementById('forgot-step2').classList.remove('hidden');
        document.getElementById('otp-display').textContent = json.message;
    } else {
        showError('forgot-error', json.message);
    }
}

async function handleResetPassword() {
    const data = new FormData();
    data.append('action', 'reset_password');
    data.append('email', document.getElementById('forgot-email').value);
    data.append('otp', document.getElementById('forgot-otp').value);
    data.append('new_password', document.getElementById('forgot-newpass').value);

    const res = await fetch('api/auth.php', { method: 'POST', body: data });
    const json = await res.json();

    if (json.success) {
        switchTab('login');
        showToast(json.message, 'success');
    } else {
        showError('reset-error', json.message);
    }
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    toast.className = `toast ${type}`;
    toast.innerHTML = `<span>${icons[type] || 'ℹ️'}</span><span style="flex:1">${message}</span><button class="toast-close" onclick="this.parentElement.remove()">✕</button>`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}
</script>
</body>
</html>
