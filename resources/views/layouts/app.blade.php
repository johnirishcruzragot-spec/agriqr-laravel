<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Overview') · AgriQR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <a class="brand" href="{{ route('dashboard') }}">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none"><path d="M7 25V14.5L16 7l9 7.5V25H7Z" stroke="currentColor" stroke-width="2"/><path d="M12 25v-7h8v7M16 7V3m0 0c-3 0-5 2-5 5 3 0 5-2 5-5Zm0 3c0-2 2-4 5-4 0 3-2 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="brand-name">Agri<span>QR</span><small>FIELD OPERATIONS</small></span>
            </a>

            <div class="nav-caption">WORKSPACE</div>
            <nav class="side-nav" aria-label="Main navigation">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="nav-icon">⌂</span>Overview</a>
                @if (auth()->user()->hasAnyRole('super_admin', 'admin', 'staff'))
                    <a class="nav-link {{ request()->routeIs('farmers.*') ? 'active' : '' }}" href="{{ route('farmers.index') }}"><span class="nav-icon">♧</span>Farmers</a>
                    <a class="nav-link {{ request()->routeIs('distributions.*') ? 'active' : '' }}" href="{{ route('distributions.index') }}"><span class="nav-icon">▣</span>Distributions</a>
                @endif
                @if (auth()->user()->hasAnyRole('super_admin', 'admin', 'supply_officer', 'custodian'))
                    <a class="nav-link {{ request()->routeIs('supplies.*') ? 'active' : '' }}" href="{{ route('supplies.index') }}"><span class="nav-icon">▤</span>Inventory</a>
                @endif
                @if (auth()->user()->hasAnyRole('super_admin', 'admin'))
                    <a class="nav-link {{ request()->routeIs('programs.*') ? 'active' : '' }}" href="{{ route('programs.index') }}"><span class="nav-icon">◇</span>Programs</a>
                    <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><span class="nav-icon">♙</span>Team access</a>
                    <a class="nav-link {{ request()->routeIs('activity.*') ? 'active' : '' }}" href="{{ route('activity.index') }}"><span class="nav-icon">◷</span>Audit log</a>
                @endif
            </nav>

            <div class="sidebar-bottom">
                <div class="office-card">
                    <span class="office-dot"></span>
                    <div><strong>Municipal Agriculture</strong><small>Office workspace</small></div>
                </div>
                <div class="account-row">
                    <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="account-name"><strong>{{ auth()->user()->name }}</strong><small>{{ str(auth()->user()->role)->replace('_', ' ')->title() }}</small></span>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="logout-button" type="submit" title="Sign out" aria-label="Sign out">↗</button>
                    </form>
                </div>
            </div>
        </aside>

        <main class="main-area">
            <header class="topbar">
                <button class="mobile-menu" type="button" aria-label="Open navigation" data-menu-toggle>☰</button>
                <div class="breadcrumb"><span>AgriQR</span><span class="crumb-separator">/</span><strong>@yield('section', 'Overview')</strong></div>
                <div class="topbar-meta"><span class="today-label">{{ now()->format('D, M j, Y') }}</span><span class="topbar-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span></div>
            </header>

            <div class="page-content">
                @if (session('success'))
                    <div class="alert alert-success" role="status">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-error" role="alert">
                        <strong>Please review the following:</strong>
                        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
