@extends('layouts.app')

@section('title', 'Seasons and groups')
@section('section', 'Seasons and groups')

@section('content')
    <div class="page-heading heading-with-action">
        <div><span class="eyebrow">SEASON MANAGEMENT</span><h1>Seasons and farmer groups</h1><p>Manage named seasons and their Hybrid and Inbred groups. Farmer assignments and program rates retain their history.</p></div>
        @if (auth()->user()->canUseButton('seasons.create'))
            <a class="button button-primary" href="{{ route('seasons.create') }}"><span>＋</span> Create season</a>
        @endif
    </div>

    <section class="panel">
        @if ($seasons->isEmpty())
            <div class="empty-state"><span class="empty-icon">◇</span><strong>No seasons created</strong><p>Create a season to assign farmers and configure program eligibility.</p>@if (auth()->user()->canUseButton('seasons.create'))<a class="button button-secondary" href="{{ route('seasons.create') }}">Create season</a>@endif</div>
        @else
            <div class="table-wrap">
                <table>
                    <thead><tr><th>SEASON</th><th>HYBRID GROUP</th><th>INBRED GROUP</th><th>STATUS</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($seasons as $season)
                            <tr>
                                <td><strong>{{ $season->name }}</strong></td>
                                @foreach (['hybrid', 'inbred'] as $seedType)
                                    @php($group = $season->groups->firstWhere('seed_type', $seedType))
                                    <td>{{ $group?->assignments_count ?? 0 }} farmer assignments</td>
                                @endforeach
                                <td><span class="status {{ $season->status === 'active' ? 'status-green' : 'status-neutral' }}">{{ str($season->status)->title() }}</span></td>
                                <td>@if (auth()->user()->canUseButton('seasons.edit'))<a class="table-action" href="{{ route('seasons.edit', $season) }}">Manage <span>→</span></a>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $seasons->links() }}</div>
        @endif
    </section>
@endsection
