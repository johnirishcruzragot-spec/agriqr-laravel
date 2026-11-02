@extends('layouts.app')

@section('title', 'Distributions')
@section('section', 'Distributions')

@section('content')
    <div class="page-heading heading-with-action">
        <div><span class="eyebrow">ASSISTANCE DELIVERY</span><h1>Distributions</h1><p>Review completed transactions and start a new farmer distribution.</p></div>
        <a class="button button-primary" href="{{ route('distributions.create') }}"><span>＋</span> New distribution</a>
    </div>
    <section class="panel">
        @if ($distributions->isEmpty())
            <div class="empty-state"><span class="empty-icon">▣</span><strong>No distributions recorded</strong><p>Record a distribution after confirming farmer registration and program eligibility.</p><a class="button button-secondary" href="{{ route('distributions.create') }}">Start a distribution</a></div>
        @else
            <div class="table-wrap"><table><thead><tr><th>TRANSACTION</th><th>FARMER</th><th>PROGRAM</th><th>ITEMS</th><th>PROCESSED BY</th><th>DATE</th><th></th></tr></thead><tbody>
                @foreach ($distributions as $distribution)
                    <tr><td><a class="table-primary" href="{{ route('distributions.show', $distribution) }}">{{ $distribution->transaction_number }}</a></td><td><strong>{{ $distribution->farmer->full_name }}</strong><small class="table-sub">{{ $distribution->farmer->farmer_id }}</small></td><td>{{ $distribution->program?->program_name ?? '—' }}</td><td>{{ $distribution->items->count() }} items</td><td>{{ $distribution->processor->name }}</td><td>{{ $distribution->distributed_at->format('M j, Y · g:i A') }}</td><td><a class="table-action" href="{{ route('distributions.show', $distribution) }}">Receipt <span>→</span></a></td></tr>
                @endforeach
            </tbody></table></div>
            <div class="pagination-wrap">{{ $distributions->links() }}</div>
        @endif
    </section>
@endsection
