<?php
require_once '../includes/admin_auth.php';

// ── Handle AJAX actions ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'update_status') {
        $id     = (int)$_POST['id'];
        $status = $_POST['status'] ?? '';
        $valid  = ['pending','confirmed','active','delivering','returned','cancelled'];
        if (!$id || !in_array($status, $valid)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data.']); exit();
        }

        // Get reservation + user info before updating
        $res = $pdo->prepare("
            SELECT r.*, u.id AS uid, v.name AS unit_name, v.unit_number
            FROM reservations r
            JOIN users u    ON u.id = r.user_id
            JOIN videokes v ON v.id = r.videoke_id
            WHERE r.id = ? LIMIT 1
        ");
        $res->execute([$id]);
        $resRow = $res->fetch();

        // Update status
        $pdo->prepare("UPDATE reservations SET status = ? WHERE id = ?")->execute([$status, $id]);

        // Status messages for the customer
        $messages = [
            'confirmed'  => [
                'title'   => '✅ Reservation Confirmed!',
                'message' => "Great news! Your reservation for Unit " . str_pad($resRow['unit_number'],2,'0',STR_PAD_LEFT) . " ({$resRow['unit_name']}) has been confirmed. We'll prepare everything for your rental dates ({$resRow['start_date']} – {$resRow['end_date']}).",
            ],
            'active'     => [
                'title'   => '🎤 Rental is Now Active',
                'message' => "Your rental for Unit " . str_pad($resRow['unit_number'],2,'0',STR_PAD_LEFT) . " ({$resRow['unit_name']}) is now active. Enjoy singing! 🎶",
            ],
            'delivering' => [
                'title'   => '🚚 Your Unit is On the Way!',
                'message' => "Unit " . str_pad($resRow['unit_number'],2,'0',STR_PAD_LEFT) . " ({$resRow['unit_name']}) is now being delivered to you. Please be ready to receive it. See you soon!",
            ],
            'returned'   => [
                'title'   => '📦 Unit Returned — Thank You!',
                'message' => "Your rental for Unit " . str_pad($resRow['unit_number'],2,'0',STR_PAD_LEFT) . " ({$resRow['unit_name']}) has been marked as returned. Thank you for choosing JKS Videoke! 🙏",
            ],
            'cancelled'  => [
                'title'   => '❌ Reservation Cancelled',
                'message' => "Your reservation for Unit " . str_pad($resRow['unit_number'],2,'0',STR_PAD_LEFT) . " ({$resRow['unit_name']}) has been cancelled. If you have questions, please contact us.",
            ],
        ];

        // Insert notification for the customer (skip for 'pending' since it's the default)
        if ($resRow && isset($messages[$status])) {
            $pdo->prepare("
                INSERT INTO notifications (user_id, res_id, type, title, message)
                VALUES (?, ?, 'status_update', ?, ?)
            ")->execute([
                $resRow['uid'],
                $id,
                $messages[$status]['title'],
                $messages[$status]['message'],
            ]);
        }

        echo json_encode(['success' => true, 'message' => 'Status updated to ' . ucfirst($status) . '. Customer notified.']);
        exit();
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID.']); exit(); }
        $pdo->prepare("DELETE FROM reservations WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Reservation deleted.']);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']); exit();
}

