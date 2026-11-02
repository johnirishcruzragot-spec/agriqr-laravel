@extends('layouts.app')

@section('title', 'New distribution')
@section('section', 'Distributions')

@section('content')
    <div class="page-heading">
        <a class="back-link" href="{{ route('distributions.index') }}">← Distribution history</a>
        <span class="eyebrow">ASSISTANCE DELIVERY</span>
        <h1>New distribution</h1>
        <p>Identify the farmer, confirm eligibility and record the supplies issued.</p>
    </div>

    <section class="panel scan-panel">
        <div class="form-section-heading"><span class="section-number">01</span><div><h2>Identify farmer</h2><p>Scan the farmer's ID or enter the AgriQR code printed on their card.</p></div></div>
        <form method="post" action="{{ route('distributions.lookup') }}" class="lookup-row" data-lookup-form>
            @csrf
            <label class="field lookup-input"><span>Farmer ID / QR code</span><input name="farmer_code" value="{{ old('farmer_code', $farmer?->farmer_id) }}" placeholder="e.g. F3C0D92B20B" autocomplete="off" required></label>
            <button class="button button-secondary scan-button" type="button" data-qr-scan>Scan QR code</button>
            <button class="button button-primary" type="submit">Find farmer</button>
        </form>
        <div class="scanner-wrap" data-scanner-wrap hidden><video class="scanner-video" data-scanner-video muted playsinline></video><p class="scanner-message" data-scanner-message>Point the camera at the farmer's AgriQR code.</p><button class="text-link" type="button" data-qr-stop>Stop camera</button></div>

        @if ($farmer)
            @php($eligible = $farmer->status === 'active' && $farmer->rsbsaRecord?->registration_status === 'registered' && $farmer->rsbsaRecord?->verification_status === 'verified')
            <div class="farmer-confirmation {{ $eligible ? 'confirmation-success' : 'confirmation-warning' }}">
                <span class="confirmation-icon">{{ $eligible ? '✓' : '!' }}</span>
                <div><strong>{{ $farmer->full_name }} <small>{{ $farmer->farmer_id }}</small></strong><p>{{ $farmer->barangay }}, {{ $farmer->municipality }} · RSBSA {{ $farmer->rsbsaRecord?->verification_status ?? 'not registered' }}</p>
                    @unless ($eligible)<p class="eligibility-warning">This profile must be active and have a registered, verified RSBSA record before assistance can be issued.</p>@endunless
                </div>
                <span class="status {{ $eligible ? 'status-green' : 'status-amber' }}">{{ $eligible ? 'Eligible' : 'Not eligible' }}</span>
            </div>
        @endif
    </section>

    @if ($farmer && ! $programs->isEmpty())
        @php($eligible = $farmer->status === 'active' && $farmer->rsbsaRecord?->registration_status === 'registered' && $farmer->rsbsaRecord?->verification_status === 'verified')
        <form method="post" action="{{ route('distributions.store') }}" class="form-layout section-gap" data-distribution-form>
            @csrf
            <input type="hidden" name="farmer_code" value="{{ $farmer?->farmer_id }}">
            <section class="panel form-panel">
                <div class="form-section-heading"><span class="section-number">02</span><div><h2>Select a program</h2><p>Only active programs within their distribution period are available.</p></div></div>
                <label class="field field-max"><span>Subsidy program <b>*</b></span><select name="subsidy_program_id" data-program-select required><option value="">Select a program</option>@foreach ($programs as $program)<option value="{{ $program->id }}" @selected((string) old('subsidy_program_id') === (string) $program->id)>{{ $program->program_name }} · {{ $program->program_code }}</option>@endforeach</select></label>
            </section>

            <section class="panel form-panel">
                <div class="form-section-heading"><span class="section-number">03</span><div><h2>Supplies to distribute</h2><p>Available stock and program limits are checked again before saving.</p></div></div>
                <div class="repeatable-list" data-repeatable-list data-distribution-items>
                    <div class="repeatable-row">
                        <label class="field"><span>Supply item</span><select name="items[0][supply_id]" data-distribution-supply required><option value="">Select a program first</option>@foreach ($programs as $program)@foreach ($program->supplies as $supply)@php($available = (float) ($supply->inventory?->quantity ?? 0) - (float) ($supply->inventory?->reserved_quantity ?? 0))<option value="{{ $supply->id }}" data-program-id="{{ $program->id }}" data-available="{{ $available }}" data-max="{{ $supply->pivot->maximum_quantity }}">{{ $supply->supply_name }} · {{ $available }} {{ $supply->unit }} available</option>@endforeach @endforeach</select></label>
                        <label class="field"><span>Quantity</span><input type="number" name="items[0][quantity]" min="0.01" step="0.01" required placeholder="0.00"><small class="distribution-limit" data-distribution-limit></small></label>
                        <button class="icon-button row-remove" type="button" aria-label="Remove item" data-remove-row>×</button>
                    </div>
                </div>
                <template id="distribution-item-template">
                    <div class="repeatable-row">
                        <label class="field"><span>Supply item</span><select data-name="items[__INDEX__][supply_id]" data-distribution-supply required><option value="">Select a program first</option>@foreach ($programs as $program)@foreach ($program->supplies as $supply)@php($available = (float) ($supply->inventory?->quantity ?? 0) - (float) ($supply->inventory?->reserved_quantity ?? 0))<option value="{{ $supply->id }}" data-program-id="{{ $program->id }}" data-available="{{ $available }}" data-max="{{ $supply->pivot->maximum_quantity }}">{{ $supply->supply_name }} · {{ $available }} {{ $supply->unit }} available</option>@endforeach @endforeach</select></label>
                        <label class="field"><span>Quantity</span><input type="number" data-name="items[__INDEX__][quantity]" min="0.01" step="0.01" required placeholder="0.00"><small class="distribution-limit" data-distribution-limit></small></label>
                        <button class="icon-button row-remove" type="button" aria-label="Remove item" data-remove-row>×</button>
                    </div>
                </template>
                <button class="button button-secondary add-row-button" type="button" data-add-row="#distribution-item-template">＋ Add another item</button>
                <label class="field field-max section-gap"><span>Notes</span><textarea name="remarks" rows="3" maxlength="2000" placeholder="Optional notes for this transaction">{{ old('remarks') }}</textarea></label>
            </section>
            <div class="form-actions"><a class="button button-secondary" href="{{ route('distributions.index') }}">Cancel</a><button class="button button-primary" type="submit" @disabled(! $eligible)>Confirm distribution</button></div>
            @if (! $eligible)<p class="form-footnote">Confirm an eligible farmer above before proceeding.</p>@endif
        </form>
    @elseif ($programs->isEmpty())
        <div class="inline-notice section-gap">No active programs are currently available. Contact an administrator to activate a program.</div>
    @else
        <div class="inline-notice section-gap">Find an active and RSBSA-verified farmer to continue.</div>
    @endif
@endsection
