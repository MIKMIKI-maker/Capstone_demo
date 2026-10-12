/* ─────────────────────────────────────────────────────────────
 * Attempt History — shared by every activity template.
 *
 * Retaking a slide used to wipe its previous score, so the student only
 * ever saw their latest try. This keeps every attempt's score per slide
 * for the current session and renders them as a row of chips
 * ("Try 1: 2/5 → Try 2: 3/5 → Try 3: 5/5") inside the score modal, so the
 * student (and later the teacher) can see whether they're improving.
 *
 *   AttemptHistory.record(slideIdx, attemptIdx, {correct, total})
 *   AttemptHistory.show(anchorEl, slideIdx)          // per-check score modal
 *   AttemptHistory.showSummary(anchorEl, slideCount) // final submit summary
 *   AttemptHistory.toJSON(slideCount)                // sent with the submission
 *
 * Every record() also takes a picture of the activity area (the
 * template's #xxxStage) as the student left it, and uploads it right away
 * to student_save_snapshot.php under this page's run id. toJSON() carries
 * the run id and each try's number, which is how the teacher's review
 * finds the pictures and shows every take side by side.
 * ───────────────────────────────────────────────────────────── */
(function () {
  if (window.AttemptHistory) return;

  var hist = {}; // slideIdx -> [ {correct,total,n}, ... ] indexed by attempt number

  // One id per time the activity is opened; pictures are stored under it.
  var RUN = (Date.now().toString(36) + Math.random().toString(36).slice(2)).replace(/[^a-z0-9]/g, '').slice(0, 24);
  var ASSETS_BASE = ((document.currentScript && document.currentScript.src) || '').replace(/[^\/]*$/, '');
  var SNAP_URL = ASSETS_BASE + '../STUDENT_FILES/STUDENT_BACKEND/student_save_snapshot.php';
  var SNAP_MAX_W = 960;

  // The activity area: every template renders it into #xxxStage
  // (#seqStage, #chkStage, …).
  function stageEl() {
    var all = document.querySelectorAll('[id$="Stage"]');
    for (var i = 0; i < all.length; i++) {
      if (/^[a-z]{3}Stage$/.test(all[i].id) && all[i].offsetWidth) return all[i];
    }
    return null;
  }

  // Popups and floating buttons are not part of the student's answer.
  function skipEl(el) {
    if (!el || el.nodeType !== 1) return false;
    var cls = typeof el.className === 'string' ? el.className : '';
    if (/ModalOverlay|stuDemoOverlay|stuSplash/.test(el.id || '')) return true;
    if (/(^|\s)(aa-fab|aa-panel|ah-box)(\s|$)/.test(cls)) return true;
    try {
      var cs = window.getComputedStyle(el);
      if (cs.position === 'fixed' && (parseInt(cs.zIndex, 10) || 0) >= 1000) return true;
    } catch (e) {}
    return false;
  }

  // html2canvas clips an <svg> to its own box, but a line layer like
  // Matching's #mtgLines is a 300×150 (default-size) svg whose lines spill
  // outside it — the browser shows them, the picture lost them. In the copy
  // html2canvas draws, swap each such svg for an image of it sized to
  // everything it actually draws.
  function svgsToImages(el) {
    return function (doc) {
      var orig = el.querySelectorAll('svg'), copyRoot = doc.getElementById(el.id);
      if (!copyRoot) return;
      var copies = copyRoot.querySelectorAll('svg');
      for (var i = 0; i < orig.length && i < copies.length; i++) {
        if (orig[i].getAttribute('width')) continue;   // icons with a real size are fine
        var w = orig[i].clientWidth, h = orig[i].clientHeight, bb = null;
        try { bb = orig[i].getBBox(); } catch (e) {}
        if (bb && bb.width) { w = Math.max(w, Math.ceil(bb.x + bb.width) + 6); h = Math.max(h, Math.ceil(bb.y + bb.height) + 6); }
        if (!w || !h) continue;
        var svg = orig[i].cloneNode(true);
        svg.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        svg.setAttribute('width', w); svg.setAttribute('height', h);
        if (!svg.getAttribute('viewBox')) svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
        svg.removeAttribute('class'); svg.removeAttribute('style'); svg.setAttribute('overflow', 'visible');
        var img = doc.createElement('img');
        img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(new XMLSerializer().serializeToString(svg));
        img.className = copies[i].getAttribute('class') || '';
        img.style.cssText = (copies[i].getAttribute('style') || '') + ';width:' + w + 'px;height:' + h + 'px;max-width:none;';
        copies[i].parentNode.replaceChild(img, copies[i]);
      }
    };
  }

  // Picture of the activity right now → upload it as (slide, try).
  // html2canvas copies the page the moment it is called, so a quick
  // Retake right after can't change what gets saved.
  // html2canvas copies the whole page before drawing. Parts of <body>
  // outside the activity area that are hidden anyway (display:none — in
  // play mode that's the whole editor: panels, settings, other tools) are
  // left out of the copy: less work and memory on a low-end tablet. Hidden
  // parts take no space, so the picture's layout can't change. Visible
  // parts stay, since removing them would let the stage reflow.
  function skipOutside(stage) {
    return function (node) {
      if (skipEl(node)) return true;
      if (!node || node.nodeType !== 1 || node.tagName === 'STYLE' || node.tagName === 'LINK') return false;
      if (!document.body.contains(node) || node.contains(stage) || stage.contains(node)) return false;
      try { return window.getComputedStyle(node).display === 'none'; } catch (e) { return false; }
    };
  }

  // A slow phone shouldn't stack up captures: at most two at once; a
  // capture asked for while two are running is skipped (that try just has
  // no picture).
  var inFlight = 0, MAX_IN_FLIGHT = 2;
  var lowMemory = (navigator.deviceMemory && navigator.deviceMemory <= 2) || /Android [4-7]\./.test(navigator.userAgent);

  function upload(body, retriesLeft) {
    return fetch(SNAP_URL, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) {
      if (!r.ok && r.status >= 500 && retriesLeft > 0) throw new Error('retry');
      return r;
    }).catch(function (e) {
      if (retriesLeft > 0) return new Promise(function (res) { setTimeout(res, 3000); }).then(function () { return upload(body, retriesLeft - 1); });
      throw e;
    });
  }

  function snap(si, attemptIdx) {
    var el = stageEl();
    if (!el || !window.html2canvas || inFlight >= MAX_IN_FLIGHT) return;
    // Only the drawing is heavy for the device, so only it counts toward
    // the limit — uploads may run alongside.
    inFlight++;
    var released = false;
    var release = function () { if (!released) { released = true; inFlight = Math.max(0, inFlight - 1); } };
    try {
      var w = el.scrollWidth || el.offsetWidth || 1;
      var maxW = lowMemory ? 640 : SNAP_MAX_W;
      window.html2canvas(el, {
        scale: Math.min(1.5, maxW / w), useCORS: true, logging: false, imageTimeout: 5000,
        backgroundColor: '#ffffff', ignoreElements: skipOutside(el), onclone: svgsToImages(el)
      }).then(function (canvas) {
        var data = canvas.toDataURL('image/jpeg', lowMemory ? 0.6 : 0.72);
        canvas.width = canvas.height = 0;                          // free the bitmap right away
        release();
        var body = new URLSearchParams({ run_id: RUN, slide: String(si + 1), attempt: String(attemptIdx || 0), image: data });
        return upload(body, 1);
      }).catch(release);                                           // a missing picture never blocks the activity
    } catch (e) { release(); }
  }

  function injectStyles() {
    if (document.getElementById('ahStyles')) return;
    var st = document.createElement('style');
    st.id = 'ahStyles';
    st.textContent =
      '.ah-box{margin:6px 0 8px;padding:10px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;text-align:left;font-family:inherit;}' +
      '.ah-title{font-size:12px;font-weight:700;color:#1e3a8a;margin-bottom:6px;}' +
      '.ah-row{display:flex;flex-wrap:wrap;align-items:center;gap:4px;margin-bottom:4px;}' +
      '.ah-row:last-child{margin-bottom:0;}' +
      '.ah-slide{font-size:11px;font-weight:700;color:#475569;margin-right:4px;min-width:52px;}' +
      '.ah-chip{display:inline-flex;flex-direction:column;align-items:center;padding:3px 9px;border-radius:10px;background:#fff;border:1px solid #cbd5e1;line-height:1.15;}' +
      '.ah-chip b{font-size:13px;color:#0f172a;}' +
      '.ah-chip small{font-size:9.5px;color:#64748b;font-weight:600;}' +
      '.ah-chip.ah-pass{border-color:#86efac;background:#f0fdf4;}' +
      '.ah-chip.ah-now{border-width:2px;border-color:#2563eb;background:#eff6ff;}' +
      '.ah-chip.ah-now small{color:#1d4ed8;}' +
      '.ah-arrow{font-size:12px;color:#94a3b8;}' +
      '.ah-trend{font-size:12px;font-weight:700;margin-top:6px;}' +
      '.ah-up{color:#15803d;}.ah-same{color:#a16207;}.ah-down{color:#b91c1c;}';
    document.head.appendChild(st);
  }

  function list(si) {
    return (hist[si] || []).filter(Boolean);
  }

  function pct(a) {
    return a.total ? Math.round((a.correct / a.total) * 100) : 0;
  }

  // The box lives right before the given anchor (the template's
  // RetakeInfo line) so it sits under the item-by-item results.
  function getBox(anchor) {
    if (!anchor || !anchor.parentNode) return null;
    var prev = anchor.previousElementSibling;
    if (prev && prev.classList && prev.classList.contains('ah-box')) return prev;
    var box = document.createElement('div');
    box.className = 'ah-box';
    box.style.display = 'none';
    anchor.parentNode.insertBefore(box, anchor);
    return box;
  }

  function rowHTML(arr, slideLabel) {
    var h = '<div class="ah-row">';
    if (slideLabel) h += '<span class="ah-slide">' + slideLabel + '</span>';
    arr.forEach(function (a, i) {
      var isNow = i === arr.length - 1;
      var cls = 'ah-chip' + (isNow ? ' ah-now' : '') + (pct(a) >= 80 ? ' ah-pass' : '');
      if (i > 0) h += '<span class="ah-arrow">→</span>';
      h += '<span class="' + cls + '"><small>' + (isNow ? 'Now' : 'Try ' + (i + 1)) + '</small>' +
           '<b>' + a.correct + '/' + a.total + '</b></span>';
    });
    return h + '</div>';
  }

  function trendHTML(arr) {
    if (arr.length < 2) return '';
    var last = arr[arr.length - 1], before = arr[arr.length - 2];
    var diff = last.correct - before.correct;
    if (diff > 0) return '<div class="ah-trend ah-up">📈 Improved! +' + diff + ' more correct than your last try.</div>';
    if (diff === 0) return '<div class="ah-trend ah-same">➖ Same as your last try — kaya mo \'yan!</div>';
    return '<div class="ah-trend ah-down">💪 ' + Math.abs(diff) + ' fewer than last try. Try again — you can do it!</div>';
  }

  window.AttemptHistory = {
    record: function (si, attemptIdx, score) {
      if (!score || !score.total) return;
      (hist[si] = hist[si] || [])[attemptIdx || 0] = { correct: score.correct, total: score.total, n: attemptIdx || 0 };
      snap(si, attemptIdx || 0);
    },

    // Only worth showing once there's something to compare against.
    show: function (anchor, si) {
      var box = getBox(anchor);
      if (!box) return;
      var arr = list(si);
      if (arr.length < 2) { box.style.display = 'none'; box.innerHTML = ''; return; }
      injectStyles();
      box.innerHTML = '<div class="ah-title">📊 Your scores per try</div>' + rowHTML(arr) + trendHTML(arr);
      box.style.display = '';
    },

    showSummary: function (anchor, slideCount) {
      var box = getBox(anchor);
      if (!box) return;
      var rows = '';
      for (var si = 0; si < slideCount; si++) {
        var arr = list(si);
        if (arr.length < 2) continue;
        rows += rowHTML(arr, slideCount > 1 ? 'Slide ' + (si + 1) : '');
        if (slideCount === 1) rows += trendHTML([arr[0], arr[arr.length - 1]]).replace('your last try', 'your first try');
      }
      if (!rows) { box.style.display = 'none'; box.innerHTML = ''; return; }
      injectStyles();
      box.innerHTML = '<div class="ah-title">📊 Your scores per try</div>' + rows;
      box.style.display = '';
    },

    toJSON: function (slideCount) {
      var out = [];
      for (var si = 0; si < slideCount; si++) {
        out.push({
          slide: si + 1,
          run: RUN,
          attempts: list(si).map(function (a) { return { correct: a.correct, total: a.total, percent: pct(a), n: a.n }; })
        });
      }
      return JSON.stringify(out);
    }
  };
})();
