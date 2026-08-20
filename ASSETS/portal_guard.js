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
      if (!response.ok) throw new Error('Session check failed');
      return response.json();
    }).then(function (result) {
      if (!result.authenticated || result.role !== role) goToLogin();
      return result;
    }).catch(function () {
      goToLogin();
    });
  }

  checkSession();
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
      checkSession();
    }
  });
})();
