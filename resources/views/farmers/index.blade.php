@extends('layouts.app')

@section('title', 'Farmers')
@section('section', 'Farmers')

@section('content')
    <div class="page-heading">
        <span class="eyebrow">FARMER DIRECTORY</span><h1>Farmers</h1><p>Manage farmer profiles, QR identities and RSBSA records.</p>
    </div>

    @if (auth()->user()->canUseButton('farmers.import') || auth()->user()->canUseButton('farmers.create'))
        <section class="farmer-action-grid" aria-label="Farmer actions">
            @if (auth()->user()->canUseButton('farmers.import'))
                <a class="farmer-action-card" href="{{ route('farmers.import') }}">
                    <span class="farmer-action-icon" aria-hidden="true">⇧</span>
                    <span class="farmer-action-copy"><strong>Import CSV</strong><small>Upload RSBSA farmer records from a spreadsheet.</small></span>
                    <span class="farmer-action-arrow" aria-hidden="true">→</span>
                </a>
            @endif
            @if (auth()->user()->canUseButton('farmers.create'))
                @if (auth()->user()->role === 'admin')
                    <button class="farmer-action-card" type="button" data-open-dialog="farmer-register-dialog">
                @else
                    <a class="farmer-action-card" href="{{ route('farmers.create') }}">
                @endif
                    <span class="farmer-action-icon" aria-hidden="true">＋</span>
                    <span class="farmer-action-copy"><strong>Register a farmer</strong><small>Create a profile with a guided two-step form.</small></span>
                    <span class="farmer-action-arrow" aria-hidden="true">→</span>
                @if (auth()->user()->role === 'admin')
                    </button>
                @else
                    </a>
                @endif
            @endif
        </section>
    @endif

    <section class="panel">
        <form class="toolbar" method="get" action="{{ route('farmers.index') }}">
            <label class="search-field"><span aria-hidden="true">⌕</span><input type="search" name="search" value="{{ $search }}" placeholder="Search name, farmer ID or barangay"></label>
            <select class="select-control" name="status" aria-label="Filter by status">
                <option value="">All statuses</option>
                <option value="active" @selected($status === 'active')>Active</option>
                <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                <option value="deceased" @selected($status === 'deceased')>Deceased</option>
            </select>
            @if ($seasons->isNotEmpty())
                <select class="select-control" name="season" aria-label="Filter by crop season">
                    <option value="">All seasons</option>
                    @foreach ($seasons as $availableSeason)
                        <option value="{{ $availableSeason->id }}" @selected((string) $season === (string) $availableSeason->id)>{{ $availableSeason->name }}</option>
                    @endforeach
                </select>
                <select class="select-control" name="seed_type" aria-label="Filter by seed group">
                    <option value="">Hybrid and Inbred</option>
                    <option value="hybrid" @selected($seedType === 'hybrid')>Hybrid</option>
                    <option value="inbred" @selected($seedType === 'inbred')>Inbred</option>
                </select>
            @endif
            @if (auth()->user()->canUseButton('farmers.filter'))
                <button class="button button-secondary" type="submit">Apply filters</button>
                @if ($search || $status || $season || $seedType)<a class="text-link" href="{{ route('farmers.index') }}">Clear</a>@endif
            @endif
        </form>

        @if ($farmers->isEmpty())
            <div class="empty-state"><span class="empty-icon">♧</span><strong>No farmer profiles found</strong><p>Try changing your search or register a new farmer.</p>@if (auth()->user()->canUseButton('farmers.create'))<a class="button button-secondary" href="{{ route('farmers.create') }}">Add farmer</a>@endif</div>
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
                                <td><div class="person-cell"><span class="person-avatar">{{ strtoupper(substr($farmer->first_name, 0, 1).substr($farmer->last_name, 0, 1)) }}</span><div>@if (auth()->user()->canUseButton('farmers.view'))<a class="table-primary" href="{{ route('farmers.show', $farmer) }}">{{ $farmer->full_name }}</a>@else<strong>{{ $farmer->full_name }}</strong>@endif<small class="table-sub">{{ $farmer->farmer_id }}</small></div></div></td>
                                <td>{{ $location ?: '—' }}</td>
                                <td>@if ($farmer->rsbsaRecord?->verification_status === 'verified')<span class="status status-green">Verified</span>@elseif ($farmer->rsbsaRecord)<span class="status status-amber">{{ str($farmer->rsbsaRecord->verification_status)->title() }}</span>@else<span class="status status-neutral">Not registered</span>@endif</td>
                                <td>{{ $farmer->created_at->format('M j, Y') }}</td>
                                <td><span class="status {{ $farmer->status === 'active' ? 'status-green' : ($farmer->status === 'deceased' ? 'status-amber' : 'status-neutral') }}">{{ str($farmer->status)->title() }}</span></td>
                                <td>@if (auth()->user()->canUseButton('farmers.view'))<a class="table-action" href="{{ route('farmers.show', $farmer) }}">View <span>→</span></a>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $farmers->links() }}</div>
        @endif
    </section>

    @if (auth()->user()->canUseButton('farmers.create') && auth()->user()->role === 'admin')
    <dialog class="farmer-register-dialog" id="farmer-register-dialog" aria-labelledby="farmer-register-title" data-registration-dialog data-open-step="{{ $errors->has('rsbsa_number') || $errors->has('registration_status') || $errors->has('verification_status') || $errors->has('registration_date') || $errors->has('farm_size') || $errors->has('farm_size_unit') || $errors->has('crop_information') || $errors->has('farm_location') || $errors->has('tenure_type') || $errors->has('landowner_name') || $errors->has('landowner_contact') || $errors->has('farm_remarks') || $errors->has('season_groups') || $errors->has('possible_duplicate') ? 2 : 1 }}" @if ($errors->any()) data-open-on-load @endif>
        <div class="farmer-register-card" data-registration-wizard-container>
            <div class="farmer-register-heading">
                <div><span class="eyebrow">FARMER DIRECTORY</span><h2 id="farmer-register-title">Register a farmer</h2><p>Keep contact, farm and RSBSA details together in one profile.</p></div>
                <button class="stock-dialog-close" type="button" data-close-dialog aria-label="Close dialog">×</button>
            </div>
            <p class="farmer-step-status" data-registration-status role="status">Step 1 of 2: Personal information</p>
            @include('farmers._form', ['farmer' => $newFarmer, 'isRegistrationDialog' => true, 'customFields' => $customFields, 'barangays' => $barangays, 'seasonGroupChoices' => $seasonGroupChoices, 'currentSeasonGroupIds' => $currentSeasonGroupIds, 'canManageSeasonGroups' => $canManageSeasonGroups, 'possibleDuplicateCandidates' => $possibleDuplicateCandidates])
        </div>
    </dialog>
    @endif
@endsection
