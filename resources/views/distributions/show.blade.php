@extends('layouts.app')

@section('title', 'Distribution receipt')
@section('section', 'Distributions')

@section('content')
    <div class="page-heading heading-with-action no-print">
        <div>@if (auth()->user()->canUseButton('distributions.list'))<a class="back-link" href="{{ route('distributions.index') }}">← Distribution history</a>@endif<span class="eyebrow">TRANSACTION RECEIPT</span><h1>Distribution recorded</h1><p>Stock has been updated and the transaction is in the audit history.</p></div>
        @if (auth()->user()->canUseButton('distributions.print'))<button class="button button-secondary" type="button" onclick="window.print()">Print receipt</button>@endif
    </div>
    <section class="panel receipt-panel">
        <div class="receipt-brand"><span class="receipt-mark">A</span><div><strong>AgriQR</strong><small>Municipal Agriculture Office</small></div><span class="receipt-status status status-green">Completed</span></div>
        <div class="receipt-number"><span>TRANSACTION NUMBER</span><strong>{{ $distribution->transaction_number }}</strong><small>{{ $distribution->distributed_at->format('F j, Y · g:i A') }}</small></div>
        <div class="receipt-details">
            <div><span class="detail-label">FARMER</span><strong>{{ $distribution->farmer->full_name }}</strong><small>{{ $distribution->farmer->farmer_id }} · {{ collect([$distribution->farmer->barangay, $distribution->farmer->municipality])->filter()->implode(', ') }}</small></div>
            <div><span class="detail-label">SUBSIDY PROGRAM</span><strong>{{ $distribution->program?->program_name ?? '—' }}</strong><small>{{ $distribution->program?->program_code }}@if ($distribution->program) · {{ str($distribution->program->subsidy_type)->replace('_', ' ')->title() }}@endif</small></div>
            <div><span class="detail-label">ELIGIBLE SEASON / GROUP</span><strong>{{ $distribution->season?->name ?? '—' }}@if ($distribution->seasonGroup) · {{ str($distribution->seasonGroup->seed_type)->title() }}@endif</strong><small>{{ $distribution->eligible_hectares !== null ? number_format((float) $distribution->eligible_hectares, 4).' eligible hectares' : 'Farm size not recorded' }}</small></div>
            <div><span class="detail-label">PROCESSED BY</span><strong>{{ $distribution->processor->name }}</strong><small>Face verification recorded{{ $distribution->face_verified_at ? ' · '.$distribution->face_verified_at->format('M j, Y · g:i A') : '' }}</small></div>
        </div>
        <div class="receipt-items"><h2>Items issued</h2><table><thead><tr><th>SUPPLY</th><th>FINAL QTY</th><th>RATE / HA</th><th>CALCULATED</th><th>MAX / FARMER</th><th>REDUCTION REASON</th><th>PHP / UNIT</th><th>VALUE</th></tr></thead><tbody>@foreach ($distribution->items as $item)<tr><td><strong>{{ $item->supply->supply_name }}</strong><small class="table-sub">{{ $item->supply->supply_code }}</small></td><td><strong>{{ number_format((float) $item->quantity, 2) }}@if ($item->unit) {{ $item->unit }}@endif</strong></td><td>{{ $item->quantity_rate_per_hectare !== null ? number_format((float) $item->quantity_rate_per_hectare, 4).($item->unit ? ' '.$item->unit : '').'/ha' : '—' }}</td><td>{{ $item->computed_quantity !== null ? number_format((float) $item->computed_quantity, 2).($item->unit ? ' '.$item->unit : '') : '—' }}</td><td>{{ $item->maximum_quantity_per_farmer !== null ? number_format((float) $item->maximum_quantity_per_farmer, 2).($item->unit ? ' '.$item->unit : '') : '—' }}</td><td>{{ $item->reduction_reason ?: '—' }}</td><td>{{ number_format((float) $item->unit_value, 2) }}</td><td>{{ number_format((float) $item->line_value, 2) }}</td></tr>@endforeach</tbody><tfoot><tr><th colspan="7">Optional recorded assistance value</th><th>PHP {{ $distribution->assistance_value !== null ? number_format((float) $distribution->assistance_value, 2) : '—' }}</th></tr></tfoot></table></div>
        @if ($distribution->remarks)<div class="receipt-remarks"><span class="detail-label">NOTES</span><p>{{ $distribution->remarks }}</p></div>@endif
        <div class="receipt-footer">Thank you for supporting local agriculture.</div>
    </section>
@endsection
