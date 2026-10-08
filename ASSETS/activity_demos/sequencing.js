/* "How to Answer" demo for Sequencing_Type_Template.html (it has a single
 * Activity Type). Drawn on ActivityDemo's 320×180 canvas; image paths are
 * relative to TEACHER_FILES/TEMPLATES/ (where the template runs). */
(function () {
  if (!window.ActivityDemo) return;

  var GOLD = '#e2c98f';   // the real step cards' border colour
  var BLUE = '#2f6fed';   // a tapped card's border + number badge (--seq-badge)
  var GREEN = '#1f9d44';  // a correct card's number badge after "Check answers"

  ActivityDemo.register('sequencing', {
    /* The step cards (.seq-card-it) are shuffled. Tap them in the order the
     * steps happen: each tapped card turns blue and gets a number badge
     * (.seq-badge-num: 1, 2, 3 …). Then "✓ Check answers" turns the cards
     * that are in the right place green. */
    'default': function (k) {
      var TOP = 40, W = 80;
      var idle = 'border-color:' + GOLD + ';box-shadow:0 4px 0 rgba(15,23,42,.10),0 8px 16px rgba(15,23,42,.08);';
      var sel = 'border-color:' + BLUE + ';box-shadow:0 0 0 4px rgba(47,111,237,.25);';
      var ok = 'border-color:#22c55e;box-shadow:0 0 0 5px rgba(34,197,94,.30);';
      var glow = function (a, r) { return 'box-shadow:0 3px 0 ' + GREEN + ',0 0 0 ' + r + 'px rgba(47,191,87,' + a + ');'; };
      k.css(
        // A step card: picture on top, its step label underneath.
        '.' + k.name('card') + '{position:absolute;top:' + TOP + 'px;width:' + W + 'px;height:92px;z-index:2;background:#fff;border:3px solid ' + GOLD + ';border-radius:16px;' + idle + '}' +
        '.' + k.name('card') + ' img{position:absolute;left:5px;top:11px;width:64px;height:44px;object-fit:cover;border-radius:9px;}' +
        '.' + k.name('lbl') + '{position:absolute;left:0;right:0;top:60px;text-align:center;font-size:12.5px;font-weight:700;color:#243a5e;line-height:1.2;}' +
        // The round number badge that sits on the card's top edge once tapped.
        '.' + k.name('num') + '{position:absolute;left:50%;top:-16px;width:28px;height:28px;margin-left:-14px;border-radius:50%;background:' + BLUE + ';color:#fff;' +
          'border:3px solid #fff;box-shadow:0 3px 0 rgba(0,0,0,.15);font-size:15px;font-weight:800;line-height:1;display:flex;align-items:center;justify-content:center;opacity:0;}' +
        // The green "✓ Check answers" button under the cards.
        '.' + k.name('chk') + '{position:absolute;left:94px;top:145px;width:132px;height:25px;z-index:2;border-radius:13px;background:#2fbf57;color:#fff;' +
          'font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;white-space:nowrap;' + glow(0, 0) + '}');

      // A card at x showing step `n` (its picture and label). It is tapped at
      // `t`% (blue border, badge `n` pops in), turns green when the answers
      // are checked at `c`%, and everything resets at the end of the loop.
      function card(x, n, src, label, t, c) {
        var cardAnim = k.kf('card' + n,
          '0%,' + (t - 0.5) + '%{' + idle + 'transform:none;}' +
          t + '%{' + sel + 'transform:scale(.94);}' +
          (t + 4) + '%,' + (c - 0.5) + '%{' + sel + 'transform:none;}' +
          c + '%{' + ok + 'transform:scale(1.05);}' +
          (c + 4) + '%,88%{' + ok + 'transform:none;}' +
          '93%,100%{' + idle + 'transform:none;}');
        var numAnim = k.kf('num' + n,
          '0%,' + (t - 0.5) + '%{opacity:0;transform:scale(0);background:' + BLUE + ';}' +
          t + '%{opacity:1;transform:scale(.4);}' +
          (t + 4) + '%{opacity:1;transform:scale(1.25);}' +
          (t + 8) + '%,' + (c - 0.5) + '%{opacity:1;transform:scale(1);background:' + BLUE + ';}' +
          c + '%{opacity:1;transform:scale(1.2);background:' + GREEN + ';}' +
          (c + 5) + '%,88%{opacity:1;transform:scale(1);background:' + GREEN + ';}' +
          '92%{opacity:0;transform:scale(.4);background:' + GREEN + ';}' +
          '100%{opacity:0;transform:scale(0);background:' + BLUE + ';}');
        return '<div class="' + k.name('card') + '" style="left:' + x + 'px;' + cardAnim + '">' +
          '<img src="' + src + '" alt=""><div class="' + k.name('lbl') + '">' + label + '</div>' +
          '<div class="' + k.name('num') + '" style="' + numAnim + '">' + n + '</div></div>';
      }

      // Shuffled like the real activity, with step 1 on the RIGHT: the very
      // first tap shows the child that the order comes from the pictures,
      // not from left → right. Cards at x = 22 / 120 / 218 (centres 62 /
      // 160 / 258). Taps: step 1 at 12%, step 2 at 26%, step 3 at 38%;
      // "Check answers" (right under step 3) at 51%.
      var C = 52;
      var html =
        card(22, 2, 'DEMO_ASSETS/applysoap.jpg', 'Soap', 26, C) +
        card(120, 3, 'DEMO_ASSETS/rinsehands.jpg', 'Rinse', 38, C) +
        card(218, 1, 'DEMO_ASSETS/wethands.jpg', 'Wet', 12, C) +
        // Check button: one soft "ping" when its turn comes, then pressed.
        '<div class="' + k.name('chk') + '" style="' + k.kf('chk',
          '0%,40%{transform:none;' + glow(0.55, 0) + '}' +
          '46%,50%{transform:none;' + glow(0, 8) + '}' +
          '51%,53%{transform:translateY(2px);box-shadow:0 1px 0 ' + GREEN + ',0 0 0 8px rgba(47,191,87,0);}' +
          '56%,100%{transform:none;' + glow(0, 8) + '}') + '">✓ Check answers</div>' +
        k.ripple(258, 76, 12) +
        k.ripple(62, 76, 26) +
        k.ripple(160, 76, 38) +
        k.ripple(186, 157, 51) +
        // ✓ ticks pop in step order — 1, 2, 3 — after checking, on each
        // card's top-right corner (clear of its number badge).
        k.badge(296, 47, 53, 88) +
        k.badge(100, 47, 55.5, 88) +
        k.badge(198, 47, 58, 88) +
        k.hand(
          // Comes up the right side — never across the Check button.
          '0%{left:292px;top:205px;opacity:0;}' +
          '9%{left:258px;top:79px;opacity:1;}' +
          '12%{left:258px;top:84px;}' +
          // Sweep to step 2, arcing a little low past the middle card.
          '15%{left:258px;top:78px;animation-timing-function:ease-in;}' +
          '18.5%{left:160px;top:96px;animation-timing-function:ease-out;}' +
          '23%{left:62px;top:79px;}' +
          '26%{left:62px;top:84px;}' +
          '29%{left:62px;top:78px;}' +
          '35%{left:160px;top:79px;}' +
          '38%{left:160px;top:84px;}' +
          '41%{left:160px;top:78px;}' +
          '48%{left:186px;top:157px;}' +
          '51%{left:186px;top:162px;}' +
          '54%,57%{left:186px;top:156px;opacity:1;}' +
          // Slide off down-right, already faded before it reaches the edge.
          '63%,100%{left:228px;top:170px;opacity:0;}');
      return {
        duration: 5.5,
        say: 'Tap the pictures in the right order: 1, 2, 3!',
        steps: [
          { icon: '👆', text: 'Tap what comes first', from: 4, to: 20 },
          { icon: '🔢', text: 'Tap what comes next', from: 20, to: 44 },
          { icon: '✅', text: 'Tap Check', from: 44, to: 90 }
        ],
        stage: k.stage(html)
      };
    }
  });
})();
