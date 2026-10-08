/* "How to Answer" demos for Math_Template.html — one per Activity Type
 * (Multiple Choice / Ten-Frame Count / Put in Order / Solve the Equation).
 * Drawn on ActivityDemo's 320×180 canvas; image paths are relative to
 * TEACHER_FILES/TEMPLATES/ (where the template runs). */
(function () {
  if (!window.ActivityDemo) return;

  var KEY_ON = 'background:#22c55e;color:#fff;border-color:#16a34a;box-shadow:0 4px 0 #15803d;';
  var KEY_OFF = 'background:#fff;color:#1e3a8a;border-color:#e2e8f0;box-shadow:0 4px 0 #cbd5e1;';

  // Answer keys 2 / 3 / 4 (x 40, 98, 156; y 96). The right one, 3, is
  // tapped at 36%, then "✓ Check answers" on the right at 52%.
  function answerRow(k) {
    return '<div class="ad-key" style="left:40px;top:96px;">2</div>' +
      '<div class="ad-key" style="left:98px;top:96px;' + k.pick(36, KEY_ON, KEY_OFF) + '">3</div>' +
      '<div class="ad-key" style="left:156px;top:96px;">4</div>' +
      k.ripple(124, 122, 36) +
      k.check(212, 107, 102, 52) +
      k.badge(148, 98, 54, 88);
  }
  // The hand: `intro` keyframes (counting or reading), then tap 3, then Check.
  function hand(k, intro) {
    return k.hand('0%{left:200px;top:200px;opacity:0;}' + intro +
      '33%{left:124px;top:116px;opacity:1;}36%{left:124px;top:123px;}39%{left:124px;top:116px;}' +
      '49%{left:263px;top:116px;}52%{left:263px;top:123px;}55%{left:263px;top:116px;opacity:1;}' +
      '63%,100%{left:305px;top:200px;opacity:0;}');
  }
  // Count bubbles (1, 2, 3) that pop next to each counted thing.
  function countCss(k) {
    k.css('.' + k.name('cnt') + '{position:absolute;z-index:3;width:22px;height:22px;margin:-11px 0 0 -11px;border-radius:50%;background:#6366f1;color:#fff;' +
      'font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;border:2px solid #fff;opacity:0;}');
  }
  function count(k, x, y, n, t) {
    return '<div class="' + k.name('cnt') + '" style="left:' + x + 'px;top:' + y + 'px;' + k.appear(t) + '">' + n + '</div>';
  }
  // Point at three things in a row at 8%, 15%, 22%.
  function countHand(xs, y) {
    return xs.map(function (x, i) {
      var t = 8 + i * 7;
      return (t - 2) + '%{left:' + x + 'px;top:' + y + 'px;opacity:1;}' + t + '%{left:' + x + 'px;top:' + (y + 4) + 'px;}' + (t + 2) + '%{left:' + x + 'px;top:' + y + 'px;}';
    }).join('');
  }
  function pickSteps(first) {
    return [
      { icon: '🔢', text: first, from: 4, to: 27 },
      { icon: '👆', text: 'Tap the answer', from: 29, to: 45 },
      { icon: '✅', text: 'Tap Check', from: 47, to: 90 }
    ];
  }

  ActivityDemo.register('math', {
    /* Multiple Choice — count the pictures, tap the number. */
    mc: function (k) {
      countCss(k);
      var html =
        k.tile(52, 20, 44, 44, 'DEMO_ASSETS/ball.jpg') +
        k.tile(102, 20, 44, 44, 'DEMO_ASSETS/ball.jpg') +
        k.tile(152, 20, 44, 44, 'DEMO_ASSETS/ball.jpg') +
        count(k, 92, 22, 1, 8) + count(k, 142, 22, 2, 15) + count(k, 192, 22, 3, 22) +
        answerRow(k) +
        hand(k, countHand([74, 124, 174], 44));
      return { duration: 5.2, say: 'Count the pictures, then tap the right number!', steps: pickSteps('Count the pictures'), stage: k.stage(html) };
    },

    /* Ten-Frame Count — count the filled dots, tap the number. */
    tenframe: function (k) {
      countCss(k);
      k.css('.' + k.name('cell') + '{position:absolute;width:22px;height:22px;border-radius:5px;background:#fff;border:2px solid #93c5fd;}' +
        '.' + k.name('dot') + '{position:absolute;left:3px;top:3px;width:12px;height:12px;border-radius:50%;background:#3b82f6;}');
      var cells = '';
      for (var i = 0; i < 10; i++) {
        cells += '<div class="' + k.name('cell') + '" style="left:' + (63 + (i % 5) * 25) + 'px;top:' + (24 + Math.floor(i / 5) * 25) + 'px;">' +
          (i < 3 ? '<div class="' + k.name('dot') + '"></div>' : '') + '</div>';
      }
      var html = cells +
        count(k, 84, 20, 1, 8) + count(k, 109, 20, 2, 15) + count(k, 134, 20, 3, 22) +
        answerRow(k) +
        hand(k, countHand([74, 99, 124], 38));
      return { duration: 5.2, say: 'Count the dots in the ten-frame, then tap the answer!', steps: pickSteps('Count the dots'), stage: k.stage(html) };
    },

    /* Solve the Equation — read it, tap the answer; the ? becomes 3. */
    equation: function (k) {
      k.css('.' + k.name('eq') + '{position:absolute;left:40px;top:20px;width:168px;height:46px;border-radius:14px;background:#fff;border:2px solid #e2e8f0;' +
          'box-shadow:0 4px 0 #cbd5e1;font-size:26px;font-weight:800;color:#1e3a8a;display:flex;align-items:center;justify-content:center;gap:6px;}' +
        '.' + k.name('q') + '{position:relative;display:inline-block;width:26px;height:34px;}' +
        '.' + k.name('q') + ' i,.' + k.name('q') + ' b{position:absolute;inset:0;font-style:normal;display:flex;align-items:center;justify-content:center;}' +
        '.' + k.name('q') + ' b{color:#16a34a;opacity:0;}');
      var html =
        '<div class="' + k.name('eq') + '">2 + 1 = <span class="' + k.name('q') + '">' +
          '<i style="' + k.kf('hide', '0%,36.5%{opacity:1;}37%,88%{opacity:0;}92%,100%{opacity:1;}') + '">?</i>' +
          '<b style="' + k.appear(37) + '">3</b></span></div>' +
        answerRow(k) +
        hand(k, '6%{left:70px;top:66px;opacity:1;}24%{left:180px;top:66px;opacity:1;}');
      return { duration: 5.2, say: 'Solve the problem, then tap the right answer!', steps: pickSteps('Read the problem'), stage: k.stage(html) };
    },

    /* Put in Order — drag 8 to the end so the row reads 2, 5, 8. */
    order: function (k) {
      var html =
        '<div class="ad-key" style="' + k.move(76, 50, 192, 50, 14, 32) + '">8</div>' +
        '<div class="ad-key" style="' + k.move(134, 50, 76, 50, 18, 30) + '">2</div>' +
        '<div class="ad-key" style="' + k.move(192, 50, 134, 50, 18, 30) + '">5</div>' +
        '<div class="ad-label" style="left:76px;top:114px;width:168px;">smallest ➜ biggest</div>' +
        k.ripple(102, 76, 12) +
        k.check(110, 140, 100, 50) +
        k.badge(128, 52, 52, 88) + k.badge(186, 52, 54, 88) + k.badge(244, 52, 56, 88) +
        k.hand('0%{left:200px;top:200px;opacity:0;}8%{left:102px;top:70px;opacity:1;}12%{left:102px;top:78px;}14%{left:102px;top:78px;}' +
          '32%{left:218px;top:78px;}35%{left:218px;top:70px;}' +
          '46%{left:160px;top:148px;}50%{left:160px;top:155px;}53%{left:160px;top:148px;opacity:1;}62%,100%{left:300px;top:200px;opacity:0;}');
      return {
        duration: 5.2,
        say: 'Drag the numbers into order, from smallest to biggest!',
        steps: [
          { icon: '✋', text: 'Drag a number', from: 6, to: 34 },
          { icon: '🔢', text: 'Smallest to biggest', from: 34, to: 46 },
          { icon: '✅', text: 'Tap Check', from: 46, to: 90 }
        ],
        stage: k.stage(html)
      };
    }
  });
  ActivityDemo.register('math', { 'default': 'mc' });
})();
