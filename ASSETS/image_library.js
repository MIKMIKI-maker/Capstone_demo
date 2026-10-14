/* =====================================================================
 * IMAGE LIBRARY — built-in real photos + per-template Quick Sets
 * ---------------------------------------------------------------------
 * Shared by the activity templates so a teacher in a hurry can fill an
 * activity without hunting for pictures. Load library_data.js first:
 *
 *   <script src="../../ASSETS/image_library/library_data.js"></script>
 *   <script src="../../ASSETS/image_library.js"></script>
 *
 * Then, from the template:
 *   ImageLibrary.init({ template:"sorting", isEditor:fn, applySet:fn })
 *   (Video & Audio also passes mediaSet:fn(level, rand) — its own random set)
 *   ImageLibrary.tabButton(function(url, photo, lang){ ... })  // per item
 *   ImageLibrary.launcher()                                     // side card
 *
 * Picked photos are stored as root-relative paths (/…/ASSETS/image_library/
 * photos/x.jpg) instead of base64, so they work from both the teacher and
 * student pages and keep saved activities small.
 * ===================================================================== */
(function () {
  "use strict";

  var BASE = new URL("image_library/", (document.currentScript && document.currentScript.src) || location.href);
  var DATA = window.IMAGE_LIBRARY_DATA || { categories: [], photos: [], sets: {} };
  var PHOTO_BY_ID = {};
  DATA.photos.forEach(function (p) { PHOTO_BY_ID[p.id] = p; });

  // Template-specific wording for the first-time pop-up.
  var INTRO = {
    sorting: "May handa nang mga totoong litrato para sa Sorting. Pumili ng Quick Set, gaya ng “Hayop at Prutas”, at mapupuno agad ang mga category at item.",
    identification: "May handa nang mga totoong litrato para sa Identification. Kapag pumili ka ng litrato, awtomatikong ilalagay ang tamang unang letra.",
    written: "May handa nang mga totoong litrato para sa Written Response. Kapag pumili ka ng litrato, awtomatikong ilalagay ang tamang sagot (letra o salita).",
    check: "May handang Quick Sets gaya ng “Masustansya ba?” — kumpleto na sa totoong litrato at tamang sagot (✓ o ✗).",
    matching: "May handang pares ng totoong litrato, gaya ng “Parehong Hayop” at “Magkaugnay na Gamit”.",
    labeling: "May handang set ng totoong litrato na may label, gaya ng “Mga Hayop” at “Mga Prutas”.",
    sequencing: "May handang sunod-sunod na totoong litrato, gaya ng “Life Cycle ng Paru-paro”.",
    _default: "May handa nang mga totoong litrato na puwede mong gamitin sa activity. Hindi mo na kailangang maghanap o mag-upload."
  };

  var cfg = { template: "", isEditor: function () { return true; }, applySet: null };
  var root = null, els = {}, pickCb = null, activeTab = "sets", activeCat = "all";

  function store(kind, key, val) {
    try {
      var s = kind === "session" ? sessionStorage : localStorage;
      if (val === undefined) return s.getItem(key);
      s.setItem(key, val);
    } catch (e) { return null; }
  }
  // forcedLang: while building a learner's own set, the language the teacher
  // published in (not whatever this device last chose).
  var forcedLang = null;
  function lang() { return forcedLang || (store("local", "imglib_lang") === "en" ? "en" : "fil"); }
  function label(obj) { return obj[lang()] || obj.fil; }
  function photoUrl(p) { return new URL(p.file, BASE).pathname; }
  function sets() { return (DATA.sets && DATA.sets[cfg.template]) || []; }
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }

  /* ---------------- styles ---------------- */
  function injectCss() {
    if (document.getElementById("imlStyles")) return;
    var css = [
      ".iml-overlay{position:fixed;inset:0;background:rgba(15,23,42,.55);display:none;align-items:center;justify-content:center;z-index:10000;padding:12px;box-sizing:border-box;font-family:'Nunito','Poppins',system-ui,sans-serif;}",
      ".iml-overlay.show{display:flex;}",
      ".iml-modal{background:#fff;border-radius:18px;width:min(940px,100%);max-height:88vh;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(15,23,42,.3);overflow:hidden;color:#1e293b;}",
      ".iml-head{display:flex;align-items:center;gap:10px;padding:14px 18px;border-bottom:1px solid #e2e8f0;}",
      ".iml-head h2{margin:0;font-size:18px;font-weight:800;flex:1;}",
      ".iml-close{border:none;background:#f1f5f9;border-radius:10px;width:34px;height:34px;font-size:18px;cursor:pointer;color:#475569;}",
      ".iml-bar{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:10px 18px;border-bottom:1px solid #e2e8f0;}",
      ".iml-tab{border:1.5px solid #cbd5e1;background:#fff;border-radius:999px;padding:7px 14px;font:inherit;font-size:13px;font-weight:700;cursor:pointer;color:#334155;}",
      ".iml-tab.sel{background:#2f6fed;border-color:#2f6fed;color:#fff;}",
      ".iml-lang{margin-left:auto;display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#64748b;}",
      ".iml-lang button{border:1.5px solid #cbd5e1;background:#fff;border-radius:8px;padding:4px 9px;font:inherit;font-size:12px;font-weight:700;cursor:pointer;color:#334155;}",
      ".iml-lang button.sel{background:#eaf2ff;border-color:#2f6fed;color:#1b4fb8;}",
      ".iml-body{overflow:auto;padding:16px 18px;flex:1;}",
      ".iml-hint{background:#eef6ff;border:1px solid #d3e6fb;color:#1e3a8a;border-radius:12px;padding:9px 12px;font-size:13px;margin-bottom:12px;}",
      ".iml-sets{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:14px;}",
      ".iml-set{border:1.5px solid #e2e8f0;border-radius:14px;padding:12px;display:flex;flex-direction:column;gap:10px;}",
      ".iml-set h3{margin:0;font-size:15px;font-weight:800;}",
      ".iml-group-label{font-size:12px;font-weight:800;color:#64748b;margin-bottom:4px;}",
      ".iml-group-row{display:grid;grid-template-columns:repeat(4,1fr);gap:5px;}",
      ".iml-group-row img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px;background:#f1f5f9;}",
      ".iml-lang-tag{margin-left:6px;font-size:10px;font-weight:800;background:#eaf2ff;color:#1b4fb8;border-radius:999px;padding:2px 7px;vertical-align:middle;}",
      ".iml-use{margin-top:auto;border:none;background:#2fbf57;color:#fff;border-radius:10px;padding:9px;font:inherit;font-weight:800;font-size:13px;cursor:pointer;}",
      ".iml-use:hover{background:#1f9d44;}",
      ".iml-search{flex:1;min-width:180px;border:1.5px solid #cbd5e1;border-radius:10px;padding:8px 11px;font:inherit;font-size:14px;}",
      ".iml-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;}",
      ".iml-chip{border:1.5px solid #cbd5e1;background:#fff;border-radius:999px;padding:5px 11px;font:inherit;font-size:12px;font-weight:700;cursor:pointer;color:#334155;}",
      ".iml-chip.sel{background:#fff7e0;border-color:#e2a400;color:#8a5a00;}",
      ".iml-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(122px,1fr));gap:10px;}",
      ".iml-tile{border:2px solid #e2e8f0;border-radius:12px;padding:6px;background:#fff;cursor:pointer;font:inherit;text-align:center;}",
      ".iml-tile:hover,.iml-tile:focus-visible{border-color:#2f6fed;outline:none;}",
      ".iml-tile img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px;display:block;background:#f1f5f9;}",
      ".iml-tile span{display:block;font-size:13px;font-weight:700;margin-top:5px;color:#1e293b;}",
      ".iml-tile.browse{cursor:default;}",
      ".iml-empty{text-align:center;color:#64748b;padding:30px 0;font-size:14px;}",
      ".iml-foot{padding:9px 18px;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;}",
      ".iml-foot button{border:none;background:none;color:#2f6fed;font:inherit;font-weight:700;cursor:pointer;padding:0;}",
      ".iml-credits{font-size:12px;line-height:1.5;color:#334155;}",
      ".iml-credits li{margin-bottom:4px;}",
      ".iml-credits a{color:#2f6fed;}",
      // Let a narrow Upload / Link / Library tab row wrap instead of overflowing.
      ":has(> .iml-libtab){flex-wrap:wrap;row-gap:4px;}",
      ".iml-new{display:inline-block;margin-left:5px;background:#e23b54;color:#fff;border-radius:999px;font-size:9px;font-weight:800;padding:1px 5px;vertical-align:middle;letter-spacing:.03em;}",
      ".iml-launch{display:flex;flex-direction:column;gap:8px;}",
      ".iml-launch p{margin:0;font-size:13px;color:#475569;line-height:1.4;}",
      ".iml-launch-btn{cursor:pointer;border:none;background:linear-gradient(135deg,#2f6fed,#7b6ef0);color:#fff;border-radius:12px;padding:10px;font:inherit;font-weight:800;font-size:14px;}",
      ".iml-rnd{margin-top:6px;padding:12px;border-radius:14px;background:linear-gradient(180deg,#fff7ed,#ffedd5);border:1.5px solid #fdba74;}",
      ".iml-body > .iml-rnd{margin:0 0 12px;}",
      ".iml-rnd-title{display:flex;align-items:center;gap:6px;font-size:14px;font-weight:800;color:#9a3412;}",
      ".iml-rnd-hint{font-size:11.5px;font-weight:600;color:#b45309;margin:3px 0 10px;line-height:1.35;}",
      ".iml-rnd-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(0,1fr));gap:8px;}",
      ".iml-rnd-btn{cursor:pointer;border:none;border-radius:12px;padding:9px 6px 8px;min-height:54px;font:inherit;color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;line-height:1.1;width:100%;"
        + "background:linear-gradient(135deg,#f59e0b,#f97316);box-shadow:0 3px 0 rgba(0,0,0,.2),0 6px 12px rgba(234,88,12,.18);transition:transform .08s ease,box-shadow .08s ease,filter .12s ease;}",
      ".iml-rnd-btn b{font-size:14px;font-weight:800;letter-spacing:.01em;white-space:nowrap;}",
      ".iml-rnd-dots{display:flex;gap:4px;}",
      ".iml-rnd-dots i{width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.38);}",
      ".iml-rnd-dots i.on{background:#fff;}",
      ".iml-rnd-l1{background:linear-gradient(135deg,#22c55e,#16a34a);box-shadow:0 3px 0 #15803d,0 6px 12px rgba(22,163,74,.2);}",
      ".iml-rnd-l2{background:linear-gradient(135deg,#f59e0b,#f97316);box-shadow:0 3px 0 #c2410c,0 6px 12px rgba(234,88,12,.2);}",
      ".iml-rnd-l3{background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 3px 0 #991b1b,0 6px 12px rgba(220,38,38,.2);}",
      ".iml-rnd-btn:hover{filter:brightness(1.07);transform:translateY(-1px);}",
      ".iml-rnd-btn:active{transform:translateY(2px);box-shadow:0 1px 0 rgba(0,0,0,.25);}",
      ".iml-rnd-btn:focus-visible{outline:3px solid #fde68a;outline-offset:2px;}",
      ".iml-intro{background:#fff;border-radius:18px;width:min(440px,100%);padding:22px;box-shadow:0 24px 60px rgba(15,23,42,.3);color:#1e293b;text-align:center;}",
      ".iml-intro h2{margin:0 0 6px;font-size:20px;font-weight:800;}",
      ".iml-intro p{margin:0 0 14px;font-size:14px;line-height:1.5;color:#475569;}",
      ".iml-intro .iml-group-row{margin:0 auto 16px;max-width:330px;}",
      ".iml-intro-actions{display:flex;gap:8px;justify-content:center;flex-wrap:wrap;}",
      ".iml-intro-actions button{border-radius:12px;padding:10px 16px;font:inherit;font-weight:800;font-size:14px;cursor:pointer;}",
      ".iml-primary{border:none;background:#2f6fed;color:#fff;}",
      ".iml-secondary{border:1.5px solid #cbd5e1;background:#fff;color:#334155;}",
      ".iml-intro label{display:flex;gap:6px;align-items:center;justify-content:center;margin-top:12px;font-size:12px;color:#64748b;cursor:pointer;}",
      "@media (max-width:560px){.iml-head,.iml-bar,.iml-body,.iml-foot{padding-left:12px;padding-right:12px;}.iml-lang{margin-left:0;}.iml-grid{grid-template-columns:repeat(auto-fill,minmax(96px,1fr));}}"
    ].join("\n");
    var st = document.createElement("style");
    st.id = "imlStyles"; st.textContent = css;
    document.head.appendChild(st);
  }

  /* ---------------- library modal ---------------- */
  function build() {
    if (root) return;
    injectCss();
    root = el("div", "iml-overlay");
    root.setAttribute("role", "dialog"); root.setAttribute("aria-modal", "true"); root.setAttribute("aria-label", "Image Library");
    var modal = el("div", "iml-modal");

    var head = el("div", "iml-head");
    head.appendChild(el("h2", null, "📷 Image Library"));
    var close = el("button", "iml-close", "✕"); close.type = "button"; close.setAttribute("aria-label", "Close");
    close.addEventListener("click", hide);
    head.appendChild(close);

    var bar = el("div", "iml-bar");
    els.tabSets = el("button", "iml-tab", "⚡ Quick Sets"); els.tabSets.type = "button";
    els.tabPhotos = el("button", "iml-tab", "🖼️ Lahat ng Litrato"); els.tabPhotos.type = "button";
    els.tabSets.addEventListener("click", function () { activeTab = "sets"; render(); });
    els.tabPhotos.addEventListener("click", function () { activeTab = "photos"; render(); });
    bar.appendChild(els.tabSets); bar.appendChild(els.tabPhotos);

    var lg = el("div", "iml-lang", "Label:");
    els.langFil = el("button", null, "Filipino"); els.langFil.type = "button";
    els.langEn = el("button", null, "English"); els.langEn.type = "button";
    els.langFil.addEventListener("click", function () { store("local", "imglib_lang", "fil"); render(); });
    els.langEn.addEventListener("click", function () { store("local", "imglib_lang", "en"); render(); });
    lg.appendChild(els.langFil); lg.appendChild(els.langEn);
    bar.appendChild(lg);

    els.body = el("div", "iml-body");

    var foot = el("div", "iml-foot");
    foot.appendChild(document.createTextNode("Mga litrato mula sa Wikimedia Commons (libreng lisensya). "));
    var cr = el("button", null, "Tingnan ang credits"); cr.type = "button";
    cr.addEventListener("click", function () { activeTab = "credits"; render(); });
    foot.appendChild(cr);

    modal.appendChild(head); modal.appendChild(bar); modal.appendChild(els.body); modal.appendChild(foot);
    root.appendChild(modal);
    root.addEventListener("click", function (e) { if (e.target === root) hide(); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape" && root.classList.contains("show")) hide(); });
    document.body.appendChild(root);
  }

  function render() {
    var hasSets = sets().length > 0;
    if (activeTab === "sets" && !hasSets) activeTab = "photos";
    els.tabSets.style.display = hasSets ? "" : "none";
    els.tabSets.classList.toggle("sel", activeTab === "sets");
    els.tabPhotos.classList.toggle("sel", activeTab === "photos");
    els.langFil.classList.toggle("sel", lang() === "fil");
    els.langEn.classList.toggle("sel", lang() === "en");
    els.body.innerHTML = "";
    if (activeTab === "sets") renderSets();
    else if (activeTab === "credits") renderCredits();
    else renderPhotos();
  }

  function thumbRow(ids) {
    var row = el("div", "iml-group-row");
    ids.forEach(function (id) {
      var p = PHOTO_BY_ID[id]; if (!p) return;
      var im = el("img"); im.src = photoUrl(p); im.alt = label(p); im.loading = "lazy";
      row.appendChild(im);
    });
    return row;
  }

  function idOf(x) { return typeof x === "string" ? x : x.id; }
  // A set may pin its label language (beginning-letter sets) — otherwise
  // it follows the Filipino / English toggle.
  function setLang(s) { return s.lang || lang(); }
  function setName(s) { var L = setLang(s); return s[L] || s.fil || s.en; }

  function renderSets() {
    els.body.appendChild(el("div", "iml-hint",
      "Isang click lang: papalitan ng Quick Set ang laman ng kasalukuyang slide. Puwede mo pa rin itong i-edit pagkatapos."));
    els.body.appendChild(fillRandom(el("div", "iml-rnd")));   // re-rendered each time, so not kept in randomHolders
    var wrap = el("div", "iml-sets");
    sets().forEach(function (s) {
      var card = el("div", "iml-set");
      var h = el("h3", null, setName(s));
      if (s.lang) h.appendChild(el("span", "iml-lang-tag", s.lang === "en" ? "English" : "Filipino"));
      card.appendChild(h);
      if (s.groups) {
        s.groups.forEach(function (g) {
          var box = el("div");
          box.appendChild(el("div", "iml-group-label", g[setLang(s)] || g.fil));
          box.appendChild(thumbRow(g.items.map(idOf)));
          card.appendChild(box);
        });
      } else if (s.pairs) {
        card.appendChild(el("div", "iml-group-label", s.pairs.length + " pares"));
        card.appendChild(thumbRow([].concat.apply([], s.pairs.slice(0, 4)).map(idOf)));
      } else {
        card.appendChild(el("div", "iml-group-label", s.items.length + " litrato"));
        card.appendChild(thumbRow(s.items.slice(0, 8).map(idOf)));
      }
      var use = el("button", "iml-use", "Gamitin ang set na ito"); use.type = "button";
      use.addEventListener("click", function () {
        if (!cfg.applySet) return;
        var message = "Papalitan nito ang laman ng kasalukuyang slide. Ituloy?";
        var ask = window.appConfirm
          ? window.appConfirm(message, { title: "Gamitin ang set na ito?", okText: "Gamitin", cancelText: "Huwag" })
          : Promise.resolve(confirm(message));
        ask.then(function (ok) {
          if (!ok) return;
          cfg.applySet(s.raw ? s : resolveSet(s), setLang(s));
          hide();
        });
      });
      card.appendChild(use);
      wrap.appendChild(card);
    });
    els.body.appendChild(wrap);
  }

  // Hands the template plain data: names in the set's language and photo
  // {id, url, name, yes} entries (yes = correct answer for Check or Not).
  function resolveSet(s) {
    var L = setLang(s);
    function item(x) {
      var o = typeof x === "string" ? { id: x } : x, p = PHOTO_BY_ID[o.id];
      return { id: o.id, url: photoUrl(p), name: o[L] || p[L] || p.fil, yes: o.yes };
    }
    function text(t) { return t ? (t[L] || t.fil || t.en) : ""; }
    return {
      id: s.id, name: setName(s), lang: L, title: text(s.title), instruction: text(s.instruction), answer: s.answer || "",
      groups: s.groups ? s.groups.map(function (g) { return { name: g[L] || g.fil, items: g.items.map(item) }; }) : null,
      items: s.items ? s.items.map(item) : null,
      pairs: s.pairs ? s.pairs.map(function (pr) { return pr.map(item); }) : null,
      extras: s.extras ? s.extras.map(item) : null,
      choices: s.choices || 0
    };
  }

  /* ---------------- 🎲 random set ----------------
   * The panel's "quick creation" request: one click fills the slide with a
   * random set of real photos, in the same shape as this template's Quick
   * Sets, so the template's own applySet handles it. Each click gives a
   * different set. Items mostly come from ONE familiar category (all
   * animals, all fruits…) to keep a slide simple for level-1 learners. */
  var CHECK_Q = {
    hayop:     { fil: "Hayop ba ito?",              en: "Is it an animal?" },
    prutas:    { fil: "Prutas ba ito?",             en: "Is it a fruit?" },
    gulay:     { fil: "Gulay ba ito?",              en: "Is it a vegetable?" },
    paaralan:  { fil: "Gamit ba ito sa paaralan?",  en: "Is it used in school?" },
    bahay:     { fil: "Gamit ba ito sa bahay?",     en: "Is it used at home?" },
    pagkain:   { fil: "Pagkain o inumin ba ito?",   en: "Is it food or a drink?" },
    kalikasan: { fil: "Bahagi ba ito ng kalikasan?", en: "Is it part of nature?" },
    pansarili: { fil: "Pansariling gamit ba ito?",  en: "Is it a personal thing?" },
    laruan:    { fil: "Laruan o sasakyan ba ito?",  en: "Is it a toy or a vehicle?" },
    bulaklak:  { fil: "Bulaklak ba ito?",           en: "Is it a flower?" }
  };
  // rand(): Math.random, or a seeded generator while personalizing (so a
  // learner gets the same set on every reload and retake).
  var rand = Math.random;
  function seeded(seed) {
    var a = (seed >>> 0) || 1;
    return function () {          // mulberry32
      a = (a + 0x6D2B79F5) >>> 0;
      var t = Math.imul(a ^ (a >>> 15), 1 | a);
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }
  function shuffle(a) {
    a = a.slice();
    for (var i = a.length - 1; i > 0; i--) { var j = Math.floor(rand() * (i + 1)), t = a[i]; a[i] = a[j]; a[j] = t; }
    return a;
  }
  function idsIn(cat) { return DATA.photos.filter(function (p) { return p.cat === cat; }).map(function (p) { return p.id; }); }
  function catsWith(n) { return shuffle(DATA.categories.filter(function (c) { return idsIn(c.id).length >= n; })); }
  // Photos whose names (in the current language) start with different
  // letters — for the beginning-letter templates.
  function distinctLetters(n) {
    var seen = {}, out = [];
    shuffle(DATA.photos).forEach(function (p) {
      var l = firstLetterOf(p[lang()] || p.fil);
      if (out.length < n && l && !seen[l]) { seen[l] = true; out.push(p.id); }
    });
    return out;
  }

  function firstLetterOf(word) {
    var m = String(word || "").normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase().match(/[a-z]/);
    return m ? m[0] : "";
  }

  // Level 1 / 2 / 3 — like the templates' Easy → Medium → Hard slides:
  // more pictures per level, and at Level 3 more mixing between categories.
  var LEVELS = [
    { n: 1, fil: "Level 1", en: "Level 1",   tip: "Kaunting litrato, iisang kategorya" },
    { n: 2, fil: "Level 2", en: "Level 2", tip: "Mas maraming litrato" },
    { n: 3, fil: "Level 3", en: "Level 3",   tip: "Pinakamaraming litrato, halo-halong kategorya" }
  ];
  function byLevel(level, l1, l2, l3) { return level === 1 ? l1 : level === 2 ? l2 : l3; }
  // Sequencing's sets are fixed sequences, so it has no levels.
  function hasLevels() { return cfg.template !== "sequencing"; }

  function randomSet(level) {
    level = level === 1 || level === 3 ? level : 2;
    var c, ids, n, tag = { fil: " (Level " + level + ")", en: " (Level " + level + ")" };
    function named(fil, en) { return { fil: "Random: " + fil + tag.fil, en: "Random: " + en + tag.en }; }
    switch (cfg.template) {
      case "matching":
        n = byLevel(level, 3, 4, 5);
        if (level === 3) {
          ids = shuffle(DATA.photos.map(function (p) { return p.id; })).slice(0, n);
          c = { fil: "Halo-halo", en: "Mixed" };
        } else {
          c = catsWith(n)[0]; if (!c) return null; ids = shuffle(idsIn(c.id)).slice(0, n);
        }
        return Object.assign({ id: "random",
          title: { fil: "Pagtapatin ang Magkapareho", en: "Match the Same Pictures" },
          instruction: { fil: "Pagdugtungin ng linya ang magkaparehong larawan.", en: "Draw a line to match the same pictures." },
          pairs: ids.map(function (id) { return [id, id]; }) }, named(c.fil, c.en));
      case "sorting":
        var per = byLevel(level, 2, 3, 3);
        var gs = catsWith(per).slice(0, byLevel(level, 2, 2, 3));
        if (gs.length < 2) return null;
        return Object.assign({ id: "random",
          groups: gs.map(function (g) { return { fil: g.fil, en: g.en, items: shuffle(idsIn(g.id)).slice(0, per) }; }) },
          named(gs.map(function (g) { return g.fil; }).join(", "), gs.map(function (g) { return g.en; }).join(", ")));
      case "labeling":
        n = byLevel(level, 3, 4, 5);
        var ex = byLevel(level, 0, 1, 2);
        c = catsWith(n + ex)[0]; if (!c) return null; ids = shuffle(idsIn(c.id));
        return Object.assign({ id: "random", items: ids.slice(0, n), extras: ids.slice(n, n + ex) }, named(c.fil, c.en));
      case "check":
        n = byLevel(level, 2, 3, 4);   // this many ✓ and this many ✗
        c = catsWith(n)[0]; if (!c) return null;
        var yes = shuffle(idsIn(c.id)).slice(0, n);
        var no = shuffle(DATA.photos.filter(function (p) { return p.cat !== c.id; }).map(function (p) { return p.id; })).slice(0, n);
        var q = CHECK_Q[c.id] || { fil: c.fil + " ba ito?", en: "Is it in " + c.en + "?" };
        return Object.assign({ id: "random", title: q,
          instruction: { fil: "Lagyan ng tsek (✓) kung oo, at ekis (✗) kung hindi.", en: "Put a check (✓) if yes, and an X if not." },
          items: shuffle(yes.map(function (id) { return { id: id, yes: true }; }).concat(no.map(function (id) { return { id: id, yes: false }; }))) },
          named(q.fil, q.en));
      case "identification":
        return Object.assign({ id: "random", items: distinctLetters(byLevel(level, 3, 4, 5)) }, named("Unang Letra", "Beginning Letters"));
      case "written":
        // Written Response turns the first picture into the worked example,
        // so one extra keeps 3 / 4 / 5 actual questions per level.
        return Object.assign({ id: "random", answer: "letter", items: distinctLetters(byLevel(level, 3, 4, 5) + 1) }, named("Unang Letra", "Beginning Letters"));
      case "media":
        // Video & Audio builds its own set (clips of the slide's category,
        // more choices per level) with this same seeded rand.
        return cfg.mediaSet ? cfg.mediaSet(level, rand) : null;
      default:
        // Sequencing (and anything else with Quick Sets): the order of a
        // sequence carries meaning, so pick one of the ready-made sets.
        var all = sets();
        return all.length ? all[Math.floor(rand() * all.length)] : null;
    }
  }

  function applyRandom(level) {
    if (!cfg.applySet) return;
    // A category with too few photos for a level (e.g. after the library
    // data is edited) must not break the page — just say so.
    var s = null;
    try { s = randomSet(level); } catch (e) { s = null; }
    var ok = s && ((s.raw && s.picks && s.picks.length) || (s.pairs && s.pairs.length) || (s.groups && s.groups.length && s.groups.every(function (g) { return g.items.length; })) ||
      (s.items && s.items.length));
    if (!ok) {
      var msg = "Walang sapat na litrato sa library para sa level na ito. Subukan ang ibang level.";
      if (window.showToast) window.showToast(msg, "error"); else alert(msg);
      return;
    }
    var message = "Papalitan nito ng random na mga larawan ang laman ng kasalukuyang slide. Ituloy?";
    var ask = window.appConfirm
      ? window.appConfirm(message, { title: "🎲 Random na set" + (hasLevels() ? " — Level " + level : "") + "?", okText: "Ituloy", cancelText: "Huwag" })
      : Promise.resolve(confirm(message));
    ask.then(function (ok) {
      if (!ok) return;
      cfg.applySet(s.raw ? s : resolveSet(s), setLang(s));
      if (root) hide();
    });
  }

  // "🎲 Random na set" + one button per level (or a single button for
  // templates without levels). Filled again by init(), because templates
  // build the side card with launcher() just before calling init().
  var randomHolders = [];
  // Only the levels of the learners chosen on Generate Materials (a Level 3
  // learner never needs the Level 1 / 2 sets); every level when editing an
  // existing activity or draft, or when opened without Generate.
  function offeredLevels() {
    var q = new URLSearchParams(location.search);
    if (q.get("edit_pub_id") || q.get("draft_id")) return [1, 2, 3];
    var g = null, all = null;
    try { g = sessionStorage.getItem("gen_level"); all = sessionStorage.getItem("gen_levels"); } catch (e) {}
    if (g === "1" || g === "2" || g === "3") return [+g];
    var list = String(all || "").split(",").map(Number).filter(function (n) { return n >= 1 && n <= 3; });
    return list.length ? list : [1, 2, 3];
  }
  function fillRandom(holder) {
    holder.innerHTML = "";
    holder.appendChild(el("div", "iml-rnd-title", "🎲 Random na set"));
    if (!hasLevels()) {
      holder.appendChild(el("div", "iml-rnd-hint", "I-click ulit para sa ibang set."));
      var one = el("button", "iml-rnd-btn"); one.type = "button";
      one.appendChild(el("b", null, "🎲 Pumili ng random na set"));
      one.addEventListener("click", function () { applyRandom(2); });
      holder.appendChild(one);
      return holder;
    }
    var offered = offeredLevels();
    holder.appendChild(el("div", "iml-rnd-hint", offered.length === 1
      ? "Para sa level ng napiling learner. I-click ulit para sa ibang set."
      : "Pumili ng level — i-click ulit para sa ibang set."));
    var row = el("div", "iml-rnd-row");
    LEVELS.forEach(function (L) {
      if (offered.indexOf(L.n) === -1) return;
      var b = el("button", "iml-rnd-btn iml-rnd-l" + L.n); b.type = "button";
      b.appendChild(el("b", null, (offered.length === 1 ? "🎲 " : "") + "Level " + L.n));
      // ●○○ / ●●○ / ●●● — how hard, at a glance
      var dots = el("span", "iml-rnd-dots");
      for (var d = 1; d <= 3; d++) dots.appendChild(el("i", d <= L.n ? "on" : null));
      b.appendChild(dots);
      b.setAttribute("aria-label", "Random set, Level " + L.n);
      b.title = L.tip + " — i-click ulit para sa ibang set.";
      b.addEventListener("click", function () { applyRandom(L.n); });
      row.appendChild(b);
    });
    holder.appendChild(row);
    return holder;
  }
  function randomBox() {
    var h = el("div", "iml-rnd");
    randomHolders.push(h);
    return fillRandom(h);
  }

  function renderPhotos() {
    if (!pickCb) {
      els.body.appendChild(el("div", "iml-hint",
        "Para gamitin ang isang litrato sa item, i-click ang “Library” sa tabi ng Upload / Link ng item na iyon."));
    }
    var top = el("div", "iml-chips");
    var search = el("input", "iml-search");
    search.type = "search"; search.placeholder = "Hanapin (hal. aso, mango, prutas)…";
    search.value = els.query || "";
    top.appendChild(search);
    els.body.appendChild(top);

    var chips = el("div", "iml-chips");
    [{ id: "all", fil: "Lahat", en: "All" }].concat(DATA.categories).forEach(function (c) {
      var b = el("button", "iml-chip" + (activeCat === c.id ? " sel" : ""), label(c)); b.type = "button";
      b.addEventListener("click", function () { activeCat = c.id; els.query = search.value; render(); });
      chips.appendChild(b);
    });
    els.body.appendChild(chips);

    var grid = el("div", "iml-grid");
    els.body.appendChild(grid);
    function fill() {
      var q = search.value.trim().toLowerCase();
      els.query = search.value;
      grid.innerHTML = "";
      var list = DATA.photos.filter(function (p) {
        if (activeCat !== "all" && p.cat !== activeCat) return false;
        if (!q) return true;
        var cat = DATA.categories.filter(function (c) { return c.id === p.cat; })[0] || {};
        return [p.fil, p.en, cat.fil, cat.en].concat(p.tags).some(function (t) {
          return t && t.toLowerCase().indexOf(q) !== -1;
        });
      });
      if (!list.length) { grid.appendChild(el("div", "iml-empty", "Walang nahanap na litrato.")); return; }
      list.forEach(function (p) {
        var t = el("button", "iml-tile" + (pickCb ? "" : " browse")); t.type = "button";
        var im = el("img"); im.src = photoUrl(p); im.alt = label(p); im.loading = "lazy";
        t.appendChild(im); t.appendChild(el("span", null, label(p)));
        if (pickCb) t.addEventListener("click", function () {
          var cb = pickCb; hide(); cb(photoUrl(p), { id: p.id, name: label(p) }, lang());
        });
        grid.appendChild(t);
      });
    }
    search.addEventListener("input", fill);
    fill();
    setTimeout(function () { search.focus(); }, 30);
  }

  function renderCredits() {
    var back = el("button", "iml-tab", "← Bumalik"); back.type = "button";
    back.addEventListener("click", function () { activeTab = sets().length ? "sets" : "photos"; render(); });
    els.body.appendChild(back);
    var ul = el("ul", "iml-credits");
    DATA.photos.forEach(function (p) {
      var li = el("li");
      li.appendChild(document.createTextNode(p.fil + " (" + p.en + ") — " + p.credit.author + ", "));
      var lic = el("a", null, p.credit.license);
      if (p.credit.licenseUrl) { lic.href = p.credit.licenseUrl; lic.target = "_blank"; lic.rel = "noopener"; }
      li.appendChild(lic);
      li.appendChild(document.createTextNode(", "));
      var src = el("a", null, "Wikimedia Commons");
      src.href = p.credit.source; src.target = "_blank"; src.rel = "noopener";
      li.appendChild(src);
      ul.appendChild(li);
    });
    els.body.appendChild(ul);
  }

  function open(opts) {
    opts = opts || {};
    build();
    pickCb = opts.onPick || null;
    activeTab = opts.tab || (pickCb ? "photos" : (sets().length ? "sets" : "photos"));
    els.query = opts.query || "";
    activeCat = "all";
    store("local", "imglib_opened", "1");
    document.querySelectorAll(".iml-new").forEach(function (b) { b.remove(); });
    render();
    root.classList.add("show");
  }
  function hide() { if (root) root.classList.remove("show"); pickCb = null; }

  /* ---------------- first-time pop-up ---------------- */
  function maybeIntro() {
    if (!cfg.isEditor()) return;
    var hideKey = "imglib_intro_hide_" + cfg.template, seenKey = "imglib_intro_seen_" + cfg.template;
    if (store("local", hideKey) || store("session", seenKey)) return;
    store("session", seenKey, "1");
    injectCss();
    var ov = el("div", "iml-overlay show");
    ov.setAttribute("role", "dialog"); ov.setAttribute("aria-modal", "true");
    var box = el("div", "iml-intro");
    box.appendChild(el("h2", null, "✨ Bago! Image Library"));
    box.appendChild(el("p", null, "Nagmamadali? " + (INTRO[cfg.template] || INTRO._default)));
    var first = sets()[0];
    var ids = !first ? DATA.photos.slice(0, 4).map(function (p) { return p.id; })
      : first.groups ? first.groups.reduce(function (a, g) { return a.concat(g.items.slice(0, 2)); }, [])
      : first.pairs ? first.pairs.map(function (pr) { return pr[1]; })
      : first.items;
    ids = ids.map(idOf).filter(function (id, i, a) { return a.indexOf(id) === i; }).slice(0, 4);
    box.appendChild(thumbRow(ids));
    var actions = el("div", "iml-intro-actions");
    var tryBtn = el("button", "iml-primary", first ? "Tingnan ang Quick Sets" : "Buksan ang Library"); tryBtn.type = "button";
    var later = el("button", "iml-secondary", "Mamaya na"); later.type = "button";
    actions.appendChild(tryBtn); actions.appendChild(later);
    box.appendChild(actions);
    var lab = el("label");
    var cb = el("input"); cb.type = "checkbox";
    lab.appendChild(cb); lab.appendChild(document.createTextNode("Huwag nang ipakita ito"));
    box.appendChild(lab);
    ov.appendChild(box);
    function done() { if (cb.checked) store("local", hideKey, "1"); ov.remove(); }
    tryBtn.addEventListener("click", function () { done(); open({}); });
    later.addEventListener("click", done);
    ov.addEventListener("click", function (e) { if (e.target === ov) done(); });
    document.body.appendChild(ov);
    tryBtn.focus();
  }

  /* ---------------- template hooks ---------------- */
  function newBadge(btn) {
    if (!store("local", "imglib_opened")) btn.appendChild(el("span", "iml-new", "BAGO"));
    return btn;
  }

  window.ImageLibrary = {
    init: function (opts) {
      cfg = Object.assign(cfg, opts || {});
      randomHolders.forEach(fillRandom);   // now the template is known
      if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", function () { setTimeout(maybeIntro, 900); });
      else setTimeout(maybeIntro, 900);
    },
    open: open,
    // True when this template can build random sets (Quick Sets, "Per student").
    canPersonalize: function () { return !!cfg.applySet; },
    // {url, name} for one photo id — lets templates seed real-photo defaults.
    photo: function (id, inLang) {
      var p = PHOTO_BY_ID[id];
      return p ? { url: photoUrl(p), name: p[inLang || lang()] || p.fil } : null;
    },
    // A "Library" button to sit next to a row's Upload / Link tabs.
    tabButton: function (onPick, getQuery) {
      injectCss();
      var b = el("button", "iml-libtab", "Library"); b.type = "button";
      b.addEventListener("click", function () { open({ onPick: onPick, query: getQuery ? getQuery() : "" }); });
      return newBadge(b);
    },
    // Just the "🎲 Random na set" Level buttons (templates without Quick Sets).
    randomBox: function () { injectCss(); return randomBox(); },
    // Side-panel card content that opens the Quick Sets.
    launcher: function () {
      injectCss();
      var w = el("div", "iml-launch");
      w.appendChild(el("p", null, "Walang oras maghanap ng larawan? Pumili ng handang set ng totoong litrato."));
      var b = el("button", "iml-launch-btn", "📷 Buksan ang Image Library"); b.type = "button";
      b.addEventListener("click", function () { open({}); });
      w.appendChild(newBadge(b));
      w.appendChild(randomBox());
      return w;
    },
    // "Per student" activities (Level 🎲 in the Publish pop-up): replace every
    // slide with this learner's own random set at their level. The server
    // adds state._personal = { level, seed } for such activities (see
    // student_get_activity_content.php). Returns true if it did anything.
    personalize: function (state) {
      var p = state && state._personal;
      if (!state || state.level !== "auto" || !p || !cfg.applySet || !state.activities || !state.activities.length) return false;
      var level = +p.level === 2 || +p.level === 3 ? +p.level : 1;
      var keepIndex = state.currentIndex || 0, done = 0;
      rand = seeded(+p.seed || 1);
      // The learner's own English/Tagalog choice (activity audio settings)
      // wins; until they pick one, the language the teacher published in.
      var chosen = window.ActivityAudio && ActivityAudio.langChosen && ActivityAudio.langChosen() ? ActivityAudio.lang() : null;
      forcedLang = chosen || (state.levelLang === "en" || state.levelLang === "fil" ? state.levelLang : null);
      try {
        for (var i = 0; i < state.activities.length; i++) {
          state.currentIndex = i;
          var s = randomSet(level);
          if (!s) continue;
          cfg.applySet(s.raw ? s : resolveSet(s), setLang(s));
          done++;
        }
      } catch (e) {
        // A template that can't take a set keeps the teacher's own slides.
      } finally {
        rand = Math.random;
        forcedLang = null;
        state.currentIndex = keepIndex;
      }
      return done > 0;
    },
    isLibraryImage: function (src) { return typeof src === "string" && src.indexOf("/image_library/photos/") !== -1; },
    // First a–z letter of a word ("Itlog" → "i"), for beginning-letter templates.
    firstLetter: function (word) {
      var m = String(word || "").normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase().match(/[a-z]/);
      return m ? m[0] : "";
    },
    // A simple sentence about one picture, for "Build the Sentence" modes.
    sentence: function (word, inLang) {
      var w = String(word || "").trim();
      if (!w) return "";
      if ((inLang || lang()) === "en") {
        var lw = w.toLowerCase();
        // "Shoes", "Grapes" → plural; "Glass", "Octopus" stay singular.
        if (/[^su]s$/.test(lw)) return "These are " + lw + ".";
        if (/^(ice|milk|toothpaste|candy)$/.test(lw)) return "This is " + lw + ".";
        return "This is " + (/^[aeiou]/.test(lw) ? "an " : "a ") + lw + ".";
      }
      return "Ito ay " + w.toLowerCase() + ".";
    }
  };
})();
