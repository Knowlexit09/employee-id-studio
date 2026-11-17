<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="referrer" content="no-referrer">
<title>ID Studio · Print calibration sheet</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#f1f5f9;color:#0f172a;font:15px system-ui,Arial,sans-serif}
.toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:15px;background:white;padding:16px 24px}
button{padding:10px 18px;border:0;border-radius:8px;background:#4338ca;color:white;cursor:pointer}
article{max-width:900px;margin:20px auto;padding:20px 25px;background:white}
.measurement{width:50mm;height:50mm;border:0.3mm solid #111827;position:relative;print-color-adjust:exact;-webkit-print-color-adjust:exact}
.measurement::before,.measurement::after{content:'';position:absolute;background:#94a3b8}
.measurement::before{width:.15mm;height:100%;left:50%;top:0}
.measurement::after{height:.15mm;width:100%;top:50%;left:0}
.ruler{width:100mm;height:1mm;background:#111827;margin:12mm 0 3mm;print-color-adjust:exact;-webkit-print-color-adjust:exact}
.test-label{font:12px Arial,sans-serif;color:#334155;margin:3mm 0}
@page{size:A4;margin:10mm}
@media print{
  body{background:white}
  .toolbar,.instructions{display:none}
  article{margin:0;padding:0;max-width:none}
}
</style>
</head>
<body>
<header class="toolbar">
  <button type="button" onclick="window.print()">Print calibration sheet</button>
  <a href="{{ route('studio.templates.index') }}">Back to templates</a>
  <span>Use A4 paper · 100% / Actual size · no Fit to page · headers and footers OFF</span>
</header>
<article>
  <h1>Employee ID Studio · Physical size calibration</h1>
  <p class="instructions">Print this sheet using the same browser, printer, print quality and paper settings as your ID cards.
    Measure the outside edges of the square with a physical ruler. A screen ruler does not prove printed dimensions.</p>
  <div class="measurement" aria-label="A physical square measuring 50 millimeters on each side"></div>
  <p class="test-label">Square outside: 50 mm × 50 mm</p>
  <div class="ruler" aria-label="100 millimeter horizontal calibration line"></div>
  <p class="test-label">Bar length: 100 mm</p>
  <section class="instructions">
    <h2>Calibration checklist</h2>
    <ol>
      <li>Measure the printed square (50 mm on each side) and horizontal bar (100 mm).</li>
      <li>If measurements do not match, disable printer-driver scaling, Fit to page and browser scaling, then repeat.</li>
      <li>Test front/back orientation and cut accuracy on plain paper before using PVC card stock or preprinted sheets.</li>
      <li>This sheet is not an automatic printer adjustment. Printer margins, duplex feeding and cut tolerances vary.</li>
    </ol>
  </section>
</article>
</body>
</html>
