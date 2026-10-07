/* ─────────────────────────────────────────────────────────────
 * Activity Demo — the "📖 Paano Sagutan?" how-to overlay shown to a
 * student right before an activity starts. Shared by every template.
 *
 * The frame (header, animated stage, synced step chips, read-aloud,
 * big "Nakuha ko!" button) lives here; each template's animations live in
 * ASSETS/activity_demos/<template>.js and register themselves:
 *
 *   ActivityDemo.register('matching', {
 *     pair: function (k) { return { say, steps, stage: k.stage(html), duration } }, ...
 *   });
 *
 *   var demo = ActivityDemo.get('matching', mode);   // falls back to modes['default']
 *   var overlay = ActivityDemo.show(demo);          // #stuDemoOverlay
 *
 * A demo is drawn on a fixed 320×180 canvas (CSS px) and scaled to fit
 * the card, so it looks the same on a phone and on a projector.
 *
 *   say      — the instruction sentence (read aloud by 🔊)
 *   duration — loop length in seconds; every animation and the step
 *              highlights run on this same clock so they stay in sync
 *   steps    — [{ icon, text, from, to }]: from/to are % of the loop
 *              when that step chip lights up
 *   stage    — HTML for the canvas (use kit helpers for keyframes)
 * ───────────────────────────────────────────────────────────── */
