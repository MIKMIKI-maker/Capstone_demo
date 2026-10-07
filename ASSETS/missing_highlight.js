/* ─────────────────────────────────────────────────────────────
 * Missing-answer highlight — shared by every activity template.
 *
 * When a student taps Check before answering everything, the old hint
 * text alone didn't say WHICH part was still blank. This lights up each
 * unanswered item with a pulsing orange glow and scrolls the first one
 * into view. An item's glow goes away as soon as the student touches it.
 *
 *   MissingMark.flag([el, el, ...])   // highlight these (clears old ones)
 *   MissingMark.clear()               // remove every highlight
 * ───────────────────────────────────────────────────────────── */
(function () {
  if (window.MissingMark) return;

  function injectStyles() {
    if (document.getElementById('mmStyles')) return;
    var st = document.createElement('style');
    st.id = 'mmStyles';
    st.textContent =
      '@keyframes mmPulse{0%,100%{box-shadow:0 0 0 3px rgba(245,158,11,.95),0 0 10px 4px rgba(245,158,11,.55);}' +
      '50%{box-shadow:0 0 0 5px rgba(239,68,68,.95),0 0 18px 8px rgba(239,68,68,.45);}}' +
      '.mm-missing{animation:mmPulse 1s ease-in-out infinite !important;outline:3px dashed #f59e0b !important;outline-offset:4px !important;border-radius:12px;}';
    document.head.appendChild(st);
  }

  function unflag(el) {
    el.classList.remove('mm-missing');
    if (el._mmOff) { el._mmOff(); el._mmOff = null; }
  }

  function clear() {
    Array.prototype.slice.call(document.querySelectorAll('.mm-missing')).forEach(unflag);
  }

  function flag(els) {
    clear();
    els = Array.prototype.filter.call(els || [], Boolean);
    if (!els.length) return 0;
    injectStyles();
    els.forEach(function (el) {
      el.classList.add('mm-missing');
      var off = function () { unflag(el); };
      ['pointerdown', 'input', 'change', 'keydown'].forEach(function (ev) { el.addEventListener(ev, off); });
      el._mmOff = function () {
        ['pointerdown', 'input', 'change', 'keydown'].forEach(function (ev) { el.removeEventListener(ev, off); });
      };
    });
    try { els[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
    return els.length;
  }

  window.MissingMark = { flag: flag, clear: clear };
})();
