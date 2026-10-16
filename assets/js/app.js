/**
 * CrediLens AI — Main Application JavaScript
 * Global utilities, navigation, modal management, and helpers
 */

'use strict';

// ============ NAVIGATION ============
function navigateTo(page, params = {}) {
    let url = `?page=${page}`;
    Object.entries(params).forEach(([k, v]) => url += `&${k}=${encodeURIComponent(v)}`);
    window.location.href = url;
}

// Update sidebar active state
function updateSidebarActive(page) {
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.toggle('active', item.dataset.page === page);
    });
}

// Toggle sidebar on mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar?.classList.toggle('open');
    overlay?.classList.toggle('active');
}

// ============ AUTH ============
async function handleLogout() {
    try {
        const fd = new FormData();
        fd.append('action', 'logout');
        await fetch('api/auth.php', { method: 'POST', body: fd });
    } catch (e) {}
    window.location.href = '?page=login';
}

// ============ MODAL MANAGEMENT ============
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Close modal on overlay click
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay') && e.target.id !== 'sidebarOverlay') {
        closeModal(e.target.id);
    }
});

// Close modal on Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => {
            if (m.id !== 'sidebarOverlay') closeModal(m.id);
        });
    }
});

// ============ TOAST NOTIFICATIONS ============
function showToast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <span style="font-size:1.1rem;">${icons[type] || 'ℹ️'}</span>
        <span style="flex:1; font-size:0.875rem; font-weight:500;">${escHtml(message)}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
    `;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// ============ FORMATTING HELPERS ============
function formatAmount(amount) {
    const num = parseFloat(amount) || 0;
    if (num >= 10000000) return (num / 10000000).toFixed(2) + ' Cr';
    if (num >= 100000) return (num / 100000).toFixed(2) + ' L';
    return num.toLocaleString('en-IN');
}

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatDateShort(dateStr) {
    if (!dateStr) return 'N/A';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function escHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function debounce(fn, delay) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}

// ============ STATUS BADGE RENDERER ============
function renderStatusBadge(status) {
    const map = {
        draft:              { color: 'muted',   icon: '📝', label: 'Draft' },
        submitted:          { color: 'info',    icon: '📨', label: 'Submitted' },
        under_review:       { color: 'primary', icon: '🔍', label: 'Under Review' },
        document_verification: { color: 'primary', icon: '📄', label: 'Doc Verification' },
        clarification_required: { color: 'warning', icon: '💬', label: 'Clarification Req.' },
        risk_assessment:    { color: 'warning', icon: '🎯', label: 'Risk Assessment' },
        compliance_check:   { color: 'info',    icon: '⚖️', label: 'Compliance Check' },
        approved:           { color: 'success', icon: '✅', label: 'Approved' },
        rejected:           { color: 'error',   icon: '❌', label: 'Rejected' },
        disbursement_pending: { color: 'warning', icon: '⏳', label: 'Disbursement Pending' },
        disbursed:          { color: 'success', icon: '💰', label: 'Disbursed' },
        closed:             { color: 'muted',   icon: '🔒', label: 'Closed' },
        cancelled:          { color: 'error',   icon: '🚫', label: 'Cancelled' },
    };

    const s = map[status] || { color: 'muted', icon: '❓', label: status };
    return `<span class="badge badge-${s.color}"><span class="status-dot ${s.color === 'success' ? 'green' : s.color === 'error' ? 'red' : s.color === 'warning' ? 'yellow' : s.color === 'info' ? 'blue' : 'purple'}"></span>${s.label}</span>`;
}

// ============ APPLICATION DETAIL VIEW ============
async function viewApplication(applicationId) {
    // Navigate to detail page or open detail modal
    window.appDetailId = applicationId;
    navigateTo('application-detail', { app_id: applicationId });
}

// ============ CHART.JS DEFAULT CONFIG ============
if (typeof Chart !== 'undefined') {
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(17,17,39,0.95)';
    Chart.defaults.plugins.tooltip.borderColor = 'rgba(255,255,255,0.08)';
    Chart.defaults.plugins.tooltip.borderWidth = 1;
    Chart.defaults.plugins.tooltip.padding = 12;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
}

// ============ DRAG & DROP FILE UPLOAD ============
function initFileDropzone(zoneId, inputId, callback) {
    const zone = document.getElementById(zoneId);
    if (!zone) return;

    zone.addEventListener('dragover', (e) => {
        e.preventDefault();
        zone.classList.add('dragover');
    });

    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));

    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0 && callback) callback(files[0]);
    });

    zone.addEventListener('click', () => document.getElementById(inputId)?.click());
}

// ============ INITIALIZE ON PAGE LOAD ============
document.addEventListener('DOMContentLoaded', () => {
    // Mark active sidebar item
    const currentPage = new URLSearchParams(window.location.search).get('page') || 'dashboard';
    updateSidebarActive(currentPage);

    // Animate stat cards on scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('slide-up');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.stat-card').forEach(card => observer.observe(card));

    // Number counter animation
    animateCounters();
});

function animateCounters() {
    document.querySelectorAll('[data-counter]').forEach(el => {
        const target = parseFloat(el.dataset.counter);
        const duration = 1200;
        const start = performance.now();

        const animate = (time) => {
            const elapsed = time - start;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.floor(eased * target).toLocaleString('en-IN');
            if (progress < 1) requestAnimationFrame(animate);
        };
        requestAnimationFrame(animate);
    });
}

// ============ GLOBAL API HELPER ============
async function apiPost(endpoint, data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    const res = await fetch(endpoint, { method: 'POST', body: fd });
    return res.json();
}

async function apiGet(endpoint, params = {}) {
    const qs = new URLSearchParams(params).toString();
    const res = await fetch(endpoint + (qs ? '?' + qs : ''));
    return res.json();
}

// ============ LOAN CALCULATION HELPERS ============
function calcEMI(principal, annualRate, tenureMonths) {
    const r = (annualRate / 100) / 12;
    if (r === 0) return principal / tenureMonths;
    return (principal * r * Math.pow(1 + r, tenureMonths)) / (Math.pow(1 + r, tenureMonths) - 1);
}

// ============ COPY TO CLIPBOARD ============
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => showToast('Copied to clipboard!', 'success'));
}

// ============ PRINT HELPER ============
function printSection(id) {
    const content = document.getElementById(id)?.innerHTML;
    if (!content) return;
    const win = window.open('', '_blank');
    win.document.write(`
        <html><head><title>Print</title>
        <style>body{font-family:Arial,sans-serif;color:#333;padding:2rem;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ddd;padding:8px;text-align:left;}</style>
        </head><body>${content}</body></html>`);
    win.document.close();
    win.print();
}

// ============ LOCAL STORAGE HELPERS ============
function saveLocal(key, data) {
    try { localStorage.setItem('cl_' + key, JSON.stringify(data)); } catch (e) {}
}

function loadLocal(key, defaultVal = null) {
    try {
        const d = localStorage.getItem('cl_' + key);
        return d ? JSON.parse(d) : defaultVal;
    } catch (e) { return defaultVal; }
}

// ============ QUICK ASSIGN (stub) ============
function quickAssign(appId) {
    showToast('Assignment panel coming in next update', 'info');
}

console.log('%c CrediLens AI %c v1.0 %c Lending Platform', 
    'background:#6366f1;color:white;padding:4px 8px;border-radius:4px 0 0 4px;font-weight:bold',
    'background:#06b6d4;color:white;padding:4px 8px',
    'background:transparent;color:#94a3b8;padding:4px 8px;font-size:0.8em');
