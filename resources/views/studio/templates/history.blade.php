@extends('studio.layout')
@section('title','Template history · '.$template->name)
@section('content')
<a class="text-indigo-700 text-sm" href="{{ route('studio.templates.index') }}">← Templates</a>
<h1 class="text-2xl font-bold mt-3 mb-2">{{ $template->name }} · Published history</h1>
<p class="text-sm text-slate-500 mb-5">Published versions never change. Updating a draft does not alter ID cards already issued.</p>
<div class="bg-white rounded-xl border overflow-x-auto">
<table class="w-full text-left text-sm">
  <thead class="bg-slate-50"><tr><th class="p-4">Version</th><th class="p-4">Published at</th><th class="p-4">Approved by</th><th class="p-4">Physical size</th><th class="p-4">View</th></tr></thead>
  <tbody>
  @forelse($versions as $version)
    <tr class="border-t"><td class="p-4">v{{ $version->version }}</td><td class="p-4">{{ $version->published_at?->format('Y-m-d H:i') }}</td><td class="p-4">User #{{ $version->published_by }}</td><td class="p-4">{{ $version->width_mm }} × {{ $version->height_mm }} mm</td><td class="p-4"><a class="text-indigo-700" href="{{ route('studio.templates.history-version',[$template,$version->version]) }}">Details</a></td></tr>
  @empty
    <tr><td class="p-8 text-slate-500" colspan="5">No published versions yet. Submit a draft for approval.</td></tr>
  @endforelse
  </tbody>
</table></div>
<div class="mt-5">{{ $versions->links() }}</div>
@endsection
