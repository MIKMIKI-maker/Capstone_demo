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
 * ───────────────────────────────────────────────────────────── */
(function () {
  if (window.AttemptHistory) return;

  var hist = {}; // slideIdx -> [ {correct,total}, ... ] indexed by attempt number

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
      (hist[si] = hist[si] || [])[attemptIdx || 0] = { correct: score.correct, total: score.total };
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
          attempts: list(si).map(function (a) { return { correct: a.correct, total: a.total, percent: pct(a) }; })
        });
      }
      return JSON.stringify(out);
    }
  };
})();
