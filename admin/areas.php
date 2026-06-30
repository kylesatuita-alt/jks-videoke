<?php
require_once '../includes/admin_auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $province = trim($_POST['province'] ?? 'Carcar');
        $barangay = trim($_POST['barangay'] ?? '');
        $sitio    = trim($_POST['sitio']    ?? '');
        $fee      = (float)($_POST['delivery_fee'] ?? 0);
        if (!$barangay || !$sitio || $fee <= 0) {
            echo json_encode(['success'=>false,'message'=>'Barangay, sitio, and price are required.']); exit();
        }
        if ($id) {
            $pdo->prepare("UPDATE delivery_areas SET province=?,barangay=?,sitio=?,delivery_fee=? WHERE id=?")
                ->execute([$province,$barangay,$sitio,$fee,$id]);
            echo json_encode(['success'=>true,'message'=>'Area updated.']);
        } else {
            $pdo->prepare("INSERT INTO delivery_areas (province,barangay,sitio,delivery_fee) VALUES (?,?,?,?)")
                ->execute([$province,$barangay,$sitio,$fee]);
            echo json_encode(['success'=>true,'message'=>'Area added.']);
        }
        exit();
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM delivery_areas WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true,'message'=>'Area deleted.']);
        exit();
    }
    echo json_encode(['success'=>false,'message'=>'Unknown action.']); exit();
}

// ── Fetch areas grouped ──────────────────────
$areas = $pdo->query("SELECT * FROM delivery_areas ORDER BY barangay ASC, sitio ASC")->fetchAll();
$grouped = [];
foreach ($areas as $a) {
    $grouped[$a['barangay']][] = $a;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Delivery Areas · JKS Videoke</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="admin-layout">
<?php require_once 'admin_shell.php'; renderAdminShell('Delivery Areas', 'areas'); ?>

<div class="section-header">
    <div class="section-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        Delivery Areas & Pricing
    </div>
    <div style="display:flex; align-items:center; gap:10px;">
        <span class="section-count"><?= count($areas) ?> areas</span>
        <button class="topbar-btn" onclick="openAreaModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Area
        </button>
    </div>
</div>

<!-- Info note -->
<div style="background:rgba(245,197,24,0.06); border:1px solid var(--gold-border); border-radius:var(--radius-md); padding:12px 16px; margin-bottom:22px; font-size:13px; color:var(--muted); display:flex; align-items:flex-start; gap:10px;">
    <svg viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="2" style="width:16px; height:16px; flex-shrink:0; margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span>The <strong style="color:var(--text);">Price</strong> column is the <strong style="color:var(--gold);">all-in total</strong> a customer pays — it covers the rental and delivery to that sitio. There is no separate rental fee.</span>
</div>

<div class="table-toolbar" style="background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-md) var(--radius-md) 0 0; border-bottom:none;">
    <div class="search-input-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" class="search-input" id="areaSearch" placeholder="Search barangay or sitio…">
    </div>
    <select class="filter-select" id="brgyFilter">
        <option value="all">All Barangays</option>
        <?php foreach (array_keys($grouped) as $b): ?>
        <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="table-wrap" style="border-radius: 0 0 var(--radius-md) var(--radius-md);">
    <table class="data-table">
        <thead>
            <tr>
                <th>Barangay</th>
                <th>Sitio / Purok</th>
                <th>All-In Price</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="areaBody">
        <?php if (empty($areas)): ?>
            <tr><td colspan="4"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><h3>No delivery areas</h3><p>Add your first delivery area.</p></div></td></tr>
        <?php else: ?>
            <?php foreach ($areas as $a): ?>
            <tr data-id="<?= $a['id'] ?>"
                data-search="<?= strtolower($a['barangay'].' '.$a['sitio']) ?>"
                data-brgy="<?= htmlspecialchars($a['barangay']) ?>"
                data-sitio="<?= htmlspecialchars($a['sitio']) ?>"
                data-province="<?= htmlspecialchars($a['province']) ?>"
                data-fee="<?= $a['delivery_fee'] ?>">
                <td class="td-name"><?= htmlspecialchars($a['barangay']) ?></td>
                <td>
                    <?= htmlspecialchars($a['sitio']) ?>
                    <?php if ($a['sitio'] === 'Proper'): ?><span class="badge confirmed" style="margin-left:6px; font-size:9px; padding:1px 6px;">Base</span><?php endif; ?>
                    <?php if ($a['sitio'] === 'Other'): ?><span class="badge returned" style="margin-left:6px; font-size:9px; padding:1px 6px;">Other</span><?php endif; ?>
                </td>
                <td style="font-family:'Bebas Neue',sans-serif; font-size:20px; color:var(--gold); letter-spacing:0.5px;">
                    ₱<?= number_format($a['delivery_fee'], 0) ?>
                </td>
                <td>
                    <div class="action-btns">
                        <button class="btn-icon" title="Edit" onclick="editArea(this.closest('tr'))">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button class="btn-icon danger" title="Delete" onclick="deleteArea(this.closest('tr'))">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ── ADD / EDIT AREA MODAL ── -->
<div class="modal-overlay" id="areaModal">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <div>
                <div class="modal-eyebrow" id="areaModalEyebrow">New Area</div>
                <div class="modal-heading" id="areaModalTitle">Add Delivery Area</div>
            </div>
            <button class="modal-close" onclick="closeModal('areaModal')">&#215;</button>
        </div>
        <div id="areaAlert" class="modal-alert"></div>
        <input type="hidden" id="editAreaId">
        <div class="form-group">
            <label>Province</label>
            <input type="text" id="aProvince" value="Carcar" placeholder="e.g. Carcar">
        </div>
        <div class="form-row-2">
            <div class="form-group">
                <label>Barangay *</label>
                <input type="text" id="aBarangay" placeholder="e.g. Calidngan">
            </div>
            <div class="form-group">
                <label>Sitio / Purok *</label>
                <input type="text" id="aSitio" placeholder="e.g. Proper">
            </div>
        </div>
        <div class="form-group">
            <label>All-In Price (₱) *</label>
            <input type="number" id="aFee" placeholder="e.g. 1800" min="0" step="50">
        </div>
        <p style="font-size:11px; color:var(--muted); margin-bottom:14px; line-height:1.5;">
            This is the <strong style="color:var(--gold);">total amount</strong> the customer pays — covers rental and delivery. No separate fee is added on top.
        </p>
        <button class="btn-submit" id="areaSaveBtn" onclick="saveArea()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Save Area
        </button>
    </div>
</div>

<!-- ── DELETE AREA MODAL ── -->
<div class="modal-overlay" id="areaDeleteModal">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <div><div class="modal-eyebrow">Confirm</div><div class="modal-heading">Delete Area?</div></div>
            <button class="modal-close" onclick="closeModal('areaDeleteModal')">&#215;</button>
        </div>
        <p style="color:var(--muted); font-size:13px; margin-bottom:20px; line-height:1.6;">
            Delete delivery area <strong id="areaDeleteLabel" style="color:var(--text);"></strong>? This cannot be undone.
        </p>
        <button class="btn-danger" id="areaDeleteBtn" onclick="confirmAreaDelete()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
            Yes, Delete
        </button>
        <button class="btn-submit" style="margin-top:8px; background:var(--bg-elevated); color:var(--text); border:1px solid var(--border);" onclick="closeModal('areaDeleteModal')">Cancel</button>
    </div>
</div>

<?php closeAdminShell(); ?>
</div>

<script>
let activeAreaRow = null;

// ── Filter ──
document.getElementById('areaSearch').addEventListener('input', filterAreas);
document.getElementById('brgyFilter').addEventListener('change', filterAreas);
function filterAreas() {
    const q = document.getElementById('areaSearch').value.toLowerCase();
    const b = document.getElementById('brgyFilter').value;
    document.querySelectorAll('#areaBody tr[data-id]').forEach(row => {
        const ms = row.dataset.search.includes(q);
        const mb = b === 'all' || row.dataset.brgy === b;
        row.style.display = ms && mb ? '' : 'none';
    });
}

// ── Modal helpers ──
function openModal(id)  { document.getElementById(id).classList.add('open'); document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow=''; }
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) { m.classList.remove('open'); document.body.style.overflow=''; } }));

