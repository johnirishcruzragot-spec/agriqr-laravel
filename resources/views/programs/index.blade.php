@extends('layouts.app')

@section('title', 'Programs')
@section('section', 'Programs')

@section('content')
    <div class="page-heading heading-with-action">
        <div><span class="eyebrow">ASSISTANCE MANAGEMENT</span><h1>Subsidy programs</h1><p>Plan assistance programs, eligibility rules and approved supply quantities.</p></div>
        <a class="button button-primary" href="{{ route('programs.create') }}"><span>＋</span> Create program</a>
    </div>
    <section class="panel">
        @if ($programs->isEmpty())
            <div class="empty-state"><span class="empty-icon">◇</span><strong>No programs created</strong><p>Create a subsidy program and attach the supplies available for distribution.</p><a class="button button-secondary" href="{{ route('programs.create') }}">Create program</a></div>
        @else
            <div class="table-wrap"><table><thead><tr><th>PROGRAM</th><th>PERIOD</th><th>SUPPLIES</th><th>STATUS</th><th>CREATED</th><th></th></tr></thead><tbody>
                @foreach ($programs as $program)
                    <tr><td><strong>{{ $program->program_name }}</strong><small class="table-sub">{{ $program->program_code }}</small></td><td>{{ $program->start_date?->format('M j, Y') ?? '—' }}<small class="table-sub">to {{ $program->end_date?->format('M j, Y') ?? 'Open ended' }}</small></td><td><div class="program-supplies">{{ $program->supplies->take(2)->pluck('supply_name')->join(', ') }}@if ($program->supplies->count() > 2)<span> +{{ $program->supplies->count() - 2 }}</span>@endif</div><small class="table-sub">{{ $program->supplies_count }} items</small></td><td><span class="status {{ $program->status === 'active' ? 'status-green' : ($program->status === 'draft' ? 'status-neutral' : 'status-amber') }}">{{ str($program->status)->title() }}</span></td><td>{{ $program->created_at->format('M j, Y') }}</td><td><a class="table-action" href="{{ route('programs.edit', $program) }}">Edit <span>→</span></a></td></tr>
                @endforeach
            </tbody></table></div>
            <div class="pagination-wrap">{{ $programs->links() }}</div>
        @endif
    </section>
@endsection
