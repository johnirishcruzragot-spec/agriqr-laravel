@extends('layouts.app')

@section('title', 'Programs')
@section('section', 'Programs')

@section('content')
    <div class="page-heading heading-with-action">
        <div><span class="eyebrow">ASSISTANCE MANAGEMENT</span><h1>Subsidy programs</h1><p>Configure season-specific farmer groups, supply rates per hectare, and per-farmer maximum quantities.</p></div>
        @if (auth()->user()->canUseButton('programs.create'))
            <a class="button button-primary" href="{{ route('programs.create') }}"><span>＋</span> Create program</a>
        @endif
    </div>
    <section class="panel">
        @if ($programs->isEmpty())
            <div class="empty-state"><span class="empty-icon">◇</span><strong>No programs created</strong><p>Create a subsidy program and configure eligible groups and supply quantities.</p>@if (auth()->user()->canUseButton('programs.create'))<a class="button button-secondary" href="{{ route('programs.create') }}">Create program</a>@endif</div>
        @else
            <div class="table-wrap"><table><thead><tr><th>PROGRAM</th><th>TYPE</th><th>SEASON / PERIOD</th><th>ELIGIBLE GROUPS</th><th>SUPPLY RATES</th><th>STATUS</th><th>CREATED</th><th></th></tr></thead><tbody>
                @foreach ($programs as $program)
                    <tr><td><strong>{{ $program->program_name }}</strong><small class="table-sub">{{ $program->program_code }}</small></td><td>{{ str($program->subsidy_type)->replace('_', ' ')->title() }}</td><td>{{ $program->season?->name ?? $program->seasonGroups->first()?->season?->name ?? 'Season not configured' }}<small class="table-sub">{{ $program->start_date?->format('M j, Y') ?? '—' }} to {{ $program->end_date?->format('M j, Y') ?? '—' }}</small></td><td><div class="program-supplies">{{ $program->seasonGroups->map(fn ($group) => str($group->seed_type)->title())->unique()->join(', ') ?: '—' }}</div></td><td><div class="program-supplies">@forelse ($program->supplyGroupRates as $rate)<span>{{ $rate->supply->supply_name }} · {{ str($rate->seasonGroup->seed_type)->title() }} · {{ number_format((float) $rate->quantity_per_hectare, 4) }}@if ($rate->supply->unit) {{ $rate->supply->unit }}@endif/ha · max {{ number_format((float) $rate->maximum_quantity_per_farmer, 2) }}@if ($rate->supply->unit) {{ $rate->supply->unit }}@endif/farmer</span>@empty<span>Rates not configured</span>@endforelse</div></td><td><span class="status {{ $program->status === 'active' ? 'status-green' : ($program->status === 'draft' ? 'status-neutral' : 'status-amber') }}">{{ str($program->status)->title() }}</span></td><td>{{ $program->created_at->format('M j, Y') }}</td><td>@if (auth()->user()->canUseButton('programs.edit'))<a class="table-action" href="{{ route('programs.edit', $program) }}">Edit <span>→</span></a>@endif</td></tr>
                @endforeach
            </tbody></table></div>
            <div class="pagination-wrap">{{ $programs->links() }}</div>
        @endif
    </section>
@endsection
