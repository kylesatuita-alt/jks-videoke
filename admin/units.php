<?php
require_once '../includes/admin_auth.php';

// ── AJAX handlers ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $brand       = trim($_POST['brand'] ?? '');
        $model       = trim($_POST['model'] ?? '');
        $screen_size = trim($_POST['screen_size'] ?? '');
        $song_count  = (int)($_POST['song_count'] ?? 0);
        $mic_count   = (int)($_POST['mic_count'] ?? 1);
        $bt          = isset($_POST['has_bluetooth']) ? 1 : 0;
        $rec         = isset($_POST['has_recording']) ? 1 : 0;
        $desc        = trim($_POST['description'] ?? '');

        if (!$name || !$brand || !$model || !$screen_size) {
            echo json_encode(['success' => false, 'message' => 'Name, brand, model, and screen size are required.']); exit();
        }

        if ($id) {
            $pdo->prepare("UPDATE videokes SET name=?,brand=?,model=?,screen_size=?,song_count=?,mic_count=?,has_bluetooth=?,has_recording=?,description=? WHERE id=?")
                ->execute([$name,$brand,$model,$screen_size,$song_count,$mic_count,$bt,$rec,$desc,$id]);
            echo json_encode(['success' => true, 'message' => 'Unit updated successfully.']);
        } else {
            $maxUnit = (int)$pdo->query("SELECT COALESCE(MAX(unit_number),0) FROM videokes")->fetchColumn();
            $pdo->prepare("INSERT INTO videokes (unit_number,name,brand,model,screen_size,song_count,mic_count,has_bluetooth,has_recording,description) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$maxUnit+1,$name,$brand,$model,$screen_size,$song_count,$mic_count,$bt,$rec,$desc]);
            echo json_encode(['success' => true, 'message' => 'Unit added successfully.']);
        }
        exit();
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM videokes WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Unit deleted.']);
        exit();
    }
    echo json_encode(['success' => false, 'message' => 'Unknown action.']); exit();
}

