@extends('layouts.app')

@section('title', 'Audit log')
@section('section', 'Audit log')

@section('content')
    <div class="page-heading">
        <span class="eyebrow">SYSTEM ACCOUNTABILITY</span>
        <h1>Audit log</h1>
        <p>Review key changes and distribution activity across AgriQR.</p>
    </div>
    <section class="panel">
        <form class="toolbar" method="get" action="{{ route('activity.index') }}">
            <label class="field"><span>Filter by module</span><select class="select-control" name="module"><option value="">All activity</option>@foreach ($modules as $option)<option value="{{ $option }}" @selected($module === $option)>{{ str($option)->title() }}</option>@endforeach</select></label>
            @if (auth()->user()->canUseButton('activity.filter'))
                <button class="button button-secondary" type="submit">Apply filter</button>
                @if ($module)<a class="text-link" href="{{ route('activity.index') }}">Clear</a>@endif
            @endif
            <span class="toolbar-spacer"></span><span class="table-count">{{ $activities->total() }} events</span>
        </form>
        @if ($activities->isEmpty())
            <div class="empty-state"><span class="empty-icon">◷</span><strong>No activity recorded</strong><p>Important profile, inventory and distribution actions will be listed here.</p></div>
        @else
            <div class="table-wrap"><table><thead><tr><th>ACTIVITY</th><th>MODULE</th><th>RECORD</th><th>PERFORMED BY</th><th>IP ADDRESS</th><th>DATE</th></tr></thead><tbody>
                @foreach ($activities as $activity)
                    <tr><td><strong>{{ str($activity->action)->replace('_', ' ')->title() }}</strong><small class="table-sub">{{ $activity->description }}</small></td><td><span class="status status-neutral">{{ str($activity->module)->title() }}</span></td><td>{{ $activity->record_id ? '#'.$activity->record_id : '—' }}</td><td>{{ $activity->user?->name ?? 'Former user' }}</td><td>{{ $activity->ip_address ?: '—' }}</td><td>{{ $activity->created_at->format('M j, Y · g:i A') }}</td></tr>
                @endforeach
            </tbody></table></div>
            <div class="pagination-wrap">{{ $activities->links() }}</div>
        @endif
    </section>
@endsection
