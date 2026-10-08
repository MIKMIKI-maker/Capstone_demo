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
  function lang() { return store("local", "imglib_lang") === "en" ? "en" : "fil"; }
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
        if (!confirm("Papalitan nito ang laman ng kasalukuyang slide. Ituloy?")) return;
        cfg.applySet(resolveSet(s), setLang(s));
        hide();
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
      extras: s.extras ? s.extras.map(item) : null
    };
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
      if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", function () { setTimeout(maybeIntro, 900); });
      else setTimeout(maybeIntro, 900);
    },
    open: open,
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
    // Side-panel card content that opens the Quick Sets.
    launcher: function () {
      injectCss();
      var w = el("div", "iml-launch");
      w.appendChild(el("p", null, "Walang oras maghanap ng larawan? Pumili ng handang set ng totoong litrato."));
      var b = el("button", "iml-launch-btn", "📷 Buksan ang Image Library"); b.type = "button";
      b.addEventListener("click", function () { open({}); });
      w.appendChild(newBadge(b));
      return w;
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
