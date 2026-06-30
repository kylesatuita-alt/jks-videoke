// =============================================
//  JKS VIDEOKE — Main JS
// =============================================

document.addEventListener('DOMContentLoaded', () => {

    // ── Capitalize first letter of each word for Name field ──
    const nameInput = document.getElementById('name');
    if (nameInput) {
        nameInput.addEventListener('input', function () {
            const pos = this.selectionStart;
            this.value = this.value
                .split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
            this.setSelectionRange(pos, pos);
        });
    }

    // ── Phone number: allow digits, +, -, spaces only ──
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9+\-\s]/g, '');
        });
    }

    // ── Auto-hide alerts after 4s ──
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 4000);
    });

    // ── Password show/toggle (optional future use) ──

});
