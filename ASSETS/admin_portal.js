(function () {
  'use strict';

  function getBadge() {
    return document.querySelector('.admin-notif-badge');
  }

  function applyNotificationStyle() {
    if (document.getElementById('admin-notification-style')) return;
    var style = document.createElement('style');
    style.id = 'admin-notification-style';
    style.textContent = '.admin-notif-btn{position:relative;width:44px;height:44px;background:#fff;border:1px solid #e2e8f0;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#2563eb;font-size:17px;text-decoration:none;box-shadow:0 2px 8px rgba(0,0,0,.06)}' +
      '.admin-notif-badge{position:absolute;top:-3px;right:-3px;min-width:16px;height:16px;padding:0 3px;border:2px solid #fff;border-radius:999px;background:#ef4444;color:#fff;font:700 9px/12px Inter,Arial,sans-serif;text-align:center;align-items:center;justify-content:center}';
    document.head.appendChild(style);
  }

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
      '.admin-sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000}' +
      '.admin-sidebar-overlay.show{display:block}' +
      '@media (max-width:768px){' +
      '.admin-hamburger-btn{display:flex !important}' +
      // Some pages (e.g. Admin_notif.html) set the sidebar's own bottom:0 +
      // min-height:100vh alongside a fixed position for their desktop
      // layout. Left un-reset, that combination could fight the top:0 +
      // height:100vh below by a sub-pixel margin, just enough to trigger a
      // visible scrollbar down the right edge that looked like a stray
      // border. Explicitly reset every box-model property, not just size.
      '.admin-sidebar{position:fixed !important;top:0 !important;left:0 !important;right:auto !important;bottom:auto !important;margin:0 !important;transform:translateX(-100%) !important;width:260px !important;min-width:260px !important;height:100vh !important;min-height:0 !important;max-height:100vh !important;flex-direction:column !important;flex-wrap:nowrap !important;padding:0 !important;border:none !important;border-radius:0 !important;z-index:1001 !important;transition:transform .25s ease !important;overflow-y:auto !important;box-shadow:4px 0 24px rgba(0,0,0,.25) !important;scrollbar-width:none !important}' +
      '.admin-sidebar::-webkit-scrollbar{display:none !important}' +
      '.admin-sidebar.admin-sidebar-open{transform:translateX(0) !important}' +
      '.admin-sidebar-logo{padding:26px 20px 22px !important;margin-bottom:0 !important}' +
      '.admin-sidebar-nav{flex-direction:column !important;flex-wrap:nowrap !important;padding:20px 12px !important;width:auto !important;flex:1 !important}' +
      '.admin-nav-item{flex:none !important;min-width:0 !important;justify-content:flex-start !important;font-size:14px !important;padding:13px 16px !important}' +
      '.admin-nav-arrow{display:inline-block !important}' +
      '.admin-sidebar-footer{display:flex !important}' +
      // Put the hamburger and the page's notification bell on the same row
      // — both pinned to the top corners of .admin-main — instead of the
      // bell sitting in its own row further down the page.
      '.admin-main{width:100% !important;position:relative !important;padding-top:76px !important}' +
      '.admin-hamburger-btn{position:absolute !important;top:20px !important;left:16px !important;margin-bottom:0 !important}' +
      '.admin-notif-btn{position:absolute !important;top:20px !important;right:16px !important}' +
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
