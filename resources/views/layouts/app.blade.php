<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Overview') · AgriQR</title>
    <link rel="icon" type="image/png" href="{{ asset('images/agriqr-icon-light.png') }}" id="app-favicon" data-light-icon="{{ asset('images/agriqr-icon-light.png') }}" data-dark-icon="{{ asset('images/agriqr-icon-dark.png') }}">
    <script>
        try {
            const theme = localStorage.getItem('agriqr-theme');
            document.documentElement.dataset.theme = theme || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        } catch {
            document.documentElement.dataset.theme = 'light';
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <a class="brand" href="{{ route('dashboard') }}">
                <img class="brand-logo" src="{{ asset('images/agriqr-logo-light.svg') }}" data-theme-logo data-light-logo="{{ asset('images/agriqr-logo-light.svg') }}" data-dark-logo="{{ asset('images/agriqr-logo-dark.svg') }}" alt="AgriQR — Smart Agriculture. Stronger Tomorrow.">
            </a>

            <div class="nav-caption">WORKSPACE</div>
            <nav class="side-nav" aria-label="Main navigation">
                @if (auth()->user()->canUseButton('dashboard'))
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m3.5 10 8.5-7 8.5 7v10.5h-17V10Z"/><path d="M9 20.5v-7h6v7"/></svg>
                    <span>Overview</span>
                </a>
                @endif

                @if ((auth()->user()->canAccessModule('farmers') && (auth()->user()->canUseButton('farmers.list') || auth()->user()->canUseButton('farmers.import'))) || (auth()->user()->canAccessModule('distributions') && auth()->user()->canUseButton('distributions.list')) || (auth()->user()->canAccessModule('inventory') && auth()->user()->canUseButton('inventory.list')))
                    <details class="nav-group" @if (request()->routeIs('farmers.*', 'distributions.*', 'supplies.*')) open @endif>
                        <summary class="nav-group-summary"><span>Field operations</span><svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 6 4 4 4-4"/></svg></summary>
                        <div class="nav-group-links">
                            @if (auth()->user()->canAccessModule('farmers') && auth()->user()->canUseButton('farmers.list'))
                                <a class="nav-link {{ request()->routeIs('farmers.*') ? 'active' : '' }}" href="{{ route('farmers.index') }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="9" cy="7" r="3.5"/><path d="M2.5 20c.5-4 2.8-6 6.5-6s6 2 6.5 6M16 5.5a3.5 3.5 0 0 1 0 6.8M18 14c2.2.9 3.3 2.8 3.5 6"/></svg><span>Farmers</span></a>
                            @elseif (auth()->user()->canAccessModule('farmers') && auth()->user()->canUseButton('farmers.import'))
                                <a class="nav-link {{ request()->routeIs('farmers.import*') ? 'active' : '' }}" href="{{ route('farmers.import') }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 16V3m0 0L7 8m5-5 5 5M4 15v5h16v-5"/></svg><span>Import farmers</span></a>
                            @endif
                            @if (auth()->user()->canAccessModule('distributions') && auth()->user()->canUseButton('distributions.list'))
                                <a class="nav-link {{ request()->routeIs('distributions.*') ? 'active' : '' }}" href="{{ route('distributions.index') }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 3.5h16v17H4z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg><span>Distributions</span></a>
                            @endif
                            @if (auth()->user()->canAccessModule('inventory') && auth()->user()->canUseButton('inventory.list'))
                                <a class="nav-link {{ request()->routeIs('supplies.*') ? 'active' : '' }}" href="{{ route('supplies.index') }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m3 7 9-4 9 4-9 4-9-4Z"/><path d="M3 12l9 4 9-4M3 17l9 4 9-4M12 11v9"/></svg><span>Inventory</span></a>
                            @endif
                        </div>
                    </details>
                @endif

                @if ((auth()->user()->canAccessModule('programs') && auth()->user()->canUseButton('programs.list')) || (auth()->user()->canAccessModule('seasons') && auth()->user()->canUseButton('seasons.list')) || (auth()->user()->canAccessModule('users') && auth()->user()->canUseButton('users.list')) || (auth()->user()->canAccessModule('activity') && auth()->user()->canUseButton('activity.list')) || (auth()->user()->canAccessModule('reports') && auth()->user()->canUseButton('reports.view')) || (auth()->user()->canAccessModule('settings') && auth()->user()->canUseButton('settings.view')) || (auth()->user()->canAccessModule('database') && auth()->user()->canUseButton('database.view')))
                    <details class="nav-group" @if (request()->routeIs('programs.*', 'seasons.*', 'users.*', 'activity.*', 'reports.*', 'settings.*', 'database.*')) open @endif>
                        <summary class="nav-group-summary"><span>Administration</span><svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 6 4 4 4-4"/></svg></summary>
                        <div class="nav-group-links">
                            @if (auth()->user()->canAccessModule('programs') && auth()->user()->canUseButton('programs.list'))
                                <a class="nav-link {{ request()->routeIs('programs.*') ? 'active' : '' }}" href="{{ route('programs.index') }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 21 8v8l-9 5-9-5V8l9-5Z"/><path d="m3.5 8.5 8.5 5 8.5-5M12 13.5V21"/></svg><span>Programs</span></a>
                            @endif
                            @if (auth()->user()->canAccessModule('seasons') && auth()->user()->canUseButton('seasons.list'))
                                <a class="nav-link {{ request()->routeIs('seasons.*') ? 'active' : '' }}" href="{{ route('seasons.index') }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20h16M6 17c0-6 2-10 6-13 4 3 6 7 6 13M12 5v12M8 11l4 2 4-2"/></svg><span>Seasons &amp; groups</span></a>
                            @endif
                            @if (auth()->user()->canAccessModule('users') && auth()->user()->canUseButton('users.list'))
                                <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="9" cy="7" r="3.5"/><path d="M2.5 20c.5-4 2.8-6 6.5-6s6 2 6.5 6M17 8h5M19.5 5.5v5"/></svg><span>Team access</span></a>
                            @endif
                            @if (auth()->user()->canAccessModule('activity') && auth()->user()->canUseButton('activity.list'))
                                <a class="nav-link {{ request()->routeIs('activity.*') ? 'active' : '' }}" href="{{ route('activity.index') }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span>Audit log</span></a>
                            @endif
                            @if (auth()->user()->canAccessModule('reports') && auth()->user()->canUseButton('reports.view'))
                                <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20V4M4 20h17"/><path d="m7 16 4-5 3 2 5-7"/></svg><span>Reports</span></a>
                            @endif
                            @if (auth()->user()->canAccessModule('settings') && auth()->user()->canUseButton('settings.view'))
                                <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.8-.7a7.8 7.8 0 0 1-1.6.9l-.3 1.9h-2.8l-.3-1.9a7.8 7.8 0 0 1-1.6-.9l-1.8.7-1.4-2.4 1.4-1.1a7 7 0 0 1 0-1.9l-1.4-1.2 1.4-2.4 1.8.7a7.8 7.8 0 0 1 1.6-.9l.3-1.9h2.8l.3 1.9a7.8 7.8 0 0 1 1.6.9l1.8-.7 1.4 2.4-1.4 1.2a7 7 0 0 1 0 1.8Z"/></svg><span>System settings</span></a>
                            @endif
                            @if (auth()->user()->canAccessModule('database') && auth()->user()->canUseButton('database.view'))
                                <a class="nav-link {{ request()->routeIs('database.*') ? 'active' : '' }}" href="{{ route('database.index') }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg><span>Database status</span></a>
                            @endif
                        </div>
                    </details>
                @endif

                @if (auth()->user()->role === 'system_admin')
                    <div class="nav-caption">SYSTEM ADMIN</div>
                    <div class="side-nav">
                        <a class="nav-link {{ request()->routeIs('button-access.*') ? 'active' : '' }}" href="{{ route('button-access.index') }}">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 5h16M4 12h16M4 19h16"/><circle cx="9" cy="5" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="8" cy="19" r="2"/></svg>
                            <span>Button access</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('schema-editor.*') ? 'active' : '' }}" href="{{ route('schema-editor.index') }}">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg>
                            <span>Schema editor</span>
                        </a>
                    </div>
                @endif
            </nav>

            <div class="sidebar-bottom">
                <div class="office-card">
                    <span class="office-dot"></span>
                    <div><strong>Municipal Agriculture</strong><small>Office workspace</small></div>
                </div>
                <details class="account-menu">
                    <summary class="account-row">
                        <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="account-name"><strong>{{ auth()->user()->name }}</strong><small>{{ str(auth()->user()->role)->replace('_', ' ')->title() }}</small></span>
                        <svg class="account-chevron" viewBox="0 0 16 16" aria-hidden="true"><path d="m4 6 4 4 4-4"/></svg>
                    </summary>
                    <div class="account-dropdown">
                        <span>{{ auth()->user()->email }}</span>
                        <form method="post" action="{{ route('logout') }}">
                        @csrf
                            @if (auth()->user()->canUseButton('workspace.sign_out'))
                            <button class="logout-button" type="submit"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M8 3H4.5A1.5 1.5 0 0 0 3 4.5v11A1.5 1.5 0 0 0 4.5 17H8M12 6l4 4-4 4M16 10H7"/></svg>Sign out</button>
                            @endif
                        </form>
                    </div>
                </details>
            </div>
        </aside>

        <main class="main-area">
            <header class="topbar">
                @if (auth()->user()->canUseButton('workspace.mobile_menu'))<button class="mobile-menu" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="sidebar" data-menu-toggle>☰</button>@endif
                <div class="breadcrumb"><span>AgriQR</span><span class="crumb-separator">/</span><strong>@yield('section', 'Overview')</strong></div>
                <div class="topbar-meta">
                    <span class="today-label">{{ now()->format('D, M j, Y') }}</span>
                    @if (auth()->user()->canUseButton('workspace.theme'))
                    <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark mode" aria-pressed="false">
                        <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
                        <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20.2 15.2A8.6 8.6 0 0 1 8.8 3.8 8.8 8.8 0 1 0 20.2 15.2Z"/></svg>
                        <span data-theme-label>Dark mode</span>
                    </button>
                    @endif
                </div>
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