(function () {
  if (window.ActivityDemo) return;

  var W = 320, H = 180, MAX_SCALE = 1.4;
  var registry = {};
  var uid = 0;

  /* ── Kit: helpers a demo uses to build its stage ───────────── */
  function makeKit(prefix) {
    var css = [];
    var p = 'ad' + prefix + (++uid) + '_';
    var k = {
      W: W, H: H,
      // Unique keyframe name for this demo.
      name: function (id) { return p + id; },
      // Raw CSS (e.g. shared classes for this demo).
      css: function (text) { css.push(text); return ''; },
      // Define keyframes; returns the `animation:` declaration to paste
      // into a style attribute. Every animation shares the loop clock.
      kf: function (id, frames, timing) {
        css.push('@keyframes ' + p + id + '{' + frames + '}');
        return 'animation:' + p + id + ' var(--ad-dur,3.2s) ' + (timing || 'ease-in-out') + ' infinite;';
      },
      // The pointing hand. `frames` animate left/top/opacity; (left,top)
      // is where the fingertip touches.
      hand: function (frames) {
        return '<div class="ad-hand" style="' + k.kf('hand' + (++uid), frames) + '">👆</div>';
      },
      // A tap ripple centred on (x, y) that bursts at `at`% of the loop.
      ripple: function (x, y, at) {
        var a = Math.max(0, at - 0.5), b = Math.min(100, at + 14);
        return '<div class="ad-ripple" style="left:' + x + 'px;top:' + y + 'px;' + k.kf('rip' + (++uid),
          '0%,' + a + '%{opacity:0;transform:scale(.3);}' +
          at + '%{opacity:1;transform:scale(.3);}' +
          b + '%{opacity:0;transform:scale(1.7);}' +
          '100%{opacity:0;transform:scale(1.7);}', 'ease-out') + '"></div>';
      },
      // A round badge (✓ by default) that pops in at `from`% and stays
      // until `to`%, centred on (x, y). kind: 'ok' | 'no' | 'star'.
      badge: function (x, y, from, to, kind) {
        var sym = kind === 'no' ? '✗' : kind === 'star' ? '★' : '✓';
        var a = Math.max(0, from - 0.5), mid = Math.min(100, from + 5), z = Math.min(100, to + 0.5);
        return '<div class="ad-badge ad-badge-' + (kind || 'ok') + '" style="left:' + x + 'px;top:' + y + 'px;' + k.kf('badge' + (++uid),
          '0%,' + a + '%{opacity:0;transform:scale(0);}' +
          from + '%{opacity:1;transform:scale(.4);}' +
          mid + '%{opacity:1;transform:scale(1.2);}' +
          Math.min(100, from + 9) + '%,' + to + '%{opacity:1;transform:scale(1);}' +
          z + '%,100%{opacity:0;transform:scale(1);}') + '">' + sym + '</div>';
      },
      // Final stage HTML with the collected CSS in front of it.
      stage: function (html) { return '<style>' + css.join('') + '</style>' + html; }
    };
    return k;
  }

  /* ── Frame styles ──────────────────────────────────────────── */
  function injectStyles() {
    if (document.getElementById('adStyles')) return;
    var st = document.createElement('style');
    st.id = 'adStyles';
    st.textContent =
      '#stuDemoOverlay{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px;' +
        'background:radial-gradient(circle at 18% 12%,rgba(99,102,241,.45),transparent 45%),radial-gradient(circle at 85% 90%,rgba(34,197,94,.30),transparent 45%),rgba(15,23,42,.86);' +
        'font-family:"Fredoka","Nunito",system-ui,sans-serif;animation:adFade .25s ease-out both;}' +
      '#stuDemoOverlay.ad-closing{animation:adFadeOut .2s ease-in both;}' +
      '#stuDemoOverlay *{box-sizing:border-box;}' +
      '@keyframes adFade{from{opacity:0;}to{opacity:1;}}' +
      '@keyframes adFadeOut{from{opacity:1;}to{opacity:0;}}' +
      '@keyframes adPop{0%{opacity:0;transform:translateY(22px) scale(.9);}60%{opacity:1;transform:translateY(-4px) scale(1.02);}100%{opacity:1;transform:none;}}' +
      '@keyframes adBob{0%,100%{transform:translateY(0) rotate(-4deg);}50%{transform:translateY(-4px) rotate(4deg);}}' +
      '@keyframes adPulse{0%,70%,100%{transform:scale(1);}80%{transform:scale(1.05);}90%{transform:scale(.99);}}' +
      '@keyframes adProgress{from{transform:scaleX(0);}to{transform:scaleX(1);}}' +

      '.ad-card{position:relative;width:min(560px,100%);max-height:calc(100vh - 32px);overflow-y:auto;background:#fff;border-radius:28px;' +
        'box-shadow:0 30px 80px rgba(0,0,0,.45),0 0 0 4px rgba(255,255,255,.08);animation:adPop .45s cubic-bezier(.2,.9,.3,1.2) both;text-align:center;color:#1e293b;}' +

      '.ad-head{position:relative;overflow:hidden;display:flex;align-items:center;gap:14px;padding:18px 20px 34px;border-radius:28px 28px 0 0;' +
        'background:linear-gradient(135deg,#6366f1 0%,#3b82f6 55%,#06b6d4 100%);color:#fff;text-align:left;}' +
      '.ad-head:before,.ad-head:after{content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.12);pointer-events:none;}' +
      '.ad-head:before{width:140px;height:140px;right:-30px;top:-60px;}' +
      '.ad-head:after{width:90px;height:90px;left:38%;bottom:-55px;}' +
      '.ad-icon{flex:none;width:54px;height:54px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;' +
        'box-shadow:0 6px 14px rgba(30,27,75,.25);animation:adBob 2.4s ease-in-out infinite;}' +
      '.ad-titles{flex:1;min-width:0;position:relative;z-index:1;}' +
      '.ad-title{margin:0;font-size:26px;font-weight:700;line-height:1.1;letter-spacing:.2px;}' +
      '.ad-sub{margin:4px 0 0;font-size:14px;font-weight:500;opacity:.92;}' +
      '.ad-speak{position:relative;z-index:1;flex:none;width:48px;height:48px;border-radius:50%;border:2px solid rgba(255,255,255,.55);background:rgba(255,255,255,.18);' +
        'color:#fff;font-size:22px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s,transform .1s;}' +
      '.ad-speak:hover{background:rgba(255,255,255,.3);}.ad-speak:active{transform:scale(.92);}' +
      '.ad-speak.ad-on{background:#fde68a;border-color:#fde68a;}' +

      '.ad-stage{position:relative;margin:-20px 18px 0;border-radius:22px;background-color:#f8fafc;' +
        'background-image:radial-gradient(#dbe3ee 1.2px,transparent 1.2px);background-size:16px 16px;border:2px solid #e2e8f0;' +
        'box-shadow:0 10px 24px rgba(15,23,42,.10);overflow:hidden;display:flex;align-items:center;justify-content:center;padding:14px 0 18px;}' +
      '.ad-watch{position:absolute;left:10px;top:10px;z-index:3;font-size:11.5px;font-weight:700;color:#4338ca;background:#eef2ff;border:1px solid #c7d2fe;' +
        'padding:3px 9px;border-radius:999px;letter-spacing:.3px;}' +
      '.ad-fit{position:relative;flex:none;}' +
      '.ad-canvas{position:absolute;left:0;top:0;width:' + W + 'px;height:' + H + 'px;transform-origin:0 0;}' +
      '.ad-progress{position:absolute;left:0;right:0;bottom:0;height:5px;background:#e2e8f0;}' +
      '.ad-progress i{display:block;height:100%;background:linear-gradient(90deg,#6366f1,#22c55e);transform-origin:0 50%;}' +

      '.ad-steps{display:grid;grid-auto-flow:column;grid-auto-columns:1fr;gap:8px;margin:16px 18px 0;padding:0;list-style:none;}' +
      '.ad-step{display:flex;align-items:center;gap:7px;min-width:0;padding:7px 10px 7px 7px;border-radius:16px;border:2px solid #e2e8f0;background:#f1f5f9;' +
        'color:#64748b;font-size:14px;font-weight:700;line-height:1.15;text-align:left;}' +
      '.ad-step-txt{min-width:0;}' +
      '.ad-num{flex:none;width:24px;height:24px;border-radius:50%;background:#cbd5e1;color:#fff;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;}' +
      '.ad-step-ico{font-size:17px;line-height:1;}' +

      '.ad-say{margin:14px 22px 0;font-size:19px;font-weight:700;color:#1e293b;line-height:1.35;}' +
      '.ad-say b{color:#4338ca;}' +
      '.ad-actions{padding:18px 20px 22px;}' +
      '.ad-go{border:none;cursor:pointer;font-family:inherit;font-size:23px;font-weight:700;color:#fff;padding:15px 52px;border-radius:999px;' +
        'background:linear-gradient(180deg,#4ade80,#16a34a);box-shadow:0 6px 0 #15803d,0 12px 26px rgba(22,163,74,.35);' +
        'animation:adPulse 2.6s ease-in-out 1.2s infinite;transition:filter .15s;}' +
      '.ad-go:hover{filter:brightness(1.06);}' +
      '.ad-go:active{transform:translateY(4px)!important;box-shadow:0 2px 0 #15803d,0 6px 14px rgba(22,163,74,.3);animation:none;}' +
      '.ad-go:focus-visible{outline:3px solid #bbf7d0;outline-offset:4px;}' +

      /* Building blocks for the demo canvases */
      '.ad-canvas .ad-tile{position:absolute;background:#fff;border:3px solid #fff;border-radius:16px;overflow:hidden;' +
        'box-shadow:0 4px 0 rgba(15,23,42,.10),0 8px 16px rgba(15,23,42,.10);}' +
      '.ad-canvas .ad-tile img{width:100%;height:100%;object-fit:cover;display:block;}' +
      '.ad-canvas .ad-key{position:absolute;width:52px;height:52px;border-radius:14px;background:#fff;border:2px solid #e2e8f0;' +
        'box-shadow:0 4px 0 #cbd5e1;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:700;color:#1e3a8a;}' +
      '.ad-canvas .ad-chip{position:absolute;padding:6px 12px;border-radius:12px;background:#fde68a;color:#78350f;font-size:15px;font-weight:700;' +
        'box-shadow:0 3px 0 #f59e0b;white-space:nowrap;}' +
      '.ad-canvas .ad-zone{position:absolute;border:3px dashed #93c5fd;background:rgba(59,130,246,.07);border-radius:16px;}' +
      '.ad-canvas .ad-label{position:absolute;font-size:12px;font-weight:700;color:#475569;text-align:center;white-space:nowrap;}' +
      '.ad-canvas .ad-hand{position:absolute;z-index:5;width:40px;height:40px;margin:-4px 0 0 -19px;font-size:36px;line-height:1;pointer-events:none;' +
        'filter:drop-shadow(0 4px 4px rgba(15,23,42,.30));}' +
      '.ad-canvas .ad-ripple{position:absolute;z-index:4;width:50px;height:50px;margin:-25px 0 0 -25px;border-radius:50%;' +
        'border:4px solid rgba(99,102,241,.85);background:rgba(99,102,241,.12);opacity:0;pointer-events:none;}' +
      '.ad-canvas .ad-badge{position:absolute;z-index:6;width:30px;height:30px;margin:-15px 0 0 -15px;border-radius:50%;color:#fff;font-size:17px;font-weight:800;' +
        'display:flex;align-items:center;justify-content:center;border:3px solid #fff;box-shadow:0 4px 10px rgba(15,23,42,.25);opacity:0;}' +
      '.ad-canvas .ad-badge-ok{background:#22c55e;}.ad-canvas .ad-badge-no{background:#ef4444;}.ad-canvas .ad-badge-star{background:#f59e0b;}' +

      '@media (max-width:480px){' +
        '.ad-head{padding:14px 14px 30px;gap:10px;}.ad-icon{width:44px;height:44px;font-size:22px;}' +
        '.ad-title{font-size:21px;}.ad-sub{font-size:12.5px;}.ad-speak{width:42px;height:42px;font-size:19px;}' +
        '.ad-stage{margin:-18px 12px 0;}.ad-steps{margin:12px 12px 0;gap:6px;}.ad-step{font-size:12px;padding:6px 7px 6px 5px;gap:5px;border-radius:14px;}.ad-step-ico{display:none;}' +
        '.ad-num{width:21px;height:21px;font-size:12px;}.ad-say{font-size:16.5px;margin:12px 14px 0;}' +
        '.ad-go{font-size:20px;padding:13px 40px;}.ad-actions{padding:14px 14px 18px;}' +
      '}' +
      '@media (max-height:560px){.ad-head{padding:10px 16px 26px;}.ad-icon{width:40px;height:40px;font-size:20px;}.ad-title{font-size:20px;}.ad-sub{display:none;}' +
        '.ad-stage{padding:8px 0 12px;}.ad-steps{margin-top:8px;}.ad-say{margin-top:6px;font-size:15px;}.ad-actions{padding:10px 16px 12px;}.ad-go{font-size:19px;padding:11px 40px;}}' +
      '@media (prefers-reduced-motion:reduce){.ad-card,.ad-icon,.ad-go{animation:none!important;}}';
    document.head.appendChild(st);
  }

  /* ── Step chip highlights, synced to the demo loop ─────────── */
  function stepCss(steps, duration, p) {
    var out = '';
    steps.forEach(function (s, i) {
      var from = Math.max(0, +s.from || 0), to = Math.min(100, s.to == null ? 100 : +s.to);
      var on = 'background:#eef2ff;border-color:#818cf8;color:#312e81;transform:translateY(-2px) scale(1.04);box-shadow:0 6px 14px rgba(99,102,241,.25);';
      var off = 'background:#f1f5f9;border-color:#e2e8f0;color:#64748b;transform:none;box-shadow:none;';
      var onN = 'background:#6366f1;transform:scale(1.1);', offN = 'background:#cbd5e1;transform:none;';
      function frames(a, b) {
        var f = '';
        if (from > 0) f += '0%,' + Math.max(0, from - 0.6) + '%{' + b + '}';
        f += from + '%,' + to + '%{' + a + '}';
        if (to < 100) f += Math.min(100, to + 0.6) + '%,100%{' + b + '}';
        return f;
      }
      out += '@keyframes ' + p + 's' + i + '{' + frames(on, off) + '}' +
             '@keyframes ' + p + 'n' + i + '{' + frames(onN, offN) + '}' +
             '#stuDemoOverlay .' + p + 's' + i + '{animation:' + p + 's' + i + ' ' + duration + 's linear infinite;}' +
             '#stuDemoOverlay .' + p + 's' + i + ' .ad-num{animation:' + p + 'n' + i + ' ' + duration + 's linear infinite;}';
    });
    return out;
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function speak(text, btn) {
    if (window.ActivityAudio && ActivityAudio.speak) {
      ActivityAudio.speak(text, {
        onstart: function () { if (btn) btn.classList.add('ad-on'); },
        onend: function () { if (btn) btn.classList.remove('ad-on'); }
      });
      return;
    }
    if (!window.speechSynthesis) return;
    try {
      speechSynthesis.cancel();
      var u = new SpeechSynthesisUtterance(text);
      u.rate = 0.9; u.pitch = 1.1;
      if (btn) {
        u.onstart = function () { btn.classList.add('ad-on'); };
        u.onend = u.onerror = function () { btn.classList.remove('ad-on'); };
      }
      speechSynthesis.speak(u);
    } catch (e) {}
  }

  /* ── Show ──────────────────────────────────────────────────── */
  function show(demo, opts) {
    opts = opts || {};
    if (!demo) return null;
    injectStyles();
    var old = document.getElementById('stuDemoOverlay');
    if (old) old.remove();

    var duration = demo.duration || 3.2;
    var p = 'adst' + (++uid) + '_';
    var steps = demo.steps || [];

    var ov = document.createElement('div');
    ov.id = 'stuDemoOverlay';
    ov.setAttribute('role', 'dialog');
    ov.setAttribute('aria-modal', 'true');
    ov.setAttribute('aria-labelledby', 'adTitle');
    ov.innerHTML =
      '<style>#stuDemoOverlay{--ad-dur:' + duration + 's;}' + stepCss(steps, duration, p) +
        '#stuDemoOverlay .ad-progress i{animation:adProgress ' + duration + 's linear infinite;}</style>' +
      '<div class="ad-card">' +
        '<div class="ad-head">' +
          '<div class="ad-icon" aria-hidden="true">' + esc(demo.icon || '📖') + '</div>' +
          '<div class="ad-titles"><h2 class="ad-title" id="adTitle">' + esc(demo.title || 'Paano Sagutan?') + '</h2>' +
            '<p class="ad-sub">' + esc(demo.subtitle || 'Panoorin muna, tapos ikaw naman! 👀') + '</p></div>' +
          (window.speechSynthesis ? '<button type="button" class="ad-speak" title="Pakinggan (Read aloud)" aria-label="Read the instructions aloud">🔊</button>' : '') +
        '</div>' +
        '<div class="ad-stage" aria-hidden="true"><span class="ad-watch">👀 Panoorin</span>' +
          '<div class="ad-fit"><div class="ad-canvas">' + (demo.stage || '') + '</div></div>' +
          '<div class="ad-progress"><i></i></div>' +
        '</div>' +
        (steps.length ? '<ol class="ad-steps">' + steps.map(function (s, i) {
          return '<li class="ad-step ' + p + 's' + i + '"><span class="ad-num">' + (i + 1) + '</span>' +
            (s.icon ? '<span class="ad-step-ico" aria-hidden="true">' + esc(s.icon) + '</span>' : '') +
            '<span class="ad-step-txt">' + esc(s.text) + '</span></li>';
        }).join('') + '</ol>' : '') +
        '<p class="ad-say">👉 ' + esc(demo.say || '') + '</p>' +
        '<div class="ad-actions"><button type="button" class="ad-go" id="stuDemoOkBtn">' + esc(demo.button || 'Nakuha ko! ▶') + '</button></div>' +
      '</div>';
    document.body.appendChild(ov);

    // Scale the 320×180 canvas to fit both the stage width and whatever
    // height the rest of the card leaves free (short landscape phones).
    var card = ov.querySelector('.ad-card'), stage = ov.querySelector('.ad-stage'),
        fit = ov.querySelector('.ad-fit'), canvas = ov.querySelector('.ad-canvas');
    function layout() {
      fit.style.width = '0px';
      fit.style.height = '0px';
      var byW = (stage.clientWidth - 24) / W;
      var byH = (window.innerHeight - 32 - card.scrollHeight) / H;
      var s = Math.max(0.5, Math.min(MAX_SCALE, byW, byH));
      fit.style.width = Math.round(W * s) + 'px';
      fit.style.height = Math.round(H * s) + 'px';
      canvas.style.transform = 'scale(' + s + ')';
    }
    layout();
    window.addEventListener('resize', layout);

    var speakBtn = ov.querySelector('.ad-speak');
    var spoken = [demo.say].concat(steps.map(function (s) { return s.text; })).filter(Boolean).join('. ');
    if (speakBtn) speakBtn.addEventListener('click', function () { speak(spoken, speakBtn); });

    var btn = ov.querySelector('#stuDemoOkBtn');
    var closed = false;
    function close() {
      if (closed) return;
      closed = true;
      window.removeEventListener('resize', layout);
      ov.classList.add('ad-closing');
      setTimeout(function () { ov.remove(); if (typeof opts.onClose === 'function') opts.onClose(); }, 190);
    }
    btn.addEventListener('click', close);
    try { btn.focus({ preventScroll: true }); } catch (e) {}

    // Read the instruction once on open if Voice is on (works when the
    // browser allows speech without a fresh tap; harmless otherwise).
    if (opts.autoSpeak !== false && window.ActivityAudio && ActivityAudio.voiceOn && ActivityAudio.voiceOn()) {
      setTimeout(function () { if (!closed) speak(spoken, speakBtn); }, 500);
    }
    return ov;
  }

  window.ActivityDemo = {
    register: function (key, modes) { registry[key] = Object.assign(registry[key] || {}, modes); },
    get: function (key, mode) {
      var modes = registry[key];
      if (!modes) return null;
      // 'default' may name another mode, e.g. { 'default': 'pair' }.
      var fn = modes[mode] || modes['default'];
      if (typeof fn === 'string') fn = modes[fn];
      if (typeof fn !== 'function') return null;
      return fn(makeKit(key.replace(/[^a-z0-9]/gi, '').slice(0, 6) + String(mode || 'd').replace(/[^a-z0-9]/gi, '').slice(0, 6)));
    },
    modes: function (key) { return Object.keys(registry[key] || {}); },
    kit: makeKit,
    show: show
  };
})();
