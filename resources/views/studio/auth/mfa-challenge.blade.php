<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="no-referrer"><title>Verify sign-in · ID Studio</title>@vite(['resources/css/app.css'])</head>
<body class="min-h-screen bg-slate-100 grid place-items-center p-5"><main class="bg-white border rounded-xl shadow-sm w-full max-w-md p-7">
<h1 class="text-2xl font-bold">Two-factor verification</h1>
<p class="text-sm text-slate-600 mt-2">Enter the current six-digit code from your authenticator, or an unused recovery code.</p>
@if($errors->any())<div class="text-red-700 text-sm mt-3" role="alert">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('studio.mfa.verify') }}" class="space-y-4 mt-5">@csrf
<label class="block text-sm font-medium">Authenticator or recovery code<input name="code" type="text" autocomplete="one-time-code" required maxlength="40" class="mt-2 block border rounded-lg w-full p-3" autofocus></label>
<button type="submit" class="bg-indigo-700 text-white px-6 py-3 rounded-lg w-full font-semibold">Verify and continue</button>
</form>
<form method="POST" action="{{ route('studio.logout') }}" class="mt-4 text-center">@csrf<button class="text-slate-500 underline text-sm">Sign out</button></form>
</main></body></html>
