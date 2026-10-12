/* ─────────────────────────────────────────────────────────────
 * Image Shrink — loaded by every activity template.
 *
 * The templates store a picture the teacher picks for an item as a data
 * URL inside the activity (FileReader.readAsDataURL). A phone photo is
 * 3–5 MB — ~6 MB as text — so a few of them made activities tens of MB:
 * slow for every student who opens one, and too big for the database to
 * save. Items are shown a few hundred pixels wide, so this makes
 * readAsDataURL hand back a resized copy (longest side ≤ MAX_SIDE) instead
 * of the original whenever an image file is bigger than MIN_BYTES.
 *
 * Done here once, in FileReader itself, so every upload in every template
 * gets it without each one being edited. Small images, GIFs (could be
 * animated) and anything that fails to decode go through untouched, and
 * PNGs with transparency stay PNG (JPEG would turn see-through parts black).
 * ───────────────────────────────────────────────────────────── */
(function () {
  if (window.ImageShrink || !window.FileReader || !window.HTMLCanvasElement) return;

  var MAX_SIDE = 1000, MIN_BYTES = 350 * 1024, JPEG_Q = 0.82;
  var SHRINKABLE = /^image\/(jpeg|jpg|png|webp|bmp)$/i;
  var nativeRead = FileReader.prototype.readAsDataURL;

  function hasTransparency(ctx, w, h) {
    try {
      var d = ctx.getImageData(0, 0, w, h).data;
      for (var i = 3; i < d.length; i += 16) if (d[i] < 250) return true;   // every 4th pixel is plenty
    } catch (e) {}
    return false;
  }

  // Blob → Promise<dataURL> of a smaller copy (rejects if it can't decode).
  function shrink(blob) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(blob);
      var img = new Image();
      img.onload = function () {
        URL.revokeObjectURL(url);
        var w = img.naturalWidth, h = img.naturalHeight;
        if (!w || !h) { reject(new Error('empty image')); return; }
        var k = Math.min(1, MAX_SIDE / Math.max(w, h));
        var cv = document.createElement('canvas');
        cv.width = Math.max(1, Math.round(w * k));
        cv.height = Math.max(1, Math.round(h * k));
        var ctx = cv.getContext('2d');
        ctx.drawImage(img, 0, 0, cv.width, cv.height);
        var keepPng = /png/i.test(blob.type) && hasTransparency(ctx, cv.width, cv.height);
        var out;
        if (keepPng) {
          out = cv.toDataURL('image/png');
        } else {
          // JPEG has no transparency — paint white under the picture first.
          var flat = document.createElement('canvas');
          flat.width = cv.width; flat.height = cv.height;
          var fctx = flat.getContext('2d');
          fctx.fillStyle = '#ffffff';
          fctx.fillRect(0, 0, flat.width, flat.height);
          fctx.drawImage(cv, 0, 0);
          out = flat.toDataURL('image/jpeg', JPEG_Q);
          flat.width = flat.height = 0;
        }
        cv.width = cv.height = 0;
        resolve(out);
      };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('cannot decode')); };
      img.src = url;
    });
  }

  FileReader.prototype.readAsDataURL = function (blob) {
    var reader = this;
    if (!blob || !SHRINKABLE.test(blob.type || '') || !(blob.size > MIN_BYTES)) {
      return nativeRead.call(reader, blob);
    }
    shrink(blob).then(function (dataUrl) {
      // Behave like a finished read: `result` holds the smaller copy, and
      // load/loadend fire (onload handlers and listeners both see ev.target === reader).
      try {
        Object.defineProperty(reader, 'result', { value: dataUrl, configurable: true });
        Object.defineProperty(reader, 'readyState', { value: 2, configurable: true });
      } catch (e) { nativeRead.call(reader, blob); return; }
      reader.dispatchEvent(new ProgressEvent('load', { lengthComputable: true, loaded: dataUrl.length, total: dataUrl.length }));
      reader.dispatchEvent(new ProgressEvent('loadend', { lengthComputable: true, loaded: dataUrl.length, total: dataUrl.length }));
    }, function () {
      nativeRead.call(reader, blob);   // couldn't shrink — keep the original
    });
  };

  window.ImageShrink = { shrink: shrink, MAX_SIDE: MAX_SIDE, MIN_BYTES: MIN_BYTES };
})();
