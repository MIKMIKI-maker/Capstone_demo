(function () {
  'use strict';

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
