<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'People workspace' }} · Crewbase</title>
    <meta name="description" content="An original people operations portfolio demo. All people and organizations are fictional.">
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/crewbase.css">
    @livewireStyles
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="/" aria-label="Crewbase home"><span class="brand-mark">c<span>·</span></span>Crewbase</a>
        <div class="workspace-label">YOUR WORKSPACE</div>
        <div class="workspace"><span class="workspace-icon">{{ mb_substr(auth()->user()->organization->name, 0, 1) }}</span><div>{{ auth()->user()->organization->name }}<small>People operations</small></div><span class="subtle">⌄</span></div>
        <nav aria-label="Main navigation">
            <a @class(['nav-link', 'selected' => request()->routeIs('people', 'workers.*')]) href="/" @if(request()->routeIs('people', 'workers.*')) aria-current="page" @endif><span aria-hidden="true">▦</span>People<span class="nav-count">{{ \App\Models\Worker::where('organization_id', auth()->user()->organization_id)->count() }}</span></a>
            <a @class(['nav-link', 'selected' => request()->routeIs('equipment')]) href="/equipment" @if(request()->routeIs('equipment')) aria-current="page" @endif><span aria-hidden="true">▣</span>Equipment</a>
            <a @class(['nav-link', 'selected' => request()->routeIs('activity')]) href="/activity" @if(request()->routeIs('activity')) aria-current="page" @endif><span aria-hidden="true">◷</span>Activity log</a>
        </nav>
        <div class="sidebar-note"><span class="note-flower" aria-hidden="true">✳</span><strong>A good start. A smooth handover.</strong><p>Keep the small details together, at every stage.</p></div>
        <div class="account"><span class="avatar mini">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ ucfirst(auth()->user()->role) }} account</small></div><form method="post" action="/logout">@csrf<button class="icon-button" title="Sign out" aria-label="Sign out">↗</button></form></div>
    </aside>
    <div class="workspace-main">
        <header class="topbar"><span><span class="status-dot"></span> {{ auth()->user()->organization->name }} <span class="crumb">/ People operations</span></span><span class="demo-pill">FICTIONAL DEMO</span></header>
        <main id="main">
            @if(session('success'))<div class="flash success" role="status">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="flash error" role="alert"><strong>We couldn’t save that change.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            {{ $slot }}
        </main>
        <footer>Independent reconstruction · Created November 2026 · Fictional data · No employment or delivery claims</footer>
    </div>
</div>
@livewireScripts
</body>
</html>
