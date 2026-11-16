@extends('studio.layout')
@section('title','Designer · '.$template->name)
@push('head')
<style>
.studio-panel{background:white;border:1px solid #e2e8f0;border-radius:14px;padding:18px}
.studio-label{display:block;color:#475569;font-size:12px;font-weight:650;margin-bottom:5px}
.studio-input{width:100%;border:1px solid #cbd5e1;border-radius:7px;padding:8px;font-size:13px;background:white}
.studio-btn{border:1px solid #cbd5e1;border-radius:8px;padding:9px 12px;background:white;font-size:13px;font-weight:600}
.studio-btn:hover{background:#f1f5f9}.studio-btn-primary{background:#4338ca;color:white;border-color:#4338ca}
.studio-btn-primary:hover{background:#3730a3}.studio-btn:disabled{opacity:.45;cursor:not-allowed}
#id-canvas{position:relative;background-color:white;box-shadow:0 12px 30px #0f172a27;overflow:hidden;touch-action:none;user-select:none}
.studio-element{position:absolute;outline:1px dashed transparent;cursor:move;overflow:hidden;display:flex;align-items:center;line-height:1.12;white-space:pre-wrap;overflow-wrap:anywhere}
.studio-element.selected{outline:2px solid #4f46e5;outline-offset:0;z-index:999!important}
.studio-element.locked{cursor:default}.studio-handle{position:absolute;right:0;bottom:0;width:12px;height:12px;background:#4f46e5;border:2px solid white;cursor:nwse-resize;z-index:10}
.layer-item{width:100%;display:flex;align-items:center;justify-content:space-between;text-align:left;padding:8px;border-radius:8px;font-size:12px}
.layer-item.active{background:#eef2ff;color:#3730a3}.layer-item:hover{background:#f1f5f9}
#id-canvas::after{content:'';position:absolute;inset:0;border:1px solid #d1d5db;pointer-events:none}
</style>
@endpush
@section('content')
<div class="flex flex-wrap justify-between items-end gap-3 mb-5"><div><a class="text-indigo-700 text-sm" href="{{ route('studio.templates.index') }}">← Templates</a><h1 class="text-2xl font-bold mt-2">{{ $template->name }}</h1><p class="text-sm text-slate-500">{{ $template->width_mm }} × {{ $template->height_mm }} mm · draft revision <span id="revision-label">{{ $template->revision }}</span> · published versions never mutate</p></div>
<div class="flex gap-2"><button type="button" id="save-template" class="studio-btn studio-btn-primary">Save draft</button><button type="button" id="publish-template" class="studio-btn">Publish version</button></div></div>
<div class="grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)_260px] items-start" id="studio-designer">
<aside class="studio-panel space-y-3"><h2 class="font-bold text-slate-800">Add field</h2>
<button type="button" data-add="text" class="studio-btn block w-full text-left">+ Text / dynamic field</button><button type="button" data-add="image" class="studio-btn block w-full text-left">+ Image / photo</button><button type="button" data-add="qr" class="studio-btn block w-full text-left">+ QR verification</button>
<hr><h3 class="font-bold text-sm">Layers <span id="layer-count" class="text-slate-400"></span></h3><div id="layers" class="space-y-1 max-h-64 overflow-y-auto"></div>
<div class="border-t pt-3 flex flex-wrap gap-2"><button type="button" id="duplicate-element" class="studio-btn">Duplicate</button><button type="button" id="delete-element" class="studio-btn">Delete</button><button type="button" id="lock-element" class="studio-btn">Lock</button></div>
<div class="border-t pt-3 space-y-3"><h3 class="font-bold text-sm">Card assets</h3><label class="studio-label">Upload kind<select id="asset-kind" class="studio-input"><option value="background">Background</option><option value="logo">Logo</option></select></label><input id="asset-file" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-xs"><button type="button" class="studio-btn w-full" id="upload-asset">Upload and apply</button>
<label class="studio-label">Background (current side)<select id="background-id" class="studio-input"></select></label><label class="studio-label">Logo (current side)<select id="logo-id" class="studio-input"></select></label></div>
</aside>
<section class="studio-panel min-w-0"><div class="flex flex-wrap justify-between gap-3 items-center mb-4"><div class="flex bg-slate-100 rounded-lg p-1"><button type="button" class="studio-btn" data-side="front">Front</button><button type="button" class="studio-btn" data-side="back">Back</button></div><label class="text-xs flex items-center gap-2"><input type="checkbox" id="snap-grid" checked> Snap to 0.5 mm</label></div>
<div class="overflow-auto rounded-xl bg-slate-200 p-6" style="min-height:430px"><div id="id-canvas" aria-label="ID design canvas"></div></div>
<p class="text-xs text-slate-500 mt-3">Drag a field to move it. Drag its bottom-right handle to resize. Use numeric controls for exact millimeter measurements. Changes require Save draft, then Publish.</p>
<div role="status" aria-live="polite" id="designer-status" class="text-sm mt-4 text-indigo-700">Ready.</div></section>
<aside class="studio-panel space-y-3"><h2 class="font-bold">Field properties</h2><p id="no-selection" class="text-xs text-slate-500">Select a field on the canvas.</p>
<div id="property-panel" hidden class="space-y-3">
<label class="studio-label">Field binding<select id="prop-binding" class="studio-input"></select></label>
<label class="studio-label" id="custom-text-label">Custom text<textarea id="prop-text" rows="3" maxlength="240" class="studio-input"></textarea></label>
<div class="grid grid-cols-2 gap-2">@foreach(['x'=>'X (mm)','y'=>'Y (mm)','w'=>'Width (mm)','h'=>'Height (mm)'] as $key=>$label)<label class="studio-label">{{ $label }}<input id="prop-{{ $key }}" data-number="{{ $key }}" type="number" step="0.1" min="0" class="studio-input"></label>@endforeach</div>
<div id="font-properties" class="space-y-3"><label class="studio-label">Font family<select id="prop-fontFamily" class="studio-input">@foreach(config('studio.font_families') as $family)<option value="{{ $family }}">{{ $family }}</option>@endforeach</select></label><label class="studio-label">Font size (pt)<input type="number" min="5" max="42" step="0.5" id="prop-fontSize" class="studio-input"></label><div class="flex gap-4 text-sm"><label><input id="prop-bold" type="checkbox"> <b>Bold</b></label><label><input id="prop-italic" type="checkbox"> <i>Italic</i></label></div><label class="studio-label">Text color<input id="prop-color" type="color" class="w-full h-9 rounded" value="#0f172a"></label><label class="studio-label">Text alignment<select id="prop-align" class="studio-input"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></label></div>
</div><div class="border-t pt-3"><div class="text-xs text-slate-500">Sensitive government numbers are intentionally excluded from ID printing.</div></div></aside>
</div>
<form id="archive-template-form" class="mt-8" method="POST" action="{{ route('studio.templates.archive',$template) }}" onsubmit="return confirm('Archive this template? Existing issued IDs remain unchanged.')">@csrf<button class="text-xs text-red-700 underline">Archive template</button></form>
@endsection
@push('scripts')
<script id="id-studio-config" type="application/json">@json([
    'layout'=>$template->draft_layout,'revision'=>$template->revision,
    'cardWidth'=>(float)$template->width_mm,'cardHeight'=>(float)$template->height_mm,
    'saveUrl'=>route('studio.templates.save',$template),
    'publishUrl'=>route('studio.templates.publish',$template),
    'uploadUrl'=>route('studio.templates.assets',$template),
    'assets'=>$assets->map(fn($a)=>['id'=>$a->id,'kind'=>$a->kind,'url'=>route('studio.assets.show',$a)])->values(),
])</script>
@vite('resources/js/id-studio-designer.js')
@endpush
