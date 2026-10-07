/* "Paano Sagutan?" demo for Check_ Or_Not_Template.html (Check or X).
 * Drawn on ActivityDemo's 320×180 canvas; image paths are relative to
 * TEACHER_FILES/TEMPLATES/ (where the template runs). */
(function () {
  if (!window.ActivityDemo) return;

  var GOLD = '#e2c98f';   // the real cards' and buttons' border colour

  ActivityDemo.register('checkornot', {
    /* Every picture card has a ✓ and a ✗ button (.chk-scard / .chk-mopt).
     * Tap ✓ if the picture fits the question, ✗ if not — the tapped button
     * fills green / red — then tap the green "✓ Check answers" button. */
    'default': function (k) {
      var idleCard = 'border-color:' + GOLD + ';box-shadow:0 4px 0 rgba(15,23,42,.10),0 8px 16px rgba(15,23,42,.08);';
      var okCard = 'border-color:#22c55e;box-shadow:0 0 0 5px rgba(34,197,94,.30);';
      var glow = function (a, r) { return 'box-shadow:0 3px 0 #1f9d44,0 0 0 ' + r + 'px rgba(47,191,87,' + a + ');'; };
      k.css(
        // The activity's question (its title / instruction line).
        '.' + k.name('q') + '{position:absolute;left:0;top:8px;width:320px;text-align:center;}' +
        '.' + k.name('q') + ' span{display:inline-block;height:22px;line-height:18px;padding:0 10px;border-radius:999px;background:#fff8dc;' +
          'border:2px solid ' + GOLD + ';color:#243a5e;font-size:14px;font-weight:700;}' +
        // A picture card: white with a gold border, ✓ / ✗ buttons under the picture.
        '.' + k.name('card') + '{position:absolute;top:37px;width:100px;height:101px;z-index:2;background:#fff;border:3px solid ' + GOLD + ';border-radius:16px;' + idleCard + '}' +
        '.' + k.name('card') + ' img{position:absolute;left:6px;top:6px;width:82px;height:49px;object-fit:contain;border-radius:9px;}' +
        '.' + k.name('opt') + '{position:absolute;top:60px;width:30px;height:30px;border:3px solid ' + GOLD + ';border-radius:9px;background:#fff;' +
          'display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;line-height:1;}' +
        '.' + k.name('yes') + '{left:12px;color:#1f9d44;}' +
        '.' + k.name('no') + '{left:52px;color:#b9263c;}' +
        // The green "✓ Check answers" button under the cards.
        '.' + k.name('chk') + '{position:absolute;left:96px;top:148px;width:128px;height:21px;z-index:2;border-radius:11px;background:#2fbf57;color:#fff;' +
          'font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;' + glow(0, 0) + '}');

      // A card at x whose `pick` button ('yes' = ✓, 'no' = ✗) is tapped at
      // `at`%: it fills green / red and stays that way; the card turns green
      // once the answers are checked, and everything resets at the end.
      function card(x, src, pick, at) {
        var c = pick === 'yes' ? ['#2fbf57', '#1f9d44'] : ['#e23b54', '#b9263c'];
        var idle = 'background:#fff;color:' + c[1] + ';border-color:' + GOLD + ';transform:scale(1);';
        var full = 'background:' + c[0] + ';color:#fff;border-color:' + c[1] + ';';
        var tap = k.kf('opt' + x,
          '0%,' + (at - 0.5) + '%{' + idle + '}' +
          at + '%{' + full + 'transform:scale(1.22);}' +
          (at + 4) + '%,88%{' + full + 'transform:scale(1);}' +
          '92%,100%{' + idle + '}');
        function opt(kind, sym) {
          return '<div class="' + k.name('opt') + ' ' + k.name(kind) + '"' + (kind === pick ? ' style="' + tap + '"' : '') + '>' + sym + '</div>';
        }
        return '<div class="' + k.name('card') + '" style="left:' + x + 'px;' +
          k.kf('card' + x, '0%,56.5%{' + idleCard + '}57%,88%{' + okCard + '}92%,100%{' + idleCard + '}') + '">' +
          '<img src="' + src + '" alt="">' + opt('yes', '✓') + opt('no', '✗') + '</div>';
      }

      // Tap points: dog ✓ (74,115), car ✗ (246,115), "Check answers" (192,158)
      // — right of centre so the finger doesn't hide the word "Check".
      var html =
        // The question lights up once at the start: read it first, then answer.
        '<div class="' + k.name('q') + '"><span style="' + k.kf('q',
          '0%,1%{background:#fff8dc;border-color:' + GOLD + ';}4.5%{background:#fde68a;border-color:#f59e0b;}' +
          '9%,100%{background:#fff8dc;border-color:' + GOLD + ';}') + '">🐾 Hayop ba?</span></div>' +
        card(44, 'DEMO_ASSETS/dog.jpg', 'yes', 12) +
        card(176, 'DEMO_ASSETS/car.jpg', 'no', 34) +
        // Check button: one soft "ping" when its turn comes, then pressed.
        '<div class="' + k.name('chk') + '" style="' + k.kf('chk',
          '0%,45%{transform:none;' + glow(0.55, 0) + '}' +
          '51%,54.5%{transform:none;' + glow(0, 8) + '}' +
          '55%,58%{transform:translateY(2px);box-shadow:0 1px 0 #1f9d44,0 0 0 8px rgba(47,191,87,0);}' +
          '61%,100%{transform:none;' + glow(0, 8) + '}') + '">✓ Check answers</div>' +
        k.ripple(74, 115, 12) +
        k.ripple(246, 115, 34) +
        k.ripple(192, 158, 55) +
        k.badge(141, 47, 58, 88) +
        k.badge(273, 47, 58, 88) +
        k.hand(
          // Comes in from the lower left — not across the Check button.
          '0%{left:40px;top:205px;opacity:0;}' +
          '9%{left:74px;top:118px;opacity:1;}' +
          '12%{left:74px;top:122px;}' +
          '15%{left:74px;top:117px;}' +
          // Step aside (away from the Check button) so the green ✓ shows.
          '19%,23%{left:58px;top:152px;}' +
          '31%{left:246px;top:118px;}' +
          '34%{left:246px;top:122px;}' +
          '37%{left:246px;top:117px;}' +
          '41%,45%{left:262px;top:152px;}' +
          '52%{left:192px;top:159px;}' +
          '55%{left:192px;top:163px;}' +
          '58%,62%{left:192px;top:158px;opacity:1;}' +
          '72%,100%{left:276px;top:200px;opacity:0;}');
      return {
        duration: 5.5,
        // "tsek" / "ekis" keep the sentence clear when read aloud, too.
        say: 'Sa bawat larawan, pindutin ang tsek ✓ kung oo, at ekis ✗ kung hindi!',
        steps: [
          { icon: '✅', text: 'I-tap ang ✓ kung oo', from: 6, to: 25 },
          { icon: '❌', text: 'I-tap ang ✗ kung hindi', from: 25, to: 46 },
          { icon: '🎉', text: 'I-tap ang Check', from: 46, to: 90 }
        ],
        stage: k.stage(html)
      };
    }
  });
})();
