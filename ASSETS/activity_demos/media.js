/* "How to Answer" demos for Media_Template.html — one per Activity Type
 * (Watch & Answer / Listen & Answer).
 * Drawn on ActivityDemo's 320×180 canvas; image paths are relative to
 * TEACHER_FILES/TEMPLATES/ (where the template runs). */
(function () {
  if (!window.ActivityDemo) return;

  var ON = 'border-color:#22c55e;box-shadow:0 0 0 3px #bbf7d0,0 4px 0 rgba(15,23,42,.10);';
  var OFF = 'border-color:#fff;';

  // Three picture choices; the dog (left) is tapped at 36%, then Check at 52%.
  function choices(k) {
    return k.tile(30, 100, 54, 54, 'DEMO_ASSETS/dog.jpg', k.pick(36, ON, OFF)) +
      k.tile(92, 100, 54, 54, 'DEMO_ASSETS/car.jpg') +
      k.tile(154, 100, 54, 54, 'DEMO_ASSETS/fish.jpg') +
      k.ripple(57, 127, 36) +
      k.check(216, 112, 100, 52) +
      k.badge(80, 102, 54, 88);
  }
  // The hand: press the play / sound button (x, y) at 12%, then the dog, then Check.
  function hand(k, x, y) {
    return k.hand('0%{left:200px;top:200px;opacity:0;}' +
      '8%{left:' + x + 'px;top:' + y + 'px;opacity:1;}12%{left:' + x + 'px;top:' + (y + 7) + 'px;}15%{left:' + x + 'px;top:' + y + 'px;}' +
      '33%{left:57px;top:122px;opacity:1;}36%{left:57px;top:129px;}39%{left:57px;top:122px;}' +
      '49%{left:266px;top:121px;}52%{left:266px;top:128px;}55%{left:266px;top:121px;opacity:1;}' +
      '63%,100%{left:305px;top:200px;opacity:0;}');
  }
  function steps(first, icon) {
    return [
      { icon: icon, text: first, from: 4, to: 30 },
      { icon: '👆', text: 'Tap the right picture', from: 31, to: 45 },
      { icon: '✅', text: 'Tap Check', from: 47, to: 90 }
    ];
  }
  function show(k, t) {   // visible from t% until the loop resets
    return k.kf('sh' + t, '0%,' + (t - 0.5) + '%{opacity:0;}' + t + '%,88%{opacity:1;}92%,100%{opacity:0;}');
  }
  function hide(k, t) {
    return k.kf('hd' + t, '0%,' + (t - 0.5) + '%{opacity:1;}' + t + '%,88%{opacity:0;}92%,100%{opacity:1;}');
  }

  ActivityDemo.register('media', {
    /* Watch & Answer — play the video (a dog), then tap the dog. */
    video: function (k) {
      k.css('.' + k.name('scr') + '{position:absolute;left:96px;top:8px;width:128px;height:80px;border-radius:12px;background:#0f172a;overflow:hidden;' +
          'border:3px solid #fff;box-shadow:0 4px 0 rgba(15,23,42,.15);}' +
        '.' + k.name('scr') + ' img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}' +
        '.' + k.name('play') + '{position:absolute;left:44px;top:20px;width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.92);' +
          'color:#1e3a8a;font-size:15px;display:flex;align-items:center;justify-content:center;}' +
        '.' + k.name('bar') + '{position:absolute;left:0;bottom:0;height:4px;background:#ef4444;width:100%;transform-origin:left;}');
      var html =
        '<div class="' + k.name('scr') + '">' +
          '<img src="DEMO_ASSETS/dog.jpg" alt="" style="' + show(k, 13) + '">' +
          '<div class="' + k.name('play') + '" style="' + hide(k, 13) + '">▶</div>' +
          '<div class="' + k.name('bar') + '" style="' + k.kf('bar', '0%,13%{transform:scaleX(0);}30%,88%{transform:scaleX(1);}92%,100%{transform:scaleX(0);}', 'linear') + '"></div>' +
        '</div>' +
        k.ripple(160, 45, 12) +
        choices(k) +
        hand(k, 160, 40);
      return { duration: 5.6, say: 'Watch the video, then tap the right picture!', steps: steps('Tap ▶ to watch', '🎬'), stage: k.stage(html) };
    },

    /* Listen & Answer — press 🔊 (a bark), then tap the dog. */
    audio: function (k) {
      k.css('.' + k.name('spk') + '{position:absolute;left:134px;top:14px;width:56px;height:56px;border-radius:50%;' +
          'background:linear-gradient(180deg,#60a5fa,#2563eb);box-shadow:0 4px 0 #1d4ed8;color:#fff;font-size:26px;' +
          'display:flex;align-items:center;justify-content:center;}' +
        '.' + k.name('wave') + '{position:absolute;font-size:17px;font-weight:800;color:#2563eb;opacity:0;}');
      var wave = function (x, y, t) {
        return '<div class="' + k.name('wave') + '" style="left:' + x + 'px;top:' + y + 'px;' +
          k.kf('wv' + t, '0%,' + (t - 0.5) + '%{opacity:0;transform:translateY(4px);}' + t + '%{opacity:1;}' + (t + 8) + '%{opacity:0;transform:translateY(-10px);}100%{opacity:0;}') + '">♪</div>';
      };
      var html =
        '<div class="' + k.name('spk') + '" style="' + k.kf('pulse', '0%,12%{transform:none;}16%{transform:scale(1.08);}20%{transform:none;}24%{transform:scale(1.08);}28%,100%{transform:none;}') + '">🔊</div>' +
        wave(198, 22, 14) + wave(210, 36, 19) + wave(112, 26, 24) +
        '<div class="ad-label" style="left:110px;top:76px;width:104px;' + show(k, 14) + '">“Woof! Woof!”</div>' +
        k.ripple(162, 42, 12) +
        choices(k) +
        hand(k, 162, 40);
      return { duration: 5.6, say: 'Press the sound button and listen, then tap the picture that made the sound!', steps: steps('Tap 🔊 to listen', '🔊'), stage: k.stage(html) };
    }
  });
  ActivityDemo.register('media', { 'default': 'video' });
})();
