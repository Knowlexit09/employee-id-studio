<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="no-referrer"><title>Save recovery codes · ID Studio</title>@vite(['resources/css/app.css'])</head>
<body class="min-h-screen bg-slate-100 grid place-items-center p-5"><main class="bg-white border rounded-xl shadow-sm w-full max-w-lg p-7">
<h1 class="text-2xl font-bold">Save your recovery codes</h1>
<p class="text-sm text-slate-600 mt-2">Store these eight one-time recovery codes offline in a secure password manager. Each code works only once. They will not be shown again.</p>
<pre class="font-mono p-4 bg-slate-100 rounded-lg whitespace-pre-wrap mt-4">{{ implode("\n", $codes) }}</pre>
<a class="mt-5 block text-center bg-indigo-700 text-white py-3 rounded-lg font-semibold" href="{{ route('studio.dashboard') }}">I saved my recovery codes</a>
</main></body></html>
