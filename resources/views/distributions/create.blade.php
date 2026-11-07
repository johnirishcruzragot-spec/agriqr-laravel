@extends('layouts.app')

@section('title', 'New distribution')
@section('section', 'Distributions')

@section('content')
    <div class="page-heading">
        @if (auth()->user()->canUseButton('distributions.list'))<a class="back-link" href="{{ route('distributions.index') }}">← Distribution history</a>@endif
        <span class="eyebrow">ASSISTANCE DELIVERY</span>
        <h1>New distribution</h1>
        <p>Identify the farmer, confirm eligibility and record the supplies issued.</p>
    </div>

    <section class="panel scan-panel">
        <div class="form-section-heading"><span class="section-number">01</span><div><h2>Identify farmer</h2><p>Scan the farmer's ID or enter the AgriQR code printed on their card.</p></div></div>
        <form method="post" action="{{ route('distributions.lookup') }}" class="lookup-row" data-lookup-form>
            @csrf
            <label class="field lookup-input"><span>Farmer ID / QR code</span><input name="farmer_code" value="{{ old('farmer_code', $farmer?->farmer_id) }}" placeholder="e.g. F3C0D92B20B" autocomplete="off" required></label>
            @if (auth()->user()->canUseButton('distributions.scan'))<button class="button button-secondary scan-button" type="button" data-qr-scan>Scan QR code</button>@endif
            <button class="button button-primary" type="submit">Find farmer</button>
        </form>
        <div class="scanner-wrap" data-scanner-wrap hidden><video class="scanner-video" data-scanner-video muted playsinline></video><p class="scanner-message" data-scanner-message>Point the camera at the farmer's AgriQR code.</p><button class="text-link" type="button" data-qr-stop>Stop camera</button></div>

        @if ($farmer)
            @php($isDeceased = $farmer->status === 'deceased' || $farmer->deceased_at !== null)
            @php($isActive = $farmer->status === 'active')
            @php($rsbsaVerified = $farmer->rsbsaRecord?->registration_status === 'registered' && $farmer->rsbsaRecord?->verification_status === 'verified')
            @php($canProcessFarmer = ! $isDeceased && (! $requireActiveFarmer || $isActive) && $hasFarmerPhoto && $farmerHasFarmSize)
            <div class="farmer-confirmation {{ $canProcessFarmer ? 'confirmation-success' : 'confirmation-warning' }}">
                <span class="confirmation-icon">{{ $canProcessFarmer ? '✓' : '!' }}</span>
                <div><strong>{{ $farmer->full_name }} <small>{{ $farmer->farmer_id }}</small></strong><p>{{ $farmer->barangay }}, {{ $farmer->municipality }} · RSBSA {{ $farmer->rsbsaRecord?->verification_status ?? 'not registered' }}</p>
                    @if ($isDeceased)<p class="eligibility-warning">Distribution is blocked because this farmer is marked deceased.</p>
                    @elseif ($requireActiveFarmer && ! $isActive)<p class="eligibility-warning">This profile must be active before assistance can be issued.</p>
                    @elseif (! $hasFarmerPhoto)<p class="eligibility-warning">Add a current farmer photo before face verification and distribution.</p>
                    @elseif (! $farmerHasFarmSize)<p class="eligibility-warning">A valid farm size is required before the allowed quantity can be calculated.</p>@endif
                </div>
                <span class="status {{ $canProcessFarmer ? 'status-green' : 'status-amber' }}">{{ $canProcessFarmer ? 'Review required' : 'Not eligible' }}</span>
            </div>
            @if ($hasFarmerPhoto)
                <div class="farmer-photo-verification">
                    <img src="{{ route('distributions.farmer-photo', $farmer) }}" alt="Current photo of {{ $farmer->full_name }}" class="farmer-verification-photo">
                    <p>Compare the person present with this stored farmer photo. The system records the staff member and verification time; it does not use automated facial recognition.</p>
                </div>
            @endif
        @endif
    </section>

    @if ($farmer && $programs->isEmpty())
        <div class="inline-notice section-gap">No active program has complete supply rates for this farmer’s assigned season group. Configure the program’s supply/group rates and per-farmer maximums first.</div>
    @endif

    @if ($farmer && ! $programs->isEmpty())
        @php($isDeceased = $farmer->status === 'deceased' || $farmer->deceased_at !== null)
        @php($isActive = $farmer->status === 'active')
        @php($rsbsaVerified = $farmer->rsbsaRecord?->registration_status === 'registered' && $farmer->rsbsaRecord?->verification_status === 'verified')
        @php($canProcessFarmer = ! $isDeceased && (! $requireActiveFarmer || $isActive) && $hasFarmerPhoto && $farmerHasFarmSize)
        <form method="post" action="{{ route('distributions.store') }}" class="form-layout section-gap" data-distribution-form data-requires-active="{{ $requireActiveFarmer ? 'true' : 'false' }}" data-requires-verified-rsbsa="{{ $requireVerifiedRsbsa ? 'true' : 'false' }}" data-farmer-active="{{ $isActive ? 'true' : 'false' }}" data-rsbsa-verified="{{ $rsbsaVerified ? 'true' : 'false' }}" data-farmer-deceased="{{ $isDeceased ? 'true' : 'false' }}" data-has-photo="{{ $hasFarmerPhoto ? 'true' : 'false' }}" data-farm-size-valid="{{ $farmerHasFarmSize ? 'true' : 'false' }}">
            @csrf
            <input type="hidden" name="farmer_code" value="{{ $farmer?->farmer_id }}">
            <div class="inline-notice" data-distribution-rsbsa-note hidden></div>
            @if ($hasFarmerPhoto)
                <label class="field face-verification-confirmation">
                    <span><input type="checkbox" name="face_verified" value="1" required> I visually confirmed that the person receiving assistance matches the farmer photo above. <b>*</b></span>
                    <small>Do not continue if the identity does not match.</small>
                </label>
            @endif
            <section class="panel form-panel">
                <div class="form-section-heading"><span class="section-number">02</span><div><h2>Select a program</h2><p>Eligible quantities use each supply/group rate and the farmer’s per-supply maximum.</p></div></div>
                <label class="field field-max"><span>Subsidy program <b>*</b></span><select name="subsidy_program_id" data-program-select required><option value="">Select a program</option>@foreach ($programs as $program)<option value="{{ $program->id }}" data-requires-rsbsa="{{ $program->requires_verified_rsbsa ? 'true' : 'false' }}" @selected((string) old('subsidy_program_id') === (string) $program->id)>{{ $program->program_name }} · {{ $program->program_code }} · {{ str($program->subsidy_type)->replace('_', ' ')->title() }}</option>@endforeach</select></label>
                <label class="field field-max section-gap"><span>Eligible season and group <b>*</b></span><select name="farmer_season_group_assignment_id" data-season-group-select required><option value="">Select a program first</option>@foreach ($distributionEligibilityOptions as $option)<option value="{{ $option['assignment_id'] }}" data-program-id="{{ $option['program_id'] }}" data-hectares="{{ $option['eligible_hectares'] ?? '' }}" @selected((string) old('farmer_season_group_assignment_id') === (string) $option['assignment_id'])>{{ $option['label'] }}</option>@endforeach</select><small data-benefit-cap-note>Select a program and season group to view supply quantity limits.</small></label>
            </section>

            <section class="panel form-panel">
                <div class="form-section-heading"><span class="section-number">03</span><div><h2>Supplies to distribute</h2><p>Calculated quantity, prior seasonal issues, and available stock are checked again before saving.</p></div></div>
                <div class="repeatable-list" data-repeatable-list data-distribution-items>
                    <div class="repeatable-row">
                        <label class="field"><span>Supply item</span><select name="items[0][supply_id]" data-distribution-supply required><option value="">Select a program and group first</option>@foreach ($programs as $program)@foreach ($distributionSupplyOptions->where('program_id', $program->id) as $option)@php($supply = $program->supplies->firstWhere('id', $option['supply_id']))<option value="{{ $supply->id }}" data-program-id="{{ $program->id }}" data-assignment-id="{{ $option['assignment_id'] }}" data-available="{{ $option['available_quantity'] }}" data-allowed="{{ $option['allowed_quantity'] }}" data-rate="{{ $option['rate_per_hectare'] }}" data-calculated="{{ $option['calculated_quantity'] }}" data-maximum="{{ $option['maximum_quantity'] }}" data-cap="{{ $option['quantity_cap'] }}" data-shortage="{{ $option['shortage'] }}" data-already-issued="{{ $option['already_issued'] ? 'true' : 'false' }}" @disabled($option['already_issued'])>{{ $supply->supply_name }} · {{ number_format($option['rate_per_hectare'], 4) }}{{ $supply->unit ? ' '.$supply->unit : '' }}/ha · {{ number_format($option['calculated_quantity'], 2) }} calculated · {{ number_format($option['maximum_quantity'], 2) }} max{{ $supply->unit ? ' '.$supply->unit : '' }} · {{ number_format($option['allowed_quantity'], 2) }} allowed{{ $option['already_issued'] ? ' · already issued this season' : '' }}</option>@endforeach @endforeach</select></label>
                        <label class="field"><span>Quantity</span><input type="number" name="items[0][quantity]" min="0.01" step="0.01" required placeholder="0.00"><small class="distribution-limit" data-distribution-limit></small></label>
                        <label class="field distribution-reduction-reason" hidden><span>Reason for reducing quantity <b>*</b></span><textarea name="items[0][reduction_reason]" rows="2" maxlength="500" data-reduction-reason></textarea></label>
                        @if (auth()->user()->canUseButton('distributions.items'))<button class="icon-button row-remove" type="button" aria-label="Remove item" data-remove-row>×</button>@endif
                    </div>
                </div>
                <template id="distribution-item-template">
                    <div class="repeatable-row">
                        <label class="field"><span>Supply item</span><select data-name="items[__INDEX__][supply_id]" data-distribution-supply required><option value="">Select a program and group first</option>@foreach ($programs as $program)@foreach ($distributionSupplyOptions->where('program_id', $program->id) as $option)@php($supply = $program->supplies->firstWhere('id', $option['supply_id']))<option value="{{ $supply->id }}" data-program-id="{{ $program->id }}" data-assignment-id="{{ $option['assignment_id'] }}" data-available="{{ $option['available_quantity'] }}" data-allowed="{{ $option['allowed_quantity'] }}" data-rate="{{ $option['rate_per_hectare'] }}" data-calculated="{{ $option['calculated_quantity'] }}" data-maximum="{{ $option['maximum_quantity'] }}" data-cap="{{ $option['quantity_cap'] }}" data-shortage="{{ $option['shortage'] }}" data-already-issued="{{ $option['already_issued'] ? 'true' : 'false' }}" @disabled($option['already_issued'])>{{ $supply->supply_name }} · {{ number_format($option['rate_per_hectare'], 4) }}{{ $supply->unit ? ' '.$supply->unit : '' }}/ha · {{ number_format($option['calculated_quantity'], 2) }} calculated · {{ number_format($option['maximum_quantity'], 2) }} max{{ $supply->unit ? ' '.$supply->unit : '' }} · {{ number_format($option['allowed_quantity'], 2) }} allowed{{ $option['already_issued'] ? ' · already issued this season' : '' }}</option>@endforeach @endforeach</select></label>
                        <label class="field"><span>Quantity</span><input type="number" data-name="items[__INDEX__][quantity]" min="0.01" step="0.01" required placeholder="0.00"><small class="distribution-limit" data-distribution-limit></small></label>
                        <label class="field distribution-reduction-reason" hidden><span>Reason for reducing quantity <b>*</b></span><textarea data-name="items[__INDEX__][reduction_reason]" rows="2" maxlength="500" data-reduction-reason></textarea></label>
                        @if (auth()->user()->canUseButton('distributions.items'))<button class="icon-button row-remove" type="button" aria-label="Remove item" data-remove-row>×</button>@endif
                    </div>
                </template>
                @if (auth()->user()->canUseButton('distributions.items'))<button class="button button-secondary add-row-button" type="button" data-add-row="#distribution-item-template">＋ Add another item</button>@endif
                <label class="field field-max section-gap"><span>Notes</span><textarea name="remarks" rows="3" maxlength="2000" placeholder="Optional notes for this transaction">{{ old('remarks') }}</textarea></label>
            </section>
            <div class="form-actions">@if (auth()->user()->canUseButton('distributions.list'))<a class="button button-secondary" href="{{ route('distributions.index') }}">Cancel</a>@endif @if (auth()->user()->canUseButton('distributions.create'))<button class="button button-primary" type="submit" data-distribution-submit @disabled(! $canProcessFarmer)>Confirm distribution</button>@endif</div>
            @unless ($canProcessFarmer)<p class="form-footnote">Resolve the eligibility or photo issue above before proceeding.</p>@endunless
        </form>
    @elseif ($farmer && $programs->isEmpty())
        <div class="inline-notice section-gap">No active programs are configured for this farmer’s current season groups. Contact an administrator to review program eligibility.</div>
    @elseif ($programs->isEmpty())
        <div class="inline-notice section-gap">No active programs are currently available. Contact an administrator to activate a program.</div>
    @else
        <div class="inline-notice section-gap">Find an active and RSBSA-verified farmer to continue.</div>
    @endif
@endsection
