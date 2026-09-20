/* ============================================================
   Jemelo — Admin Panel JS
   ============================================================ */

(function() {
  'use strict';

  // ── Sidebar toggle (mobile) ──────────────────────────────
  const sidebar    = document.getElementById('sidebar');
  const overlay    = document.getElementById('sidebar-overlay');
  const menuBtn    = document.getElementById('topbar-menu-btn');

  function openSidebar() {
    sidebar?.classList.add('open');
    overlay?.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('open');
    document.body.style.overflow = '';
  }

  menuBtn?.addEventListener('click', openSidebar);
  overlay?.addEventListener('click', closeSidebar);

  // ── Modal helpers ────────────────────────────────────────
  window.openModal = function(id) {
    const m = document.getElementById(id);
    if (!m) return;
    // Move to <body> so position:fixed is always relative to the viewport,
    // never to a parent with will-change/transform that creates a containing block.
    if (m.parentNode !== document.body) document.body.appendChild(m);
    m.classList.add('open');
    document.body.style.overflow = 'hidden';
  };
  window.closeModal = function(id) {
    const m = id ? document.getElementById(id) : document.querySelector('.modal-overlay.open');
    if (m) { m.classList.remove('open'); document.body.style.overflow = ''; }
  };

  document.addEventListener('click', function(e) {
    if (e.target.matches('.modal-overlay')) closeModal();
    if (e.target.matches('.modal-close') || e.target.closest('.modal-close')) {
      closeModal(e.target.closest('.modal-overlay')?.id);
    }
  });

  // ── Confirm dialogs ──────────────────────────────────────
  document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-confirm]');
    if (btn && !confirm(btn.dataset.confirm)) e.preventDefault();
  });

  // ── Image preview on file select ─────────────────────────
  document.addEventListener('change', function(e) {
    if (e.target.matches('input[type=file][data-preview]')) {
      const preview     = document.getElementById(e.target.dataset.preview);
      const placeholder = e.target.dataset.placeholder ? document.getElementById(e.target.dataset.placeholder) : null;
      const file        = e.target.files[0];
      if (preview && file) {
        const reader = new FileReader();
        reader.onload = ev => {
          preview.src           = ev.target.result;
          preview.style.display = 'block';
          if (placeholder) placeholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
      }
    }
  });

  // ── Toast ────────────────────────────────────────────────
  window.showToast = function(msg, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="fas ${icons[type] || icons.info}"></i> ${msg}`;
    container.appendChild(toast);
    setTimeout(() => {
      toast.style.transition = '.3s';
      toast.style.opacity    = '0';
      setTimeout(() => toast.remove(), 300);
    }, 3500);
  };

  // ── AJAX order status update ─────────────────────────────
  document.addEventListener('change', function(e) {
    if (e.target.matches('.order-status-select')) {
      const orderId = e.target.dataset.orderId;
      const status  = e.target.value;
      const csrf    = document.querySelector('meta[name=csrf-token]')?.content;

      const fd = new FormData();
      fd.append('order_id', orderId);
      fd.append('status', status);
      if (csrf) fd.append('csrf_token', csrf);

      fetch(window.SITE_URL + '/ajax/update_order.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => showToast(data.message, data.success ? 'success' : 'error'))
        .catch(() => showToast('Network error.', 'error'));
    }
  });

  // ── Filter / search auto-submit ──────────────────────────
  document.querySelectorAll('.admin-filter select').forEach(sel => {
    sel.addEventListener('change', () => sel.closest('form')?.submit());
  });

  // ── DataTable-like: client-side search ───────────────────
  const tableSearch = document.getElementById('table-search');
  const tableBody   = document.getElementById('table-body');
  if (tableSearch && tableBody) {
    tableSearch.addEventListener('input', function() {
      const q    = this.value.toLowerCase().trim();
      const rows = tableBody.querySelectorAll('tr');
      rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  }

  // ── Topbar live clock ─────────────────────────────────────
  const clockEl = document.getElementById('topbar-clock');
  if (clockEl) {
    function updateClock() {
      clockEl.textContent = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    updateClock();
    setInterval(updateClock, 60000);
  }

  // ── Select all checkbox ───────────────────────────────────
  const selectAll = document.getElementById('select-all');
  if (selectAll) {
    selectAll.addEventListener('change', function() {
      document.querySelectorAll('.row-check').forEach(cb => { cb.checked = this.checked; });
    });
  }

  // ── Tabs ──────────────────────────────────────────────────
  document.addEventListener('click', function(e) {
    const tab = e.target.closest('[data-tab]');
    if (!tab) return;
    const group  = tab.closest('[data-tab-group]');
    if (!group) return;
    const target = document.getElementById(tab.dataset.tab);
    if (!target) return;
    group.querySelectorAll('[data-tab]').forEach(t => t.classList.remove('active'));
    group.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    tab.classList.add('active');
    target.classList.add('active');
  });

  // ── Admin Notification Bell ───────────────────────────────
  (function() {
    const bell      = document.getElementById('notif-bell-admin');
    const dropdown  = document.getElementById('notif-dropdown-admin');
    const list      = document.getElementById('notif-list-admin');
    const badge     = document.getElementById('notif-badge-admin');
    const markAllBtn= document.getElementById('notif-mark-all-admin');
    if (!bell) return;

    let loaded = false;

    function updateBadge(count) {
      if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = '';
      } else {
        badge.style.display = 'none';
      }
    }

    bell.addEventListener('click', function(e) {
      e.stopPropagation();
      const isOpen = dropdown.classList.toggle('open');
      if (isOpen && !loaded) { fetchNotifs(); loaded = true; }
    });

    document.addEventListener('click', function(e) {
      if (!dropdown.contains(e.target) && e.target !== bell) {
        dropdown.classList.remove('open');
      }
    });

    function fetchNotifs() {
      fetch(window.SITE_URL + '/ajax/notifications.php?action=fetch')
        .then(r => r.json())
        .then(data => {
          list.innerHTML = data.html;
          updateBadge(data.unread);
          list.querySelectorAll('.notif-item[data-id]').forEach(item => {
            item.addEventListener('click', function() {
              const id = this.dataset.id;
              fetch(window.SITE_URL + '/ajax/notifications.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: `action=mark_read&id=${id}`
              }).then(r => r.json()).then(d => {
                this.classList.remove('notif-item--unread');
                this.querySelector('.notif-dot')?.remove();
                updateBadge(d.unread);
              });
            });
          });
        }).catch(() => {
          list.innerHTML = '<div class="notif-empty"><i class="fas fa-exclamation-circle"></i><p>Failed to load</p></div>';
        });
    }

    markAllBtn?.addEventListener('click', function() {
      fetch(window.SITE_URL + '/ajax/notifications.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=mark_all_read'
      }).then(r => r.json()).then(() => {
        updateBadge(0);
        loaded = false;
        if (dropdown.classList.contains('open')) fetchNotifs();
      });
    });

      // Poll every 30s
    setInterval(function() {
      fetch(window.SITE_URL + '/ajax/notifications.php?action=poll')
        .then(r => r.json())
        .then(d => {
          updateBadge(d.unread);
          if (d.unread > 0 && loaded) loaded = false;
        });
    }, 30000);
  })();

  // ── Admin Content Transition System ─────────────────────
  // .admin-content has a CSS enter animation that plays automatically on load.
  // On navigation: only .admin-content exits (fade + slide down).
  // Sidebar and topbar are never touched — no white flash, no layout shift.

  var html = document.documentElement;

  // Progress bar (fixed, below topbar, above content) — injected once
  var navBar = document.createElement('div');
  navBar.id = 'admin-nav-bar';
  document.body.appendChild(navBar);

  // Content skeleton — injected inside .admin-main, shown during exit via CSS
  (function buildSkeleton() {
    var sk = document.createElement('div');
    sk.id  = 'admin-sk';
    function s(w, h, r) {
      return '<div class="adm-sk-s" style="width:' + w + ';height:' + h +
             (r ? ';border-radius:' + r : '') + '"></div>';
    }
    var statCards = Array(4).fill(
      '<div class="adm-sk-stat">' +
        s('40%','11px') + s('60%','26px') +
      '</div>'
    ).join('');
    var tableRows = Array(6).fill(
      '<div class="adm-sk-row">' +
        s('30px','30px','50%') +
        s('20%','12px') + s('25%','12px') + s('12%','12px') +
        s('12%','22px','20px') + s('8%','12px') +
      '</div>'
    ).join('');

    sk.innerHTML =
      s('160px','22px') +
      '<div class="adm-sk-stats">' + statCards + '</div>' +
      '<div class="adm-sk-table">' +
        '<div class="adm-sk-row-header">' +
          s('140px','18px') + s('96px','32px','8px') +
        '</div>' +
        tableRows +
      '</div>';

    var main = document.querySelector('.admin-main');
    if (main) main.appendChild(sk);
  }());

  // Clean up exit state when page is restored from bfcache (back/forward navigation)
  window.addEventListener('pageshow', function(e) {
    if (e.persisted) {
      html.classList.remove('admin-page-exit');
      exiting = false;
    }
  });

  // Navigate: trigger exit animation, show skeleton + progress bar, then redirect
  var exiting = false;
  function adminNavigate(href) {
    if (exiting) return;
    exiting = true;

    // Restart navBar CSS animation cleanly
    navBar.style.animation = 'none';
    void navBar.offsetWidth;   // force reflow
    navBar.style.animation = '';

    html.classList.add('admin-page-exit');

    setTimeout(function() {
      window.location.href = href;
    }, 260);
  }

  // 5. Intercept same-origin link clicks
  document.addEventListener('click', function(e) {
    if (e.defaultPrevented) return;
    // Don't intercept if a modal is open
    if (document.querySelector('.modal-overlay.open')) return;

    var link = e.target.closest('a');
    if (!link) return;
    var href = link.getAttribute('href');
    if (!href || href === '#' || href.startsWith('#') ||
        href.startsWith('javascript') || link.target === '_blank' ||
        e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
    try {
      var url = new URL(href, window.location.href);
      if (url.hostname !== window.location.hostname) return;
    } catch(_) { return; }
    if (link.hasAttribute('download')) return;

    e.preventDefault();
    adminNavigate(href);
  });

  // 6. Intercept GET filter form submissions (search/filter bars)
  //    POST forms are left alone — they handle their own redirects
  document.addEventListener('submit', function(e) {
    var form = e.target;
    if (!form || (form.method || '').toLowerCase() === 'post') return;
    if (e.defaultPrevented) return;

    e.preventDefault();
    var base   = (form.action || window.location.href).split('?')[0];
    var params = new URLSearchParams(new FormData(form)).toString();
    adminNavigate(base + (params ? '?' + params : ''));
  });

})();
