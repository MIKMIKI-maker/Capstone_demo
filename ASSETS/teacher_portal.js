(function () {
  'use strict';

  // Every Teacher_*.html page already loads TEACHER_CSS/teacher_responsive.css
  // last, which defines the off-canvas drawer styles (.teacher-sidebar's
  // mobile position/transform, .teacher-hamburger-btn, .teacher-sidebar-overlay)
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
    // Inserted into the main content column (not fixed to the viewport) so
    // it takes its own space above the greeting instead of floating over it.
    var main = document.querySelector('.teacher-main-content') || document.querySelector('main') || document.body;
    main.insertBefore(btn, main.firstChild);

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
