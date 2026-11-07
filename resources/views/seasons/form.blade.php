@extends('layouts.app')

@section('title', $season->exists ? 'Edit season' : 'Create season')
@section('section', 'Seasons and groups')

@section('content')
    <div class="page-heading">
        @if (auth()->user()->canUseButton('seasons.list'))<a class="back-link" href="{{ route('seasons.index') }}">← Seasons and groups</a>@endif
        <span class="eyebrow">SEASON MANAGEMENT</span>
        <h1>{{ $season->exists ? 'Manage season' : 'Create a season' }}</h1>
        <p>Every season includes Hybrid and Inbred groups. Closing a season preserves its history and prevents new assignments and distributions.</p>
    </div>

    <form method="post" action="{{ $season->exists ? route('seasons.update', $season) : route('seasons.store') }}" class="form-layout">
        @csrf
        @if ($season->exists)
            @method('put')
        @endif
        <section class="panel form-panel">
            <div class="form-grid">
                <label class="field"><span>Season name <b>*</b></span><input name="name" value="{{ old('name', $season->name) }}" maxlength="100" required placeholder="e.g. Wet 2026"></label>
                @if ($season->exists)
                    <label class="field"><span>Season status <b>*</b></span><select name="status" required><option value="active" @selected(old('status', $season->status) === 'active')>Active</option><option value="closed" @selected(old('status', $season->status) === 'closed')>Closed</option></select></label>
                @else
                    <div class="detail-note"><span class="detail-label">GROUPS CREATED AUTOMATICALLY</span><p>Hybrid and Inbred groups will be available for farmer assignments and program eligibility.</p></div>
                @endif
            </div>
            @if ($season->exists)
                <div class="rsbsa-subsection">
                    <span class="detail-label">SEASON GROUPS</span>
                    <div class="detail-grid">
                        @foreach ($season->groups as $group)
                            <div><span class="detail-label">{{ str($group->seed_type)->upper() }}</span><strong>{{ $group->current_assignments_count }} current farmer assignments</strong></div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
        <div class="form-actions">@if (auth()->user()->canUseButton('seasons.list'))<a class="button button-secondary" href="{{ route('seasons.index') }}">Cancel</a>@endif @if (auth()->user()->canUseButton($season->exists ? 'seasons.edit' : 'seasons.create'))<button class="button button-primary" type="submit">{{ $season->exists ? 'Save season' : 'Create season' }}</button>@endif</div>
    </form>
@endsection
