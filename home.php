<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

requireLogin();
$user = getCurrentUser();

$today = date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT v.*,
        CASE
            WHEN EXISTS (
                SELECT 1 FROM reservations r
                WHERE r.videoke_id = v.id
                  AND r.status IN ('confirmed','active','pending','delivering')
                  AND ? BETWEEN r.start_date AND r.end_date
            ) THEN 'unavailable'
            ELSE 'available'
        END AS today_status,
        (
            SELECT DATE_ADD(MAX(r2.end_date), INTERVAL 1 DAY)
            FROM reservations r2
            WHERE r2.videoke_id = v.id
              AND r2.status IN ('confirmed','active','pending','delivering')
              AND r2.end_date >= ?
        ) AS next_available_date
    FROM videokes v
    ORDER BY v.unit_number ASC
");
$stmt->execute([$today, $today]);
$videokes = $stmt->fetchAll();

$cartStmt = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
$cartStmt->execute([$user['id']]);
$cartCount = (int)$cartStmt->fetchColumn();

$cartItems = $pdo->prepare("
    SELECT c.*, v.name AS v_name, v.unit_number, v.price_3days
    FROM cart c JOIN videokes v ON v.id = c.videoke_id
    WHERE c.user_id = ?
    ORDER BY c.added_at DESC
");
$cartItems->execute([$user['id']]);
$cartRows = $cartItems->fetchAll();

$available = count(array_filter($videokes, fn($v) => $v['today_status'] === 'available'));
$rented    = count($videokes) - $available;

// Fetch user's favorited videoke IDs
$favStmt = $pdo->prepare("SELECT videoke_id FROM favorites WHERE user_id = ?");
$favStmt->execute([$user['id']]);
$favIds = array_column($favStmt->fetchAll(), 'videoke_id');

// Sync avatar into session if not set
if (!isset($_SESSION['user']['avatar'])) {
    $avatarRow = $pdo->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
    $avatarRow->execute([$user['id']]);
    $_SESSION['user']['avatar'] = $avatarRow->fetchColumn() ?: null;
    $user = getCurrentUser();
}

// Fetch delivery areas for reserve modal
$areaRows = $pdo->query("SELECT barangay, sitio, delivery_fee FROM delivery_areas ORDER BY barangay ASC, sitio ASC")->fetchAll();
$areaMap  = [];
foreach ($areaRows as $a) {
    $areaMap[$a['barangay']][$a['sitio']] = (float)$a['delivery_fee'];
}

// Fetch ALL booked date ranges per unit (future only) for calendar blocking
$bookedRows = $pdo->query("
    SELECT videoke_id, start_date, end_date
    FROM reservations
    WHERE status IN ('pending','confirmed','active','delivering')
      AND end_date >= CURDATE()
    ORDER BY videoke_id, start_date
")->fetchAll();
$bookedRanges = []; // [ videoke_id => [ [start, end], ... ] ]
foreach ($bookedRows as $b) {
    $bookedRanges[$b['videoke_id']][] = [$b['start_date'], $b['end_date']];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JKS Videoke — Browse Units</title>
    <link rel="stylesheet" href="css/home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
</head>
<body>

<!-- ── NAVBAR ── -->
<nav class="navbar">
    <div class="nav-left">
        <div class="nav-logo-icon">
            <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="15" y="4" width="10" height="16" rx="5" fill="#F5C518"/>
                <path d="M9 20 C9 29 31 29 31 20" stroke="#F5C518" stroke-width="2" stroke-linecap="round" fill="none"/>
                <line x1="20" y1="28" x2="20" y2="34" stroke="#F5C518" stroke-width="2" stroke-linecap="round"/>
                <line x1="14" y1="34" x2="26" y2="34" stroke="#F5C518" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        <span class="nav-wordmark">JKS Videoke</span>
    </div>
    <div class="nav-right">
        <span class="nav-greeting">Hi, <strong><?php echo htmlspecialchars($user['name']); ?></strong></span>
        <?php if ($user['role'] === 'admin'): ?>
        <a href="admin/dashboard.php" class="nav-link-btn" style="color:var(--gold); border-color:var(--gold-border); background:var(--gold-soft);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Admin Dashboard
        </a>
        <?php endif; ?>
        <a href="favorites.php" class="nav-link-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            <span class="nav-btn-label">Favorites</span>
        </a>
        <button class="notif-btn" id="notifToggle" title="Notifications">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
        </button>
        <button class="cart-btn" id="cartToggle">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            Cart
            <span class="cart-badge <?php echo $cartCount > 0 ? 'visible' : ''; ?>" id="cartBadge"><?php echo $cartCount; ?></span>
        </button>
        <button class="cs-btn" id="csToggle" title="Customer Service">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span class="nav-btn-label">Support</span>
        </button>
        <div class="nav-divider"></div>
        <button class="profile-avatar-btn" id="profileToggle" title="My Account">
            <?php if (!empty($user['avatar'])): ?>
            <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar"
                 class="avatar-photo" id="navAvatarPhoto">
            <?php else: ?>
            <div class="avatar-initials" id="navAvatarInitials"><?php echo htmlspecialchars(strtoupper(substr($user['name'], 0, 1))); ?></div>
            <?php endif; ?>
            <span class="avatar-name"><?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?></span>
            <svg class="avatar-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
    </div>
</nav>

<div class="hero">
    <div class="hero-eyebrow">
        <span class="hero-eyebrow-dot"></span>
        JKS Videoke Rental
    </div>

    <h1>Ready to <em>Sing</em>?</h1>

    <p class="hero-sub">
        Browse available videoke units, reserve online,
        and enjoy a 3-day rental with delivery right to your home.
    </p>

    <div class="hero-stats">
        <div class="stat-item">
            <div class="stat-num"><?php echo count($videokes); ?></div>
            <div class="stat-label">Total Units</div>
        </div>
        <div class="stat-item">
            <div class="stat-num green"><?php echo $available; ?></div>
            <div class="stat-label">Available</div>
        </div>
        <div class="stat-item">
            <div class="stat-num red"><?php echo $rented; ?></div>
            <div class="stat-label">Rented</div>
        </div>
    </div>
</div>

<!-- ── FILTER BAR ── -->
<div class="controls-bar">
    <button class="filter-chip active" data-filter="all">All Units</button>
    <button class="filter-chip" data-filter="available">&#10003; Available Today</button>
    <button class="filter-chip" data-filter="unavailable">Currently Rented</button>
</div>

<!-- ── JUMP TO UNIT ── -->
<div class="jump-to-unit-wrap" id="jumpNav">
    <span class="jump-label">Jump to unit:</span>
    <?php foreach ($videokes as $jv):
        $jIsAvail = $jv['today_status'] === 'available';
    ?>
    <button
        class="jump-btn <?= $jIsAvail ? 'avail' : '' ?>"
        onclick="document.getElementById('vcard-<?= $jv['id'] ?>').scrollIntoView({behavior:'smooth', block:'center'})"
        title="Unit <?= str_pad($jv['unit_number'],2,'0',STR_PAD_LEFT) ?>"
    ><?= str_pad($jv['unit_number'],2,'0',STR_PAD_LEFT) ?></button>
    <?php endforeach; ?>
</div>

<!-- ── GRID ── -->
<div class="grid-wrapper">
    <div class="unit-grid" id="unitGrid">

        <?php foreach ($videokes as $v):
            $isAvail = $v['today_status'] === 'available';
            $inCart  = false;
            foreach ($cartRows as $ci) {
                if ((int)$ci['videoke_id'] === (int)$v['id']) { $inCart = true; break; }
            }
            $isFav   = in_array($v['id'], $favIds);
            $opacity = '0.9';
        ?>
        <div class="v-card" id="vcard-<?php echo $v['id']; ?>" data-status="<?php echo $v['today_status']; ?>">

            <!-- Image -->
            <div class="v-card-img">
                <span class="unit-tag">UNIT <?php echo str_pad($v['unit_number'], 2, '0', STR_PAD_LEFT); ?></span>
                <svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="30" y="8" width="20" height="30" rx="10" fill="#F5C518" opacity="<?php echo $opacity; ?>"/>
                    <path d="M18 38 C18 54 62 54 62 38" stroke="#F5C518" stroke-width="3" stroke-linecap="round" fill="none" opacity="<?php echo $opacity; ?>"/>
                    <line x1="40" y1="53" x2="40" y2="65" stroke="#F5C518" stroke-width="3" stroke-linecap="round" opacity="<?php echo $opacity; ?>"/>
                    <line x1="30" y1="65" x2="50" y2="65" stroke="#F5C518" stroke-width="3" stroke-linecap="round" opacity="<?php echo $opacity; ?>"/>
                    <?php if ($v['has_bluetooth']): ?>
                    <path d="M63 22 L67 26 L60 33 L67 40 L63 44 L63 22Z" stroke="#F5C518" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" opacity="0.45"/>
                    <?php endif; ?>
                </svg>
                <span class="status-ribbon <?php echo $isAvail ? 'available' : 'rented'; ?>">
                    <?php echo $isAvail ? '&#10003; Available' : '&#8987; Currently Rented'; ?>
                </span>
                <button class="btn-fav <?php echo $isFav ? 'active' : ''; ?>" data-id="<?php echo $v['id']; ?>" title="<?php echo $isFav ? 'Remove from favorites' : 'Add to favorites'; ?>">
                    <svg viewBox="0 0 24 24" fill="<?php echo $isFav ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div class="v-card-body">
                <div class="v-card-name"><?php echo htmlspecialchars($v['name']); ?></div>
                <div class="v-card-model"><?php echo htmlspecialchars($v['brand']); ?> &middot; <?php echo htmlspecialchars($v['model']); ?></div>

                <div class="specs-row">
                    <span class="spec-tag">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                        <?php echo htmlspecialchars($v['screen_size']); ?>
                    </span>
                    <span class="spec-tag">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/></svg>
                        <?php echo $v['mic_count']; ?> Mic<?php echo $v['mic_count'] > 1 ? 's' : ''; ?>
                    </span>
                    <span class="spec-tag hi">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                        <?php echo number_format($v['song_count']); ?> Songs
                    </span>
                    <?php if ($v['has_bluetooth']): ?>
                    <span class="spec-tag hi">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6.5 6.5 17.5 17.5 12 23 12 1 17.5 6.5 6.5 17.5"/></svg>
                        BT
                    </span>
                    <?php endif; ?>
                    <?php if ($v['has_recording']): ?>
                    <span class="spec-tag">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3" fill="currentColor"/></svg>
                        Rec
                    </span>
                    <?php endif; ?>
                </div>

                <div class="price-row">
                    <div class="v-price-label">3-Day Rental</div>
                    <div class="v-price v-price-location">Select location</div>
                    <div class="v-price-period">price varies by delivery area</div>
                </div>

                <div class="card-actions">
                    <button class="btn-details"
                        data-id="<?php echo $v['id']; ?>"
                        data-name="<?php echo htmlspecialchars($v['name']); ?>"
                        data-unit="<?php echo $v['unit_number']; ?>"
                        data-brand="<?php echo htmlspecialchars($v['brand']); ?>"
                        data-model="<?php echo htmlspecialchars($v['model']); ?>"
                        data-screen="<?php echo htmlspecialchars($v['screen_size']); ?>"
                        data-songs="<?php echo number_format($v['song_count']); ?>"
                        data-mics="<?php echo $v['mic_count']; ?>"
                        data-bt="<?php echo $v['has_bluetooth']; ?>"
                        data-rec="<?php echo $v['has_recording']; ?>"
                        data-desc="<?php echo htmlspecialchars($v['description'] ?? ''); ?>"
                        data-status="<?php echo $v['today_status']; ?>"
                        data-avail="<?php echo $isAvail ? '1' : '0'; ?>"
                        data-next-available="<?php echo htmlspecialchars($v['next_available_date'] ?? ''); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        View Details
                    </button>
                    <?php if (!$isAvail): ?>
                    <div class="rented-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="11" height="11"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        Currently rented — see calendar for available dates
                    </div>
                    <?php endif; ?>
                    <div class="btn-row">
                    <button class="btn-reserve <?php echo !$isAvail ? 'btn-reserve-future' : ''; ?>"
                        data-id="<?php echo $v['id']; ?>"
                        data-name="<?php echo htmlspecialchars($v['name']); ?>"
                        data-unit="<?php echo $v['unit_number']; ?>"
                        data-next-available="<?php echo htmlspecialchars($v['next_available_date'] ?? ''); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        <?php echo $isAvail ? 'Reserve' : 'Reserve Future Date'; ?>
                    </button>
                    <button class="btn-cart <?php echo $inCart ? 'in-cart' : ''; ?>"
                        data-id="<?php echo $v['id']; ?>"
                        data-name="<?php echo htmlspecialchars($v['name']); ?>">
                        <?php if ($inCart): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            In Cart
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                            Add to Cart
                        <?php endif; ?>
                    </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
</div>

<!-- ── UNIT DETAIL MODAL ── -->
<div class="modal-overlay" id="detailModal">
    <div class="modal" style="max-width:520px;">
        <div class="modal-header">
            <div>
                <div class="modal-eyebrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
                    Unit Details
                </div>
                <div class="modal-title" id="detailName">—</div>
                <div class="modal-unit"  id="detailUnit"></div>
            </div>
            <button class="modal-close" id="detailClose">&#215;</button>
        </div>
        <div class="modal-body">

            <!-- Mic illustration -->
            <div class="detail-hero" id="detailHero">
                <svg viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg" width="90" height="90">
                    <rect x="45" y="10" width="30" height="48" rx="15" fill="#F5C518" opacity="0.9"/>
                    <path d="M25 58 C25 82 95 82 95 58" stroke="#F5C518" stroke-width="4" stroke-linecap="round" fill="none"/>
                    <line x1="60" y1="80" x2="60" y2="98" stroke="#F5C518" stroke-width="4" stroke-linecap="round"/>
                    <line x1="44" y1="98" x2="76" y2="98" stroke="#F5C518" stroke-width="4" stroke-linecap="round"/>
                </svg>
                <div class="detail-status-wrap">
                    <span class="status-ribbon" id="detailStatusRibbon" style="position:static; display:inline-flex;"></span>
                </div>
            </div>

            <!-- Description -->
            <p class="detail-desc" id="detailDesc"></p>

            <!-- Specs grid -->
            <div class="detail-specs-grid">
                <div class="dsg-item">
                    <div class="dsg-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    </div>
                    <div class="dsg-label">Screen Size</div>
                    <div class="dsg-value" id="detailScreen">—</div>
                </div>
                <div class="dsg-item">
                    <div class="dsg-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/></svg>
                    </div>
                    <div class="dsg-label">Microphones</div>
                    <div class="dsg-value" id="detailMics">—</div>
                </div>
                <div class="dsg-item">
                    <div class="dsg-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                    </div>
                    <div class="dsg-label">Song Library</div>
                    <div class="dsg-value" id="detailSongs">—</div>
                </div>
                <div class="dsg-item" id="dsgBT">
                    <div class="dsg-icon" style="background:rgba(61,214,140,0.08);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="var(--available)" stroke-width="2"><polyline points="6.5 6.5 17.5 17.5 12 23 12 1 17.5 6.5 6.5 17.5"/></svg>
                    </div>
                    <div class="dsg-label">Bluetooth</div>
                    <div class="dsg-value" id="detailBT">—</div>
                </div>
                <div class="dsg-item" id="dsgRec">
                    <div class="dsg-icon" style="background:rgba(78,168,245,0.08);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#4ea8f5" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3" fill="#4ea8f5"/></svg>
                    </div>
                    <div class="dsg-label">Recording</div>
                    <div class="dsg-value" id="detailRec">—</div>
                </div>
                <div class="dsg-item">
                    <div class="dsg-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </div>
                    <div class="dsg-label">Rental Period</div>
                    <div class="dsg-value">3 Days</div>
                </div>
            </div>

            <!-- Price note -->
            <div class="detail-price-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                Price depends on your delivery location — select it during reservation.
            </div>

            <!-- Action buttons -->
            <div id="detailActions" style="display:flex; gap:8px; margin-top:18px;"></div>
        </div>
    </div>
</div>

<!-- ── RESERVE MODAL ── -->
<div class="modal-overlay" id="reserveModal">
    <div class="modal">
        <div class="modal-header">
            <div>
                <div class="modal-eyebrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                    New Reservation
                </div>
                <div class="modal-title" id="modalTitle">Reserve Unit</div>
                <div class="modal-unit"  id="modalUnit"></div>
            </div>
            <button class="modal-close" id="modalClose">&#215;</button>
        </div>
        <div class="modal-body">
            <div id="modalError" class="modal-alert" style="display:none;"></div>
            <form id="reserveForm">
                <input type="hidden" name="videoke_id" id="modalVideoke">

                <!-- Calendar date picker -->
                <div class="form-group">
                    <label>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="11" height="11"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Select Start Date <span class="label-hint">· 3-day rental, end date auto-set</span>
                    </label>
                    <!-- Hidden inputs still submitted with form -->
                    <input type="hidden" name="start_date" id="startDate">
                    <input type="hidden" name="end_date"   id="endDate">
                    <!-- Custom calendar -->
                    <div class="cal-wrap" id="calWrap">
                        <div class="cal-nav">
                            <button type="button" class="cal-nav-btn" id="calPrev">&#8249;</button>
                            <span class="cal-month-label" id="calMonthLabel"></span>
                            <button type="button" class="cal-nav-btn" id="calNext">&#8250;</button>
                        </div>
                        <div class="cal-weekdays">
                            <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                        </div>
                        <div class="cal-grid" id="calGrid"></div>
                        <div class="cal-legend">
                            <span class="cal-legend-item"><span class="cal-legend-dot booked"></span> Booked</span>
                            <span class="cal-legend-item"><span class="cal-legend-dot selected"></span> Your dates</span>
                            <span class="cal-legend-item"><span class="cal-legend-dot today-dot"></span> Today</span>
                        </div>
                        <div class="cal-selection" id="calSelection" style="display:none;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span id="calSelectionText"></span>
                        </div>
                    </div>
                </div>

                <!-- Phone -->
                <div class="form-group">
                    <label>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="11" height="11"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.63 3.18 2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.96a16 16 0 0 0 6.13 6.13l1.02-.97a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        Phone Number
                    </label>
                    <input type="tel" name="phone" id="modalPhone" placeholder="09XX XXX XXXX" required>
                </div>

                <!-- Location -->
                <div class="form-section-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    Delivery Location
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label>Barangay</label>
                        <div class="select-wrap">
                            <select name="barangay" id="modalBarangay" required>
                                <option value="" disabled selected>Select barangay</option>
                                <?php foreach (array_keys($areaMap) as $brgy): ?>
                                <option value="<?= htmlspecialchars($brgy) ?>"><?= htmlspecialchars($brgy) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Sitio / Purok</label>
                        <div class="select-wrap">
                            <select name="sitio" id="modalSitio" required disabled>
                                <option value="" disabled selected>Select sitio</option>
                            </select>
                            <svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                    </div>
                </div>
                <div class="form-group" id="modalOtherWrap" style="display:none;">
                    <label>Enter Sitio / Purok Name</label>
                    <input type="text" name="sitio_other" id="modalSitioOther" placeholder="e.g. Purok 3">
                </div>

                <!-- Notes -->
                <div class="form-group">
                    <label>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="11" height="11"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Notes <span class="label-hint">optional</span>
                    </label>
                    <textarea name="notes" placeholder="Delivery instructions, landmark, special requests…"></textarea>
                </div>

                <!-- Price summary — single total, delivery included -->
                <div class="modal-price-box">
                    <div class="mpb-left">
                        <div class="mpb-label">Total Amount</div>
                        <div class="mpb-sub" id="modalPriceSub">3-day rental · delivery included</div>
                    </div>
                    <div class="mpb-amount" id="modalTotal">&#8369;0</div>
                </div>

                <button type="submit" class="btn-confirm" id="modalSubmitBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="15" height="15"><polyline points="20 6 9 17 4 12"/></svg>
                    Confirm Reservation
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ── NOTIFICATION DRAWER ── -->
<div class="drawer-overlay" id="notifOverlay"></div>
<div class="cart-drawer" id="notifDrawer" style="width:360px;">
    <div class="drawer-header">
        <span class="drawer-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="color:var(--gold);"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            Notifications
        </span>
        <div style="display:flex; align-items:center; gap:8px;">
            <button class="notif-read-all" id="notifReadAll">Mark all read</button>
            <button class="drawer-close" id="notifClose">&#215;</button>
        </div>
    </div>
    <div class="drawer-items" id="notifList">
        <div class="drawer-empty" id="notifEmpty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <p>No notifications yet.<br>We'll let you know when your reservation status changes!</p>
        </div>
    </div>
</div>

<!-- ── CART DRAWER ── -->
<div class="drawer-overlay" id="drawerOverlay"></div>
<div class="cart-drawer" id="cartDrawer">
    <div class="drawer-header">
        <span class="drawer-title">My Cart</span>
        <button class="drawer-close" id="drawerClose">&#215;</button>
    </div>
    <div class="drawer-items" id="drawerItems">
        <div class="drawer-empty" id="emptyMsg" style="display:<?php echo empty($cartRows) ? 'block' : 'none'; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            <p>Your cart is empty.<br>Add a unit to get started!</p>
        </div>
        <?php foreach ($cartRows as $ci): ?>
        <div class="cart-item-card" id="ci-<?php echo $ci['videoke_id']; ?>">
            <div class="ci-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                    <line x1="12" y1="19" x2="12" y2="23"/>
                    <line x1="8"  y1="23" x2="16" y2="23"/>
                </svg>
            </div>
            <div class="ci-info">
                <div class="ci-name">Unit <?php echo str_pad($ci['unit_number'],2,'0',STR_PAD_LEFT); ?> &middot; <?php echo htmlspecialchars($ci['v_name']); ?></div>
                <div class="ci-dates"><?php echo $ci['start_date']; ?> &#8594; <?php echo $ci['end_date']; ?></div>
                <div class="ci-price ci-price-note">Price set at reservation</div>
            </div>
            <button class="ci-remove" data-id="<?php echo $ci['videoke_id']; ?>">&#215;</button>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="drawer-footer">
        <div class="drawer-total">
            <span>Items in cart</span>
            <strong id="drawerTotal"><?php echo $cartCount; ?></strong>
        </div>
        <button class="btn-checkout" id="checkoutBtn">Proceed to Reserve</button>
    </div>
</div>

<!-- ── CUSTOMER SERVICE CHAT ── -->
<div class="cs-overlay" id="csOverlay"></div>
<div class="cs-panel" id="csPanel">
    <div class="cs-header">
        <div class="cs-header-info">
            <div class="cs-avatar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div>
                <div class="cs-header-name">JKS Support</div>
                <div class="cs-header-status">
                    <span class="cs-online-dot"></span> Online
                </div>
            </div>
        </div>
        <button class="cs-close" id="csClose">&#215;</button>
    </div>

    <div class="cs-messages" id="csMessages">
        <!-- Welcome bubble -->
        <div class="cs-bubble cs-bubble-admin">
            <div class="cs-bubble-text">
                👋 Hi <strong><?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?></strong>! Welcome to JKS Videoke Support. How can we help you today?
            </div>
            <div class="cs-bubble-time">JKS Support</div>
        </div>
        <!-- Quick reply suggestions -->
        <div class="cs-suggestions" id="csSuggestions">
            <button class="cs-suggest-btn" data-msg="I want to ask about my reservation status.">📋 My reservation status</button>
            <button class="cs-suggest-btn" data-msg="When will my unit be delivered?">🚚 Delivery inquiry</button>
            <button class="cs-suggest-btn" data-msg="I want to cancel my reservation.">❌ Cancel reservation</button>
            <button class="cs-suggest-btn" data-msg="I have a concern about the unit I rented.">⚠️ Unit concern</button>
        </div>
    </div>

    <div class="cs-input-wrap">
        <textarea class="cs-input" id="csInput" placeholder="Type your message…" rows="1"></textarea>
        <button class="cs-send" id="csSend">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </button>
    </div>
</div>


<!-- ── PROFILE OVERLAY + PANEL ── -->
<div class="profile-overlay" id="profileOverlay"></div>
<div class="profile-panel" id="profilePanel">

    <!-- Header -->
    <div class="pp-header">
        <!-- Avatar — Facebook-style: camera badge + context menu -->
        <div class="pp-avatar-wrap" id="ppAvatarWrap">
            <?php if (!empty($user['avatar'])): ?>
            <img src="<?php echo htmlspecialchars($user['avatar']); ?>"
                 alt="Profile Photo" class="pp-avatar-img" id="ppAvatarImg">
            <?php else: ?>
            <div class="pp-avatar-large" id="ppAvatarLarge"><?php echo htmlspecialchars(strtoupper(substr($user['name'], 0, 1))); ?></div>
            <?php endif; ?>

            <!-- Camera badge (always visible) -->
            <div class="pp-avatar-overlay" id="ppAvatarOverlay">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                    <circle cx="12" cy="13" r="4"/>
                </svg>
            </div>

            <!-- Hidden file input -->
            <input type="file" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">

            <!-- Facebook-style context menu -->
            <div class="pp-avatar-menu" id="ppAvatarMenu">
                <?php if (!empty($user['avatar'])): ?>
                <button class="pp-avatar-menu-item" id="ppMenuSee">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    See profile picture
                </button>
                <?php endif; ?>
                <button class="pp-avatar-menu-item" id="ppMenuChoose">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    Choose profile picture
                </button>
                <?php if (!empty($user['avatar'])): ?>
                <button class="pp-avatar-menu-item danger" id="ppMenuRemove">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    Remove photo
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="pp-header-info">
            <div class="pp-name"  id="ppName"><?php echo htmlspecialchars($user['name']); ?></div>
            <div class="pp-email" id="ppEmail"><?php echo htmlspecialchars($user['email']); ?></div>
            <span class="pp-role-badge"><?php echo ucfirst($user['role']); ?></span>
        </div>
        <button class="pp-close" id="profileClose">&#215;</button>
    </div>

    <!-- Scrollable body: tabs + content -->
    <div class="pp-body">
    <!-- Tabs -->
    <div class="pp-tabs">
        <button class="pp-tab active" data-tab="info">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            My Info
        </button>
        <button class="pp-tab" data-tab="password">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Password
        </button>
    </div>

    <!-- Tab: My Info -->
    <div class="pp-tab-content active" id="tab-info">
        <div id="profileAlert" class="pp-alert" style="display:none;"></div>
        <form id="profileForm">
            <div class="pp-field-group">
                <label for="fieldName">Full Name</label>
                <input type="text" id="fieldName" name="name"
                       value="<?php echo htmlspecialchars($user['name']); ?>"
                       placeholder="Juan Dela Cruz" required>
            </div>
            <div class="pp-field-group">
                <label for="fieldEmail">Email Address</label>
                <input type="email" id="fieldEmail" name="email"
                       value="<?php echo htmlspecialchars($user['email']); ?>"
                       placeholder="you@example.com" required>
            </div>
            <?php
            // Fetch phone and location from DB (not stored in session)
            $profileRow = $pdo->prepare("SELECT phone, location, created_at, avatar FROM users WHERE id = ? LIMIT 1");
            $profileRow->execute([$user['id']]);
            $profileData = $profileRow->fetch();
            ?>
            <div class="pp-field-group">
                <label for="fieldPhone">Phone Number</label>
                <input type="tel" id="fieldPhone" name="phone"
                       value="<?php echo htmlspecialchars($profileData['phone'] ?? ''); ?>"
                       placeholder="09XX XXX XXXX" required>
            </div>
            <div class="pp-field-group">
                <label for="fieldLocation">Delivery Location</label>
                <input type="text" id="fieldLocation" name="location"
                       value="<?php echo htmlspecialchars($profileData['location'] ?? ''); ?>"
                       placeholder="e.g. Brgy. Calidngan, Carcar City" required>
            </div>
            <button type="submit" class="pp-save-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Save Changes
            </button>
        </form>
    </div>

    <!-- Tab: Password -->
    <div class="pp-tab-content" id="tab-password">
        <div id="passwordAlert" class="pp-alert" style="display:none;"></div>
        <form id="passwordForm">
            <div class="pp-field-group">
                <label for="currentPass">Current Password</label>
                <div class="pp-input-wrap">
                    <input type="password" id="currentPass" name="current_password" placeholder="••••••••" required>
                    <button type="button" class="toggle-pass" data-target="currentPass" tabindex="-1">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <div class="pp-field-group">
                <label for="newPass">New Password</label>
                <div class="pp-input-wrap">
                    <input type="password" id="newPass" name="new_password" placeholder="At least 6 characters" required minlength="6">
                    <button type="button" class="toggle-pass" data-target="newPass" tabindex="-1">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <div class="pp-strength" id="passStrength" style="display:none;">
                    <div class="pp-strength-bar"><div class="pp-strength-fill" id="strengthFill"></div></div>
                    <span id="strengthLabel"></span>
                </div>
            </div>
            <div class="pp-field-group">
                <label for="confirmPass">Confirm New Password</label>
                <div class="pp-input-wrap">
                    <input type="password" id="confirmPass" name="confirm_password" placeholder="••••••••" required>
                    <button type="button" class="toggle-pass" data-target="confirmPass" tabindex="-1">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="pp-save-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Update Password
            </button>
        </form>
    </div>

    </div><!-- /.pp-body -->

    <!-- Footer -->
    <div class="pp-footer">
        <a href="logout.php" class="pp-logout-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Log Out
        </a>
        <span class="pp-member-since">Member since <?php echo htmlspecialchars(date('M Y', strtotime($profileData['created_at'] ?? 'now'))); ?></span>
    </div>

</div>

<!-- ── FLOATING UNIT SCROLL BUTTONS (mobile only) ── -->
<div class="unit-float-nav" id="unitFloatNav">
    <button class="unit-float-btn" id="unitFloatUp" title="Previous unit" disabled>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 15 12 9 18 15"/>
        </svg>
    </button>
    <span class="unit-float-counter" id="unitFloatCounter">1 / <?php echo count($videokes); ?></span>
    <button class="unit-float-btn" id="unitFloatDown" title="Next unit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    </button>
</div>

<!-- ── AVATAR LIGHTBOX (See profile picture) ── -->
<div class="pp-lightbox" id="ppLightbox">
    <button class="pp-lightbox-close" id="ppLightboxClose">&#215;</button>
    <div id="ppLightboxContent"></div>
</div>

<!-- ── TOAST ── -->
<div class="toast-container" id="toastContainer"></div>

<script>
let cartTotal = 0;
let cartCount = <?php echo $cartCount; ?>;
const areaMap      = <?php echo json_encode($areaMap); ?>;
const bookedRanges = <?php echo json_encode($bookedRanges); ?>;
</script>
<!-- ── CROP MODAL ── -->
<div class="crop-overlay" id="cropOverlay">
    <div class="crop-modal">
        <div class="crop-modal-header">
            <span class="crop-modal-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                Edit Profile Photo
            </span>
            <button class="crop-modal-close" id="cropCancel">&#215;</button>
        </div>
        <div class="crop-modal-body">
            <div class="crop-hint">Drag to reposition · Pinch or scroll to zoom</div>
            <div class="crop-container">
                <img id="cropImage" src="" alt="Crop preview">
            </div>
            <div class="crop-zoom-bar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                <input type="range" id="cropZoom" min="0.1" max="3" step="0.01" value="1" class="crop-zoom-slider">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
            </div>
        </div>
        <div class="crop-modal-footer">
            <button class="crop-btn-cancel" id="cropCancelBtn">Cancel</button>
            <button class="crop-btn-confirm" id="cropConfirmBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg>
                Use Photo
            </button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script src="js/home.js"></script>
</body>
</html>