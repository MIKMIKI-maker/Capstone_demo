(function () {
  'use strict';

  // .student-notif-btn normally lives in the page's own header
  // (.student-topbar / .student-page-header, varies per page) — a
  // separate DOM subtree from .student-sidebar. On mobile it should
  // visually sit inside the blue sidebar card's logo row instead, so
  // actually move the element there (not clone it — a clone would need
  // its own copy of the JS-updated unread badge kept in sync) and move
  // it back to its original spot above the breakpoint.
  function init() {
    var btn = document.querySelector('.student-notif-btn');
    var logo = document.querySelector('.student-sidebar-logo');
    if (!btn || !logo) return;

    var originalParent = btn.parentElement;
    var originalNext = btn.nextSibling;
    var mq = window.matchMedia('(max-width: 900px)');

    function place(isMobile) {
      if (isMobile) {
        if (btn.parentElement !== logo) logo.appendChild(btn);
      } else if (btn.parentElement !== originalParent) {
        originalParent.insertBefore(btn, originalNext);
      }
    }

    place(mq.matches);
    if (mq.addEventListener) mq.addEventListener('change', function (e) { place(e.matches); });
    else mq.addListener(function (e) { place(e.matches); });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
