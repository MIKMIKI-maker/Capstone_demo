/*
 * Printable options shared by every activity template (Generate Printable /
 * Download PDF). Before printing, the teacher picks who the worksheet is for:
 *   - Blank: one set with "Name: ____  Date: ____" lines to write on.
 *   - Learners: one set per chosen learner, each headed by that learner's
 *     full name to trace (dashed pen-path letters like the Tracing
 *     template, or faded) on handwriting lines.
 *
 * A template wires it up in two places:
 *   button click  → PrintOptions.ask(generatePrintable)        (or downloadAsPdf)
 *   page building → PrintOptions.build(buildPrintPages, "mtg")  (instead of buildPrintPages())
 * where "mtg" is the template's print class prefix (mtg-print-page, ...).
 */
(function () {
  var script = document.currentScript;
  var learnersUrl = new URL('../TEACHER_FILES/TEACHER_BACKEND/teacher_list_learners.php', script.src).href;
  var STORE_KEY = 'spedalm_print_options_v2';

  // Last choices, kept between prints on this device.
  var config = {
    mode: 'blank',          // 'blank' | 'learners'
    learnerIds: [],
    trace: true,
    style: 'trace',         // 'trace' (dashed pen path) | 'fade'
    traceRows: 0,           // extra practice page: rows with the name to trace (0-6)
    writeRows: 0,           // extra practice page: blank rows to write it (0-4)
  };
  try {
    var saved = JSON.parse(localStorage.getItem(STORE_KEY) || 'null');
    if (saved) Object.keys(config).forEach(function (k) { if (k in saved) config[k] = saved[k]; });
    if (config.style !== 'fade') config.style = 'trace';
    // Older saved choice from the single "Rows" option.
    if (saved && saved.repeat && !('traceRows' in saved)) {
      config.traceRows = 0;
      config.writeRows = 0;
    }
  } catch (e) {}
  var learners = [];   // [{id, name}] chosen for the current print

  function esc(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  var css = ''
    + '.pro-overlay{position:fixed;inset:0;background:rgba(15,23,42,.55);display:flex;align-items:flex-start;justify-content:center;z-index:100000;overflow-y:auto;padding:28px 14px;font-family:Poppins,sans-serif;}'
    + '.pro-box{background:#fff;border-radius:18px;max-width:560px;width:100%;padding:22px 24px;color:#1e293b;box-shadow:0 20px 50px rgba(15,23,42,.25);}'
    + '.pro-box h2{font-size:18px;margin:0 0 4px;}'
    + '.pro-hint{font-size:12.5px;color:#64748b;margin:0 0 12px;line-height:1.5;}'
    + '.pro-sect{font-size:13px;font-weight:700;margin:16px 0 8px;color:#1e3a8a;}'
    + '.pro-choice{display:flex;align-items:flex-start;gap:8px;font-size:13.5px;padding:7px 0;cursor:pointer;}'
    + '.pro-choice input{margin-top:3px;}'
    + '.pro-list{border:1.5px solid #e2e8f0;border-radius:12px;max-height:220px;overflow-y:auto;padding:4px 12px;margin:4px 0 0 24px;}'
    + '.pro-list label{display:flex;align-items:center;gap:8px;font-size:13px;padding:6px 0;border-bottom:1px solid #f1f5f9;cursor:pointer;}'
    + '.pro-list label:last-child{border-bottom:none;}'
    + '.pro-list .pro-all{font-weight:700;}'
    + '.pro-grid{display:grid;grid-template-columns:110px 1fr;gap:8px 12px;align-items:center;font-size:13px;margin-left:24px;}'
    + '.pro-grid select{padding:7px 9px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:13px;background:#fff;}'
    + '.pro-off{opacity:.45;pointer-events:none;}'
    + '.pro-foot{display:flex;justify-content:flex-end;gap:10px;margin-top:20px;}'
    + '.pro-btn{padding:10px 18px;border-radius:10px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;border:1.5px solid #e2e8f0;background:#fff;color:#334155;}'
    + '.pro-btn--go{background:#1e3a8a;border-color:#1e3a8a;color:#fff;}'
    + '.pro-btn--go:disabled{background:#94a3b8;border-color:#94a3b8;cursor:not-allowed;}'
    + '.pro-preview{margin:10px 0 0 24px;border:1px dashed #cbd5e1;border-radius:10px;padding:6px 10px;background:#f8fafc;overflow:hidden;}'
    + '@media (max-width:520px){.pro-grid{grid-template-columns:1fr;margin-left:0;}.pro-list,.pro-preview{margin-left:0;}}';
  var style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  var TRACE_FONT = "'Andika', sans-serif";

  // Zhang-Suen thinning: reduces filled letters to one-pixel center lines
  // (same algorithm as the Tracing template's _trcThinBinary).
  function thin(bin, w, h) {
    function at(x, y) { return (x < 0 || x >= w || y < 0 || y >= h) ? 0 : bin[y * w + x]; }
    var changed = true;
    while (changed) {
      changed = false;
      for (var pass = 0; pass < 2; pass++) {
        var toClear = [];
        for (var y = 0; y < h; y++) {
          for (var x = 0; x < w; x++) {
            if (!at(x, y)) continue;
            var p2 = at(x, y - 1), p3 = at(x + 1, y - 1), p4 = at(x + 1, y), p5 = at(x + 1, y + 1),
                p6 = at(x, y + 1), p7 = at(x - 1, y + 1), p8 = at(x - 1, y), p9 = at(x - 1, y - 1);
            var n = [p2, p3, p4, p5, p6, p7, p8, p9];
            var B = n[0] + n[1] + n[2] + n[3] + n[4] + n[5] + n[6] + n[7];
            if (B < 2 || B > 6) continue;
            var A = 0;
            for (var i = 0; i < 8; i++) if (n[i] === 0 && n[(i + 1) % 8] === 1) A++;
            if (A !== 1) continue;
            if (pass === 0) { if (p2 * p4 * p6 !== 0 || p4 * p6 * p8 !== 0) continue; }
            else { if (p2 * p4 * p8 !== 0 || p2 * p6 * p8 !== 0) continue; }
            toClear.push(y * w + x);
          }
        }
        if (toClear.length) { for (var k = 0; k < toClear.length; k++) bin[toClear[k]] = 0; changed = true; }
      }
    }
  }

  /*
   * The name as dashed center-line strokes to trace with a pencil — the same
   * look as the Tracing template's letters, not an outlined font. The text is
   * drawn on a hidden canvas, thinned to its pen path, and the path is walked
   * so the dashes follow each stroke. Returns SVG <line>s with the text's
   * left edge at x and its baseline at baseY.
   */
  var skeletonCache = {};
  if (document.fonts && document.fonts.load) document.fonts.load('400 80px Andika').then(function () { skeletonCache = {}; });
  function traceSkeletonSvg(text, size, x, baseY) {
    var key = text + '|' + size;
    var sk = skeletonCache[key];
    if (!sk) {
      var pad = Math.ceil(size * 0.4);
      var font = '400 ' + size + 'px ' + TRACE_FONT;
      var probe = document.createElement('canvas').getContext('2d');
      probe.font = font;
      var offW = Math.ceil(probe.measureText(text).width) + pad * 2;
      var offH = Math.ceil(size * 1.5) + pad * 2;
      var off = document.createElement('canvas');
      off.width = offW; off.height = offH;
      var ctx = off.getContext('2d', { willReadFrequently: true });
      var baseOff = pad + size * 1.05;
      ctx.font = font;
      ctx.textBaseline = 'alphabetic';
      ctx.fillStyle = '#000';
      ctx.fillText(text, pad, baseOff);
      var data = ctx.getImageData(0, 0, offW, offH).data;
      var bin = new Uint8Array(offW * offH);
      var minX = offW;
      for (var i = 0; i < offW * offH; i++) {
        if (data[i * 4 + 3] > 100) { bin[i] = 1; if (i % offW < minX) minX = i % offW; }
      }
      thin(bin, offW, offH);
      var dashOn = size * 0.16, period = dashOn + size * 0.08;
      var visited = new Uint8Array(offW * offH), segments = [];
      var dirs = [[1, 0], [1, 1], [0, 1], [-1, 1], [-1, 0], [-1, -1], [0, -1], [1, -1]];
      for (var sy = 0; sy < offH; sy++) {
        for (var sx = 0; sx < offW; sx++) {
          if (!bin[sy * offW + sx] || visited[sy * offW + sx]) continue;
          var queue = [[sx, sy, 0]], qi = 0;
          visited[sy * offW + sx] = 1;
          while (qi < queue.length) {
            var cur = queue[qi++];
            for (var d = 0; d < 8; d++) {
              var nx = cur[0] + dirs[d][0], ny = cur[1] + dirs[d][1];
              if (nx < 0 || ny < 0 || nx >= offW || ny >= offH || !bin[ny * offW + nx] || visited[ny * offW + nx]) continue;
              visited[ny * offW + nx] = 1;
              queue.push([nx, ny, cur[2] + (dirs[d][0] && dirs[d][1] ? 1.4142 : 1)]);
              if ((cur[2] % period) < dashOn) segments.push([cur[0], cur[1], nx, ny]);
            }
          }
          // A tiny piece (the dot on i / j) thins down to a point with no
          // stroke to dash — draw it as a single round dot instead.
          if (queue.length < 4) segments.push([sx, sy, sx, sy]);
        }
      }
      sk = skeletonCache[key] = { segments: segments, left: minX === offW ? 0 : minX, baseOff: baseOff };
    }
    var dx = x - sk.left, dy = baseY - sk.baseOff;
    var out = '<g stroke="#64748b" stroke-width="' + Math.max(2, size * 0.045).toFixed(2) + '" stroke-linecap="round">';
    sk.segments.forEach(function (s) {
      out += '<line x1="' + (s[0] + dx).toFixed(1) + '" y1="' + (s[1] + dy).toFixed(1) + '" x2="' + (s[2] + dx).toFixed(1) + '" y2="' + (s[3] + dy).toFixed(1) + '"/>';
    });
    return out + '</g>';
  }

  // One handwriting row: solid top line, dashed midline, solid baseline,
  // with the learner's full name to trace (or faded), or empty to write on.
  // Kept short (about 65px tall on an A4 page) so the worksheet below still fits.
  function handwritingRow(text) {
    var w = 1000, h = 84, top = 10, base = 70, mid = (top + base) / 2;
    var letters = '';
    if (text) {
      // Capitals reach the top line; a long full name shrinks to fit the row.
      var size = 80;
      var probe = document.createElement('canvas').getContext('2d');
      probe.font = '400 ' + size + 'px ' + TRACE_FONT;
      var textW = probe.measureText(text).width;
      if (textW > w - 50) size = Math.floor(size * (w - 50) / textW);
      letters = config.style === 'fade'
        ? '<text x="20" y="' + base + '" font-family="Andika, sans-serif" font-size="' + size + '" fill="#cbd5e1">' + esc(text) + '</text>'
        : traceSkeletonSvg(text, size, 20, base);
    }
    return '<svg viewBox="0 0 ' + w + ' ' + h + '" style="width:100%;height:auto;display:block;" xmlns="http://www.w3.org/2000/svg">'
      + '<line x1="8" y1="' + top + '" x2="' + (w - 8) + '" y2="' + top + '" stroke="#94a3b8" stroke-width="2"/>'
      + '<line x1="8" y1="' + mid + '" x2="' + (w - 8) + '" y2="' + mid + '" stroke="#94a3b8" stroke-width="1.6" stroke-dasharray="8 5"/>'
      + '<line x1="8" y1="' + base + '" x2="' + (w - 8) + '" y2="' + base + '" stroke="#94a3b8" stroke-width="2"/>'
      + letters + '</svg>';
  }

  function dateLine() {
    return '<span style="display:flex;align-items:center;gap:8px;">Date: <span style="display:inline-block;width:170px;border-bottom:1.5px solid #64748b;height:14px;"></span></span>';
  }

  // The line at the top of a worksheet page: just the Date (the learner's
  // name goes on the title's ruled lines instead — see nameOnTitleLines).
  // With tracing turned off, the name is printed here plainly.
  function headerStrip(learner) {
    var plainName = learner && !config.trace ? '<span>Name: <b>' + esc(learner.name) + '</b></span>' : '<span></span>';
    return '<div class="pro-print-header" style="font-family:Poppins,sans-serif;color:#243a5e;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;gap:24px;font-size:14px;font-weight:600;">'
      + plainName + dateLine() + '</div>';
  }

  /*
   * Every template draws three ruled lines under the worksheet title (a
   * "-print-divider": solid / dashed / solid, 65px tall). That is the name
   * line: the learner's full name is traced right on it, capitals reaching
   * the top line and letters sitting on the bottom one.
   */
  var DIVIDER_H = 65, DIVIDER_TOP = 1.5, DIVIDER_BASE = 63.5, NAME_MAX_W = 690;
  function nameOnTitleLines(name) {
    var probe = document.createElement('canvas').getContext('2d');
    probe.font = '400 100px ' + TRACE_FONT;
    var capH = probe.measureText('H').actualBoundingBoxAscent || 70;
    var size = Math.floor(100 * (DIVIDER_BASE - DIVIDER_TOP - 2) / capH);
    probe.font = '400 ' + size + 'px ' + TRACE_FONT;
    var textW = probe.measureText(name).width;
    if (textW > NAME_MAX_W) size = Math.floor(size * NAME_MAX_W / textW);
    var x = 24;
    var letters = config.style === 'fade'
      ? '<text x="' + x + '" y="' + DIVIDER_BASE + '" font-family="Andika, sans-serif" font-size="' + size + '" fill="#cbd5e1">' + esc(name) + '</text>'
      : traceSkeletonSvg(name, size, x, DIVIDER_BASE);
    return '<svg class="pro-title-name" style="position:absolute;left:0;top:0;width:100%;height:' + DIVIDER_H + 'px;overflow:visible;" xmlns="http://www.w3.org/2000/svg">' + letters + '</svg>';
  }

  // Optional extra page of name practice before the learner's worksheet.
  function usesPracticePage() {
    return config.mode === 'learners' && config.trace && (Number(config.traceRows) + Number(config.writeRows)) > 0;
  }

  function practiceRows(learner) {
    var name = String(learner.name || '').trim();
    var rows = '';
    for (var i = 0; i < Number(config.traceRows); i++) rows += handwritingRow(name);
    for (var j = 0; j < Number(config.writeRows); j++) rows += handwritingRow('');
    return rows;
  }

  function practicePage(learner, prefix) {
    var gap = '<div style="height:14px;"></div>';
    var rows = practiceRows(learner).replace(/<\/svg>/g, '</svg>' + gap);
    return '<div class="' + prefix + '-print-page" style="font-family:Poppins,sans-serif;color:#243a5e;">'
      + '<div style="display:flex;justify-content:flex-end;font-size:14px;font-weight:600;margin-bottom:6px;">' + dateLine() + '</div>'
      + '<h2 style="text-align:center;font-size:21px;font-weight:700;margin:6px 0 4px;">Bakatin ang iyong pangalan</h2>'
      + '<div style="text-align:center;font-size:13.5px;font-style:italic;color:#5d7299;font-weight:600;margin-bottom:18px;">(Trace your name with a pencil, then write it on your own.)</div>'
      + rows + '</div>';
  }

  /*
   * Builds the print pages for the chosen learners (or one blank set) by
   * calling the template's own buildPrintPages once per learner and adding
   * the header strip. Every set starts on a new sheet.
   */
  function build(buildPages, prefix) {
    var pageSel = '.' + prefix + '-print-page';
    var sets = config.mode === 'learners' && learners.length ? learners : [null];
    var holder = document.createElement('div');
    sets.forEach(function (learner) {
      var tmp = document.createElement('div');
      tmp.innerHTML = buildPages();
      var pages = tmp.querySelectorAll(pageSel);
      if (learner && usesPracticePage() && pages.length) pages[0].insertAdjacentHTML('beforebegin', practicePage(learner, prefix));
      Array.prototype.forEach.call(pages, function (page, i) {
        // Every page gets the Date and the learner's name, so loose sheets
        // never lose whose they are.
        page.insertAdjacentHTML('afterbegin', headerStrip(learner));
        var divider = page.querySelector('.' + prefix + '-print-divider');
        if (learner && config.trace && divider) {
          divider.style.position = 'relative';
          divider.insertAdjacentHTML('beforeend', nameOnTitleLines(String(learner.name || '').trim()));
        }
      });
      while (tmp.firstChild) holder.appendChild(tmp.firstChild);
    });
    var all = holder.querySelectorAll(pageSel);
    Array.prototype.forEach.call(all, function (page, i) {
      if (i < all.length - 1) {
        page.classList.remove(prefix + '-print-page--last');
        page.style.pageBreakAfter = 'always';
        page.style.breakAfter = 'page';
      }
    });
    return holder.innerHTML;
  }

  /*
   * Download PDF renders each page at a fixed A4 height. If the name strip
   * pushed a page's content past that, let the page grow so nothing is cut
   * off — the PDF step then scales the taller image down to fit the sheet.
   * Call after the template adds its "-pdf-capture" class.
   */
  function fit(area, prefix) {
    area.querySelectorAll('.' + prefix + '-print-page').forEach(function (page) {
      if (page.scrollHeight > page.clientHeight + 1) page.style.height = page.scrollHeight + 'px';
    });
  }

  // Drawn at the real worksheet width, then scaled down to fit the dialog
  // (see fitPreview) so the preview shows exactly what prints.
  var PREVIEW_W = 740;
  function previewTitleLines(learner) {
    var rule = '<div style="border-top:3px solid #94a3b8;"></div>';
    return '<div class="pro-preview-scale" style="width:' + PREVIEW_W + 'px;transform-origin:left top;display:flex;flex-direction:column;gap:28px;position:relative;margin:4px 0;">'
      + rule + '<div style="border-top:3px dashed #94a3b8;"></div>' + rule
      + nameOnTitleLines(String(learner.name || '').trim()) + '</div>';
  }

  function fitPreview(box) {
    var inner = box.querySelector('.pro-preview-scale');
    if (!inner) return;
    var scale = Math.min(1, (box.clientWidth - 20) / PREVIEW_W);
    inner.style.transform = 'scale(' + scale + ')';
    box.style.height = Math.ceil((DIVIDER_H + 12) * scale + 12) + 'px';
  }

  function save() {
    try { localStorage.setItem(STORE_KEY, JSON.stringify(config)); } catch (e) {}
  }

  /* Shows the options, then runs `go` (the template's print or PDF function). */
  function ask(go) {
    var el = document.createElement('div');
    el.className = 'pro-overlay';
    el.innerHTML = '<div class="pro-box" role="dialog" aria-modal="true" aria-label="Printable options">'
      + '<h2>Printable options</h2>'
      + '<p class="pro-hint">Choose who this worksheet is for.</p>'
      + '<label class="pro-choice"><input type="radio" name="pro-mode" value="blank"> <span><b>Blank</b> — one copy with Name and Date lines to write on</span></label>'
      + '<label class="pro-choice"><input type="radio" name="pro-mode" value="learners"> <span><b>Choose learners</b> — one copy per learner, with their name</span></label>'
      + '<div class="pro-list" id="pro-list"><div style="font-size:12.5px;color:#94a3b8;padding:8px 0;">Loading learners…</div></div>'
      + '<div id="pro-trace-wrap">'
      +   '<div class="pro-sect">Trace Name</div>'
      +   '<label class="pro-choice"><input type="checkbox" id="pro-trace"> <span>Let each learner trace their full name on the lines under the title of every page</span></label>'
      +   '<div class="pro-grid" id="pro-trace-opts">'
      +     '<span>Letters</span><select id="pro-style"><option value="trace">Trace lines (like Tracing)</option><option value="fade">Light gray (fade)</option></select>'
      +     '<span>Practice page</span><select id="pro-traceRows">' + [0, 1, 2, 3, 4, 5, 6].map(function (n) { return '<option value="' + n + '">' + (n ? n + (n === 1 ? ' row' : ' rows') + ' to trace' : 'None') + '</option>'; }).join('') + '</select>'
      +     '<span>+ Write rows</span><select id="pro-writeRows">' + [0, 1, 2, 3, 4].map(function (n) { return '<option value="' + n + '">' + (n ? n + (n === 1 ? ' blank row' : ' blank rows') + ' to write' : 'None') + '</option>'; }).join('') + '</select>'
      +   '</div>'
      +   '<div class="pro-hint" id="pro-rows-note" style="margin:8px 0 0 24px;"></div>'
      +   '<div class="pro-preview" id="pro-preview"></div>'
      + '</div>'
      + '<div class="pro-foot"><button type="button" class="pro-btn" id="pro-cancel">Cancel</button><button type="button" class="pro-btn pro-btn--go" id="pro-go">Continue</button></div>'
      + '</div>';
    document.body.appendChild(el);

    var $ = function (id) { return el.querySelector('#' + id); };
    var roster = [];

    function refresh() {
      var mode = el.querySelector('input[name="pro-mode"]:checked').value;
      config.mode = mode;
      config.trace = $('pro-trace').checked;
      ['style', 'traceRows', 'writeRows'].forEach(function (k) { config[k] = $('pro-' + k).value; });
      config.traceRows = Number(config.traceRows);
      config.writeRows = Number(config.writeRows);
      config.learnerIds = Array.prototype.map.call(el.querySelectorAll('.pro-learner:checked'), function (c) { return Number(c.value); });

      $('pro-list').classList.toggle('pro-off', mode !== 'learners');
      $('pro-trace-wrap').classList.toggle('pro-off', mode !== 'learners');
      $('pro-trace-opts').classList.toggle('pro-off', !config.trace);
      var picked = roster.filter(function (l) { return config.learnerIds.indexOf(l.id) !== -1; });
      var sample = picked[0] || roster[0];
      $('pro-rows-note').textContent = usesPracticePage()
        ? 'Each learner also gets a "Bakatin ang iyong pangalan" practice page before the worksheet.'
        : 'Optional: add a separate page for more name practice.';
      $('pro-preview').innerHTML = mode === 'learners' && config.trace && sample ? previewTitleLines(sample) : '';
      $('pro-preview').style.display = $('pro-preview').innerHTML ? '' : 'none';
      $('pro-preview').style.height = '';
      fitPreview($('pro-preview'));
      var all = el.querySelector('.pro-all input');
      if (all) all.checked = roster.length > 0 && picked.length === roster.length;
      $('pro-go').disabled = mode === 'learners' && !picked.length;
      $('pro-go').textContent = mode === 'learners' && picked.length ? 'Continue (' + picked.length + ' learner' + (picked.length > 1 ? 's' : '') + ')' : 'Continue';
    }

    el.querySelector('input[name="pro-mode"][value="' + config.mode + '"]').checked = true;
    $('pro-trace').checked = config.trace;
    ['style', 'traceRows', 'writeRows'].forEach(function (k) { $('pro-' + k).value = config[k]; });

    fetch(learnersUrl, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (list) {
        roster = (Array.isArray(list) ? list : []).map(function (s) { return { id: Number(s.id), name: s.name }; });
        if (!roster.length) {
          $('pro-list').innerHTML = '<div style="font-size:12.5px;color:#94a3b8;padding:8px 0;">No learners in your class yet.</div>';
        } else {
          var keep = config.learnerIds.filter(function (id) { return roster.some(function (l) { return l.id === id; }); });
          var pre = keep.length ? keep : roster.map(function (l) { return l.id; });
          $('pro-list').innerHTML = '<label class="pro-all"><input type="checkbox"> Select all (' + roster.length + ')</label>'
            + roster.map(function (l) {
              return '<label><input type="checkbox" class="pro-learner" value="' + l.id + '"' + (pre.indexOf(l.id) !== -1 ? ' checked' : '') + '> ' + esc(l.name) + '</label>';
            }).join('');
          el.querySelector('.pro-all input').addEventListener('change', function (e) {
            el.querySelectorAll('.pro-learner').forEach(function (c) { c.checked = e.target.checked; });
            refresh();
          });
        }
        refresh();
      })
      .catch(function () {
        $('pro-list').innerHTML = '<div style="font-size:12.5px;color:#94a3b8;padding:8px 0;">Could not load learners — you can still print a blank copy.</div>';
        refresh();
      });

    el.addEventListener('change', refresh);
    refresh();
    // Redraw the preview once the Andika font is in (the first draw may have
    // used a fallback font).
    if (document.fonts && document.fonts.load) {
      document.fonts.load('400 80px Andika').then(function () { skeletonCache = {}; if (el.isConnected) refresh(); });
    }

    var close = function () { el.remove(); document.removeEventListener('keydown', onKey); };
    var onKey = function (e) { if (e.key === 'Escape') close(); };
    document.addEventListener('keydown', onKey);
    $('pro-cancel').addEventListener('click', close);
    el.addEventListener('click', function (e) { if (e.target === el) close(); });
    $('pro-go').addEventListener('click', function () {
      learners = roster.filter(function (l) { return config.learnerIds.indexOf(l.id) !== -1; });
      save();
      close();
      // The trace letters are drawn from the Andika font on a canvas, so make
      // sure it has loaded first (falls through after 1.5s regardless).
      var fontReady = document.fonts && document.fonts.load ? document.fonts.load('400 80px Andika') : Promise.resolve();
      Promise.race([fontReady, new Promise(function (r) { setTimeout(r, 1500); })]).then(go, go);
    });
  }

  window.PrintOptions = { ask: ask, build: build, fit: fit };
})();
