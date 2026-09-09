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

  // Real-time-ish "important event" popup — a stacked toast in the top-right
  // corner (like a desktop chat app's incoming-message popup), distinct from
  // showToast() above (a single bottom-center confirmation for the teacher's
  // OWN actions). This one is for things that happen in the BACKGROUND that
  // the teacher should notice even if they never open the Notifications
  // page — right now, just "student still struggling with retakes" alerts.
  function applyNotifPopupStyle() {
    if (document.getElementById('teacher-notifpop-style')) return;
    var style = document.createElement('style');
    style.id = 'teacher-notifpop-style';
    style.textContent =
      '.teacher-notifpop-stack{position:fixed;top:20px;right:20px;z-index:100000;display:flex;flex-direction:column;gap:12px;max-width:360px;width:calc(100vw - 40px)}' +
      '.teacher-notifpop{position:relative;display:flex;align-items:flex-start;gap:13px;background:#fff;color:#1E293B;padding:16px 18px;border-radius:16px;font-family:"Poppins",sans-serif;box-shadow:0 16px 40px rgba(15,23,42,.22),0 0 0 1px rgba(245,158,11,.15);cursor:pointer;opacity:0;transform:translateX(60px) scale(.9);transition:opacity .35s ease,transform .45s cubic-bezier(.34,1.56,.64,1);overflow:hidden}' +
      '.teacher-notifpop::before{content:"";position:absolute;left:0;top:0;bottom:0;width:6px;background:linear-gradient(180deg,#FBBF24,#F59E0B)}' +
      '.teacher-notifpop.show{opacity:1;transform:translateX(0) scale(1);animation:teacherNotifpopWiggle .5s ease .45s}' +
      '@keyframes teacherNotifpopWiggle{0%,100%{transform:translateX(0) scale(1)}30%{transform:translateX(-4px) scale(1.015)}60%{transform:translateX(2px) scale(1)}}' +
      '.teacher-notifpop-icon{flex:none;position:relative;width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#FBBF24,#F59E0B);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;box-shadow:0 4px 10px rgba(245,158,11,.4)}' +
      '.teacher-notifpop-icon::after{content:"";position:absolute;inset:0;border-radius:50%;box-shadow:0 0 0 0 rgba(245,158,11,.55);animation:teacherNotifpopPulse 1.8s ease-out 3}' +
      '@keyframes teacherNotifpopPulse{0%{box-shadow:0 0 0 0 rgba(245,158,11,.55)}100%{box-shadow:0 0 0 14px rgba(245,158,11,0)}}' +
      '.teacher-notifpop-body{min-width:0;flex:1;padding-top:1px;padding-right:14px}' +
      '.teacher-notifpop-tag{display:table;font-size:9.5px;font-weight:800;letter-spacing:.06em;color:#B45309;background:#FEF3C7;padding:2px 7px;border-radius:6px;text-transform:uppercase;margin-bottom:6px}' +
      '.teacher-notifpop-title{display:block;font-weight:700;font-size:13.5px;margin-bottom:3px;line-height:1.3}' +
      '.teacher-notifpop-msg{display:block;font-size:12px;color:#475569;line-height:1.45}' +
      '.teacher-notifpop-close{position:absolute;top:10px;right:10px;flex:none;background:#f1f5f9;border:none;border-radius:50%;width:20px;height:20px;color:#64748b;font-size:13px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center}' +
      '.teacher-notifpop-close:hover{background:#e2e8f0;color:#1e293b}';
    document.head.appendChild(style);
  }

  function showNotifPopup(title, message, onClick) {
    applyNotifPopupStyle();
    var stack = document.getElementById('teacher-notifpop-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.id = 'teacher-notifpop-stack';
      stack.className = 'teacher-notifpop-stack';
      document.body.appendChild(stack);
    }
    var pop = document.createElement('div');
    pop.className = 'teacher-notifpop';
    pop.innerHTML = '<span class="teacher-notifpop-icon"><i class="fa-solid fa-rotate"></i></span>' +
      '<span class="teacher-notifpop-body"><span class="teacher-notifpop-tag">Needs attention</span>' +
      '<span class="teacher-notifpop-title"></span>' +
      '<span class="teacher-notifpop-msg"></span></span>' +
      '<button type="button" class="teacher-notifpop-close">&times;</button>';
    pop.querySelector('.teacher-notifpop-title').textContent = title;
    pop.querySelector('.teacher-notifpop-msg').textContent = message;
    function dismiss() {
      pop.classList.remove('show');
      window.setTimeout(function () { pop.remove(); }, 250);
    }
    pop.addEventListener('click', function (e) {
      if (e.target.closest('.teacher-notifpop-close')) { dismiss(); return; }
      if (typeof onClick === 'function') onClick();
    });
    pop.querySelector('.teacher-notifpop-close').addEventListener('click', function (e) { e.stopPropagation(); dismiss(); });
    stack.appendChild(pop);
    requestAnimationFrame(function () { pop.classList.add('show'); });
    window.setTimeout(dismiss, 8000);
  }

  // Popping the SAME retake alert again every poll would be spammy — a
  // student mid-activity can sit on one retake count for a while, and this
  // endpoint is polled repeatedly. Track which notification IDs have
  // already been shown as a popup (not the same as "read" — the teacher
  // might pop it, dismiss it, and still open the Notifications page
  // later) so each one only interrupts once, in localStorage so it
  // survives navigating between teacher pages.
  function getPoppedIds() {
    try { return JSON.parse(localStorage.getItem('teacher_popped_notif_ids') || '[]'); } catch (e) { return []; }
  }
  function markPopped(ids) {
    var seen = getPoppedIds();
    ids.forEach(function (id) { if (seen.indexOf(id) === -1) seen.push(id); });
    // Cap the history so this never grows unbounded over a long session.
    if (seen.length > 200) seen = seen.slice(seen.length - 200);
    try { localStorage.setItem('teacher_popped_notif_ids', JSON.stringify(seen)); } catch (e) {}
  }

  // teacher_portal.js loads from both TEACHER_FILES/Teacher_*.html (one
  // level above TEACHER_BACKEND) AND TEACHER_FILES/TEMPLATES/*.html (two
  // levels above it) — check the more specific /TEMPLATES/ path first, or
  // the plain /TEACHER_FILES/ check would match both and get the deeper
  // one wrong.
  function teacherRelativePath(target) {
    var p = window.location.pathname;
    if (/\/TEACHER_FILES\/TEMPLATES\//.test(p)) return '../' + target;
    if (/\/TEACHER_FILES\//.test(p)) return target;
    return 'TEACHER_FILES/' + target;
  }

  function pollRetakeAlerts() {
    var teacherId = sessionStorage.getItem('teacher_id') || '';
    if (!teacherId) return;
    fetch(teacherRelativePath('TEACHER_BACKEND/teacher_manage_notifications.php') + '?action=list&limit=5&teacher_id=' + encodeURIComponent(teacherId), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.success || !Array.isArray(d.notifications)) return;
        var popped = getPoppedIds();
        var toPop = d.notifications.filter(function (n) {
          return n.notification_type === 'retake_alert' && !n.is_read && popped.indexOf(n.id) === -1;
        });
        if (!toPop.length) return;
        markPopped(toPop.map(function (n) { return n.id; }));
        // Oldest first, so they stack in the order they actually happened.
        toPop.reverse().forEach(function (n) {
          showNotifPopup(n.title || 'Student Still Retrying', n.message || '', function () {
            window.location.href = teacherRelativePath('Teacher_notif.html');
          });
        });
      })
      .catch(function () {});
  }

  function initNotifPolling() {
    pollRetakeAlerts();
    window.setInterval(function () {
      if (document.hidden) return;
      pollRetakeAlerts();
    }, 20000);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initNotifPolling);
  else initNotifPolling();

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
