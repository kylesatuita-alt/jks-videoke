// =============================================
//  JKS VIDEOKE — Home Page JS
// =============================================

document.addEventListener('DOMContentLoaded', () => {

    // ── Helpers ──────────────────────────────
    const $  = id => document.getElementById(id);
    const $$ = sel => document.querySelectorAll(sel);
    const today = new Date().toISOString().split('T')[0];

    // ── Guest gate — intercept Reserve/Cart/Fav clicks ──
    if (typeof IS_GUEST !== 'undefined' && IS_GUEST) {
        const guestModal = document.getElementById('guestModal');

        function showGuestModal(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (guestModal) {
                guestModal.classList.add('open');
                document.body.style.overflow = 'hidden';
            }
        }

        // Intercept all guest-gated buttons
        document.querySelectorAll('[data-guest="1"]').forEach(btn => {
            btn.addEventListener('click', showGuestModal, true); // capture phase
        });

        // Close modal on overlay click
        guestModal?.addEventListener('click', e => {
            if (e.target === guestModal) {
                guestModal.classList.remove('open');
                document.body.style.overflow = '';
            }
        });

        // Close modal on X button click
        document.getElementById('guestModalClose')?.addEventListener('click', () => {
            guestModal.classList.remove('open');
            document.body.style.overflow = '';
        });

        // Also intercept the "Reserve" button inside the View Details panel
        document.addEventListener('click', e => {
            const btn = e.target.closest('.detail-reserve-btn, .detail-cart-btn');
            if (btn) { showGuestModal(e); }
        }, true);
    }

    function calcEndDate(start) {
        const d = new Date(start);
        d.setDate(d.getDate() + 3);
        return d.toISOString().split('T')[0];
    }

    function toast(msg, type = 'success') {
        const t = document.createElement('div');
        t.className = `toast ${type === 'error' ? 'error' : ''}`;
        t.innerHTML = type === 'success'
            ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> ${msg}`
            : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> ${msg}`;
        $('toastContainer').appendChild(t);
        setTimeout(() => t.remove(), 2800);
    }

    function updateBadge() {
        const badge = $('cartBadge');
        badge.textContent = cartCount;
        badge.classList.toggle('visible', cartCount > 0);
    }

    function updateDrawerTotal() {
        $('drawerTotal').textContent = '₱' + cartTotal.toLocaleString('en-PH', { minimumFractionDigits: 0 });
    }

    function updateEmptyState() {
        const items  = $$('.cart-item-card');
        const empty  = $('emptyMsg');
        const footer = document.querySelector('.drawer-footer');
        if (items.length === 0) {
            empty.style.display  = 'block';
            footer.style.opacity = '0.4';
            footer.style.pointerEvents = 'none';
        } else {
            empty.style.display  = 'none';
            footer.style.opacity = '1';
            footer.style.pointerEvents = 'all';
        }
    }

    // ── Filter chips ──────────────────────────
    $$('.filter-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            $$('.filter-chip').forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            const filter = chip.dataset.filter;
            $$('.v-card').forEach(card => {
                const show = filter === 'all' || card.dataset.status === filter;
                card.style.display = show ? '' : 'none';
            });
        });
    });

    // ── Unit Detail Modal ─────────────────────
    const detailModal = $('detailModal');
    const detailClose = $('detailClose');

    function openDetailModal(btn) {
        const d = btn.dataset;
        const isAvail = d.avail === '1';

        $('detailName').textContent = d.name;
        $('detailUnit').textContent = 'Unit ' + String(d.unit).padStart(2, '0') + ' · ' + d.brand + ' ' + d.model;
        $('detailDesc').textContent = d.desc || 'A great videoke unit for your party needs.';
        $('detailScreen').textContent = d.screen;
        $('detailMics').textContent   = d.mics + ' Mic' + (d.mics > 1 ? 's' : '');
        $('detailSongs').textContent  = d.songs + ' Songs';
        $('detailBT').textContent     = d.bt === '1' ? 'Yes' : 'No';
        $('detailRec').textContent    = d.rec === '1' ? 'Yes' : 'No';

        // Color Bluetooth & Recording based on yes/no
        $('dsgBT').style.opacity  = d.bt  === '1' ? '1' : '0.4';
        $('dsgRec').style.opacity = d.rec === '1' ? '1' : '0.4';

        // Status ribbon
        const ribbon = $('detailStatusRibbon');
        ribbon.className = 'status-ribbon ' + (isAvail ? 'available' : 'rented');
        ribbon.style.cssText = 'position:static; display:inline-flex;';
        if (isAvail) {
            ribbon.innerHTML = '&#10003; Available Today';
        } else {
            const nextAvail = d.nextAvailable;
            const nextLabel = nextAvail
                ? `Available from ${new Date(nextAvail + 'T00:00:00').toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})}`
                : 'Currently Rented';
            ribbon.innerHTML = `&#8987; ${nextLabel}`;
        }

        // Action buttons
        const actions = $('detailActions');
        if (isAvail) {
            actions.innerHTML = `
                <button class="btn-confirm detail-reserve-btn" style="flex:1;" data-id="${d.id}" data-name="${d.name}" data-unit="${d.unit}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    Reserve Now
                </button>
                <button class="btn-confirm detail-cart-btn" style="flex:1; background:var(--bg-elevated); color:var(--text); border:1px solid var(--border);" data-id="${d.id}" data-name="${d.name}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    Add to Cart
                </button>`;

            // Reserve from detail modal
            actions.querySelector('.detail-reserve-btn').addEventListener('click', () => {
                closeDetailModal();
                setTimeout(() => openModal(d.id, d.name, d.unit, d.nextAvailable || null), 180);
            });

            // Add to cart from detail modal
            actions.querySelector('.detail-cart-btn').addEventListener('click', async (e) => {
                const cartBtn = e.currentTarget;
                cartBtn.disabled = true;
                try {
                    const fd = new FormData();
                    fd.append('videoke_id', d.id);
                    fd.append('start_date', today);
                    fd.append('end_date',   calcEndDate(today));
                    const res  = await fetch('cart_action.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.success) {
                        cartBtn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg> Added!`;
                        cartBtn.style.color = 'var(--available)';
                        cartBtn.style.borderColor = 'rgba(61,214,140,0.4)';

                        // Also update the card btn on the grid
                        const gridBtn = document.querySelector(`.btn-cart[data-id="${d.id}"]`);
                        if (gridBtn) {
                            gridBtn.classList.add('in-cart');
                            gridBtn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> In Cart`;
                        }

                        cartCount++;
                        updateBadge();
                        updateEmptyState();
                        toast(`${d.name} added to cart!`);
                    } else {
                        toast(data.message || 'Could not add to cart.', 'error');
                    }
                } catch { toast('Network error.', 'error'); }
                cartBtn.disabled = false;
            });
        } else {
            actions.innerHTML = `<div class="btn-unavailable" style="width:100%; text-align:center; padding:11px;">Not Available Today</div>`;
        }

        detailModal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeDetailModal() {
        detailModal.classList.remove('open');
        document.body.style.overflow = '';
    }

    detailClose?.addEventListener('click', closeDetailModal);
    detailModal?.addEventListener('click', e => { if (e.target === detailModal) closeDetailModal(); });

    $$('.btn-details').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            openDetailModal(btn);
        });
    });


    $$('.btn-fav').forEach(btn => {
        btn.addEventListener('click', async e => {
            e.stopPropagation();
            const id = btn.dataset.id;
            btn.disabled = true;
            try {
                const fd = new FormData();
                fd.append('videoke_id', id);
                const res  = await fetch('favorite_action.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    const isFav = data.favorited;
                    btn.classList.toggle('active', isFav);
                    btn.title = isFav ? 'Remove from favorites' : 'Add to favorites';
                    btn.querySelector('svg').setAttribute('fill', isFav ? 'currentColor' : 'none');
                    toast(isFav ? 'Added to favorites! ♥' : 'Removed from favorites.');

                    // On favorites page: hide the card if unfavorited
                    if (!isFav && document.body.dataset.page === 'favorites') {
                        const card = btn.closest('.v-card');
                        if (card) {
                            card.style.transition = 'opacity 0.3s, transform 0.3s';
                            card.style.opacity    = '0';
                            card.style.transform  = 'scale(0.95)';
                            setTimeout(() => card.remove(), 320);
                        }
                    }
                } else {
                    toast(data.message || 'Could not update favorite.', 'error');
                }
            } catch {
                toast('Network error. Please try again.', 'error');
            }
            btn.disabled = false;
        });
    });

    // ── Custom Calendar Picker ────────────────
    let calYear, calMonth, calUnitId, calSelectedStart = null;

    const calGrid      = $('calGrid');
    const calMonthLbl  = $('calMonthLabel');
    const calSelection = $('calSelection');
    const calSelTxt    = $('calSelectionText');

    const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    function toYMD(date) {
        return date.toISOString().split('T')[0];
    }
    function ymdToDate(ymd) {
        const [y,m,d] = ymd.split('-').map(Number);
        return new Date(y, m-1, d);
    }
    function addDays(ymd, n) {
        const d = ymdToDate(ymd);
        d.setDate(d.getDate() + n);
        return toYMD(d);
    }

    function isBooked(ymd, unitId) {
        const ranges = bookedRanges[unitId] || [];
        return ranges.some(([s, e]) => ymd >= s && ymd <= e);
    }

    // Check if a 3-day range [start, start+1, start+2] overlaps any booked range
    function rangeHasConflict(startYmd, unitId) {
        const endYmd = addDays(startYmd, 2);
        const ranges = bookedRanges[unitId] || [];
        return ranges.some(([s, e]) => startYmd <= e && endYmd >= s);
    }

    function renderCal() {
        if (!calGrid || !calMonthLbl) return;
        calMonthLbl.textContent = `${MONTHS[calMonth]} ${calYear}`;
        const todayYmd = toYMD(new Date());
        const firstDay = new Date(calYear, calMonth, 1).getDay();
        const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();

        calGrid.innerHTML = '';

        // Empty cells before first day
        for (let i = 0; i < firstDay; i++) {
            const empty = document.createElement('div');
            empty.className = 'cal-day';
            calGrid.appendChild(empty);
        }

        for (let d = 1; d <= daysInMonth; d++) {
            const ymd  = `${calYear}-${String(calMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
            const cell = document.createElement('div');
            cell.className = 'cal-day';
            cell.textContent = d;

            const booked   = isBooked(ymd, calUnitId);
            const isPast   = ymd < todayYmd;
            const isToday  = ymd === todayYmd;

            if (booked)       cell.classList.add('booked');
            else if (isPast)  cell.classList.add('past');
            if (isToday)      cell.classList.add('is-today');

            // Highlight selected range
            if (calSelectedStart) {
                const endYmd = addDays(calSelectedStart, 2);
                if (ymd === calSelectedStart)         cell.classList.add('sel-start');
                else if (ymd === endYmd)              cell.classList.add('sel-end');
                else if (ymd > calSelectedStart && ymd < endYmd) cell.classList.add('in-range');
            }

            if (!booked && !isPast) {
                cell.addEventListener('click', () => onDayClick(ymd));
            }

            calGrid.appendChild(cell);
        }
    }

    function onDayClick(ymd) {
        // Check if 3-day block starting here has any conflict
        if (rangeHasConflict(ymd, calUnitId)) {
            // Find which days in the range are booked and tell user
            const end = addDays(ymd, 2);
            toast(`Some dates in ${ymd} – ${end} are already booked. Please pick another start date.`, 'error');
            return;
        }

        calSelectedStart = ymd;
        const endYmd = addDays(ymd, 2);

        // Update hidden inputs
        $('startDate').value = ymd;
        $('endDate').value   = endYmd;

        // Show selection strip
        const fmtOpts = { month: 'short', day: 'numeric', year: 'numeric' };
        const startFmt = ymdToDate(ymd).toLocaleDateString('en-PH', fmtOpts);
        const endFmt   = ymdToDate(endYmd).toLocaleDateString('en-PH', fmtOpts);
        calSelTxt.textContent = `${startFmt} → ${endFmt} (3 days)`;
        calSelection.style.display = 'flex';

        // Reset price since date changed
        $('modalTotal').textContent    = '—';
        $('modalPriceSub').textContent = 'Select your location to see price';
        if ($('modalBarangay').value && $('modalSitio').value) {
            // Re-trigger sitio change to recalculate
            $('modalSitio').dispatchEvent(new Event('change'));
        }

        renderCal();
    }

    $('calPrev')?.addEventListener('click', () => {
        calMonth--;
        if (calMonth < 0) { calMonth = 11; calYear--; }
        renderCal();
    });
    $('calNext')?.addEventListener('click', () => {
        calMonth++;
        if (calMonth > 11) { calMonth = 0; calYear++; }
        renderCal();
    });

    // ── Reserve Modal ─────────────────────────
    const modal      = $('reserveModal');
    const modalClose = $('modalClose');

    function openModal(id, name, unit, nextAvailable = null) {
        $('modalVideoke').value = id;
        $('modalTitle').textContent = name;
        $('modalUnit').textContent  = 'Unit ' + String(unit).padStart(2, '0');

        // Reset calendar state
        calUnitId      = id;
        calSelectedStart = null;
        $('startDate').value = '';
        $('endDate').value   = '';
        calSelection.style.display = 'none';

        // Start calendar on correct month
        const startFrom = nextAvailable && nextAvailable > today ? nextAvailable : today;
        const sf = ymdToDate(startFrom);
        calYear  = sf.getFullYear();
        calMonth = sf.getMonth();
        renderCal();

        // Show hint if unit is currently rented
        const errBox = $('modalError');
        if (nextAvailable && nextAvailable > today) {
            errBox.style.display     = 'block';
            errBox.style.background  = 'rgba(245,166,35,0.08)';
            errBox.style.borderColor = 'rgba(245,166,35,0.28)';
            errBox.style.color       = '#f5a623';
            errBox.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                This unit is currently rented. Red dates are unavailable — pick any white date to see your free window.`;
        } else {
            errBox.style.display = 'none';
        }

        // Reset price / location
        $('modalTotal').textContent    = '—';
        $('modalPriceSub').textContent = 'Select your location to see price';

        // Reset dropdowns
        $('modalBarangay').value = '';
        const sitioSel = $('modalSitio');
        sitioSel.innerHTML = '<option value="" disabled selected>Select sitio</option>';
        sitioSel.disabled  = true;
        $('modalOtherWrap').style.display = 'none';

        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }

    // Open modal on Reserve button click
    $$('.btn-reserve').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            openModal(btn.dataset.id, btn.dataset.name, btn.dataset.unit, btn.dataset.nextAvailable || null);
        });
    });

    modalClose.addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal(); closeDrawer(); } });

    // ── Modal location cascade ────────────────
    // areaMap: { barangay: { sitio: delivery_fee (= total price) } }
    $('modalBarangay')?.addEventListener('change', function () {
        const brgy     = this.value;
        const sitioSel = $('modalSitio');
        sitioSel.innerHTML = '<option value="" disabled selected>Select sitio</option>';
        sitioSel.disabled  = !brgy;
        $('modalOtherWrap').style.display = 'none';

        if (brgy && areaMap[brgy]) {
            const sitios = Object.keys(areaMap[brgy]).sort((a, b) => {
                if (a === 'Proper') return -1; // Proper always first
                if (b === 'Proper') return 1;
                if (a === 'Other')  return 1;  // Other always last
                if (b === 'Other')  return -1;
                return a.localeCompare(b);
            });
            sitios.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s;
                opt.textContent = s === 'Other' ? 'Other (type below)' : s;
                sitioSel.appendChild(opt);
            });
        }

        // Reset price until sitio chosen
        $('modalTotal').textContent    = '—';
        $('modalPriceSub').textContent = 'Select sitio to see price';
    });

    $('modalSitio')?.addEventListener('change', function () {
        const brgy = $('modalBarangay').value;
        const sit  = this.value;

        $('modalOtherWrap').style.display = sit === 'Other' ? 'block' : 'none';
        const otherInput = $('modalSitioOther');
        if (otherInput) otherInput.required = sit === 'Other';

        if (brgy && sit && areaMap[brgy]?.[sit] !== undefined) {
            // delivery_fee IS the all-in price (rental + delivery bundled)
            const total    = areaMap[brgy][sit];
            const sitLabel = sit === 'Other' ? 'Other' : sit;
            $('modalTotal').textContent    = '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 0 });
            $('modalPriceSub').textContent = `${brgy} · ${sitLabel} · 3-day rental, delivery included`;
        }
    });

    // Reserve form submit via AJAX
    $('reserveForm').addEventListener('submit', async e => {
        e.preventDefault();
        const errBox = $('modalError');

        // Validate date selected from calendar
        if (!$('startDate').value || !$('endDate').value) {
            errBox.style.display     = 'block';
            errBox.style.background  = 'rgba(224,92,92,0.1)';
            errBox.style.borderColor = 'rgba(224,92,92,0.3)';
            errBox.style.color       = '#f08080';
            errBox.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Please select a start date on the calendar first.`;
            return;
        }

        // Validate location selected
        const brgy = $('modalBarangay')?.value;
        const sit  = $('modalSitio')?.value;
        if (!brgy || !sit) {
            errBox.style.display     = 'block';
            errBox.style.background  = 'rgba(224,92,92,0.1)';
            errBox.style.borderColor = 'rgba(224,92,92,0.3)';
            errBox.style.color       = '#f08080';
            errBox.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Please select your barangay and sitio.`;
            return;
        }

        const submitBtn = $('modalSubmitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="15" height="15"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> Submitting…';

        const fd = new FormData(e.target);
        try {
            const res  = await fetch('reserve.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                toast('Reservation submitted! We\'ll contact you soon. 🎤');
                closeModal();
            } else {
                errBox.style.display     = 'block';
                errBox.style.background  = 'rgba(224,92,92,0.1)';
                errBox.style.borderColor = 'rgba(224,92,92,0.3)';
                errBox.style.color       = '#f08080';
                errBox.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> ${data.message || 'Something went wrong.'}`;
            }
        } catch {
            toast('Network error. Please try again.', 'error');
        }

        submitBtn.disabled = false;
        submitBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="15" height="15"><polyline points="20 6 9 17 4 12"/></svg> Confirm Reservation';
    });

    // ── Notification Drawer ───────────────────
    const notifDrawer  = $('notifDrawer');
    const notifOverlay = $('notifOverlay');
    const notifToggle  = $('notifToggle');
    const notifClose   = $('notifClose');
    const notifBadge   = $('notifBadge');
    const notifList    = $('notifList');
    const notifEmpty   = $('notifEmpty');

    function openNotifDrawer() {
        if (!notifDrawer) return;
        notifDrawer.classList.add('open');
        notifOverlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        loadNotifications();
    }
    function closeNotifDrawer() {
        if (!notifDrawer) return;
        notifDrawer.classList.remove('open');
        notifOverlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    if (!IS_GUEST && notifToggle) {
    notifToggle.addEventListener('click', openNotifDrawer);
    notifClose?.addEventListener('click', closeNotifDrawer);
    notifOverlay?.addEventListener('click', closeNotifDrawer);
    }

    function timeAgo(dateStr) {
        const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
        if (diff < 60)   return 'Just now';
        if (diff < 3600) return Math.floor(diff/60) + 'm ago';
        if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
        return Math.floor(diff/86400) + 'd ago';
    }

    async function loadNotifications() {
        try {
            const res  = await fetch('notifications.php?action=list');
            const data = await res.json();
            const items = data.notifications || [];

            // Remove old items (keep empty msg)
            notifList.querySelectorAll('.notif-item').forEach(el => el.remove());

            if (items.length === 0) {
                notifEmpty.style.display = 'block';
                return;
            }
            notifEmpty.style.display = 'none';

            items.forEach(n => {
                const div = document.createElement('div');
                div.className = 'notif-item' + (n.is_read === '0' || n.is_read === 0 ? ' unread' : '');
                div.dataset.id = n.id;
                const statusClass = n.res_status || '';
                div.innerHTML = `
                    <div class="notif-dot"></div>
                    <div class="notif-title">${n.title}</div>
                    <div class="notif-msg">${n.message}</div>
                    <div class="notif-meta">
                        <span class="notif-unit">Unit ${String(n.unit_number).padStart(2,'0')} · ${n.unit_name}</span>
                        <span class="notif-status ${statusClass}">${statusClass.charAt(0).toUpperCase()+statusClass.slice(1)}</span>
                        <span class="notif-time">${timeAgo(n.created_at)}</span>
                    </div>`;
                div.addEventListener('click', () => markRead(div, n.id));
                notifList.appendChild(div);
            });
        } catch(e) { console.error('Notification load error', e); }
    }

    async function markRead(el, id) {
        if (!el.classList.contains('unread')) return;
        const fd = new FormData();
        fd.append('action', 'read');
        fd.append('id', id);
        await fetch('notifications.php', { method: 'POST', body: fd });
        el.classList.remove('unread');
        el.querySelector('.notif-dot')?.remove();
        await refreshNotifCount();
    }

    $('notifReadAll')?.addEventListener('click', async () => {
        const fd = new FormData();
        fd.append('action', 'read_all');
        await fetch('notifications.php', { method: 'POST', body: fd });
        notifList?.querySelectorAll('.notif-item.unread').forEach(el => {
            el.classList.remove('unread');
            el.querySelector('.notif-dot')?.remove();
        });
        if (notifBadge) notifBadge.style.display = 'none';
        notifToggle?.classList.remove('has-unread');
    });

    async function refreshNotifCount() {
        if (!notifBadge || !notifToggle) return;
        try {
            const res  = await fetch('notifications.php?action=count');
            const data = await res.json();
            const count = data.count || 0;
            if (count > 0) {
                notifBadge.textContent  = count > 9 ? '9+' : count;
                notifBadge.style.display = 'flex';
                notifToggle.classList.add('has-unread');
            } else {
                notifBadge.style.display = 'none';
                notifToggle.classList.remove('has-unread');
            }
        } catch(e) {}
    }

    // Poll every 30 seconds for new notifications (logged-in only)
    if (!IS_GUEST) {
        refreshNotifCount();
        setInterval(refreshNotifCount, 30000);
    }

    const drawer        = $('cartDrawer');
    const drawerOverlay = $('drawerOverlay');

    function openDrawer() {
        drawer?.classList.add('open');
        drawerOverlay?.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
        drawer?.classList.remove('open');
        drawerOverlay?.classList.remove('open');
        document.body.style.overflow = '';
    }

    if (!IS_GUEST) {
    $('cartToggle')?.addEventListener('click', openDrawer);
    $('drawerClose')?.addEventListener('click', closeDrawer);
    }
    drawerOverlay?.addEventListener('click', closeDrawer);

    // ── Add to Cart ───────────────────────────
    $$('.btn-cart').forEach(btn => {
        btn.addEventListener('click', async e => {
            e.stopPropagation();
            if (btn.classList.contains('in-cart')) {
                openDrawer();
                return;
            }

            btn.disabled = true;
            try {
                const fd = new FormData();
                fd.append('videoke_id', btn.dataset.id);
                fd.append('start_date', today);
                fd.append('end_date',   calcEndDate(today));

                const res  = await fetch('cart_action.php', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    btn.classList.add('in-cart');
                    btn.innerHTML = `
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        In Cart`;

                    // Add to drawer (no price shown — price depends on delivery location)
                    const drawerItems = $('drawerItems');
                    const div = document.createElement('div');
                    div.className = 'cart-item-card';
                    div.id = `ci-${btn.dataset.id}`;
                    div.innerHTML = `
                        <div class="ci-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
                        </div>
                        <div class="ci-info">
                            <div class="ci-name">${btn.dataset.name}</div>
                            <div class="ci-dates">${today} → ${calcEndDate(today)}</div>
                            <div class="ci-price ci-price-note">Price set at reservation</div>
                        </div>
                        <button class="ci-remove" data-id="${btn.dataset.id}">×</button>`;
                    drawerItems.appendChild(div);

                    div.querySelector('.ci-remove').addEventListener('click', handleRemove);

                    cartCount++;
                    updateBadge();
                    updateEmptyState();
                    toast(`${btn.dataset.name} added to cart!`);
                } else {
                    toast(data.message || 'Could not add to cart.', 'error');
                }
            } catch {
                toast('Network error. Please try again.', 'error');
            }
            btn.disabled = false;
        });
    });

    // ── Remove from Cart ──────────────────────
    async function handleRemove(e) {
        const btn        = e.currentTarget;
        const videoke_id = btn.dataset.id;

        try {
            const fd = new FormData();
            fd.append('action',     'remove');
            fd.append('videoke_id', videoke_id);

            const res  = await fetch('cart_action.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                const item = $(`ci-${videoke_id}`);
                if (item) item.remove();

                const cardBtn = document.querySelector(`.btn-cart[data-id="${videoke_id}"]`);
                if (cardBtn) {
                    cardBtn.classList.remove('in-cart');
                    cardBtn.innerHTML = `
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        Add to Cart`;
                }

                cartCount = Math.max(0, cartCount - 1);
                updateBadge();
                updateEmptyState();
                toast('Removed from cart.');
            } else {
                toast(data.message || 'Could not remove item.', 'error');
            }
        } catch {
            toast('Network error.', 'error');
        }
    }

    // Attach remove to existing cart items (server-rendered)
    $$('.ci-remove').forEach(btn => btn.addEventListener('click', handleRemove));

    // ── Checkout / Proceed to Reserve ────────
    // Cart items get reserved one by one via the reserve modal flow
    $('checkoutBtn')?.addEventListener('click', () => {
        if (cartCount === 0) {
            toast('Your cart is empty!', 'error');
            return;
        }
        // Open the first cart item's reserve modal
        const firstCartItem = document.querySelector('.cart-item-card');
        if (firstCartItem) {
            const id = firstCartItem.querySelector('.ci-remove')?.dataset.id;
            if (id) {
                const reserveBtn = document.querySelector(`.btn-reserve[data-id="${id}"]`);
                if (reserveBtn) {
                    closeDrawer();
                    setTimeout(() => reserveBtn.click(), 280);
                    return;
                }
            }
        }
        // Fallback: close drawer and let user pick from cards
        closeDrawer();
        toast('Click Reserve on a unit card to complete your booking.', 'error');
    });

    // Init
    updateEmptyState();
    updateBadge();
    updateDrawerTotal();

    // ── Customer Service Chat ─────────────────
    const csPanel    = document.getElementById('csPanel');
    const csOverlay  = document.getElementById('csOverlay');
    const csToggle   = document.getElementById('csToggle');
    const csClose    = document.getElementById('csClose');
    const csInput    = document.getElementById('csInput');
    const csSend     = document.getElementById('csSend');
    const csMessages = document.getElementById('csMessages');
    if (!csPanel) return;
    let csLastId = 0;
    let csPollInterval = null;

    function openCS() {
        csPanel.classList.add('open');
        csOverlay.classList.add('open');
        csInput.focus();
        csToggle.classList.remove('has-unread');
        loadCsMessages();
        csPollInterval = setInterval(pollCsMessages, 5000);
    }
    function closeCS() {
        csPanel.classList.remove('open');
        csOverlay.classList.remove('open');
        clearInterval(csPollInterval);
    }
    csToggle?.addEventListener('click', openCS);
    csClose?.addEventListener('click',  closeCS);
    csOverlay?.addEventListener('click', closeCS);

    // Quick suggestion buttons
    document.querySelectorAll('.cs-suggest-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            csInput.value = btn.dataset.msg;
            btn.closest('.cs-suggestions')?.remove();
            sendCsMessage();
        });
    });

    function appendCsBubble(msg, sender, time) {
        const div = document.createElement('div');
        div.className = `cs-bubble cs-bubble-${sender}`;
        const t = time ? new Date(time).toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'}) : 'Now';
        div.innerHTML = `<div class="cs-bubble-text">${msg.replace(/\n/g,'<br>')}</div><div class="cs-bubble-time">${t}</div>`;
        csMessages.appendChild(div);
        csMessages.scrollTop = csMessages.scrollHeight;
    }

    async function sendCsMessage() {
        const msg = csInput.value.trim();
        if (!msg) return;
        csInput.value = ''; csInput.style.height = 'auto';
        appendCsBubble(msg, 'user', null);
        const fd = new FormData();
        fd.append('action', 'send'); fd.append('message', msg);
        try { await fetch('messages.php', { method: 'POST', body: fd }); } catch(e) {}
    }

    async function loadCsMessages() {
        try {
            const res  = await fetch('messages.php?action=fetch&since=0');
            const data = await res.json();
            csMessages.querySelectorAll('.cs-bubble').forEach(b => b.remove());
            (data.messages || []).forEach(m => {
                appendCsBubble(m.message, m.sender, m.created_at);
                csLastId = Math.max(csLastId, parseInt(m.id));
            });
        } catch(e) {}
    }

    async function pollCsMessages() {
        try {
            const res  = await fetch(`messages.php?action=fetch&since=${csLastId}`);
            const data = await res.json();
            (data.messages || []).forEach(m => {
                if (m.sender === 'admin') appendCsBubble(m.message, 'admin', m.created_at);
                csLastId = Math.max(csLastId, parseInt(m.id));
            });
        } catch(e) {}
    }

    async function checkCsUnread() {
        if (!csToggle) return;
        try {
            const res  = await fetch('messages.php?action=unread');
            const data = await res.json();
            csToggle.classList.toggle('has-unread', data.count > 0);
        } catch(e) {}
    }

    csSend?.addEventListener('click', sendCsMessage);
    csInput?.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendCsMessage(); } });
    csInput?.addEventListener('input', function() { this.style.height = 'auto'; this.style.height = Math.min(this.scrollHeight, 100) + 'px'; });

    if (csToggle) {
        checkCsUnread();
        setInterval(checkCsUnread, 30000);
    }
});


// =============================================
//  PROFILE PANEL
// =============================================
if (typeof IS_GUEST === 'undefined' || !IS_GUEST) (function () {
    const panel     = document.getElementById('profilePanel');
    const overlay   = document.getElementById('profileOverlay');
    const toggleBtn = document.getElementById('profileToggle');
    const closeBtn  = document.getElementById('profileClose');

    if (!panel || !toggleBtn) return;

    function openPanel()  { panel.classList.add('open'); overlay.classList.add('open'); toggleBtn.classList.add('open'); document.body.style.overflow = 'hidden'; }
    function closePanel() { panel.classList.remove('open'); overlay.classList.remove('open'); toggleBtn.classList.remove('open'); document.body.style.overflow = ''; }

    toggleBtn.addEventListener('click', () => panel.classList.contains('open') ? closePanel() : openPanel());
    closeBtn.addEventListener('click', closePanel);
    overlay.addEventListener('click', closePanel);

    document.querySelectorAll('.pp-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.pp-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.pp-tab-content').forEach(c => c.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
            document.querySelectorAll('.pp-alert').forEach(a => { a.style.display = 'none'; a.textContent = ''; });
        });
    });

    document.querySelectorAll('.toggle-pass').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target);
            input.type = input.type === 'password' ? 'text' : 'password';
            btn.style.color = input.type === 'text' ? 'var(--gold)' : '';
        });
    });

    const newPassInput  = document.getElementById('newPass');
    const strengthWrap  = document.getElementById('passStrength');
    const strengthFill  = document.getElementById('strengthFill');
    const strengthLabel = document.getElementById('strengthLabel');

    if (newPassInput) newPassInput.addEventListener('input', () => {
        const val = newPassInput.value;
        if (!val) { strengthWrap.style.display = 'none'; return; }
        strengthWrap.style.display = 'flex';

        let score = 0;
        if (val.length >= 6)  score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const levels = [
            { pct: '20%', color: '#E05C5C', label: 'Very weak' },
            { pct: '40%', color: '#F5A623', label: 'Weak' },
            { pct: '60%', color: '#F5C518', label: 'Fair' },
            { pct: '80%', color: '#6fcfaa', label: 'Good' },
            { pct: '100%',color: '#3DD68C', label: 'Strong' },
        ];
        const lvl = levels[Math.min(score - 1, 4)] || levels[0];
        strengthFill.style.width      = lvl.pct;
        strengthFill.style.background = lvl.color;
        strengthLabel.textContent     = lvl.label;
        strengthLabel.style.color     = lvl.color;
    });

    function showAlert(elId, msg, type) {
        const el = document.getElementById(elId);
        el.textContent   = msg;
        el.className     = 'pp-alert ' + type;
        el.style.display = 'block';
        if (type === 'success') setTimeout(() => { el.style.display = 'none'; }, 4000);
    }

    const profileForm = document.getElementById('profileForm');
    if (profileForm) profileForm.addEventListener('submit', async e => {
        e.preventDefault();
        const btn = e.target.querySelector('.pp-save-btn');
        btn.disabled = true; btn.textContent = 'Saving…';

        const fd = new FormData(e.target);
        fd.append('action', 'info');
        try {
            const res  = await fetch('profile_update.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showAlert('profileAlert', data.message, 'success');

                // ── Profile panel ──
                const ppName = document.getElementById('ppName');
                if (ppName) ppName.textContent = data.name;

                const ppEmail = document.getElementById('ppEmail');
                if (ppEmail) ppEmail.textContent = data.email;

                const ppInitial = document.getElementById('ppInitial');
                if (ppInitial) ppInitial.textContent = data.initial;

                // ── Navbar avatar: initials (only if showing, i.e. no photo) ──
                const navInitials = document.getElementById('navAvatarInitials');
                if (navInitials) navInitials.textContent = data.initial;

                // ── Navbar avatar name (first name, safe null check) ──
                const navAvatarName = document.querySelector('.avatar-name');
                if (navAvatarName) navAvatarName.textContent = data.name.split(' ')[0];

                // ── Navbar greeting "Hi, Name" ──
                const navGreeting = document.querySelector('.nav-greeting strong');
                if (navGreeting) navGreeting.textContent = data.name.split(' ')[0];

            } else {
                showAlert('profileAlert', data.message, 'error');
            }
        } catch { showAlert('profileAlert', 'Network error. Please try again.', 'error'); }

        btn.disabled = false;
        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Save Changes';
    });

    const passwordForm = document.getElementById('passwordForm');
    if (passwordForm) passwordForm.addEventListener('submit', async e => {
        e.preventDefault();
        const btn = e.target.querySelector('.pp-save-btn');
        btn.disabled = true; btn.textContent = 'Updating…';

        const fd = new FormData(e.target);
        fd.append('action', 'password');
        try {
            const res  = await fetch('profile_update.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showAlert('passwordAlert', data.message, 'success');
                e.target.reset();
                document.getElementById('passStrength').style.display = 'none';
            } else {
                showAlert('passwordAlert', data.message, 'error');
            }
        } catch { showAlert('passwordAlert', 'Network error. Please try again.', 'error'); }

        btn.disabled = false;
        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Update Password';
    });

    const nameField = document.getElementById('fieldName');
    if (nameField) nameField.addEventListener('input', function () {
        const pos = this.selectionStart;
        this.value = this.value.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
        this.setSelectionRange(pos, pos);
    });

    const phoneField = document.getElementById('fieldPhone');
    if (phoneField) phoneField.addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9+\-\s]/g, '');
    });

    // ── AVATAR UPLOAD + CROPPER ───────────────────────────────
    const avatarWrap      = document.getElementById('ppAvatarWrap');
    const avatarInput     = document.getElementById('avatarInput');

    // Crop modal elements
    const cropOverlay    = document.getElementById('cropOverlay');
    const cropImageEl    = document.getElementById('cropImage');
    const cropZoomSlider = document.getElementById('cropZoom');
    const cropConfirmBtn = document.getElementById('cropConfirmBtn');
    const cropCancelBtn  = document.getElementById('cropCancelBtn');
    const cropCancelX    = document.getElementById('cropCancel');
    let   cropper        = null;

    // ── Helper: update avatars in both navbar and panel ──
    function applyAvatarUrl(url) {
        const full = url + '?v=' + Date.now();

        // ── Panel avatar ──
        let panelImg = document.getElementById('ppAvatarImg');
        if (panelImg) {
            panelImg.src = full;
        } else {
            const initialsEl = document.getElementById('ppAvatarLarge');
            if (initialsEl) {
                const img     = document.createElement('img');
                img.src       = full;
                img.alt       = 'Profile Photo';
                img.className = 'pp-avatar-img';
                img.id        = 'ppAvatarImg';
                initialsEl.replaceWith(img);
            }
        }

        // ── Navbar avatar ──
        const navPhoto    = document.getElementById('navAvatarPhoto');
        const navInitials = document.getElementById('navAvatarInitials');
        const newNavImg   = document.createElement('img');
        newNavImg.src       = full;
        newNavImg.alt       = 'Avatar';
        newNavImg.className = 'avatar-photo';
        newNavImg.id        = 'navAvatarPhoto';
        if (navPhoto) navPhoto.replaceWith(newNavImg);
        else if (navInitials) navInitials.replaceWith(newNavImg);

        // ── Restore "See" + "Remove" menu items if missing ──
        const menu = document.getElementById('ppAvatarMenu');
        if (menu) {
            if (!document.getElementById('ppMenuSee')) {
                const seeBtn = document.createElement('button');
                seeBtn.className = 'pp-avatar-menu-item';
                seeBtn.id = 'ppMenuSee';
                seeBtn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> See profile picture`;
                seeBtn.addEventListener('click', e => {
                    e.stopPropagation();
                    menu.classList.remove('open');
                    const src = document.getElementById('ppAvatarImg')?.src;
                    const lbContent = document.getElementById('ppLightboxContent');
                    const lb = document.getElementById('ppLightbox');
                    if (lbContent) lbContent.innerHTML = src ? `<img src="${src}" class="pp-lightbox-img" alt="Profile Photo">` : '';
                    lb?.classList.add('open');
                    document.body.style.overflow = 'hidden';
                });
                menu.insertBefore(seeBtn, menu.firstChild);
            }
            if (!document.getElementById('ppMenuRemove')) {
                const removeBtn = document.createElement('button');
                removeBtn.className = 'pp-avatar-menu-item danger';
                removeBtn.id = 'ppMenuRemove';
                removeBtn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg> Remove photo`;
                removeBtn.addEventListener('click', e => { e.stopPropagation(); menu.classList.remove('open'); removeAvatar(); });
                menu.appendChild(removeBtn);
            }
        }
    }

    // ── Open crop modal ──
    function openCropper(file) {
        const reader = new FileReader();
        reader.onload = e => {
            cropImageEl.src = e.target.result;
            cropOverlay.classList.add('open');
            document.body.style.overflow = 'hidden';

            // Destroy old cropper if exists
            if (cropper) { cropper.destroy(); cropper = null; }

            // Init Cropper.js — square crop, free zoom
            cropper = new Cropper(cropImageEl, {
                aspectRatio: 1,
                viewMode:    1,
                dragMode:    'move',
                autoCropArea: 0.85,
                restore:     false,
                guides:      false,
                center:      true,
                highlight:   false,
                cropBoxMovable:   false,
                cropBoxResizable: false,
                toggleDragModeOnDblclick: false,
                zoom(e) {
                    // Sync slider with wheel/pinch zoom
                    if (cropZoomSlider) cropZoomSlider.value = e.detail.ratio;
                }
            });
        };
        reader.readAsDataURL(file);
    }

    function closeCropper() {
        cropOverlay.classList.remove('open');
        document.body.style.overflow = '';
        if (cropper) { cropper.destroy(); cropper = null; }
        cropZoomSlider.value = 1;
        if (avatarInput) avatarInput.value = '';
    }

    // ── Zoom slider ──
    cropZoomSlider?.addEventListener('input', function() {
        if (cropper) cropper.zoomTo(parseFloat(this.value));
    });

    // ── Cancel buttons ──
    cropCancelBtn?.addEventListener('click', closeCropper);
    cropCancelX?.addEventListener('click',   closeCropper);
    cropOverlay?.addEventListener('click', e => {
        if (e.target === cropOverlay) closeCropper();
    });

    // ── Confirm → upload cropped canvas ──
    cropConfirmBtn?.addEventListener('click', async () => {
        if (!cropper) return;

        const canvas   = cropper.getCroppedCanvas({ width: 400, height: 400, imageSmoothingQuality: 'high' });
        const origText = cropConfirmBtn.innerHTML;
        cropConfirmBtn.disabled = true;
        cropConfirmBtn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="spin" width="14" height="14"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg> Saving…`;

        canvas.toBlob(async blob => {
            const fd = new FormData();
            fd.append('action', 'avatar');
            fd.append('avatar', blob, 'avatar.jpg');

            try {
                const res  = await fetch('profile_update.php', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    closeCropper();
                    applyAvatarUrl(data.avatarUrl);
                    toast('Profile photo updated! 📸');
                } else {
                    toast(data.message || 'Upload failed.', 'error');
                    cropConfirmBtn.disabled = false;
                    cropConfirmBtn.innerHTML = origText;
                }
            } catch {
                toast('Network error. Please try again.', 'error');
                cropConfirmBtn.disabled = false;
                cropConfirmBtn.innerHTML = origText;
            }

        }, 'image/jpeg', 0.92);
    });

    // ── FACEBOOK-STYLE AVATAR CONTEXT MENU ──────────────────────
    const avatarMenu        = document.getElementById('ppAvatarMenu');
    const ppLightbox        = document.getElementById('ppLightbox');
    const ppLightboxClose   = document.getElementById('ppLightboxClose');
    const ppLightboxContent = document.getElementById('ppLightboxContent');

    // Toggle menu on avatar click
    if (avatarWrap && avatarMenu) {
        avatarWrap.addEventListener('click', e => {
            e.stopPropagation();
            avatarMenu.classList.toggle('open');
        });
    }

    // Close menu on outside click
    document.addEventListener('click', () => avatarMenu?.classList.remove('open'));

    // ── "See profile picture" → lightbox ──
    document.getElementById('ppMenuSee')?.addEventListener('click', e => {
        e.stopPropagation();
        avatarMenu.classList.remove('open');
        const src = document.getElementById('ppAvatarImg')?.src;
        const name = document.getElementById('ppName')?.textContent || 'U';
        ppLightboxContent.innerHTML = src
            ? `<img src="${src}" class="pp-lightbox-img" alt="Profile Photo">`
            : `<div class="pp-lightbox-initials">${name.charAt(0).toUpperCase()}</div>`;
        ppLightbox.classList.add('open');
        document.body.style.overflow = 'hidden';
    });

    // ── "Choose profile picture" → open file picker ──
    document.getElementById('ppMenuChoose')?.addEventListener('click', e => {
        e.stopPropagation();
        avatarMenu.classList.remove('open');
        avatarInput?.click();
    });

    // ── "Remove photo" in menu ──
    document.getElementById('ppMenuRemove')?.addEventListener('click', e => {
        e.stopPropagation();
        avatarMenu.classList.remove('open');
        removeAvatar();
    });

    // File input → open cropper
    avatarInput?.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        if (file.size > 10 * 1024 * 1024) {
            toast('Image must be under 10 MB.', 'error');
            this.value = '';
            return;
        }
        openCropper(file);
    });

    // Close lightbox
    ppLightboxClose?.addEventListener('click', () => {
        ppLightbox.classList.remove('open');
        document.body.style.overflow = '';
    });
    ppLightbox?.addEventListener('click', e => {
        if (e.target === ppLightbox) {
            ppLightbox.classList.remove('open');
            document.body.style.overflow = '';
        }
    });

    // ── REMOVE AVATAR ──────────────────────────────────────────
    async function removeAvatar() {
        if (!confirm('Remove your profile photo?')) return;

        const fd = new FormData();
        fd.append('action', 'remove_avatar');

        try {
            const res  = await fetch('profile_update.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                const initial = (document.getElementById('ppName')?.textContent || 'U').charAt(0).toUpperCase();

                // Panel: replace img with initials div
                const panelImg = document.getElementById('ppAvatarImg');
                if (panelImg) {
                    const div = document.createElement('div');
                    div.className   = 'pp-avatar-large';
                    div.id          = 'ppAvatarLarge';
                    div.textContent = initial;
                    panelImg.replaceWith(div);
                }

                // Navbar: replace img with initials div
                const navImg = document.getElementById('navAvatarPhoto');
                if (navImg) {
                    const div = document.createElement('div');
                    div.className   = 'avatar-initials';
                    div.id          = 'navAvatarInitials';
                    div.textContent = initial;
                    navImg.replaceWith(div);
                }

                // Remove "See" and "Remove" from menu (no photo anymore)
                document.getElementById('ppMenuSee')?.remove();
                document.getElementById('ppMenuRemove')?.remove();

                toast('Profile photo removed.');
            } else {
                toast(data.message || 'Could not remove photo.', 'error');
            }
        } catch {
            toast('Network error.', 'error');
        }
    }

    // ── FLOATING UNIT SCROLL BUTTONS (mobile only) ──────────────
    (function() {
        const floatUp      = document.getElementById('unitFloatUp');
        const floatDown    = document.getElementById('unitFloatDown');
        const floatCounter = document.getElementById('unitFloatCounter');
        if (!floatUp || !floatDown) return;

        let currentIndex = 0;

        function getVisibleCards() {
            return Array.from(document.querySelectorAll('#unitGrid .v-card'))
                .filter(c => c.style.display !== 'none');
        }

        function updateButtons(cards) {
            floatUp.disabled   = currentIndex === 0;
            floatDown.disabled = currentIndex === cards.length - 1;
            if (floatCounter) {
                floatCounter.textContent = (currentIndex + 1) + ' / ' + cards.length;
            }
        }

        function scrollToIndex(cards) {
            if (!cards.length) return;
            const card  = cards[currentIndex];
            const navH  = document.querySelector('.navbar')?.offsetHeight || 56;
            const top   = card.getBoundingClientRect().top + window.scrollY - navH - 12;
            window.scrollTo({ top, behavior: 'smooth' });
            updateButtons(cards);
        }

        floatDown.addEventListener('click', () => {
            const cards = getVisibleCards();
            if (currentIndex < cards.length - 1) {
                currentIndex++;
                scrollToIndex(cards);
            }
        });

        floatUp.addEventListener('click', () => {
            const cards = getVisibleCards();
            if (currentIndex > 0) {
                currentIndex--;
                scrollToIndex(cards);
            }
        });

        // Sync counter when user scrolls manually
        window.addEventListener('scroll', () => {
            const cards = getVisibleCards();
            if (!cards.length) return;
            const navH = document.querySelector('.navbar')?.offsetHeight || 56;
            let closest = 0, minDist = Infinity;
            cards.forEach((card, i) => {
                const dist = Math.abs(card.getBoundingClientRect().top - navH - 12);
                if (dist < minDist) { minDist = dist; closest = i; }
            });
            if (closest !== currentIndex) {
                currentIndex = closest;
                updateButtons(cards);
            }
        }, { passive: true });

        // Reset when filter chip changes
        document.querySelectorAll('.filter-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                currentIndex = 0;
                setTimeout(() => updateButtons(getVisibleCards()), 80);
            });
        });

        // Init
        updateButtons(getVisibleCards());
    })();

})();