// ── Fetch reservations ──────────────────────
$reservations = $pdo->query("
    SELECT r.*, u.name AS customer, u.email AS cust_email, v.name AS unit_name, v.unit_number
    FROM reservations r
    JOIN users u ON u.id = r.user_id
    JOIN videokes v ON v.id = r.videoke_id
    ORDER BY r.created_at DESC
")->fetchAll();

$statuses = ['all','pending','confirmed','active','delivering','returned','cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Reservations · JKS Videoke</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="admin-layout">
<?php require_once 'admin_shell.php'; renderAdminShell('Reservations', 'reservations'); ?>

<div class="section-header">
    <div class="section-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        All Reservations
    </div>
    <span class="section-count" id="visibleCount"><?= count($reservations) ?> total</span>
</div>

<div class="table-wrap">
    <!-- Toolbar -->
    <div class="table-toolbar">
        <div class="search-input-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="search-input" id="searchInput" placeholder="Search customer, unit, notes…">
        </div>
        <select class="filter-select" id="statusFilter">
            <option value="all">All Statuses</option>
            <?php foreach (['pending','confirmed','active','delivering','returned','cancelled'] as $s): ?>
            <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <table class="data-table" id="resTable">
        <thead>
            <tr>
                <th>#</th>
                <th>Customer</th>
                <th>Unit</th>
                <th class="mobile-hide">Dates</th>
                <th class="desktop-only-cell">Total</th>
                <th class="desktop-only-cell">Status</th>
                <th class="mobile-combined-cell" style="display:none;">Total / Status</th>
                <th class="mobile-hide">Booked</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="resBody">
        <?php if (empty($reservations)): ?>
            <tr><td colspan="9">
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <h3>No reservations yet</h3>
                    <p>Reservations from customers will appear here.</p>
                </div>
            </td></tr>
        <?php else: ?>
            <?php foreach ($reservations as $r): ?>
            <tr data-id="<?= $r['id'] ?>"
                data-status="<?= $r['status'] ?>"
                data-search="<?= strtolower(htmlspecialchars($r['customer'] . ' ' . $r['unit_name'] . ' ' . $r['notes'])) ?>"
                data-customer="<?= htmlspecialchars($r['customer']) ?>"
                data-cemail="<?= htmlspecialchars($r['cust_email']) ?>"
                data-unit="Unit <?= str_pad($r['unit_number'],2,'0',STR_PAD_LEFT) ?> · <?= htmlspecialchars($r['unit_name']) ?>"
                data-start="<?= $r['start_date'] ?>"
                data-end="<?= $r['end_date'] ?>"
                data-total="<?= $r['total_price'] ?>"
                data-notes="<?= htmlspecialchars($r['notes'] ?? '') ?>"
                data-created="<?= date('M j, Y g:i A', strtotime($r['created_at'])) ?>">
                <td class="td-mono">#<?= $r['id'] ?></td>
                <td>
                    <div class="td-name"><?= htmlspecialchars($r['customer']) ?></div>
                    <div class="td-muted"><?= htmlspecialchars($r['cust_email']) ?></div>
                </td>
                <td>
                    <div class="td-unit">Unit <?= str_pad($r['unit_number'],2,'0',STR_PAD_LEFT) ?></div>
                    <div class="td-muted"><?= htmlspecialchars($r['unit_name']) ?></div>
                </td>
                <td class="td-muted mobile-hide"><?= $r['start_date'] ?><br><?= $r['end_date'] ?></td>
                <td class="desktop-only-cell" style="font-weight:700; color:var(--gold);">₱<?= number_format($r['total_price'],0) ?></td>
                <td class="desktop-only-cell"><span class="badge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                <td class="mobile-combined-cell" style="display:none;">
                    <div style="font-weight:700; color:var(--gold); margin-bottom:4px;">₱<?= number_format($r['total_price'],0) ?></div>
                    <span class="badge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
                </td>
                <td class="td-muted mobile-hide"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                <td>
                    <div class="action-btns">
                        <button class="btn-icon" title="View details" onclick="openDetail(this.closest('tr'))">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                        <button class="btn-icon" title="Change status" onclick="openStatus(this.closest('tr'))">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button class="btn-icon danger" title="Delete" onclick="openDelete(this.closest('tr'))">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ── VIEW DETAIL MODAL ── -->
<div class="modal-overlay" id="detailModal">
    <div class="modal">
        <div class="modal-header">
            <div>
                <div class="modal-eyebrow">Reservation Details</div>
                <div class="modal-heading" id="detailTitle">—</div>
            </div>
            <button class="modal-close" onclick="closeModal('detailModal')">&#215;</button>
        </div>
        <div id="detailBody"></div>
    </div>
</div>

<!-- ── CHANGE STATUS MODAL ── -->
<div class="modal-overlay" id="statusModal">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <div>
                <div class="modal-eyebrow">Update Status</div>
                <div class="modal-heading" id="statusTitle">—</div>
            </div>
            <button class="modal-close" onclick="closeModal('statusModal')">&#215;</button>
        </div>
        <div id="statusAlert" class="modal-alert"></div>
        <div class="form-group">
            <label>New Status</label>
            <select id="newStatus">
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="active">Active</option>
                <option value="delivering">Delivering 🚚</option>
                <option value="returned">Returned</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        <button class="btn-submit" id="statusSaveBtn" onclick="saveStatus()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            Save Status
        </button>
    </div>
</div>

<!-- ── DELETE CONFIRM MODAL ── -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <div>
                <div class="modal-eyebrow">Confirm Deletion</div>
                <div class="modal-heading">Delete Reservation?</div>
            </div>
            <button class="modal-close" onclick="closeModal('deleteModal')">&#215;</button>
        </div>
        <p style="color:var(--muted); font-size:13px; margin-bottom:20px; line-height:1.6;">
            This will permanently delete reservation <strong id="deleteLabel" style="color:var(--text);"></strong>. This cannot be undone.
        </p>
        <button class="btn-danger" id="deleteConfirmBtn" onclick="confirmDelete()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
            Yes, Delete
        </button>
        <button class="btn-submit" style="margin-top:8px; background:var(--bg-elevated); color:var(--text); border:1px solid var(--border);" onclick="closeModal('deleteModal')">Cancel</button>
    </div>
</div>

<?php closeAdminShell(); ?>
</div>

<script>
let activeRow = null;

// ── Filter / Search ──
const searchInput  = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const rows = document.querySelectorAll('#resBody tr[data-id]');
const visibleCount = document.getElementById('visibleCount');

function applyFilters() {
    const q   = searchInput.value.toLowerCase();
    const sel = statusFilter.value;
    let count = 0;
    rows.forEach(row => {
        const matchSearch = row.dataset.search.includes(q);
        const matchStatus = sel === 'all' || row.dataset.status === sel;
        const show = matchSearch && matchStatus;
        row.style.display = show ? '' : 'none';
        if (show) count++;
    });
    visibleCount.textContent = count + ' shown';
}
searchInput.addEventListener('input', applyFilters);
statusFilter.addEventListener('change', applyFilters);

// ── Modal helpers ──
function openModal(id)  { document.getElementById(id).classList.add('open'); document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow=''; }
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) { m.classList.remove('open'); document.body.style.overflow=''; } }));
document.addEventListener('keydown', e => { if (e.key === 'Escape') { document.querySelectorAll('.modal-overlay.open').forEach(m => { m.classList.remove('open'); document.body.style.overflow=''; }); }});

