
@extends('layouts.app')

@section('title', 'Program Distribution Details')
@section('section', 'Reports')

@section('content')
    <div class="page-heading heading-with-action">
        <div>
            <a class="back-link"
               href="{{ route('reports.index', [
                   'report' => 'program-distributions',
                   'date_from' => $filters['date_from'] ?? null,
                   'date_to' => $filters['date_to'] ?? null,
               ]) }}">
                ← Back to Reports
            </a>

            <span class="eyebrow">PROGRAM DISTRIBUTION DETAILS</span>
            <h1>{{ $program->program_name }}</h1>

            <p>
                Review farmers who received assistance, receipt dates,
                verification information, and supplies distributed.
            </p>
        </div>
    </div>

    {{-- PROGRAM INFORMATION --}}
    <section class="panel section-gap">
        <div class="report-transaction-details">
            <div>
                <span class="detail-label">PROGRAM NAME</span>
                <strong>{{ $program->program_name }}</strong>
            </div>

            <div>
                <span class="detail-label">PROGRAM CODE</span>
                <strong>{{ $program->program_code }}</strong>
            </div>

            <div>
                <span class="detail-label">SUBSIDY TYPE</span>
                <strong>{{ str_replace('_', ' ', str($program->subsidy_type)->title()) }}</strong>
            </div>

            <div>
                <span class="detail-label">PROGRAM STATUS</span>
                <strong>{{ str($program->status)->title() }}</strong>
            </div>

            <div>
                <span class="detail-label">START DATE</span>
                <strong>{{ $program->start_date ? \Illuminate\Support\Carbon::parse($program->start_date)->format('M j, Y') : '—' }}</strong>
            </div>

            <div>
                <span class="detail-label">END DATE</span>
                <strong>{{ $program->end_date ? \Illuminate\Support\Carbon::parse($program->end_date)->format('M j, Y') : '—' }}</strong>
            </div>
        </div>
    </section>

    {{-- DATE FILTER --}}
    @if (auth()->user()->canUseButton('reports.filter'))
        <section class="panel section-gap">
            <form class="toolbar report-filters" method="get"
                  action="{{ route('reports.programs.distributions', $program) }}">

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

                <button class="button button-secondary" type="submit">
                    Apply Filters
                </button>
            </form>
        </section>
    @endif

    {{-- RECIPIENT TRANSACTIONS --}}
    <section class="panel section-gap">
        <div class="page-heading">
            <div>
                <span class="eyebrow">RECEIVED ASSISTANCE</span>
                <h2>Recipient Transactions</h2>
                <p>
                    Completed distribution records for this subsidy program.
                </p>
            </div>
        </div>

        @if ($distributions->isEmpty())
            <div class="empty-state">
                <span class="empty-icon">▤</span>
                <strong>No recipients found</strong>
                <p>No completed transactions match the selected program and date range.</p>
            </div>
        @else
            <div class="report-distribution-list">
                @foreach ($distributions as $distribution)
                    <article class="report-distribution-card">

                        {{-- TRANSACTION HEADER --}}
                        <div class="report-transaction-heading">
                            <div>
                                <span class="detail-label">TRANSACTION NUMBER</span>
                                <strong>{{ $distribution->transaction_number }}</strong>
                            </div>

                            <span class="status status-green">
                                {{ str($distribution->transaction_status)->title() }}
                            </span>
                        </div>

                        {{-- FARMER INFORMATION --}}
                        <div class="report-transaction-details">

                            <div>
                                <span class="detail-label">FARMER NAME</span>
                                <strong>{{ $distribution->farmer?->full_name ?? 'Farmer unavailable' }}</strong>
                                <small>{{ $distribution->farmer?->farmer_id ?? '—' }}</small>
                            </div>

                            <div>
                                <span class="detail-label">CONTACT NUMBER</span>
                                <strong>{{ $distribution->farmer?->contact_number ?? '—' }}</strong>
                            </div>

                            <div>
                                <span class="detail-label">COMPLETE ADDRESS</span>
                                <strong>
                                    {{ collect([
                                        $distribution->farmer?->barangay,
                                        $distribution->farmer?->municipality,
                                        $distribution->farmer?->province
                                    ])->filter()->implode(', ') ?: '—' }}
                                </strong>
                            </div>

                            <div>
                                <span class="detail-label">RSBSA NUMBER</span>
                                <strong>
                                    {{ $distribution->farmer?->rsbsaRecord?->rsbsa_number ?? 'No RSBSA record' }}
                                </strong>
                            </div>

                            <div>
                                <span class="detail-label">RSBSA VERIFICATION</span>
                                <strong>
                                    {{ $distribution->farmer?->rsbsaRecord
                                        ? str($distribution->farmer->rsbsaRecord->verification_status)->title()
                                        : 'No record' }}
                                </strong>
                            </div>
                        </div>

                        <div class="divider"></div>

                        {{-- DISTRIBUTION INFORMATION --}}
                        <div class="report-transaction-details">

                            <div>
                                <span class="detail-label">DATE AND TIME RECEIVED</span>
                                <strong>
                                    {{ $distribution->distributed_at
                                        ? $distribution->distributed_at->format('M j, Y · g:i A')
                                        : '—' }}
                                </strong>
                            </div>

                            <div>
                                <span class="detail-label">ELIGIBILITY STATUS</span>
                                <strong>
                                    {{ str($distribution->eligibility_status)->title() }}
                                </strong>
                            </div>

                            <div>
                                <span class="detail-label">SEASON</span>
                                <strong>{{ $distribution->season?->name ?? '—' }}</strong>
                            </div>

                            <div>
                                <span class="detail-label">FARMER GROUP</span>
                                <strong>
                                    {{ $distribution->seasonGroup
                                        ? str($distribution->seasonGroup->seed_type)->title()
                                        : '—' }}
                                </strong>
                            </div>

                            <div>
                                <span class="detail-label">PROCESSED BY</span>
                                <strong>{{ $distribution->processor?->name ?? 'Unavailable' }}</strong>
                            </div>

                            <div>
                                <span class="detail-label">FACE VERIFICATION DATE</span>
                                <strong>
                                    {{ $distribution->face_verified_at
                                        ? $distribution->face_verified_at->format('M j, Y · g:i A')
                                        : 'Not recorded' }}
                                </strong>
                            </div>

                            <div>
                                <span class="detail-label">FACE VERIFIED BY</span>
                                <strong>{{ $distribution->faceVerifier?->name ?? 'Not recorded' }}</strong>
                            </div>

                            <div>
                                <span class="detail-label">ELIGIBLE FARM AREA</span>
                                <strong>
                                    {{ $distribution->eligible_hectares !== null
                                        ? number_format((float) $distribution->eligible_hectares, 2).' hectares'
                                        : '—' }}
                                </strong>
                            </div>

                            <div>
                                <span class="detail-label">RECORDED ASSISTANCE VALUE</span>
                                <strong>
                                    ₱{{ number_format(
                                        (float) ($distribution->assistance_value ?? $distribution->items->sum('line_value')),
                                        2
                                    ) }}
                                </strong>
                            </div>
                        </div>

                        {{-- SUPPLIES RECEIVED --}}
                        <div class="report-issued-items">
                            <span class="detail-label">AGRICULTURAL SUPPLIES RECEIVED</span>

                            @if ($distribution->items->isEmpty())
                                <p>No supply line items were recorded.</p>
                            @else
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>SUPPLY NAME</th>
                                                <th>QUANTITY</th>
                                                <th>UNIT VALUE</th>
                                                <th>LINE VALUE</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @foreach ($distribution->items as $item)
                                                <tr>
                                                    <td>
                                                        {{ $item->supply?->supply_name ?? 'Supply unavailable' }}
                                                    </td>

                                                    <td>{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</td>

                                                    <td>
                                                        ₱{{ number_format((float) $item->unit_value, 2) }}
                                                    </td>

                                                    <td>
                                                        ₱{{ number_format((float) $item->line_value, 2) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>

                        {{-- REMARKS --}}
                        @if ($distribution->remarks)
                            <div class="report-transaction-remarks">
                                <span class="detail-label">DISTRIBUTION REMARKS</span>
                                <p>{{ $distribution->remarks }}</p>
                            </div>
                        @endif

                    </article>
                @endforeach
            </div>

            <div class="pagination-wrap">
                {{ $distributions->links() }}
            </div>
        @endif
    </section>
@endsection
