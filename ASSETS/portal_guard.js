(function () {
  var script = document.currentScript;
  var role = script && script.getAttribute('data-portal-role');
  var loginPath = script && script.getAttribute('data-login-path');
  var statusPath = script && script.getAttribute('data-status-path');

  if (!role || !loginPath || !statusPath) return;

  // Some pages (the activity template editors, opened by a teacher in edit
  // mode and by a student in play mode) are shared between two roles —
  // data-portal-role="teacher,student" lists every role allowed on that page.
  var allowedRoles = role.split(',').map(function (r) { return r.trim(); }).filter(Boolean);

  var redirecting = false;

  function goToLogin() {
    if (redirecting) return;
    redirecting = true;
    try { sessionStorage.clear(); } catch (error) {}
    window.location.replace(loginPath);
  }

  function checkSession() {
    return fetch(statusPath, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Cache-Control': 'no-cache' }
    }).then(function (response) {
      return response.json().then(function (result) {
        if (response.status === 401 || result.authenticated === false) goToLogin();
        if (!response.ok) throw new Error('Session check temporarily unavailable');
        if (allowedRoles.indexOf(result.role) === -1) goToLogin();
        return result;
      });
    }).catch(function () {
      // A temporary server/network error must not log out an active user.
      setTimeout(checkSession, 5000);
    });
  }

  checkSession();
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
      checkSession();
    }
  });

  // Re-checking on an interval (not just once per page load) also doubles
  // as a heartbeat — session_status.php refreshes last_seen on every call,
  // which is what Manage Users' "currently online" status is based on.
  // Only while the tab is actually visible, so a background/minimized tab
  // doesn't keep a user looking "online" indefinitely.
  setInterval(function () {
    if (document.visibilityState === 'visible') checkSession();
  }, 60000);
})();
