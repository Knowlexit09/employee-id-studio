@extends('studio.layout')
@section('title','Published template v'.$version->version)
@section('content')
<a class="text-indigo-700 text-sm" href="{{ route('studio.templates.history',$template) }}">← Version history</a>
<h1 class="text-2xl font-bold my-3">{{ $template->name }} · Version {{ $version->version }}</h1>
<section class="max-w-4xl bg-white border rounded-xl p-5 space-y-3">
  <p class="text-sm text-slate-600">Published: {{ $version->published_at?->format('Y-m-d H:i') }} · Approved by user #{{ $version->published_by }}</p>
  <p class="text-sm">Physical size: {{ $version->width_mm }} × {{ $version->height_mm }} mm</p>
  @foreach(['front','back'] as $side)
    <h2 class="font-semibold">{{ ucfirst($side) }}</h2>
    <table class="text-sm w-full"><thead><tr><th class="text-left">Field ID</th><th class="text-left">Binding</th><th class="text-right">X</th><th class="text-right">Y</th></tr></thead><tbody>
    @foreach($version->layout[$side]['elements'] as $element)
      <tr class="border-t"><td class="py-2">{{ $element['id'] }}</td><td>{{ $element['binding'] }}</td><td class="text-right">{{ $element['x'] }} mm</td><td class="text-right">{{ $element['y'] }} mm</td></tr>
    @endforeach
    </tbody></table>
  @endforeach
  <p class="text-xs text-slate-500">Read-only published layout. Sensitive employee values are not included here.</p>
</section>
@endsection
