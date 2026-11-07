@extends('layouts.app')

@section('title', 'Reports')
@section('section', 'Reports')

@section('content')
    <div class="page-heading heading-with-action">
        <div><span class="eyebrow">SYSTEM REPORTING</span><h1>Reports and analytics</h1><p>Review and export reports available to your role.</p></div>
        @if (auth()->user()->canUseButton('reports.export'))<a class="button button-secondary" href="{{ request()->fullUrlWithQuery(['download' => 1]) }}">Export CSV</a>@endif
    </div>

    <section class="panel">
        <form class="toolbar report-filters" method="get" action="{{ route('reports.index') }}">
            <label class="field"><span>Report</span><select class="select-control" name="report">
                @foreach ($reports as $value => $label)
                    <option value="{{ $value }}" @selected($report === $value)>{{ $label }}</option>
                @endforeach
            </select></label>
            <label class="field"><span>From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
            <label class="field"><span>To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
            <label class="field"><span>Subsidy program</span><select class="select-control" name="program_id"><option value="">All programs</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected((string) ($filters['program_id'] ?? '') === (string) $program->id)>{{ $program->program_name }}</option>
                @endforeach
            </select></label>
            @if (auth()->user()->canUseButton('reports.filter'))<button class="button button-secondary" type="submit">Apply filters</button>@endif
        </form>

        @if ($report === 'failed-verifications' && ! $faceVerificationAvailable)
            <p class="report-note">Face verification is not currently implemented. This report includes failed QR lookups and RSBSA eligibility checks recorded by AgriQR.</p>
        @endif

        @if ($rows->isEmpty())
            <div class="empty-state"><span class="empty-icon">▤</span><strong>No report records found</strong><p>Try a different report or date range.</p></div>
        @else
            <div class="table-wrap">
                <table>
                    <thead><tr>@foreach ($headers as $label)<th>{{ strtoupper($label) }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                @foreach (array_keys($headers) as $key)
                                    <td>{{ $row->{$key} ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $rows->links() }}</div>
        @endif
    </section>
@endsection
