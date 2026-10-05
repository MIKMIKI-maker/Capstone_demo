/*
 * School Year dropdown for Admin and Teacher pages. Include with:
 *   <script src="../ASSETS/school_year_switcher.js" defer></script>
 * It adds a "S.Y." picker to the page's top bar. Picking a School Year that
 * isn't Active shows a read-only banner and disables (greys out, but keeps
 * visible) anything marked data-sy-write (Generate, Upload, Add Student,
 * Edit, Unpublish, ...); the server also refuses changes while one is viewed
 * (see ADMIN_FILES/ADMIN_BACKEND/school_year.php).
 */
(function () {
  var script = document.currentScript;
  // The picker itself is shown on the Teacher Dashboard only; other pages load
  // this with data-picker="off" so they still show the banner and lock
  // changes while a past S.Y. (chosen on the Dashboard) is being viewed.
  // data-picker="label" (student portal): just a badge naming the Active S.Y.
  var labelOnly = script.getAttribute('data-picker') === 'label';
  var showPicker = !labelOnly && script.getAttribute('data-picker') !== 'off';
  var endpoint = new URL('../ADMIN_FILES/ADMIN_BACKEND/school_year_view.php', script.src).href;

  var css = ''
    + '.sy-switcher{display:inline-flex;align-items:center;gap:6px;background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;padding:4px 6px 4px 10px;font-family:inherit;font-size:12.5px;color:#334155;white-space:nowrap;}'
    + '.sy-switcher i{color:#64748b;}'
    + '.sy-switcher select{border:none;background:transparent;font:inherit;font-weight:600;color:#1e293b;cursor:pointer;outline:none;max-width:150px;}'
    + '.sy-switcher.sy-archived{border-color:#fcd34d;background:#fffbeb;}'
    + '.sy-banner{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:#fffbeb;border:1.5px solid #fcd34d;color:#92400e;border-radius:12px;padding:10px 14px;margin:0 0 16px;font-size:13px;font-weight:500;}'
    + '.sy-banner button{margin-left:auto;background:#92400e;color:#fff;border:none;border-radius:8px;padding:6px 12px;font:inherit;font-size:12px;font-weight:600;cursor:pointer;}'
    + 'body.sy-archived [data-sy-write]{opacity:.45!important;cursor:not-allowed!important;filter:grayscale(.4);box-shadow:none!important;transform:none!important;}'
    + '.sy-blocked{max-width:520px;margin:40px auto;text-align:center;background:#fff;border:1.5px solid #fcd34d;border-radius:16px;padding:32px 24px;color:#334155;}'
    + '.sy-blocked i{font-size:34px;color:#d97706;margin-bottom:12px;}'
    + '.sy-blocked h2{font-size:18px;margin:0 0 8px;color:#1e293b;}'
    + '.sy-blocked p{font-size:13.5px;margin:0 0 18px;line-height:1.6;}'
    + '.sy-blocked a{display:inline-block;margin:4px;padding:9px 16px;border-radius:9px;font-size:13px;font-weight:600;text-decoration:none;background:#1e3a8a;color:#fff;}'
    + '@media (max-width:640px){.sy-switcher select{max-width:110px;}.sy-banner button{margin-left:0;}}';
  var style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  function setView(id) {
    var body = new URLSearchParams();
    body.append('view_id', id);
    return fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function () { location.reload(); });
  }

  // Where the picker goes: the top bar's right-side group, or just before
  // the bell on top bars that don't have one.
  function findSlot() {
    var group = document.querySelector('.teacher-topbar-right, .admin-topbar-right, .teacher-topbar-actions');
    if (group) return { parent: group, before: group.firstChild };
    var bell = document.querySelector('.teacher-topbar .teacher-notif-btn, .admin-topbar .admin-notif-btn, header .student-notif-btn');
    if (bell) return { parent: bell.parentNode, before: bell };
    var header = document.querySelector('.student-topbar, .student-page-header');
    if (header) return { parent: header, before: null };
    return null;
  }

  // Generating new material belongs to the Active S.Y. only.
  function blockGeneratePage(syName) {
    var main = document.querySelector('.teacher-main-content');
    if (!main || !/Teacher_generate\.html$/i.test(location.pathname)) return;
    var topbar = main.querySelector('.teacher-topbar');
    Array.prototype.forEach.call(main.children, function (el) {
      if (el !== topbar && el.id !== 'sy-banner') el.style.display = 'none';
    });
    var box = document.createElement('div');
    box.className = 'sy-blocked';
    box.innerHTML = '<i class="fa-solid fa-box-archive"></i><h2>Generating is turned off</h2><p></p>'
      + '<a href="Teacher_IEP.html">View past activities</a>';
    box.querySelector('p').textContent = 'You are viewing S.Y. ' + syName + ', which is read-only. Switch back to the Active School Year to generate new materials.';
    main.appendChild(box);
  }

  var READ_ONLY_TIP = 'Read-only: switch back to the Active School Year to make changes';

  // Disables every data-sy-write control, including ones a page draws later
  // (activity rows, notes, ...), so they stay visible but can't be used.
  function lockWriteControls() {
    if (!document.body.classList.contains('sy-archived')) return;
    document.querySelectorAll('[data-sy-write]:not([data-sy-locked])').forEach(function (el) {
      el.setAttribute('data-sy-locked', '');
      if ('disabled' in el) el.disabled = true;
      el.setAttribute('aria-disabled', 'true');
      el.title = READ_ONLY_TIP;
    });
  }
  new MutationObserver(lockWriteControls).observe(document.documentElement, { childList: true, subtree: true });
  // Links (and anything else that can't be disabled) are blocked on click.
  document.addEventListener('click', function (e) {
    if (!document.body.classList.contains('sy-archived')) return;
    if (e.target.closest && e.target.closest('[data-sy-write]')) {
      e.preventDefault();
      e.stopImmediatePropagation();
    }
  }, true);

  function renderLabel(data) {
    // Anything on the page that names the School Year shows the Active one.
    var current = data.years.filter(function (sy) { return sy.id === data.active_id; })[0];
    if (current) {
      window.activeSchoolYearName = current.name;
      document.querySelectorAll('[data-sy-active-name]').forEach(function (el) { el.textContent = current.name; });
    }
    var slot = findSlot();
    if (!slot || document.getElementById('sy-switcher')) return;
    var active = data.years.filter(function (sy) { return sy.id === data.active_id; })[0];
    if (!active) return;
    var badge = document.createElement('span');
    badge.id = 'sy-switcher';
    badge.className = 'sy-switcher';
    badge.title = 'Current School Year';
    badge.style.marginLeft = 'auto';
    badge.style.marginRight = '10px';
    badge.style.padding = '6px 12px';
    badge.textContent = '🟢 S.Y. ' + active.name;
    slot.parent.insertBefore(badge, slot.before);
  }

  function render(data) {
    if (labelOnly) return renderLabel(data);
    var slot = findSlot();
    if (!slot) return;

    var old = document.getElementById('sy-switcher');
    if (old) old.remove();
    var oldBanner = document.getElementById('sy-banner');
    if (oldBanner) oldBanner.remove();

    var archived = data.viewing_id !== data.active_id;
    var wrap = document.createElement('label');
    wrap.id = 'sy-switcher';
    wrap.className = 'sy-switcher' + (archived ? ' sy-archived' : '');
    wrap.title = 'School Year';
    wrap.innerHTML = '<i class="fa-solid fa-calendar-days"></i><span>S.Y.</span>';

    var select = document.createElement('select');
    select.setAttribute('aria-label', 'School Year');
    data.years.forEach(function (sy) {
      var opt = document.createElement('option');
      opt.value = sy.id;
      // Green light = the Active S.Y., gray light = an inactive (past) one.
      opt.textContent = (sy.is_active ? '🟢 ' : '⚪ ') + sy.name;
      opt.title = sy.is_active ? 'Active School Year' : 'Not Active (read-only)';
      if (sy.id === data.viewing_id) opt.selected = true;
      select.appendChild(opt);
    });
    select.addEventListener('change', function () { setView(select.value); });
    wrap.appendChild(select);
    if (showPicker) slot.parent.insertBefore(wrap, slot.before);

    document.body.classList.toggle('sy-archived', archived);
    lockWriteControls();
    if (archived) {
      var viewing = data.years.filter(function (sy) { return sy.id === data.viewing_id; })[0];
      var syName = viewing ? viewing.name : '';
      var banner = document.createElement('div');
      banner.id = 'sy-banner';
      banner.className = 'sy-banner';
      banner.innerHTML = '<i class="fa-solid fa-box-archive"></i><span></span><button type="button">Back to Active S.Y.</button>';
      banner.querySelector('span').textContent = 'Viewing S.Y. ' + syName + ' (not Active) — history only. Generating, enrolling, uploading and other changes are turned off.';
      banner.querySelector('button').addEventListener('click', function () { setView(data.active_id); });
      var topbar = slot.parent.closest('header') || slot.parent;
      topbar.parentNode.insertBefore(banner, topbar.nextSibling);
      blockGeneratePage(syName);
    }
  }

  // The Active S.Y. this page was drawn for. If the Admin switches it
  // (here or in another tab), pages showing the old one reload themselves.
  var shownActiveId = null;
  var retries = 0;

  function load() {
    fetch(endpoint, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.success) throw new Error();
        retries = 0;
        shownActiveId = data.active_id;
        render(data);
      })
      .catch(function () {
        // A failed first load (e.g. server briefly busy) would leave the page
        // with no picker at all — try again a few times.
        if (retries++ < 5) setTimeout(load, 3000);
      });
  }

  function checkForChange() {
    if (shownActiveId === null || document.hidden) return;
    fetch(endpoint, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.success && data.active_id !== shownActiveId) location.reload();
      })
      .catch(function () {});
  }

  window.refreshSchoolYearSwitcher = load;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', load);
  else load();
  setInterval(checkForChange, 30000);
  document.addEventListener('visibilitychange', checkForChange);
})();
