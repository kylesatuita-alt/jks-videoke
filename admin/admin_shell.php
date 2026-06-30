<?php
// includes/admin_auth.php — include this at top of every admin page
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();
$user = getCurrentUser();
if ($user['role'] !== 'admin') {
    header('Location: ../home.php');
    exit();
}

// Helper: render sidebar + topbar
// Call: renderAdminShell($pageTitle, $activeLink)
function renderAdminShell(string $pageTitle, string $activeLink): void {
    global $user;
    $pages = [
        'dashboard'    => ['icon' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',          'label' => 'Dashboard',        'href' => 'dashboard.php'],
        'reservations' => ['icon' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>', 'label' => 'Reservations', 'href' => 'reservations.php'],
        'units'        => ['icon' => '<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/>',   'label' => 'Videoke Units',    'href' => 'units.php'],
        'users'        => ['icon' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',                             'label' => 'Users',            'href' => 'users.php'],
        'areas'        => ['icon' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',                       'label' => 'Delivery Areas',   'href' => 'areas.php'],
        'messages'     => ['icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',                                         'label' => 'Customer Messages','href' => 'messages.php'],
    ];
    $initial = strtoupper(substr($user['name'], 0, 1));
?>
<!-- ── MOBILE SIDEBAR OVERLAY ── -->
<div class="sidebar-mobile-overlay" id="sidebarOverlay"></div>

<!-- ── SIDEBAR ── -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-logo">
        <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="15" y="4" width="10" height="16" rx="5" fill="#F5C518"/>
            <path d="M9 20 C9 29 31 29 31 20" stroke="#F5C518" stroke-width="2" stroke-linecap="round" fill="none"/>
            <line x1="20" y1="28" x2="20" y2="34" stroke="#F5C518" stroke-width="2" stroke-linecap="round"/>
            <line x1="14" y1="34" x2="26" y2="34" stroke="#F5C518" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="sidebar-wordmark">JKS</span>
        <span class="sidebar-badge">Admin</span>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>
        <?php foreach ($pages as $key => $p): ?>
        <a href="<?= $p['href'] ?>" class="sidebar-link <?= $activeLink === $key ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <?= $p['icon'] ?>
            </svg>
            <?= $p['label'] ?>
        </a>
        <?php endforeach; ?>
        <div class="nav-section-label" style="margin-top:10px;">Site</div>
        <a href="../home.php" class="sidebar-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            View Live Site
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar"><?= htmlspecialchars($initial) ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= htmlspecialchars($user['name']) ?></div>
                <div class="sidebar-user-role">Administrator</div>
            </div>
        </div>
        <a href="../logout.php" class="sidebar-logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 0 2 2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Log Out
        </a>
    </div>
</aside>

<!-- ── MAIN ── -->
<div class="admin-main">
    <header class="admin-topbar">
        <div class="topbar-title">
            <button class="sidebar-toggle" id="sidebarToggle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <?= $pages[$activeLink]['icon'] ?? '<circle cx="12" cy="12" r="10"/>' ?>
            </svg>
            <?= htmlspecialchars($pageTitle) ?>
        </div>
        <div class="topbar-right" id="topbarRight">
            <!-- page-specific buttons injected here via JS or inline echo -->
        </div>
    </header>
    <div class="admin-content">
<?php
} // end renderAdminShell

function closeAdminShell(): void {
?>
    </div><!-- /.admin-content -->
</div><!-- /.admin-main -->

<div class="toast-container" id="toastContainer"></div>

<script>
// Sidebar toggle (mobile)
const sidebar = document.getElementById('adminSidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const sidebarToggle  = document.getElementById('sidebarToggle');
if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        sidebarOverlay.classList.toggle('open');
    });
    sidebarOverlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('open');
    });
}

// Toast helper
function toast(msg, type = 'success') {
    const t = document.createElement('div');
    t.className = `toast ${type === 'error' ? 'error' : ''}`;
    t.innerHTML = type === 'success'
        ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> ${msg}`
        : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> ${msg}`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => t.remove(), 2800);
}
</script>
<?php
} // end closeAdminShell