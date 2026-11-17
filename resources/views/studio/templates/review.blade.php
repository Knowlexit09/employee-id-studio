@extends('studio.layout')
@section('title','Template approval · '.$template->name)
@push('head')
<style>
.review-card{position:relative;background:white;border:1px solid #94a3b8;flex-shrink:0;overflow:hidden;background-repeat:no-repeat;background-size:100% 100%;print-color-adjust:exact}
.review-element{position:absolute;display:flex;align-items:center;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.12;overflow:hidden}
.review-element img{width:100%;height:100%;object-fit:contain}
</style>
@endpush
@section('content')
<div class="max-w-6xl space-y-5">
  <a class="text-indigo-700" href="{{ route('studio.templates.index') }}">← ID Templates</a>
  <h1 class="text-2xl font-bold">Independent template approval</h1>
  <section class="bg-white border rounded-xl p-6 space-y-3">
    <h2 class="font-semibold text-lg">{{ $template->name }}</h2>
    <p class="text-sm">Physical size: {{ $template->width_mm }} × {{ $template->height_mm }} mm</p>
    <p class="text-sm">Draft revision: {{ $template->revision }}</p>
    <p class="text-sm">Current approval status: <strong>{{ strtoupper($template->approval_status) }}</strong></p>
    <p class="text-sm">Requested by user #{{ $template->approval_requested_by ?? '—' }} · {{ $template->approval_requested_at?->format('Y-m-d H:i') ?? 'Not requested' }}</p>
    @if($template->approval_rejection_reason)
      <p class="text-red-700 text-sm">Previous rejection: {{ $template->approval_rejection_reason }}</p>
    @endif
    <a class="underline text-indigo-700 text-sm" href="{{ route('studio.templates.history',$template) }}">View published version history</a>
  </section>

  <section class="bg-white border rounded-xl p-6">
    <h2 class="text-lg font-bold mb-2">Read-only front and back preview</h2>
    <p class="text-sm text-slate-600 mb-5">Sample field labels appear instead of real employee information. Check alignment, logo, backgrounds, custom text, and visual privacy before approval. This screen cannot edit the layout.</p>
    <div class="flex gap-6 flex-wrap">
      @foreach(['front','back'] as $side)
        @php
          $page = $template->draft_layout[$side];
          $background = $assets->get($page['background_asset_id'] ?? 0);
          $logo = $assets->get($page['logo_asset_id'] ?? 0);
        @endphp
        <div>
          <h3 class="font-semibold text-sm mb-2">ID {{ $side }} preview</h3>
          <div class="overflow-auto max-w-full bg-slate-100 p-3 rounded-lg">
            <div class="review-card" style="width:{{ $template->width_mm }}mm;height:{{ $template->height_mm }}mm;@if($background)background-image:url('{{ route('studio.assets.show',$background) }}');@endif">
              @foreach($page['elements'] as $element)
                <div class="review-element" style="left:{{ $element['x'] }}mm;top:{{ $element['y'] }}mm;width:{{ $element['w'] }}mm;height:{{ $element['h'] }}mm;z-index:{{ $element['z'] }};font-family:{{ $element['fontFamily'] }};font-size:{{ $element['fontSize'] }}pt;font-weight:{{ $element['bold'] ? '700' : '400' }};font-style:{{ $element['italic'] ? 'italic' : 'normal' }};color:{{ $element['color'] }};text-align:{{ $element['align'] }}">
                  @if($element['type'] === 'text')
                    {{ $element['binding'] === 'custom' ? $element['text'] : strtoupper(str_replace('_',' ',$element['binding'])) }}
                  @elseif($element['type'] === 'qr')
                    [QR VERIFICATION]
                  @elseif($element['binding'] === 'logo' && $logo)
                    <img src="{{ route('studio.assets.show',$logo) }}" alt="Company logo artwork">
                  @else
                    [EMPLOYEE PHOTO]
                  @endif
                </div>
              @endforeach
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </section>

  @if($template->approval_status === 'pending')
    @if((int)$template->approval_requested_by === (int)auth()->id())
      <p role="alert" class="text-red-700">You cannot approve your own draft. A second manager or administrator must review it.</p>
    @else
      <form method="POST" action="{{ route('studio.templates.publish',$template) }}" class="bg-white border rounded-xl p-5 space-y-3">
        @csrf
        <input type="hidden" name="revision" value="{{ $template->revision }}">
        <label class="flex gap-2 text-sm items-start"><input type="checkbox" required name="review_confirmed" value="1" class="mt-1">I have reviewed both sides, background/logo artwork, layout placement and privacy.</label>
        <button class="bg-emerald-700 text-white py-3 px-5 rounded-lg" onclick="return confirm('Approve and publish this exact revision?')">Approve &amp; publish revision</button>
      </form>
      <form method="POST" action="{{ route('studio.templates.reject',$template) }}" class="bg-white border rounded-xl p-5 space-y-3">
        @csrf
        <input type="hidden" name="revision" value="{{ $template->revision }}">
        <label class="block text-sm font-semibold">Reason for rejection<textarea required minlength="5" maxlength="240" name="reason" rows="3" class="mt-2 border rounded-lg p-3 w-full"></textarea></label>
        <button class="border border-red-500 text-red-700 rounded-lg px-5 py-3">Reject revision</button>
      </form>
    @endif
  @else
    <p class="text-slate-500">No current revision is awaiting approval.</p>
  @endif
</div>
@endsection
