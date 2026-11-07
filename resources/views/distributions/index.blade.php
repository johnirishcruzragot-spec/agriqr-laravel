@extends('layouts.app')

@section('title', 'Distributions')
@section('section', 'Distributions')

@section('content')
    <div class="page-heading heading-with-action">
        <div><span class="eyebrow">ASSISTANCE DELIVERY</span><h1>Distributions</h1><p>Review issued supplies, season eligibility, and per-farmer quantity limits.</p></div>
        @if (auth()->user()->canUseButton('distributions.create'))<a class="button button-primary" href="{{ route('distributions.create') }}"><span>＋</span> New distribution</a>@endif
    </div>
    <section class="panel">
        @if ($distributions->isEmpty())
            <div class="empty-state"><span class="empty-icon">▣</span><strong>No distributions recorded</strong><p>Recorded assistance and program eligibility will appear here.</p>@if (auth()->user()->canUseButton('distributions.create'))<a class="button button-secondary" href="{{ route('distributions.create') }}">Start a distribution</a>@endif</div>
        @else
            <div class="table-wrap"><table><thead><tr><th>TRANSACTION</th><th>FARMER</th><th>PROGRAM</th><th>SEASON / GROUP</th><th>OPTIONAL VALUE</th><th>ITEMS</th><th>PROCESSED BY</th><th>DATE</th><th></th></tr></thead><tbody>
                @foreach ($distributions as $distribution)
                    <tr><td>@if (auth()->user()->canUseButton('distributions.view'))<a class="table-primary" href="{{ route('distributions.show', $distribution) }}">{{ $distribution->transaction_number }}</a>@else<strong>{{ $distribution->transaction_number }}</strong>@endif</td><td><strong>{{ $distribution->farmer->full_name }}</strong><small class="table-sub">{{ $distribution->farmer->farmer_id }}</small></td><td>{{ $distribution->program?->program_name ?? '—' }}</td><td>{{ $distribution->season?->name ?? '—' }}@if ($distribution->seasonGroup)<small class="table-sub">{{ str($distribution->seasonGroup->seed_type)->title() }}</small>@endif</td><td>{{ $distribution->assistance_value !== null ? 'PHP '.number_format((float) $distribution->assistance_value, 2) : '—' }}</td><td>{{ $distribution->items->count() }} items</td><td>{{ $distribution->processor->name }}</td><td>{{ $distribution->distributed_at->format('M j, Y · g:i A') }}</td><td>@if (auth()->user()->canUseButton('distributions.view'))<a class="table-action" href="{{ route('distributions.show', $distribution) }}">Receipt <span>→</span></a>@endif</td></tr>
                @endforeach
            </tbody></table></div>
            <div class="pagination-wrap">{{ $distributions->links() }}</div>
        @endif
    </section>
@endsection
