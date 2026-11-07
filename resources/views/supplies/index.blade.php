@extends('layouts.app')

@section('title', 'Inventory')
@section('section', 'Inventory')

@section('content')
    <div class="page-heading heading-with-action">
        <div><span class="eyebrow">SUPPLY MANAGEMENT</span><h1>Inventory</h1><p>Monitor stock levels and record incoming agricultural supplies.</p></div>
        <div class="heading-actions">@if (auth()->user()->canUseButton('inventory.import'))<a class="button button-secondary" href="{{ route('supplies.import') }}">Import CSV</a>@endif @if (auth()->user()->canUseButton('inventory.create'))<a class="button button-primary" href="{{ route('supplies.create') }}"><span>＋</span> Add supply</a>@endif</div>
    </div>

    <section class="panel">
        <form class="toolbar" method="get" action="{{ route('supplies.index') }}">
            <label class="search-field"><span aria-hidden="true">⌕</span><input type="search" name="search" value="{{ $search }}" placeholder="Search supply, code or category"></label>
            @if (auth()->user()->canUseButton('inventory.search'))
                <button class="button button-secondary" type="submit">Search</button>
                @if ($search)<a class="text-link" href="{{ route('supplies.index') }}">Clear</a>@endif
            @endif
            <span class="toolbar-spacer"></span><span class="table-count">{{ $supplies->total() }} items</span>
        </form>

        @if ($supplies->isEmpty())
            <div class="empty-state"><span class="empty-icon">▤</span><strong>No supply items yet</strong><p>Add your first supply to begin tracking stock.</p>@if (auth()->user()->canUseButton('inventory.create'))<a class="button button-secondary" href="{{ route('supplies.create') }}">Add supply item</a>@endif</div>
        @else
            <div class="table-wrap">
                <table>
                    <thead><tr><th>SUPPLY</th><th>CATEGORY</th><th>ON HAND</th><th>MINIMUM</th><th>STOCK STATUS</th><th>ITEM STATUS</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($supplies as $supply)
                            @php($quantity = (float) ($supply->inventory?->quantity ?? 0))
                            <tr>
                                <td><div class="supply-cell"><span class="supply-avatar">{{ strtoupper(substr($supply->supply_name, 0, 1)) }}</span><div><strong>{{ $supply->supply_name }}</strong><small class="table-sub">{{ $supply->supply_code }}@if ($supply->unit) · {{ $supply->unit }}@endif</small></div></div></td>
                                <td>{{ $supply->category }}</td>
                                <td><strong>{{ number_format($quantity, 2) }}</strong>@if ($supply->unit) {{ $supply->unit }}@endif</td>
                                <td>{{ number_format((float) $supply->minimum_stock, 2) }}@if ($supply->unit) {{ $supply->unit }}@endif</td>
                                <td>@if ($quantity <= 0)<span class="status status-red">Out of stock</span>@elseif ($quantity <= (float) $supply->minimum_stock)<span class="status status-amber">Low stock</span>@else<span class="status status-green">In stock</span>@endif</td>
                                <td><span class="status {{ $supply->status === 'active' ? 'status-green' : 'status-neutral' }}">{{ str($supply->status)->title() }}</span></td>
                                <td>
                                    <div class="row-actions">
                                        @if (auth()->user()->canUseButton('inventory.edit'))<a class="table-action" href="{{ route('supplies.edit', $supply) }}">Edit</a>@endif
                                        @if (auth()->user()->canUseButton('inventory.stock_in'))<button class="table-action stock-open-button" type="button" data-open-dialog="stock-dialog-{{ $supply->id }}">Receive stock</button>@endif
                                    </div>
                                    @if (auth()->user()->canUseButton('inventory.stock_in'))
                                    <dialog class="stock-dialog" id="stock-dialog-{{ $supply->id }}" aria-labelledby="stock-dialog-title-{{ $supply->id }}">
                                        <form method="post" action="{{ route('supplies.stock-in', $supply) }}" class="stock-dialog-form">
                                            @csrf
                                            <div class="stock-dialog-heading">
                                                <h2 id="stock-dialog-title-{{ $supply->id }}">Receive {{ $supply->supply_name }}</h2>
                                                <button class="stock-dialog-close" type="button" data-close-dialog aria-label="Close dialog">×</button>
                                            </div>
                                            <label class="field"><span>Quantity @if ($supply->unit) ({{ $supply->unit }})@endif</span><input type="number" name="quantity" min="0.01" step="0.01" required></label>
                                            <label class="field"><span>Reference number</span><input name="reference_no" maxlength="100"></label>
                                            <label class="field"><span>Supplier</span><input name="supplier" maxlength="150"></label>
                                            <label class="field"><span>Notes</span><input name="remarks" maxlength="2000"></label>
                                            <div class="stock-dialog-actions">
                                                <button class="button button-secondary" type="button" data-close-dialog>Cancel</button>
                                                <button class="button button-primary" type="submit">Record stock receipt</button>
                                            </div>
                                        </form>
                                    </dialog>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $supplies->links() }}</div>
        @endif
    </section>
@endsection