// ── Fetch units + today status ──────────────
$today = date('Y-m-d');
$units = $pdo->query("
    SELECT v.*,
        CASE WHEN EXISTS (
            SELECT 1 FROM reservations r
            WHERE r.videoke_id = v.id
              AND r.status IN ('confirmed','active','pending')
              AND '$today' BETWEEN r.start_date AND r.end_date
        ) THEN 'rented' ELSE 'available' END AS today_status
    FROM videokes v ORDER BY v.unit_number ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Units · JKS Videoke</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="admin-layout">
<?php require_once 'admin_shell.php'; renderAdminShell('Videoke Units', 'units'); ?>

<div class="section-header">
    <div class="section-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
        Videoke Units
    </div>
    <div style="display:flex; align-items:center; gap:10px;">
        <span class="section-count"><?= count($units) ?> units</span>
        <button class="topbar-btn" onclick="openUnitModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Unit
        </button>
    </div>
</div>

<div class="table-wrap">
    <div class="table-toolbar">
        <div class="search-input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="search-input" id="unitSearch" placeholder="Search by name, brand, model…">
        </div>
        <select class="filter-select" id="unitStatusFilter">
            <option value="all">All Units</option>
            <option value="available">Available Today</option>
            <option value="rented">Rented Today</option>
        </select>
    </div>

    <table class="data-table" id="unitTable">
        <thead>
            <tr>
                <th>Unit</th>
                <th>Name</th>
                <th>Brand / Model</th>
                <th class="mobile-hide">Screen</th>
                <th class="mobile-hide">Songs</th>
                <th class="mobile-hide">Specs</th>
                <th>Today</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="unitBody">
        <?php if (empty($units)): ?>
            <tr><td colspan="8"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/></svg><h3>No units yet</h3><p>Add your first videoke unit.</p></div></td></tr>
        <?php else: ?>
            <?php foreach ($units as $v): ?>
            <tr data-id="<?= $v['id'] ?>"
                data-status="<?= $v['today_status'] ?>"
                data-search="<?= strtolower($v['name'].' '.$v['brand'].' '.$v['model']) ?>"
                data-name="<?= htmlspecialchars($v['name']) ?>"
                data-brand="<?= htmlspecialchars($v['brand']) ?>"
                data-model="<?= htmlspecialchars($v['model']) ?>"
                data-screen="<?= htmlspecialchars($v['screen_size']) ?>"
                data-songs="<?= $v['song_count'] ?>"
                data-mics="<?= $v['mic_count'] ?>"
                data-bt="<?= $v['has_bluetooth'] ?>"
                data-rec="<?= $v['has_recording'] ?>"
                data-desc="<?= htmlspecialchars($v['description'] ?? '') ?>">
                <td><span class="td-unit">Unit <?= str_pad($v['unit_number'],2,'0',STR_PAD_LEFT) ?></span></td>
                <td class="td-name"><?= htmlspecialchars($v['name']) ?></td>
                <td>
                    <div><?= htmlspecialchars($v['brand']) ?></div>
                    <div class="td-muted"><?= htmlspecialchars($v['model']) ?></div>
                </td>
                <td class="td-muted mobile-hide"><?= htmlspecialchars($v['screen_size']) ?></td>
                <td class="td-muted mobile-hide"><?= number_format($v['song_count']) ?></td>
                <td class="mobile-hide">
                    <div style="display:flex; gap:4px; flex-wrap:wrap;">
                        <span style="font-size:10px; color:var(--muted);"><?= $v['mic_count'] ?> mic<?= $v['mic_count']>1?'s':'' ?></span>
                        <?php if ($v['has_bluetooth']): ?><span class="badge confirmed" style="font-size:9px; padding:1px 7px;">BT</span><?php endif; ?>
                        <?php if ($v['has_recording']): ?><span class="badge active" style="font-size:9px; padding:1px 7px;">REC</span><?php endif; ?>
                    </div>
                </td>
                <td><span class="badge <?= $v['today_status'] ?>"><?= ucfirst($v['today_status']) ?></span></td>
                <td>
                    <div class="action-btns">
                        <button class="btn-icon" title="Edit unit" onclick="editUnit(this.closest('tr'))">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button class="btn-icon danger" title="Delete unit" onclick="openUnitDelete(this.closest('tr'))">
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

<!-- ── ADD / EDIT UNIT MODAL ── -->
<div class="modal-overlay" id="unitModal">
    <div class="modal">
        <div class="modal-header">
            <div>
                <div class="modal-eyebrow" id="unitModalEyebrow">New Unit</div>
                <div class="modal-heading" id="unitModalTitle">Add Videoke Unit</div>
            </div>
            <button class="modal-close" onclick="closeModal('unitModal')">&#215;</button>
        </div>
        <div id="unitAlert" class="modal-alert"></div>
        <input type="hidden" id="editUnitId">
        <div class="form-row-2">
            <div class="form-group">
                <label>Unit Name *</label>
                <input type="text" id="uName" placeholder="e.g. Party King Pro">
            </div>
            <div class="form-group">
                <label>Brand *</label>
                <input type="text" id="uBrand" placeholder="e.g. Magic Sing">
            </div>
        </div>
        <div class="form-row-2">
            <div class="form-group">
                <label>Model *</label>
                <input type="text" id="uModel" placeholder="e.g. ET-25KH">
            </div>
            <div class="form-group">
                <label>Screen Size *</label>
                <input type="text" id="uScreen" placeholder='e.g. 21"'>
            </div>
        </div>
        <div class="form-row-2">
            <div class="form-group">
                <label>Song Count</label>
                <input type="number" id="uSongs" placeholder="28000" min="0">
            </div>
            <div class="form-group">
                <label>Mic Count</label>
                <input type="number" id="uMics" placeholder="2" min="1" max="4">
            </div>
        </div>
        <div style="display:flex; gap:18px; margin-bottom:14px;">
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; font-weight:500;">
                <input type="checkbox" id="uBT" style="accent-color:var(--gold); width:16px; height:16px;"> Bluetooth
            </label>
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; font-weight:500;">
                <input type="checkbox" id="uRec" style="accent-color:var(--gold); width:16px; height:16px;"> Recording
            </label>
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea id="uDesc" placeholder="Short description of this unit…"></textarea>
        </div>
        <button class="btn-submit" id="unitSaveBtn" onclick="saveUnit()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Save Unit
        </button>
    </div>
</div>

<!-- ── DELETE UNIT MODAL ── -->
<div class="modal-overlay" id="unitDeleteModal">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <div><div class="modal-eyebrow">Confirm</div><div class="modal-heading">Delete Unit?</div></div>
            <button class="modal-close" onclick="closeModal('unitDeleteModal')">&#215;</button>
        </div>
        <p style="color:var(--muted); font-size:13px; margin-bottom:20px; line-height:1.6;">
            Deleting <strong id="unitDeleteLabel" style="color:var(--text);"></strong> will also remove all related reservations. This cannot be undone.
        </p>
        <button class="btn-danger" id="unitDeleteBtn" onclick="confirmUnitDelete()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
            Yes, Delete
        </button>
        <button class="btn-submit" style="margin-top:8px; background:var(--bg-elevated); color:var(--text); border:1px solid var(--border);" onclick="closeModal('unitDeleteModal')">Cancel</button>
    </div>
</div>

<?php closeAdminShell(); ?>
</div>

<script>
let activeUnitRow = null;

// ── Filter ──
document.getElementById('unitSearch').addEventListener('input', filterUnits);
document.getElementById('unitStatusFilter').addEventListener('change', filterUnits);
function filterUnits() {
    const q = document.getElementById('unitSearch').value.toLowerCase();
    const s = document.getElementById('unitStatusFilter').value;
    document.querySelectorAll('#unitBody tr[data-id]').forEach(row => {
        const ms = row.dataset.search.includes(q);
        const mf = s === 'all' || row.dataset.status === s;
        row.style.display = ms && mf ? '' : 'none';
    });
}

// ── Modal helpers ──
function openModal(id)  { document.getElementById(id).classList.add('open'); document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow=''; }
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) { m.classList.remove('open'); document.body.style.overflow=''; } }));

