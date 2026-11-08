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

    @if (! $farmer->exists)
        <section class="panel farmer-registration-page" data-registration-wizard-container data-open-step="{{ $errors->has('rsbsa_number') || $errors->has('registration_status') || $errors->has('verification_status') || $errors->has('registration_date') || $errors->has('farm_size') || $errors->has('farm_size_unit') || $errors->has('crop_information') || $errors->has('farm_location') || $errors->has('tenure_type') || $errors->has('landowner_name') || $errors->has('landowner_contact') || $errors->has('farm_remarks') || $errors->has('season_groups') || $errors->has('possible_duplicate') ? 2 : 1 }}">
            <p class="farmer-step-status" data-registration-status role="status">Step 1 of 2: Personal information</p>
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
        </section>
    @else
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
    @endif
@endsection
