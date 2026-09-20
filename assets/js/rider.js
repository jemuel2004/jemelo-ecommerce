/* ============================================================
   Jemelo — Rider Portal JS
   ============================================================ */
(function () {
  'use strict';

  // ── Sidebar toggle (mobile) ──────────────────────────────
  const sidebar = document.getElementById('rider-sidebar');
  const overlay = document.getElementById('rider-sidebar-overlay');
  const menuBtn = document.getElementById('rider-menu-btn');

  function openSidebar()  { sidebar?.classList.add('open'); overlay?.classList.add('open'); document.body.style.overflow = 'hidden'; }
  function closeSidebar() { sidebar?.classList.remove('open'); overlay?.classList.remove('open'); document.body.style.overflow = ''; }

  menuBtn?.addEventListener('click', openSidebar);
  overlay?.addEventListener('click', closeSidebar);

  // ── Dropdown toggle ──────────────────────────────────────
  document.addEventListener('click', function (e) {
    const toggle = e.target.closest('[data-dropdown]');
    if (toggle) {
      const id = toggle.dataset.dropdown;
      const menu = document.getElementById(id);
      if (menu) {
        document.querySelectorAll('.dropdown-menu.show').forEach(m => { if (m !== menu) m.classList.remove('show'); });
        menu.classList.toggle('show');
        e.stopPropagation();
        return;
      }
    }
    document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
  });

  // ── Toast notifications ──────────────────────────────────
  window.riderToast = function (msg, type) {
    type = type || 'info';
    let wrap = document.querySelector('.rider-toast-wrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'rider-toast-wrap';
      document.body.appendChild(wrap);
    }
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle' };
    const t = document.createElement('div');
    t.className = 'rider-toast ' + type;
    t.innerHTML = '<i class="fas ' + (icons[type] || 'fa-bell') + '"></i> ' + msg;
    wrap.appendChild(t);
    requestAnimationFrame(() => { requestAnimationFrame(() => t.classList.add('in')); });
    setTimeout(() => {
      t.classList.remove('in');
      setTimeout(() => t.remove(), 350);
    }, 3500);
  };

  // ── Confirm dialogs ──────────────────────────────────────
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-confirm]');
    if (btn && !confirm(btn.dataset.confirm)) e.preventDefault();
  });

  // ── Topbar clock ─────────────────────────────────────────
  const clock = document.getElementById('rider-clock');
  function updateClock() {
    if (!clock) return;
    const now = new Date();
    clock.textContent = now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
  }
  updateClock();
  setInterval(updateClock, 30000);

  // ── Notification bell ────────────────────────────────────
  const bell      = document.getElementById('rider-notif-bell');
  const notifDrop = document.getElementById('rider-notif-drop');
  const notifList = document.getElementById('rider-notif-list');
  const notifBadge= document.getElementById('rider-notif-badge');
  const markAllBtn= document.getElementById('rider-mark-all');
  let notifLoaded = false;

  if (bell && notifDrop) {
    bell.addEventListener('click', function (e) {
      e.stopPropagation();
      const isOpen = notifDrop.classList.toggle('open');
      if (isOpen && !notifLoaded) { fetchNotifs(); notifLoaded = true; }
    });

    document.addEventListener('click', function (e) {
      if (!notifDrop.contains(e.target) && e.target !== bell) notifDrop.classList.remove('open');
    });
  }

  function updateNotifBadge(n) {
    if (!notifBadge) return;
    if (n > 0) { notifBadge.textContent = n > 99 ? '99+' : n; notifBadge.style.display = ''; }
    else         { notifBadge.style.display = 'none'; }
  }

  function fetchNotifs() {
    if (!notifList) return;
    fetch(window.SITE_URL + '/ajax/notifications.php?action=fetch')
      .then(r => r.json()).then(data => {
        notifList.innerHTML = data.html;
        updateNotifBadge(data.unread);
        notifList.querySelectorAll('.notif-item[data-id]').forEach(item => {
          item.addEventListener('click', function () {
            fetch(window.SITE_URL + '/ajax/notifications.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: 'action=mark_read&id=' + this.dataset.id
            }).then(r => r.json()).then(d => {
              this.classList.remove('notif-item--unread');
              this.querySelector('.notif-dot')?.remove();
              updateNotifBadge(d.unread);
              if (this.dataset.link) window.location.href = this.dataset.link;
            });
          });
        });
      }).catch(() => { if (notifList) notifList.innerHTML = '<div class="notif-empty">Failed to load.</div>'; });
  }

  if (markAllBtn) {
    markAllBtn.addEventListener('click', function () {
      fetch(window.SITE_URL + '/ajax/notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_all_read'
      }).then(r => r.json()).then(() => { updateNotifBadge(0); notifLoaded = false; });
    });
  }

  // Poll for new notifications every 20s
  setInterval(function () {
    fetch(window.SITE_URL + '/ajax/notifications.php?action=poll')
      .then(r => r.json()).then(d => {
        updateNotifBadge(d.unread);
        if (d.unread > 0 && notifLoaded) notifLoaded = false;
      });
  }, 20000);

  // ── Delivery status update ───────────────────────────────
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-update-delivery]');
    if (!btn) return;
    e.preventDefault();
    if (btn.dataset.confirm && !confirm(btn.dataset.confirm)) return;

    const orderId    = btn.dataset.updateDelivery;
    const newStatus  = btn.dataset.status;
    const notes      = btn.dataset.notes || '';
    const csrf       = document.querySelector('meta[name=csrf-token]')?.content || '';

    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch(window.SITE_URL + '/ajax/rider.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `action=update_status&order_id=${orderId}&status=${newStatus}&notes=${encodeURIComponent(notes)}&csrf_token=${csrf}`
    })
    .then(r => r.json()).then(data => {
      if (data.success) {
        riderToast(data.message || 'Status updated!', 'success');
        setTimeout(() => window.location.reload(), 800);
      } else {
        riderToast(data.message || 'Error updating status.', 'error');
        btn.disabled = false;
        btn.innerHTML = orig;
      }
    }).catch(() => { riderToast('Network error.', 'error'); btn.disabled = false; btn.innerHTML = orig; });
  });

  // ── Poll for new deliveries (dashboard only) ─────────────
  const deliveryBadge = document.getElementById('new-delivery-badge');
  if (deliveryBadge) {
    let lastCount = parseInt(deliveryBadge.dataset.count || '0');
    setInterval(function () {
      fetch(window.SITE_URL + '/ajax/rider.php?action=poll_new')
        .then(r => r.json()).then(d => {
          if (d.count > lastCount) {
            riderToast('New delivery assigned to you!', 'info');
            lastCount = d.count;
            deliveryBadge.textContent = d.count;
            deliveryBadge.style.display = d.count > 0 ? '' : 'none';
          }
        });
    }, 15000);
  }

  // ── Image preview on file select ─────────────────────────
  document.querySelectorAll('input[type=file][data-preview]').forEach(inp => {
    inp.addEventListener('change', function () {
      const prev = document.getElementById(this.dataset.preview);
      if (prev && this.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { prev.src = e.target.result; };
        reader.readAsDataURL(this.files[0]);
      }
    });
  });

})();
