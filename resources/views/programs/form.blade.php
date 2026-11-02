@extends('layouts.app')

@section('title', $program->exists ? 'Edit program' : 'New program')
@section('section', 'Programs')

@section('content')
    <div class="page-heading">
        <a class="back-link" href="{{ route('programs.index') }}">← Back to programs</a>
        <span class="eyebrow">ASSISTANCE MANAGEMENT</span>
        <h1>{{ $program->exists ? 'Edit subsidy program' : 'Create a program' }}</h1>
        <p>Set the distribution period and choose which supplies are included.</p>
    </div>
    <form method="post" action="{{ $program->exists ? route('programs.update', $program) : route('programs.store') }}" class="form-layout">
        @csrf
        @if ($program->exists) @method('put') @endif
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-number">01</span><div><h2>Program details</h2><p>Dates are inclusive; an active program must be within its distribution period.</p></div></div>
            <div class="form-grid">
                <label class="field"><span>Program code <b>*</b></span><input name="program_code" value="{{ old('program_code', $program->program_code) }}" maxlength="50" pattern="[A-Za-z0-9_-]+" required placeholder="e.g. SUB-2026-01"></label>
                <label class="field"><span>Program name <b>*</b></span><input name="program_name" value="{{ old('program_name', $program->program_name) }}" maxlength="150" required></label>
                <label class="field"><span>Start date</span><input type="date" name="start_date" value="{{ old('start_date', $program->start_date?->format('Y-m-d')) }}"></label>
                <label class="field"><span>End date</span><input type="date" name="end_date" value="{{ old('end_date', $program->end_date?->format('Y-m-d')) }}"></label>
                <label class="field"><span>Program status</span><select name="status">@foreach (['draft', 'active', 'paused', 'completed', 'archived'] as $value)<option value="{{ $value }}" @selected(old('status', $program->status ?: 'draft') === $value)>{{ str($value)->title() }}</option>@endforeach</select></label>
                <label class="field field-wide"><span>Description</span><textarea name="description" rows="3" maxlength="5000">{{ old('description', $program->description) }}</textarea></label>
                <label class="field field-wide"><span>Eligibility rules</span><textarea name="eligibility_rules" rows="3" maxlength="5000" placeholder="For example: active farmer with a registered and verified RSBSA record.">{{ old('eligibility_rules', $program->eligibility_rules) }}</textarea></label>
            </div>
        </section>

        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-number">02</span><div><h2>Program supplies</h2><p>Set an optional per-farmer maximum for each item.</p></div></div>
            @if ($supplies->isEmpty())
                <div class="inline-notice">Create at least one active inventory item before setting up program supplies. <a href="{{ route('supplies.create') }}">Add a supply →</a></div>
            @else
                <div class="repeatable-list" data-repeatable-list>
                    @php($existingItems = old('items', $program->supplies->map(fn ($supply) => ['supply_id' => $supply->id, 'maximum_quantity' => $supply->pivot->maximum_quantity])->all() ?: [['supply_id' => '', 'maximum_quantity' => '']]))
                    @foreach ($existingItems as $index => $item)
                        <div class="repeatable-row">
                            <label class="field"><span>Supply item</span><select name="items[{{ $index }}][supply_id]" required><option value="">Select a supply</option>@foreach ($supplies as $supply)<option value="{{ $supply->id }}" @selected((string) ($item['supply_id'] ?? '') === (string) $supply->id)>{{ $supply->supply_name }} · {{ $supply->supply_code }}</option>@endforeach</select></label>
                            <label class="field"><span>Maximum quantity</span><input type="number" name="items[{{ $index }}][maximum_quantity]" min="0.01" step="0.01" value="{{ $item['maximum_quantity'] ?? '' }}" placeholder="No limit"></label>
                            <button class="icon-button row-remove" type="button" aria-label="Remove item" data-remove-row>×</button>
                        </div>
                    @endforeach
                </div>
                <template id="program-item-template">
                    <div class="repeatable-row">
                        <label class="field"><span>Supply item</span><select data-name="items[__INDEX__][supply_id]" required><option value="">Select a supply</option>@foreach ($supplies as $supply)<option value="{{ $supply->id }}">{{ $supply->supply_name }} · {{ $supply->supply_code }}</option>@endforeach</select></label>
                        <label class="field"><span>Maximum quantity</span><input type="number" data-name="items[__INDEX__][maximum_quantity]" min="0.01" step="0.01" placeholder="No limit"></label>
                        <button class="icon-button row-remove" type="button" aria-label="Remove item" data-remove-row>×</button>
                    </div>
                </template>
                <button class="button button-secondary add-row-button" type="button" data-add-row="#program-item-template">＋ Add another supply</button>
            @endif
        </section>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('programs.index') }}">Cancel</a><button class="button button-primary" type="submit" @disabled($supplies->isEmpty())>{{ $program->exists ? 'Save program' : 'Create program' }}</button></div>
    </form>
@endsection
