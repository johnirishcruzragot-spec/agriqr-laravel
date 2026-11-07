@extends('layouts.app')

@section('title', 'Import inventory')
@section('section', 'Inventory')

@section('content')
    <div class="page-heading">
        @if (auth()->user()->canUseButton('inventory.list'))<a class="back-link" href="{{ route('supplies.index') }}">← Back to inventory</a>@endif
        <span class="eyebrow">SUPPLY MANAGEMENT</span>
        <h1>Import inventory from CSV</h1>
        <p>Upload a comma-separated CSV file to create supply items or receive stock for existing items.</p>
    </div>

    <section class="panel import-panel">
        <div class="panel-heading">
            <div><h2>Upload a CSV file</h2><p>Files may contain up to 5,000 rows and must be 5 MB or smaller.</p></div>
            @if (auth()->user()->canUseButton('inventory.import'))<a class="button button-secondary" href="{{ route('supplies.import.template') }}">Download CSV template</a>@endif
        </div>
        <div class="import-notice">
            <strong>How matching works</strong>
            <p>Rows are matched by supply code. A new code creates an item and sets its opening stock. An existing code updates its supply details and adds the CSV quantity to current stock. Blank optional fields leave saved values unchanged. Every positive imported quantity is recorded as a stock receipt.</p>
        </div>
        <form method="post" action="{{ route('supplies.import.store') }}" enctype="multipart/form-data" class="import-form">
            @csrf
            <label class="field field-max">
                <span>CSV file <b>*</b></span>
                <input type="file" name="file" accept=".csv,text/csv" required>
                <small>Use the downloaded template with the supported columns below.</small>
            </label>
            @if (auth()->user()->canUseButton('inventory.import'))<button class="button button-primary" type="submit">Import inventory</button>@endif
        </form>
        <div class="import-headers">
            <span class="detail-label">SUPPORTED COLUMN HEADERS</span>
            <p>{{ implode(' · ', $headers) }}</p>
            <span class="detail-label">SUPPORTED CATEGORIES</span>
            <p>{{ implode(' · ', $categories) }}</p>
            <p>Unit is optional for Cash, Farm Tools, and Other Agricultural Supplies.</p>
        </div>
    </section>

    @if ($report)
        <section class="panel section-gap import-results" aria-live="polite">
            <div class="panel-heading">
                <div><h2>Import results</h2><p>{{ number_format($report['processed_rows']) }} non-empty rows processed.</p></div>
            </div>
            <div class="import-stat-grid">
                <div><strong>{{ number_format($report['created']) }}</strong><span>Created</span></div>
                <div><strong>{{ number_format($report['updated']) }}</strong><span>Updated / stock received</span></div>
                <div><strong>{{ number_format($report['failed']) }}</strong><span>Skipped with errors</span></div>
            </div>
            @if ($report['failed'] > 0)
                <div class="import-errors">
                    <strong>Rows that need attention</strong>
                    <ul>
                        @foreach ($report['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    @if ($report['errors_truncated'])
                        <p>Only the first 25 row errors are shown.</p>
                    @endif
                </div>
            @elseif ($report['processed_rows'] === 0)
                <p class="import-empty">The CSV contained no data rows.</p>
            @endif
        </section>
    @endif
@endsection
