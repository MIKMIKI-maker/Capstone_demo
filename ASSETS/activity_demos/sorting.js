/* "How to Answer" demos for Sorting_Type_Template.html — one per Activity
 * Type (Sort into Bins / Odd One Out / Yes/No Sort). Drawn on
 * ActivityDemo's 320×180 canvas; image paths are relative to
 * TEACHER_FILES/TEMPLATES/ (where the template runs). */
(function () {
  if (!window.ActivityDemo) return;

  var PHOTOS = '../../ASSETS/image_library/photos/';
  var TILE_ON = 'border-color:#22c55e;box-shadow:0 0 0 4px rgba(34,197,94,.35);';
  var TILE_OFF = 'border-color:#fff;box-shadow:0 4px 0 rgba(15,23,42,.10),0 8px 16px rgba(15,23,42,.10);';

  ActivityDemo.register('sorting', {
    /* Sort into Bins — drag the cat into Animals and the apple into Fruits. */
    bins: function (k) {
      var html =
        '<div class="ad-zone" style="left:16px;top:96px;width:136px;height:78px;"></div>' +
        '<div class="ad-zone" style="left:168px;top:96px;width:136px;height:78px;"></div>' +
        '<div class="ad-label" style="left:16px;top:100px;width:136px;">🐾 Animals</div>' +
        '<div class="ad-label" style="left:168px;top:100px;width:136px;">🍎 Fruits</div>' +
        k.tile(70, 14, 54, 54, PHOTOS + 'pusa.jpg', k.move(70, 14, 57, 116, 12, 28)) +
        k.tile(150, 14, 54, 54, PHOTOS + 'mansanas.jpg', k.move(150, 14, 209, 116, 38, 54)) +
        k.ripple(97, 41, 10) + k.ripple(177, 41, 37) +
        k.check(210, 28, 104, 66) +
        k.badge(108, 118, 68, 88) + k.badge(260, 118, 70, 88) +
        k.hand('0%{left:200px;top:200px;opacity:0;}7%{left:97px;top:37px;opacity:1;}10%{left:97px;top:43px;}12%{left:97px;top:43px;}' +
          '28%{left:84px;top:145px;}31%{left:84px;top:138px;}' +
          '35%{left:177px;top:37px;}37%{left:177px;top:43px;}38%{left:177px;top:43px;}' +
          '54%{left:236px;top:145px;}57%{left:236px;top:138px;}' +
          '63%{left:262px;top:37px;}66%{left:262px;top:44px;}69%{left:262px;top:37px;opacity:1;}77%,100%{left:305px;top:200px;opacity:0;}');
      return {
        duration: 6,
        say: 'Drag each picture into the group where it belongs!',
        steps: [
          { icon: '✋', text: 'Drag a picture', from: 6, to: 30 },
          { icon: '📦', text: 'Drop it in its group', from: 31, to: 58 },
          { icon: '✅', text: 'Tap Check', from: 60, to: 92 }
        ],
        stage: k.stage(html)
      };
    },

    /* Odd One Out — look at every picture, tap the one that is different. */
    oddoneout: function (k) {
      var html =
        k.tile(40, 30, 76, 76, 'DEMO_ASSETS/dog.jpg') +
        k.tile(122, 30, 76, 76, 'DEMO_ASSETS/dog.jpg') +
        k.tile(204, 30, 76, 76, 'DEMO_ASSETS/car.jpg', k.pick(30, TILE_ON, TILE_OFF)) +
        k.ripple(242, 68, 30) +
        k.check(108, 136, 104, 50) +
        k.badge(276, 34, 52, 88) +
        k.hand('0%{left:160px;top:200px;opacity:0;}5%{left:78px;top:84px;opacity:1;}12%{left:160px;top:84px;}19%{left:242px;top:84px;}' +
          '27%{left:242px;top:66px;}30%{left:242px;top:73px;}33%{left:242px;top:66px;}' +
          '46%{left:160px;top:144px;}50%{left:160px;top:151px;}53%{left:160px;top:144px;opacity:1;}62%,100%{left:300px;top:200px;opacity:0;}');
      return {
        duration: 5.2,
        say: 'Find the picture that is different, and tap it!',
        steps: [
          { icon: '👀', text: 'Look at every picture', from: 2, to: 24 },
          { icon: '👆', text: 'Tap the different one', from: 25, to: 44 },
          { icon: '✅', text: 'Tap Check', from: 45, to: 90 }
        ],
        stage: k.stage(html)
      };
    },

    /* Yes/No Sort — look at the picture, tap Yes or No. */
    yesno: function (k) {
      var ON = 'background:#22c55e;color:#fff;border-color:#16a34a;';
      var OFF = 'background:#fff;color:#1e3a8a;border-color:#e2e8f0;';
      k.css('.' + k.name('btn') + '{position:absolute;left:170px;width:120px;height:40px;border-radius:12px;background:#fff;border:3px solid #e2e8f0;' +
        'box-shadow:0 4px 0 #cbd5e1;font-size:17px;font-weight:800;color:#1e3a8a;display:flex;align-items:center;justify-content:center;}');
      var html =
        '<div class="ad-label" style="left:0;top:10px;width:320px;font-size:14px;color:#1e293b;">Is it an animal?</div>' +
        k.tile(40, 36, 100, 100, PHOTOS + 'pusa.jpg') +
        '<div class="' + k.name('btn') + '" style="top:42px;' + k.pick(26, ON, OFF) + '">Yes</div>' +
        '<div class="' + k.name('btn') + '" style="top:92px;">No</div>' +
        k.ripple(230, 62, 26) +
        k.check(170, 140, 120, 48) +
        k.badge(290, 44, 50, 88) +
        k.hand('0%{left:230px;top:200px;opacity:0;}4%{left:90px;top:120px;opacity:1;}14%{left:90px;top:120px;}' +
          '23%{left:230px;top:60px;}26%{left:230px;top:67px;}29%{left:230px;top:60px;}' +
          '44%{left:230px;top:148px;}48%{left:230px;top:155px;}51%{left:230px;top:148px;opacity:1;}60%,100%{left:300px;top:200px;opacity:0;}');
      return {
        duration: 5.2,
        say: 'Look at the picture, then tap Yes or No!',
        steps: [
          { icon: '👀', text: 'Look at the picture', from: 2, to: 16 },
          { icon: '👆', text: 'Tap Yes or No', from: 18, to: 40 },
          { icon: '✅', text: 'Tap Check', from: 42, to: 90 }
        ],
        stage: k.stage(html)
      };
    }
  });
  ActivityDemo.register('sorting', { 'default': 'bins' });
})();
