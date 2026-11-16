<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · ID Studio</title>@vite(['resources/css/app.css'])</head>
<body class="bg-slate-100 min-h-screen grid place-items-center p-5"><main class="bg-white p-8 rounded-2xl border max-w-md w-full shadow-sm">
<div class="text-xs uppercase font-semibold tracking-widest text-indigo-700">Employee ID Management</div><h1 class="text-3xl font-bold mt-2">Welcome back</h1><p class="text-sm text-slate-500 mt-2">Administrator and HR sign-in only. No public registration.</p>
@if($errors->any())<p role="alert" class="mt-4 text-sm text-red-700">{{ $errors->first() }}</p>@endif
<form action="{{ route('studio.login.submit') }}" method="POST" class="mt-6 space-y-4">@csrf
<label class="block text-sm font-medium">Email<input name="email" type="email" autocomplete="username" required value="{{ old('email') }}" class="block mt-1 border rounded-lg w-full p-3"></label>
<label class="block text-sm font-medium">Password<input name="password" type="password" autocomplete="current-password" required class="block mt-1 border rounded-lg w-full p-3"></label>
<button class="bg-indigo-700 hover:bg-indigo-800 text-white w-full py-3 rounded-lg font-semibold">Sign in</button>
</form></main></body></html>
