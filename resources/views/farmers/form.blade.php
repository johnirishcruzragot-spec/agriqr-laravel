@extends('layouts.app')

@section('title', $farmer->exists ? 'Edit farmer' : 'Add farmer')
@section('section', 'Farmers')

@section('content')
    <div class="page-heading">
        <a class="back-link" href="{{ $farmer->exists ? route('farmers.show', $farmer) : route('farmers.index') }}">← Back to farmers</a>
        <span class="eyebrow">FARMER DIRECTORY</span>
        <h1>{{ $farmer->exists ? 'Edit farmer profile' : 'Register a farmer' }}</h1>
        <p>Keep contact, farm and RSBSA details together in one profile.</p>
    </div>

    <form method="post" action="{{ $farmer->exists ? route('farmers.update', $farmer) : route('farmers.store') }}" class="form-layout">
        @csrf
        @if ($farmer->exists) @method('put') @endif

        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-number">01</span><div><h2>Personal information</h2><p>Basic details used to identify and contact the farmer.</p></div></div>
            <div class="form-grid">
                <label class="field"><span>First name <b>*</b></span><input name="first_name" value="{{ old('first_name', $farmer->first_name) }}" maxlength="100" required></label>
                <label class="field"><span>Middle name</span><input name="middle_name" value="{{ old('middle_name', $farmer->middle_name) }}" maxlength="100"></label>
                <label class="field"><span>Last name <b>*</b></span><input name="last_name" value="{{ old('last_name', $farmer->last_name) }}" maxlength="100" required></label>
                <label class="field"><span>Suffix</span><input name="suffix" value="{{ old('suffix', $farmer->suffix) }}" maxlength="20" placeholder="Jr., Sr., III"></label>
                <label class="field"><span>Contact number</span><input name="contact_number" value="{{ old('contact_number', $farmer->contact_number) }}" maxlength="30" autocomplete="tel"></label>
                <label class="field"><span>Profile status</span><select name="status"><option value="active" @selected(old('status', $farmer->status ?: 'active') === 'active')>Active</option><option value="inactive" @selected(old('status', $farmer->status) === 'inactive')>Inactive</option></select></label>
                <label class="field field-wide"><span>Street address <b>*</b></span><input name="address" value="{{ old('address', $farmer->address) }}" maxlength="2000" required></label>
                <label class="field"><span>Barangay <b>*</b></span><input name="barangay" value="{{ old('barangay', $farmer->barangay) }}" maxlength="100" required></label>
                <label class="field"><span>Municipality / city <b>*</b></span><input name="municipality" value="{{ old('municipality', $farmer->municipality) }}" maxlength="100" required></label>
                <label class="field"><span>Province <b>*</b></span><input name="province" value="{{ old('province', $farmer->province) }}" maxlength="100" required></label>
                <label class="field field-wide"><span>Farm notes</span><textarea name="farm_information" rows="3" maxlength="5000">{{ old('farm_information', $farmer->farm_information) }}</textarea></label>
            </div>
        </section>

        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-number">02</span><div><h2>Farm and RSBSA registration</h2><p>Verified RSBSA details are required before a distribution can be recorded.</p></div></div>
            <div class="form-grid">
                <label class="field"><span>RSBSA number</span><input name="rsbsa_number" value="{{ old('rsbsa_number', $farmer->rsbsaRecord?->rsbsa_number) }}" maxlength="100" placeholder="Leave blank if not registered"></label>
                <label class="field"><span>Registration status</span><select name="registration_status">@foreach (['registered', 'pending', 'unregistered'] as $value)<option value="{{ $value }}" @selected(old('registration_status', $farmer->rsbsaRecord?->registration_status ?? 'registered') === $value)>{{ str($value)->title() }}</option>@endforeach</select></label>
                <label class="field"><span>Verification status</span><select name="verification_status">@foreach (['unverified', 'pending', 'verified'] as $value)<option value="{{ $value }}" @selected(old('verification_status', $farmer->rsbsaRecord?->verification_status ?? 'unverified') === $value)>{{ str($value)->title() }}</option>@endforeach</select></label>
                <label class="field"><span>Registration date</span><input type="date" name="registration_date" value="{{ old('registration_date', $farmer->rsbsaRecord?->registration_date?->format('Y-m-d')) }}"></label>
                <label class="field"><span>Farm size</span><div class="input-with-suffix"><input type="number" step="0.01" min="0" name="farm_size" value="{{ old('farm_size', $farmer->rsbsaRecord?->farm_size ?? $farmer->farmInfo?->farm_size_hectares) }}"><select name="farm_size_unit"><option value="hectare" @selected(old('farm_size_unit', $farmer->rsbsaRecord?->farm_size_unit ?? 'hectare') === 'hectare')>hectares</option><option value="sqm" @selected(old('farm_size_unit', $farmer->rsbsaRecord?->farm_size_unit) === 'sqm')>sqm</option><option value="acre" @selected(old('farm_size_unit', $farmer->rsbsaRecord?->farm_size_unit) === 'acre')>acres</option></select></div></label>
                <label class="field"><span>Tenure</span><select name="tenure_type"><option value="">Select tenure</option><option value="owner" @selected(old('tenure_type', $farmer->farmInfo?->tenure_type) === 'owner')>Owner</option><option value="tenant" @selected(old('tenure_type', $farmer->farmInfo?->tenure_type) === 'tenant')>Tenant</option></select></label>
                <label class="field"><span>Farm location</span><input name="farm_location" value="{{ old('farm_location', $farmer->farmInfo?->farm_location) }}" maxlength="255"></label>
                <label class="field"><span>Landowner name</span><input name="landowner_name" value="{{ old('landowner_name', $farmer->farmInfo?->landowner_name) }}" maxlength="150"></label>
                <label class="field"><span>Landowner contact</span><input name="landowner_contact" value="{{ old('landowner_contact', $farmer->farmInfo?->landowner_contact) }}" maxlength="30"></label>
                <label class="field field-wide"><span>Crop information</span><textarea name="crop_information" rows="3" maxlength="5000">{{ old('crop_information', $farmer->rsbsaRecord?->crop_information ?? $farmer->farmInfo?->crop_information) }}</textarea></label>
                <label class="field field-wide"><span>Farm remarks</span><textarea name="farm_remarks" rows="2" maxlength="5000">{{ old('farm_remarks', $farmer->farmInfo?->remarks) }}</textarea></label>
            </div>
        </section>

        <div class="form-actions"><a class="button button-secondary" href="{{ $farmer->exists ? route('farmers.show', $farmer) : route('farmers.index') }}">Cancel</a><button class="button button-primary" type="submit">{{ $farmer->exists ? 'Save changes' : 'Create farmer profile' }}</button></div>
    </form>
@endsection
