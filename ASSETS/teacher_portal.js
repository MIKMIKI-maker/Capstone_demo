(function () {
  'use strict';

  // Shared floating success/failure toast for every teacher page — replaces
  // the page-local showIepToast() (Teacher_IEP.html) with one definition
  // everyone calls, plus the error/red variant that one never had.
  var TOAST_ICONS = { success: 'fa-circle-check', warning: 'fa-triangle-exclamation', error: 'fa-circle-xmark' };

  function applyToastStyle() {
    if (document.getElementById('teacher-toast-style')) return;
    var style = document.createElement('style');
    style.id = 'teacher-toast-style';
    style.textContent =
      '.teacher-toast{position:fixed;bottom:32px;left:50%;transform:translateX(-50%) translateY(20px);display:flex;align-items:center;gap:10px;background:#fff;color:#1E293B;padding:14px 22px;border-radius:14px;font-family:"Poppins",sans-serif;font-weight:600;font-size:14px;box-shadow:0 6px 20px rgba(0,0,0,.10);border-left:4px solid #10B981;opacity:0;transition:opacity .25s ease,transform .25s ease;z-index:99999;pointer-events:none;max-width:90vw}' +
      '.teacher-toast.show{opacity:1;transform:translateX(-50%) translateY(0)}' +
      '.teacher-toast--warning{border-left-color:#F59E0B}' +
      '.teacher-toast--error{border-left-color:#EF4444}' +
      '.teacher-toast i{font-size:16px;color:#10B981}' +
      '.teacher-toast--warning i{color:#F59E0B}' +
      '.teacher-toast--error i{color:#EF4444}';
    document.head.appendChild(style);
  }

  function showToast(message, type, durationMs) {
    applyToastStyle();
    type = (type === 'warning' || type === 'error') ? type : 'success';
    var old = document.querySelector('.teacher-toast');
    if (old) old.remove();
    var t = document.createElement('div');
    t.className = 'teacher-toast' + (type !== 'success' ? ' teacher-toast--' + type : '');
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
  window.showToast = showToast;

  // Small "Refreshing…" pill shown briefly on every auto-refresh tick, so
  // the periodic reload is visible instead of silently swapping data in.
  function applyRefreshIndicatorStyle() {
    if (document.getElementById('teacher-refresh-indicator-style')) return;
    var style = document.createElement('style');
    style.id = 'teacher-refresh-indicator-style';
    style.textContent =
      '.teacher-refresh-indicator{position:fixed;top:14px;left:50%;transform:translateX(-50%) translateY(-12px);display:flex;align-items:center;gap:8px;background:#fff;color:#1E3A8A;padding:7px 16px;border-radius:999px;font-family:"Poppins",sans-serif;font-weight:600;font-size:12px;box-shadow:0 6px 18px rgba(0,0,0,.12);opacity:0;transition:opacity .2s ease,transform .2s ease;z-index:99998;pointer-events:none}' +
      '.teacher-refresh-indicator.show{opacity:1;transform:translateX(-50%) translateY(0)}' +
      '.teacher-refresh-indicator i{font-size:12px;animation:teacherRefreshSpin 0.8s linear infinite}' +
      '@keyframes teacherRefreshSpin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}';
    document.head.appendChild(style);
  }

  function showRefreshIndicator() {
    applyRefreshIndicatorStyle();
    var el = document.getElementById('teacher-refresh-indicator');
    if (!el) {
      el = document.createElement('div');
      el.id = 'teacher-refresh-indicator';
      el.className = 'teacher-refresh-indicator';
      el.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i><span>Refreshing…</span>';
      document.body.appendChild(el);
    }
    el.classList.add('show');
    window.clearTimeout(el._hideTimer);
    el._hideTimer = window.setTimeout(function () { el.classList.remove('show'); }, 1200);
  }

  // Shared polling helper so list pages (activities, dashboard stats, etc.)
  // pick up additions from elsewhere without a manual refresh. Pauses while
  // the tab is hidden, and lets each page supply a guard so a tick doesn't
  // clobber in-progress work (an open modal, an in-flight confirm dialog).
  function startAutoRefresh(fn, intervalMs, guardFn) {
    if (typeof fn !== 'function') return;
    return window.setInterval(function () {
      if (document.hidden) return;
      if (typeof guardFn === 'function' && guardFn()) return;
      showRefreshIndicator();
      fn();
    }, intervalMs || 20000);
  }
  window.startAutoRefresh = startAutoRefresh;

  // Every Teacher_*.html page already loads TEACHER_CSS/teacher_responsive.css
  // last, which defines the off-canvas drawer styles (.teacher-sidebar's
  // mobile position/transform, .teacher-mobile-topbar, .teacher-sidebar-overlay)
  // at <=1024px. This just wires up the toggle behavior once, shared across
  // every teacher page, instead of duplicating it in each file.
  function setupSidebarToggle() {
    var sidebar = document.querySelector('.teacher-sidebar');
    if (!sidebar) return;

    var overlay = document.createElement('div');
    overlay.className = 'teacher-sidebar-overlay';
    document.body.appendChild(overlay);

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'teacher-hamburger-btn';
    btn.setAttribute('aria-label', 'Toggle menu');
    btn.innerHTML = '<i class="fa-solid fa-bars"></i>';

    // A real flex row (hamburger left, notif bell right) in normal
    // document flow, instead of leaving the bell in its original topbar
    // position where it ended up hanging below the greeting once that
    // topbar wrapped on a phone. Inserted as .teacher-main-content's
    // first child, so it takes its own space at the very top and pushes
    // the greeting/page header down cleanly below it.
    var bar = document.createElement('div');
    bar.className = 'teacher-mobile-topbar';
    bar.appendChild(btn);

    // .teacher-main is Teacher_settings.html's own name for this same
    // element (every other Teacher_*.html page calls it
    // .teacher-main-content) and it has no <main> tag either, so
    // without this the bar fell all the way through to document.body —
    // landing outside the page's flex layout entirely instead of inside it.
    var main = document.querySelector('.teacher-main-content') || document.querySelector('.teacher-main') || document.querySelector('main') || document.body;
    main.insertBefore(bar, main.firstChild);

    // Move the page's own notif bell into this row instead of leaving
    // it wherever it sits in the topbar (some pages share that spot
    // with other action buttons, e.g. "Generate PDF" — those stay put,
    // only the bell moves). Relocating the existing element (not
    // cloning it) keeps its badge/href/listeners intact — but only at
    // <=1024px: this is a real DOM move, not a copy, and
    // .teacher-mobile-topbar is display:none above that width, so
    // moving it unconditionally made the bell vanish on desktop too.
    // Track its original spot so it can move back on resize.
    var notifBtn = document.querySelector('.teacher-notif-btn');
    var notifHome = notifBtn ? notifBtn.parentNode : null;
    var notifNextSibling = notifBtn ? notifBtn.nextSibling : null;

    function placeNotifBtn() {
      if (!notifBtn) return;
      if (window.innerWidth <= 1024) {
        if (notifBtn.parentNode !== bar) bar.appendChild(notifBtn);
      } else if (notifHome && notifBtn.parentNode !== notifHome) {
        notifHome.insertBefore(notifBtn, notifNextSibling);
      }
    }
    placeNotifBtn();

    // Without this, the page behind the overlay could still scroll while
    // the drawer was open.
    function closeSidebar() {
      sidebar.classList.remove('teacher-sidebar-open');
      overlay.classList.remove('show');
      document.body.style.overflow = '';
    }
    function openSidebar() {
      sidebar.classList.add('teacher-sidebar-open');
      overlay.classList.add('show');
      document.body.style.overflow = 'hidden';
    }

    btn.addEventListener('click', function () {
      if (sidebar.classList.contains('teacher-sidebar-open')) closeSidebar();
      else openSidebar();
    });
    overlay.addEventListener('click', closeSidebar);
    sidebar.querySelectorAll('.teacher-nav-item').forEach(function (item) {
      item.addEventListener('click', closeSidebar);
    });
    window.addEventListener('resize', function () {
      if (window.innerWidth > 1024) closeSidebar();
      placeNotifBtn();
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupSidebarToggle);
  else setupSidebarToggle();
})();
