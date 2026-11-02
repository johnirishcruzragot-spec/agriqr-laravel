@extends('layouts.app')

@section('title', 'Import farmers')
@section('section', 'Farmers')

@section('content')
    <div class="page-heading">
        <a class="back-link" href="{{ route('farmers.index') }}">← Back to farmer directory</a>
        <span class="eyebrow">RSBSA DATA IMPORT</span>
        <h1>Import farmers from CSV</h1>
        <p>Upload an RSBSA spreadsheet saved as a comma-separated CSV file.</p>
    </div>

    <section class="panel import-panel">
        <div class="panel-heading">
            <div><h2>Upload a CSV file</h2><p>Files may contain up to 5,000 rows and must be 5 MB or smaller.</p></div>
            <a class="button button-secondary" href="{{ route('farmers.import.template') }}">Download CSV template</a>
        </div>
        <div class="import-notice">
            <strong>How matching works</strong>
            <p>Rows are matched by LGU RSBSA Number. A new number creates a farmer; an existing number updates that farmer and matching parcel. Blank optional cells leave saved values unchanged. Imported records are marked unverified.</p>
        </div>
        <form method="post" action="{{ route('farmers.import.store') }}" enctype="multipart/form-data" class="import-form">
            @csrf
            <label class="field field-max">
                <span>CSV file <b>*</b></span>
                <input type="file" name="file" accept=".csv,text/csv" required>
                <small>Use the downloaded template or the spreadsheet headers listed below.</small>
            </label>
            <button class="button button-primary" type="submit">Import farmers</button>
        </form>
        <div class="import-headers">
            <span class="detail-label">SUPPORTED COLUMN HEADERS</span>
            <p>{{ implode(' · ', $headers) }}</p>
        </div>
    </section>

    @if ($report)
        <section class="panel section-gap import-results" aria-live="polite">
            <div class="panel-heading">
                <div><h2>Import results</h2><p>{{ number_format($report['processed_rows']) }} non-empty rows processed.</p></div>
            </div>
            <div class="import-stat-grid">
                <div><strong>{{ number_format($report['created']) }}</strong><span>Created</span></div>
                <div><strong>{{ number_format($report['updated']) }}</strong><span>Updated</span></div>
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
