/* ─────────────────────────────────────────────────────────────
 * One language for the whole system — loaded by every portal page and
 * every activity template (after i18n_dict.js).
 *
 * English or Tagalog, chosen once with the 🌐 EN | TL button (or the
 * activity's own Language setting — it is the same setting,
 * localStorage 'spedalm_audio_lang'), and every page follows it: text,
 * buttons, placeholders, pop-ups, alerts and text added later by scripts.
 *
 * How: i18n_dict.js lists [English, Tagalog] pairs. Each text node /
 * attribute keeps its original text; it is shown as-is, or swapped for its
 * pair in the chosen language. Numbers are matched as "#" so "5 of 10
 * learners" and "3 of 4 learners" share one entry. Longer text that has no
 * entry of its own gets known phrases inside it swapped.
 *
 * Mark anything that must never be translated (names typed by users,
 * answers…) with data-no-i18n.
 * ───────────────────────────────────────────────────────────── */
(function () {
  if (window.SpedI18n) return;

  var KEY = 'spedalm_audio_lang';
  function lang() {
    try { return localStorage.getItem(KEY) === 'fil' ? 'fil' : 'en'; } catch (e) { return 'en'; }
  }
  var current = lang();

  /* ── dictionary ─────────────────────────────────────────────── */
  var NUM = /\d+(?:[.,]\d+)*/g;
  function norm(s) { return String(s).replace(/\s+/g, ' ').trim(); }
  function key(s) { return norm(s).replace(NUM, '#'); }
  var toTL = new Map(), toEN = new Map();
  (window.SPEDALM_I18N_PAIRS || []).forEach(function (p) {
    var e = key(p[0]), t = key(p[1]);
    if (!e || !t || e === t) return;
    if (!toTL.has(e)) toTL.set(e, norm(p[1]));
    if (!toEN.has(t)) toEN.set(t, norm(p[0]));
  });
  // Phrases (2+ words, no numbers) to swap inside longer text.
  function phraseRe(map) {
    var keys = [];
    map.forEach(function (_, k) { if (k.indexOf(' ') > 0 && k.indexOf('#') < 0 && k.length >= 8) keys.push(k); });
    if (!keys.length) return null;
    keys.sort(function (a, b) { return b.length - a.length; });
    return new RegExp('(^|[^A-Za-zÀ-ÿ])(' + keys.map(function (k) { return k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }).join('|') + ')(?![A-Za-zÀ-ÿ])', 'g');
  }
  var reTL = null, reEN = null;   // built on first use

  // Put the source text's numbers back into the translation, in order.
  function withNums(tr, src) {
    var nums = norm(src).match(NUM);
    if (!nums) return tr;
    var i = 0;
    return tr.replace(NUM, function (m) { return i < nums.length ? nums[i++] : m; });
  }
  // Editors redraw on every keystroke, so remember recent answers.
  var cache = { en: new Map(), fil: new Map() };
  function translate(s, target) {
    target = target || current;
    if (s == null) return s;
    s = String(s);
    if (!/[A-Za-zÀ-ÿ]{2}/.test(s)) return s;
    var memo = cache[target === 'fil' ? 'fil' : 'en'];
    var got = memo.get(s);
    if (got !== undefined) return got;
    var out = translateNow(s, target);
    if (memo.size > 6000) memo.clear();
    memo.set(s, out);
    return out;
  }
  function translateNow(s, target) {
    var map = target === 'fil' ? toTL : toEN;
    var hit = map.get(key(s));
    if (hit != null) {
      var lead = s.match(/^\s*/)[0], trail = s.match(/\s*$/)[0];
      return lead + withNums(hit, s) + trail;
    }
    if (target === 'fil') { if (reTL === null) reTL = phraseRe(toTL) || false; }
    else if (reEN === null) reEN = phraseRe(toEN) || false;
    var re = target === 'fil' ? reTL : reEN;
    if (!re || s.length < 10 || s.length > 4000) return s;
    return s.replace(re, function (m, pre, phrase) {
      var t = map.get(phrase);
      return t != null ? pre + t : m;
    });
  }

  /* ── the page ───────────────────────────────────────────────── */
  var ATTRS = ['placeholder', 'title', 'aria-label', 'alt'];
  var textOrig = new WeakMap(), textMine = new WeakMap();   // node → original / what we wrote
  var attrOrig = new WeakMap();                              // el → {attr: [original, written]}
  var SKIP_TAGS = { SCRIPT: 1, STYLE: 1, TEXTAREA: 1, NOSCRIPT: 1, CODE: 1, PRE: 1, svg: 1, SVG: 1 };
  function skipped(el) {
    for (var e = el; e && e.nodeType === 1; e = e.parentNode) {
      if (SKIP_TAGS[e.tagName] || e.isContentEditable || e.hasAttribute('data-no-i18n')) return true;
    }
    return false;
  }
  function doText(node) {
    var now = node.data;
    var orig = textOrig.get(node);
    if (orig === undefined || now !== textMine.get(node)) { orig = now; textOrig.set(node, orig); }
    var out = current === 'en' && !toEN.size ? orig : translate(orig);
    textMine.set(node, out);
    if (out !== now) node.data = out;
  }
  function doAttrs(el) {
    var rec = attrOrig.get(el);
    for (var i = 0; i < ATTRS.length; i++) {
      var a = ATTRS[i], now = el.getAttribute(a);
      if (now == null || now === '') continue;
      if (!rec) { rec = {}; attrOrig.set(el, rec); }
      var r = rec[a];
      if (!r || now !== r[1]) r = rec[a] = [now, now];
      var out = translate(r[0]);
      r[1] = out;
      if (out !== now) el.setAttribute(a, out);
    }
    if (el.tagName === 'INPUT' && (el.type === 'button' || el.type === 'submit') && el.value) {
      if (!rec) { rec = {}; attrOrig.set(el, rec); }
      var v = rec.value;
      if (!v || el.value !== v[1]) v = rec.value = [el.value, el.value];
      v[1] = translate(v[0]);
      if (v[1] !== el.value) el.value = v[1];
    }
  }
  function walk(root) {
    if (!root) return;
    if (root.nodeType === 3) { if (root.parentNode && !skipped(root.parentNode)) doText(root); return; }
    if (root.nodeType !== 1 && root.nodeType !== 9 && root.nodeType !== 11) return;
    if (root.nodeType === 1 && skipped(root)) return;
    if (root.nodeType === 1) doAttrs(root);
    var tw = document.createTreeWalker(root, 5 /* element | text */, {
      acceptNode: function (n) {
        if (n.nodeType === 1) return SKIP_TAGS[n.tagName] || n.isContentEditable || n.hasAttribute('data-no-i18n') ? 2 /* reject subtree */ : 1;
        return /[A-Za-zÀ-ÿ]/.test(n.data) ? 1 : 3;
      }
    });
    var n;
    while ((n = tw.nextNode())) { if (n.nodeType === 3) doText(n); else doAttrs(n); }
  }
  var busy = false;
  function apply() {
    busy = true;
    try { walk(document.documentElement); document.title = translate(titleOrig()); } finally { busy = false; }
    paintToggle();
  }
  var _title = null, _titleMine = null;
  function titleOrig() {
    if (_title === null || document.title !== _titleMine) _title = document.title;
    _titleMine = translate(_title);
    return _title;
  }

  var mo = new MutationObserver(function (records) {
    if (busy) return;
    busy = true;
    try {
      for (var i = 0; i < records.length; i++) {
        var r = records[i];
        if (r.type === 'childList') for (var j = 0; j < r.addedNodes.length; j++) walk(r.addedNodes[j]);
        else if (r.type === 'characterData') { if (r.target.parentNode && !skipped(r.target.parentNode)) doText(r.target); }
        else if (r.type === 'attributes' && r.target.nodeType === 1 && !skipped(r.target)) doAttrs(r.target);
      }
    } finally { busy = false; }
  });
  mo.observe(document.documentElement, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ATTRS.concat(['value']) });

  // Native pop-ups.
  ['alert', 'confirm', 'prompt'].forEach(function (fn) {
    var orig = window[fn];
    if (typeof orig !== 'function') return;
    window[fn] = function (msg) {
      var args = Array.prototype.slice.call(arguments);
      if (args.length) args[0] = translate(msg);
      return orig.apply(window, args);
    };
  });

  /* ── 🌐 EN | TL button ──────────────────────────────────────── */
  var toggle = null;
  function wantToggle() {
    var q = new URLSearchParams(location.search);
    // Learners in an activity use the activity's own ⚙ Language setting.
    return q.get('student_mode') !== '1' && !document.body.className.match(/-preview-only\b/);
  }
  function paintToggle() {
    if (!toggle) return;
    var b = toggle.querySelectorAll('button');
    b[0].classList.toggle('on', current === 'en');
    b[1].classList.toggle('on', current === 'fil');
    toggle.title = current === 'fil' ? 'Wika ng system' : 'System language';
  }
  function mountToggle() {
    if (toggle || !document.body || !wantToggle()) return;
    var st = document.createElement('style');
    st.textContent = '#spedLang{position:fixed;right:14px;bottom:14px;z-index:99990;display:flex;align-items:center;gap:2px;padding:4px;border-radius:999px;' +
      'background:rgba(255,255,255,.92);border:1px solid #dbe3ef;box-shadow:0 6px 18px rgba(15,23,42,.14);font:700 12px/1 Poppins,system-ui,sans-serif;backdrop-filter:blur(8px);}' +
      '#spedLang span{padding:0 6px 0 4px;font-size:14px;}' +
      '#spedLang button{border:0;background:transparent;color:#475569;font:inherit;padding:7px 10px;border-radius:999px;cursor:pointer;}' +
      '#spedLang button.on{background:#1e3a8a;color:#fff;}' +
      '#spedLang button:focus-visible{outline:2px solid #60a5fa;outline-offset:1px;}' +
      '@media print{#spedLang{display:none!important;}}';
    document.head.appendChild(st);
    toggle = document.createElement('div');
    toggle.id = 'spedLang';
    toggle.setAttribute('data-no-i18n', '');
    toggle.setAttribute('role', 'group');
    toggle.innerHTML = '<span aria-hidden="true">🌐</span><button type="button" data-l="en">EN</button><button type="button" data-l="fil">TL</button>';
    toggle.addEventListener('click', function (e) {
      var b = e.target.closest('button');
      if (b) setLang(b.getAttribute('data-l'));
    });
    document.body.appendChild(toggle);
    paintToggle();
  }

  function setLang(l) {
    l = l === 'fil' ? 'fil' : 'en';
    if (window.ActivityAudio && ActivityAudio.setLang) ActivityAudio.setLang(l);   // also saves + fires 'activitylang'
    else {
      try { localStorage.setItem(KEY, l); } catch (e) {}
      try { window.dispatchEvent(new CustomEvent('activitylang', { detail: l })); } catch (e) {}
    }
    changed(l);
  }
  // The Image Library's photo names follow too (it has its own switch for
  // a one-off choice inside the library).
  function syncLibrary() { try { localStorage.setItem('imglib_lang', current); } catch (e) {} }
  function changed(l) {
    l = l === 'fil' ? 'fil' : 'en';
    if (l === current) { paintToggle(); return; }
    current = l;
    syncLibrary();
    document.documentElement.lang = l === 'fil' ? 'fil' : 'en';
    apply();
  }
  window.addEventListener('activitylang', function (e) { changed(e.detail); });
  window.addEventListener('storage', function (e) { if (e.key === KEY) changed(e.newValue); });   // other tabs

  document.documentElement.lang = current === 'fil' ? 'fil' : 'en';
  function start() { apply(); mountToggle(); }
  syncLibrary();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();

  window.SpedI18n = { lang: function () { return current; }, setLang: setLang, t: function (s) { return translate(s); } };
})();
