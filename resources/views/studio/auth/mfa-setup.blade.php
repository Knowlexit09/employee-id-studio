<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="no-referrer"><title>Set up authenticator · ID Studio</title>@vite(['resources/css/app.css'])</head>
<body class="min-h-screen bg-slate-100 grid place-items-center p-5"><main class="bg-white border rounded-xl shadow-sm w-full max-w-lg p-7">
<h1 class="text-2xl font-bold mb-2">Set up two-factor authentication</h1>
<p class="text-sm text-slate-600 mb-4">Open an authenticator app, scan this QR code or enter the secret manually. The code changes every 30 seconds. Keep the secret private.</p>
<div class="flex justify-center my-4" role="img" aria-label="Authenticator enrollment QR">{!! $qrSvg !!}</div>
<p class="text-xs text-slate-500">Manual setup key</p><p class="font-mono break-all tracking-wide bg-slate-100 p-3 rounded" id="mfa-setup-secret">{{ $secret }}</p>
@if($errors->any())<div class="text-red-700 text-sm mt-3" role="alert">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('studio.mfa.confirm') }}" class="space-y-3 mt-5">@csrf
<label class="block text-sm font-medium">Six-digit authenticator code<input type="text" name="code" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required class="mt-2 block border rounded-lg w-full p-3" autofocus></label>
<button type="submit" class="bg-indigo-700 text-white font-semibold px-6 py-3 rounded-lg w-full">Enable MFA</button>
</form>
<form method="POST" action="{{ route('studio.logout') }}" class="mt-3 text-center">@csrf<button class="text-slate-500 underline text-sm">Sign out</button></form>
</main></body></html>
