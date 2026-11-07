@extends('layouts.app')

@section('title', $supply->exists ? 'Edit supply' : 'Add supply')
@section('section', 'Inventory')

@section('content')
    <div class="page-heading">
        @if (auth()->user()->canUseButton('inventory.list'))<a class="back-link" href="{{ route('supplies.index') }}">← Back to inventory</a>@endif
        <span class="eyebrow">SUPPLY MANAGEMENT</span>
        <h1>{{ $supply->exists ? 'Edit supply item' : 'Add a supply item' }}</h1>
        <p>Define how the item is identified and monitored in inventory.</p>
    </div>
    <form method="post" action="{{ $supply->exists ? route('supplies.update', $supply) : route('supplies.store') }}" class="form-layout">
        @csrf
        @if ($supply->exists) @method('put') @endif
        <section class="panel form-panel">
            <div class="form-grid">
                <label class="field"><span>Supply code <b>*</b></span><input name="supply_code" value="{{ old('supply_code', $supply->supply_code) }}" maxlength="50" required placeholder="e.g. SEED-001"></label>
                <label class="field"><span>Supply name <b>*</b></span><input name="supply_name" value="{{ old('supply_name', $supply->supply_name) }}" maxlength="150" required placeholder="e.g. Rice seed"></label>
                @php($selectedCategory = old('category', $supply->category))
                @php($unitRequired = ! in_array($selectedCategory, $unitOptionalCategories, true))
                <label class="field"><span>Category <b>*</b></span><select name="category" data-supply-category data-unit-optional-categories="{{ implode('|', $unitOptionalCategories) }}" required><option value="">Select a category</option>@foreach ($categories as $category)<option value="{{ $category }}" @selected($selectedCategory === $category)>{{ $category }}</option>@endforeach</select></label>
                <label class="field"><span>Unit of measurement <b data-unit-required @if (! $unitRequired) hidden @endif>*</b></span><input name="unit" data-supply-unit value="{{ old('unit', $supply->unit) }}" maxlength="50" @required($unitRequired) placeholder="bag, kg, pack"><small>Not required for Cash, Farm Tools, or Other Agricultural Supplies.</small></label>
                <label class="field"><span>Minimum stock threshold <b>*</b></span><input type="number" min="0" step="0.01" name="minimum_stock" value="{{ old('minimum_stock', $supply->minimum_stock ?? '0') }}" required></label>
                <label class="field"><span>Item status</span><select name="status"><option value="active" @selected(old('status', $supply->status ?: 'active') === 'active')>Active</option><option value="inactive" @selected(old('status', $supply->status) === 'inactive')>Inactive</option></select></label>
                <label class="field field-wide"><span>Description</span><textarea name="description" rows="4" maxlength="5000">{{ old('description', $supply->description) }}</textarea></label>
            </div>
        </section>
        <div class="form-actions">@if (auth()->user()->canUseButton('inventory.list'))<a class="button button-secondary" href="{{ route('supplies.index') }}">Cancel</a>@endif @if (auth()->user()->canUseButton($supply->exists ? 'inventory.edit' : 'inventory.create'))<button class="button button-primary" type="submit">{{ $supply->exists ? 'Save changes' : 'Create supply' }}</button>@endif</div>
    </form>
@endsection
