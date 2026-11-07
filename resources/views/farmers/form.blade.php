@extends('layouts.app')

@section('title', $farmer->exists ? 'Edit farmer' : 'Add farmer')
@section('section', 'Farmers')

@section('content')
    <div class="page-heading">
        @if (($farmer->exists && auth()->user()->canUseButton('farmers.view')) || (! $farmer->exists && auth()->user()->canUseButton('farmers.list')))
            <a class="back-link" href="{{ $farmer->exists ? route('farmers.show', $farmer) : route('farmers.index') }}">← Back to farmers</a>
        @endif
        <span class="eyebrow">FARMER DIRECTORY</span>
        <h1>{{ $farmer->exists ? 'Edit farmer profile' : 'Register a farmer' }}</h1>
        <p>Keep contact, farm and RSBSA details together in one profile.</p>
    </div>

    @include('farmers._form', [
        'farmer' => $farmer,
        'isRegistrationDialog' => false,
        'customFields' => $customFields,
        'barangays' => $barangays,
        'seasonGroupChoices' => $seasonGroupChoices,
        'currentSeasonGroupIds' => $currentSeasonGroupIds,
        'canManageSeasonGroups' => $canManageSeasonGroups,
        'possibleDuplicateCandidates' => $possibleDuplicateCandidates,
    ])
@endsection
