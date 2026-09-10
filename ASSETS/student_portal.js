(function () {
  'use strict';

  // Shared floating success/failure toast for every student page (matches
  // the look of the page-local showToast() already in Student_settings.html,
  // extended with warning/error color variants).
  function applyToastStyle() {
    if (document.getElementById('student-toast-style')) return;
    var style = document.createElement('style');
    style.id = 'student-toast-style';
    style.textContent =
      '.student-toast{position:fixed;bottom:32px;left:50%;transform:translateX(-50%) translateY(20px);background:#19a87c;color:#fff;padding:14px 28px;border-radius:16px;font-family:"Fredoka","Poppins",sans-serif;font-weight:700;font-size:15px;box-shadow:0 8px 24px rgba(15,117,86,.35);opacity:0;transition:opacity .3s ease,transform .3s ease;z-index:99999;pointer-events:none;max-width:90vw}' +
      '.student-toast.show{opacity:1;transform:translateX(-50%) translateY(0)}' +
      '.student-toast--warning{background:#ffc34d;color:#5c3d00;box-shadow:0 8px 24px rgba(210,146,15,.35)}' +
      '.student-toast--error{background:#ff8a5b;color:#fff;box-shadow:0 8px 24px rgba(196,84,37,.35)}';
    document.head.appendChild(style);
  }

  function showToast(message, type, durationMs) {
    applyToastStyle();
    type = (type === 'warning' || type === 'error') ? type : 'success';
    var old = document.querySelector('.student-toast');
    if (old) old.remove();
    var t = document.createElement('div');
    t.className = 'student-toast' + (type !== 'success' ? ' student-toast--' + type : '');
    t.textContent = message;
    document.body.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('show'); });
    window.setTimeout(function () {
      t.classList.remove('show');
      window.setTimeout(function () { t.remove(); }, 350);
    }, durationMs || 3200);
  }
  window.showToast = showToast;

  // Small "Refreshing…" pill shown briefly on every auto-refresh tick, so
  // the periodic reload is visible instead of silently swapping data in.
  function applyRefreshIndicatorStyle() {
    if (document.getElementById('student-refresh-indicator-style')) return;
    var style = document.createElement('style');
    style.id = 'student-refresh-indicator-style';
    style.textContent =
      '.student-refresh-indicator{position:fixed;top:14px;left:50%;transform:translateX(-50%) translateY(-12px);display:flex;align-items:center;gap:8px;background:#fff;color:#1b4fb8;padding:8px 18px;border-radius:999px;font-family:"Fredoka","Poppins",sans-serif;font-weight:700;font-size:12px;box-shadow:0 6px 18px rgba(36,58,94,.15);opacity:0;transition:opacity .2s ease,transform .2s ease;z-index:99998;pointer-events:none}' +
      '.student-refresh-indicator.show{opacity:1;transform:translateX(-50%) translateY(0)}' +
      '.student-refresh-indicator i{font-size:12px;animation:studentRefreshSpin 0.8s linear infinite}' +
      '@keyframes studentRefreshSpin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}';
    document.head.appendChild(style);
  }

  function showRefreshIndicator() {
    applyRefreshIndicatorStyle();
    var el = document.getElementById('student-refresh-indicator');
    if (!el) {
      el = document.createElement('div');
      el.id = 'student-refresh-indicator';
      el.className = 'student-refresh-indicator';
      el.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i><span>Refreshing…</span>';
      document.body.appendChild(el);
    }
    el.classList.add('show');
    window.clearTimeout(el._hideTimer);
    el._hideTimer = window.setTimeout(function () { el.classList.remove('show'); }, 1200);
  }

  // Shared polling helper so list pages (materials, dashboard, etc.) pick up
  // additions from elsewhere without a manual refresh. Pauses while the tab
  // is hidden, and lets each page supply a guard so a tick doesn't clobber
  // in-progress work.
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
  // showToast() above (a single bottom-center confirmation for the student's
  // OWN actions, e.g. finishing an activity). This one is for things the
  // TEACHER does in the background — a note/recommendation on the progress
  // report, a direct message, or publishing a new activity — that the
  // student should notice even if they never open the Notifications page
  // themselves.
  function applyNotifPopupStyle() {
    if (document.getElementById('student-notifpop-style')) return;
    var style = document.createElement('style');
    style.id = 'student-notifpop-style';
    style.textContent =
      '.student-notifpop-stack{position:fixed;top:20px;right:20px;z-index:100000;display:flex;flex-direction:column;gap:12px;max-width:360px;width:calc(100vw - 40px)}' +
      '.student-notifpop{position:relative;display:flex;align-items:flex-start;gap:13px;background:#fff;color:#243a5e;padding:16px 18px;border-radius:20px;font-family:"Fredoka","Poppins",sans-serif;box-shadow:0 16px 40px rgba(36,58,94,.28),0 0 0 1px rgba(47,111,237,.12);cursor:pointer;opacity:0;transform:translateX(60px) scale(.9);transition:opacity .35s ease,transform .45s cubic-bezier(.34,1.56,.64,1);overflow:hidden}' +
      '.student-notifpop::before{content:"";position:absolute;left:0;top:0;bottom:0;width:6px;background:linear-gradient(180deg,#60a5fa,#2f6fed)}' +
      '.student-notifpop.show{opacity:1;transform:translateX(0) scale(1);animation:studentNotifpopWiggle .5s ease .45s}' +
      '@keyframes studentNotifpopWiggle{0%,100%{transform:translateX(0) scale(1)}30%{transform:translateX(-4px) scale(1.015)}60%{transform:translateX(2px) scale(1)}}' +
      '.student-notifpop-icon{flex:none;position:relative;width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#60a5fa,#2f6fed);display:flex;align-items:center;justify-content:center;font-size:19px;box-shadow:0 4px 10px rgba(47,111,237,.4)}' +
      '.student-notifpop-icon::after{content:"";position:absolute;inset:0;border-radius:50%;box-shadow:0 0 0 0 rgba(47,111,237,.5);animation:studentNotifpopPulse 1.8s ease-out 3}' +
      '@keyframes studentNotifpopPulse{0%{box-shadow:0 0 0 0 rgba(47,111,237,.5)}100%{box-shadow:0 0 0 14px rgba(47,111,237,0)}}' +
      '.student-notifpop-body{min-width:0;flex:1;padding-top:1px;padding-right:14px}' +
      '.student-notifpop-tag{display:table;font-size:9.5px;font-weight:800;letter-spacing:.06em;color:#1b4fb8;background:#eaf2ff;padding:2px 7px;border-radius:6px;text-transform:uppercase;margin-bottom:6px}' +
      '.student-notifpop-title{display:block;font-weight:700;font-size:14px;margin-bottom:3px;line-height:1.3}' +
      '.student-notifpop-msg{display:block;font-size:12.5px;color:#5d7299;line-height:1.45}' +
      '.student-notifpop-close{position:absolute;top:10px;right:10px;flex:none;background:#eef3fb;border:none;border-radius:50%;width:20px;height:20px;color:#7d92b8;font-size:13px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center}' +
      '.student-notifpop-close:hover{background:#dde8fb;color:#243a5e}';
    document.head.appendChild(style);
  }

  function showNotifPopup(title, message, icon, onClick) {
    applyNotifPopupStyle();
    var stack = document.getElementById('student-notifpop-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.id = 'student-notifpop-stack';
      stack.className = 'student-notifpop-stack';
      document.body.appendChild(stack);
    }
    var pop = document.createElement('div');
    pop.className = 'student-notifpop';
    pop.innerHTML = '<span class="student-notifpop-icon">' + (icon || '🔔') + '</span>' +
      '<span class="student-notifpop-body"><span class="student-notifpop-tag">New!</span>' +
      '<span class="student-notifpop-title"></span>' +
      '<span class="student-notifpop-msg"></span></span>' +
      '<button type="button" class="student-notifpop-close">&times;</button>';
    pop.querySelector('.student-notifpop-title').textContent = title;
    pop.querySelector('.student-notifpop-msg').textContent = message;
    function dismiss() {
      pop.classList.remove('show');
      window.setTimeout(function () { pop.remove(); }, 250);
    }
    pop.addEventListener('click', function (e) {
      if (e.target.closest('.student-notifpop-close')) { dismiss(); return; }
      if (typeof onClick === 'function') onClick();
    });
    pop.querySelector('.student-notifpop-close').addEventListener('click', function (e) { e.stopPropagation(); dismiss(); });
    stack.appendChild(pop);
    requestAnimationFrame(function () { pop.classList.add('show'); });
    window.setTimeout(dismiss, 8000);
  }

  // Popping the SAME note/message again every poll would be spammy — track
  // which notification IDs have already been shown as a popup (not the same
  // as "read" — the student might pop it, dismiss it, and still open the
  // Notifications page later) in localStorage so each one only interrupts
  // once, surviving navigation between student pages.
  function getPoppedIds() {
    try { return JSON.parse(localStorage.getItem('student_popped_notif_ids') || '[]'); } catch (e) { return []; }
  }
  function markPopped(ids) {
    var seen = getPoppedIds();
    ids.forEach(function (id) { if (seen.indexOf(id) === -1) seen.push(id); });
    if (seen.length > 200) seen = seen.slice(seen.length - 200);
    try { localStorage.setItem('student_popped_notif_ids', JSON.stringify(seen)); } catch (e) {}
  }

  function pollStudentNotifs() {
    var srid = sessionStorage.getItem('student_record_id') || '';
    if (!srid) return;
    fetch('STUDENT_BACKEND/student_get_notifications.php?_t=' + Date.now(), { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.success || !Array.isArray(d.notifications)) return;
        var popped = getPoppedIds();
        var toPop = d.notifications.filter(function (n) {
          return !n.read && popped.indexOf(n.id) === -1;
        });
        if (!toPop.length) return;
        markPopped(toPop.map(function (n) { return n.id; }));
        toPop.reverse().forEach(function (n) {
          var icon = n.type === 'new_activity' ? '📚'
            : String(n.id).indexOf('note_') === 0 ? '📝' : '💬';
          // A newly-published activity is more useful landing the student
          // straight in My Materials (where they'd actually open it) than
          // the Notifications list.
          var dest = n.type === 'new_activity' ? 'Student_mymaterials.html' : 'Student_notif.html';
          showNotifPopup(n.title || 'New message', n.text || '', icon, function () {
            window.location.href = dest;
          });
        });
      })
      .catch(function () {});
  }

  function initNotifPolling() {
    pollStudentNotifs();
    window.setInterval(function () {
      if (document.hidden) return;
      pollStudentNotifs();
    }, 20000);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initNotifPolling);
  else initNotifPolling();

  // Every STUDENT_FILES page (and student_responsive.css) collapses
  // .student-sidebar into a wrapped horizontal strip at 900px — the nav
  // items, avatar and sign-out button all squeeze into a few awkward rows
  // above the page content instead of staying in an actual sidebar. This
  // overrides that, once, into a proper off-canvas hamburger menu on every
  // student page instead of duplicating the fix in 5 separate CSS files —
  // same pattern already used for the Admin portal in admin_portal.js.
  function applySidebarToggleStyle() {
    if (document.getElementById('student-sidebar-toggle-style')) return;
    var style = document.createElement('style');
    style.id = 'student-sidebar-toggle-style';
    style.textContent =
      // In normal flow (not fixed) so it takes its own space above the page
      // header instead of floating over the title/subtitle underneath it.
      '.student-hamburger-btn{display:none;width:40px;height:40px;margin-bottom:14px;border-radius:10px;background:#1E3A8A;color:#fff;border:none;align-items:center;justify-content:center;font-size:16px;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.2);flex-shrink:0}' +
      '.student-sidebar-overlay{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:9000;opacity:0;pointer-events:none;transition:opacity .25s ease}' +
      '.student-sidebar-overlay.show{opacity:1;pointer-events:auto}' +
      '@media (max-width:900px){' +
      '.student-hamburger-btn{display:flex !important}' +
      // Reset every box-model property (not just size) — the desktop rule
      // has position:sticky + a right-rounded border-radius that would
      // otherwise fight the fixed off-canvas panel below by a sub-pixel
      // margin, same class of bug the admin sidebar had.
      '.student-sidebar{position:fixed !important;top:0 !important;left:0 !important;right:auto !important;bottom:auto !important;margin:0 !important;padding-left:14px !important;padding-right:14px !important;transform:translateX(-100%) !important;width:200px !important;min-width:200px !important;max-width:72vw !important;height:100vh !important;min-height:0 !important;max-height:100vh !important;flex-direction:column !important;flex-wrap:nowrap !important;border-radius:0 !important;z-index:9001 !important;transition:transform .25s ease !important;overflow-y:auto !important;box-shadow:4px 0 24px rgba(0,0,0,.25) !important;scrollbar-width:none !important}' +
      '.student-sidebar::-webkit-scrollbar{display:none !important}' +
      '.student-sidebar.student-sidebar-open{transform:translateX(0) !important}' +
      '.student-sidebar-logo{margin-bottom:18px !important}' +
      '.student-sidebar-nav{flex-direction:column !important;flex-wrap:nowrap !important;width:auto !important;margin-top:6px !important}' +
      '.student-nav-item{flex:none !important;min-width:0 !important;justify-content:flex-start !important}' +
      '.student-sidebar-footer{display:flex !important;flex-direction:column !important;margin:auto 0 0 0 !important}' +
      // align-self:flex-start stops this card from stretching to the full
      // cross-axis width of the (flex-column) footer — it was filling the
      // whole sidebar edge-to-edge instead of sizing to its own content
      // like the nav items above it do.
      // align-self:flex-start alone wasn't enough — shrink-to-fit still
      // follows the natural (long) width of the name/role text since
      // .student-user-info has flex:1 with nothing to constrain it against.
      // An explicit max-width forces that text to actually truncate with
      // the ellipsis rule below instead of just stretching the card out.
      '.student-sidebar-user{min-width:0 !important;align-self:flex-start !important;max-width:150px !important;padding:10px !important;gap:9px !important}' +
      '.student-user-info{min-width:0 !important;flex:1 !important}' +
      '.student-user-name,.student-user-role{overflow:hidden !important;text-overflow:ellipsis !important;white-space:nowrap !important}' +
      // Pin the hamburger and the page's own notification bell to the same
      // row, at opposite top corners, instead of the bell sitting further
      // down wherever it happens to fall in each page's own header layout
      // (which varies page to page and can wrap below a multi-line
      // greeting/title on a narrow phone). Floating both out of normal flow
      // sidesteps that per-page layout variation entirely.
      '.student-main{width:100% !important;position:relative !important;padding-top:78px !important}' +
      '.student-hamburger-btn{position:absolute !important;top:20px !important;left:16px !important;margin-bottom:0 !important}' +
      '.student-notif-btn{position:absolute !important;top:20px !important;right:16px !important}' +
      '}';
    document.head.appendChild(style);
  }

  function setupSidebarToggle() {
    var sidebar = document.querySelector('.student-sidebar');
    if (!sidebar) return;

    var overlay = document.createElement('div');
    overlay.className = 'student-sidebar-overlay';
    document.body.appendChild(overlay);

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'student-hamburger-btn';
    btn.setAttribute('aria-label', 'Toggle menu');
    btn.innerHTML = '<i class="fa-solid fa-bars"></i>';
    var main = document.querySelector('.student-main') || document.querySelector('main') || document.body;
    main.insertBefore(btn, main.firstChild);

    // Without this the page behind the overlay could still scroll while the
    // sidebar was open — the fixed sidebar stays put but the background
    // content shifts underneath it.
    function closeSidebar() {
      sidebar.classList.remove('student-sidebar-open');
      overlay.classList.remove('show');
      document.body.style.overflow = '';
    }
    function openSidebar() {
      sidebar.classList.add('student-sidebar-open');
      overlay.classList.add('show');
      document.body.style.overflow = 'hidden';
    }

    btn.addEventListener('click', function () {
      if (sidebar.classList.contains('student-sidebar-open')) closeSidebar();
      else openSidebar();
    });
    overlay.addEventListener('click', closeSidebar);
    sidebar.querySelectorAll('.student-nav-item').forEach(function (item) {
      item.addEventListener('click', closeSidebar);
    });
    window.addEventListener('resize', function () {
      if (window.innerWidth > 900) closeSidebar();
    });
  }

  function start() {
    applySidebarToggleStyle();
    setupSidebarToggle();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
