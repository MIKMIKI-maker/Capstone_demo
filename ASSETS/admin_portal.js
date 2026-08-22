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

  function start() {
    applyNotificationStyle();
    updateBadge();
    window.setInterval(updateBadge, 30000);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
