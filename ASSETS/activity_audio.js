/* ─────────────────────────────────────────────────────────────
 * Activity Audio — shared by every activity template.
 *
 *  • Read-aloud: a big 🗣️ button reads the slide's title + instruction
 *    with the browser's built-in voice, and (if Voice is on) the
 *    instruction is read automatically whenever a new slide appears.
 *  • Voice feedback: says "Good job!" / "Let's try again!" with the score
 *    popup (hooked into each template's playActivitySound).
 *  • Everything is spoken in English with an English voice, even on
 *    devices that also have a Filipino voice installed.
 *  • Volume control: a ⚙️ panel with separate sliders for sound effects
 *    and background music, plus a Voice on/off switch. Saved per browser.
 *
 * Templates hook in with:
 *   _actTone(...)            peakGain * ActivityAudio.sfxLevel()
 *   var THEME_VOLUME = ActivityAudio.musicVolume()
 *   playActivitySound(tier)  → ActivityAudio.cheer(tier)
 *   initActivityTheme()      → ActivityAudio.mount()
 * ───────────────────────────────────────────────────────────── */
(function () {
  if (window.ActivityAudio) return;

  var MUSIC_MAX = 0.3;          // slider at 100% → theme gain 0.3 (old fixed level was 0.15 = 50%)
  var DEFAULTS = { sfx: 0.8, music: 0.5, voice: true };

  function load(key, def) {
    try {
      var v = localStorage.getItem('spedalm_audio_' + key);
      if (v === null) return def;
      return typeof def === 'boolean' ? v === '1' : Math.min(1, Math.max(0, parseFloat(v)));
    } catch (e) { return def; }
  }
  function save(key, val) {
    try { localStorage.setItem('spedalm_audio_' + key, typeof val === 'boolean' ? (val ? '1' : '0') : String(val)); } catch (e) {}
  }

  var settings = { sfx: load('sfx', DEFAULTS.sfx), music: load('music', DEFAULTS.music), voice: load('voice', DEFAULTS.voice) };

  /* ── Speech ─────────────────────────────────────────────── */
  var synth = window.speechSynthesis || null;
  var voices = [];
  function refreshVoices() { try { voices = synth ? synth.getVoices() : []; } catch (e) { voices = []; } }
  if (synth) { refreshVoices(); try { synth.addEventListener('voiceschanged', refreshVoices); } catch (e) {} }

  function pickVoice() {
    if (!voices.length) refreshVoices();
    var pref = ['en-PH', 'en-US', 'en-GB', 'en'];
    for (var i = 0; i < pref.length; i++) {
      for (var j = 0; j < voices.length; j++) {
        if (voices[j].lang && voices[j].lang.toLowerCase().indexOf(pref[i].toLowerCase()) === 0) return voices[j];
      }
    }
    return null;
  }

  // Lower the background music while the voice is talking so it's clear.
  function duckMusic(on) {
    try {
      var g = window._themeGain, ctx = window._actAudioCtx;
      if (!g || !ctx || window._themeMuted) return;
      var target = on ? window.THEME_VOLUME * 0.25 : window.THEME_VOLUME;
      g.gain.cancelScheduledValues(ctx.currentTime);
      g.gain.setValueAtTime(g.gain.value, ctx.currentTime);
      g.gain.linearRampToValueAtTime(target, ctx.currentTime + 0.2);
    } catch (e) {}
  }

  function speak(text, opts) {
    opts = opts || {};
    text = String(text || '').replace(/\s+/g, ' ').trim();
    if (!synth || !text) return;
    try {
      synth.cancel();
      var u = new SpeechSynthesisUtterance(text);
      var v = pickVoice();
      if (v) { u.voice = v; u.lang = v.lang; } else { u.lang = 'en-US'; }
      u.rate = opts.rate || 0.9;   // a little slower for young learners
      u.pitch = 1.1;
      u.volume = 1;
      u.onstart = function () { duckMusic(true); setReadBtnActive(true); if (opts.onstart) opts.onstart(); };
      u.onend = u.onerror = function () { duckMusic(false); setReadBtnActive(false); if (opts.onend) opts.onend(); };
      synth.speak(u);
    } catch (e) {}
  }
  function stopSpeaking() { try { if (synth) synth.cancel(); } catch (e) {} duckMusic(false); setReadBtnActive(false); }

  /* ── Score voice ─────────────────────────────────────────── */
  var CHEERS = { perfect: 'Perfect! Amazing job!', good: 'Good job! You passed!', tryagain: "Let's try again. You can do it!" };
  function cheer(tier) {
    // Only while a student is playing (mount() ran) — not in the teacher's editor preview.
    if (!settings.voice || !readBtn) return;
    var line = CHEERS[tier] || CHEERS.tryagain;
    // Let the chime play first, then talk over the end of it.
    setTimeout(function () { speak(line, { rate: 0.95 }); }, 450);
  }

  /* ── UI ──────────────────────────────────────────────────── */
  var prefix = '', readBtn = null, panel = null, lastAutoRead = '';

  function el(id) { return document.getElementById(prefix + id); }
  function visibleText(node) {
    if (!node) return '';
    var cs = window.getComputedStyle(node);
    return cs.display === 'none' || cs.visibility === 'hidden' ? '' : (node.textContent || '');
  }
  function slideText() {
    var parts = [visibleText(el('Title')), visibleText(el('Instr'))].filter(function (t) { return t.trim(); });
    return parts.join('. ');
  }
  function readSlide() {
    if (synth && synth.speaking) { stopSpeaking(); return; }
    speak(slideText() || 'There are no instructions on this slide.');
  }

  function setReadBtnActive(on) {
    if (!readBtn) return;
    readBtn.style.background = on ? '#fde68a' : '#fff';
    readBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
  }

  function injectStyles() {
    if (document.getElementById('aaStyles')) return;
    var st = document.createElement('style');
    st.id = 'aaStyles';
    st.textContent =
      '.aa-fab{position:fixed;right:18px;z-index:99999;width:46px;height:46px;border-radius:50%;border:none;background:#fff;' +
        'box-shadow:0 4px 14px rgba(0,0,0,.18);font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;}' +
      '.aa-fab:active{transform:scale(.94);}' +
      '.aa-read{bottom:74px;width:58px;height:58px;font-size:26px;right:12px;border:3px solid #fbbf24;}' +
      '.aa-gear{bottom:142px;}' +
      '.aa-panel{position:fixed;right:76px;bottom:74px;z-index:100000;width:250px;max-width:calc(100vw - 96px);background:#fff;border-radius:16px;' +
        'box-shadow:0 10px 30px rgba(0,0,0,.25);padding:14px 16px;font-family:inherit;color:#1e293b;display:none;}' +
      '.aa-panel.show{display:block;}' +
      '.aa-panel h4{margin:0 0 10px;font-size:14px;color:#1e3a8a;}' +
      '.aa-row{margin-bottom:12px;}' +
      '.aa-row label{display:flex;justify-content:space-between;font-size:12.5px;font-weight:700;margin-bottom:4px;}' +
      '.aa-row input[type=range]{width:100%;accent-color:#2563eb;}' +
      '.aa-switch{display:flex;align-items:center;justify-content:space-between;font-size:12.5px;font-weight:700;}' +
      '.aa-toggle{border:none;border-radius:20px;padding:5px 14px;font-weight:700;font-size:12px;cursor:pointer;}' +
      '.aa-toggle.on{background:#22c55e;color:#fff;}.aa-toggle.off{background:#e2e8f0;color:#475569;}' +
      '.aa-note{font-size:10.5px;color:#64748b;margin-top:8px;line-height:1.35;}';
    document.head.appendChild(st);
  }

  function applyMusic() {
    window.THEME_VOLUME = settings.music * MUSIC_MAX;
    try {
      var g = window._themeGain, ctx = window._actAudioCtx;
      if (g && ctx && !window._themeMuted) {
        g.gain.cancelScheduledValues(ctx.currentTime);
        g.gain.setValueAtTime(g.gain.value, ctx.currentTime);
        g.gain.linearRampToValueAtTime(window.THEME_VOLUME, ctx.currentTime + 0.15);
      }
    } catch (e) {}
  }

  function buildPanel() {
    panel = document.createElement('div');
    panel.className = 'aa-panel';
    panel.innerHTML =
      '<h4>🎧 Audio Settings</h4>' +
      '<div class="aa-row"><label>🔔 Sound effects <span id="aaSfxVal"></span></label><input type="range" min="0" max="100" step="5" id="aaSfx"></div>' +
      '<div class="aa-row"><label>🎵 Music <span id="aaMusicVal"></span></label><input type="range" min="0" max="100" step="5" id="aaMusic"></div>' +
      '<div class="aa-switch"><span>🗣️ Voice (read-aloud &amp; praise)</span><button type="button" class="aa-toggle" id="aaVoice"></button></div>' +
      (synth ? '' : '<div class="aa-note">⚠️ This browser has no built-in voice, so read-aloud is unavailable.</div>');
    document.body.appendChild(panel);

    var sfx = panel.querySelector('#aaSfx'), music = panel.querySelector('#aaMusic'), voice = panel.querySelector('#aaVoice');
    function paint() {
      sfx.value = Math.round(settings.sfx * 100); panel.querySelector('#aaSfxVal').textContent = sfx.value + '%';
      music.value = Math.round(settings.music * 100); panel.querySelector('#aaMusicVal').textContent = music.value + '%';
      voice.textContent = settings.voice ? 'ON' : 'OFF';
      voice.className = 'aa-toggle ' + (settings.voice ? 'on' : 'off');
    }
    sfx.addEventListener('input', function () { settings.sfx = sfx.value / 100; save('sfx', settings.sfx); paint(); });
    sfx.addEventListener('change', function () { if (typeof window.playDropSound === 'function') window.playDropSound(true); });
    music.addEventListener('input', function () { settings.music = music.value / 100; save('music', settings.music); applyMusic(); paint(); });
    voice.addEventListener('click', function () {
      settings.voice = !settings.voice; save('voice', settings.voice); paint();
      if (settings.voice) speak('Voice is on.'); else stopSpeaking();
    });
    paint();

    document.addEventListener('pointerdown', function (e) {
      if (!panel.classList.contains('show')) return;
      if (panel.contains(e.target) || (e.target.closest && e.target.closest('.aa-gear'))) return;
      panel.classList.remove('show');
    });
  }

  // Read each new slide's instruction automatically when Voice is on.
  function watchSlides() {
    var title = el('Title'), instr = el('Instr');
    if (!window.MutationObserver || (!title && !instr)) return;
    var t = null;
    var obs = new MutationObserver(function () {
      clearTimeout(t);
      t = setTimeout(function () {
        var txt = slideText();
        if (settings.voice && txt && txt !== lastAutoRead) { lastAutoRead = txt; speak(txt); }
      }, 300);
    });
    [title, instr].forEach(function (n) { if (n) obs.observe(n, { childList: true, characterData: true, subtree: true }); });
  }

  // Called from initActivityTheme() — i.e. after a real tap, so audio is allowed.
  function mount() {
    if (readBtn) return;
    var anchor = document.querySelector('[id$="RetakeInfo"]');
    prefix = anchor ? anchor.id.replace(/RetakeInfo$/, '') : '';
    injectStyles();

    readBtn = document.createElement('button');
    readBtn.type = 'button';
    readBtn.className = 'aa-fab aa-read';
    readBtn.textContent = '🗣️';
    readBtn.title = 'Read aloud';
    readBtn.setAttribute('aria-label', 'Read the instructions aloud');
    readBtn.onclick = readSlide;
    if (!synth) readBtn.style.display = 'none';
    document.body.appendChild(readBtn);

    var gear = document.createElement('button');
    gear.type = 'button';
    gear.className = 'aa-fab aa-gear';
    gear.textContent = '⚙️';
    gear.title = 'Audio settings (volume & voice)';
    gear.setAttribute('aria-label', 'Audio settings');
    gear.onclick = function () { panel.classList.toggle('show'); };
    document.body.appendChild(gear);

    buildPanel();
    applyMusic();
    watchSlides();

    // Read the first slide once the "how to answer" demo is dismissed.
    lastAutoRead = slideText();
    if (settings.voice && lastAutoRead) {
      var demo = document.getElementById('stuDemoOverlay');
      if (demo && window.MutationObserver) {
        var o = new MutationObserver(function () {
          if (!document.body.contains(demo)) { o.disconnect(); setTimeout(function () { speak(slideText()); }, 250); }
        });
        o.observe(document.body, { childList: true });
      } else {
        setTimeout(function () { speak(lastAutoRead); }, 400);
      }
    }
  }

  window.addEventListener('pagehide', stopSpeaking);

  window.ActivityAudio = {
    sfxLevel: function () { return settings.sfx; },
    voiceOn: function () { return settings.voice; },
    musicVolume: function () { return settings.music * MUSIC_MAX; },
    cheer: cheer,
    speak: speak,
    stop: stopSpeaking,
    mount: mount
  };
})();
