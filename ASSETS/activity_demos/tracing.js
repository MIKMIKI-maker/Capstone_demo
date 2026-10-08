/* "How to Answer" demos for Tracing_Template.html — one per Activity Type
 * (Letters & Numbers / Shapes & Lines). Drawn on ActivityDemo's 320×180
 * canvas.
 *
 * Every traced stroke is ONE list of points used three ways, so they can
 * never drift apart: the grey dashed guide, the ink that draws itself
 * behind the pointer (stroke-dashoffset), and the pointer's own keyframes
 * (one per point, linear, timed by distance = constant speed). */
(function () {
  if (!window.ActivityDemo) return;

  function r1(n) { return Math.round(n * 10) / 10; }
  function r2(n) { return Math.round(n * 100) / 100; }

  // Points on an ellipse arc, angles in degrees on screen (0 = right,
  // -90 = up); going from a0 DOWN to a1 runs counter-clockwise, the way a
  // "C" or "O" is written.
  function arc(cx, cy, rx, ry, a0, a1) {
    var n = Math.ceil(Math.abs(a1 - a0) / 6), pts = [];
    for (var i = 0; i <= n; i++) {
      var a = (a0 + (a1 - a0) * i / n) * Math.PI / 180;
      pts.push([r1(cx + rx * Math.cos(a)), r1(cy + ry * Math.sin(a))]);
    }
    return pts;
  }
  // Points on a chain of quadratic curves [[x0,y0],[cx,cy],[x1,y1]], ...
  function quads(segs) {
    var pts = [];
    segs.forEach(function (s, si) {
      for (var i = si ? 1 : 0; i <= 10; i++) {
        var t = i / 10, u = 1 - t;
        pts.push([r1(u * u * s[0][0] + 2 * u * t * s[1][0] + t * t * s[2][0]),
                  r1(u * u * s[0][1] + 2 * u * t * s[1][1] + t * t * s[2][1])]);
      }
    });
    return pts;
  }
  // Running distance along the points; the last entry is the full length.
  function dist(pts) {
    var out = [0];
    for (var i = 1; i < pts.length; i++) {
      out.push(out[i - 1] + Math.hypot(pts[i][0] - pts[i - 1][0], pts[i][1] - pts[i - 1][1]));
    }
    return out;
  }
  function pathD(pts) {
    return 'M' + pts.map(function (p) { return p[0] + ' ' + p[1]; }).join('L');
  }
  // Pointer keyframes that follow `pts` from t0% to t1% at an even speed.
  function along(pts, t0, t1) {
    var d = dist(pts), len = d[d.length - 1], out = '';
    pts.forEach(function (p, i) {
      out += r2(t0 + (t1 - t0) * d[i] / len) + '%{left:' + p[0] + 'px;top:' + p[1] + 'px;opacity:1;' +
        (i < pts.length - 1 ? 'animation-timing-function:linear;' : '') + '}';
    });
    return out;
  }
  // Ink that appears behind the pointer between t0% and t1% (same timing
  // as along()), stays until `hold`%, then fades so the loop restarts clean.
  function ink(k, id, pts, t0, t1, hold, style) {
    var d = dist(pts), len = Math.ceil(d[d.length - 1]) + 1;
    return '<path d="' + pathD(pts) + '" style="' + style + 'stroke-dasharray:' + len + ' ' + len + ';' + k.kf(id,
      '0%,' + r2(t0 - 0.1) + '%{stroke-dashoffset:' + len + 'px;opacity:0;}' +
      t0 + '%{stroke-dashoffset:' + len + 'px;opacity:1;animation-timing-function:linear;}' +
      t1 + '%,' + hold + '%{stroke-dashoffset:0px;opacity:1;}' +
      (hold + 4) + '%,100%{stroke-dashoffset:0px;opacity:0;}') + '"/>';
  }
  function at(p, dx, dy) { return 'left:' + r1(p[0] + (dx || 0)) + 'px;top:' + r1(p[1] + (dy || 0)) + 'px;'; }

  ActivityDemo.register('tracing', {
    /* Letters & Numbers — the real .trc-scard: label, a white box with a
     * dashed baseline + midline and the grey dashed "Cc", the finger lays
     * blue ink (the default pen colour) over the big C, then the small c,
     * then presses "✓ Check answers": the card turns green, "✓ Traced!". */
    letters: function (k) {
      k.css(
        '.' + k.name('card') + '{position:absolute;left:14px;top:24px;width:170px;height:146px;border-radius:18px;background:#fff;border:3px solid #e2c98f;' +
          'box-shadow:0 4px 0 rgba(15,23,42,.08),0 8px 18px rgba(15,23,42,.10);}' +
        '.' + k.name('lbl') + '{position:absolute;left:14px;top:30px;width:170px;text-align:center;font-size:13px;font-weight:700;color:#243a5e;line-height:16px;}' +
        '.' + k.name('box') + '{position:absolute;left:26px;top:49px;width:146px;height:92px;border-radius:12px;background:#fff;border:1.5px solid #e6ebf4;}' +
        '.' + k.name('st') + '{position:absolute;left:14px;top:146px;width:170px;text-align:center;font-size:13px;font-weight:700;color:#1f9d44;line-height:16px;opacity:0;}' +
        '.' + k.name('svg') + '{position:absolute;left:0;top:0;width:320px;height:180px;overflow:visible;z-index:3;pointer-events:none;}' +
        '.' + k.name('chk') + '{position:absolute;left:194px;top:79px;width:118px;height:36px;border-radius:12px;background:#2fbf57;color:#fff;' +
          'display:flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:700;white-space:nowrap;box-shadow:0 4px 0 #1f9d44;z-index:2;}');

      // Ruled box (box x 26–172, y 49–141): baseline at 72% like the real
      // canvas, midline at x-height. Big C 60px tall, small c 36px.
      var base = 116, mid = 80;
      var C = arc(87.6, 86, 26, 30, -48, -312);
      var c = arc(129.6, 98, 15.5, 18, -52, -308);
      var C0 = C[0], C1 = C[C.length - 1], c0 = c[0], c1 = c[c.length - 1];
      var BTN = [253, 104];   // fingertip on the lower half of the button
      var guide = 'fill:none;stroke:#94a3b8;stroke-width:4;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:1.5 7;';
      var pen = 'fill:none;stroke:#1b4fb8;stroke-width:7;stroke-linecap:round;stroke-linejoin:round;';
      var rule = 'stroke:#94a3b8;stroke-width:1.5;stroke-dasharray:6 4;';

      var idle = 'border-color:#e2c98f;box-shadow:0 4px 0 rgba(15,23,42,.08),0 8px 18px rgba(15,23,42,.10);';
      var ok = 'border-color:#22c55e;box-shadow:0 0 0 5px rgba(34,197,94,.30);';
      var html =
        '<div class="' + k.name('card') + '" style="' + k.kf('card', '0%,61.5%{' + idle + '}62.5%,88%{' + ok + '}92%,100%{' + idle + '}') + '"></div>' +
        '<div class="' + k.name('lbl') + '">Cc — Cat</div>' +
        '<div class="' + k.name('box') + '"></div>' +
        '<svg class="' + k.name('svg') + '" viewBox="0 0 320 180">' +
          '<line x1="33" y1="' + base + '" x2="165" y2="' + base + '" style="' + rule + '"/>' +
          '<line x1="33" y1="' + mid + '" x2="165" y2="' + mid + '" style="' + rule + '"/>' +
          '<path d="' + pathD(C) + '" style="' + guide + '"/>' +
          '<path d="' + pathD(c) + '" style="' + guide + '"/>' +
          ink(k, 'inkBig', C, 11, 34, 88, pen) +
          ink(k, 'inkSmall', c, 40.5, 52, 88, pen) +
        '</svg>' +
        '<div class="' + k.name('st') + '" style="' + k.kf('st',
          '0%,61.9%{opacity:0;transform:scale(.6);}62.5%{opacity:1;transform:scale(1.25);}66%,88%{opacity:1;transform:none;}' +
          '91%,100%{opacity:0;transform:none;}') + '">✓ Traced!</div>' +
        '<div class="' + k.name('chk') + '" style="' + k.kf('chk',
          '0%,58.5%{transform:none;box-shadow:0 4px 0 #1f9d44;}' +
          '59.5%,61%{transform:translateY(3px);box-shadow:0 1px 0 #1f9d44;}' +
          '62.5%,100%{transform:none;box-shadow:0 4px 0 #1f9d44;}') + '">✓ Check answers</div>' +
        k.ripple(C0[0], C0[1], 10) +
        k.ripple(c0[0], c0[1], 39.5) +
        k.ripple(BTN[0], 97, 59.5) +
        k.badge(182, 28, 63, 88) +
        k.hand(
          '0%{left:205px;top:200px;opacity:0;}' +
          '7%{' + at(C0, 0, -7) + 'opacity:1;}' +
          '9.5%{' + at(C0) + 'opacity:1;}' +
          along(C, 11, 34) +
          '37%{' + at(c0, -4, -8) + 'opacity:1;}' +
          '39%{' + at(c0) + 'opacity:1;}' +
          along(c, 40.5, 52) +
          '57%{' + at(BTN) + 'opacity:1;}' +
          '59.5%{' + at(BTN, 0, 5) + 'opacity:1;}' +
          '61.5%{' + at(BTN) + 'opacity:1;}' +
          '70%,100%{left:290px;top:192px;opacity:0;}');
      return {
        duration: 5.4,
        say: 'Use your finger to trace the dotted letter, then tap Check!',
        steps: [
          { icon: '🔠', text: 'Trace the big letter', from: 8, to: 35 },
          { icon: '🔡', text: 'Trace the small letter', from: 37, to: 53 },
          { icon: '✅', text: 'Tap Check', from: 55, to: 90 }
        ],
        stage: k.stage(html)
      };
    },

    /* Shapes & Lines — print-only in the real template (the student's
     * screen shows the dotted worksheet + "🖨️ For Printing Only"), so the
     * demo is the printed sheet: a pencil traces the dotted circle, then
     * the dotted wavy line, leaving a grey pencil line; a star for each. */
    shapes: function (k) {
      k.css(
        '.' + k.name('paper') + '{position:absolute;left:30px;top:24px;width:260px;height:146px;border-radius:8px;background:#fff;' +
          'box-shadow:0 0 0 1px #e2e8f0,0 4px 0 rgba(15,23,42,.06),0 10px 20px rgba(15,23,42,.10);}' +
        '.' + k.name('fold') + '{position:absolute;left:268px;top:24px;width:22px;height:22px;border-radius:0 8px 0 6px;' +
          'background:linear-gradient(225deg,#f8fafc 0 50%,#e2e8f0 50% 100%);box-shadow:-2px 2px 3px rgba(15,23,42,.10);}' +
        '.' + k.name('tag') + '{position:absolute;left:42px;top:32px;padding:2px 8px;border-radius:8px;background:#fff7e6;border:1.5px solid #f5c563;' +
          'color:#92620a;font-size:12px;font-weight:700;line-height:16px;white-space:nowrap;}' +
        '.' + k.name('svg') + '{position:absolute;left:0;top:0;width:320px;height:180px;overflow:visible;z-index:3;pointer-events:none;}' +
        '.' + k.name('pen') + '{position:absolute;z-index:5;width:0;height:0;pointer-events:none;}' +
        '.' + k.name('pen') + ' svg{position:absolute;left:-6px;top:-46px;width:52px;height:52px;overflow:visible;filter:drop-shadow(0 4px 3px rgba(15,23,42,.28));}');

      // One ruled worksheet line (solid top, dashed middle, solid base),
      // shapes standing on the baseline like the printed sheet.
      var top = 62, mid = 117, base = 150;
      var O = arc(110, 106, 33, 33, -90, -450);           // circle, from the top, counter-clockwise
      var S = quads([[[172, 106], [192, 80], [212, 106]], [[212, 106], [232, 132], [252, 106]]]); // wavy line, left → right
      var O0 = O[0], S0 = S[0], S1 = S[S.length - 1];
      var dots = 'fill:none;stroke:#94a3b8;stroke-width:4.5;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:0.1 8.5;';
      var lead = 'fill:none;stroke:#475569;stroke-width:4;stroke-linecap:round;stroke-linejoin:round;';
      var html =
        '<div class="' + k.name('paper') + '"></div>' +
        '<div class="' + k.name('fold') + '"></div>' +
        '<div class="' + k.name('tag') + '">🖨️ For Printing Only</div>' +
        '<svg class="' + k.name('svg') + '" viewBox="0 0 320 180">' +
          '<line x1="44" y1="' + top + '" x2="276" y2="' + top + '" style="stroke:#94a3b8;stroke-width:1.5;"/>' +
          '<line x1="44" y1="' + mid + '" x2="276" y2="' + mid + '" style="stroke:#94a3b8;stroke-width:1.5;stroke-dasharray:6 4;"/>' +
          '<line x1="44" y1="' + base + '" x2="276" y2="' + base + '" style="stroke:#94a3b8;stroke-width:1.5;"/>' +
          '<path d="' + pathD(O) + '" style="' + dots + '"/>' +
          '<path d="' + pathD(S) + '" style="' + dots + '"/>' +
          ink(k, 'leadO', O, 11, 40, 88, lead) +
          ink(k, 'leadS', S, 46, 63, 88, lead) +
        '</svg>' +
        k.badge(140, 76, 41, 88, 'star') +
        k.badge(258, 92, 64, 88, 'star') +
        // The pencil: an SVG drawn with its tip at (0,0), so left/top of the
        // wrapper is exactly where the lead touches the paper.
        '<div class="' + k.name('pen') + '" style="' + k.kf('pen',
          '0%{left:300px;top:206px;opacity:0;}' +
          '8%{' + at(O0, 0, -7) + 'opacity:1;}' +
          '10%{' + at(O0) + 'opacity:1;}' +
          along(O, 11, 40) +
          '43%{' + at(S0, 0, -8) + 'opacity:1;}' +
          '45%{' + at(S0) + 'opacity:1;}' +
          along(S, 46, 63) +
          '66%{' + at(S1, 8, -10) + 'opacity:1;}' +
          '74%,100%{left:300px;top:206px;opacity:0;}') + '">' +
          '<svg viewBox="-6 -46 52 52"><g transform="rotate(-45)">' +
            '<polygon points="0,0 12,-5.5 12,5.5" fill="#f3d2a2"/>' +
            '<polygon points="0,0 4.6,-2.1 4.6,2.1" fill="#334155"/>' +
            '<rect x="12" y="-5.5" width="30" height="11" fill="#fbbf24"/>' +
            '<rect x="12" y="-5.5" width="30" height="3.6" fill="#fcd34d"/>' +
            '<rect x="12" y="1.9" width="30" height="3.6" fill="#f59e0b"/>' +
            '<rect x="42" y="-5.5" width="5" height="11" fill="#cbd5e1"/>' +
            '<path d="M47 -5.5h3.5a5.5 5.5 0 0 1 0 11H47z" fill="#f472b6"/>' +
          '</g></svg></div>';
      return {
        duration: 5.4,
        say: 'Use a pencil to trace the dotted shape and line on the paper!',
        steps: [
          { icon: '⭕', text: 'Trace the shape', from: 8, to: 41 },
          { icon: '〰️', text: 'Trace the line', from: 43, to: 64 },
          { icon: '🌟', text: 'Great job! All done!', from: 65, to: 90 }
        ],
        stage: k.stage(html)
      };
    }
  });
  // Slides saved before Activity Types existed have no mode = letters.
  ActivityDemo.register('tracing', { 'default': 'letters' });
})();
