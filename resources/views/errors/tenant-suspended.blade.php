<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>Temporarily unavailable</title>
    <style>body{font:16px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f8fafc;color:#0f172a;display:grid;place-items:center;min-height:100vh;margin:0;padding:24px}.box{max-width:460px;text-align:center}h1{font-size:28px;margin:0 0 8px}p{color:#64748b}a{display:inline-block;margin-top:16px;padding:10px 18px;border-radius:8px;background:#2563eb;color:#fff;text-decoration:none}</style>
</head>
<body>
<div class="box">
    <h1>Temporarily unavailable</h1>
    <p>{{ $tenant->name ?? 'This site' }} is paused right now. If you run this site, sign in and renew your plan to reactivate it straight away.</p>
    <a href="/{{ trim((string) config('starter.admin_path', 'admin'), '/') }}/billing">Sign in and renew</a>
</div>
</body>
</html>
