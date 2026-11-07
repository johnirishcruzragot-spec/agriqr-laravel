@extends('layouts.app')

@section('title', 'Database administration')
@section('section', 'Database administration')

@section('content')
    <div class="page-heading heading-with-action">
        <div>
            <span class="eyebrow">SUPER ADMINISTRATION</span>
            <h1>Database administration</h1>
            <p>Review the health of the application’s active database connection.</p>
        </div>
        <a class="button button-secondary" href="{{ route('database.index') }}">Refresh status</a>
    </div>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>Connection health</h2>
                <p>Checked with a read-only database query.</p>
            </div>
            <span class="status {{ $connected ? 'status-green' : 'status-red' }}">{{ $connected ? 'Connected' : 'Unavailable' }}</span>
        </div>

        @unless ($connected)
            <div class="alert alert-error" role="alert">
                The connection check failed. Verify database availability and deployment-managed configuration, then refresh this page.
            </div>
        @endunless

        <div class="detail-grid">
            <div><span class="detail-label">Connection</span><strong>{{ $connectionName }}</strong></div>
            <div><span class="detail-label">Database engine</span><strong>{{ $driver }}</strong></div>
            <div><span class="detail-label">Response time</span><strong>{{ $responseTime === null ? 'Unavailable' : number_format($responseTime, 2).' ms' }}</strong></div>
            <div><span class="detail-label">Last checked</span><strong>{{ $checkedAt->format('M j, Y · g:i:s A') }}</strong></div>
        </div>
    </section>

    <div class="inline-notice section-gap">
        <strong>Protected configuration</strong>
        <p>Database hosts, names, usernames, passwords, and connection URLs are managed in the deployment environment. This page does not display or modify credentials and does not run schema changes.</p>
        <a href="{{ route('settings.index') }}">Open system configuration <span>→</span></a>
    </div>
@endsection
