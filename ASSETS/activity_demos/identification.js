/* "Paano Sagutan?" demo for Identification_Template.html (Beginning
 * Letter/Sound ID). Drawn on ActivityDemo's 320×180 canvas; image paths are
 * relative to TEACHER_FILES/TEMPLATES/ (where the template runs). */
(function () {
  if (!window.ActivityDemo) return;

  ActivityDemo.register('identification', {
    /* One picture card like the real .bgl-scard: the picture on top and
     * three lowercase letter buttons under it. Tap the letter the picture's
     * name starts with (it turns blue, like .bgl-lopt.sel), then press the
     * green "✓ Check answers" button: the right letter turns green with a ✓
     * and confetti (the real app then shows the score). Ball/bola starts
     * with "b" in English and Tagalog, so the example works for every child. */
    'default': function (k) {
      k.css(
        // Picture card — mirrors .bgl-scard (white, tan border).
        '.' + k.name('card') + '{position:absolute;left:18px;top:24px;width:156px;height:140px;border-radius:18px;background:#fff;border:3px solid #e2c98f;' +
          'box-shadow:0 4px 0 rgba(15,23,42,.08),0 8px 18px rgba(15,23,42,.10);}' +
        '.' + k.name('pic') + '{position:absolute;left:63px;top:37px;width:66px;height:66px;object-fit:contain;z-index:1;}' +
        // Letter choices — mirror .bgl-lopt (lowercase, tan border, navy text).
        '.' + k.name('key') + '{position:absolute;top:112px;width:40px;height:40px;border-radius:11px;background:#fff;border:3px solid #e2c98f;' +
          'display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;line-height:1;color:#243a5e;z-index:2;}' +
        // Speech bubble that names the picture, its tail pointing at it.
        '.' + k.name('bub') + '{position:absolute;left:206px;top:44px;width:84px;height:52px;border-radius:18px;background:#fff;border:2px solid #e2e8f0;' +
          'display:flex;align-items:center;justify-content:center;white-space:nowrap;box-shadow:0 6px 14px rgba(15,23,42,.12);transform-origin:0 50%;z-index:3;}' +
        '.' + k.name('bub') + ':before{content:"";position:absolute;left:-8px;top:18px;width:13px;height:13px;background:#fff;' +
          'border-left:2px solid #e2e8f0;border-bottom:2px solid #e2e8f0;transform:rotate(45deg);}' +
        '.' + k.name('word') + '{font-size:30px;font-weight:700;line-height:1;color:#243a5e;letter-spacing:.5px;}' +
        '.' + k.name('first') + '{display:inline-block;transform-origin:50% 85%;}' +
        // The real green "✓ Check answers" button (.bgl-mini--check).
        '.' + k.name('chk') + '{position:absolute;left:186px;top:116px;width:124px;height:36px;border-radius:12px;background:#2fbf57;color:#fff;' +
          'display:flex;align-items:center;justify-content:center;font-size:13.5px;font-weight:700;white-space:nowrap;box-shadow:0 4px 0 #1f9d44;z-index:2;}' +
        // Confetti piece: z-index 1 + later in the DOM = over the picture but
        // under the letter keys (z 2), so it bursts out from behind the key.
        '.' + k.name('cf') + '{position:absolute;z-index:1;width:7px;height:11px;margin:-5px 0 0 -3px;border-radius:2px;opacity:0;}');

      var idleKey = 'border-color:#e2c98f;background:#fff;box-shadow:none;transform:none;';
      var selKey = 'border-color:#2f6fed;background:#eaf1ff;box-shadow:0 0 0 4px rgba(47,111,237,.25);';
      var okKey = 'border-color:#22c55e;background:#ecfdf5;box-shadow:0 0 0 5px rgba(34,197,94,.30);transform:none;';
      function key(x, letter, frames) {
        return '<div class="' + k.name('key') + '" style="left:' + x + 'px;' + (frames ? k.kf('key' + x, frames) : '') + '">' + letter + '</div>';
      }
      // Confetti (the real app throws confetti on a perfect score): pieces
      // fan out from the right letter at 50%, drift down and fade by 69%.
      var COLORS = ['#f59e0b', '#22c55e', '#3b82f6', '#ef4444', '#a855f7', '#06b6d4', '#facc15', '#ec4899'];
      function confetti(x, y) {
        var out = '';
        for (var i = 0; i < 14; i++) {
          var ang = (-172 + i * 12.3) * Math.PI / 180, dist = 58 + (i % 3) * 16;
          var dx = Math.round(Math.cos(ang) * dist), dy = Math.round(Math.sin(ang) * dist * 0.85);
          var rot = (i % 2 ? 1 : -1) * (160 + i * 25);
          var end = 'translate(' + dx + 'px,' + (dy + 24) + 'px) rotate(' + Math.round(rot * 1.6) + 'deg) scale(1)';
          out += '<div class="' + k.name('cf') + '" style="left:' + x + 'px;top:' + y + 'px;background:' + COLORS[i % COLORS.length] + ';' +
            (i % 3 === 1 ? 'width:8px;height:8px;border-radius:50%;' : '') + k.kf('cf' + i,
              '0%,50%{opacity:0;transform:translate(0,0) rotate(0) scale(.4);}' +
              '52%{opacity:1;transform:translate(' + Math.round(dx * 0.45) + 'px,' + Math.round(dy * 0.45) + 'px) rotate(' + Math.round(rot * 0.4) + 'deg) scale(.9);}' +
              '59%{opacity:1;transform:translate(' + dx + 'px,' + dy + 'px) rotate(' + rot + 'deg) scale(1);}' +
              '69%,100%{opacity:0;transform:' + end + ';}', 'ease-out') + '"></div>';
        }
        return out;
      }

      var html =
        '<div class="' + k.name('card') + '"></div>' +
        // The ball gives two little hops while the child looks at it (it
        // stays inside the card's border, y ≥ 30).
        '<img class="' + k.name('pic') + '" src="DEMO_ASSETS/ball.jpg" alt="" style="' + k.kf('pic',
          '0%,4%{transform:none;}7%{transform:translateY(-7px);}10%{transform:none;}12.5%{transform:translateY(-3px);}15%,100%{transform:none;}') + '">' +
        key(28, 'm') +
        key(76, 'b',
          '0%,19.5%{' + idleKey + '}' +
          '20%{' + selKey + 'transform:scale(1.12);}' +
          '24%,48%{' + selKey + 'transform:none;}' +
          '49.5%,88%{' + okKey + '}' +
          '92%,100%{' + idleKey + '}') +
        key(124, 's') +
        // Bubble: pops in, then its first letter lights up blue (the sound
        // to find), and turns green with the key once it is checked.
        '<div class="' + k.name('bub') + '" style="' + k.kf('bub',
          '0%,3%{opacity:0;transform:scale(.3);}' +
          '7%{opacity:1;transform:scale(1.08);}' +
          '9.5%,88%{opacity:1;transform:none;}' +
          '92%,100%{opacity:0;transform:scale(.6);}') + '">' +
          '<span class="' + k.name('word') + '"><span class="' + k.name('first') + '" style="' + k.kf('first',
            '0%,9%{color:#243a5e;transform:none;}' +
            '12%{color:#2f6fed;transform:scale(1.4);}' +
            '15%,48%{color:#2f6fed;transform:scale(1.15);}' +
            '49.5%,88%{color:#16a34a;transform:scale(1.15);}' +
            '92%,100%{color:#243a5e;transform:none;}') + '">b</span>all</span>' +
        '</div>' +
        '<div class="' + k.name('chk') + '" style="' + k.kf('chk',
          '0%,44%{transform:none;box-shadow:0 4px 0 #1f9d44;}' +
          '45.5%,47.5%{transform:translateY(3px);box-shadow:0 1px 0 #1f9d44;}' +
          '50%,100%{transform:none;box-shadow:0 4px 0 #1f9d44;}') + '">✓ Check answers</div>' +
        confetti(96, 124) +
        k.ripple(96, 134, 20) +
        k.ripple(248, 134, 45.5) +
        k.badge(114, 113, 50, 88) +
        // Fingertip touches the lower part of each target so the letter and
        // the button label stay readable while they are pressed.
        k.hand(
          '0%{left:150px;top:200px;opacity:0;}' +
          '16%{left:98px;top:142px;opacity:1;}' +
          '19%{left:98px;top:147px;}' +
          '22%,28%{left:98px;top:141px;}' +
          '42%{left:252px;top:144px;}' +
          '45%{left:252px;top:149px;}' +
          '48%,56%{left:252px;top:143px;opacity:1;}' +
          '66%,100%{left:292px;top:188px;opacity:0;}');

      return {
        duration: 5.4,
        say: 'I-tap ang unang letra ng bawat larawan, tapos pindutin ang Check!',
        steps: [
          { icon: '👀', text: 'Tingnan ang larawan', from: 3, to: 20 },
          { icon: '👆', text: 'I-tap ang unang letra', from: 20, to: 44 },
          { icon: '✅', text: 'Pindutin ang Check', from: 44, to: 90 }
        ],
        stage: k.stage(html)
      };
    }
  });
})();
