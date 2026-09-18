(function () {
  'use strict';

  // Shared floating success/failure toast for every student page (matches
  // the look of the page-local showToast() already in Student_settings.html,
  // extended with warning/error color variants).
  // Floats center-screen (not a corner toast) so it reads clearly during a
  // live demo; a success message is preceded by the same full-screen
  // loading beat as the page's own initial load, held for at least 2s, so
  // the confirmation never just flashes by unnoticed.
  var ACTION_LOADING_MS = 2000;

  function applyToastStyle() {
    if (document.getElementById('student-toast-style')) return;
    var style = document.createElement('style');
    style.id = 'student-toast-style';
    style.textContent =
      // z-index 100001 — one below .student-action-loading's 100002, so a
      // new action's loading indicator always wins if it ever has to appear
      // while a previous action's toast is still fading out.
      '.student-toast{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) scale(.92);background:#19a87c;color:#fff;padding:16px 30px;border-radius:18px;font-family:"Fredoka","Poppins",sans-serif;font-weight:700;font-size:15.5px;box-shadow:0 20px 50px rgba(15,117,86,.4);opacity:0;transition:opacity .3s ease,transform .3s ease;z-index:100001;pointer-events:none;max-width:90vw}' +
      '.student-toast.show{opacity:1;transform:translate(-50%,-50%) scale(1)}' +
      '.student-toast--warning{background:#ffc34d;color:#5c3d00;box-shadow:0 20px 50px rgba(210,146,15,.4)}' +
      '.student-toast--error{background:#ff8a5b;color:#fff;box-shadow:0 20px 50px rgba(196,84,37,.4)}';
    document.head.appendChild(style);
  }

  // No backdrop at all — the page underneath stays fully visible; just a
  // small floating loading indicator, not the blue full-screen #spedLoading
  // look used when a page first opens (that one stays exactly as-is).
  // Never blocks clicks (pointer-events:none throughout) since there's no
  // visual cue of a blocked page to justify blocking it.
  function applyActionLoadingStyle() {
    if (document.getElementById('student-action-loading-style')) return;
    var style = document.createElement('style');
    style.id = 'student-action-loading-style';
    style.textContent =
      '.student-action-loading{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:100002;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;opacity:0;transition:opacity .3s ease;pointer-events:none}' +
      '.student-action-loading.show{opacity:1}' +
      '.student-action-loading img{width:72px;height:72px;animation:studentAlPulse 2s ease-in-out infinite;filter:drop-shadow(0 3px 8px rgba(0,0,0,.25))}' +
      '.student-action-loading p{color:#1b4fb8;font-family:"Fredoka","Poppins",sans-serif;font-size:14px;font-weight:700;margin:0;text-shadow:0 1px 3px rgba(255,255,255,.9),0 0 10px rgba(255,255,255,.7)}' +
      '.student-al-dots{display:flex;gap:9px;align-items:center}' +
      '.student-al-dots span{width:11px;height:11px;border-radius:50%;background:#1b4fb8;display:inline-block;animation:studentAlBounce 1.3s ease-in-out infinite;box-shadow:0 2px 6px rgba(0,0,0,.3)}' +
      '.student-al-dots span:nth-child(2){animation-delay:.18s}' +
      '.student-al-dots span:nth-child(3){animation-delay:.36s}' +
      '@keyframes studentAlBounce{0%,80%,100%{transform:translateY(0);opacity:.5}40%{transform:translateY(-14px);opacity:1}}' +
      '@keyframes studentAlPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.06)}}';
    document.head.appendChild(style);
  }

  function getActionLoadingEl() {
    applyActionLoadingStyle();
    var el = document.getElementById('student-action-loading');
    if (!el) {
      el = document.createElement('div');
      el.id = 'student-action-loading';
      el.className = 'student-action-loading';
      el.innerHTML = '<img src="../ASSETS/logo_loading.png" alt="" onerror="this.style.display=\'none\'">' +
        '<div class="student-al-dots"><span></span><span></span><span></span></div>' +
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
    // to ~3.2s) when a new action starts. That old toast is now stale, so
    // clear it immediately instead of letting it linger on top of (or
    // behind) the new loading indicator.
    var oldToast = document.querySelector('.student-toast');
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
  // looks broken.
  var QUICK_LOADING_MS = 1000;

  function showQuickLoading() {
    var oldToast = document.querySelector('.student-toast');
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
    var el = document.getElementById('student-action-loading');
    if (!el) return;
    var elapsed = Date.now() - (el._startedAt || Date.now());
    var remaining = Math.max(0, QUICK_LOADING_MS - elapsed);
    window.setTimeout(function () { el.classList.remove('show'); }, remaining);
  }
  window.hideQuickLoading = hideQuickLoading;

  function renderToast(message, type, durationMs) {
    applyToastStyle();
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

  function showToast(message, type, durationMs) {
    type = (type === 'warning' || type === 'error') ? type : 'success';
    var el = document.getElementById('student-action-loading');
    var alreadyLoading = !!(el && el.classList.contains('show'));

    if (type === 'success') {
      if (!alreadyLoading) el = showActionLoading();
      var elapsed = Date.now() - (el._startedAt || Date.now());
      var remaining = Math.max(0, ACTION_LOADING_MS - elapsed);
      window.setTimeout(function () {
        el.classList.remove('show');
        renderToast(message, type, durationMs);
      }, remaining);
    } else {
      if (alreadyLoading) el.classList.remove('show');
      renderToast(message, type, durationMs);
    }
  }
  window.showToast = showToast;

  // Shared polling helper so list pages (materials, dashboard, etc.) pick up
  // additions from elsewhere without a manual refresh. Pauses while the tab
  // is hidden, and lets each page supply a guard so a tick doesn't clobber
  // in-progress work. Runs silently — no visible "Refreshing…" pill — so
  // switching between panels during a demo doesn't flash a notice on screen.
  function startAutoRefresh(fn, intervalMs, guardFn) {
    if (typeof fn !== 'function') return;
    return window.setInterval(function () {
      if (document.hidden) return;
      if (typeof guardFn === 'function' && guardFn()) return;
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

  // "Liquid glass" look for the notif bell — frosted/translucent circle with
  // a glossy top-left highlight (the ::before radial-gradient) instead of a
  // flat white disc, and a drop-shadow on the icon itself so the bell shape
  // reads clearly against the blur instead of flattening into the page.
  // Appended after every page's own linked CSS, so this same-specificity
  // selector wins the cascade and overrides the (now-legacy) per-page
  // .student-notif-btn rules without having to touch every CSS file. Never
  // sets `display` on the badge — that stays controlled by each page's own
  // .student-notif-badge.has-notifs toggle.
  function applyNotifBtnStyle() {
    if (document.getElementById('student-notif-btn-style')) return;
    var style = document.createElement('style');
    style.id = 'student-notif-btn-style';
    style.textContent = '.student-notif-btn{position:relative;width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#1b4fb8;font-size:18px;text-decoration:none;cursor:pointer;background:linear-gradient(135deg,rgba(255,255,255,.8),rgba(255,255,255,.4));-webkit-backdrop-filter:blur(14px) saturate(180%);backdrop-filter:blur(14px) saturate(180%);border:1px solid rgba(255,255,255,.7);box-shadow:0 12px 28px rgba(27,79,184,.32),0 3px 8px rgba(27,79,184,.22),inset 0 1px 0 rgba(255,255,255,.9),inset 0 -6px 10px -6px rgba(27,79,184,.14);transition:transform .25s cubic-bezier(.34,1.56,.64,1),box-shadow .25s ease}' +
      '.student-notif-btn::before{content:"";position:absolute;inset:0;border-radius:50%;background:radial-gradient(circle at 30% 22%,rgba(255,255,255,.95),rgba(255,255,255,0) 55%);pointer-events:none}' +
      '.student-notif-btn i{position:relative;z-index:1;filter:drop-shadow(0 2px 3px rgba(27,79,184,.6))}' +
      '.student-notif-btn:hover{transform:translateY(-2px) scale(1.06);box-shadow:0 16px 34px rgba(27,79,184,.42),0 4px 10px rgba(27,79,184,.28),inset 0 1px 0 rgba(255,255,255,.95),inset 0 -6px 10px -6px rgba(27,79,184,.18)}' +
      '.student-notif-badge{border:1.5px solid rgba(255,255,255,.9);z-index:2}';
    document.head.appendChild(style);
  }

  function start() {
    applySidebarToggleStyle();
    applyNotifBtnStyle();
    setupSidebarToggle();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
