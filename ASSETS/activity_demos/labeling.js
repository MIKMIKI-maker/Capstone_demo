/* "How to Answer" demos for Picture_ Labeling_Template.html — one per
 * Activity Type (Label Each Picture / Type the Label / Label the Picture).
 * Drawn on ActivityDemo's 320×180 canvas; image paths are relative to
 * TEACHER_FILES/TEMPLATES/ (where the template runs). */
(function () {
  if (!window.ActivityDemo) return;

  var CHIP = 'width:64px;text-align:center;';
  var CHECK = '✓ Check Answers';   // this template's button label

  ActivityDemo.register('labeling', {
    /* Label Each Picture — drag each name from the bank into the empty
     * slot under its picture. */
    grid: function (k) {
      var html =
        k.tile(30, 12, 80, 64, 'DEMO_ASSETS/dog.jpg') +
        k.tile(130, 12, 80, 64, 'DEMO_ASSETS/fish.jpg') +
        '<div class="ad-zone" style="left:30px;top:80px;width:80px;height:32px;"></div>' +
        '<div class="ad-zone" style="left:130px;top:80px;width:80px;height:32px;"></div>' +
        '<div class="ad-chip" style="' + CHIP + k.move(34, 134, 138, 81, 38, 54) + '">Fish</div>' +
        '<div class="ad-chip" style="' + CHIP + k.move(124, 134, 38, 81, 12, 28) + '">Dog</div>' +
        k.ripple(156, 148, 10) + k.ripple(66, 148, 37) +
        k.check(220, 81, 96, 66, CHECK) +
        k.badge(106, 16, 68, 88) + k.badge(206, 16, 70, 88) +
        k.hand('0%{left:200px;top:200px;opacity:0;}7%{left:156px;top:144px;opacity:1;}10%{left:156px;top:150px;}12%{left:156px;top:150px;}' +
          '28%{left:70px;top:97px;}31%{left:70px;top:90px;}' +
          '35%{left:66px;top:144px;}37%{left:66px;top:150px;}38%{left:66px;top:150px;}' +
          '54%{left:170px;top:97px;}57%{left:170px;top:90px;}' +
          '63%{left:268px;top:89px;}66%{left:268px;top:96px;}69%{left:268px;top:89px;opacity:1;}77%,100%{left:305px;top:200px;opacity:0;}');
      return {
        duration: 6,
        say: 'Drag each name under its matching picture!',
        steps: [
          { icon: '✋', text: 'Drag a name', from: 6, to: 32 },
          { icon: '🖼️', text: 'Drop it under its picture', from: 33, to: 58 },
          { icon: '✅', text: 'Tap Check', from: 60, to: 92 }
        ],
        stage: k.stage(html)
      };
    },

    /* Type the Label — tap the box and type the picture's name. */
    type: function (k) {
      k.css('.' + k.name('box') + '{position:absolute;left:158px;top:50px;width:146px;height:42px;border-radius:12px;background:#fff;border:2px solid #93c5fd;}' +
        '.' + k.name('ltr') + '{position:absolute;top:56px;width:18px;text-align:center;font-family:monospace;font-size:22px;font-weight:800;color:#1e3a8a;opacity:0;}' +
        '.' + k.name('caret') + '{position:absolute;top:58px;width:2px;height:26px;background:#1e3a8a;}');
      var letters = ['D', 'O', 'G'].map(function (c, i) {
        return '<div class="' + k.name('ltr') + '" style="left:' + (168 + i * 18) + 'px;' + k.appear(14 + i * 10) + '">' + c + '</div>';
      }).join('');
      var html =
        k.tile(30, 30, 110, 110, 'DEMO_ASSETS/dog.jpg') +
        '<div class="' + k.name('box') + '"></div>' + letters +
        '<div class="' + k.name('caret') + '" style="left:168px;' + k.kf('caret',
          '0%,13.5%{left:168px;}14%,23.5%{left:186px;}24%,33.5%{left:204px;}34%,88%{left:222px;}92%,100%{left:168px;}', 'step-end') + '"></div>' +
        k.ripple(282, 74, 8) +
        k.check(170, 112, 120, 52, CHECK) +
        k.badge(300, 52, 54, 88) +
        k.hand('0%{left:282px;top:200px;opacity:0;}6%{left:282px;top:70px;opacity:1;}8%{left:282px;top:76px;}10%{left:282px;top:70px;}' +
          '13%{left:282px;top:75px;}15%{left:282px;top:71px;}23%{left:282px;top:75px;}25%{left:282px;top:71px;}33%{left:282px;top:75px;}35%{left:282px;top:71px;}' +
          '48%{left:230px;top:121px;}52%{left:230px;top:128px;}55%{left:230px;top:121px;opacity:1;}63%,100%{left:300px;top:200px;opacity:0;}');
      return {
        duration: 5.2,
        say: 'Look at the picture, then type its name!',
        steps: [
          { icon: '👀', text: 'Look at the picture', from: 2, to: 11 },
          { icon: '⌨️', text: 'Type its name', from: 12, to: 40 },
          { icon: '✅', text: 'Tap Check', from: 44, to: 90 }
        ],
        stage: k.stage(html)
      };
    },

    /* Label the Picture — drag the right name onto the dot in the picture. */
    diagram: function (k) {
      k.css('.' + k.name('spot') + '{position:absolute;left:95px;top:55px;width:30px;height:30px;border-radius:50%;border:3px dashed #f59e0b;background:rgba(253,230,138,.55);z-index:2;}');
      var html =
        k.tile(20, 14, 180, 152, 'DEMO_ASSETS/dog.jpg') +
        '<div class="' + k.name('spot') + '" style="' + k.kf('spot', '0%,100%{transform:scale(1);}50%{transform:scale(1.15);}') + '"></div>' +
        '<div class="ad-chip" style="' + CHIP + k.move(232, 40, 78, 55, 14, 32) + '">Dog</div>' +
        '<div class="ad-chip" style="' + CHIP + 'left:232px;top:84px;">Cat</div>' +
        k.ripple(264, 55, 12) +
        k.check(212, 134, 102, 50, CHECK) +
        k.badge(146, 52, 52, 88) +
        k.hand('0%{left:200px;top:200px;opacity:0;}8%{left:264px;top:50px;opacity:1;}12%{left:264px;top:57px;}14%{left:264px;top:57px;}' +
          '32%{left:110px;top:72px;}35%{left:110px;top:65px;}' +
          '46%{left:263px;top:142px;}50%{left:263px;top:149px;}53%{left:263px;top:142px;opacity:1;}62%,100%{left:300px;top:200px;opacity:0;}');
      return {
        duration: 5.2,
        say: 'Drag the right name onto the dot in the picture!',
        steps: [
          { icon: '🔍', text: 'Find the dot', from: 2, to: 12 },
          { icon: '✋', text: 'Drag the name onto it', from: 12, to: 36 },
          { icon: '✅', text: 'Tap Check', from: 44, to: 90 }
        ],
        stage: k.stage(html)
      };
    }
  });
  ActivityDemo.register('labeling', { 'default': 'grid' });
})();
