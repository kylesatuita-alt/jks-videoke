<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

requireLogin();
$user  = getCurrentUser();
$today = date('Y-m-d');

// Fetch user's favorites with availability status
$stmt = $pdo->prepare("
    SELECT v.*,
        CASE
            WHEN EXISTS (
                SELECT 1 FROM reservations r
                WHERE r.videoke_id = v.id
                  AND r.status IN ('confirmed','active','pending')
                  AND ? BETWEEN r.start_date AND r.end_date
            ) THEN 'unavailable'
            ELSE 'available'
        END AS today_status,
        (
            SELECT DATE_ADD(MAX(r2.end_date), INTERVAL 1 DAY)
            FROM reservations r2
            WHERE r2.videoke_id = v.id
              AND r2.status IN ('confirmed','active','pending')
              AND r2.end_date >= ?
        ) AS next_available_date
    FROM favorites f
    JOIN videokes v ON v.id = f.videoke_id
    WHERE f.user_id = ?
    ORDER BY f.added_at DESC
");
$stmt->execute([$today, $today, $user['id']]);
$favorites = $stmt->fetchAll();

$cartItems = $pdo->prepare("
    SELECT c.*, v.name AS v_name, v.unit_number, v.price_3days
    FROM cart c JOIN videokes v ON v.id = c.videoke_id
    WHERE c.user_id = ?
    ORDER BY c.added_at DESC
");
$cartItems->execute([$user['id']]);
$cartRows = $cartItems->fetchAll();

// Booked date ranges per unit (for calendar blocking in reserve modal)
$bookedRows = $pdo->query("
    SELECT videoke_id, start_date, end_date
    FROM reservations
    WHERE status IN ('pending','confirmed','active','delivering')
      AND end_date >= CURDATE()
    ORDER BY videoke_id, start_date
")->fetchAll();
$bookedRanges = [];
foreach ($bookedRows as $b) {
    $bookedRanges[$b['videoke_id']][] = [$b['start_date'], $b['end_date']];
}

$cartCount = count($cartRows);
$availCount = count(array_filter($favorites, fn($v) => $v['today_status'] === 'available'));

// Fetch delivery areas for reserve modal (same as home.php)
$areaRows = $pdo->query("SELECT barangay, sitio, delivery_fee FROM delivery_areas ORDER BY barangay ASC, sitio ASC")->fetchAll();
$areaMap  = [];
foreach ($areaRows as $a) {
    $areaMap[$a['barangay']][$a['sitio']] = (float)$a['delivery_fee'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JKS Videoke — My Favorites</title>
    <link rel="stylesheet" href="css/home.css">
</head>
<!-- data-page lets JS know it's the favorites page (to remove card on unfavorite) -->
<body data-page="favorites">

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
        <a href="index.php" class="nav-link-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Browse Units
        </a>
        <span class="nav-greeting">Hi, <strong><?php echo htmlspecialchars($user['name']); ?></strong></span>
        <button class="cart-btn" id="cartToggle">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            Cart
            <span class="cart-badge <?php echo $cartCount > 0 ? 'visible' : ''; ?>" id="cartBadge"><?php echo $cartCount; ?></span>
        </button>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </div>
</nav>

<!-- ── HERO ── -->
<div class="hero">
    <div class="hero-eyebrow">
        <span class="hero-eyebrow-dot"></span>
        My Favorites
    </div>
    <h1>Your <em>Saved</em> Units</h1>
    <p class="hero-sub">
        Units you've starred for quick access.
        <?php if (!empty($favorites)): ?>
            <?php echo $availCount; ?> of <?php echo count($favorites); ?> are available today.
        <?php endif; ?>
    </p>
</div>

<!-- ── CONTENT ── -->
<div class="grid-wrapper">

    <?php if (empty($favorites)): ?>
    <!-- Empty state -->
    <div class="fav-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
        </svg>
        <h3>No favorites yet</h3>
        <p>Browse units and tap the ♥ heart to save them here.</p>
        <a href="index.php" class="btn-goto-browse">Browse Units</a>
    </div>

    <?php else: ?>

    <!-- Filter chips -->
    <div class="controls-bar" style="margin-top:0; margin-bottom:28px;">
        <button class="filter-chip active" data-filter="all">All Favorites</button>
        <button class="filter-chip" data-filter="available">&#10003; Available Today</button>
        <button class="filter-chip" data-filter="unavailable">Currently Rented</button>
    </div>

    <div class="unit-grid" id="unitGrid">
        <?php foreach ($favorites as $v):
            $isAvail = $v['today_status'] === 'available';
            $opacity = $isAvail ? '0.9' : '0.28';
        ?>
        <div class="v-card <?php echo $isAvail ? '' : 'unavailable'; ?>" data-status="<?php echo $v['today_status']; ?>">

            <div class="v-card-img">
                <span class="unit-tag">UNIT <?php echo str_pad($v['unit_number'], 2, '0', STR_PAD_LEFT); ?></span>
                <svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="30" y="8" width="20" height="30" rx="10" fill="#F5C518" opacity="<?php echo $opacity; ?>"/>
                    <path d="M18 38 C18 54 62 54 62 38" stroke="#F5C518" stroke-width="3" stroke-linecap="round" fill="none" opacity="<?php echo $opacity; ?>"/>
                    <line x1="40" y1="53" x2="40" y2="65" stroke="#F5C518" stroke-width="3" stroke-linecap="round" opacity="<?php echo $opacity; ?>"/>
                    <line x1="30" y1="65" x2="50" y2="65" stroke="#F5C518" stroke-width="3" stroke-linecap="round" opacity="<?php echo $opacity; ?>"/>
                    <?php if ($v['has_bluetooth']): ?>
                    <path d="M63 22 L67 26 L60 33 L67 40 L63 44 L63 22Z" stroke="#F5C518" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" opacity="<?php echo $isAvail ? '0.45' : '0.15'; ?>"/>
                    <?php endif; ?>
                </svg>

                <!-- Heart button — already saved, clicking removes -->
                <button class="btn-fav active" data-id="<?php echo $v['id']; ?>" title="Remove from favorites">
                    <svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                </button>

                <span class="status-ribbon <?php echo $isAvail ? 'available' : 'rented'; ?>">
                    <?php echo $isAvail ? '&#10003; Available' : '&#10007; Rented'; ?>
                </span>
            </div>

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
                    <?php if ($isAvail): ?>
                    <div class="btn-row">
                    <button class="btn-reserve"
                        data-id="<?php echo $v['id']; ?>"
                        data-name="<?php echo htmlspecialchars($v['name']); ?>"
                        data-unit="<?php echo $v['unit_number']; ?>"
                        data-next-available="<?php echo htmlspecialchars($v['next_available_date'] ?? ''); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        Reserve
                    </button>
                    <button class="btn-cart"
                        data-id="<?php echo $v['id']; ?>"
                        data-name="<?php echo htmlspecialchars($v['name']); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        Add to Cart
                    </button>
                    </div>
                    <?php else: ?>
                    <div class="btn-row">
                    <button class="btn-reserve btn-reserve-future"
                        data-id="<?php echo $v['id']; ?>"
                        data-name="<?php echo htmlspecialchars($v['name']); ?>"
                        data-unit="<?php echo $v['unit_number']; ?>"
                        data-next-available="<?php echo htmlspecialchars($v['next_available_date'] ?? ''); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        Reserve Future Date
                    </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<!-- ── RESERVE MODAL (same as home.php) ── -->
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

                <!-- Price: delivery_fee IS the total -->
                <div class="modal-price-box">
                    <div class="mpb-left">
                        <div class="mpb-label">Total Amount</div>
                        <div class="mpb-sub" id="modalPriceSub">Select your location to see price</div>
                    </div>
                    <div class="mpb-amount" id="modalTotal">—</div>
                </div>

                <button type="submit" class="btn-confirm" id="modalSubmitBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="15" height="15"><polyline points="20 6 9 17 4 12"/></svg>
                    Confirm Reservation
                </button>
            </form>
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

<!-- ── TOAST ── -->
<div class="toast-container" id="toastContainer"></div>

<script>
let cartTotal = 0;
let cartCount = <?php echo $cartCount; ?>;
const areaMap     = <?php echo json_encode($areaMap); ?>;
const bookedRanges = <?php echo json_encode($bookedRanges); ?>;
const IS_GUEST = false;

// Pre-populate cart items from server
const serverCartRows = <?php echo json_encode(array_map(fn($c) => [
    'id'         => $c['id'],
    'videoke_id' => $c['videoke_id'],
    'v_name'     => $c['v_name'],
    'unit_number'=> $c['unit_number'],
], $cartRows)); ?>;
</script>
<script src="js/home.js"></script>
</body>
</html>