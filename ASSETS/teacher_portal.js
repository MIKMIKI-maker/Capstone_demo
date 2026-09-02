(function () {
  'use strict';

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

    // Move the page's own notif bell into this row instead of leaving
    // it wherever it sits in the topbar (some pages share that spot
    // with other action buttons, e.g. "Generate PDF" — those stay put,
    // only the bell moves). Relocating the existing element (not
    // cloning it) keeps its badge/href/listeners intact.
    var notifBtn = document.querySelector('.teacher-notif-btn');
    if (notifBtn) bar.appendChild(notifBtn);

    // .teacher-main is Teacher_settings.html's own name for this same
    // element (every other Teacher_*.html page calls it
    // .teacher-main-content) and it has no <main> tag either, so
    // without this the bar fell all the way through to document.body —
    // landing outside the page's flex layout entirely instead of inside it.
    var main = document.querySelector('.teacher-main-content') || document.querySelector('.teacher-main') || document.querySelector('main') || document.body;
    main.insertBefore(bar, main.firstChild);

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
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupSidebarToggle);
  else setupSidebarToggle();
})();
