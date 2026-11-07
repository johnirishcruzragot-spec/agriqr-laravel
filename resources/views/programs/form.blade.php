@extends('layouts.app')

@section('title', $program->exists ? 'Edit program' : 'New program')
@section('section', 'Programs')

@section('content')
    @php
        $selectedSeasonId = old('season_id', $program->season_id ?? $program->seasonGroups->first()?->season_id);
        $selectedGroupIds = collect(old('season_group_ids', $program->seasonGroups->pluck('id')->all()))
            ->map(fn ($id) => (string) $id)
            ->all();
        $existingItems = old('items', $program->supplies->map(fn ($supply) => [
            'supply_id' => $supply->id,
            'unit_value' => $supply->pivot->unit_value,
        ])->all() ?: [['supply_id' => '', 'unit_value' => '']]);
        $existingRates = old('rates', $program->supplyGroupRates->map(fn ($rate) => [
            'supply_id' => $rate->supply_id,
            'season_group_id' => $rate->season_group_id,
            'quantity_per_hectare' => $rate->quantity_per_hectare,
            'maximum_quantity_per_farmer' => $rate->maximum_quantity_per_farmer,
        ])->all());
        $ratesByCombination = collect($existingRates)->keyBy(
            fn (array $rate): string => ($rate['supply_id'] ?? '').':'.($rate['season_group_id'] ?? ''),
        );
    @endphp
    <div class="page-heading">
        @if (auth()->user()->canUseButton('programs.list'))<a class="back-link" href="{{ route('programs.index') }}">← Back to programs</a>@endif
        <span class="eyebrow">ASSISTANCE MANAGEMENT</span>
        <h1>{{ $program->exists ? 'Edit subsidy program' : 'Create a program' }}</h1>
        <p>Configure a season, eligible farmer groups, supply rates per hectare, and per-farmer quantity limits.</p>
    </div>
    <form method="post" action="{{ $program->exists ? route('programs.update', $program) : route('programs.store') }}" class="form-layout" data-program-form>
        @csrf
        @if ($program->exists) @method('put') @endif
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-number">01</span><div><h2>Program details</h2><p>Active programs are available only within their configured dates.</p></div></div>
            <div class="form-grid">
                <label class="field"><span>Program code <b>*</b></span><input name="program_code" value="{{ old('program_code', $program->program_code) }}" maxlength="50" pattern="[A-Za-z0-9_-]+" required placeholder="e.g. SUB-2026-01"></label>
                <label class="field"><span>Program name <b>*</b></span><input name="program_name" value="{{ old('program_name', $program->program_name) }}" maxlength="150" required></label>
                <label class="field"><span>Season <b>*</b></span><select name="season_id" data-program-season-select required><option value="">Select a season</option>@foreach ($seasons as $season)<option value="{{ $season->id }}" @selected((string) $selectedSeasonId === (string) $season->id)>{{ $season->name }}@if ($season->status !== 'active') (Closed season)@endif</option>@endforeach</select></label>
                <label class="field"><span>Subsidy type <b>*</b></span><select name="subsidy_type" required>
                    @foreach (['in_kind' => 'In-kind (supplies)', 'hybrid' => 'Hybrid', 'embedded' => 'Embedded'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('subsidy_type', $program->subsidy_type ?: 'in_kind') === $value)>{{ $label }}</option>
                    @endforeach
                </select><small>Hybrid subsidy combines assistance such as cash and agricultural supplies in one program. Embedded subsidy provides a discounted product price.</small></label>
                <label class="field"><span>Start date <b>*</b></span><input type="date" name="start_date" value="{{ old('start_date', $program->start_date?->format('Y-m-d')) }}" required></label>
                <label class="field"><span>End date <b>*</b></span><input type="date" name="end_date" value="{{ old('end_date', $program->end_date?->format('Y-m-d')) }}" required></label>
                <label class="field"><span>Program status <b>*</b></span><select name="status" required>@foreach (['draft', 'active', 'paused', 'completed', 'archived'] as $value)<option value="{{ $value }}" @selected(old('status', $program->status ?: 'draft') === $value)>{{ str($value)->title() }}</option>@endforeach</select></label>
                <label class="field field-wide"><span><input type="hidden" name="requires_verified_rsbsa" value="0"><input type="checkbox" name="requires_verified_rsbsa" value="1" @checked(old('requires_verified_rsbsa', $program->exists ? $program->requires_verified_rsbsa : true))> Require registered and verified RSBSA</span><small>The system-wide RSBSA requirement also applies when enabled.</small></label>
                <label class="field field-wide"><span>Description</span><textarea name="description" rows="3" maxlength="5000">{{ old('description', $program->description) }}</textarea></label>
                <label class="field field-wide"><span>Eligibility rules</span><textarea name="eligibility_rules" rows="3" maxlength="5000" placeholder="For example: active farmer with a registered and verified RSBSA record.">{{ old('eligibility_rules', $program->eligibility_rules) }}</textarea></label>
            </div>
        </section>

        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-number">02</span><div><h2>Eligible farmer groups</h2><p>Select which groups in this season can receive each included supply.</p></div></div>
            @if ($seasonGroups->isEmpty())
                <div class="inline-notice">Create an active season and its farmer groups before configuring program eligibility.</div>
            @else
                <div class="form-grid" data-program-season-groups>
                    @foreach ($seasonGroups as $seasonGroup)
                        <label class="field" data-season-group-option data-season-id="{{ $seasonGroup->season_id }}" @if ((string) $selectedSeasonId !== (string) $seasonGroup->season_id) hidden @endif>
                            <span><input type="checkbox" name="season_group_ids[]" value="{{ $seasonGroup->id }}" @checked(in_array((string) $seasonGroup->id, $selectedGroupIds, true)) @disabled((string) $selectedSeasonId !== (string) $seasonGroup->season_id)> {{ $seasonGroup->season->name }} · {{ str($seasonGroup->seed_type)->title() }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-number">03</span><div><h2>Included supplies</h2><p>Add every supply that can be issued. Unit values are optional legacy receipt reporting; quantity eligibility is configured below.</p></div></div>
            @if ($supplies->isEmpty())
                <div class="inline-notice">Create at least one active inventory item before setting up program supplies.
                    @if (auth()->user()->canAccessModule('inventory') && auth()->user()->canUseButton('inventory.create'))
                        <a href="{{ route('supplies.create') }}">Add a supply →</a>
                    @else
                        Ask a user with Inventory access to add a supply.
                    @endif
                </div>
            @else
                <div class="repeatable-list" data-repeatable-list data-program-items>
                    @foreach ($existingItems as $index => $item)
                        <div class="repeatable-row">
                            <label class="field"><span>Supply item <b>*</b></span><select name="items[{{ $index }}][supply_id]" required><option value="">Select a supply</option>@foreach ($supplies as $supply)<option value="{{ $supply->id }}" @selected((string) ($item['supply_id'] ?? '') === (string) $supply->id)>{{ $supply->supply_name }} · {{ $supply->supply_code }}</option>@endforeach</select></label>
                            <label class="field"><span>PHP value per unit (optional)</span><input type="number" name="items[{{ $index }}][unit_value]" min="0" step="0.01" value="{{ $item['unit_value'] ?? '' }}"></label>
                            <button class="icon-button row-remove" type="button" aria-label="Remove item" data-remove-row>×</button>
                        </div>
                    @endforeach
                </div>
                <template id="program-item-template">
                    <div class="repeatable-row">
                        <label class="field"><span>Supply item <b>*</b></span><select data-name="items[__INDEX__][supply_id]" required><option value="">Select a supply</option>@foreach ($supplies as $supply)<option value="{{ $supply->id }}">{{ $supply->supply_name }} · {{ $supply->supply_code }}</option>@endforeach</select></label>
                        <label class="field"><span>PHP value per unit (optional)</span><input type="number" data-name="items[__INDEX__][unit_value]" min="0" step="0.01"></label>
                        <button class="icon-button row-remove" type="button" aria-label="Remove item" data-remove-row>×</button>
                    </div>
                </template>
                <button class="button button-secondary add-row-button" type="button" data-add-row="#program-item-template">＋ Add another supply</button>
            @endif
        </section>

        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-number">04</span><div><h2>Supply rates and farmer maximums</h2><p>A separate row is generated for every included supply and eligible group. Set the quantity-per-hectare rate and maximum total quantity for each row. These are configurable examples, not official MAO rates.</p></div></div>
            @if ($supplies->isEmpty() || $seasonGroups->isEmpty())
                <div class="inline-notice">Select a supply, season, and eligible farmer group before entering group-specific rates.</div>
            @else
                <div class="repeatable-list" data-program-rates-list>
                    @php
                        $rateIndex = 0;
                    @endphp
                    @foreach ($existingItems as $item)
                        @php
                            $rateSupply = $supplies->firstWhere('id', (int) ($item['supply_id'] ?? 0));
                        @endphp
                        @if ($rateSupply)
                            @foreach ($selectedGroupIds as $groupId)
                                @php
                                    $rateGroup = $seasonGroups->firstWhere('id', (int) $groupId);
                                    $rate = $ratesByCombination->get($rateSupply->id.':'.$groupId, []);
                                @endphp
                                @if ($rateGroup)
                                    @php
                                        $currentRateIndex = $rateIndex++;
                                    @endphp
                                    <div class="repeatable-row program-rate-row" data-program-rate-row data-supply-id="{{ $rateSupply->id }}" data-group-id="{{ $rateGroup->id }}" data-rate-quantity="{{ $rate['quantity_per_hectare'] ?? '' }}" data-rate-maximum="{{ $rate['maximum_quantity_per_farmer'] ?? '' }}">
                                        <div class="program-rate-combination"><strong>{{ $rateSupply->supply_name }}</strong><span>{{ $rateGroup->season->name }} · {{ str($rateGroup->seed_type)->title() }}</span></div>
                                        <input type="hidden" name="rates[{{ $currentRateIndex }}][supply_id]" value="{{ $rateSupply->id }}">
                                        <input type="hidden" name="rates[{{ $currentRateIndex }}][season_group_id]" value="{{ $rateGroup->id }}">
                                        <label class="field"><span>Quantity per hectare <b>*</b></span><input type="number" name="rates[{{ $currentRateIndex }}][quantity_per_hectare]" data-rate-quantity min="0.0001" step="0.0001" value="{{ $rate['quantity_per_hectare'] ?? '' }}" required></label>
                                        <label class="field"><span>Maximum per farmer <b>*</b></span><input type="number" name="rates[{{ $currentRateIndex }}][maximum_quantity_per_farmer]" data-rate-maximum min="0.01" step="0.01" value="{{ $rate['maximum_quantity_per_farmer'] ?? '' }}" required></label>
                                    </div>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                </div>
                <p class="form-note" data-program-rates-empty @if ($rateIndex > 0) hidden @endif>Select at least one included supply and eligible farmer group to generate their rate rows.</p>
                <template id="program-rate-row-template">
                    <div class="repeatable-row program-rate-row" data-program-rate-row>
                        <div class="program-rate-combination"><strong data-rate-supply-label></strong><span data-rate-group-label></span></div>
                        <input type="hidden" data-name="rates[__INDEX__][supply_id]">
                        <input type="hidden" data-name="rates[__INDEX__][season_group_id]">
                        <label class="field"><span>Quantity per hectare <b>*</b></span><input type="number" data-name="rates[__INDEX__][quantity_per_hectare]" data-rate-quantity min="0.0001" step="0.0001" required></label>
                        <label class="field"><span>Maximum per farmer <b>*</b></span><input type="number" data-name="rates[__INDEX__][maximum_quantity_per_farmer]" data-rate-maximum min="0.01" step="0.01" required></label>
                    </div>
                </template>
            @endif
        </section>
        <div class="form-actions">@if (auth()->user()->canUseButton('programs.list'))<a class="button button-secondary" href="{{ route('programs.index') }}">Cancel</a>@endif @if (auth()->user()->canUseButton($program->exists ? 'programs.edit' : 'programs.create'))<button class="button button-primary" type="submit" @disabled($supplies->isEmpty() || $seasonGroups->isEmpty() || $seasons->isEmpty())>{{ $program->exists ? 'Save program' : 'Create program' }}</button>@endif</div>
    </form>
@endsection
