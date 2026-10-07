/* "Paano Sagutan?" demos for Matching_Type_Template.html — one per
 * Activity Type. Drawn on ActivityDemo's 320×180 canvas; image paths are
 * relative to TEACHER_FILES/TEMPLATES/ (where the template runs). */
(function () {
  if (!window.ActivityDemo) return;

  function tile(x, y, size, src, style) {
    return '<div class="ad-tile" style="left:' + x + 'px;top:' + y + 'px;width:' + size + 'px;height:' + size + 'px;z-index:2;' + (style || '') + '">' +
      '<img src="' + src + '" alt=""></div>';
  }

  ActivityDemo.register('matching', {
    /* Pair Matching — press a picture, drag a line to its match, let go. */
    pair: function (k) {
      // Dog (80,54) → Dog house (240,128): 176px long at 24.8°.
      var line = 'position:absolute;left:80px;top:54px;height:6px;margin-top:-3px;border-radius:3px;transform-origin:0 50%;transform:rotate(24.82deg);z-index:1;';
      var html =
        tile(48, 22, 64, 'DEMO_ASSETS/dog.jpg', k.kf('dog',
          '0%,12%{border-color:#fff;transform:none;}' +
          '13%,22%{border-color:#3b82f6;transform:scale(1.07);}' +
          '26%,56%{border-color:#3b82f6;transform:none;}' +
          '59%,86%{border-color:#1e293b;transform:none;}' +
          '92%,100%{border-color:#fff;transform:none;}')) +
        tile(48, 96, 64, 'DEMO_ASSETS/fish.jpg') +
        tile(208, 22, 64, 'MATCHING/Sea.webp') +
        tile(208, 96, 64, 'DEMO_ASSETS/doghouse.jpg', k.kf('house',
          '0%,46%{border-color:#fff;box-shadow:0 4px 0 rgba(15,23,42,.10),0 8px 16px rgba(15,23,42,.10);}' +
          '52%{border-color:#93c5fd;box-shadow:0 0 0 6px rgba(59,130,246,.30);}' +
          '57%,86%{border-color:#1e293b;box-shadow:0 4px 0 rgba(15,23,42,.10),0 8px 16px rgba(15,23,42,.10);}' +
          '92%,100%{border-color:#fff;}')) +
        // The line being dragged (dashed, grows with the finger) …
        '<div style="' + line + 'width:0;background:repeating-linear-gradient(90deg,#334155 0 11px,transparent 11px 19px);' + k.kf('drag',
          '0%,22%{width:0;opacity:1;animation-timing-function:linear;}' +
          '55%{width:176px;opacity:1;}' +
          '58%,100%{width:176px;opacity:0;}') + '"></div>' +
        // … and the connected line once it's dropped on the match.
        '<div style="' + line + 'width:176px;background:#1e293b;opacity:0;' + k.kf('done',
          '0%,56%{opacity:0;}59%,86%{opacity:1;}92%,100%{opacity:0;}') + '"></div>' +
        k.ripple(80, 54, 13) +
        k.ripple(240, 128, 57) +
        k.badge(270, 98, 59, 86) +
        k.hand(
          '0%{left:170px;top:200px;opacity:0;}' +
          '10%{left:80px;top:58px;opacity:1;}' +
          '13%{left:80px;top:63px;}' +
          '16%,22%{left:80px;top:57px;animation-timing-function:linear;}' +
          '55%{left:240px;top:131px;}' +
          '58%{left:240px;top:136px;}' +
          '62%{left:240px;top:131px;opacity:1;}' +
          '72%,100%{left:276px;top:170px;opacity:0;}');
      return {
        duration: 4.8,
        say: 'Pindutin ang larawan at i-drag ang guhit papunta sa katugma nito!',
        steps: [
          { icon: '👆', text: 'Pindutin ang larawan', from: 8, to: 22 },
          { icon: '✏️', text: 'I-drag ang guhit', from: 22, to: 56 },
          { icon: '✅', text: 'Bitawan sa katugma', from: 56, to: 90 }
        ],
        stage: k.stage(html)
      };
    },

    /* Memory Match — flip two face-down cards to find a matching pair. */
    memory: function (k) {
      k.css(
        '.' + k.name('c') + '{position:absolute;top:59px;width:62px;height:62px;z-index:2;}' +
        '.' + k.name('f') + '{position:absolute;inset:0;border-radius:14px;display:flex;align-items:center;justify-content:center;border:3px solid #fff;' +
          'box-shadow:0 4px 0 rgba(15,23,42,.12),0 8px 16px rgba(15,23,42,.10);}' +
        '.' + k.name('back') + '{background:linear-gradient(135deg,#7b6ef0,#2f6fed);font-size:26px;}' +
        '.' + k.name('front') + '{background:#fff;opacity:0;}' +
        '.' + k.name('front') + ' img{width:78%;height:78%;object-fit:contain;}');
      // A card flips (squish to 0 width, swap faces, open again) at `t`%,
      // turns green when matched at `m`%, and flips back near the end.
      function card(x, src, t, m) {
        var flip = t == null ? '' : k.kf('flip' + x,
          '0%,' + t + '%{transform:scaleX(1);}' + (t + 4) + '%{transform:scaleX(0);}' + (t + 8) + '%,89%{transform:scaleX(1);}' +
          '93%{transform:scaleX(0);}97%,100%{transform:scaleX(1);}');
        var back = t == null ? '' : k.kf('back' + x, '0%,' + (t + 4) + '%{opacity:1;}' + (t + 4.1) + '%,93%{opacity:0;}93.1%,100%{opacity:1;}', 'step-end');
        var front = t == null ? '' : k.kf('front' + x,
          '0%,' + (t + 4) + '%{opacity:0;border-color:#fff;}' + (t + 4.1) + '%,' + (m - 0.5) + '%{opacity:1;border-color:#fff;}' +
          m + '%,89%{opacity:1;border-color:#22c55e;box-shadow:0 0 0 5px rgba(34,197,94,.30);}' +
          '93%{opacity:1;}93.1%,100%{opacity:0;}', 'step-end');
        return '<div class="' + k.name('c') + '" style="left:' + x + 'px;' + flip + '">' +
          '<div class="' + k.name('f') + ' ' + k.name('back') + '" style="' + back + '">❓</div>' +
          '<div class="' + k.name('f') + ' ' + k.name('front') + '" style="' + front + '"><img src="' + src + '" alt=""></div></div>';
      }
      var html =
        card(15, 'DEMO_ASSETS/dog.jpg', 14, 56) +
        card(91, 'DEMO_ASSETS/car.jpg') +
        card(167, 'DEMO_ASSETS/doghouse.jpg', 44, 56) +
        card(243, 'DEMO_ASSETS/ball.jpg') +
        k.ripple(46, 90, 14) +
        k.ripple(198, 90, 44) +
        k.badge(73, 61, 58, 88) +
        k.badge(225, 61, 58, 88) +
        k.hand(
          '0%{left:150px;top:200px;opacity:0;}' +
          '10%{left:46px;top:92px;opacity:1;}' +
          '13%{left:46px;top:97px;}' +
          '16%,30%{left:46px;top:91px;}' +
          '40%{left:198px;top:92px;}' +
          '43%{left:198px;top:97px;}' +
          '46%,58%{left:198px;top:91px;opacity:1;}' +
          '68%,100%{left:250px;top:165px;opacity:0;}');
      return {
        duration: 5.2,
        say: 'I-tap ang dalawang nakataob na card. Hanapin ang magkatugma!',
        steps: [
          { icon: '👆', text: 'I-tap ang isang card', from: 8, to: 30 },
          { icon: '👆', text: 'I-tap pa ang isa', from: 36, to: 55 },
          { icon: '🎉', text: 'Magkatugma? Panalo!', from: 56, to: 90 }
        ],
        stage: k.stage(html)
      };
    },

    /* Grid Match — every card is face-up; tap a picture, then its match. */
    grid: function (k) {
      k.css(
        '.' + k.name('g') + '{position:absolute;top:59px;width:62px;height:62px;z-index:2;border-radius:14px;background:#fff;border:3px solid #e2c98f;' +
          'display:flex;align-items:center;justify-content:center;box-shadow:0 4px 0 rgba(15,23,42,.10),0 8px 16px rgba(15,23,42,.08);}' +
        '.' + k.name('g') + ' img{width:78%;height:78%;object-fit:contain;}');
      function card(x, src, frames) {
        return '<div class="' + k.name('g') + '" style="left:' + x + 'px;' + (frames ? k.kf('g' + x, frames) : '') + '"><img src="' + src + '" alt=""></div>';
      }
      var sel = 'border-color:#2f6fed;box-shadow:0 0 0 5px rgba(47,111,237,.28);';
      var ok = 'border-color:#22c55e;box-shadow:0 0 0 5px rgba(34,197,94,.30);';
      var idle = 'border-color:#e2c98f;box-shadow:0 4px 0 rgba(15,23,42,.10),0 8px 16px rgba(15,23,42,.08);';
      var html =
        card(15, 'DEMO_ASSETS/dog.jpg', '0%,13%{' + idle + '}14%,49%{' + sel + '}50%,88%{' + ok + '}93%,100%{' + idle + '}') +
        card(91, 'DEMO_ASSETS/car.jpg') +
        card(167, 'DEMO_ASSETS/doghouse.jpg', '0%,43%{' + idle + '}44%,49%{' + sel + '}50%,88%{' + ok + '}93%,100%{' + idle + '}') +
        card(243, 'MATCHING/Highway.webp') +
        k.ripple(46, 90, 14) +
        k.ripple(198, 90, 44) +
        k.badge(73, 61, 52, 88) +
        k.badge(225, 61, 52, 88) +
        k.hand(
          '0%{left:150px;top:200px;opacity:0;}' +
          '10%{left:46px;top:92px;opacity:1;}' +
          '13%{left:46px;top:97px;}' +
          '16%,30%{left:46px;top:91px;}' +
          '40%{left:198px;top:92px;}' +
          '43%{left:198px;top:97px;}' +
          '46%,58%{left:198px;top:91px;opacity:1;}' +
          '68%,100%{left:250px;top:165px;opacity:0;}');
      return {
        duration: 4.8,
        say: 'I-tap ang larawan, tapos i-tap ang katugma nito!',
        steps: [
          { icon: '👆', text: 'I-tap ang larawan', from: 8, to: 30 },
          { icon: '👆', text: 'I-tap ang katugma', from: 36, to: 50 },
          { icon: '✅', text: 'Magkapareha na!', from: 50, to: 90 }
        ],
        stage: k.stage(html)
      };
    }
  });
  // Older slides saved before Activity Types existed have no mode.
  ActivityDemo.register('matching', { 'default': 'pair' });
})();
