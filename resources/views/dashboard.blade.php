@extends('layouts.app')

@section('title', 'Overview')
@section('section', 'Overview')

@section('content')
    @if ($isTechnician)
        <div class="page-heading heading-with-action">
            <div>
                <span class="eyebrow">FIELD WORKSPACE · {{ strtoupper(now()->format('l, F j')) }}</span>
                <h1>Welcome, {{ str(auth()->user()->name)->before(' ') }}.</h1>
                <p>Track the assistance you deliver and keep farmer visits moving.</p>
            </div>
            @if (auth()->user()->canAccessModule('distributions') && auth()->user()->canUseButton('distributions.create'))
                <a class="button button-primary" href="{{ route('distributions.create') }}"><span>＋</span> Record assistance</a>
            @endif
        </div>

        <section class="stats-grid staff-stats-grid" aria-label="Your field activity">
            @if (auth()->user()->canAccessModule('distributions'))
                <article class="stat-card"><div class="stat-top"><span class="stat-icon violet">↗</span><span class="stat-label">DELIVERED TODAY</span></div><strong class="stat-value">{{ number_format(collect($distributionChart)->last()['total'] ?? 0) }}</strong><span class="stat-note">Completed by your account</span></article>
                <article class="stat-card"><div class="stat-top"><span class="stat-icon blue">▥</span><span class="stat-label">LAST 7 DAYS</span></div><strong class="stat-value">{{ number_format($distributionChartTotal) }}</strong><span class="stat-note">Completed distributions</span></article>
            @endif
            @if (auth()->user()->canAccessModule('farmers'))
                <article class="stat-card"><div class="stat-top"><span class="stat-icon green">♧</span><span class="stat-label">FARMER RECORDS</span></div><strong class="stat-value">{{ number_format($stats['farmers']) }}</strong><span class="stat-note">{{ number_format($stats['active_farmers']) }} active profiles</span></article>
            @endif
            @if (auth()->user()->canAccessModule('programs'))
                <article class="stat-card"><div class="stat-top"><span class="stat-icon amber">◇</span><span class="stat-label">ACTIVE PROGRAMS</span></div><strong class="stat-value">{{ number_format($stats['active_programs']) }}</strong><span class="stat-note">Available for assistance work</span></article>
            @endif
        </section>

        @if (auth()->user()->canAccessModule('distributions'))
            <section class="panel staff-chart-panel">
                <div class="panel-heading">
                    <div><h2>Your distribution activity</h2><p>Completed transactions by day · last 7 days</p></div>
                    <span class="chart-total">{{ number_format($distributionChartTotal) }} <small>total</small></span>
                </div>
                @include('dashboard.distribution-chart', ['chart' => $distributionChart, 'chartLabel' => 'Your completed distributions over the last seven days'])
            </section>

            <section class="panel section-gap">
                <div class="panel-heading">
                    <div><h2>Your recent distributions</h2><p>Assistance recorded by your account</p></div>
                    @if (auth()->user()->canUseButton('distributions.list'))<a class="text-link" href="{{ route('distributions.index') }}">View all <span>→</span></a>@endif
                </div>
                @if ($recentDistributions->isEmpty())
                    <div class="empty-state compact"><span class="empty-icon">▣</span><strong>No distributions recorded yet</strong><p>Your completed field transactions will appear here.</p></div>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>TRANSACTION</th><th>FARMER</th><th>PROGRAM</th><th>DATE</th><th>STATUS</th></tr></thead>
                            <tbody>
                                @foreach ($recentDistributions as $distribution)
                                    <tr>
                                        <td>@if (auth()->user()->canUseButton('distributions.view'))<a class="table-primary" href="{{ route('distributions.show', $distribution) }}">{{ $distribution->transaction_number }}</a>@else<strong>{{ $distribution->transaction_number }}</strong>@endif</td>
                                        <td><strong>{{ $distribution->farmer->full_name }}</strong><small class="table-sub">{{ $distribution->farmer->farmer_id }}</small></td>
                                        <td>{{ $distribution->program?->program_name ?? '—' }}</td>
                                        <td>{{ $distribution->distributed_at->format('M j, Y') }}</td>
                                        <td><span class="status {{ $distribution->transaction_status === 'completed' ? 'status-green' : 'status-neutral' }}">{{ str($distribution->transaction_status)->title() }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif

        <section class="quick-actions">
            <div><span class="quick-label">FIELD SHORTCUTS</span><strong>Ready for your next farmer visit?</strong></div>
            <div class="quick-links">
                @if (auth()->user()->canUseButton('farmers.create'))<a href="{{ route('farmers.create') }}">＋ Register farmer</a>@endif
                @if (auth()->user()->canUseButton('farmers.list'))<a href="{{ route('farmers.index') }}">♧ Find farmer</a>@endif
                @if (auth()->user()->canUseButton('distributions.create'))<a href="{{ route('distributions.create') }}">▣ Record assistance</a>@endif
                @if (auth()->user()->canUseButton('programs.list'))<a href="{{ route('programs.index') }}">◇ View programs</a>@endif
            </div>
        </section>
    @else
    <div class="page-heading heading-with-action">
        <div>
            <span class="eyebrow">{{ strtoupper(now()->format('l, F j, Y')) }}</span>
            <h1>Good day, {{ str(auth()->user()->name)->before(' ') }}.</h1>
            <p>Here is a snapshot of your agriculture support operations.</p>
        </div>
        @if (auth()->user()->canAccessModule('distributions') && auth()->user()->canUseButton('distributions.create'))
            <a class="button button-primary" href="{{ route('distributions.create') }}"><span>＋</span> New distribution</a>
        @endif
    </div>

    @if (auth()->user()->canAccessModule('farmers') || auth()->user()->canAccessModule('inventory') || auth()->user()->canAccessModule('programs') || auth()->user()->canAccessModule('distributions') || auth()->user()->canAccessModule('users'))
        <section class="stats-grid" aria-label="System overview">
            @if (auth()->user()->canAccessModule('farmers'))
                <article class="stat-card"><div class="stat-top"><span class="stat-icon green">♧</span><span class="stat-label">REGISTERED FARMERS</span></div><strong class="stat-value">{{ number_format($stats['farmers']) }}</strong><span class="stat-note">{{ number_format($stats['active_farmers']) }} active profiles</span></article>
            @endif
            @if (auth()->user()->canAccessModule('inventory'))
                <article class="stat-card"><div class="stat-top"><span class="stat-icon amber">▤</span><span class="stat-label">INVENTORY ITEMS</span></div><strong class="stat-value">{{ number_format($stockSummary->items ?? 0) }}</strong><span class="stat-note">{{ number_format($stockSummary->low_stock ?? 0) }} need attention</span></article>
            @endif
            @if (auth()->user()->canAccessModule('programs'))
                <article class="stat-card"><div class="stat-top"><span class="stat-icon blue">◇</span><span class="stat-label">SUBSIDY PROGRAMS</span></div><strong class="stat-value">{{ number_format($stats['programs']) }}</strong><span class="stat-note">{{ number_format($stats['active_programs']) }} active programs</span></article>
            @endif
            @if (auth()->user()->canAccessModule('distributions') && auth()->user()->canUseButton('distributions.list'))
                <article class="stat-card"><div class="stat-top"><span class="stat-icon violet">↗</span><span class="stat-label">DISTRIBUTIONS</span></div><strong class="stat-value">{{ number_format($stats['distributions']) }}</strong><span class="stat-note">{{ number_format($stats['completed_distributions']) }} completed transactions</span></article>
            @endif
            @if (auth()->user()->canAccessModule('users'))
                <article class="stat-card"><div class="stat-top"><span class="stat-icon blue">♙</span><span class="stat-label">ACTIVE USERS</span></div><strong class="stat-value">{{ number_format($stats['users']) }}</strong><span class="stat-note">{{ number_format($stats['active_staff']) }} technicians · {{ number_format($stats['active_admins']) }} administrators</span></article>
            @endif
        </section>
    @endif

    @if (auth()->user()->canAccessModule('distributions'))
        <section class="panel admin-chart-panel">
            <div class="panel-heading">
                <div><h2>Distribution performance</h2><p>Completed farmer assistance · last 7 days</p></div>
                <span class="chart-total">{{ number_format($distributionChartTotal) }} <small>total</small></span>
            </div>
            @include('dashboard.distribution-chart', ['chart' => $distributionChart, 'chartLabel' => 'All completed distributions over the last seven days'])
        </section>
    @endif

    @if (auth()->user()->canAccessModule('distributions') || auth()->user()->canAccessModule('inventory'))
        <div class="dashboard-grid">
        @if (auth()->user()->canAccessModule('distributions'))
        <section class="panel panel-main">
            <div class="panel-heading">
                <div><h2>Recent distributions</h2><p>Latest assistance delivered to farmers</p></div>
                @if (auth()->user()->canUseButton('distributions.list'))<a class="text-link" href="{{ route('distributions.index') }}">View all <span>→</span></a>@endif
            </div>
            @if ($recentDistributions->isEmpty())
                <div class="empty-state"><span class="empty-icon">▣</span><strong>No distributions yet</strong><p>Completed distribution transactions will appear here.</p></div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>TRANSACTION</th><th>FARMER</th><th>PROGRAM</th><th>DATE</th><th>STATUS</th></tr></thead>
                        <tbody>
                            @foreach ($recentDistributions as $distribution)
                                <tr>
                                    <td>@if (auth()->user()->canUseButton('distributions.view'))<a class="table-primary" href="{{ route('distributions.show', $distribution) }}">{{ $distribution->transaction_number }}</a>@else<strong>{{ $distribution->transaction_number }}</strong>@endif</td>
                                    <td><strong>{{ $distribution->farmer->full_name }}</strong><small class="table-sub">{{ $distribution->farmer->farmer_id }}</small></td>
                                    <td>{{ $distribution->program?->program_name ?? '—' }}</td>
                                    <td>{{ $distribution->distributed_at->format('M j, Y') }}</td>
                                    <td><span class="status status-green">{{ str($distribution->transaction_status)->title() }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
        @endif

        @if (auth()->user()->canAccessModule('inventory') && auth()->user()->canUseButton('inventory.list'))
        <section class="panel panel-side">
            <div class="panel-heading"><div><h2>Stock activity</h2><p>Recent inventory movement</p></div>@if (auth()->user()->canUseButton('inventory.list'))<a class="text-link" href="{{ route('supplies.index') }}">Inventory <span>→</span></a>@endif</div>
            @if ($recentMovements->isEmpty())
                <div class="empty-state compact"><span class="empty-icon">▤</span><strong>No stock activity</strong><p>Stock receipts and distributions will show here.</p></div>
            @else
                <ul class="activity-list">
                    @foreach ($recentMovements as $movement)
                        <li>
                            <span class="movement-mark {{ $movement->movement_type === 'IN' ? 'movement-in' : 'movement-out' }}">{{ $movement->movement_type === 'IN' ? '＋' : '−' }}</span>
                            <div class="activity-copy"><strong>{{ $movement->supply->supply_name }}</strong><small>{{ $movement->created_at->diffForHumans() }} · {{ $movement->movement_type === 'IN' ? 'Stock received' : 'Distributed' }}</small></div>
                            <span class="movement-quantity {{ $movement->movement_type === 'IN' ? 'quantity-in' : '' }}">{{ $movement->movement_type === 'IN' ? '+' : '−' }}{{ number_format((float) $movement->quantity, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
        @endif
        </div>
    @endif

    @if (auth()->user()->canAccessModule('activity') || auth()->user()->canAccessModule('inventory'))
        <div class="dashboard-grid section-gap">
            @if (auth()->user()->canAccessModule('activity') && auth()->user()->canUseButton('activity.list'))
            <section class="panel panel-main">
                <div class="panel-heading"><div><h2>Recent system activity</h2><p>Latest account and record changes</p></div>@if (auth()->user()->canUseButton('activity.list'))<a class="text-link" href="{{ route('activity.index') }}">Audit log <span>→</span></a>@endif</div>
                @if ($recentActivities->isEmpty())
                    <div class="empty-state compact"><span class="empty-icon">◷</span><strong>No recent activity</strong><p>Sign-ins and important changes will be listed here.</p></div>
                @else
                    <ul class="activity-list">
                        @foreach ($recentActivities as $activity)
                            <li><span class="movement-mark movement-out">◷</span><div class="activity-copy"><strong>{{ $activity->description }}</strong><small>{{ $activity->user?->name ?? 'Unknown account' }} · {{ $activity->created_at->diffForHumans() }}</small></div><span class="status status-neutral">{{ str($activity->module)->title() }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </section>
            @endif
            @if (auth()->user()->canAccessModule('inventory') && auth()->user()->canUseButton('inventory.list'))
            <section class="panel panel-side">
                <div class="panel-heading"><div><h2>Stock alerts</h2><p>Active supplies at or below minimum stock</p></div>@if (auth()->user()->canUseButton('inventory.list'))<a class="text-link" href="{{ route('supplies.index') }}">Inventory <span>→</span></a>@endif</div>
                @if ($lowStockSupplies->isEmpty())
                    <div class="empty-state compact"><span class="empty-icon">✓</span><strong>All stock levels are healthy</strong><p>No active supply is below its minimum stock.</p></div>
                @else
                    <ul class="activity-list">
                        @foreach ($lowStockSupplies as $inventory)
                            <li><span class="movement-mark movement-out">!</span><div class="activity-copy"><strong>{{ $inventory->supply->supply_name }}</strong><small>{{ $inventory->quantity }} on hand · minimum {{ $inventory->supply->minimum_stock }}</small></div><span class="status {{ (float) $inventory->quantity <= 0 ? 'status-red' : 'status-amber' }}">{{ (float) $inventory->quantity <= 0 ? 'Out' : 'Low' }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </section>
            @endif
        </div>
    @endif

    <section class="quick-actions">
        <div><span class="quick-label">ADMIN SHORTCUTS</span><strong>Manage agriculture support operations</strong></div>
        <div class="quick-links">
            @if (auth()->user()->canUseButton('farmers.create'))<a href="{{ route('farmers.create') }}">＋ Add farmer</a>@endif
            @if (auth()->user()->canUseButton('distributions.create'))<a href="{{ route('distributions.create') }}">▣ Record distribution</a>@endif
            @if (auth()->user()->canUseButton('inventory.create'))<a href="{{ route('supplies.create') }}">＋ Add supply</a>@endif
            @if (auth()->user()->canUseButton('programs.create'))<a href="{{ route('programs.create') }}">◇ Create program</a>@endif
            @if (! auth()->user()->canUseButton('farmers.create') && ! auth()->user()->canUseButton('distributions.create') && ! auth()->user()->canUseButton('inventory.create') && ! auth()->user()->canUseButton('programs.create'))
                <span>Contact an Admin to request module access.</span>
            @endif
        </div>
    </section>
    @endif
@endsection