// ── Detail modal ──
function openDetail(row) {
    activeRow = row;
    const d = row.dataset;
    document.getElementById('detailTitle').textContent = 'Reservation #' + d.id;

    // Parse notes for phone/barangay/sitio
    const notes = d.notes || '';
    const phone = notes.match(/Phone:\s*([^|]+)/)?.[1]?.trim() || '—';
    const brgy  = notes.match(/Barangay:\s*([^|]+)/)?.[1]?.trim() || '—';
    const sitio = notes.match(/Sitio:\s*([^|]+)/)?.[1]?.trim() || '—';
    const extra = notes.match(/Notes:\s*(.+)$/)?.[1]?.trim() || '';

    document.getElementById('detailBody').innerHTML = `
        <div class="detail-row">
            <div class="detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
            <div><div class="detail-label">Customer</div><div class="detail-value">${d.customer}</div><div class="td-muted" style="font-size:12px;margin-top:2px;">${d.cemail}</div></div>
        </div>
        <div class="detail-row">
            <div class="detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.63 3.18 2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.96a16 16 0 0 0 6.13 6.13l1.02-.97a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
            <div><div class="detail-label">Phone</div><div class="detail-value">${phone}</div></div>
        </div>
        <div class="detail-row">
            <div class="detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
            <div><div class="detail-label">Delivery Location</div><div class="detail-value">${brgy} · ${sitio}</div></div>
        </div>
        <div class="detail-row">
            <div class="detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg></div>
            <div><div class="detail-label">Unit</div><div class="detail-value">${d.unit}</div></div>
        </div>
        <div class="detail-row">
            <div class="detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
            <div><div class="detail-label">Rental Period</div><div class="detail-value">${d.start} → ${d.end}</div></div>
        </div>
        <div class="detail-row">
            <div class="detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <div><div class="detail-label">Total Amount</div><div class="detail-value" style="color:var(--gold);font-size:20px;font-family:'Bebas Neue',sans-serif;">₱${parseFloat(d.total).toLocaleString('en-PH',{minimumFractionDigits:0})}</div></div>
        </div>
        <div class="detail-row">
            <div class="detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
            <div><div class="detail-label">Status</div><div class="detail-value" style="margin-top:3px;"><span class="badge ${d.status}">${d.status.charAt(0).toUpperCase()+d.status.slice(1)}</span></div></div>
        </div>
        ${extra ? `<div class="detail-row"><div class="detail-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div><div><div class="detail-label">Notes</div><div class="detail-value" style="font-size:13px;">${extra}</div></div></div>` : ''}
        <div style="margin-top:18px; display:flex; gap:8px;">
            <button class="btn-submit" style="flex:1;" onclick="closeModal('detailModal'); openStatus(activeRow);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Change Status
            </button>
        </div>`;
    openModal('detailModal');
}

// ── Status modal ──
function openStatus(row) {
    activeRow = row;
    document.getElementById('statusTitle').textContent = 'Reservation #' + row.dataset.id;
    document.getElementById('newStatus').value = row.dataset.status;
    document.getElementById('statusAlert').style.display = 'none';
    openModal('statusModal');
}

async function saveStatus() {
    const btn = document.getElementById('statusSaveBtn');
    const alert = document.getElementById('statusAlert');
    const status = document.getElementById('newStatus').value;
    btn.disabled = true; btn.textContent = 'Saving…';
    try {
        const fd = new FormData();
        fd.append('action', 'update_status');
        fd.append('id',     activeRow.dataset.id);
        fd.append('status', status);
        const res  = await fetch('reservations.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            // Update row in DOM
            activeRow.dataset.status = status;
            activeRow.querySelector('.badge').className = 'badge ' + status;
            activeRow.querySelector('.badge').textContent = status.charAt(0).toUpperCase() + status.slice(1);
            closeModal('statusModal');
            toast(data.message);
        } else {
            alert.textContent = data.message; alert.style.display = 'block';
        }
    } catch { alert.textContent = 'Network error.'; alert.style.display = 'block'; }
    btn.disabled = false; btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Save Status';
}

// ── Delete modal ──
function openDelete(row) {
    activeRow = row;
    document.getElementById('deleteLabel').textContent = '#' + row.dataset.id + ' (' + row.dataset.customer + ')';
    openModal('deleteModal');
}

async function confirmDelete() {
    const btn = document.getElementById('deleteConfirmBtn');
    btn.disabled = true; btn.textContent = 'Deleting…';
    try {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', activeRow.dataset.id);
        const res  = await fetch('reservations.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            activeRow.style.transition = 'opacity 0.3s'; activeRow.style.opacity = '0';
            setTimeout(() => activeRow.remove(), 320);
            closeModal('deleteModal');
            toast(data.message);
            applyFilters();
        } else { toast(data.message, 'error'); }
    } catch { toast('Network error.', 'error'); }
    btn.disabled = false; btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg> Yes, Delete';
}
</script>
</body>
</html>