// ── Open Add ──
function openUnitModal() {
    document.getElementById('editUnitId').value = '';
    document.getElementById('unitModalEyebrow').textContent = 'New Unit';
    document.getElementById('unitModalTitle').textContent   = 'Add Videoke Unit';
    document.getElementById('uName').value    = '';
    document.getElementById('uBrand').value   = '';
    document.getElementById('uModel').value   = '';
    document.getElementById('uScreen').value  = '';
    document.getElementById('uSongs').value   = '';
    document.getElementById('uMics').value    = '1';
    document.getElementById('uBT').checked    = false;
    document.getElementById('uRec').checked   = false;
    document.getElementById('uDesc').value    = '';
    document.getElementById('unitAlert').style.display = 'none';
    openModal('unitModal');
}

// ── Open Edit ──
function editUnit(row) {
    const d = row.dataset;
    document.getElementById('editUnitId').value = d.id;
    document.getElementById('unitModalEyebrow').textContent = 'Edit Unit';
    document.getElementById('unitModalTitle').textContent   = d.name;
    document.getElementById('uName').value    = d.name;
    document.getElementById('uBrand').value   = d.brand;
    document.getElementById('uModel').value   = d.model;
    document.getElementById('uScreen').value  = d.screen;
    document.getElementById('uSongs').value   = d.songs;
    document.getElementById('uMics').value    = d.mics;
    document.getElementById('uBT').checked    = d.bt === '1';
    document.getElementById('uRec').checked   = d.rec === '1';
    document.getElementById('uDesc').value    = d.desc;
    document.getElementById('unitAlert').style.display = 'none';
    openModal('unitModal');
}

// ── Save ──
async function saveUnit() {
    const btn = document.getElementById('unitSaveBtn');
    const alert = document.getElementById('unitAlert');
    btn.disabled = true; btn.textContent = 'Saving…';
    const fd = new FormData();
    fd.append('action', 'save');
    fd.append('id',           document.getElementById('editUnitId').value);
    fd.append('name',         document.getElementById('uName').value);
    fd.append('brand',        document.getElementById('uBrand').value);
    fd.append('model',        document.getElementById('uModel').value);
    fd.append('screen_size',  document.getElementById('uScreen').value);
    fd.append('song_count',   document.getElementById('uSongs').value || 0);
    fd.append('mic_count',    document.getElementById('uMics').value  || 1);
    if (document.getElementById('uBT').checked)  fd.append('has_bluetooth', '1');
    if (document.getElementById('uRec').checked) fd.append('has_recording', '1');
    fd.append('description',  document.getElementById('uDesc').value);
    try {
        const res  = await fetch('units.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            closeModal('unitModal');
            toast(data.message);
            setTimeout(() => location.reload(), 900);
        } else {
            alert.textContent = data.message; alert.style.display = 'block';
        }
    } catch { alert.textContent = 'Network error.'; alert.style.display = 'block'; }
    btn.disabled = false; btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Save Unit';
}

// ── Delete ──
function openUnitDelete(row) {
    activeUnitRow = row;
    document.getElementById('unitDeleteLabel').textContent = row.dataset.name;
    openModal('unitDeleteModal');
}
async function confirmUnitDelete() {
    const btn = document.getElementById('unitDeleteBtn');
    btn.disabled = true; btn.textContent = 'Deleting…';
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', activeUnitRow.dataset.id);
    try {
        const res  = await fetch('units.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            activeUnitRow.style.opacity = '0'; activeUnitRow.style.transition = 'opacity 0.3s';
            setTimeout(() => activeUnitRow.remove(), 320);
            closeModal('unitDeleteModal'); toast(data.message);
        } else { toast(data.message, 'error'); }
    } catch { toast('Network error.', 'error'); }
    btn.disabled = false; btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg> Yes, Delete';
}
</script>
</body>
</html>