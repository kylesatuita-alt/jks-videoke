<?php
require_once '../includes/admin_auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'set_role') {
        $id   = (int)$_POST['id'];
        $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
        if (!$id) { echo json_encode(['success'=>false,'message'=>'Invalid ID.']); exit(); }
        if ($id == $user['id']) { echo json_encode(['success'=>false,'message'=>"You can't change your own role."]); exit(); }
        $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role, $id]);
        echo json_encode(['success'=>true,'message'=>'Role updated to '.ucfirst($role).'.']);
        exit();
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id == $user['id']) { echo json_encode(['success'=>false,'message'=>"You can't delete yourself."]); exit(); }
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true,'message'=>'User deleted.']);
        exit();
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action.']); exit();
}

// ── Fetch users with reservation count ──────
$users = $pdo->query("
    SELECT u.*, COUNT(r.id) AS res_count
    FROM users u
    LEFT JOIN reservations r ON r.user_id = u.id
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Users · JKS Videoke</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="admin-layout">
<?php require_once 'admin_shell.php'; renderAdminShell('Users', 'users'); ?>

<div class="section-header">
    <div class="section-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        All Users
    </div>
    <span class="section-count"><?= count($users) ?> registered</span>
</div>

<div class="table-wrap">
    <div class="table-toolbar">
        <div class="search-input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="search-input" id="userSearch" placeholder="Search name, email, phone…">
        </div>
        <select class="filter-select" id="roleFilter">
            <option value="all">All Roles</option>
            <option value="user">Users</option>
            <option value="admin">Admins</option>
        </select>
    </div>

    <table class="data-table" id="userTable">
        <thead>
            <tr>
                <th>Name</th>
                <th class="mobile-hide">Email</th>
                <th class="mobile-hide">Phone</th>
                <th class="mobile-hide">Location</th>
                <th>Role</th>
                <th>Reservations</th>
                <th class="mobile-hide">Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="userBody">
        <?php foreach ($users as $u): ?>
        <tr data-id="<?= $u['id'] ?>"
            data-role="<?= $u['role'] ?>"
            data-search="<?= strtolower($u['name'].' '.$u['email'].' '.$u['phone']) ?>"
            data-name="<?= htmlspecialchars($u['name']) ?>"
            data-email="<?= htmlspecialchars($u['email']) ?>"
            data-phone="<?= htmlspecialchars($u['phone']) ?>"
            data-location="<?= htmlspecialchars($u['location']) ?>"
            data-joined="<?= date('M j, Y', strtotime($u['created_at'])) ?>"
            data-rescount="<?= $u['res_count'] ?>">
            <td>
                <div style="display:flex; align-items:center; gap:9px;">
                    <div style="width:30px; height:30px; border-radius:50%; background:var(--gold); color:#0A1520; font-size:12px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <?= strtoupper(substr($u['name'],0,1)) ?>
                    </div>
                    <div class="td-name"><?= htmlspecialchars($u['name']) ?></div>
                </div>
            </td>
            <td class="td-muted mobile-hide"><?= htmlspecialchars($u['email']) ?></td>
            <td class="td-muted mobile-hide"><?= htmlspecialchars($u['phone']) ?></td>
            <td class="td-muted mobile-hide" style="max-width:160px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($u['location']) ?></td>
            <td>
                <span class="badge <?= $u['role'] === 'admin' ? 'active' : 'returned' ?>" style="text-transform:capitalize;">
                    <?= ucfirst($u['role']) ?>
                </span>
            </td>
            <td style="font-weight:700; color:<?= $u['res_count'] > 0 ? 'var(--gold)' : 'var(--muted)' ?>;"><?= $u['res_count'] ?></td>
            <td class="td-muted mobile-hide"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
            <td>
                <div class="action-btns">
                    <button class="btn-icon" title="View details" onclick="openUserDetail(this.closest('tr'))">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                    <?php if ($u['id'] != $user['id']): ?>
                    <button class="btn-icon danger" title="Delete user" onclick="openUserDelete(this.closest('tr'))">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                    </button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ── USER DETAIL MODAL ── -->
<div class="modal-overlay" id="userDetailModal">
    <div class="modal" style="max-width:420px;">
        <div class="modal-header">
            <div>
                <div class="modal-eyebrow">User Profile</div>
                <div class="modal-heading" id="userDetailName">—</div>
            </div>
            <button class="modal-close" onclick="closeModal('userDetailModal')">&#215;</button>
        </div>
        <div id="userDetailBody"></div>
    </div>
</div>

<!-- ── DELETE USER MODAL ── -->
<div class="modal-overlay" id="userDeleteModal">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <div><div class="modal-eyebrow">Confirm</div><div class="modal-heading">Delete User?</div></div>
            <button class="modal-close" onclick="closeModal('userDeleteModal')">&#215;</button>
        </div>
        <p style="color:var(--muted); font-size:13px; margin-bottom:20px; line-height:1.6;">
            Deleting <strong id="userDeleteLabel" style="color:var(--text)"></strong> will remove their account and all their reservations. This cannot be undone.
        </p>
        <button class="btn-danger" id="userDeleteBtn" onclick="confirmUserDelete()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
            Yes, Delete User
        </button>
        <button class="btn-submit" style="margin-top:8px; background:var(--bg-elevated); color:var(--text); border:1px solid var(--border);" onclick="closeModal('userDeleteModal')">Cancel</button>
    </div>
</div>

<?php closeAdminShell(); ?>
</div>

<script>
let activeUserRow = null;
const currentAdminId = <?= $user['id'] ?>;

// ── Filter ──
document.getElementById('userSearch').addEventListener('input', filterUsers);
document.getElementById('roleFilter').addEventListener('change', filterUsers);
function filterUsers() {
    const q = document.getElementById('userSearch').value.toLowerCase();
    const r = document.getElementById('roleFilter').value;
    document.querySelectorAll('#userBody tr[data-id]').forEach(row => {
        const ms = row.dataset.search.includes(q);
        const mr = r === 'all' || row.dataset.role === r;
        row.style.display = ms && mr ? '' : 'none';
    });
}

// ── Modal helpers ──
function openModal(id)  { document.getElementById(id).classList.add('open'); document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow=''; }
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) { m.classList.remove('open'); document.body.style.overflow=''; } }));

