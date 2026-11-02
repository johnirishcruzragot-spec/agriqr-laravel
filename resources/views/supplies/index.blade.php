@extends('layouts.app')

@section('title', 'Inventory')
@section('section', 'Inventory')

@section('content')
    <div class="page-heading heading-with-action">
        <div><span class="eyebrow">SUPPLY MANAGEMENT</span><h1>Inventory</h1><p>Monitor stock levels and record incoming agricultural supplies.</p></div>
        <a class="button button-primary" href="{{ route('supplies.create') }}"><span>＋</span> Add supply</a>
    </div>

    <section class="panel">
        <form class="toolbar" method="get" action="{{ route('supplies.index') }}">
            <label class="search-field"><span aria-hidden="true">⌕</span><input type="search" name="search" value="{{ $search }}" placeholder="Search supply, code or category"></label>
            <button class="button button-secondary" type="submit">Search</button>
            @if ($search)<a class="text-link" href="{{ route('supplies.index') }}">Clear</a>@endif
            <span class="toolbar-spacer"></span><span class="table-count">{{ $supplies->total() }} items</span>
        </form>

        @if ($supplies->isEmpty())
            <div class="empty-state"><span class="empty-icon">▤</span><strong>No supply items yet</strong><p>Add your first supply to begin tracking stock.</p><a class="button button-secondary" href="{{ route('supplies.create') }}">Add supply item</a></div>
        @else
            <div class="table-wrap">
                <table>
                    <thead><tr><th>SUPPLY</th><th>CATEGORY</th><th>ON HAND</th><th>MINIMUM</th><th>STOCK STATUS</th><th>ITEM STATUS</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($supplies as $supply)
                            @php($quantity = (float) ($supply->inventory?->quantity ?? 0))
                            <tr>
                                <td><div class="supply-cell"><span class="supply-avatar">{{ strtoupper(substr($supply->supply_name, 0, 1)) }}</span><div><strong>{{ $supply->supply_name }}</strong><small class="table-sub">{{ $supply->supply_code }} · {{ $supply->unit }}</small></div></div></td>
                                <td>{{ $supply->category }}</td>
                                <td><strong>{{ number_format($quantity, 2) }}</strong> {{ $supply->unit }}</td>
                                <td>{{ number_format((float) $supply->minimum_stock, 2) }} {{ $supply->unit }}</td>
                                <td>@if ($quantity <= 0)<span class="status status-red">Out of stock</span>@elseif ($quantity <= (float) $supply->minimum_stock)<span class="status status-amber">Low stock</span>@else<span class="status status-green">In stock</span>@endif</td>
                                <td><span class="status {{ $supply->status === 'active' ? 'status-green' : 'status-neutral' }}">{{ str($supply->status)->title() }}</span></td>
                                <td><div class="row-actions"><a class="table-action" href="{{ route('supplies.edit', $supply) }}">Edit</a><details class="stock-details"><summary class="table-action">Receive stock</summary><form method="post" action="{{ route('supplies.stock-in', $supply) }}" class="stock-popover">@csrf<h3>Receive {{ $supply->supply_name }}</h3><label class="field"><span>Quantity ({{ $supply->unit }})</span><input type="number" name="quantity" min="0.01" step="0.01" required></label><label class="field"><span>Reference number</span><input name="reference_no" maxlength="100"></label><label class="field"><span>Supplier</span><input name="supplier" maxlength="150"></label><label class="field"><span>Notes</span><input name="remarks" maxlength="2000"></label><button class="button button-primary button-wide" type="submit">Record stock receipt</button></form></details></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $supplies->links() }}</div>
        @endif
    </section>
@endsection
