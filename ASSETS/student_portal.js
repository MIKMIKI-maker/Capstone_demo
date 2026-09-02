(function () {
  'use strict';

  // Every Student_*.html page already loads STUDENT_CSS/student_responsive.css
  // last, which defines the off-canvas drawer styles (.student-sidebar's
  // mobile position/transform, .student-hamburger-btn, .student-sidebar-overlay)
  // at <=900px. This just wires up the toggle behavior once, shared across
  // every student page, instead of duplicating it in each file.
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
    // Inserted into the main content column (not fixed to the viewport) so
    // it takes its own space above the greeting instead of floating over it.
    var main = document.querySelector('.student-main') || document.querySelector('main') || document.body;
    main.insertBefore(btn, main.firstChild);

    // Without this, the page behind the overlay could still scroll while
    // the drawer was open.
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

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupSidebarToggle);
  else setupSidebarToggle();
})();
