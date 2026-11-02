@extends('layouts.app')

@section('title', 'Overview')
@section('section', 'Overview')

@section('content')
    <div class="page-heading heading-with-action">
        <div>
            <span class="eyebrow">{{ strtoupper(now()->format('l, F j, Y')) }}</span>
            <h1>Good day, {{ str(auth()->user()->name)->before(' ') }}.</h1>
            <p>Here is a snapshot of your agriculture support operations.</p>
        </div>
        @if (auth()->user()->hasAnyRole('super_admin', 'admin', 'staff'))
            <a class="button button-primary" href="{{ route('distributions.create') }}"><span>＋</span> New distribution</a>
        @endif
    </div>

    <section class="stats-grid" aria-label="System overview">
        <article class="stat-card"><div class="stat-top"><span class="stat-icon green">♧</span><span class="stat-label">REGISTERED FARMERS</span></div><strong class="stat-value">{{ number_format($stats['farmers']) }}</strong><span class="stat-note">{{ number_format($stats['active_farmers']) }} active profiles</span></article>
        <article class="stat-card"><div class="stat-top"><span class="stat-icon amber">▤</span><span class="stat-label">INVENTORY ITEMS</span></div><strong class="stat-value">{{ number_format($stockSummary->items ?? 0) }}</strong><span class="stat-note">{{ number_format($stockSummary->low_stock ?? 0) }} need attention</span></article>
        <article class="stat-card"><div class="stat-top"><span class="stat-icon blue">◇</span><span class="stat-label">ACTIVE PROGRAMS</span></div><strong class="stat-value">{{ number_format($stats['programs']) }}</strong><span class="stat-note">Subsidy programs running</span></article>
        <article class="stat-card"><div class="stat-top"><span class="stat-icon violet">↗</span><span class="stat-label">DISTRIBUTIONS</span></div><strong class="stat-value">{{ number_format($stats['distributions']) }}</strong><span class="stat-note">Completed transactions</span></article>
    </section>

    <div class="dashboard-grid">
        <section class="panel panel-main">
            <div class="panel-heading">
                <div><h2>Recent distributions</h2><p>Latest assistance delivered to farmers</p></div>
                @if (auth()->user()->hasAnyRole('super_admin', 'admin', 'staff'))<a class="text-link" href="{{ route('distributions.index') }}">View all <span>→</span></a>@endif
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
                                    <td><a class="table-primary" href="{{ route('distributions.show', $distribution) }}">{{ $distribution->transaction_number }}</a></td>
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

        <section class="panel panel-side">
            <div class="panel-heading"><div><h2>Stock activity</h2><p>Recent inventory movement</p></div>@if (auth()->user()->hasAnyRole('super_admin', 'admin', 'supply_officer', 'custodian'))<a class="text-link" href="{{ route('supplies.index') }}">Inventory <span>→</span></a>@endif</div>
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
    </div>

    <section class="quick-actions">
        <div><span class="quick-label">QUICK ACCESS</span><strong>Keep field work moving</strong></div>
        <div class="quick-links">
            @if (auth()->user()->hasAnyRole('super_admin', 'admin', 'staff'))<a href="{{ route('farmers.create') }}">＋ Add farmer</a><a href="{{ route('distributions.create') }}">▣ Record distribution</a>@endif
            @if (auth()->user()->hasAnyRole('super_admin', 'admin', 'supply_officer', 'custodian'))<a href="{{ route('supplies.create') }}">＋ Add supply</a>@endif
            @if (auth()->user()->hasAnyRole('super_admin', 'admin'))<a href="{{ route('programs.create') }}">◇ Create program</a>@endif
        </div>
    </section>
@endsection
