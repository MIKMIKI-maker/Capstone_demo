(function () {
  'use strict';

  function getBadge() {
    return document.querySelector('.admin-notif-badge');
  }

  // "Liquid glass" look for the notif bell — frosted/translucent circle with
  // a glossy top-left highlight (the ::before radial-gradient) instead of a
  // flat white disc, and a drop-shadow on the icon itself so the bell shape
  // reads clearly against the blur instead of flattening into the page.
  // Appended after every page's own linked CSS, so this same-specificity
  // selector wins the cascade and overrides the (now-legacy) per-page
  // .admin-notif-btn rules without having to touch every CSS file.
  function applyNotificationStyle() {
    if (document.getElementById('admin-notification-style')) return;
    var style = document.createElement('style');
    style.id = 'admin-notification-style';
    style.textContent = '.admin-notif-btn{position:relative;width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#1e3a8a;font-size:18px;text-decoration:none;background:linear-gradient(135deg,rgba(255,255,255,.8),rgba(255,255,255,.4));-webkit-backdrop-filter:blur(14px) saturate(180%);backdrop-filter:blur(14px) saturate(180%);border:1px solid rgba(255,255,255,.7);box-shadow:0 12px 28px rgba(30,58,138,.32),0 3px 8px rgba(30,58,138,.22),inset 0 1px 0 rgba(255,255,255,.9),inset 0 -6px 10px -6px rgba(30,58,138,.14);transition:transform .25s cubic-bezier(.34,1.56,.64,1),box-shadow .25s ease}' +
      '.admin-notif-btn::before{content:"";position:absolute;inset:0;border-radius:50%;background:radial-gradient(circle at 30% 22%,rgba(255,255,255,.95),rgba(255,255,255,0) 55%);pointer-events:none}' +
      '.admin-notif-btn i{position:relative;z-index:1;filter:drop-shadow(0 2px 3px rgba(30,58,138,.6))}' +
      '.admin-notif-btn:hover{transform:translateY(-2px) scale(1.06);box-shadow:0 16px 34px rgba(30,58,138,.42),0 4px 10px rgba(30,58,138,.28),inset 0 1px 0 rgba(255,255,255,.95),inset 0 -6px 10px -6px rgba(30,58,138,.18)}' +
      '.admin-notif-badge{position:absolute;top:-4px;right:-4px;min-width:21px;height:21px;padding:0 4px;border:2px solid #fff;border-radius:999px;background:#ef4444;color:#fff;font:800 11.5px/1 Inter,Arial,sans-serif;text-align:center;align-items:center;justify-content:center;z-index:2;box-shadow:0 2px 6px rgba(0,0,0,.25)}';
    document.head.appendChild(style);
  }

  // Shared floating success/failure toast for every admin page — replaces
  // the old copy-pasted showMuToast() (Admin_manage_user.html /
  // Admin_deleted_accounts.html) with one definition everyone calls.
  // Floats center-screen (not a corner toast) so it reads clearly during a
  // live demo; a success message is preceded by the same full-screen
  // loading beat as the page's own initial load, held for at least 2s, so
  // the confirmation never just flashes by unnoticed.
  var TOAST_ICONS = { success: 'fa-circle-check', warning: 'fa-triangle-exclamation', error: 'fa-circle-xmark' };
  var ACTION_LOADING_MS = 2000;

  function applyToastStyle() {
    if (document.getElementById('admin-toast-style')) return;
    var style = document.createElement('style');
    style.id = 'admin-toast-style';
    style.textContent =
      // z-index 100001 — one below .admin-action-loading's 100002, so a new
      // action's loading indicator always wins if it ever has to appear
      // while a previous action's toast is still fading out.
      '.admin-toast{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) scale(.92);display:flex;align-items:center;gap:10px;background:#fff;color:#1e2a3a;padding:16px 26px;border-radius:16px;font-family:"Poppins",sans-serif;font-weight:600;font-size:14.5px;box-shadow:0 20px 50px rgba(0,0,0,.25);border-left:4px solid #16a34a;opacity:0;transition:opacity .25s ease,transform .25s ease;z-index:100001;pointer-events:none;max-width:90vw}' +
      '.admin-toast.show{opacity:1;transform:translate(-50%,-50%) scale(1)}' +
      '.admin-toast--warning{border-left-color:#F59E0B}' +
      '.admin-toast--error{border-left-color:#ef4444}' +
      '.admin-toast i{font-size:18px;color:#16a34a}' +
      '.admin-toast--warning i{color:#F59E0B}' +
      '.admin-toast--error i{color:#ef4444}';
    document.head.appendChild(style);
  }

  // No backdrop at all — the page underneath stays fully visible; just a
  // small floating loading indicator, not the blue full-screen #spedLoading
  // look used when a page first opens (that one stays exactly as-is).
  // Never blocks clicks (pointer-events:none throughout) since there's no
  // visual cue of a blocked page to justify blocking it.
  function applyActionLoadingStyle() {
    if (document.getElementById('admin-action-loading-style')) return;
    var style = document.createElement('style');
    style.id = 'admin-action-loading-style';
    style.textContent =
      '.admin-action-loading{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:100002;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;opacity:0;transition:opacity .3s ease;pointer-events:none}' +
      '.admin-action-loading.show{opacity:1}' +
      '.admin-action-loading img{width:72px;height:72px;animation:adminAlPulse 2s ease-in-out infinite;filter:drop-shadow(0 3px 8px rgba(0,0,0,.25))}' +
      '.admin-action-loading p{color:#1E3A8A;font-family:"Poppins",sans-serif;font-size:14px;font-weight:700;margin:0;text-shadow:0 1px 3px rgba(255,255,255,.9),0 0 10px rgba(255,255,255,.7)}' +
      '.admin-al-dots{display:flex;gap:9px;align-items:center}' +
      '.admin-al-dots span{width:11px;height:11px;border-radius:50%;background:#1E3A8A;display:inline-block;animation:adminAlBounce 1.3s ease-in-out infinite;box-shadow:0 2px 6px rgba(0,0,0,.3)}' +
      '.admin-al-dots span:nth-child(2){animation-delay:.18s}' +
      '.admin-al-dots span:nth-child(3){animation-delay:.36s}' +
      '@keyframes adminAlBounce{0%,80%,100%{transform:translateY(0);opacity:.5}40%{transform:translateY(-14px);opacity:1}}' +
      '@keyframes adminAlPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.06)}}';
    document.head.appendChild(style);
  }

  function getActionLoadingEl() {
    applyActionLoadingStyle();
    var el = document.getElementById('admin-action-loading');
    if (!el) {
      el = document.createElement('div');
      el.id = 'admin-action-loading';
      el.className = 'admin-action-loading';
      el.innerHTML = '<img src="../ASSETS/logo_loading.png" alt="" onerror="this.style.display=\'none\'">' +
        '<div class="admin-al-dots"><span></span><span></span><span></span></div>' +
        '<p>Please wait…</p>';
      document.body.appendChild(el);
    }
    return el;
  }

  // Call this the moment an action starts (right before the fetch), so the
  // loading screen covers the actual network wait instead of only appearing
  // after the response comes back — without it, the page looks frozen for
  // however long the request takes, then the loading screen pops in late.
  function showActionLoading() {
    // A toast from a PREVIOUS action can still be fading out (it stays up
    // to ~3.2s) when a new action starts — e.g. archiving one row while the
    // "Account(s) moved..." toast from the last archive is still on screen.
    // That old toast is now stale, so clear it immediately instead of
    // letting it linger on top of (or behind) the new loading indicator.
    var oldToast = document.querySelector('.admin-toast');
    if (oldToast) oldToast.remove();

    var el = getActionLoadingEl();
    if (!el.classList.contains('show')) {
      el._startedAt = Date.now();
      requestAnimationFrame(function () { el.classList.add('show'); });
    }
    return el;
  }
  window.showActionLoading = showActionLoading;

  // Lighter tier for snappy, no-server-round-trip actions (pagination,
  // switching a tab/filter) — same floating indicator, but only a 1s
  // minimum (not 2s) and never followed by a success toast. The point is
  // just to confirm the click registered and something happened; a toast
  // on every page-flip or tab-switch would be noise, but total silence
  // (Activity Library's page buttons, before this) looks broken.
  var QUICK_LOADING_MS = 1000;

  function showQuickLoading() {
    var oldToast = document.querySelector('.admin-toast');
    if (oldToast) oldToast.remove();
    var el = getActionLoadingEl();
    if (!el.classList.contains('show')) {
      el._startedAt = Date.now();
      requestAnimationFrame(function () { el.classList.add('show'); });
    }
    return el;
  }
  window.showQuickLoading = showQuickLoading;

  // Call after the (synchronous or already-finished) work is done. Honors
  // the 1s minimum from when showQuickLoading() was called, same "don't
  // let it flash by unnoticed" reasoning as the full loading tier.
  function hideQuickLoading() {
    var el = document.getElementById('admin-action-loading');
    if (!el) return;
    var elapsed = Date.now() - (el._startedAt || Date.now());
    var remaining = Math.max(0, QUICK_LOADING_MS - elapsed);
    window.setTimeout(function () { el.classList.remove('show'); }, remaining);
  }
  window.hideQuickLoading = hideQuickLoading;

  function renderToast(message, type, durationMs) {
    applyToastStyle();
    var old = document.querySelector('.admin-toast');
    if (old) old.remove();
    var t = document.createElement('div');
    t.className = 'admin-toast' + (type !== 'success' ? ' admin-toast--' + type : '');
    var icon = document.createElement('i');
    icon.className = 'fa-solid ' + TOAST_ICONS[type];
    var span = document.createElement('span');
    span.textContent = message;
    t.appendChild(icon);
    t.appendChild(span);
    document.body.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('show'); });
    window.setTimeout(function () {
      t.classList.remove('show');
      window.setTimeout(function () { t.remove(); }, 300);
    }, durationMs || 3200);
  }

  function showToast(message, type, durationMs) {
    type = (type === 'warning' || type === 'error') ? type : 'success';
    var el = document.getElementById('admin-action-loading');
    var alreadyLoading = !!(el && el.classList.contains('show'));

    if (type === 'success') {
      // A success confirmation gets a deliberate "working on it… done" beat
      // instead of appearing instantly. If showActionLoading() was already
      // called when the action started, only wait out whatever's left of
      // the 2s minimum (the fetch time already counts); otherwise (a call
      // site that never pre-triggered it) fall back to a full 2s now.
      if (!alreadyLoading) el = showActionLoading();
      var elapsed = Date.now() - (el._startedAt || Date.now());
      var remaining = Math.max(0, ACTION_LOADING_MS - elapsed);
      window.setTimeout(function () {
        el.classList.remove('show');
        renderToast(message, type, durationMs);
      }, remaining);
    } else {
      // Something went wrong — don't make the user sit through a fake
      // loading wait just to find out why.
      if (alreadyLoading) el.classList.remove('show');
      renderToast(message, type, durationMs);
    }
  }
  window.showToast = showToast;

  // Shared polling helper so list pages (accounts, activities, etc.) pick up
  // additions from elsewhere without a manual refresh. Pauses while the tab
  // is hidden, and lets each page supply a guard so a tick doesn't clobber
  // in-progress work (an open modal, a checked bulk-select checkbox). Runs
  // silently — no visible "Refreshing…" pill — so switching between panels
  // during a demo doesn't flash a notice on screen.
  function startAutoRefresh(fn, intervalMs, guardFn) {
    if (typeof fn !== 'function') return;
    return window.setInterval(function () {
      if (document.hidden) return;
      if (typeof guardFn === 'function' && guardFn()) return;
      fn();
    }, intervalMs || 20000);
  }
  window.startAutoRefresh = startAutoRefresh;

  function updateBadge() {
    fetch('ADMIN_BACKEND/admin_notif_count.php', {
      credentials: 'same-origin',
      cache: 'no-store'
    })
      .then(function (response) {
        if (!response.ok) throw new Error('Notification count failed');
        return response.json();
      })
      .then(function (data) {
        var badge = getBadge();
        if (!badge) return;
        var count = Math.max(0, Number(data.unread) || 0);
        badge.textContent = count > 9 ? '9+' : (count || '');
        badge.classList.toggle('has-notifs', count > 0);
        badge.style.display = count > 0 ? 'flex' : 'none';
        badge.setAttribute('aria-label', count + ' unread notifications');
      })
      .catch(function () {
        var badge = getBadge();
        if (badge) badge.style.display = 'none';
      });
  }

  // Every ADMIN_FILES page defines its own .admin-sidebar mobile CSS (the
  // sidebar collapses into a wrapped horizontal nav bar and hides
  // .admin-sidebar-footer — which hides the Sign Out button). Overriding it
  // here, once, turns the sidebar into a proper off-canvas hamburger menu on
  // every admin page instead of duplicating this in 5 separate CSS files.
  function applySidebarToggleStyle() {
    if (document.getElementById('admin-sidebar-toggle-style')) return;
    var style = document.createElement('style');
    style.id = 'admin-sidebar-toggle-style';
    style.textContent =
      // In normal flow (not fixed) so it takes its own space at the top of the
      // page instead of floating over the greeting/title text underneath it.
      '.admin-hamburger-btn{display:none;width:40px;height:40px;margin-bottom:14px;border-radius:10px;background:#1E3A8A;color:#fff;border:none;align-items:center;justify-content:center;font-size:16px;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.2);flex-shrink:0}' +
      // Fades in/out instead of an abrupt display:none/block toggle, so the
      // dim overlay and the sidebar's own slide happen in sync as one
      // motion instead of the overlay looking like a separate layer that
      // just pops on top. pointer-events keeps it non-interactive (and the
      // page behind it tappable) while hidden, since opacity alone would
      // still block clicks during the fade-out.
      '.admin-sidebar-overlay{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:9000;opacity:0;pointer-events:none;transition:opacity .25s ease}' +
      '.admin-sidebar-overlay.show{opacity:1;pointer-events:auto}' +
      '@media (max-width:768px){' +
      '.admin-hamburger-btn{display:flex !important}' +
      // Some pages (e.g. Admin_notif.html) set the sidebar's own bottom:0 +
      // min-height:100vh alongside a fixed position for their desktop
      // layout. Left un-reset, that combination could fight the top:0 +
      // height:100vh below by a sub-pixel margin, just enough to trigger a
      // visible scrollbar down the right edge that looked like a stray
      // border. Explicitly reset every box-model property, not just size.
      '.admin-sidebar{position:fixed !important;top:0 !important;left:0 !important;right:auto !important;bottom:auto !important;margin:0 !important;transform:translateX(-100%) !important;width:260px !important;min-width:260px !important;height:100vh !important;min-height:0 !important;max-height:100vh !important;flex-direction:column !important;flex-wrap:nowrap !important;padding:0 !important;border:none !important;border-radius:0 !important;z-index:9001 !important;transition:transform .25s ease !important;overflow-y:auto !important;box-shadow:4px 0 24px rgba(0,0,0,.25) !important;scrollbar-width:none !important}' +
      '.admin-sidebar::-webkit-scrollbar{display:none !important}' +
      '.admin-sidebar.admin-sidebar-open{transform:translateX(0) !important}' +
      '.admin-sidebar-logo{padding:26px 20px 22px !important;margin-bottom:0 !important}' +
      '.admin-sidebar-nav{flex-direction:column !important;flex-wrap:nowrap !important;padding:20px 12px !important;width:auto !important;flex:1 !important}' +
      '.admin-nav-item{flex:none !important;min-width:0 !important;justify-content:flex-start !important;font-size:14px !important;padding:13px 16px !important}' +
      '.admin-nav-arrow{display:inline-block !important}' +
      '.admin-sidebar-footer{display:flex !important}' +
      // .admin-user-info (name + role) defaults to min-width:auto as a flex
      // child of .admin-sidebar-user, so a longer name refused to shrink
      // and pushed the profile card past the fixed 260px panel width —
      // the "sagging"/misaligned card at the bottom. Let it shrink and
      // ellipsize instead of overflowing.
      '.admin-sidebar-user{min-width:0 !important}' +
      '.admin-user-info{min-width:0 !important;flex:1 !important}' +
      '.admin-user-name,.admin-user-role{overflow:hidden !important;text-overflow:ellipsis !important;white-space:nowrap !important}' +
      // Put the hamburger and the page's notification bell on the same row
      // — both pinned to the top corners of .admin-main — instead of the
      // bell sitting in its own row further down the page. Icons sit 24px
      // from the top (not 20px) so they don't look glued to the very edge
      // of the screen, with matching extra clearance in the main padding.
      // .admin-main previously had position:relative with no z-index (i.e.
      // z-index:auto) — some pages give it a CSS animation on load, which
      // by spec forces it into its own stacking context regardless of
      // z-index, and an "auto" one can end up placed ambiguously depending
      // on the browser. That let the notif bell and hamburger (both
      // positioned inside .admin-main) render above the drawer/overlay
      // instead of being covered by them. Giving .admin-main an explicit,
      // low z-index removes that ambiguity outright.
      '.admin-main{width:100% !important;position:relative !important;z-index:1 !important;padding-top:84px !important}' +
      '.admin-hamburger-btn{position:absolute !important;top:24px !important;left:16px !important;margin-bottom:0 !important}' +
      '.admin-notif-btn{position:absolute !important;top:24px !important;right:16px !important}' +
      '}';
    document.head.appendChild(style);
  }

  function setupSidebarToggle() {
    var sidebar = document.querySelector('.admin-sidebar');
    if (!sidebar) return;

    var overlay = document.createElement('div');
    overlay.className = 'admin-sidebar-overlay';
    document.body.appendChild(overlay);

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'admin-hamburger-btn';
    btn.setAttribute('aria-label', 'Toggle menu');
    btn.innerHTML = '<i class="fa-solid fa-bars"></i>';
    // Insert into the main content column (not fixed to the viewport) so it
    // takes up its own space above the page's own heading instead of
    // floating on top of it.
    var main = document.querySelector('.admin-main') || document.querySelector('main') || document.body;
    main.insertBefore(btn, main.firstChild);

    // Without this, the page behind the overlay could still scroll while the
    // sidebar was open — the fixed sidebar stayed put but the background
    // content shifted underneath it, making its edge look like it was
    // jittering/moving.
    function closeSidebar() {
      sidebar.classList.remove('admin-sidebar-open');
      overlay.classList.remove('show');
      document.body.style.overflow = '';
    }
    function openSidebar() {
      sidebar.classList.add('admin-sidebar-open');
      overlay.classList.add('show');
      document.body.style.overflow = 'hidden';
    }

    btn.addEventListener('click', function () {
      if (sidebar.classList.contains('admin-sidebar-open')) closeSidebar();
      else openSidebar();
    });
    overlay.addEventListener('click', closeSidebar);
    sidebar.querySelectorAll('.admin-nav-item').forEach(function (item) {
      item.addEventListener('click', closeSidebar);
    });
    window.addEventListener('resize', function () {
      if (window.innerWidth > 768) closeSidebar();
    });
  }

  function start() {
    applyNotificationStyle();
    applySidebarToggleStyle();
    setupSidebarToggle();
    updateBadge();
    window.setInterval(updateBadge, 30000);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
