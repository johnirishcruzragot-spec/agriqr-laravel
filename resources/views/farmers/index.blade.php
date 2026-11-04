@extends('layouts.app')

@section('title', 'Farmers')
@section('section', 'Farmers')

@section('content')
    <div class="page-heading heading-with-action">
        <div><span class="eyebrow">FARMER DIRECTORY</span><h1>Farmers</h1><p>Manage farmer profiles, QR identities and RSBSA records.</p></div>
        <div class="heading-actions">
            <a class="button button-secondary" href="{{ route('farmers.import') }}">Import CSV</a>
            <a class="button button-primary" href="{{ route('farmers.create') }}"><span>＋</span> Add farmer</a>
        </div>
    </div>

    <section class="panel">
        <form class="toolbar" method="get" action="{{ route('farmers.index') }}">
            <label class="search-field"><span aria-hidden="true">⌕</span><input type="search" name="search" value="{{ $search }}" placeholder="Search name, farmer ID or barangay"></label>
            <select class="select-control" name="status" aria-label="Filter by status">
                <option value="">All statuses</option>
                <option value="active" @selected($status === 'active')>Active</option>
                <option value="inactive" @selected($status === 'inactive')>Inactive</option>
            </select>
            <button class="button button-secondary" type="submit">Apply filters</button>
            @if ($search || $status)<a class="text-link" href="{{ route('farmers.index') }}">Clear</a>@endif
        </form>

        @if ($farmers->isEmpty())
            <div class="empty-state"><span class="empty-icon">♧</span><strong>No farmer profiles found</strong><p>Try changing your search or register a new farmer.</p><a class="button button-secondary" href="{{ route('farmers.create') }}">Add farmer</a></div>
        @else
            <div class="table-wrap">
                <table>
                    <thead><tr><th>FARMER</th><th>LOCATION</th><th>RSBSA</th><th>REGISTERED</th><th>STATUS</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($farmers as $farmer)
                            @php
                                $location = collect([
                                    $farmer->rsbsaRecord?->farmer_address_1,
                                    $farmer->rsbsaRecord?->farmer_address_2,
                                    $farmer->rsbsaRecord?->farmer_address_3,
                                ])->filter()->implode(', ') ?: collect([
                                    $farmer->barangay,
                                    $farmer->municipality,
                                ])->filter()->implode(', ');
                            @endphp
                            <tr>
                                <td><div class="person-cell"><span class="person-avatar">{{ strtoupper(substr($farmer->first_name, 0, 1).substr($farmer->last_name, 0, 1)) }}</span><div><a class="table-primary" href="{{ route('farmers.show', $farmer) }}">{{ $farmer->full_name }}</a><small class="table-sub">{{ $farmer->farmer_id }}</small></div></div></td>
                                <td>{{ $location ?: '—' }}</td>
                                <td>@if ($farmer->rsbsaRecord?->verification_status === 'verified')<span class="status status-green">Verified</span>@elseif ($farmer->rsbsaRecord)<span class="status status-amber">{{ str($farmer->rsbsaRecord->verification_status)->title() }}</span>@else<span class="status status-neutral">Not registered</span>@endif</td>
                                <td>{{ $farmer->created_at->format('M j, Y') }}</td>
                                <td><span class="status {{ $farmer->status === 'active' ? 'status-green' : 'status-neutral' }}">{{ str($farmer->status)->title() }}</span></td>
                                <td><a class="table-action" href="{{ route('farmers.show', $farmer) }}">View <span>→</span></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $farmers->links() }}</div>
        @endif
    </section>
@endsection
