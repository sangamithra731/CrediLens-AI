<?php
/**
 * CrediLens AI — Main Entry Point
 * Handles routing between auth pages and the main application
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$page = $_GET['page'] ?? '';

// If logged in and no specific page, go to dashboard
if (isLoggedIn() && empty($page)) {
    $page = 'dashboard';
}

// Auth pages (login, register, forgot password)
if (!isLoggedIn() || in_array($page, ['login', 'register', 'forgot_password'])) {
    if (isLoggedIn() && in_array($page, ['login', 'register'])) {
        header('Location: ?page=dashboard');
        exit;
    }
    include __DIR__ . '/pages/auth.php';
    exit;
}

// Main application layout
$currentUser = getCurrentUser();
$unreadCount = getUnreadNotificationCount();
$userRole = $currentUser['role'];

// Role-based navigation
$navItems = getNavItems($userRole);
$pageTitle = getPageTitle($page);

// SVG icon library for nav items
function getNavSVG($id) {
    $icons = [
        'dashboard'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
        'my-applications'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        'new-application'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
        'all-applications' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
        'review-queue'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9,11 12,14 22,4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
        'documents'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13,2 13,9 20,9"/></svg>',
        'risk-assessment'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'loan-management'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        'notifications'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
        'reports'          => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        'admin-users'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'admin-policies'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>',
        'audit-logs'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9,12 11,14 15,10"/></svg>',
    ];
    return $icons[$id] ?? $icons['dashboard'];
}

function getNavItems($role) {
    $items = [
        'main' => [
            ['id' => 'dashboard',        'label' => 'Dashboard',         'roles' => ['applicant','loan_officer','risk_analyst','compliance_officer','admin']],
        ],
        'applications' => [
            ['id' => 'my-applications',  'label' => 'My Applications',   'roles' => ['applicant']],
            ['id' => 'new-application',  'label' => 'New Application',   'roles' => ['applicant']],
            ['id' => 'all-applications', 'label' => 'All Applications',  'roles' => ['loan_officer','risk_analyst','compliance_officer','admin']],
            ['id' => 'review-queue',     'label' => 'Review Queue',      'roles' => ['loan_officer','risk_analyst','compliance_officer','admin']],
        ],
        'processing' => [
            ['id' => 'documents',        'label' => 'Documents',         'roles' => ['applicant','loan_officer','admin']],
            ['id' => 'risk-assessment',  'label' => 'Risk Assessment',   'roles' => ['risk_analyst','loan_officer','admin']],
            ['id' => 'loan-management',  'label' => 'Loan Management',   'roles' => ['applicant','loan_officer','admin']],
        ],
        'tracking' => [
            ['id' => 'notifications',    'label' => 'Notifications',     'roles' => ['applicant','loan_officer','risk_analyst','compliance_officer','admin']],
            ['id' => 'reports',          'label' => 'Reports',           'roles' => ['loan_officer','risk_analyst','compliance_officer','admin']],
        ],
        'admin' => [
            ['id' => 'admin-users',      'label' => 'User Management',   'roles' => ['admin']],
            ['id' => 'admin-policies',   'label' => 'Lending Policies',  'roles' => ['admin','compliance_officer']],
            ['id' => 'audit-logs',       'label' => 'Audit Logs',        'roles' => ['admin','compliance_officer']],
        ]
    ];
    
    $filtered = [];
    foreach ($items as $section => $sectionItems) {
        $filteredItems = array_filter($sectionItems, fn($item) => in_array($role, $item['roles']));
        if (!empty($filteredItems)) {
            $filtered[$section] = array_values($filteredItems);
        }
    }
    return $filtered;
}

function getPageTitle($page) {
    $titles = [
        'dashboard'          => 'Dashboard',
        'my-applications'    => 'My Applications',
        'new-application'    => 'New Loan Application',
        'all-applications'   => 'All Applications',
        'review-queue'       => 'Review Queue',
        'documents'          => 'Documents',
        'risk-assessment'    => 'Risk Assessment',
        'loan-management'    => 'Loan Management',
        'notifications'      => 'Notifications',
        'reports'            => 'Reports & Analytics',
        'admin-users'        => 'User Management',
        'admin-policies'     => 'Lending Policies',
        'audit-logs'         => 'Audit Logs',
        'application-detail' => 'Application Details',
        'profile'            => 'My Profile',
    ];
    return $titles[$page] ?? 'Dashboard';
}

$sectionLabels = [
    'main'         => 'Overview',
    'applications' => 'Applications',
    'processing'   => 'Processing',
    'tracking'     => 'Monitoring',
    'admin'        => 'Administration'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CrediLens AI — AI-assisted lending and credit assessment platform with document verification, risk scoring, and compliance management.">
    <title><?= htmlspecialchars($pageTitle) ?> | CrediLens AI</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Apply saved theme before page renders to prevent flash
        (function() {
            var t = localStorage.getItem('cl_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
</head>
<body>
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <div class="app-layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 22V12a9 9 0 0 1 18 0v10"/>
                        <path d="M12 17v5"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </div>
                <div class="sidebar-brand">
                    <h2>CrediLens AI</h2>
                    <span>Lending Platform</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <?php foreach ($navItems as $section => $items): ?>
                <div class="nav-section">
                    <div class="nav-section-title"><?= $sectionLabels[$section] ?? ucfirst($section) ?></div>
                    <?php foreach ($items as $item): ?>
                    <button class="nav-item <?= $page === $item['id'] ? 'active' : '' ?>" 
                            onclick="navigateTo('<?= $item['id'] ?>')"
                            data-page="<?= $item['id'] ?>">
                        <span class="nav-icon icon" style="width:20px;height:20px;">
                            <?= getNavSVG($item['id']) ?>
                        </span>
                        <span><?= $item['label'] ?></span>
                        <?php if ($item['id'] === 'notifications' && $unreadCount > 0): ?>
                        <span class="nav-badge"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </nav>
            
            <div class="sidebar-footer">
                <div class="sidebar-user" onclick="navigateTo('profile')">
                    <div class="user-avatar"><?= strtoupper(substr($currentUser['full_name'], 0, 2)) ?></div>
                    <div class="user-info">
                        <div class="name"><?= htmlspecialchars($currentUser['full_name']) ?></div>
                        <div class="role"><?= ucwords(str_replace('_', ' ', $currentUser['role'])) ?></div>
                    </div>
                </div>
                <button class="nav-item" onclick="handleLogout()" style="margin-top: 0.5rem; color: var(--error-light);">
                    <span class="nav-icon icon" style="width:20px;height:20px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16,17 21,12 16,7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                    </span>
                    <span>Sign Out</span>
                </button>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <line x1="3" y1="6" x2="21" y2="6"/>
                            <line x1="3" y1="12" x2="21" y2="12"/>
                            <line x1="3" y1="18" x2="21" y2="18"/>
                        </svg>
                    </button>
                    <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
                </div>
                <div class="top-bar-right">
                    <!-- Theme Toggle -->
                    <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Toggle light/dark theme">
                        <svg id="iconSun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                            <circle cx="12" cy="12" r="5"/>
                            <line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                            <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                        </svg>
                        <svg id="iconMoon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                        </svg>
                    </button>
                    <!-- AI Assistant -->
                    <button class="top-bar-btn" onclick="toggleAiChat()" title="CrediLens AI Assistant" style="position: relative;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1.27A7 7 0 0 1 14 22h-4a7 7 0 0 1-6.73-5H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2z"/>
                            <circle cx="10" cy="14" r="1.5" fill="currentColor"/>
                            <circle cx="14" cy="14" r="1.5" fill="currentColor"/>
                        </svg>
                        <span style="position:absolute;top:4px;right:4px;width:7px;height:7px;background:#10b981;border-radius:50%;box-shadow:0 0 6px #10b981;"></span>
                    </button>
                    <!-- Notifications -->
                    <button class="top-bar-btn" onclick="navigateTo('notifications')" title="Notifications">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        <?php if ($unreadCount > 0): ?>
                        <span class="notification-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
                        <?php endif; ?>
                    </button>
                    <!-- Profile -->
                    <button class="top-bar-btn" onclick="navigateTo('profile')" title="Profile">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </button>
                    <!-- Logout -->
                    <button class="top-bar-btn" onclick="handleLogout()" title="Logout">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16,17 21,12 16,7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                    </button>
                </div>
            </header>

            <!-- Page Content -->
            <div class="page-content" id="pageContent">
                <?php
                $pageFile = __DIR__ . '/pages/' . str_replace('-', '_', $page) . '.php';
                if (file_exists($pageFile)) {
                    include $pageFile;
                } else {
                    include __DIR__ . '/pages/dashboard.php';
                }
                ?>
            </div>
        </main>
    </div>

    <!-- Sidebar overlay for mobile -->
    <div class="modal-overlay" id="sidebarOverlay" onclick="toggleSidebar()" style="z-index: 99; background: rgba(0,0,0,0.5); backdrop-filter: none;"></div>

    <script src="assets/js/app.js"></script>
    <script>
    // Theme toggle logic
    function toggleTheme() {
        var current = document.documentElement.getAttribute('data-theme') || 'dark';
        var next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('cl_theme', next);
        updateThemeIcon(next);
    }
    function updateThemeIcon(theme) {
        document.getElementById('iconSun').style.display  = theme === 'light' ? 'block' : 'none';
        document.getElementById('iconMoon').style.display = theme === 'dark'  ? 'block' : 'none';
    }
    // Init icon state
    updateThemeIcon(document.documentElement.getAttribute('data-theme') || 'dark');
    </script>

    <!-- Floating CrediLens AI Assistant -->
    <?php include __DIR__ . '/includes/ai_widget.php'; ?>
</body>
</html>
