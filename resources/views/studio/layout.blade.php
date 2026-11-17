<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="referrer" content="no-referrer">
<title>@yield('title', 'ID Studio')</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@stack('head')
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<header class="bg-white border-b border-slate-200 print:hidden"><div class="mx-auto max-w-7xl px-5 py-4 flex items-center justify-between flex-wrap gap-4">
  <div><a href="{{ route('studio.dashboard') }}" class="font-bold text-lg text-slate-900">ID Studio</a><div class="text-xs text-slate-500">{{ auth()->user()->studioCompany?->name }}</div></div>
  <nav class="flex gap-5 items-center text-sm font-medium"><a class="hover:text-indigo-600" href="{{ route('studio.dashboard') }}">Dashboard</a><a class="hover:text-indigo-600" href="{{ route('studio.employees.index') }}">Employees</a><a class="hover:text-indigo-600" href="{{ route('studio.templates.index') }}">ID Templates</a><a class="hover:text-indigo-600" href="{{ route('studio.cards.bulk.index') }}">Bulk Print</a><form action="{{ route('studio.logout') }}" method="POST">@csrf<button class="rounded-lg bg-slate-800 text-white px-3 py-2">Sign out</button></form></nav>
</div></header>
<main class="mx-auto max-w-7xl px-5 py-7">
@if(session('status'))<p class="mb-4 border-l-4 border-emerald-500 bg-emerald-50 px-4 py-3 text-sm">{{ session('status') }}</p>@endif
@if($errors->any())<div role="alert" class="mb-5 rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-800"><strong>Check these fields:</strong><ul class="list-disc ml-5 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</main>@stack('scripts')
</body></html>
