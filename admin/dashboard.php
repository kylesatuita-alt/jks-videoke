<?php
require_once '../includes/admin_auth.php';

// ── Stats ──────────────────────────────────
$today = date('Y-m-d');

// Reservations by status
$statuses = ['pending','confirmed','active','returned','cancelled'];
$statusCounts = [];
foreach ($statuses as $s) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE status = ?");
    $st->execute([$s]);
    $statusCounts[$s] = (int)$st->fetchColumn();
}
$totalRes = array_sum($statusCounts);

// Revenue (returned + active + confirmed)
$rev = $pdo->query("SELECT COALESCE(SUM(total_price),0) FROM reservations WHERE status IN ('confirmed','active','returned')")->fetchColumn();

// Units availability
$totalUnits = (int)$pdo->query("SELECT COUNT(*) FROM videokes")->fetchColumn();
$rentedUnits = (int)$pdo->query("
    SELECT COUNT(DISTINCT videoke_id) FROM reservations
    WHERE status IN ('confirmed','active')
      AND start_date <= '$today' AND end_date >= '$today'
")->fetchColumn();
$availUnits = $totalUnits - $rentedUnits;

// Recent reservations (last 8)
$recent = $pdo->query("
    SELECT r.*, u.name AS customer, v.name AS unit_name, v.unit_number
    FROM reservations r
    JOIN users u ON u.id = r.user_id
    JOIN videokes v ON v.id = r.videoke_id
    ORDER BY r.created_at DESC
    LIMIT 8
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Dashboard · JKS Videoke</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="admin-layout">
<?php require_once 'admin_shell.php'; renderAdminShell('Dashboard', 'dashboard'); ?>

<!-- ── STAT CARDS ── -->
<div class="stat-grid">
    <div class="stat-card amber">
        <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <div class="sc-num"><?= $statusCounts['pending'] ?></div>
        <div class="sc-label">Pending</div>
        <div class="sc-sub">Awaiting confirmation</div>
    </div>
    <div class="stat-card green">
        <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div class="sc-num"><?= $statusCounts['confirmed'] + $statusCounts['active'] ?></div>
        <div class="sc-label">Active / Confirmed</div>
        <div class="sc-sub"><?= $statusCounts['confirmed'] ?> confirmed · <?= $statusCounts['active'] ?> active</div>
    </div>
    <div class="stat-card">
        <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg></div>
        <div class="sc-num"><?= $availUnits ?> / <?= $totalUnits ?></div>
        <div class="sc-label">Units Available</div>
        <div class="sc-sub"><?= $rentedUnits ?> currently rented</div>
    </div>
    <div class="stat-card green">
        <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <div class="sc-num">₱<?= number_format($rev, 0) ?></div>
        <div class="sc-label">Revenue</div>
        <div class="sc-sub">Confirmed + active + returned</div>
    </div>
    <div class="stat-card blue">
        <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <div class="sc-num"><?= $totalRes ?></div>
        <div class="sc-label">Total Reservations</div>
        <div class="sc-sub"><?= $statusCounts['returned'] ?> returned · <?= $statusCounts['cancelled'] ?> cancelled</div>
    </div>
</div>

<!-- ── STATUS BREAKDOWN ── -->
<div class="section-header" style="margin-bottom:10px;">
    <div class="section-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Reservation Status Breakdown
    </div>
    <a href="reservations.php" class="topbar-btn secondary" style="font-size:12px; padding:6px 12px;">View All</a>
</div>
<div class="status-breakdown-wrap" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:28px;">
    <?php foreach (['pending'=>'Pending','confirmed'=>'Confirmed','active'=>'Active','returned'=>'Returned','cancelled'=>'Cancelled'] as $s => $l): ?>
    <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-md); padding:14px 20px; display:flex; align-items:center; gap:10px; min-width:130px;">
        <span class="badge <?= $s ?>"><?= $l ?></span>
        <span style="font-family:'Bebas Neue',sans-serif; font-size:28px; color:var(--text);"><?= $statusCounts[$s] ?></span>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── RECENT RESERVATIONS ── -->
<div class="section-header">
    <div class="section-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Recent Reservations
    </div>
    <span class="section-count"><?= count($recent) ?> latest</span>
</div>
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Customer</th>
                <th>Unit</th>
                <th class="mobile-hide">Dates</th>
                <th>Total</th>
                <th>Status</th>
                <th class="mobile-hide">Booked</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($recent)): ?>
            <tr><td colspan="7"><div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg><p>No reservations yet.</p></div></td></tr>
        <?php else: ?>
            <?php foreach ($recent as $r): ?>
            <tr>
                <td class="td-mono">#<?= $r['id'] ?></td>
                <td class="td-name"><?= htmlspecialchars($r['customer']) ?></td>
                <td>
                    <span class="td-unit">Unit <?= str_pad($r['unit_number'],2,'0',STR_PAD_LEFT) ?></span><br>
                    <span class="td-muted"><?= htmlspecialchars($r['unit_name']) ?></span>
                </td>
                <td class="td-muted mobile-hide"><?= $r['start_date'] ?> → <?= $r['end_date'] ?></td>
                <td style="color:var(--gold); font-weight:700;">₱<?= number_format($r['total_price'],0) ?></td>
                <td><span class="badge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                <td class="td-muted mobile-hide"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php closeAdminShell(); ?>
</div><!-- /.admin-layout -->
</body>
</html>