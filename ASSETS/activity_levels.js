/* ─────────────────────────────────────────────────────────────
 * Activity Levels — loaded by every activity template.
 *
 * Adds a "📶 Level" choice to the template's Publish pop-up, right under
 * Learning Domain (same option chips):
 *   Level 1 / Level 2 / Level 3
 *   🎲 Per student — templates with the Image Library only: every learner
 *      gets their own random set at THEIR level, worked out from their
 *      recent scores (TEACHER_BACKEND/student_level.php), built by
 *      ImageLibrary.personalize() when the learner opens the activity.
 *
 * The choice is saved inside the activity as state.level ('1' | '2' | '3' |
 * 'auto'); the server keeps a copy in teacher_activities.difficulty so lists
 * can show and filter by level. For 'auto' the Image Library language the
 * teacher used is kept too (state.levelLang), so names match what they saw.
 * ───────────────────────────────────────────────────────────── */
(function () {
  if (window.ActivityLevels) return;

  // '1' / '2' / '3' = Easy / Moderate / Difficult, for learners with ASD or
  // ID; the teacher picks it here or with the Image Library's Random set.
  var LEVELS = [
    { v: '1', label: 'Level 1 · Easy', support: 'the easiest: fewest and biggest pictures' },
    { v: '2', label: 'Level 2 · Moderate', support: 'a little more to do, with visual hints' },
    { v: '3', label: 'Level 3 · Difficult', support: 'the most to do, with less visual support' },
    { v: 'auto', label: '🎲 Per student', personal: true }
  ];

  // The template's `const state` lives in its main script's global scope.
  function tplState() {
    try { return typeof state !== 'undefined' ? state : null; } catch (e) { return null; }   // eslint-disable-line no-undef
  }
  function canPersonalize() {
    var L = window.ImageLibrary;
    return !!(L && L.personalize && (L.canPersonalize ? L.canPersonalize() : true));
  }

  function setLevel(v) {
    var st = tplState();
    if (!st) return;
    st.level = v;
    if (v === 'auto') {
      var lang = null;
      try { lang = localStorage.getItem('imglib_lang'); } catch (e) {}
      st.levelLang = lang === 'en' ? 'en' : 'fil';
    }
  }

  function build() {
    var domains = document.querySelector('[class$="-domain-radios"]');
    if (!domains || document.getElementById('tplLevelField')) return;
    var prefix = (domains.className.match(/([a-z]{3})-domain-radios/) || [])[1];
    var domainField = domains.parentElement;
    if (!prefix || !domainField) return;

    var field = document.createElement('div');
    field.className = domainField.className;
    field.id = 'tplLevelField';
    var lbl = document.createElement('label');
    lbl.textContent = '📶 Level';
    field.appendChild(lbl);
    var wrap = document.createElement('div');
    wrap.className = domains.className;
    LEVELS.forEach(function (L) {
      if (L.personal && !canPersonalize()) return;
      var opt = document.createElement('label');
      opt.className = prefix + '-domain-opt';
      var r = document.createElement('input');
      r.type = 'radio';
      r.name = 'tplLevel';
      r.value = L.v;
      r.addEventListener('change', function () { if (r.checked) { setLevel(L.v); hint(); } });
      opt.appendChild(r);
      opt.appendChild(document.createTextNode(' ' + L.label));
      wrap.appendChild(opt);
    });
    field.appendChild(wrap);
    var help = document.createElement('div');
    help.id = 'tplLevelHelp';
    help.style.cssText = 'font-size:12px;color:#64748b;margin-top:6px;line-height:1.4;';
    field.appendChild(help);
    domainField.parentNode.insertBefore(field, domainField.nextSibling);
    sync();
  }

  function hint() {
    var help = document.getElementById('tplLevelHelp');
    var st = tplState();
    if (!help || !st) return;
    help.textContent = st.level === 'auto'
      ? '🎲 Bawat learner ay may sariling random na set ayon sa level niya (galing sa huling 5 scores: <60% = L1, 60–84% = L2, 85%+ = L3). Ang slides mo ang preview lang.'
      : ((LEVELS.filter(function (L) { return L.v === String(st.level); })[0] || LEVELS[0]).label)
        + ' — ' + ((LEVELS.filter(function (L) { return L.v === String(st.level); })[0] || LEVELS[0]).support) + '. Every assigned learner receives this activity.';
  }

  // Starting choice for a new activity: Level 1 (Easy) unless "Per student"
  // was chosen on Generate Materials; the teacher picks the level here.
  function generateLevel() {
    var g = null;
    try { g = sessionStorage.getItem('gen_level'); } catch (e) {}
    return g === 'auto' && canPersonalize() ? 'auto' : '1';
  }

  // Show what the activity is set to. A new activity starts at the level
  // chosen on Generate Materials (Level 1 if none); an existing one keeps its own.
  function sync() {
    var st = tplState();
    if (!st) return;
    if (['1', '2', '3', 'auto'].indexOf(String(st.level)) === -1 || (st.level === 'auto' && !canPersonalize())) setLevel(generateLevel());
    var radios = document.querySelectorAll('input[name="tplLevel"]');
    for (var i = 0; i < radios.length; i++) radios[i].checked = radios[i].value === String(st.level);
    hint();
  }

  function init() {
    build();
    // Re-read state.level each time the Publish pop-up is opened (a loaded
    // draft or an activity being edited may carry its own level).
    var pub = document.querySelector('[id$="PubBtn"]');
    if (pub) pub.addEventListener('click', function () { setTimeout(sync, 0); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

  // Set the activity's level from elsewhere (e.g. the Image Library's Random
  // set button), so Publish opens on the level the teacher just picked.
  function use(v) { setLevel(String(v)); sync(); }

  window.ActivityLevels = { sync: sync, use: use, levels: LEVELS };
})();
