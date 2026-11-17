<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="referrer" content="no-referrer"><title>Bulk ID printing · {{ count($entries) }} cards</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#e2e8f0;color:#0f172a;font-family:Arial,sans-serif}
.toolbar{background:white;padding:14px 22px;display:flex;gap:18px;flex-wrap:wrap;align-items:center}
.toolbar button{background:#4338ca;border:0;border-radius:7px;padding:10px 16px;color:white;cursor:pointer}
.print-sheet{padding:12mm;display:flex;flex-wrap:wrap;gap:8mm;align-items:flex-start;justify-content:flex-start}
.id-pair{display:flex;gap:4mm;flex-wrap:wrap;align-items:flex-start;break-inside:avoid;page-break-inside:avoid;padding:3mm;border:1px dashed #94a3b8}
.id-pair-label{flex-basis:100%;font-size:12px;color:#475569}
.card{position:relative;background:white;flex-shrink:0;overflow:hidden;border:1px solid #cbd5e1;background-repeat:no-repeat;background-size:100% 100%;print-color-adjust:exact;-webkit-print-color-adjust:exact}
.id-item{position:absolute;overflow:hidden;display:flex;align-items:center;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.12}
.card-invalid-overlay{position:absolute;inset:0;z-index:1001;display:flex;align-items:center;justify-content:center;color:#991b1b;background:#ffffffad;font-weight:900;font-size:15pt;letter-spacing:.15em;text-shadow:1px 1px white;pointer-events:none}
.id-item img{width:100%;height:100%;object-fit:cover}.id-item.logo img{object-fit:contain}.id-item svg{height:100%;width:100%;display:block}
@page{size:A4;margin:6mm}
@media print{body{background:white}.toolbar,.id-pair-label{display:none}.print-sheet{padding:0;gap:4mm}.id-pair{padding:0;border:0;gap:4mm}.card{border:0;break-inside:avoid}}
</style></head><body>
<div class="toolbar"><button type="button" onclick="window.print()">Print / Save as PDF</button>
<a href="{{ route('studio.cards.bulk.index') }}">Back to selection</a>
<span>{{ count($entries) }} selected cards · Set print scale to <b>100% (actual size)</b>; disable browser headers/footers and print backgrounds. Front and back are separate cutouts, not automatically duplex-aligned. Larger card sizes may require larger paper.</span></div>
<div class="print-sheet">
@foreach($entries as $entry)
    @php($record=$entry['record'])
    @php($assets=$entry['assets'])
    @php($fields=$entry['fields'])
    @php($qrSvg=$entry['qrSvg'])
    <section class="id-pair" aria-label="Employee ID {{ $fields['employee_code'] }}">
        <div class="id-pair-label">{{ $fields['employee_code'] }} — {{ $fields['full_name'] }} · Front / Back</div>
        @foreach(['front','back'] as $side)
            @include('studio.cards._card', ['record'=>$record, 'assets'=>$assets, 'fields'=>$fields, 'qrSvg'=>$qrSvg, 'side'=>$side])
        @endforeach
    </section>
@endforeach
</div></body></html>
