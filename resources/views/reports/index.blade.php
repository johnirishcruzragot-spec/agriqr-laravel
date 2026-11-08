
@extends('layouts.app')

@section('title', 'Reports and Analytics')
@section('section', 'Reports')

@section('content')
    <div class="page-heading heading-with-action">
        <div>
            <span class="eyebrow">SYSTEM REPORTING</span>
            <h1>Reports and analytics</h1>
            <p>Monitor agricultural subsidy programs, farmer assistance, and distribution activities.</p>
        </div>

        @if (auth()->user()->canUseButton('reports.export'))
            <a class="button button-secondary"
               href="{{ request()->fullUrlWithQuery(['download' => 1]) }}">
                Export CSV
            </a>
        @endif
    </div>

    <section class="panel">
        <form class="toolbar report-filters" method="get" action="{{ route('reports.index') }}">
            <label class="field">
                <span>Report</span>
                <select class="select-control" name="report">
                    @foreach ($reports as $value => $label)
                        <option value="{{ $value }}" @selected($report === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span>From</span>
                <input type="date" name="date_from"
                       value="{{ $filters['date_from'] ?? '' }}">
            </label>

            <label class="field">
                <span>To</span>
                <input type="date" name="date_to"
                       value="{{ $filters['date_to'] ?? '' }}">
            </label>

            <label class="field">
                <span>Subsidy program</span>
                <select class="select-control" name="program_id">
                    <option value="">All programs</option>
                    @foreach ($programs as $programOption)
                        <option value="{{ $programOption->id }}"
                            @selected((string) ($filters['program_id'] ?? '') === (string) $programOption->id)>
                            {{ $programOption->program_name }}
                        </option>
                    @endforeach
                </select>
            </label>

            @if (auth()->user()->canUseButton('reports.filter'))
                <button class="button button-secondary" type="submit">
                    Apply filters
                </button>
            @endif
        </form>

        @if ($report === 'failed-verifications' && ! $faceVerificationAvailable)
            <p class="report-note">
                Face verification is not currently implemented in this report.
                This report includes failed QR lookups and RSBSA eligibility checks
                recorded by AgriQR.
            </p>
        @endif

        @if ($report === 'program-distributions')
            <p class="report-note">
                This summary includes completed transactions only.
                Select View to see individual recipients, receipt dates, and the
                exact supplies and quantities they received.
            </p>
        @endif

        @if ($rows->isEmpty())
            <div class="empty-state">
                <span class="empty-icon">▤</span>
                <strong>No report records found</strong>
                <p>Try selecting another report or changing the date range.</p>
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            @foreach ($headers as $label)
                                <th>{{ strtoupper($label) }}</th>
                            @endforeach

                            @if ($report === 'program-distributions')
                                <th>ACTION</th>
                            @endif
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                @foreach (array_keys($headers) as $key)
                                    <td>
                                        @if ($report === 'program-distributions' && $key === 'program_name')
                                            <strong>{{ $row->{$key} }}</strong>

                                        @elseif ($report === 'program-distributions' && $key === 'subsidy_type')
                                            {{ str_replace('_', ' ', str($row->{$key})->title()) }}

                                        @elseif ($report === 'program-distributions' && $key === 'program_status')
                                            <span class="status status-green">
                                                {{ str($row->{$key})->title() }}
                                            </span>

                                        @elseif ($report === 'program-distributions' && in_array($key, ['transactions', 'farmers', 'items_issued'], true))
                                            {{ number_format((int) $row->{$key}) }}

                                        @elseif ($report === 'program-distributions' && $key === 'quantity')
                                            {{ number_format((float) $row->{$key}, 2) }}

                                        @elseif ($report === 'program-distributions' && $key === 'distributed_value')
                                            ₱{{ number_format((float) $row->{$key}, 2) }}

                                        @elseif ($report === 'program-distributions' && $key === 'latest_distribution_at')
                                            {{ $row->{$key} ? \Illuminate\Support\Carbon::parse($row->{$key})->format('M j, Y') : '—' }}

                                        @else
                                            {{ $row->{$key} ?? '—' }}
                                        @endif
                                    </td>
                                @endforeach

                                @if ($report === 'program-distributions')
                                    <td>
                                        @if ($row->program_id)
                                            <a class="table-action"
                                               href="{{ route('reports.programs.distributions', [
                                                   'program' => $row->program_id,
                                                   'date_from' => $filters['date_from'] ?? null,
                                                   'date_to' => $filters['date_to'] ?? null,
                                               ]) }}">
                                                View <span>→</span>
                                            </a>
                                        @else
                                            <span class="table-sub">Unavailable</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrap">
                {{ $rows->links() }}
            </div>
        @endif
    </section>
@endsection
