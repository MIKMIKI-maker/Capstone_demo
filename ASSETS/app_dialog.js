/* ─────────────────────────────────────────────────────────────
 * App Dialog — styled replacements for the browser's alert()/confirm()
 * boxes ("capstone-demo-ezwr.onrender.com says…").
 *
 *   appConfirm(message, opts) → Promise<boolean>
 *       opts: { title, okText, cancelText, danger }
 *   appAlert(message, opts)   → Promise<void>
 *       opts: { title, okText }
 *
 * Including this script also routes window.alert() to appAlert, so the
 * existing alert(...) calls get the same look. Unlike the native box it
 * doesn't pause the code, which is fine where alert() is followed by a
 * return. confirm() can't be swapped that way (callers need the answer
 * right away), so those call appConfirm(...).then(...) instead.
 * ───────────────────────────────────────────────────────────── */
(function () {
  if (window.appConfirm) return;

  var nativeAlert = window.alert.bind(window);
  var queue = Promise.resolve();

  function injectStyles() {
    if (document.getElementById('appDialogStyles')) return;
    var st = document.createElement('style');
    st.id = 'appDialogStyles';
    st.textContent =
      '.appdlg-overlay{position:fixed;inset:0;z-index:2147483000;background:rgba(15,23,42,.55);display:flex;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;animation:appdlgFade .15s ease-out;font-family:"Nunito","Poppins",system-ui,sans-serif;}' +
      '.appdlg-box{background:#fff;color:#1e293b;border-radius:18px;width:min(420px,100%);box-shadow:0 24px 60px rgba(15,23,42,.35);padding:24px 22px 18px;text-align:center;animation:appdlgPop .18s ease-out;}' +
      '.appdlg-icon{width:52px;height:52px;border-radius:50%;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:24px;background:#dbeafe;}' +
      '.appdlg-icon.warn{background:#fef3c7;}' +
      '.appdlg-icon.danger{background:#fee2e2;}' +
      '.appdlg-title{margin:0 0 6px;font-size:18px;font-weight:800;color:#1e3a8a;}' +
      '.appdlg-msg{margin:0 0 20px;font-size:15px;line-height:1.5;color:#475569;white-space:pre-line;word-break:break-word;}' +
      '.appdlg-actions{display:flex;gap:10px;justify-content:center;}' +
      '.appdlg-btn{flex:1;max-width:170px;border:none;border-radius:12px;padding:11px 16px;font:inherit;font-size:15px;font-weight:800;cursor:pointer;}' +
      '.appdlg-btn:focus-visible{outline:3px solid #93c5fd;outline-offset:2px;}' +
      '.appdlg-cancel{background:#f1f5f9;color:#334155;}' +
      '.appdlg-cancel:hover{background:#e2e8f0;}' +
      '.appdlg-ok{background:#1e3a8a;color:#fff;}' +
      '.appdlg-ok:hover{background:#1e40af;}' +
      '.appdlg-ok.danger{background:#dc2626;}' +
      '.appdlg-ok.danger:hover{background:#b91c1c;}' +
      '@keyframes appdlgFade{from{opacity:0}to{opacity:1}}' +
      '@keyframes appdlgPop{from{opacity:0;transform:scale(.94)}to{opacity:1;transform:scale(1)}}';
    (document.head || document.documentElement).appendChild(st);
  }

  function open(kind, message, opts) {
    opts = opts || {};
    return new Promise(function (resolve) {
      injectStyles();
      var isConfirm = kind === 'confirm';
      var overlay = document.createElement('div');
      overlay.className = 'appdlg-overlay';
      overlay.setAttribute('role', isConfirm ? 'alertdialog' : 'dialog');
      overlay.setAttribute('aria-modal', 'true');

      var box = document.createElement('div');
      box.className = 'appdlg-box';

      var icon = document.createElement('div');
      icon.className = 'appdlg-icon' + (opts.danger ? ' danger' : isConfirm ? ' warn' : '');
      icon.textContent = opts.danger ? '🗑️' : isConfirm ? '⚠️' : 'ℹ️';

      var title = document.createElement('h3');
      title.className = 'appdlg-title';
      title.id = 'appdlgTitle' + Date.now();
      title.textContent = opts.title || (isConfirm ? 'Are you sure?' : 'Notice');
      overlay.setAttribute('aria-labelledby', title.id);

      var msg = document.createElement('p');
      msg.className = 'appdlg-msg';
      msg.textContent = String(message == null ? '' : message);

      var actions = document.createElement('div');
      actions.className = 'appdlg-actions';
      var cancel = null;
      if (isConfirm) {
        cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'appdlg-btn appdlg-cancel';
        cancel.textContent = opts.cancelText || 'Cancel';
        actions.appendChild(cancel);
      }
      var ok = document.createElement('button');
      ok.type = 'button';
      ok.className = 'appdlg-btn appdlg-ok' + (opts.danger ? ' danger' : '');
      ok.textContent = opts.okText || 'OK';
      actions.appendChild(ok);

      box.appendChild(icon);
      box.appendChild(title);
      box.appendChild(msg);
      box.appendChild(actions);
      overlay.appendChild(box);

      var previousFocus = document.activeElement;
      function finish(answer) {
        document.removeEventListener('keydown', onKey, true);
        if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        try { if (previousFocus && previousFocus.focus) previousFocus.focus(); } catch (e) {}
        resolve(answer);
      }
      function onKey(e) {
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); finish(false); }
        else if (e.key === 'Tab') {
          // keep focus inside the dialog
          var items = cancel ? [cancel, ok] : [ok];
          var i = items.indexOf(document.activeElement);
          e.preventDefault();
          items[(i + (e.shiftKey ? -1 : 1) + items.length) % items.length].focus();
        }
      }
      ok.addEventListener('click', function () { finish(true); });
      if (cancel) cancel.addEventListener('click', function () { finish(false); });
      overlay.addEventListener('click', function (e) { if (e.target === overlay) finish(false); });
      document.addEventListener('keydown', onKey, true);

      (document.body || document.documentElement).appendChild(overlay);
      // A risky confirm starts on Cancel; everything else on OK.
      ((opts.danger && cancel) ? cancel : ok).focus();
    });
  }

  // One dialog at a time: a second call waits for the first to close.
  function enqueue(kind, message, opts) {
    var p = queue.then(function () { return open(kind, message, opts); });
    queue = p.then(function () {}, function () {});
    return p;
  }

  window.appConfirm = function (message, opts) { return enqueue('confirm', message, opts); };
  window.appAlert = function (message, opts) {
    if (!document.body) { nativeAlert(message); return Promise.resolve(); }
    return enqueue('alert', message, opts).then(function () {});
  };
  window.alert = function (message) { window.appAlert(message); };
})();
