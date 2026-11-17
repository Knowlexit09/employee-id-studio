@extends('studio.layout')
@section('title','ID Templates · ID Studio')
@section('content')
<div class="flex flex-wrap justify-between items-end gap-3">
    <div>
        <h1 class="text-2xl font-bold">ID Templates</h1>
        <p class="text-slate-500 text-sm mt-1">Design, request independent approval, and track immutable published versions.</p>
    </div>
</div>
@if(auth()->user()->canDesignStudio())
<form method="POST" action="{{ route('studio.templates.create') }}" class="bg-white rounded-xl border p-5 my-6 flex flex-wrap gap-3 items-end">
    @csrf
    <label class="text-sm">Template name<input required name="name" maxlength="120" placeholder="Office ID 2026" class="block mt-1 p-2.5 rounded-lg border w-64"></label>
    <label class="text-sm">Width (mm)<input required type="number" step="0.01" min="40" max="220" name="width_mm" value="85.60" class="block mt-1 p-2.5 border rounded-lg w-28"></label>
    <label class="text-sm">Height (mm)<input required type="number" step="0.01" min="30" max="310" name="height_mm" value="53.98" class="block mt-1 p-2.5 border rounded-lg w-28"></label>
    <button class="rounded-lg bg-indigo-700 text-white px-5 py-2.5">+ Create template</button>
</form>
@endif
<div class="rounded-xl border bg-white overflow-x-auto my-6">
<table class="w-full text-sm">
    <thead class="text-left bg-slate-50"><tr>
        <th class="p-4">Template</th><th class="p-4">Size</th><th class="p-4">Draft revision</th>
        <th class="p-4">Approval</th><th class="p-4">Published</th><th class="p-4">Actions</th>
    </tr></thead>
    <tbody>
    @forelse($templates as $template)
      <tr class="border-t">
        <td class="p-4 font-semibold">{{ $template->name }}</td>
        <td class="p-4">{{ $template->width_mm }} × {{ $template->height_mm }} mm</td>
        <td class="p-4">{{ $template->revision }}</td>
        <td class="p-4">{{ ucfirst($template->approval_status) }}</td>
        <td class="p-4">{{ $template->published_version_id ? 'Yes' : 'No' }}</td>
        <td class="p-4">
            @if(auth()->user()->canDesignStudio())
                <a class="text-indigo-700 mr-3" href="{{ route('studio.templates.edit',$template) }}">Designer</a>
            @endif
            @if(auth()->user()->canApproveStudioTemplate() && $template->approval_status === 'pending')
                <a class="text-indigo-700 mr-3" href="{{ route('studio.templates.review',$template) }}">Review request</a>
            @endif
            <a class="text-indigo-700" href="{{ route('studio.templates.history',$template) }}">History</a>
        </td>
      </tr>
    @empty
      <tr><td colspan="6" class="p-8 text-center text-slate-500">No templates yet</td></tr>
    @endforelse
    </tbody>
</table>
</div>
{{ $templates->links() }}
@endsection