// ── User Detail ──
function openUserDetail(row) {
    activeUserRow = row;
    const d = row.dataset;
    const isSelf = d.id == currentAdminId;
    document.getElementById('userDetailName').textContent = d.name;
    document.getElementById('userDetailBody').innerHTML = `
        <div class="detail-row">
            <div class="detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
            <div><div class="detail-label">Email</div><div class="detail-value">${d.email}</div></div>
        </div>
        <div class="detail-row">
            <div class="detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.63 3.18 2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.96a16 16 0 0 0 6.13 6.13l1.02-.97a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
            <div><div class="detail-label">Phone</div><div class="detail-value">${d.phone || '—'}</div></div>
        </div>
        <div class="detail-row">
            <div class="detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
            <div><div class="detail-label">Location</div><div class="detail-value">${d.location || '—'}</div></div>
        </div>
        <div class="detail-row">
            <div class="detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
            <div><div class="detail-label">Total Reservations</div><div class="detail-value">${d.rescount}</div></div>
        </div>
        <div class="detail-row">
            <div class="detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
            <div><div class="detail-label">Member Since</div><div class="detail-value">${d.joined}</div></div>
        </div>
        ${!isSelf ? `
        <div class="form-section-label" style="margin-top:16px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Role Management
        </div>
        <div class="form-group">
            <label>User Role</label>
            <select id="roleSelect">
                <option value="user" ${d.role==='user'?'selected':''}>User</option>
                <option value="admin" ${d.role==='admin'?'selected':''}>Admin</option>
            </select>
        </div>
        <button class="btn-submit" onclick="saveRole()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Save Role
        </button>` : `<p style="font-size:12px; color:var(--muted); margin-top:14px; text-align:center;">(This is your account)</p>`}
    `;
    openModal('userDetailModal');
}

async function saveRole() {
    const role = document.getElementById('roleSelect').value;
    const fd = new FormData();
    fd.append('action', 'set_role');
    fd.append('id', activeUserRow.dataset.id);
    fd.append('role', role);
    try {
        const res  = await fetch('users.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            activeUserRow.dataset.role = role;
            closeModal('userDetailModal');
            toast(data.message);
            setTimeout(() => location.reload(), 900);
        } else { toast(data.message, 'error'); }
    } catch { toast('Network error.', 'error'); }
}

// ── Delete ──
function openUserDelete(row) {
    activeUserRow = row;
    document.getElementById('userDeleteLabel').textContent = row.dataset.name;
    openModal('userDeleteModal');
}
async function confirmUserDelete() {
    const btn = document.getElementById('userDeleteBtn');
    btn.disabled = true; btn.textContent = 'Deleting…';
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', activeUserRow.dataset.id);
    try {
        const res  = await fetch('users.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            activeUserRow.style.opacity = '0'; activeUserRow.style.transition = 'opacity 0.3s';
            setTimeout(() => activeUserRow.remove(), 320);
            closeModal('userDeleteModal'); toast(data.message);
        } else { toast(data.message, 'error'); }
    } catch { toast('Network error.', 'error'); }
    btn.disabled = false; btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg> Yes, Delete User';
}
</script>
</body>
</html>