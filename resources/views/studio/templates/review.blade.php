@extends('studio.layout')
@section('title','Template approval · '.$template->name)
@section('content')
<div class="max-w-4xl space-y-5">
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
    @foreach(['front','back'] as $side)
      <p class="text-sm">{{ ucfirst($side) }}: {{ count($template->draft_layout[$side]['elements'] ?? []) }} layout fields</p>
    @endforeach
    <p class="text-xs text-slate-500">Check the layout, artwork and privacy with the designer before approving. Published designs are immutable, but uploaded artwork must be inspected manually.</p>
    <a class="underline text-indigo-700 text-sm" href="{{ route('studio.templates.history',$template) }}">View published version history</a>
  </section>
  @if($template->approval_status === 'pending')
    @if((int)$template->approval_requested_by === (int)auth()->id())
      <p role="alert" class="text-red-700">You cannot approve your own draft. A second manager or administrator must review it.</p>
    @else
      <form method="POST" action="{{ route('studio.templates.publish',$template) }}" class="bg-white border rounded-xl p-5">
        @csrf
        <input type="hidden" name="revision" value="{{ $template->revision }}">
        <button class="bg-emerald-700 text-white py-3 px-5 rounded-lg" onclick="return confirm('Approve and publish this revision?')">Approve &amp; publish revision</button>
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
