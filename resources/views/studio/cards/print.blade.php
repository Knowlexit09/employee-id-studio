<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="referrer" content="no-referrer"><title>Print ID · {{ $fields['employee_code'] }}</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#e2e8f0;color:#0f172a;font-family:Arial,sans-serif}
.toolbar{padding:16px 24px;background:white;display:flex;gap:20px;align-items:center;flex-wrap:wrap}
.toolbar button{background:#4338ca;color:white;padding:10px 16px;border:0;border-radius:7px;cursor:pointer}
.sheet{display:flex;gap:10mm;flex-wrap:wrap;padding:16mm;justify-content:center}
.card{position:relative;background:white;flex-shrink:0;overflow:hidden;border:1px solid #cbd5e1;background-repeat:no-repeat;background-size:100% 100%;print-color-adjust:exact;-webkit-print-color-adjust:exact}
.id-item{position:absolute;overflow:hidden;display:flex;align-items:center;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.12}
.card-invalid-overlay{position:absolute;inset:0;z-index:1001;display:flex;align-items:center;justify-content:center;color:#991b1b;background:#ffffffad;font-weight:900;font-size:15pt;letter-spacing:.15em;text-shadow:1px 1px white;pointer-events:none}
.id-item img{width:100%;height:100%;object-fit:cover}.id-item.logo img{object-fit:contain}.id-item svg{height:100%;width:100%;display:block}
@page{size:A4;margin:0}
@media print{body{background:white}.toolbar{display:none}.sheet{justify-content:flex-start;align-items:flex-start;padding:10mm;gap:8mm}.card{border:0;break-inside:avoid}}
</style></head><body>
<div class="toolbar"><button onclick="window.print()">Print / Save as PDF</button><a href="{{ route('studio.cards.form',$record->employee_id) }}">Back to employee</a><a href="{{ route('studio.cards.calibration') }}" target="_blank" rel="noopener noreferrer">Print size calibration</a><span>Set print scale to <b>100% / actual size</b>, disable browser headers/footers and print backgrounds. {{ $record->status === 'active' ? '' : 'REVOKED: reprinting for historical reference only.' }}</span></div>
<div class="sheet">
@foreach(['front','back'] as $side)
@include('studio.cards._card', ['record'=>$record, 'assets'=>$assets, 'fields'=>$fields, 'qrSvg'=>$qrSvg, 'side'=>$side])
@endforeach
</div></body></html>
