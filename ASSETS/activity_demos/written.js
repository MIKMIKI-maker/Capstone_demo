/* "How to Answer" demos for Written_Response_Template.html — one per
 * Activity Type (Written Response, typing a whole word or just the first
 * letter / Word Scramble / Build the Sentence). Drawn on ActivityDemo's
 * 320×180 canvas; image paths are relative to TEACHER_FILES/TEMPLATES/. */
(function () {
  if (!window.ActivityDemo) return;

  // Written Response: tap the box, type `word` letter by letter, Check.
  function typing(k, word, say, typeStep) {
    k.css('.' + k.name('box') + '{position:absolute;left:150px;top:50px;width:154px;height:42px;border-radius:12px;background:#fff;border:2px solid #93c5fd;}' +
      '.' + k.name('ltr') + '{position:absolute;top:56px;width:18px;text-align:center;font-family:monospace;font-size:22px;font-weight:800;color:#1e3a8a;opacity:0;}' +
      '.' + k.name('caret') + '{position:absolute;top:58px;width:2px;height:26px;background:#1e3a8a;}');
    var letters = '', caret = '0%,13.5%{left:160px;}';
    word.split('').forEach(function (c, i) {
      var t = 14 + i * 10;
      letters += '<div class="' + k.name('ltr') + '" style="left:' + (160 + i * 18) + 'px;' + k.appear(t) + '">' + c + '</div>';
      caret += t + '%,' + (t + 9.5) + '%{left:' + (178 + i * 18) + 'px;}';
    });
    var html =
      k.tile(26, 30, 110, 110, 'DEMO_ASSETS/dog.jpg') +
      '<div class="' + k.name('box') + '"></div>' + letters +
      '<div class="' + k.name('caret') + '" style="left:160px;' + k.kf('caret', caret + '88%,100%{left:160px;}', 'step-end') + '"></div>' +
      k.ripple(282, 74, 8) +
      k.check(166, 112, 124, 52) +
      k.badge(300, 52, 54, 88) +
      k.hand('0%{left:282px;top:200px;opacity:0;}6%{left:282px;top:70px;opacity:1;}8%{left:282px;top:76px;}10%{left:282px;top:70px;}' +
        '13%{left:282px;top:75px;}15%{left:282px;top:71px;}23%{left:282px;top:75px;}25%{left:282px;top:71px;}33%{left:282px;top:75px;}35%{left:282px;top:71px;}' +
        '48%{left:228px;top:121px;}52%{left:228px;top:128px;}55%{left:228px;top:121px;opacity:1;}63%,100%{left:300px;top:200px;opacity:0;}');
    return {
      duration: 5.2,
      say: say,
      steps: [
        { icon: '👀', text: 'Look at the picture', from: 2, to: 11 },
        { icon: '⌨️', text: typeStep, from: 12, to: 40 },
        { icon: '✅', text: 'Tap Check', from: 44, to: 90 }
      ],
      stage: k.stage(html)
    };
  }

  // Word Scramble / Build the Sentence: tap tiles in order; each tapped
  // tile turns blue and gets its number (1, 2, 3), like the real
  // .wrt-tile.tapped + .wrt-tile-badge. tiles: [{ x, w, text, n }].
  function tapOrder(k, tiles, extra, say, steps) {
    var ON = 'border-color:#2f6fed;background:#eaf2ff;';
    var OFF = 'border-color:#e2e8f0;background:#fff;';
    k.css('.' + k.name('tile') + '{position:absolute;top:30px;height:52px;border-radius:14px;background:#fff;border:3px solid #e2e8f0;box-shadow:0 4px 0 #cbd5e1;' +
        'font-size:20px;font-weight:800;color:#1e3a8a;display:flex;align-items:center;justify-content:center;}' +
      '.' + k.name('num') + '{position:absolute;top:-13px;right:-10px;width:24px;height:24px;border-radius:50%;background:#2f6fed;color:#fff;border:2px solid #fff;' +
        'font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;opacity:0;}');
    var html = extra, handKf = '0%{left:240px;top:200px;opacity:0;}';
    tiles.forEach(function (t) {
      var at = 12 * t.n, cx = t.x + t.w / 2;
      html += '<div class="' + k.name('tile') + '" style="left:' + t.x + 'px;width:' + t.w + 'px;' + k.pick(at, ON, OFF) + '">' + t.text +
          '<div class="' + k.name('num') + '" style="' + k.appear(at) + '">' + t.n + '</div></div>' +
        k.ripple(cx, 56, at) +
        k.badge(t.x + t.w - 4, 80, 52 + t.n * 2, 88);
    });
    tiles.slice().sort(function (a, b) { return a.n - b.n; }).forEach(function (t) {
      var at = 12 * t.n, cx = t.x + t.w / 2;
      handKf += (at - 3) + '%{left:' + cx + 'px;top:52px;opacity:1;}' + at + '%{left:' + cx + 'px;top:58px;}' + (at + 3) + '%{left:' + cx + 'px;top:52px;}';
    });
    handKf += '47%{left:160px;top:119px;}50%{left:160px;top:126px;}53%{left:160px;top:119px;opacity:1;}62%,100%{left:300px;top:200px;opacity:0;}';
    html += k.check(108, 110, 104, 50) + k.hand(handKf);
    return { duration: 5.2, say: say, steps: steps, stage: k.stage(html) };
  }

  ActivityDemo.register('written', {
    written: function (k) {
      return typing(k, 'DOG', 'Look at the picture, then type your answer in the box!', 'Type the answer');
    },
    // Written Response set to "first letter only".
    letter: function (k) {
      return typing(k, 'D', 'Look at the picture, then type the first letter of its name!', 'Type the first letter');
    },
    scramble: function (k) {
      return tapOrder(k,
        [{ x: 92, w: 52, text: 'O', n: 2 }, { x: 150, w: 52, text: 'G', n: 3 }, { x: 208, w: 52, text: 'D', n: 1 }],
        k.tile(14, 30, 64, 52, 'DEMO_ASSETS/dog.jpg'),
        'Tap the letters in the right order to spell the word!',
        [
          { icon: '👆', text: 'Tap the first letter', from: 4, to: 17 },
          { icon: '🔤', text: 'Tap the next letters', from: 18, to: 41 },
          { icon: '✅', text: 'Tap Check', from: 43, to: 90 }
        ]);
    },
    sentence: function (k) {
      return tapOrder(k,
        [{ x: 24, w: 80, text: 'runs.', n: 3 }, { x: 120, w: 80, text: 'The', n: 1 }, { x: 216, w: 80, text: 'dog', n: 2 }],
        '',
        'Tap the words in the right order to make a sentence!',
        [
          { icon: '👆', text: 'Tap the first word', from: 4, to: 17 },
          { icon: '📝', text: 'Tap the next words', from: 18, to: 41 },
          { icon: '✅', text: 'Tap Check', from: 43, to: 90 }
        ]);
    }
  });
  ActivityDemo.register('written', { 'default': 'written' });
})();
