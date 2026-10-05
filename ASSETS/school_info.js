// Fills [data-school-name] / [data-school-location] from the server's school
// settings (SCHOOL_NAME / SCHOOL_LOCATION). The text already in the page is
// the fallback if the request fails. Pages that build the text in their own
// script can read window.schoolInfo or listen for the "schoolinfo" event.
(function () {
  window.schoolInfo = { name: 'Mamatid Elementary School', location: 'Cabuyao, Laguna' };
  var url = new URL('../ADMIN_FILES/ADMIN_BACKEND/school_info.php', document.currentScript.src).href;

  function apply() {
    document.querySelectorAll('[data-school-name]').forEach(function (el) { el.textContent = window.schoolInfo.name; });
    document.querySelectorAll('[data-school-location]').forEach(function (el) { el.textContent = window.schoolInfo.location; });
  }

  fetch(url, { credentials: 'same-origin' })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (info) {
      if (!info || !info.name) return;
      window.schoolInfo = { name: info.name, location: info.location || '' };
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apply);
      else apply();
      document.dispatchEvent(new CustomEvent('schoolinfo', { detail: window.schoolInfo }));
    })
    .catch(function () {});
})();
