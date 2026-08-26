(function () {
  var script = document.currentScript;
  var role = script && script.getAttribute('data-portal-role');
  var loginPath = script && script.getAttribute('data-login-path');
  var statusPath = script && script.getAttribute('data-status-path');

  if (!role || !loginPath || !statusPath) return;

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
        if (result.role !== role) goToLogin();
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
})();
