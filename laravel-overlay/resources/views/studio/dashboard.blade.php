@extends('studio.layout')
@section('title','Dashboard · ID Studio')
@section('content')
<div class="flex justify-between items-center mb-6"><div><p class="text-indigo-700 font-semibold text-sm">WORKSPACE OVERVIEW</p><h1 class="text-3xl font-bold">Employee ID Studio</h1><p class="text-slate-500 mt-1">Design, issue, and verify identification cards</p></div></div>
<div class="grid gap-4 sm:grid-cols-3">
@foreach([['Employees',$employees],['Templates',$templates],['Active ID cards',$issued]] as [$label,$number])
<div class="bg-white rounded-xl border p-6"><p class="text-sm text-slate-500">{{ $label }}</p><p class="text-4xl font-bold mt-2">{{ number_format($number) }}</p></div>
@endforeach
</div><div class="flex gap-3 mt-8"><a class="rounded-lg bg-indigo-700 text-white px-5 py-3 font-medium" href="{{ route('studio.employees.index') }}">Manage employees</a><a class="rounded-lg border border-slate-300 bg-white px-5 py-3 font-medium" href="{{ route('studio.templates.index') }}">Open template designer</a></div>
@endsection
