@extends('layouts.app')

@section('title', 'Distribution receipt')
@section('section', 'Distributions')

@section('content')
    <div class="page-heading heading-with-action no-print">
        <div><a class="back-link" href="{{ route('distributions.index') }}">← Distribution history</a><span class="eyebrow">TRANSACTION RECEIPT</span><h1>Distribution recorded</h1><p>Stock has been updated and the transaction is in the audit history.</p></div>
        <button class="button button-secondary" type="button" onclick="window.print()">Print receipt</button>
    </div>
    <section class="panel receipt-panel">
        <div class="receipt-brand"><span class="receipt-mark">A</span><div><strong>AgriQR</strong><small>Municipal Agriculture Office</small></div><span class="receipt-status status status-green">Completed</span></div>
        <div class="receipt-number"><span>TRANSACTION NUMBER</span><strong>{{ $distribution->transaction_number }}</strong><small>{{ $distribution->distributed_at->format('F j, Y · g:i A') }}</small></div>
        <div class="receipt-details">
            <div><span class="detail-label">FARMER</span><strong>{{ $distribution->farmer->full_name }}</strong><small>{{ $distribution->farmer->farmer_id }} · {{ collect([$distribution->farmer->barangay, $distribution->farmer->municipality])->filter()->implode(', ') }}</small></div>
            <div><span class="detail-label">SUBSIDY PROGRAM</span><strong>{{ $distribution->program?->program_name ?? '—' }}</strong><small>{{ $distribution->program?->program_code }}</small></div>
            <div><span class="detail-label">PROCESSED BY</span><strong>{{ $distribution->processor->name }}</strong><small>RSBSA verified</small></div>
        </div>
        <div class="receipt-items"><h2>Items issued</h2><table><thead><tr><th>SUPPLY</th><th>CODE</th><th>QUANTITY</th></tr></thead><tbody>@foreach ($distribution->items as $item)<tr><td><strong>{{ $item->supply->supply_name }}</strong></td><td>{{ $item->supply->supply_code }}</td><td><strong>{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</strong></td></tr>@endforeach</tbody></table></div>
        @if ($distribution->remarks)<div class="receipt-remarks"><span class="detail-label">NOTES</span><p>{{ $distribution->remarks }}</p></div>@endif
        <div class="receipt-footer">Thank you for supporting local agriculture.</div>
    </section>
@endsection
