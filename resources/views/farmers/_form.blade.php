@if ($errors->any())
    <div class="inline-notice" role="alert">
        <strong>Please correct the following:</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php($isRegistrationWizard = ! $farmer->exists)

<form method="post" action="{{ $farmer->exists ? route('farmers.update', $farmer) : route('farmers.store') }}" class="form-layout" @if ($isRegistrationWizard) data-registration-wizard novalidate @endif>
    @csrf
    @if ($farmer->exists)
        @method('put')
    @endif

    @if ($possibleDuplicateCandidates !== [])
        <div class="inline-notice" role="alert">
            <strong>Possible existing farmer profiles</strong>
            <ul>
                @foreach ($possibleDuplicateCandidates as $candidate)
                    <li>{{ $candidate['full_name'] }} - {{ $candidate['farmer_id'] }} - {{ $candidate['location'] ?: 'Location not provided' }}</li>
                @endforeach
            </ul>
            <label class="check-line"><input type="checkbox" name="confirm_possible_duplicate" value="1" @checked(old('confirm_possible_duplicate')) required> I reviewed these matches and confirm this is a different farmer.</label>
        </div>
    @endif

    <section class="panel form-panel" @if ($isRegistrationWizard) data-registration-step="1" @endif>
        <div class="form-section-heading"><span class="section-number">01</span><div><h2>Personal information</h2><p>Basic details used to identify and contact the farmer.</p></div></div>
        <div class="form-grid">
            <label class="field"><span>First name <b>*</b></span><input name="first_name" value="{{ old('first_name', $farmer->first_name) }}" maxlength="100" required></label>
            <label class="field"><span>Middle name</span><input name="middle_name" value="{{ old('middle_name', $farmer->middle_name) }}" maxlength="100"></label>
            <label class="field"><span>Last name <b>*</b></span><input name="last_name" value="{{ old('last_name', $farmer->last_name) }}" maxlength="100" required></label>
            <label class="field"><span>Suffix</span><input name="suffix" value="{{ old('suffix', $farmer->suffix) }}" maxlength="20" placeholder="Jr., Sr., III"></label>
            <label class="field"><span>Contact number</span><input name="contact_number" value="{{ old('contact_number', $farmer->contact_number) }}" maxlength="30" autocomplete="tel"></label>
            <label class="field"><span>Profile status</span><select name="status"><option value="active" @selected(old('status', $farmer->status ?: 'active') === 'active')>Active</option><option value="inactive" @selected(old('status', $farmer->status) === 'inactive')>Inactive</option>@if (auth()->user()->role === 'admin')<option value="deceased" @selected(old('status', $farmer->status) === 'deceased')>Deceased</option>@endif</select></label>
            @if (auth()->user()->role === 'admin')
                <label class="field"><span>Date deceased <small>(optional)</small></span><input type="date" name="deceased_at" value="{{ old('deceased_at', $farmer->deceased_at?->format('Y-m-d')) }}" max="{{ now()->toDateString() }}"></label>
            @endif
            <label class="field field-wide"><span>Street address <b>*</b></span><input name="address" value="{{ old('address', $farmer->address) }}" maxlength="2000" required></label>
            <label class="field"><span>Barangay <b>*</b></span><select name="barangay" required><option value="">Select barangay</option>@foreach ($barangays as $barangay)<option value="{{ $barangay }}" @selected(old('barangay', $farmer->barangay) === $barangay)>{{ $barangay }}</option>@endforeach</select>@if ($barangays === [])<small>Barangays must be configured by an Admin before registering new farmers.</small>@endif</label>
            <label class="field"><span>Municipality / city <b>*</b></span><input name="municipality" value="{{ old('municipality', $farmer->municipality) }}" maxlength="100" required></label>
            <label class="field"><span>Province <b>*</b></span><input name="province" value="{{ old('province', $farmer->province) }}" maxlength="100" required></label>
            <label class="field field-wide"><span>Farm notes</span><textarea name="farm_information" rows="3" maxlength="5000">{{ old('farm_information', $farmer->farm_information) }}</textarea></label>
            @foreach ($customFields as $field)
                @php($customValue = old("custom_fields.{$field['key']}", $farmer->custom_fields[$field['key']] ?? ''))
                <label class="field">
                    <span>{{ $field['label'] }} @if ($field['required'])<b>*</b>@endif</span>
                    @if ($field['type'] === 'select')
                        <select name="custom_fields[{{ $field['key'] }}]" @required($field['required'])>
                            <option value="">Select {{ str($field['label'])->lower() }}</option>
                            @foreach ($field['options'] as $option)
                                <option value="{{ $option }}" @selected($customValue === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="{{ in_array($field['type'], ['number', 'date'], true) ? $field['type'] : 'text' }}" name="custom_fields[{{ $field['key'] }}]" value="{{ $customValue }}" maxlength="1000" @if ($field['type'] === 'number') step="any" @endif @required($field['required'])>
                    @endif
                </label>
            @endforeach
        </div>
    </section>

    <section class="panel form-panel" @if ($isRegistrationWizard) data-registration-step="2" hidden @endif>
        <div class="form-section-heading"><span class="section-number">02</span><div><h2>Farm and RSBSA registration</h2><p>Verified RSBSA details are required before a distribution can be recorded.</p></div></div>
        <div class="form-grid">
            <label class="field"><span>RSBSA number</span><input name="rsbsa_number" value="{{ old('rsbsa_number', $farmer->rsbsaRecord?->rsbsa_number) }}" maxlength="100" placeholder="Leave blank if not registered"></label>
            <label class="field"><span>Registration status</span><select name="registration_status">@foreach (['registered', 'pending', 'unregistered'] as $value)<option value="{{ $value }}" @selected(old('registration_status', $farmer->rsbsaRecord?->registration_status ?? 'registered') === $value)>{{ str($value)->title() }}</option>@endforeach</select></label>
            
<label class="field">
    <span>Verification status</span>
    <select name="verification_status">
        @foreach (
            auth()->user()->hasAnyRole('admin')
                ? ['unverified', 'pending', 'verified']
                : ['unverified', 'pending']
            as $value
        )
            <option
                value="{{ $value }}"
                @selected(
                    old(
                        'verification_status',
                        $farmer->rsbsaRecord?->verification_status ?? 'unverified'
                    ) === $value
                )
            >
                {{ str($value)->title() }}
            </option>
        @endforeach
    </select>
</label>

            <label class="field"><span>Registration date</span><input type="date" name="registration_date" value="{{ old('registration_date', $farmer->rsbsaRecord?->registration_date?->format('Y-m-d')) }}"></label>
            @php($farmSizeUnit = old('farm_size_unit', $farmer->rsbsaRecord?->farm_size_unit ?? 'hectare'))
            <label class="field"><span>Farm size</span><div class="input-with-suffix"><input type="number" step="0.01" min="0.01" max="{{ \App\Models\RsbsaRecord::FARM_SIZE_LIMITS[$farmSizeUnit] ?? \App\Models\RsbsaRecord::FARM_SIZE_LIMITS['hectare'] }}" name="farm_size" data-farm-size value="{{ old('farm_size', $farmer->rsbsaRecord?->farm_size ?? $farmer->farmInfo?->farm_size_hectares) }}"><select name="farm_size_unit" data-farm-size-unit><option value="hectare" data-maximum="{{ \App\Models\RsbsaRecord::FARM_SIZE_LIMITS['hectare'] }}" @selected($farmSizeUnit === 'hectare')>hectares</option><option value="sqm" data-maximum="{{ \App\Models\RsbsaRecord::FARM_SIZE_LIMITS['sqm'] }}" @selected($farmSizeUnit === 'sqm')>sqm</option><option value="acre" data-maximum="{{ \App\Models\RsbsaRecord::FARM_SIZE_LIMITS['acre'] }}" @selected($farmSizeUnit === 'acre')>acres</option></select></div><small>Must be greater than zero; maximum 1,000 ha, 10,000,000 sqm, or 2,500 acres.</small></label>
            <label class="field"><span>Tenure</span><select name="tenure_type"><option value="">Select tenure</option><option value="owner" @selected(old('tenure_type', $farmer->farmInfo?->tenure_type) === 'owner')>Owner</option><option value="tenant" @selected(old('tenure_type', $farmer->farmInfo?->tenure_type) === 'tenant')>Tenant</option></select></label>
            <label class="field"><span>Farm location</span><input name="farm_location" value="{{ old('farm_location', $farmer->farmInfo?->farm_location) }}" maxlength="255"></label>
            <label class="field"><span>Landowner name</span><input name="landowner_name" value="{{ old('landowner_name', $farmer->farmInfo?->landowner_name) }}" maxlength="150"></label>
            <label class="field"><span>Landowner contact</span><input name="landowner_contact" value="{{ old('landowner_contact', $farmer->farmInfo?->landowner_contact) }}" maxlength="30"></label>
            <label class="field field-wide"><span>Crop information</span><textarea name="crop_information" rows="3" maxlength="5000">{{ old('crop_information', $farmer->rsbsaRecord?->crop_information ?? $farmer->farmInfo?->crop_information) }}</textarea></label>
            <label class="field field-wide"><span>Farm remarks</span><textarea name="farm_remarks" rows="2" maxlength="5000">{{ old('farm_remarks', $farmer->farmInfo?->remarks) }}</textarea></label>
        </div>
        <div class="rsbsa-subsection">
            <span class="detail-label">SEASONAL HYBRID / INBRED GROUPS</span>
            @if ($canManageSeasonGroups)
                @if ($seasonGroupChoices->isNotEmpty())
                    <div class="form-grid">
                        @foreach ($seasonGroupChoices as $season)
                            @php($selectedSeasonGroupId = old("season_groups.{$season->id}", $currentSeasonGroupIds[$season->id] ?? ''))
                            <label class="field">
                                <span>{{ $season->name }} @if ($season->status !== 'active') <small>(closed)</small> @endif</span>
                                <select name="season_groups[{{ $season->id }}]">
                                    <option value="">Not assigned</option>
                                    @foreach ($season->groups as $group)
                                        <option value="{{ $group->id }}" @selected((string) $selectedSeasonGroupId === (string) $group->id) @disabled($season->status !== 'active' && (string) $selectedSeasonGroupId !== (string) $group->id)>{{ str($group->seed_type)->title() }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endforeach
                    </div>
                @else
                    <div class="detail-note"><p>Create an active season in Seasons and groups before assigning farmers.</p>@if (auth()->user()->canAccessModule('seasons') && auth()->user()->canUseButton('seasons.list'))<a href="{{ route('seasons.index') }}">Manage seasons →</a>@endif</div>
                @endif
            @else
                @php($currentAssignments = $farmer->seasonGroupAssignments->whereNull('ended_at'))
                @if ($currentAssignments->isEmpty())
                    <p>No current season group assignments.</p>
                @else
                    <div class="detail-grid">
                        @foreach ($currentAssignments as $assignment)
                            <div><span class="detail-label">{{ $assignment->seasonGroup->season->name }}</span><strong>{{ str($assignment->seasonGroup->seed_type)->title() }}</strong></div>
                        @endforeach
                    </div>
                    <p>Only Admin users can change seasonal group assignments.</p>
                @endif
            @endif
        </div>
    </section>

    <div class="form-actions">
        @if ($isRegistrationWizard)
            @if ($isRegistrationDialog)
                <button class="button button-secondary" type="button" data-close-dialog>Cancel</button>
            @elseif (auth()->user()->canUseButton('farmers.list'))
                <a class="button button-secondary" href="{{ route('farmers.index') }}">Cancel</a>
            @endif
            <button class="button button-primary" type="button" data-registration-next>Next</button>
            <button class="button button-secondary" type="button" data-registration-back hidden>Back</button>
            @if (auth()->user()->canUseButton('farmers.create'))
                <button class="button button-primary" type="submit" data-registration-submit hidden>Create farmer profile</button>
            @endif
        @else
            @if (($farmer->exists && auth()->user()->canUseButton('farmers.view')) || (! $farmer->exists && auth()->user()->canUseButton('farmers.list')))
                <a class="button button-secondary" href="{{ $farmer->exists ? route('farmers.show', $farmer) : route('farmers.index') }}">Cancel</a>
            @endif
            @if (auth()->user()->canUseButton($farmer->exists ? 'farmers.edit' : 'farmers.create'))
                <button class="button button-primary" type="submit">{{ $farmer->exists ? 'Save changes' : 'Create farmer profile' }}</button>
            @endif
        @endif
    </div>
</form>
