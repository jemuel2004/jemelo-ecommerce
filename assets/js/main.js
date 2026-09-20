/* ============================================================
   Jemelo — Customer JS
   ============================================================ */

(function () {
  'use strict';

  // ── Page transitions (skeleton) ─────────────────────────
  document.documentElement.classList.add('js-page-loading');
  window.addEventListener('DOMContentLoaded', function () {
    requestAnimationFrame(function () {
      document.documentElement.classList.remove('js-page-loading');
      document.documentElement.classList.add('js-page-loaded');
    });
  });

  // bfcache restore: browser Back/Forward from cached page keeps whatever
  // CSS classes were on the document when we navigated away (pts-show, js-page-exit).
  // DOMContentLoaded never fires on bfcache restore — only pageshow with persisted=true.
  window.addEventListener('pageshow', function (e) {
    if (!e.persisted) return;
    document.documentElement.classList.remove('js-page-exit', 'js-page-loading');
    document.documentElement.classList.add('js-page-loaded');
    var overlay = ptsOverlay || document.getElementById('pts-overlay');
    if (overlay) overlay.classList.remove('pts-show');
  });

  // Build customer skeleton overlay (injected once into DOM)
  function buildCustomerSkeleton() {
    const el = document.createElement('div');
    el.id = 'pts-overlay';
    function s(w, h, extra) {
      return '<div class="pts-s" style="width:' + w + ';height:' + h + (extra ? ';' + extra : '') + '"></div>';
    }
    el.innerHTML =
      '<div class="pts-bar"></div>' +
      // Navbar
      '<div class="pts-nav">' +
        s('32px','32px','border-radius:50%;flex-shrink:0') +
        s('120px','26px') +
        '<div style="flex:1"></div>' +
        s('240px','36px','border-radius:20px') +
        '<div style="flex:1"></div>' +
        s('32px','32px','border-radius:50%') +
        s('32px','32px','border-radius:50%') +
        s('80px','34px','border-radius:8px') +
      '</div>' +
      // Category bar
      '<div class="pts-catbar">' +
        s('50px','18px') + s('80px','18px') + s('70px','18px') +
        s('90px','18px') + s('65px','18px') + s('75px','18px') +
      '</div>' +
      // Body
      '<div style="flex:1;overflow:hidden;padding:24px;box-sizing:border-box">' +
        '<div style="max-width:1260px;margin:0 auto">' +
          s('100%','200px','border-radius:16px;margin-bottom:24px') +
          '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">' +
            s('160px','22px') + s('120px','32px','border-radius:8px') +
          '</div>' +
          '<div class="pts-grid">' +
            Array(8).fill(
              '<div class="pts-card">' +
                '<div class="pts-card-img pts-s"></div>' +
                '<div class="pts-card-body">' +
                  s('90%','14px') + s('60%','12px') + s('50%','20px') +
                '</div>' +
              '</div>'
            ).join('') +
          '</div>' +
        '</div>' +
      '</div>';
    document.body.appendChild(el);
    return el;
  }

  let ptsOverlay = null;
  function showSkeleton(href) {
    if (!ptsOverlay) ptsOverlay = buildCustomerSkeleton();
    // Reset progress bar animation
    const bar = ptsOverlay.querySelector('.pts-bar');
    if (bar) { bar.style.animation = 'none'; void bar.offsetWidth; bar.style.animation = ''; }
    ptsOverlay.classList.add('pts-show');
    document.documentElement.classList.add('js-page-exit');
    setTimeout(function () { window.location.href = href; }, 240);
  }

  document.addEventListener('click', function (e) {
    const link = e.target.closest('a');
    if (!link) return;
    const href = link.getAttribute('href');
    if (!href || href === '#' || href.startsWith('#') ||
        href.startsWith('javascript') || link.target === '_blank' ||
        e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
    try {
      const url = new URL(href, window.location.href);
      if (url.hostname !== window.location.hostname) return;
    } catch (_) { return; }
    if (link.hasAttribute('download')) return;
    e.preventDefault();
    showSkeleton(href);
  });

  // ── Toast notifications ─────────────────────────────────
  window.showToast = function (msg, type) {
    type = type || 'info';
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle', warning: 'fa-exclamation-triangle' };
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = '<i class="fas ' + (icons[type] || icons.info) + '"></i> ' + msg;
    container.appendChild(toast);
    // Trigger enter animation
    requestAnimationFrame(function () { toast.classList.add('toast-visible'); });
    setTimeout(function () {
      toast.classList.remove('toast-visible');
      setTimeout(function () { toast.remove(); }, 350);
    }, 3200);
  };

  // ── Dropdown toggle ──────────────────────────────────────
  document.addEventListener('click', function (e) {
    const toggle = e.target.closest('[data-dropdown-toggle]');
    if (toggle) {
      const target = document.getElementById(toggle.dataset.dropdownToggle);
      if (target) {
        const isOpen = target.classList.contains('show');
        // Close all others first
        document.querySelectorAll('.dropdown-menu.show').forEach(function (m) { m.classList.remove('show'); });
        if (!isOpen) target.classList.add('show');
        e.stopPropagation();
        return;
      }
    }
    document.querySelectorAll('.dropdown-menu.show').forEach(function (m) { m.classList.remove('show'); });
  });

  // ── Mobile search toggle ─────────────────────────────────
  const searchToggle = document.getElementById('search-toggle');
  const navSearch    = document.querySelector('.navbar-search');
  if (searchToggle && navSearch) {
    searchToggle.addEventListener('click', function () {
      navSearch.classList.toggle('open');
      if (navSearch.classList.contains('open')) {
        const inp = navSearch.querySelector('input');
        if (inp) inp.focus();
      }
    });
  }

  // ── Quantity controls ────────────────────────────────────
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.qty-btn');
    if (!btn) return;
    const input = btn.closest('.qty-group') && btn.closest('.qty-group').querySelector('input[type=number]');
    if (!input) return;
    const min = parseInt(input.min) || 1;
    const max = parseInt(input.max) || 999;
    let val = parseInt(input.value) || 1;
    if (btn.dataset.action === 'inc') val = Math.min(val + 1, max);
    if (btn.dataset.action === 'dec') val = Math.max(val - 1, min);
    input.value = val;
    input.dispatchEvent(new Event('change'));
  });

  // ── Cart operations ──────────────────────────────────────
  async function cartRequest(action, productId, quantity) {
    quantity = quantity || 1;
    const fd = new FormData();
    fd.append('action', action);
    fd.append('product_id', productId);
    fd.append('quantity', quantity);
    const csrf = document.querySelector('meta[name=csrf-token]');
    if (csrf) fd.append('csrf_token', csrf.content);

    try {
      const res  = await fetch(window.SITE_URL + '/ajax/cart.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        updateCartBadge(data.cart_count);
        showToast(data.message, 'success');
      } else {
        showToast(data.message || 'Something went wrong.', 'error');
      }
      return data;
    } catch (_) {
      showToast('Network error. Please try again.', 'error');
    }
  }

  function updateCartBadge(count) {
    document.querySelectorAll('.cart-badge').forEach(function (el) {
      el.textContent = count;
      el.style.display = count > 0 ? '' : 'none';
    });
  }

  document.addEventListener('click', function (e) {
    // Add to cart
    const addBtn = e.target.closest('[data-add-cart]');
    if (addBtn) {
      e.preventDefault();
      const pid      = addBtn.dataset.addCart;
      const qtyInput = document.getElementById('qty-' + pid) || document.querySelector('#product-qty');
      const qty      = qtyInput ? parseInt(qtyInput.value) : 1;
      addBtn.disabled = true;
      addBtn.classList.add('btn-loading');
      cartRequest('add', pid, qty).finally(function () {
        addBtn.disabled = false;
        addBtn.classList.remove('btn-loading');
      });
      return;
    }

    // Remove from cart
    const removeBtn = e.target.closest('[data-remove-cart]');
    if (removeBtn) {
      e.preventDefault();
      const pid = removeBtn.dataset.removeCart;
      const row = removeBtn.closest('.cart-item');
      cartRequest('remove', pid).then(function (data) {
        if (data && data.success && row) {
          row.style.transition = 'opacity .3s ease, transform .3s ease';
          row.style.opacity    = '0';
          row.style.transform  = 'translateX(-12px)';
          setTimeout(function () {
            row.style.overflow = 'hidden';
            row.style.maxHeight = row.offsetHeight + 'px';
            row.style.transition = 'max-height .3s ease, padding .3s ease, margin .3s ease';
            requestAnimationFrame(function () {
              row.style.maxHeight = '0';
              row.style.padding   = '0';
              row.style.margin    = '0';
            });
            setTimeout(function () { row.remove(); updateCartTotals(); }, 310);
          }, 300);
        }
      });
    }
  });

  // Cart quantity change — debounced
  let qtyTimer;
  document.addEventListener('change', function (e) {
    if (!e.target.matches('.cart-qty-input')) return;
    clearTimeout(qtyTimer);
    qtyTimer = setTimeout(function () {
      const pid = e.target.dataset.productId;
      const qty = parseInt(e.target.value) || 1;
      cartRequest('update', pid, qty).then(function (data) {
        if (data && data.success) {
          const subtotal = document.querySelector('[data-subtotal="' + pid + '"]');
          if (subtotal && data.item_subtotal) subtotal.textContent = data.item_subtotal;
          updateCartTotals();
        }
      });
    }, 500);
  });

  function updateCartTotals() {
    let total = 0;
    document.querySelectorAll('[data-item-subtotal]').forEach(function (el) {
      total += parseFloat(el.dataset.itemSubtotal || 0);
    });
    const totalEl = document.getElementById('cart-total');
    if (totalEl) totalEl.textContent = '₱' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  // ── Product image gallery ────────────────────────────────
  const mainImg = document.getElementById('main-product-img');
  document.querySelectorAll('.thumb-img').forEach(function (thumb) {
    thumb.addEventListener('click', function () {
      if (mainImg) {
        mainImg.style.opacity = '0';
        mainImg.style.transform = 'scale(.97)';
        setTimeout(function () {
          mainImg.src = thumb.src;
          mainImg.style.transition = 'opacity .25s ease, transform .25s ease';
          mainImg.style.opacity = '1';
          mainImg.style.transform = 'scale(1)';
        }, 150);
      }
      document.querySelectorAll('.thumb-img').forEach(function (t) { t.classList.remove('active'); });
      thumb.classList.add('active');
    });
  });

  // ── Search form ──────────────────────────────────────────
  document.querySelectorAll('.search-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      const input = this.querySelector('input[name=q]');
      if (input && !input.value.trim()) e.preventDefault();
    });
  });

  // ── Confirm dialogs ──────────────────────────────────────
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-confirm]');
    if (btn && !confirm(btn.dataset.confirm)) e.preventDefault();
  });

  // ── Filter auto-submit ───────────────────────────────────
  document.querySelectorAll('.auto-submit select, .auto-submit input[type=checkbox]').forEach(function (el) {
    el.addEventListener('change', function () {
      this.closest('form') && this.closest('form').submit();
    });
  });

  // ── Smooth scroll for anchor links ───────────────────────
  document.addEventListener('click', function (e) {
    const link = e.target.closest('a[href^="#"]');
    if (!link) return;
    const id = link.getAttribute('href');
    if (id === '#') return;
    const target = document.querySelector(id);
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });

  // ── Intersection Observer — scroll animations ────────────
  if ('IntersectionObserver' in window) {
    // Lazy images
    const imgIO = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          const img = entry.target;
          if (img.dataset.src) { img.src = img.dataset.src; delete img.dataset.src; }
          imgIO.unobserve(img);
        }
      });
    }, { rootMargin: '200px' });
    document.querySelectorAll('img[data-src]').forEach(function (img) { imgIO.observe(img); });

    // Scroll-reveal for cards and sections
    const revealIO = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('revealed');
          revealIO.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08 });
    document.querySelectorAll('.product-card, .section-header, .stat-card, .admin-card').forEach(function (el) {
      el.classList.add('reveal-on-scroll');
      revealIO.observe(el);
    });
  }

  // ── Input focus effects ──────────────────────────────────
  document.querySelectorAll('.form-control').forEach(function (inp) {
    const wrapper = inp.parentElement;
    inp.addEventListener('focus',  function () { wrapper.classList.add('input-focused'); });
    inp.addEventListener('blur',   function () { wrapper.classList.remove('input-focused'); });
  });

})();
