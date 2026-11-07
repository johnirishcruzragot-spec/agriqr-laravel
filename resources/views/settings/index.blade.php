@extends('layouts.app')

@section('title', 'System configuration')
@section('section', 'System configuration')

@section('content')
    <div class="page-heading">
        <span class="eyebrow">SUPER ADMINISTRATION</span>
        <h1>System configuration</h1>
        <p>Manage approved supply categories, optional farmer fields and distribution checks.</p>
    </div>

    <form method="post" action="{{ route('settings.update') }}" class="form-layout" data-sensitive-settings-form>
        @csrf
        @method('put')

        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-number">01</span><div><h2>Supply categories</h2><p>Enter one approved category per line. Categories assigned to existing supplies cannot be removed.</p></div></div>
            <label class="field"><span>Approved categories <b>*</b></span><textarea name="supply_categories" rows="8" required>{{ old('supply_categories', implode("\n", $settings['supply_categories'])) }}</textarea></label>
        </section>

        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-number">02</span><div><h2>Farmer information fields</h2><p>Add optional approved fields to the Personal information section of farmer profiles.</p></div></div>
            @php($farmerFields = old('farmer_fields', $settings['farmer_fields']))
            <div class="repeatable-list" data-settings-fields>
                @foreach ($farmerFields as $index => $field)
                    @include('settings._farmer-field', ['field' => $field, 'index' => $index])
                @endforeach
            </div>
            @if (auth()->user()->canUseButton('settings.fields'))<button class="button button-secondary add-row-button" type="button" data-add-settings-field>Add custom field</button>@endif
        </section>

        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-number">03</span><div><h2>Farmer locations and seasons</h2><p>Configure farmer barangay choices here. Manage named seasons, Hybrid/Inbred groups and assignment history in the dedicated workspace.</p></div></div>
            <div class="form-grid">
                <label class="field"><span>Barangays <b>*</b></span><textarea name="barangays" rows="6" required>{{ old('barangays', implode("\n", $settings['barangays'])) }}</textarea><small>Enter one barangay per line. New farmer profiles can select only these values.</small></label>
                <div class="detail-note"><span class="detail-label">SEASONS AND GROUPS</span><p>Seasons have dedicated status, program eligibility, group rates and farmer assignment history.</p>@if (auth()->user()->canUseButton('seasons.list'))<a class="button button-secondary" href="{{ route('seasons.index') }}">Manage seasons and groups →</a>@endif</div>
            </div>
        </section>

        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-number">04</span><div><h2>Distribution and verification</h2><p>These controls are applied when recording a new distribution. Existing completed records remain unchanged.</p></div></div>
            <div class="form-grid">
                <label class="check-line"><input type="hidden" name="require_active_farmer" value="0"><input type="checkbox" name="require_active_farmer" value="1" @checked((bool) old('require_active_farmer', $settings['distribution_settings']['require_active_farmer']))> Require an active farmer</label>
                <label class="check-line"><input type="hidden" name="require_verified_rsbsa" value="0"><input type="checkbox" name="require_verified_rsbsa" value="1" @checked((bool) old('require_verified_rsbsa', $settings['distribution_settings']['require_verified_rsbsa']))> Require registered and verified RSBSA</label>
                <label class="check-line"><input type="hidden" name="require_active_program" value="0"><input type="checkbox" name="require_active_program" value="1" @checked((bool) old('require_active_program', $settings['distribution_settings']['require_active_program']))> Require an active, in-date program</label>
                <label class="field"><span>Maximum quantity per supply (optional)</span><input type="number" name="max_distribution_quantity" min="0.01" step="0.01" value="{{ old('max_distribution_quantity', $settings['distribution_settings']['max_distribution_quantity']) }}"><small>Leave blank to use only each program’s supply limit.</small></label>
            </div>
        </section>

        <div class="form-actions">@if (auth()->user()->canUseButton('dashboard'))<a class="button button-secondary" href="{{ route('dashboard') }}">Cancel</a>@endif @if (auth()->user()->canUseButton('settings.update'))<button class="button button-primary" type="submit">Save system configuration</button>@endif</div>
        <template data-settings-field-template>
            <div class="repeatable-row settings-field-row" data-settings-field>
                <label class="field"><span>Field key</span><input data-name="farmer_fields[__INDEX__][key]" pattern="[a-z][a-z0-9_]{0,39}" placeholder="e.g. preferred_crop" required @disabled(! auth()->user()->canUseButton('settings.fields'))></label>
                <label class="field"><span>Display label</span><input data-name="farmer_fields[__INDEX__][label]" maxlength="80" required @disabled(! auth()->user()->canUseButton('settings.fields'))></label>
                <label class="field"><span>Field type</span><select data-name="farmer_fields[__INDEX__][type]" data-settings-field-type @disabled(! auth()->user()->canUseButton('settings.fields'))><option value="text">Text</option><option value="number">Number</option><option value="date">Date</option><option value="select">Dropdown</option></select></label>
                <label class="field settings-field-options" data-settings-field-options><span>Dropdown options (one per line)</span><textarea data-name="farmer_fields[__INDEX__][options]" rows="2" @disabled(! auth()->user()->canUseButton('settings.fields'))></textarea></label>
                <label class="check-line settings-field-required"><input type="hidden" data-name="farmer_fields[__INDEX__][required]" value="0" @disabled(! auth()->user()->canUseButton('settings.fields'))><input type="checkbox" data-name="farmer_fields[__INDEX__][required]" value="1" @disabled(! auth()->user()->canUseButton('settings.fields'))> Required</label>
                @if (auth()->user()->canUseButton('settings.fields'))<button class="icon-button" type="button" data-remove-settings-field aria-label="Remove custom field">×</button>@endif
            </div>
        </template>
    </form>
@endsection
