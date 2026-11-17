@extends('studio.layout')
@section('title','Bulk ID printing · ID Studio')
@section('content')
<div class="flex flex-wrap justify-between items-center gap-3 mb-5">
    <div>
        <h1 class="text-2xl font-bold">Bulk ID printing</h1>
        <p class="text-sm text-slate-500">Print up to 24 existing, active, unexpired IDs at once. Each ID keeps its original design and QR code.</p>
    </div>
    <a href="{{ route('studio.employees.index') }}" class="text-indigo-700 text-sm">← Employees</a>
</div>
<form method="POST" action="{{ route('studio.cards.bulk.print') }}" class="space-y-4">
    @csrf
    <div class="rounded-xl border bg-white overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-700">
                <tr><th class="px-4 py-3">Select</th><th class="px-4 py-3">Employee</th><th class="px-4 py-3">Number</th><th class="px-4 py-3">Issue</th><th class="px-4 py-3">Version</th><th class="px-4 py-3">Expires</th></tr>
            </thead>
            <tbody>
                @forelse($issues as $issue)
                    <tr class="border-t">
                        <td class="px-4 py-3"><input aria-label="Print {{ $issue->employee?->employee_code }}" type="checkbox" name="ids[]" value="{{ $issue->id }}" class="w-4 h-4" @checked(in_array($issue->id, old('ids', [])))></td>
                        <td class="px-4 py-3 font-semibold">{{ $issue->employee?->full_name }}</td>
                        <td class="px-4 py-3 font-mono">{{ $issue->employee?->employee_code }}</td>
                        <td class="px-4 py-3">{{ $issue->issued_at?->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">v{{ $issue->version?->version }}</td>
                        <td class="px-4 py-3">{{ $issue->expires_at?->format('Y-m-d') ?? 'None' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center text-slate-500">No active, unexpired IDs to print. Issue IDs first.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="flex flex-wrap gap-4 items-center">
        <button type="submit" @disabled($issues->isEmpty()) class="rounded-lg bg-indigo-700 text-white px-5 py-3 disabled:opacity-50">Preview / Print selected IDs</button>
        <span class="text-xs text-slate-500">Only selections on this page are submitted. Empty, duplicate or cross-company selections are rejected.</span>
    </div>
</form>
<div class="mt-5">{{ $issues->links() }}</div>
@endsection
