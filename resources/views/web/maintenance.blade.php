<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Back soon · {{ setting('site_name', config('app.name')) }}</title>
    @vite(['resources/web/web.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 p-6 font-sans">
    <main class="max-w-lg text-center">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-600 text-xl font-bold text-white">{{ initials(setting('site_name', config('app.name'))) }}</span>
        <h1 class="mt-6 text-3xl font-bold text-ink">We’ll be right back</h1>
        <p class="mt-3 text-lg text-slate-600">{{ $message }}</p>
        @if ($email = setting('contact_email'))
            <p class="mt-6 text-sm text-slate-500">Need help now? Email <a class="font-semibold text-brand-700" href="mailto:{{ $email }}">{{ $email }}</a></p>
        @endif
    </main>
</body>
</html>