// ── Add ──
function openAreaModal() {
    document.getElementById('editAreaId').value  = '';
    document.getElementById('areaModalEyebrow').textContent = 'New Area';
    document.getElementById('areaModalTitle').textContent   = 'Add Delivery Area';
    document.getElementById('aProvince').value = 'Carcar';
    document.getElementById('aBarangay').value = '';
    document.getElementById('aSitio').value    = '';
    document.getElementById('aFee').value      = '';
    document.getElementById('areaAlert').style.display = 'none';
    openModal('areaModal');
}

// ── Edit ──
function editArea(row) {
    const d = row.dataset;
    document.getElementById('editAreaId').value  = d.id;
    document.getElementById('areaModalEyebrow').textContent = 'Edit Area';
    document.getElementById('areaModalTitle').textContent   = d.brgy + ' · ' + d.sitio;
    document.getElementById('aProvince').value = d.province;
    document.getElementById('aBarangay').value = d.brgy;
    document.getElementById('aSitio').value    = d.sitio;
    document.getElementById('aFee').value      = d.fee;
    document.getElementById('areaAlert').style.display = 'none';
    openModal('areaModal');
}

// ── Save ──
async function saveArea() {
    const btn = document.getElementById('areaSaveBtn');
    const alert = document.getElementById('areaAlert');
    btn.disabled = true; btn.textContent = 'Saving…';
    const fd = new FormData();
    fd.append('action',       'save');
    fd.append('id',           document.getElementById('editAreaId').value);
    fd.append('province',     document.getElementById('aProvince').value);
    fd.append('barangay',     document.getElementById('aBarangay').value);
    fd.append('sitio',        document.getElementById('aSitio').value);
    fd.append('delivery_fee', document.getElementById('aFee').value);
    try {
        const res  = await fetch('areas.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            closeModal('areaModal'); toast(data.message);
            setTimeout(() => location.reload(), 900);
        } else { alert.textContent = data.message; alert.style.display = 'block'; }
    } catch { alert.textContent = 'Network error.'; alert.style.display = 'block'; }
    btn.disabled = false; btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Save Area';
}

// ── Delete ──
function deleteArea(row) {
    activeAreaRow = row;
    document.getElementById('areaDeleteLabel').textContent = row.dataset.brgy + ' · ' + row.dataset.sitio;
    openModal('areaDeleteModal');
}
async function confirmAreaDelete() {
    const btn = document.getElementById('areaDeleteBtn');
    btn.disabled = true; btn.textContent = 'Deleting…';
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', activeAreaRow.dataset.id);
    try {
        const res  = await fetch('areas.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            activeAreaRow.style.opacity = '0'; activeAreaRow.style.transition = 'opacity 0.3s';
            setTimeout(() => activeAreaRow.remove(), 320);
            closeModal('areaDeleteModal'); toast(data.message);
        } else { toast(data.message, 'error'); }
    } catch { toast('Network error.', 'error'); }
    btn.disabled = false; btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg> Yes, Delete';
}
</script>
</body>
</html>